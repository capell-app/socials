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
    $manifest = socialsJsonObject($packagePath . '/capell.json');
    $composer = socialsJsonObject($packagePath . '/composer.json');

    (new ManifestValidator)->validate($manifest, $composer, 'capell-app/socials', $packagePath . '/capell.json');

    $contributionDefinitions = $manifest['contributes'] ?? null;

    if (! is_array($contributionDefinitions)) {
        throw new RuntimeException('Socials manifest contributions must be an array.');
    }

    /** @var list<array<string, mixed>> $contributions */
    $contributions = [];

    foreach ($contributionDefinitions as $contribution) {
        if (! is_array($contribution)) {
            throw new RuntimeException('Socials manifest contributions must be objects.');
        }

        $contributions[] = $contribution;
    }

    $contributionClasses = [];

    foreach ($contributions as $contribution) {
        $class = $contribution['class'] ?? null;

        if (! is_string($class)) {
            throw new RuntimeException('Socials manifest contributions require a class.');
        }

        $contributionClasses[] = $class;

        expect(class_exists($class))->toBeTrue()
            ->and(is_subclass_of($class, ExtensionContribution::class))->toBeTrue();
    }

    expect($manifest['product'])->toMatchArray(['tier' => 'free', 'bundle' => 'foundation'])
        ->and($manifest['permissions'])->toContain('Manage:Socials')
        ->and($manifest['providers']['admin'])->toContain('Capell\\Socials\\Providers\\AdminServiceProvider')
        ->and($manifest['contributionTraceability']['deferredContributions'])->toBe([])
        ->and($contributionClasses)->toContain(
            SocialProfileModelContribution::class,
            SocialSitePreferencesModelContribution::class,
            SocialsAdminPageContribution::class,
            SocialsWidgetContribution::class,
        );

    $marketplace = $manifest['marketplace'] ?? null;
    $screenshots = is_array($marketplace) ? $marketplace['screenshots'] ?? null : null;

    if (! is_array($screenshots)) {
        throw new RuntimeException('Socials Marketplace screenshots must be an array.');
    }

    foreach ($screenshots as $screenshot) {
        if (! is_array($screenshot) || ! is_string($screenshot['path'] ?? null)) {
            throw new RuntimeException('Socials Marketplace screenshot metadata is invalid.');
        }

        expect($screenshot['alt'] ?? null)->toBeString()->not->toBe('')
            ->and($screenshot['caption'] ?? null)->toBeString()->not->toBe('')
            ->and(is_file($packagePath . '/' . $screenshot['path']))->toBeTrue();
    }
});

/** @return array<string, mixed> */
function socialsJsonObject(string $path): array
{
    $contents = file_get_contents($path);

    if (! is_string($contents)) {
        throw new RuntimeException(sprintf('Unable to read [%s].', $path));
    }

    $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new RuntimeException(sprintf('[%s] must contain a JSON object.', $path));
    }

    $object = [];

    foreach ($decoded as $key => $value) {
        if (! is_string($key)) {
            throw new RuntimeException(sprintf('[%s] must contain a JSON object.', $path));
        }

        $object[$key] = $value;
    }

    return $object;
}
