<?php

declare(strict_types=1);

use Capell\Core\Actions\InstallPackageAction;
use Capell\Core\Actions\RuntimeRefresh\RefreshInstalledPackageRuntimeAction;
use Capell\Core\Data\Runtime\RuntimeRoleSelectionData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Site;
use Capell\Core\Support\Runtime\RuntimeRoleResolver;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\SocialsCacheEpoch;
use Capell\Socials\Tests\Fixtures\SocialsBuilderForm;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Foundation\Application;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

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

it('offers named profiles through the real content builder after fresh or in-process installation', function (bool $initiallyInstalled): void {
    InstalledRuntimeState::run(static function () use ($initiallyInstalled): void {
        $app = InstalledRuntimeTestCase::boot(InstalledRuntimeParity::manifest('socials'), $initiallyInstalled, sourceRoot: dirname(__DIR__, 3));
        $package = CapellCore::getPackage('capell-app/socials');
        if (! $initiallyInstalled) {
            InstalledRuntimeTestCase::prepareLateInstallMigrations($app);
            InstallPackageAction::run($package);
        }

        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $app->make(Factory::class)->guard()->login($user);
        $site = Site::factory()->create();
        $profile = SocialProfile::query()->create([
            'site_id' => $site->getKey(), 'network_key' => 'linkedin', 'profile_value' => 'capell',
            'custom_label' => 'Installed studio team', 'is_enabled' => true,
        ]);
        foreach ([false, true, true] as $refresh) {
            if ($refresh) {
                RefreshInstalledPackageRuntimeAction::run($package);
            }

            $component = Livewire::test(SocialsBuilderForm::class, ['siteId' => $site->getKey(), 'state' => ['profile_ids' => []]])
                ->assertSee('Installed studio team');
            $key = array_key_first($component->get('data.blocks'));
            $component->set("data.blocks.{$key}.data.profile_ids", [$profile->getKey()])->call('save')->assertHasNoErrors();
            expect(array_values($component->get('saved.blocks'))[0]['data']['profile_ids'])->toBe([$profile->id]);
        }
    });
})->with([true, false]);
