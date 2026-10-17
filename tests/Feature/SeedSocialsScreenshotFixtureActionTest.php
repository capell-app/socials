<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Socials\Actions\SeedSocialsScreenshotFixtureAction;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\ScreenshotManifest;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
    $this->site = Site::factory()->default()->create(['language_id' => $language->getKey(), 'name' => 'A Capture Site']);
    config()->set('auth.providers.users.model', User::class);
    $actor = User::factory()->create(['email' => 'admin@example.com']);
    $actor->assignGlobalRole(config()->string('capell.roles.super_admin', 'super_admin'));
});

it('seeds enabled profiles and defaults for the disposable Socials preview', function (): void {
    withSocialsScreenshotFixtureEnvironment(function (): void {
        $first = SeedSocialsScreenshotFixtureAction::run();
        $firstProfileIds = SocialProfile::query()->where('site_id', $this->site->getKey())->orderBy('sort_order')->pluck('id')->all();
        $second = SeedSocialsScreenshotFixtureAction::run();

        $profiles = SocialProfile::query()
            ->where('site_id', $this->site->getKey())
            ->orderBy('sort_order')
            ->get();

        expect($second->getKey())->toBe($first->getKey())
            ->and($profiles)->toHaveCount(3)
            ->and($profiles->pluck('id')->all())->toBe($firstProfileIds)
            ->and($profiles->pluck('network_key')->all())->toBe(['x', 'linkedin', null])
            ->and($profiles->first()?->custom_label)->toBeNull()
            ->and($profiles->every(fn (SocialProfile $profile): bool => $profile->is_enabled))->toBeTrue()
            ->and(SocialSitePreferences::query()->where('site_id', $this->site->getKey())->sole()->follow_label_style)->toBe(SocialLabelStyle::IconsAndLabels)
            ->and($second->share_network_keys)->toBe(['x', 'linkedin'])
            ->and($second->share_networks_customised)->toBeTrue()
            ->and($second->share_label_style)->toBe(SocialLabelStyle::Labels)
            ->and($this->site->fresh()?->meta['capture_seed_socials'])->toBe('socials-screenshot-site');
    });
});

it('refuses an unselected capture actor before touching Socials state', function (): void {
    $previous = getenv('CAPELL_SCREENSHOT_ADMIN_EMAIL');
    putenv('CAPELL_SCREENSHOT_ADMIN_EMAIL=missing-socials-actor@example.test');
    try {
        withSocialsScreenshotFixtureEnvironment(function (): void {
            expect(fn (): SocialSitePreferences => SeedSocialsScreenshotFixtureAction::run())
                ->toThrow(ModelNotFoundException::class);
        });
    } finally {
        putenv($previous === false ? 'CAPELL_SCREENSHOT_ADMIN_EMAIL' : 'CAPELL_SCREENSHOT_ADMIN_EMAIL=' . $previous);
    }

    expect(SocialProfile::query()->count())->toBe(0)
        ->and(SocialSitePreferences::query()->count())->toBe(0);
});

it('refuses to adopt foreign preferences on the selected site', function (): void {
    SocialSitePreferences::query()->create(['site_id' => $this->site->getKey()]);

    withSocialsScreenshotFixtureEnvironment(function (): void {
        expect(fn (): SocialSitePreferences => SeedSocialsScreenshotFixtureAction::run())
            ->toThrow(RuntimeException::class, 'already has profile or preference state');
    });

    expect(SocialSitePreferences::query()->where('site_id', $this->site->getKey())->count())->toBe(1)
        ->and(SocialProfile::query()->count())->toBe(0);
});

it('refuses to adopt foreign profiles on the selected site before writing its marker', function (): void {
    SocialProfile::query()->create(['site_id' => $this->site->getKey(), 'network_key' => 'x', 'profile_value' => '@foreign', 'sort_order' => 0, 'is_enabled' => true]);

    withSocialsScreenshotFixtureEnvironment(function (): void {
        expect(fn (): SocialSitePreferences => SeedSocialsScreenshotFixtureAction::run())
            ->toThrow(RuntimeException::class, 'already has profile or preference state');
    });

    expect(SocialProfile::query()->where('site_id', $this->site->getKey())->sole()->profile_value)->toBe('@foreign')
        ->and($this->site->fresh()?->meta['capture_seed_socials'] ?? null)->toBeNull();
});

