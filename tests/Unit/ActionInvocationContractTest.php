<?php

declare(strict_types=1);

use Capell\Socials\Actions\BuildFollowSocialRenderDataAction;
use Capell\Socials\Actions\BuildShareSocialRenderDataAction;
use Capell\Socials\Data\SharePageContextData;
use Capell\Socials\Data\SocialFollowWidgetConfigData;
use Capell\Socials\Data\SocialShareWidgetConfigData;
use Capell\Socials\Data\SocialSitePreferencesData;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Support\BuiltIns\BuiltInSocialNetworkDefinitions;
use Capell\Socials\Support\SocialNetworkRegistry;
use Capell\Socials\Support\SocialsCacheEpoch;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

require_once __DIR__ . '/../Support/RenderSocialsView.php';

function previewSocialNetworkRegistry(): SocialNetworkRegistry
{
    $registry = new SocialNetworkRegistry;

    foreach (BuiltInSocialNetworkDefinitions::all() as $definition) {
        $registry->register($definition);
    }

    return $registry;
}

it('builds and renders a follow preview from editor configuration', function (): void {
    $registry = previewSocialNetworkRegistry();
    $action = new BuildFollowSocialRenderDataAction(new SocialsCacheEpoch(new Repository(new ArrayStore)));
    $renderData = $action->preview(
        SocialFollowWidgetConfigData::fromState([
            'heading' => 'Follow the community', 'label_style' => 'icons_and_labels',
            'custom_links' => [['label' => 'Community', 'url' => 'https://example.test/community']],
        ]),
        new SocialSitePreferencesData($registry, followLabelStyle: SocialLabelStyle::IconsAndLabels),
        [],
    );
    $html = renderSocialsView('capell-socials::filament.partials.follow-preview', $renderData);

    expect($renderData->shouldRender())->toBeTrue()
        ->and($html)->toContain('Follow the community', 'Community', 'href="https://example.test/community"');
});

it('builds and renders share preview URLs from the configured page', function (): void {
    $registry = previewSocialNetworkRegistry();
    $action = new BuildShareSocialRenderDataAction($registry, new SocialsCacheEpoch(new Repository(new ArrayStore)));
    $renderData = $action->preview(
        new SharePageContextData('https://example.test/article', 'An article', 'en'),
        SocialShareWidgetConfigData::fromState(['heading' => 'Share this article', 'share_network_keys' => ['x']], $registry),
        new SocialSitePreferencesData($registry),
    );
    $html = renderSocialsView('capell-socials::filament.partials.share-preview', $renderData);

    expect($renderData->shouldRender())->toBeTrue()
        ->and($html)->toContain('Share this article', 'https://x.com/intent/post?', 'https%3A%2F%2Fexample.test%2Farticle');
});

it('renders useful empty previews when follow links or page context are absent', function (): void {
    $registry = previewSocialNetworkRegistry();
    $action = new BuildFollowSocialRenderDataAction(new SocialsCacheEpoch(new Repository(new ArrayStore)));
    $follow = $action->preview(SocialFollowWidgetConfigData::fromState([]), new SocialSitePreferencesData($registry), []);

    expect(renderSocialsView('capell-socials::filament.partials.follow-preview', $follow))
        ->toContain(__('capell-socials::socials.admin.preview_empty'))
        ->not->toContain('<a ')
        ->and(renderSocialsView('capell-socials::filament.partials.share-preview', null))
        ->toContain(__('capell-socials::socials.admin.share_preview_unavailable'))
        ->not->toContain('<a ');
});
