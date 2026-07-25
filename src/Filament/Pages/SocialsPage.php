<?php

declare(strict_types=1);

namespace Capell\Socials\Filament\Pages;

use BackedEnum;
use Capell\Admin\Support\SiteScope;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page as ContentPage;
use Capell\Core\Models\Site;
use Capell\Frontend\Actions\ResolvePageCanonicalUrlAction;
use Capell\Socials\Actions\BuildFollowSocialRenderDataAction;
use Capell\Socials\Actions\BuildShareSocialRenderDataAction;
use Capell\Socials\Actions\SaveSocialSiteConfigurationAction;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\SharePageContextData;
use Capell\Socials\Data\SocialFollowRenderData;
use Capell\Socials\Data\SocialFollowWidgetConfigData;
use Capell\Socials\Data\SocialNetworkDefinitionData;
use Capell\Socials\Data\SocialProfileConfigurationData;
use Capell\Socials\Data\SocialShareRenderData;
use Capell\Socials\Data\SocialShareWidgetConfigData;
use Capell\Socials\Data\SocialSitePreferencesData;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Enums\SocialNetworkCapability;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\HttpUrlValidator;
use Capell\Socials\Support\SocialSiteId;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Override;
use ValueError;

/** @property Schema $form */
final class SocialsPage extends Page implements HasForms
{
    use InteractsWithForms;

    public const string VIEW_PERMISSION = 'View:SocialsPage';

    /** @var array<string, mixed> */
    public array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static ?string $slug = 'socials';

    protected string $view = 'capell-socials::filament.pages.socials';

    #[Override]
    public static function canAccess(): bool
    {
        return auth()->user()?->can(self::VIEW_PERMISSION) ?? false;
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('capell-socials::socials.admin.navigation');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return __('capell-admin::navigation.group_growth');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('capell-socials::socials.admin.title');
    }

    #[Override]
    public function getSubheading(): string
    {
        return __('capell-socials::socials.admin.subheading');
    }

    public function mount(): void
    {
        $site = $this->sites()->first();

        $this->fillForSite($site instanceof Site ? $site : null);
    }