it('preserves another site and refuses later foreign edits to its owned profiles', function (): void {
    $foreignSite = Site::factory()->create(['name' => 'Z Foreign Site', 'language_id' => $this->site->language_id]);
    $foreign = SocialProfile::query()->create(['site_id' => $foreignSite->getKey(), 'network_key' => 'x', 'profile_value' => '@foreign', 'sort_order' => 0, 'is_enabled' => true]);

    withSocialsScreenshotFixtureEnvironment(function (): void {
        SeedSocialsScreenshotFixtureAction::run();
        $owned = SocialProfile::query()->where('site_id', $this->site->getKey())->orderBy('sort_order')->firstOrFail();
        $owned->update(['profile_value' => '@changed-by-editor']);

        expect(fn (): SocialSitePreferences => SeedSocialsScreenshotFixtureAction::run())
            ->toThrow(RuntimeException::class, 'contains foreign profile or preference state');
        expect($owned->fresh()?->profile_value)->toBe('@changed-by-editor');
    });

    expect($foreign->fresh()?->profile_value)->toBe('@foreign');
});

it('registers the guarded fixture command for the disposable screenshot app', function (): void {
    withSocialsScreenshotFixtureEnvironment(function (): void {
        foreach (ScreenshotManifest::fixtureCommands(dirname(__DIR__, 2) . '/docs/screenshots.json') as $command) {
            capell_artisan($command, ['--force' => true])
                ->expectsOutput('Socials screenshot fixture initialized.')
                ->assertSuccessful();
        }

        expect(SocialProfile::query()->where('site_id', $this->site->getKey())->count())->toBe(3);
    });
});

it('renders public follow and share fixtures without replacing the seeded site settings', function (): void {
    withSocialsScreenshotFixtureEnvironment(function (): void {
        SeedSocialsScreenshotFixtureAction::run();
        require dirname(__DIR__, 4) . '/workbench/routes/screenshot-fixtures.php';

        $profilesBefore = SocialProfile::query()->where('site_id', $this->site->getKey())->orderBy('id')->get()->toArray();
        $preferencesBefore = SocialSitePreferences::query()->where('site_id', $this->site->getKey())->sole()->getAttributes();

        $kernel = resolve(Kernel::class);
        foreach (['follow', 'share'] as $surface) {
            $response = $kernel->handle(Request::create('/screenshot-fixtures/socials/' . $surface));
            $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
            $content = $response->getContent();
            $this->assertIsString($content);
            expect($content)->toContain('capell-socials');
        }

        expect(SocialProfile::query()->where('site_id', $this->site->getKey())->orderBy('id')->get()->toArray())->toBe($profilesBefore)
            ->and(SocialSitePreferences::query()->where('site_id', $this->site->getKey())->sole()->getAttributes())->toBe($preferencesBefore);
    });
});

it('refuses to seed outside the explicit disposable screenshot environment', function (): void {
    expect(fn (): SocialSitePreferences => SeedSocialsScreenshotFixtureAction::run())
        ->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');
});

it('refuses an authenticated actor other than the selected capture identity before writes', function (): void {
    $previous = [];
    foreach (['CAPELL_SCREENSHOT_FIXTURE', 'CAPELL_SCREENSHOT_APP_PATH', 'CAPELL_SCREENSHOT_ADMIN_EMAIL'] as $key) {
        $previous[$key] = getenv($key);
    }

    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());
    putenv('CAPELL_SCREENSHOT_ADMIN_EMAIL=selected-capture@example.test');
    $actor = new Illuminate\Foundation\Auth\User;
    $actor->forceFill(['id' => 987654, 'email' => 'another-actor@example.test']);
    $this->actingAs($actor);
    $before = SocialProfile::query()->count();

    try {
        expect(fn (): SocialSitePreferences => SeedSocialsScreenshotFixtureAction::run())->toThrow(RuntimeException::class, 'selected capture actor');
        expect(SocialProfile::query()->count())->toBe($before);
    } finally {
        foreach ($previous as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
    }
});

it('rejects a different physical checkout and production even when fixture mode is selected', function (): void {
    $previous = [getenv('CAPELL_SCREENSHOT_FIXTURE'), getenv('CAPELL_SCREENSHOT_APP_PATH')];
    $environment = config('app.env');
    $runtimeEnvironment = app()->environment();
    $actor = new Illuminate\Foundation\Auth\User;
    $before = SocialProfile::query()->count();
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    try {
        foreach (['foreign-checkout', 'production'] as $state) {
            putenv('CAPELL_SCREENSHOT_APP_PATH=' . ($state === 'foreign-checkout' ? __DIR__ : base_path()));
            config()->set('app.env', $state === 'production' ? 'production' : 'testing');
            app()->instance('env', $state === 'production' ? 'production' : 'testing');
            expect(fn (): SocialSitePreferences => SeedSocialsScreenshotFixtureAction::run())->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');
            expect(SocialProfile::query()->count())->toBe($before);
        }
    } finally {
        config()->set('app.env', $environment);
        app()->instance('env', $runtimeEnvironment);
        foreach (['CAPELL_SCREENSHOT_FIXTURE', 'CAPELL_SCREENSHOT_APP_PATH'] as $index => $key) {
            putenv($previous[$index] === false ? $key : $key . '=' . $previous[$index]);
        }
    }
});
