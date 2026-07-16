<?php

declare(strict_types=1);

namespace Capell\Socials\Health;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Socials\Blocks\SocialsBlockDefinitionProvider;
use Capell\Socials\Blocks\SocialsBlockRenderer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class SocialsHealthCheck implements ChecksExtensionHealth
{
    /** @var list<string> */
    private const array RequiredTables = ['social_profiles', 'social_site_preferences'];

    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }

    /** @return Collection<int, DoctorCheckResultData> */
    public static function runDiagnostics(): Collection
    {
        $missingTables = array_values(array_filter(
            self::RequiredTables,
            static fn (string $table): bool => ! Schema::hasTable($table),
        ));

        $widgetAvailable = class_exists(SocialsBlockDefinitionProvider::class) && class_exists(SocialsBlockRenderer::class);

        return collect([
            new DoctorCheckResultData(
                label: 'Socials storage tables',
                passed: $missingTables === [],
                message: $missingTables === []
                    ? 'The Socials storage tables are present.'
                    : 'Missing Socials tables: ' . implode(', ', $missingTables) . '.',
                remediation: $missingTables === []
                    ? null
                    : 'Run capell:socials-install or the Capell migrations.',
            ),
            new DoctorCheckResultData(
                label: 'Socials frontend widget',
                passed: $widgetAvailable,
                message: $widgetAvailable
                    ? 'The Socials block definition and renderer are available.'
                    : 'The Socials block definition or renderer could not be loaded.',
                remediation: $widgetAvailable
                    ? null
                    : 'Ensure the Socials package autoloader and service provider are registered.',
            ),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }
}
