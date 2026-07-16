<?php

declare(strict_types=1);

use Capell\Socials\Data\SocialFollowWidgetConfigData;
use Capell\Socials\Data\SocialShareWidgetConfigData;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Support\BuiltIns\BuiltInSocialNetworkDefinitions;
use Capell\Socials\Support\SocialNetworkRegistry;

it('normalizes safe follow widget configuration without exposing selected profile IDs to public render data', function (): void {
    $config = SocialFollowWidgetConfigData::fromState([
        'heading' => ' Follow us ',
        'label_style' => 'icons_and_labels',
        'link_target' => 'new_tab',
        'alignment' => 'center',
        'profile_ids' => [4, '7', 4],
        'custom_links' => [['label' => 'Community', 'url' => 'https://example.com/community']],
    ]);

    expect($config->heading)->toBe('Follow us')
        ->and($config->labelStyle)->toBe(SocialLabelStyle::IconsAndLabels)
        ->and($config->openInNewTab)->toBeTrue()
        ->and($config->profileIds)->toBe([4, 7])
        ->and($config->customLinks[0]->url)->toBe('https://example.com/community');
});

it('accepts only registered share-capable networks for share widgets', function (): void {
    $registry = new SocialNetworkRegistry;

    foreach (BuiltInSocialNetworkDefinitions::all() as $definition) {
        $registry->register($definition);
    }

    $config = SocialShareWidgetConfigData::fromState([
        'share_network_keys' => ['twitter', 'linkedin', 'x'],
    ], $registry);

    expect($config->networkKeys)->toBe(['x', 'linkedin']);

    SocialShareWidgetConfigData::fromState(['share_network_keys' => ['mastodon']], $registry);
})->throws(InvalidArgumentException::class);
