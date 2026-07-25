<?php

declare(strict_types=1);

it('uses container-backed Laravel Action entrypoints across Socials orchestration', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $follow = file_get_contents($packagePath . '/src/Actions/BuildFollowSocialRenderDataAction.php');
    $share = file_get_contents($packagePath . '/src/Actions/BuildShareSocialRenderDataAction.php');
    $renderer = file_get_contents($packagePath . '/src/Blocks/SocialsBlockRenderer.php');
    $command = file_get_contents($packagePath . '/src/Console/Commands/InstallSocialsCommand.php');

    expect($follow)->toBeString()
        ->toContain('use AsFake;')
        ->toContain('use AsObject;')
        ->toContain('ResolveSiteSocialProfilesAction::run(')
        ->not->toContain('private readonly ResolveSiteSocialProfilesAction')
        ->and($share)->toBeString()
        ->toContain('use AsFake;')
        ->toContain('use AsObject;')
        ->toContain('BuildShareLinksAction::run(')
        ->not->toContain('new BuildShareLinksAction')
        ->and($renderer)->toBeString()
        ->toContain('BuildFollowSocialRenderDataAction::run(')
        ->toContain('BuildShareSocialRenderDataAction::run(')
        ->not->toContain('private readonly BuildFollowSocialRenderDataAction')
        ->not->toContain('private readonly BuildShareSocialRenderDataAction')
        ->and($command)->toBeString()
        ->toContain('ImportLegacySocialProfilesAction::run(');
});

it('passes typed preview render data into Filament views instead of assuming a Livewire view variable', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $page = file_get_contents($packagePath . '/src/Filament/Pages/SocialsPage.php');
    $pageView = file_get_contents($packagePath . '/resources/views/filament/pages/socials.blade.php');
    $followPreview = file_get_contents($packagePath . '/resources/views/filament/partials/follow-preview.blade.php');
    $sharePreview = file_get_contents($packagePath . '/resources/views/filament/partials/share-preview.blade.php');

    expect($page)->toBeString()
        ->toContain("->viewData(fn (): array => ['renderData' => \$this->getFollowPreviewProperty()])")
        ->toContain("->viewData(fn (): array => ['renderData' => \$this->getSharePreviewProperty()])")
        ->and($followPreview)->toBeString()
        ->toContain('@if ($renderData->shouldRender())')
        ->not->toContain('$livewire')
        ->and($sharePreview)->toBeString()
        ->toContain('@if ($renderData?->shouldRender())')
        ->not->toContain('$livewire')
        ->and($pageView)->toBeString()
        ->toContain('[data-capell-socials-admin-preview] .capell-socials a')
        ->and($followPreview)->toContain('data-capell-socials-admin-preview')
        ->and($sharePreview)->toContain('data-capell-socials-admin-preview');
});
