<?php

declare(strict_types=1);

use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
    $this->previousFacadeApplication = Facade::getFacadeApplication();
    $this->previousModelEventDispatcher = Model::getEventDispatcher();

    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $container = new Application;
    $container->instance('db', $capsule->getDatabaseManager());
    $container->instance('db.schema', $capsule->schema());

    $events = new Dispatcher($container);
    $container->instance('events', $events);
    Model::setEventDispatcher($events);
    Container::setInstance($container);
    Facade::setFacadeApplication($container);
    Facade::clearResolvedInstances();

    Schema::create('sites', function (Blueprint $table): void {
        $table->id();
    });

    $createProfiles = require dirname(__DIR__, 2) . '/database/migrations/2026_07_16_000001_create_social_profiles_table.php';
    $createPreferences = require dirname(__DIR__, 2) . '/database/migrations/2026_07_16_000002_create_social_site_preferences_table.php';
    $addShareCustomised = require dirname(__DIR__, 2) . '/database/migrations/2026_08_31_000001_add_share_networks_customised_to_social_site_preferences.php';
    $createProfiles->up();
    $createPreferences->up();
    $addShareCustomised->up();

    DB::table('sites')->insert(['id' => 1]);
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
    $previousDispatcher = $this->previousModelEventDispatcher;
    if ($previousDispatcher instanceof Illuminate\Contracts\Events\Dispatcher) {
        Model::setEventDispatcher($previousDispatcher);
    } else {
        Model::unsetEventDispatcher();
    }

    Facade::setFacadeApplication($this->previousFacadeApplication);
    Container::setInstance($this->previousContainer);
});

it('enforces one registered network per site while allowing multiple custom profiles', function (): void {
    DB::table('social_profiles')->insert([
        'site_id' => 1,
        'network_key' => 'x',
        'profile_value' => '@capell',
        'sort_order' => 0,
        'is_enabled' => true,
    ]);

    expect(fn (): bool => DB::table('social_profiles')->insert([
        'site_id' => 1,
        'network_key' => 'x',
        'profile_value' => '@other',
        'sort_order' => 1,
        'is_enabled' => true,
    ]))->toThrow(QueryException::class);

    DB::table('social_profiles')->insert([
        [
            'site_id' => 1,
            'network_key' => null,
            'profile_value' => 'https://example.com/one',
            'custom_label' => 'One',
            'sort_order' => 2,
            'is_enabled' => true,
        ],
        [
            'site_id' => 1,
            'network_key' => null,
            'profile_value' => 'https://example.com/two',
            'custom_label' => 'Two',
            'sort_order' => 3,
            'is_enabled' => true,
        ],
    ]);

    expect(DB::table('social_profiles')->whereNull('network_key')->count())->toBe(2);
});

it('adds a guarded share-networks-customised flag that defaults to off and casts to boolean', function (): void {
    expect(Schema::hasColumn('social_site_preferences', 'share_networks_customised'))->toBeTrue();

    DB::table('social_site_preferences')->insert([
        'site_id' => 1,
        'follow_label_style' => 'icons',
        'follow_open_in_new_tab' => false,
        'share_label_style' => 'icons',
        'share_open_in_new_tab' => false,
    ]);

    $stored = SocialSitePreferences::query()->where('site_id', 1)->sole();

    expect($stored->share_networks_customised)->toBeFalse()
        ->and((new SocialSitePreferences)->share_networks_customised)->toBeFalse();

    $stored->update(['share_networks_customised' => true]);

    expect(SocialSitePreferences::query()->where('site_id', 1)->sole()->share_networks_customised)->toBeTrue();

    $addShareCustomised = require dirname(__DIR__, 2) . '/database/migrations/2026_08_31_000001_add_share_networks_customised_to_social_site_preferences.php';
    $addShareCustomised->up();

    expect(Schema::hasColumn('social_site_preferences', 'share_networks_customised'))->toBeTrue();
});

it('backfills the customisation flag from legacy non-empty share selections and supports reapply', function (): void {
    $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_08_31_000001_add_share_networks_customised_to_social_site_preferences.php';
    $migration->down();

    expect(Schema::hasColumn('social_site_preferences', 'share_networks_customised'))->toBeFalse();

    DB::table('social_site_preferences')->insert([
        [
            'site_id' => 1,
            'follow_label_style' => 'icons',
            'follow_open_in_new_tab' => false,
            'share_network_keys' => json_encode(['x', 'linkedin']),
            'share_label_style' => 'icons',
            'share_open_in_new_tab' => false,
        ],
    ]);

    $migration->up();

    expect(SocialSitePreferences::query()->where('site_id', 1)->sole()->share_networks_customised)->toBeTrue();

    $migration->down();
    $migration->up();

    expect(Schema::hasColumn('social_site_preferences', 'share_networks_customised'))->toBeTrue();
});

