<?php

declare(strict_types=1);

namespace Capell\Socials\Providers;

use Capell\Core\Support\Migration\MigrationRootRegistry;
use Illuminate\Support\ServiceProvider;
use Override;

final class MigrationMetadataServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->callAfterResolving(MigrationRootRegistry::class, static function (MigrationRootRegistry $registry): void {
            $registry->register('capell-app/socials', __DIR__ . '/../../database/migrations', [
                '2026_07_16_000001_create_social_profiles_table' => 'social_profiles',
                '2026_07_16_000002_create_social_site_preferences_table' => 'social_site_preferences',
            ]);
        });
    }
}
