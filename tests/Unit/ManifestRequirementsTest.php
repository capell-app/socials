<?php

declare(strict_types=1);

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Support\Manifest\ManifestValidator;
use Capell\Socials\Manifest\SocialProfileModelContribution;
use Capell\Socials\Manifest\SocialsAdminPageContribution;
use Capell\Socials\Manifest\SocialSitePreferencesModelContribution;
use Capell\Socials\Manifest\SocialsWidgetContribution;

it('declares the shipped Socials package surfaces and Marketplace assets', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = json_decode((string) file_get_contents($packagePath . '/capell.json'), true, flags: JSON_THROW_ON_ERROR);
    $composer = json_decode((string) file_get_contents($packagePath . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    (new ManifestValidator)->validate($manifest, $composer, 'capell-app/socials', $packagePath . '/capell.json');

    $contributions = collect($manifest['contributes'] ?? []);

    expect($manifest['product'])->toMatchArray(['tier' => 'free', 'bundle' => 'foundation'])
        ->and($manifest['permissions'])->toContain('Manage:Socials')
        ->and($manifest['providers']['admin'])->toContain('Capell\\Socials\\Providers\\AdminServiceProvider')
        ->and($manifest['contributionTraceability']['deferredContributions'])->toBe([])
        ->and($contributions->pluck('class')->all())->toContain(
            SocialProfileModelContribution::class,
            SocialSitePreferencesModelContribution::class,
            SocialsAdminPageContribution::class,
            SocialsWidgetContribution::class,
        );

    foreach ($contributions as $contribution) {
        $class = $contribution['class'] ?? null;

        expect(is_string($class) && class_exists($class))->toBeTrue()
            ->and(is_subclass_of($class, ExtensionContribution::class))->toBeTrue();
    }

    foreach ($manifest['marketplace']['screenshots'] as $screenshot) {
        expect($screenshot['alt'] ?? null)->toBeString()->not->toBe('')
            ->and($screenshot['caption'] ?? null)->toBeString()->not->toBe('')
            ->and(is_file($packagePath . '/' . $screenshot['path']))->toBeTrue();
    }
});
