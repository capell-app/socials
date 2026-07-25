<?php

declare(strict_types=1);

it('ships distinct readable evidence for every required Socials surface', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $contract = socialsScreenshotJsonObject($packagePath . '/docs/screenshots.json');
    $entries = $contract['entries'] ?? null;

    if (! is_array($entries)) {
        throw new RuntimeException('Socials screenshot contract entries must be an array.');
    }

    $hashes = [];

    foreach ($entries as $entry) {
        if (! is_array($entry) || ($entry['required'] ?? false) !== true) {
            continue;
        }

        $relativePath = $entry['screenshotPath'] ?? null;

        if (! is_string($relativePath) || ! str_starts_with($relativePath, 'packages/socials/')) {
            throw new RuntimeException('Required Socials screenshot paths must target the Socials package.');
        }

        $path = dirname($packagePath, 2) . '/' . $relativePath;
        $imageSize = is_file($path) ? getimagesize($path) : false;
        $hash = is_file($path) ? hash_file('sha256', $path) : false;

        expect($imageSize)->toBeArray()
            ->and($imageSize[0] ?? 0)->toBeGreaterThanOrEqual(1200)
            ->and($imageSize[1] ?? 0)->toBeGreaterThanOrEqual(800)
            ->and($hash)->toBeString()->not->toBe('');

        $hashes[] = $hash;
    }

    expect($hashes)->toHaveCount(5)
        ->and(array_values(array_unique($hashes)))->toHaveCount(5);
});

/** @return array<string, mixed> */
function socialsScreenshotJsonObject(string $path): array
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
