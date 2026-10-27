<?php

declare(strict_types=1);

namespace Capell\Socials\Filament\BuilderBlocks;

use Capell\Admin\Contracts\Widgets\FilamentWidget;
use Capell\BlockLibrary\Contracts\FilamentBuilderBlock;
use Capell\Core\Models\Site;
use Capell\Frontend\Facades\Frontend;
use Capell\Socials\Actions\ResolveSocialProfileChoicesAction;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\SocialProfileChoicesData;
use Capell\Socials\Enums\SocialNetworkCapability;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Override;

final class SocialsBuilderBlock implements FilamentBuilderBlock, FilamentWidget
{
    #[Override]
    public static function getBuilderBlockName(): string
    {
        return 'socials';
    }

    #[Override]
    public static function getWidgetName(): string
    {
        return self::getBuilderBlockName();
    }

    #[Override]
    public static function make(): Block
    {
        return Block::make(self::getBuilderBlockName())
            ->label(__('capell-socials::socials.widget.label'))
            ->schema([
                Select::make('mode')->options(['follow' => __('capell-socials::socials.widget.follow'), 'share' => __('capell-socials::socials.widget.share')])->default('follow')->required()->live(),
                TextInput::make('heading')->maxLength(120),
                Select::make('label_style')->options(['inherit' => __('capell-socials::socials.widget.inherit'), 'icons' => __('capell-socials::socials.widget.icons'), 'labels' => __('capell-socials::socials.widget.labels'), 'icons_and_labels' => __('capell-socials::socials.widget.icons_and_labels')])->default('inherit')->required(),
                Select::make('link_target')->options(['inherit' => __('capell-socials::socials.widget.inherit'), 'same_tab' => __('capell-socials::socials.widget.same_tab'), 'new_tab' => __('capell-socials::socials.widget.new_tab')])->default('inherit')->required(),
                Select::make('alignment')->options(['start' => __('capell-socials::socials.widget.start'), 'center' => __('capell-socials::socials.widget.center'), 'end' => __('capell-socials::socials.widget.end')])->default('start')->required(),
                Select::make('profile_selection')
                    ->label(__('capell-socials::socials.widget.profile_selection'))
                    ->options(['all' => __('capell-socials::socials.widget.profiles_all'), 'choose' => __('capell-socials::socials.widget.profiles_choose')])
                    ->afterStateHydrated(static function (Select $component, Component $livewire): void {
                        $ids = data_get($livewire, $component->resolveRelativeStatePath('profile_ids'));
                        $component->state(is_array($ids) ? 'choose' : 'all');
                    })
                    ->afterStateUpdated(static fn (mixed $state, Set $set): mixed => $set('profile_ids', $state === 'all' ? null : []))
                    ->dehydrated(false)
                    ->required()->live()
                    ->visible(static fn (Get $get): bool => $get('mode') === 'follow'),
                Select::make('profile_ids')
                    ->label(__('capell-socials::socials.widget.profile_ids'))
                    ->multiple()->live()
                    ->options(static fn (?Model $record, Component $livewire): array => self::profileChoices($record, $livewire)->options)
                    ->helperText(static function (?Model $record, Component $livewire, Get $get): string {
                        $choices = self::profileChoices($record, $livewire);

                        return __('capell-socials::socials.widget.' . ($choices->hasUnavailableSelections($get('profile_ids'))
                            ? 'profiles_unavailable' : ($choices->options === [] ? 'profiles_none' : 'profiles_empty')));
                    })
                    ->maxItems(50)
                    ->dehydrateStateUsing(static fn (mixed $state, Get $get): ?array => $get('profile_selection') === 'all' ? null : (is_array($state) ? $state : []))
                    ->dehydratedWhenHidden(static fn (Get $get): bool => $get('mode') === 'follow')
                    ->visible(static fn (Get $get): bool => $get('mode') === 'follow' && $get('profile_selection') === 'choose'),
                Repeater::make('custom_links')
                    ->label(__('capell-socials::socials.widget.custom_links'))
                    ->schema([
                        TextInput::make('label')->required()->maxLength(120),
                        TextInput::make('url')->required()->url()->maxLength(2048),
                    ])
                    ->visible(static fn (Get $get): bool => $get('mode') === 'follow')
                    ->maxItems(10),
                Select::make('share_network_keys')
                    ->label(__('capell-socials::socials.widget.share_network_keys'))
                    ->multiple()
                    ->options(static fn (): array => collect(resolve(SocialNetworkRegistry::class)->all())
                        ->filter(static fn ($network): bool => $network->supports(SocialNetworkCapability::Share))
                        ->mapWithKeys(static fn ($network): array => [$network->key => $network->resolveLabel()])
                        ->all())
                    ->visible(static fn (Get $get): bool => $get('mode') === 'share'),
            ]);
    }

    private static function profileChoices(?Model $record, Component $livewire): SocialProfileChoicesData
    {
        $site = property_exists($livewire, 'site') ? $livewire->site : Frontend::site();

        return ResolveSocialProfileChoicesAction::run($record, $site instanceof Site ? $site : null);
    }
}
