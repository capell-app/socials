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
    $createProfiles->up();
    $createPreferences->up();

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
