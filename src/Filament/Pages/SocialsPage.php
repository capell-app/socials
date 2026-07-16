<?php

declare(strict_types=1);

namespace Capell\Socials\Filament\Pages;

use BackedEnum;
use Capell\Admin\Support\SiteScope;
use Capell\Core\Models\Site;
use Capell\Socials\Actions\SaveSocialSiteConfigurationAction;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\SocialProfileConfigurationData;
use Capell\Socials\Data\SocialSitePreferencesData;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Enums\SocialNetworkCapability;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Override;

/** @property Schema $form */
final class SocialsPage extends Page implements HasForms
{
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static ?string $slug = 'socials';

    protected string $view = 'capell-socials::filament.pages.socials';

    #[Override]
    public static function canAccess(): bool
    {
        return auth()->user()?->can('Manage:Socials') ?? false;
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
    public function getTitle(): string|Htmlable
    {
        return __('capell-socials::socials.admin.title');
    }

    #[Override]
    public function getSubheading(): ?string
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
                                    ->schema([
                                        Select::make('network_key')
                                            ->label(__('capell-socials::socials.admin.network'))
                                            ->options($this->networkOptions())
                                            ->required()
                                            ->live(),
                                        TextInput::make('profile_value')
                                            ->label(__('capell-socials::socials.admin.profile_value'))
                                            ->required()
                                            ->maxLength(2048),
                                        TextInput::make('custom_label')
                                            ->label(__('capell-socials::socials.admin.public_label'))
                                            ->required(fn (Get $get): bool => $get('network_key') === '__custom')
                                            ->maxLength(120),
                                        Toggle::make('is_enabled')
                                            ->label(__('capell-socials::socials.admin.enabled'))
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
                                            ->required(),
                                        Toggle::make('follow_open_in_new_tab')
                                            ->label(__('capell-socials::socials.admin.open_in_new_tab')),
                                    ])
                                    ->columns(2),
                                Section::make(__('capell-socials::socials.admin.share_defaults'))
                                    ->schema([
                                        Select::make('share_network_keys')
                                            ->label(__('capell-socials::socials.admin.share_networks'))
                                            ->multiple()
                                            ->options($this->shareNetworkOptions()),
                                        Select::make('share_label_style')
                                            ->label(__('capell-socials::socials.admin.label_style'))
                                            ->options($this->labelStyleOptions())
                                            ->required(),
                                        Toggle::make('share_open_in_new_tab')
                                            ->label(__('capell-socials::socials.admin.open_in_new_tab')),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $site = $this->siteFromState($state);

        SaveSocialSiteConfigurationAction::run(
            $site,
            $this->profilesFromState($state),
            new SocialSitePreferencesData(
                resolve(SocialNetworkRegistry::class),
                SocialLabelStyle::from((string) ($state['follow_label_style'] ?? SocialLabelStyle::Icons->value)),
                (bool) ($state['follow_open_in_new_tab'] ?? false),
                Arr::wrap($state['share_network_keys'] ?? []),
                SocialLabelStyle::from((string) ($state['share_label_style'] ?? SocialLabelStyle::Icons->value)),
                (bool) ($state['share_open_in_new_tab'] ?? false),
            ),
        );

        $this->fillForSite($site);

        Notification::make('capell_socials_saved')
            ->success()
            ->title(__('capell-socials::socials.admin.saved'))
            ->send();
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
        $siteId = $this->data['site_id'] ?? null;

        return is_numeric($siteId)
            ? SiteScope::applyForCurrentActor(Site::query(), 'id', denyWhenMissingActor: true)->find((int) $siteId)
            : null;
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

    /** @return array<string, string> */
    private function siteOptions(): array
    {
        return $this->sites()->mapWithKeys(static fn (Site $site): array => [(string) $site->getKey() => $site->name])->all();
    }

    /** @return array<string, string> */
    private function networkOptions(): array
    {
        return ['__custom' => __('capell-socials::socials.admin.custom_link')]
            + collect(resolve(SocialNetworkRegistry::class)->all())
                ->mapWithKeys(static fn ($network): array => [$network->key => $network->label])
                ->all();
    }

    /** @return array<string, string> */
    private function shareNetworkOptions(): array
    {
        return collect(resolve(SocialNetworkRegistry::class)->all())
            ->filter(static fn ($network): bool => $network->supports(SocialNetworkCapability::Share))
            ->mapWithKeys(static fn ($network): array => [$network->key => $network->label])
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
    private function siteFromState(array $state): Site
    {
        $siteId = $state['site_id'] ?? null;

        if (! is_numeric($siteId)) {
            throw new InvalidArgumentException('A valid site is required.');
        }

        $site = SiteScope::applyForCurrentActor(Site::query(), 'id', denyWhenMissingActor: true)->find((int) $siteId);

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
        return collect(Arr::wrap($state['profiles'] ?? []))
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
            ->all();
    }
}
