<?php

declare(strict_types=1);

use Capell\Core\Actions\InstallPackageAction;
use Capell\Core\Actions\RuntimeRefresh\RefreshInstalledPackageRuntimeAction;
use Capell\Core\Data\Runtime\RuntimeRoleSelectionData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Runtime\RuntimeRoleResolver;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\SocialsCacheEpoch;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
use Illuminate\Foundation\Application;

it('matches fresh installed boot after in-process installation and repeated refresh', function (): void {
    InstalledRuntimeParity::assertPackage('socials', static function (Application $app): void {
        $epoch = $app->make(SocialsCacheEpoch::class);
        foreach ([new SocialProfile(['site_id' => 1]), new SocialSitePreferences(['site_id' => 1])] as $model) {
            $before = $epoch->current(1);
            event('eloquent.created: ' . $model::class, [$model]);

            expect($epoch->current(1))->toBe($before + 1);
        }
    });
});

it('does not invalidate site caches before installation', function (): void {
    InstalledRuntimeState::run(static function (): void {
        $app = InstalledRuntimeTestCase::boot(InstalledRuntimeParity::manifest('socials'), false, sourceRoot: dirname(__DIR__, 3));
        $epoch = $app->make(SocialsCacheEpoch::class);
        $before = $epoch->current(1);

        event('eloquent.created: ' . SocialProfile::class, [new SocialProfile(['site_id' => 1])]);

        expect($epoch->current(1))->toBe($before);
    });
});

it('invalidates caches once per model event in the public runtime', function (bool $initiallyInstalled): void {
    InstalledRuntimeState::run(static function () use ($initiallyInstalled): void {
        $app = InstalledRuntimeTestCase::boot(InstalledRuntimeParity::manifest('socials'), $initiallyInstalled, static function (Application $app): void {
            $app->instance(RuntimeRoleResolver::class, new RuntimeRoleResolver(RuntimeRoleSelectionData::fromConfiguredValue('public')));
        }, sourceRoot: dirname(__DIR__, 3));
        $package = CapellCore::getPackage('capell-app/socials');
        if (! $initiallyInstalled) {
            InstalledRuntimeTestCase::prepareLateInstallMigrations($app);
            InstallPackageAction::run($package);
        }
        RefreshInstalledPackageRuntimeAction::run($package);
        RefreshInstalledPackageRuntimeAction::run($package);
        $epoch = $app->make(SocialsCacheEpoch::class);
        $before = $epoch->current(1);

        event('eloquent.created: ' . SocialProfile::class, [new SocialProfile(['site_id' => 1])]);

        expect($epoch->current(1))->toBe($before + 1);
    });
})->with([true, false]);
