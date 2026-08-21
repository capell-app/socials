<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Socials\Actions\SeedSocialsScreenshotFixtureAction;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;

function migrateSocialsScreenshotFixtureTables(): void
{
    foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.php') ?: [] as $path) {
        $migration = require $path;

        throw_unless(is_object($migration) && method_exists($migration, 'up'), RuntimeException::class, sprintf(
            'Expected [%s] to return a database migration.',
            $path,
        ));

        $migration->up();
    }
}

function withSocialsScreenshotFixtureEnvironment(Closure $callback): void
{
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());
    putenv('APP_ENV=testing');

    try {
        $callback();
    } finally {
        putenv('CAPELL_SCREENSHOT_FIXTURE');
        putenv('CAPELL_SCREENSHOT_APP_PATH');
        putenv('APP_ENV');
    }
}

beforeEach(function (): void {
    migrateSocialsScreenshotFixtureTables();

    $language = Language::factory()->create();
    $this->site = Site::factory()->default()->create(['language_id' => $language->getKey()]);
});

it('seeds enabled profiles and defaults for the disposable Socials preview', function (): void {
    withSocialsScreenshotFixtureEnvironment(function (): void {
        $first = SeedSocialsScreenshotFixtureAction::run();
        $second = SeedSocialsScreenshotFixtureAction::run();

        $profiles = SocialProfile::query()
            ->where('site_id', $this->site->getKey())
            ->orderBy('sort_order')
            ->get();

        expect($second->getKey())->toBe($first->getKey())
            ->and($profiles)->toHaveCount(3)
            ->and($profiles->pluck('network_key')->all())->toBe(['x', 'linkedin', null])
            ->and($profiles->every(fn (SocialProfile $profile): bool => $profile->is_enabled))->toBeTrue()
            ->and(SocialSitePreferences::query()->where('site_id', $this->site->getKey())->sole()->follow_label_style)->toBe(SocialLabelStyle::IconsAndLabels)
            ->and($second->share_network_keys)->toBe(['x', 'linkedin'])
            ->and($second->share_label_style)->toBe(SocialLabelStyle::Labels);
    });
});

it('registers the guarded fixture command for the disposable screenshot app', function (): void {
    withSocialsScreenshotFixtureEnvironment(function (): void {
        test()->artisan('capell:socials-screenshot-fixture', ['--force' => true])
            ->expectsOutput('Socials screenshot fixture initialized.')
            ->assertSuccessful();

        expect(SocialProfile::query()->where('site_id', $this->site->getKey())->count())->toBe(3);
    });
});

it('refuses to seed outside the explicit disposable screenshot environment', function (): void {
    expect(fn (): SocialSitePreferences => SeedSocialsScreenshotFixtureAction::run())
        ->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');
});
