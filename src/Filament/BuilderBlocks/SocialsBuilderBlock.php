<?php

declare(strict_types=1);

namespace Capell\Socials\Filament\BuilderBlocks;

use Capell\BlockLibrary\Contracts\FilamentBuilderBlock;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Enums\SocialNetworkCapability;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

final class SocialsBuilderBlock implements FilamentBuilderBlock
{
    public static function getBuilderBlockName(): string
    {
        return 'socials';
    }

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
                Repeater::make('profile_ids')
                    ->label(__('capell-socials::socials.widget.profile_ids'))
                    ->simple(TextInput::make('profile_id')->numeric()->minValue(1))
                    ->visible(static fn (Get $get): bool => $get('mode') === 'follow')
                    ->maxItems(50),
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
                    ->options(static fn (): array => collect(app(SocialNetworkRegistry::class)->all())
                        ->filter(static fn ($network): bool => $network->supports(SocialNetworkCapability::Share))
                        ->mapWithKeys(static fn ($network): array => [$network->key => $network->resolveLabel()])
                        ->all())
                    ->visible(static fn (Get $get): bool => $get('mode') === 'share'),
            ]);
    }
}