it('round-trips custom and recommended rows without classifying recommended snapshots as custom', function (): void {
    DB::table('social_site_preferences')->insert([
        [
            'site_id' => 1,
            'follow_label_style' => 'icons',
            'follow_open_in_new_tab' => false,
            'share_network_keys' => json_encode(['x', 'linkedin']),
            'share_networks_customised' => true,
            'share_label_style' => 'icons',
            'share_open_in_new_tab' => false,
        ],
    ]);
    DB::table('sites')->insert(['id' => 2]);
    DB::table('social_site_preferences')->insert([
        'site_id' => 2,
        'follow_label_style' => 'icons',
        'follow_open_in_new_tab' => false,
        'share_network_keys' => json_encode(['x', 'facebook', 'linkedin']),
        'share_networks_customised' => false,
        'share_label_style' => 'icons',
        'share_open_in_new_tab' => false,
    ]);

    $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_08_31_000001_add_share_networks_customised_to_social_site_preferences.php';
    $migration->down();

    $customShareKeys = DB::table('social_site_preferences')->where('site_id', 1)->value('share_network_keys');
    $recommendedShareKeys = DB::table('social_site_preferences')->where('site_id', 2)->value('share_network_keys');

    throw_unless(is_string($customShareKeys), RuntimeException::class, 'Expected custom share network keys to remain JSON.');
    throw_unless(is_string($recommendedShareKeys), RuntimeException::class, 'Expected recommended share network keys to remain JSON.');

    expect(Schema::hasColumn('social_site_preferences', 'share_networks_customised'))->toBeFalse()
        ->and(json_decode($customShareKeys, true))->toBe(['x', 'linkedin'])
        ->and(json_decode($recommendedShareKeys, true))->toBe(['x', 'facebook', 'linkedin']);

    $migration->up();

    expect(SocialSitePreferences::query()->where('site_id', 1)->sole()->share_networks_customised)->toBeTrue()
        ->and(SocialSitePreferences::query()->where('site_id', 2)->sole()->share_networks_customised)->toBeFalse();
});

it('repairs an incomplete marker table and tolerates an absent preferences table', function (): void {
    $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_08_31_000001_add_share_networks_customised_to_social_site_preferences.php';
    $migration->down();

    Schema::drop('social_site_preferences_share_semantics');
    Schema::create('social_site_preferences_share_semantics', function (Blueprint $table): void {
        $table->unsignedBigInteger('preference_id')->primary();
    });

    $migration->up();

    expect(Schema::hasColumns('social_site_preferences_share_semantics', [
        'preference_id',
        'site_id',
        'table_generation',
        'row_fingerprint',
        'customised',
    ]))->toBeTrue();

    $migration->down();
    Schema::drop('social_site_preferences');
    $migration->up();

    expect(Schema::hasTable('social_site_preferences'))->toBeFalse()
        ->and(Schema::hasTable('social_site_preferences_share_semantics'))->toBeTrue();
});

it('does not apply a stale marker when the preferences table is dropped and recreated', function (): void {
    $fixedTimestamp = '2026-08-31 12:00:00';

    DB::table('social_site_preferences')->insert([
        'site_id' => 1,
        'share_network_keys' => json_encode(['x']),
        'share_networks_customised' => false,
        'created_at' => $fixedTimestamp,
        'updated_at' => $fixedTimestamp,
    ]);

    $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_08_31_000001_add_share_networks_customised_to_social_site_preferences.php';
    $createPreferences = require dirname(__DIR__, 2) . '/database/migrations/2026_07_16_000002_create_social_site_preferences_table.php';
    $migration->down();
    $previousGeneration = DB::table('social_site_preferences_table_generation')->where('id', 1)->value('generation');
    $createPreferences->down();
    $createPreferences->up();

    $currentGeneration = DB::table('social_site_preferences_table_generation')->where('id', 1)->value('generation');

    DB::table('social_site_preferences')->insert([
        'site_id' => 1,
        'share_network_keys' => json_encode(['x']),
        'created_at' => $fixedTimestamp,
        'updated_at' => $fixedTimestamp,
    ]);

    $migration->up();

    $preferences = SocialSitePreferences::query()->where('site_id', 1)->sole();

    expect($previousGeneration)->toBeString()->not->toBe($currentGeneration)
        ->and($preferences->getKey())->toBe(1)
        ->and($preferences->share_networks_customised)->toBeTrue()
        ->and($preferences->share_network_keys)->toBe(['x'])
        ->and(DB::table('social_site_preferences_share_semantics')->where('preference_id', 1)->value('customised'))->toBe(1);
});

it('normalises persisted PDO boolean strings without PHP truthiness', function (): void {
    $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_08_31_000001_add_share_networks_customised_to_social_site_preferences.php';
    $normaliseBoolean = new ReflectionObject($migration)->getMethod('normaliseBoolean');

    expect($normaliseBoolean->invoke($migration, '0'))->toBeFalse()
        ->and($normaliseBoolean->invoke($migration, '1'))->toBeTrue()
        ->and($normaliseBoolean->invoke($migration, 0))->toBeFalse()
        ->and($normaliseBoolean->invoke($migration, 1))->toBeTrue();
});

it('enforces a single preference row per site and casts persisted values on the models', function (): void {
    $preferences = SocialSitePreferences::query()->create([
        'site_id' => 1,
        'follow_label_style' => SocialLabelStyle::Labels,
        'follow_open_in_new_tab' => true,
        'share_network_keys' => ['x', 'facebook'],
        'share_label_style' => SocialLabelStyle::IconsAndLabels,
        'share_open_in_new_tab' => true,
    ]);

    expect($preferences->follow_label_style)->toBe(SocialLabelStyle::Labels)
        ->and($preferences->follow_open_in_new_tab)->toBeTrue()
        ->and($preferences->share_network_keys)->toBe(['x', 'facebook'])
        ->and($preferences->share_label_style)->toBe(SocialLabelStyle::IconsAndLabels)
        ->and($preferences->share_open_in_new_tab)->toBeTrue()
        ->and((new SocialProfile)->getCasts())->toMatchArray([
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ]);

    DB::table('sites')->insert(['id' => 2]);

    DB::table('social_site_preferences')->insert([
        'site_id' => 2,
        'follow_label_style' => 'icons',
        'follow_open_in_new_tab' => false,
        'share_label_style' => 'icons',
        'share_open_in_new_tab' => false,
    ]);

    expect(SocialSitePreferences::query()->where('site_id', 2)->sole()->share_network_keys)->toBe([])
        ->and((new SocialSitePreferences)->share_network_keys)->toBe([]);
});
