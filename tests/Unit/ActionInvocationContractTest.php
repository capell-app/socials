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