    public function updatedDataSiteId(): void
    {
        $site = $this->selectedSite();

        $this->fillForSite($site);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('site_id')
                    ->label(__('capell-socials::socials.admin.site'))
                    ->options($this->siteOptions())
                    ->required()
                    ->live(),
                Tabs::make('socials')
                    ->tabs([
                        Tab::make(__('capell-socials::socials.admin.profiles'))
                            ->schema([
                                Repeater::make('profiles')
                                    ->label(__('capell-socials::socials.admin.profiles'))
                                    ->defaultItems(0)
                                    ->orderable()
                                    ->live()
                                    ->schema([
                                        Select::make('network_key')
                                            ->label(__('capell-socials::socials.admin.network'))
                                            ->options($this->networkOptions())
                                            ->required()
                                            ->live()
                                            ->disableOptionWhen(static function (string $value, Get $get): bool {
                                                if ($value === '__custom' || $value === $get('network_key')) {
                                                    return false;
                                                }

                                                return collect(self::repeaterRows($get('../../')))
                                                    ->pluck('network_key')
                                                    ->contains($value);
                                            })
                                            ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                                if (! is_string($value) || $value === '__custom') {
                                                    return;
                                                }

                                                $duplicates = collect(self::repeaterRows($get('../../')))
                                                    ->pluck('network_key')
                                                    ->filter(static fn (mixed $networkKey): bool => $networkKey === $value)
                                                    ->count();

                                                if ($duplicates > 1) {
                                                    $fail(__('capell-socials::socials.admin.validation.duplicate_network'));
                                                }
                                            }),
                                        TextInput::make('profile_value')
                                            ->label(__('capell-socials::socials.admin.profile_value'))
                                            ->required()
                                            ->live()
                                            ->maxLength(2048)
                                            ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                                if (! is_string($value) || trim($value) === '') {
                                                    return;
                                                }

                                                $networkKey = $get('network_key');
                                                $profileValue = trim($value);

                                                if (! is_string($networkKey) || $networkKey === '__custom') {
                                                    try {
                                                        resolve(HttpUrlValidator::class)->validate($profileValue);
                                                    } catch (InvalidArgumentException) {
                                                        $fail(__('capell-socials::socials.admin.validation.invalid_custom_url'));
                                                    }

                                                    return;
                                                }

                                                $network = resolve(SocialNetworkRegistry::class)->get($networkKey);

                                                if ($network === null) {
                                                    $fail(__('capell-socials::socials.admin.validation.unknown_network'));

                                                    return;
                                                }

                                                try {
                                                    $network->validator->validate($profileValue);
                                                    $network->normalizer->normalize($profileValue);
                                                } catch (InvalidArgumentException) {
                                                    $fail(__('capell-socials::socials.admin.validation.invalid_profile_value', ['network' => $network->resolveLabel()]));
                                                }
                                            }),
                                        TextInput::make('custom_label')
                                            ->label(__('capell-socials::socials.admin.public_label'))
                                            ->required(fn (Get $get): bool => $get('network_key') === '__custom')
                                            ->live()
                                            ->maxLength(120),
                                        Toggle::make('is_enabled')
                                            ->label(__('capell-socials::socials.admin.enabled'))
                                            ->live()
                                            ->default(true),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make(__('capell-socials::socials.admin.defaults'))
                            ->schema([
                                Section::make(__('capell-socials::socials.admin.follow_defaults'))
                                    ->schema([
                                        Select::make('follow_label_style')
                                            ->label(__('capell-socials::socials.admin.label_style'))
                                            ->options($this->labelStyleOptions())
                                            ->live()
                                            ->required(),
                                        Toggle::make('follow_open_in_new_tab')
                                            ->label(__('capell-socials::socials.admin.open_in_new_tab'))
                                            ->live(),
                                    ])
                                    ->columns(2),
                                Section::make(__('capell-socials::socials.admin.share_defaults'))
                                    ->schema([
                                        Select::make('share_network_keys')
                                            ->label(__('capell-socials::socials.admin.share_networks'))
                                            ->multiple()
                                            ->options($this->shareNetworkOptions())
                                            ->live(),
                                        Select::make('share_label_style')
                                            ->label(__('capell-socials::socials.admin.label_style'))
                                            ->options($this->labelStyleOptions())
                                            ->live()
                                            ->required(),
                                        Toggle::make('share_open_in_new_tab')
                                            ->label(__('capell-socials::socials.admin.open_in_new_tab'))
                                            ->live(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make(__('capell-socials::socials.admin.preview'))
                            ->schema([
                                Section::make(__('capell-socials::socials.admin.follow_preview'))
                                    ->schema([
                                        ViewField::make('follow_preview')
                                            ->view('capell-socials::filament.partials.follow-preview')
                                            ->viewData(fn (): array => ['renderData' => $this->getFollowPreviewProperty()]),
                                    ]),
                                Section::make(__('capell-socials::socials.admin.share_preview'))
                                    ->schema([
                                        ViewField::make('share_preview')
                                            ->view('capell-socials::filament.partials.share-preview')
                                            ->viewData(fn (): array => ['renderData' => $this->getSharePreviewProperty()]),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        try {
            $site = $this->siteFromState($state);

            SaveSocialSiteConfigurationAction::run(
                $site,
                $this->profilesFromState($state),
                $this->preferencesFromState($state),
            );
        } catch (InvalidArgumentException|ValueError) {
            $this->addError('data.profiles', __('capell-socials::socials.admin.validation.save_failed'));

            Notification::make('capell_socials_save_failed')
                ->danger()
                ->title(__('capell-socials::socials.admin.validation.save_failed'))
                ->send();

            return;
        }

        $this->fillForSite($site);

        Notification::make('capell_socials_saved')
            ->success()
            ->title(__('capell-socials::socials.admin.saved'))
            ->send();
    }

    public function getFollowPreviewProperty(): SocialFollowRenderData
    {
        $site = $this->selectedSite();

        if (! $site instanceof Site) {
            return new SocialFollowRenderData(null, SocialLabelStyle::Icons, false, 'start', []);
        }

        try {
            return resolve(BuildFollowSocialRenderDataAction::class)->preview(
                new SocialFollowWidgetConfigData(null, null, null, 'start', null, []),
                $this->preferencesFromState($this->data),
                $this->profilesFromState($this->data),
            );
        } catch (InvalidArgumentException|ValueError) {
            return new SocialFollowRenderData(null, SocialLabelStyle::Icons, false, 'start', []);
        }
    }

    public function getSharePreviewProperty(): ?SocialShareRenderData
    {
        $site = $this->selectedSite();

        if (! $site instanceof Site) {
            return null;
        }

        try {
            $site->loadMissing('language');
            $language = $site->language;

            if (! $language instanceof Language) {
                return null;
            }

            $homepage = ContentPage::getSiteHomePage($site, $language);

            if (! $homepage instanceof ContentPage) {
                return null;
            }

            $homepage->loadMissing(['canonicalPage.pageUrls.siteDomain', 'pageUrl.siteDomain', 'pageUrls.siteDomain']);
            $canonicalUrl = ResolvePageCanonicalUrlAction::run($homepage, $language);
            $title = trim(strip_tags((string) $homepage->title));

            if ($canonicalUrl === null || $title === '') {
                return null;
            }

            return resolve(BuildShareSocialRenderDataAction::class)->preview(
                new SharePageContextData($canonicalUrl, $title, $language->code),
                new SocialShareWidgetConfigData(null, null, null, 'start', null),
                $this->preferencesFromState($this->data),
            );
        } catch (InvalidArgumentException|ValueError) {
            return null;
        }
    }

    /** @return array<array-key, mixed> */
    private static function repeaterRows(mixed $state): array
    {
        return is_array($state) ? $state : [];
    }

    /** @return Collection<int, Site> */
    private function sites(): Collection
    {
        return SiteScope::applyForCurrentActor(Site::query(), 'id', denyWhenMissingActor: true)
            ->orderBy('name')
            ->get();
    }

    private function selectedSite(): ?Site
    {
        $siteId = SocialSiteId::fromInput($this->data['site_id'] ?? null);

        if ($siteId === null) {
            return null;
        }

        return SiteScope::applyForCurrentActor(Site::query(), 'id', denyWhenMissingActor: true)->find($siteId);
    }

    private function fillForSite(?Site $site): void
    {
        if ($site === null) {
            $this->data = [];
            $this->form->fill($this->data);

            return;
        }

        $preferences = SocialSitePreferences::query()->firstWhere('site_id', $site->getKey()) ?? new SocialSitePreferences;
        $profiles = SocialProfile::query()
            ->where('site_id', $site->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(static fn (SocialProfile $profile): array => [
                'network_key' => $profile->network_key ?? '__custom',
                'profile_value' => $profile->profile_value,
                'custom_label' => $profile->custom_label,
                'is_enabled' => $profile->is_enabled,
            ])
            ->all();

        $this->data = [
            'site_id' => $site->getKey(),
            'profiles' => $profiles,
            'follow_label_style' => $preferences->follow_label_style->value,
            'follow_open_in_new_tab' => $preferences->follow_open_in_new_tab,
            'share_network_keys' => $preferences->share_network_keys,
            'share_label_style' => $preferences->share_label_style->value,
            'share_open_in_new_tab' => $preferences->share_open_in_new_tab,
        ];
        $this->form->fill($this->data);
    }

    /** @return array<int|string, string> */
    private function siteOptions(): array
    {
        return $this->sites()->mapWithKeys(static fn (Site $site): array => [(string) SocialSiteId::from($site) => $site->name])->all();
    }

    /** @return array<string, string> */
    private function networkOptions(): array
    {
        return ['__custom' => __('capell-socials::socials.admin.custom_link')]
            + collect(resolve(SocialNetworkRegistry::class)->all())
                ->mapWithKeys(static fn (SocialNetworkDefinitionData $network): array => [$network->key => $network->resolveLabel()])
                ->all();
    }

    /** @return array<string, string> */
    private function shareNetworkOptions(): array
    {
        return collect(resolve(SocialNetworkRegistry::class)->all())
            ->filter(static fn (SocialNetworkDefinitionData $network): bool => $network->supports(SocialNetworkCapability::Share))
            ->mapWithKeys(static fn (SocialNetworkDefinitionData $network): array => [$network->key => $network->resolveLabel()])
            ->all();
    }

    /** @return array<string, string> */
    private function labelStyleOptions(): array
    {
        return [
            SocialLabelStyle::Icons->value => __('capell-socials::socials.widget.icons'),
            SocialLabelStyle::Labels->value => __('capell-socials::socials.widget.labels'),
            SocialLabelStyle::IconsAndLabels->value => __('capell-socials::socials.widget.icons_and_labels'),
        ];
    }

    /** @param array<string, mixed> $state */
    private function preferencesFromState(array $state): SocialSitePreferencesData
    {
        $followLabelStyle = $state['follow_label_style'] ?? SocialLabelStyle::Icons->value;
        $shareLabelStyle = $state['share_label_style'] ?? SocialLabelStyle::Icons->value;
        $shareNetworkKeys = $state['share_network_keys'] ?? [];

        if (! is_string($followLabelStyle) || ! is_string($shareLabelStyle) || ! is_array($shareNetworkKeys)) {
            throw new InvalidArgumentException('Social preferences are invalid.');
        }

        $normalizedShareNetworkKeys = [];

        foreach ($shareNetworkKeys as $shareNetworkKey) {
            if (! is_string($shareNetworkKey)) {
                throw new InvalidArgumentException('Social share networks are invalid.');
            }

            $normalizedShareNetworkKeys[] = $shareNetworkKey;
        }

        return new SocialSitePreferencesData(
            resolve(SocialNetworkRegistry::class),
            SocialLabelStyle::from($followLabelStyle),
            (bool) ($state['follow_open_in_new_tab'] ?? false),
            $normalizedShareNetworkKeys,
            SocialLabelStyle::from($shareLabelStyle),
            (bool) ($state['share_open_in_new_tab'] ?? false),
        );
    }

    /** @param array<string, mixed> $state */
    private function siteFromState(array $state): Site
    {
        $siteId = SocialSiteId::fromInput($state['site_id'] ?? null);

        if ($siteId === null) {
            throw new InvalidArgumentException('A valid site is required.');
        }

        $site = SiteScope::applyForCurrentActor(Site::query(), 'id', denyWhenMissingActor: true)->find($siteId);

        if (! $site instanceof Site) {
            throw new InvalidArgumentException('The selected site is not available.');
        }

        return $site;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<SocialProfileConfigurationData>
     */
    private function profilesFromState(array $state): array
    {
        return array_values(collect(Arr::wrap($state['profiles'] ?? []))
            ->map(static function (mixed $profile): SocialProfileConfigurationData {
                if (! is_array($profile)) {
                    throw new InvalidArgumentException('Social profile data is invalid.');
                }

                $networkKey = $profile['network_key'] ?? null;

                return new SocialProfileConfigurationData(
                    networkKey: $networkKey === '__custom' ? null : (is_string($networkKey) ? $networkKey : null),
                    profileValue: is_string($profile['profile_value'] ?? null) ? $profile['profile_value'] : '',
                    customLabel: is_string($profile['custom_label'] ?? null) ? $profile['custom_label'] : null,
                    isEnabled: (bool) ($profile['is_enabled'] ?? false),
                );
            })
            ->values()
            ->all());
    }
}
