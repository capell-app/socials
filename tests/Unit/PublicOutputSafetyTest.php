<?php

declare(strict_types=1);

use Capell\Socials\Data\SocialFollowRenderData;
use Capell\Socials\Data\SocialProfileData;
use Capell\Socials\Enums\SocialLabelStyle;

require_once __DIR__ . '/../Support/RenderSocialsView.php';

it('renders escaped public links without authoring or package internals', function (): void {
    $renderData = new SocialFollowRenderData(
        heading: '<script>window.leak = true</script>Follow us',
        labelStyle: SocialLabelStyle::IconsAndLabels,
        openInNewTab: true,
        alignment: 'center',
        profiles: [
            new SocialProfileData(
                networkKey: 'custom',
                label: '<img src=x onerror=alert(1)>Community',
                url: 'https://example.com/community?from="socials"',
                handle: null,
                icon: 'link',
                capabilities: [],
            ),
        ],
    );

    $html = renderSocialsView('capell-socials::blocks.socials', $renderData);

    expect($html)
        ->toContain('capell-socials--center')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('&lt;script&gt;window.leak = true&lt;/script&gt;Follow us')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;Community')
        ->toContain('https://example.com/community?from=&quot;socials&quot;')
        ->not->toContain('<script>')
        ->not->toContain('<img')
        ->not->toContain('data-model-id')
        ->not->toContain('data-field-path')
        ->not->toContain('frontend-authoring')
        ->not->toContain('signed-editor')
        ->not->toContain('Capell\\Socials')
        ->not->toContain('wire:');
});
