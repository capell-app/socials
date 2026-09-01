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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Override;
use ValueError;

/** @property Schema $form */
final class SocialsPage extends Page implements HasForms
{
    use InteractsWithForms;

    public const string VIEW_PERMISSION = 'View:SocialsPage';

    private const string CUSTOM_NETWORK_KEY = '__custom';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * The site whose stored configuration is currently loaded into the form.
     */
    public ?string $activeSiteId = null;

    /** Display name of the active site, for the unsaved-changes prompt. */
    public string $activeSiteName = '';

    /**
     * A site the editor is trying to switch to while the form still has
     * unsaved changes; resolved through the Save / Discard / Stay prompt.
     */
    public ?string $pendingSiteId = null;

    public bool $showSiteSwitchPrompt = false;

    /** One of: idle, saved, error. */
    public string $saveState = 'idle';

    /**
     * Fingerprint of the last persisted (or freshly loaded) form state, used
     * to detect unsaved changes without comparing Filament's internal keys.
     *
     * @var array<string, mixed>
     */
    public array $persistedState = [];

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

        $this->loadSite($site instanceof Site ? (string) SocialSiteId::from($site) : null);
    }

    public function updatedDataSiteId(): void
    {
        $siteId = SocialSiteId::fromInput($this->data['site_id'] ?? null);
        $target = $siteId === null ? null : (string) $siteId;

        if ($target === null || $target === $this->activeSiteId) {
            return;
        }

        if ($this->isDirty()) {
            $this->pendingSiteId = $target;
            $this->showSiteSwitchPrompt = true;

            return;
        }

        $this->loadSite($target);
    }

    public function updated(string $property): void
    {
        if ($property !== 'data.site_id' && str_starts_with($property, 'data.')) {
            $this->saveState = 'idle';
        }
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
                Section::make(__('capell-socials::socials.admin.profiles'))
                    ->description(__('capell-socials::socials.admin.profiles_hint'))
                    ->schema([
                        Repeater::make('profiles')
                            ->hiddenLabel()
                            ->addActionLabel(__('capell-socials::socials.admin.add_profile'))
                            ->defaultItems(0)
                            ->orderable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->collapsed()
                            ->itemLabel(fn (array $state): string => $this->profileSummary($state))
                            ->live()
                            ->schema([
                                Select::make('network_key')
                                    ->label(__('capell-socials::socials.admin.network'))
                                    ->options($this->networkOptions())
                                    ->required()
                                    ->live()
                                    ->disableOptionWhen(static function (string $value, Get $get): bool {
                                        if ($value === self::CUSTOM_NETWORK_KEY || $value === $get('network_key')) {
                                            return false;
                                        }

                                        return collect(self::repeaterRows($get('../../')))
                                            ->pluck('network_key')
                                            ->contains($value);
                                    })
                                    ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                        if (! is_string($value) || $value === self::CUSTOM_NETWORK_KEY) {
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
                                    ->extraInputAttributes(['data-capell-socials-profile-value' => true])
                                    ->helperText(fn (Get $get): string => $this->profileValueHint($get('network_key')))
                                    ->required()
                                    ->live()
                                    ->maxLength(2048)
                                    ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                        if (! is_string($value) || trim($value) === '') {
                                            return;
                                        }

                                        $networkKey = $get('network_key');
                                        $profileValue = trim($value);

                                        if (! is_string($networkKey) || $networkKey === self::CUSTOM_NETWORK_KEY) {
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
                                Toggle::make('show_public_label')
                                    ->label(__('capell-socials::socials.admin.override_label'))
                                    ->extraAttributes(['data-capell-socials-profile-label-toggle' => true])
                                    ->live()
                                    ->default(false)
                                    ->visible(fn (Get $get): bool => $get('network_key') !== self::CUSTOM_NETWORK_KEY),
                                TextInput::make('custom_label')
                                    ->label(__('capell-socials::socials.admin.public_label'))
                                    ->extraInputAttributes(['data-capell-socials-profile-custom-label' => true])
                                    ->helperText(fn (Get $get): string => $get('network_key') === self::CUSTOM_NETWORK_KEY
                                        ? __('capell-socials::socials.admin.public_label_custom_hint')
                                        : __('capell-socials::socials.admin.public_label_override_hint'))
                                    ->required(fn (Get $get): bool => $get('network_key') === self::CUSTOM_NETWORK_KEY)
                                    ->visible(fn (Get $get): bool => $get('network_key') === self::CUSTOM_NETWORK_KEY || $get('show_public_label') === true)
                                    ->live()
                                    ->maxLength(120),
                                Toggle::make('is_enabled')
                                    ->label(__('capell-socials::socials.admin.enabled'))
                                    ->helperText(__('capell-socials::socials.admin.enabled_hint'))
                                    ->live()
                                    ->default(true),
                            ])
                            ->columns(1),
                    ]),
                Grid::make()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Group::make()->schema([
                            Section::make(__('capell-socials::socials.admin.follow_appearance'))
                                ->schema([
                                    Select::make('follow_label_style')
                                        ->label(__('capell-socials::socials.admin.label_style'))
                                        ->options($this->labelStyleOptions())
                                        ->live()
                                        ->required(),
                                    Toggle::make('follow_open_in_new_tab')
                                        ->label(__('capell-socials::socials.admin.open_in_new_tab'))
                                        ->live(),
                                ]),
                            Section::make(__('capell-socials::socials.admin.sharing'))
                                ->schema([
                                    Placeholder::make('recommended_share_networks')
                                        ->label(__('capell-socials::socials.admin.recommended_share_label'))
                                        ->helperText(__('capell-socials::socials.admin.recommended_share_hint'))
                                        ->content(fn (): string => $this->recommendedShareSummary()),
                                    Toggle::make('share_networks_customised')
                                        ->label(__('capell-socials::socials.admin.customise_sharing'))
                                        ->live(),
                                    Select::make('share_network_keys')
                                        ->label(__('capell-socials::socials.admin.share_networks'))
                                        ->multiple()
                                        ->options($this->shareNetworkOptions())
                                        ->live()
                                        ->visible(fn (Get $get): bool => (bool) $get('share_networks_customised')),
                                    Select::make('share_label_style')
                                        ->label(__('capell-socials::socials.admin.label_style'))
                                        ->options($this->labelStyleOptions())
                                        ->live()
                                        ->required(fn (Get $get): bool => (bool) $get('share_networks_customised'))
                                        ->visible(fn (Get $get): bool => (bool) $get('share_networks_customised')),
                                    Toggle::make('share_open_in_new_tab')
                                        ->label(__('capell-socials::socials.admin.open_in_new_tab'))
                                        ->live()
                                        ->visible(fn (Get $get): bool => (bool) $get('share_networks_customised')),
                                ]),
                        ]),
                        Group::make()->schema([
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
                    ]),
            ]);
    }

    public function save(): void
    {
        try {
            $state = $this->form->getState();
            $state['site_id'] = $this->activeSiteId;
            $site = $this->siteFromState($state);

            SaveSocialSiteConfigurationAction::run(
                $site,
                $this->profilesFromState($state),
                $this->preferencesFromState($state),
            );
        } catch (ValidationException $exception) {
            $this->saveState = 'error';

            throw $exception;
        } catch (InvalidArgumentException|ValueError) {
            $this->saveState = 'error';
            $this->addError('data.profiles', __('capell-socials::socials.admin.validation.save_failed'));

            Notification::make('capell_socials_save_failed')
                ->danger()
                ->title(__('capell-socials::socials.admin.validation.save_failed'))
                ->send();

            return;
        }

        $this->loadSite((string) SocialSiteId::from($site));
        $this->saveState = 'saved';

        Notification::make('capell_socials_saved')
            ->success()
            ->title(__('capell-socials::socials.admin.saved'))
            ->send();
    }

    public function stayOnCurrentSite(): void
    {
        $this->data['site_id'] = $this->activeSiteId;
        $this->pendingSiteId = null;
        $this->showSiteSwitchPrompt = false;
    }

    public function discardAndSwitchSite(): void
    {
        if ($this->pendingSiteId !== null) {
            $this->loadSite($this->pendingSiteId);
        }
    }

    public function saveAndSwitchSite(): void
    {
        $pendingSiteId = $this->pendingSiteId;
        $this->data['site_id'] = $this->activeSiteId;

        $this->save();

        if ($this->saveState !== 'error' && $pendingSiteId !== null) {
            $this->loadSite($pendingSiteId);
        }
    }

    public function isDirty(): bool
    {
        return $this->stateFingerprint($this->data) !== $this->persistedState;
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
        $siteId = SocialSiteId::fromInput($this->activeSiteId);

        if ($siteId === null) {
            return null;
        }

        return SiteScope::applyForCurrentActor(Site::query(), 'id', denyWhenMissingActor: true)->find($siteId);
    }

    private function loadSite(?string $siteId): void
    {
        $resolvedSiteId = SocialSiteId::fromInput($siteId);
        $site = $resolvedSiteId === null
            ? null
            : SiteScope::applyForCurrentActor(Site::query(), 'id', denyWhenMissingActor: true)->find($resolvedSiteId);

        $this->activeSiteId = $site instanceof Site ? (string) SocialSiteId::from($site) : null;
        $this->activeSiteName = $site instanceof Site && is_string($site->name) ? $site->name : '';
        $this->pendingSiteId = null;
        $this->showSiteSwitchPrompt = false;
        $this->saveState = 'idle';

        $this->fillForSite($site instanceof Site ? $site : null);

        $this->persistedState = $this->stateFingerprint($this->data);
    }

    private function fillForSite(?Site $site): void
    {
        if (! $site instanceof Site) {
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
                'network_key' => $profile->network_key ?? self::CUSTOM_NETWORK_KEY,
                'profile_value' => $profile->profile_value,
                'custom_label' => $profile->custom_label,
                'show_public_label' => $profile->network_key !== null && $profile->custom_label !== null,
                'is_enabled' => $profile->is_enabled,
            ])
            ->all();

        $this->data = [
            'site_id' => (string) SocialSiteId::from($site),
            'profiles' => $profiles,
            'follow_label_style' => $preferences->follow_label_style->value,
            'follow_open_in_new_tab' => $preferences->follow_open_in_new_tab,
            'share_networks_customised' => $preferences->share_networks_customised,
            'share_network_keys' => $preferences->share_network_keys,
            'share_label_style' => $preferences->share_label_style->value,
            'share_open_in_new_tab' => $preferences->share_open_in_new_tab,
        ];
        $this->form->fill($this->data);
    }

    /**
     * A stable projection of the editable configuration, deliberately excluding
     * the site selector: switching site is a navigation, not an unsaved edit.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stateFingerprint(array $data): array
    {
        $profiles = array_map(
            static fn (mixed $profile): array => is_array($profile) ? [
                'network_key' => $profile['network_key'] ?? null,
                'profile_value' => is_string($profile['profile_value'] ?? null) ? trim($profile['profile_value']) : '',
                'custom_label' => is_string($profile['custom_label'] ?? null) ? trim($profile['custom_label']) : null,
                'show_public_label' => (bool) ($profile['show_public_label'] ?? false),
                'is_enabled' => (bool) ($profile['is_enabled'] ?? false),
            ] : [],
            array_values(Arr::wrap($data['profiles'] ?? [])),
        );

        return [
            'profiles' => $profiles,
            'follow_label_style' => $data['follow_label_style'] ?? null,
            'follow_open_in_new_tab' => (bool) ($data['follow_open_in_new_tab'] ?? false),
            'share_networks_customised' => (bool) ($data['share_networks_customised'] ?? false),
            'share_network_keys' => array_values(Arr::wrap($data['share_network_keys'] ?? [])),
            'share_label_style' => $data['share_label_style'] ?? null,
            'share_open_in_new_tab' => (bool) ($data['share_open_in_new_tab'] ?? false),
        ];
    }

    private function profileSummary(mixed $state): string
    {
        $state = is_array($state) ? $state : [];
        $networkKey = is_string($state['network_key'] ?? null) ? $state['network_key'] : null;
        $profileValue = is_string($state['profile_value'] ?? null) ? trim($state['profile_value']) : '';
        $status = ($state['is_enabled'] ?? false)
            ? __('capell-socials::socials.admin.summary.enabled')
            : __('capell-socials::socials.admin.summary.disabled');

        if ($networkKey === null || $networkKey === '' || $profileValue === '') {
            return __('capell-socials::socials.admin.summary.incomplete');
        }

        if ($networkKey === self::CUSTOM_NETWORK_KEY) {
            $label = is_string($state['custom_label'] ?? null) ? trim($state['custom_label']) : '';

            return sprintf(
                '%s · %s · %s',
                $label !== '' ? $label : __('capell-socials::socials.admin.custom_link'),
                $this->hostOf($profileValue),
                $status,
            );
        }

        $network = resolve(SocialNetworkRegistry::class)->get($networkKey);

        if ($network === null) {
            return __('capell-socials::socials.admin.summary.incomplete');
        }

        try {
            $destination = $network->normalizer->normalize($profileValue)->url;
        } catch (InvalidArgumentException) {
            $destination = $profileValue;
        }

        return sprintf('%s · %s · %s', $network->resolveLabel(), $destination, $status);
    }

    private function hostOf(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $url;
    }

    private function profileValueHint(mixed $networkKey): string
    {
        if (! is_string($networkKey) || $networkKey === self::CUSTOM_NETWORK_KEY) {
            return __('capell-socials::socials.admin.hints.custom_url');
        }

        $network = resolve(SocialNetworkRegistry::class)->get($networkKey);

        return $network === null
            ? __('capell-socials::socials.admin.hints.generic')
            : __('capell-socials::socials.admin.hints.network', ['network' => $network->resolveLabel()]);
    }

    private function recommendedShareSummary(): string
    {
        $registry = resolve(SocialNetworkRegistry::class);

        $labels = array_map(
            static fn (string $key): string => $registry->get($key)?->resolveLabel() ?? $key,
            SocialSitePreferencesData::recommendedShareNetworkKeys($registry),
        );

        return implode(', ', $labels);
    }

    /** @return array<int|string, string> */
    private function siteOptions(): array
    {
        return $this->sites()->mapWithKeys(static fn (Site $site): array => [(string) SocialSiteId::from($site) => $site->name])->all();
    }

    /** @return array<string, string> */
    private function networkOptions(): array
    {
        return [self::CUSTOM_NETWORK_KEY => __('capell-socials::socials.admin.custom_link')]
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
        $shareNetworksCustomised = (bool) ($state['share_networks_customised'] ?? false);
        $shareNetworkKeys = $shareNetworksCustomised ? ($state['share_network_keys'] ?? []) : [];

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
            shareNetworksCustomised: $shareNetworksCustomised,
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
                $isCustomProfile = $networkKey === self::CUSTOM_NETWORK_KEY;
                $showPublicLabel = (bool) ($profile['show_public_label'] ?? false);
                $customLabel = is_string($profile['custom_label'] ?? null) ? $profile['custom_label'] : null;

                if (! $isCustomProfile && ! $showPublicLabel) {
                    $customLabel = null;
                }

                return new SocialProfileConfigurationData(
                    networkKey: $isCustomProfile ? null : (is_string($networkKey) ? $networkKey : null),
                    profileValue: is_string($profile['profile_value'] ?? null) ? $profile['profile_value'] : '',
                    customLabel: $customLabel,
                    isEnabled: (bool) ($profile['is_enabled'] ?? false),
                );
            })
            ->values()
            ->all());
    }
}
