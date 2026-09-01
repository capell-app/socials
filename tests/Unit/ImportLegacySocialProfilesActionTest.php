<?php

declare(strict_types=1);

use Capell\Core\Events\FrontendSurrogateKeysInvalidated;
use Capell\Socials\Actions\ImportLegacySocialProfilesAction;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\BuiltIns\BuiltInSocialNetworkDefinitions;
use Capell\Socials\Support\HttpUrlValidator;
use Capell\Socials\Support\SocialNetworkRegistry;
use Capell\Socials\Support\SocialsCacheEpoch;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
    $this->previousFacadeApplication = Facade::getFacadeApplication();
    $this->previousModelEventDispatcher = Model::getEventDispatcher();
    $this->invalidatedSurrogateKeys = [];

    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $container = new Application;
    $loader = new ArrayLoader;
    $loader->addMessages('en', 'capell-socials::socials', require dirname(__DIR__, 2) . '/resources/lang/en/socials.php');

    $container->instance('translator', new Translator($loader, 'en'));
    $container->instance('db', $capsule->getDatabaseManager());
    $container->instance('db.schema', $capsule->schema());

    $transactionManager = new DatabaseTransactionsManager;
    $capsule->getConnection()->setTransactionManager($transactionManager);
    $container->instance('db.transactions', $transactionManager);

    $events = new Dispatcher($container);
    $events->listen(FrontendSurrogateKeysInvalidated::class, function (FrontendSurrogateKeysInvalidated $event): void {
        $this->invalidatedSurrogateKeys[] = $event->surrogateKeys;
    });
    $container->instance('events', $events);
    Model::setEventDispatcher($events);

    Container::setInstance($container);
    Facade::setFacadeApplication($container);
    Facade::clearResolvedInstances();

    Schema::create('sites', function (Blueprint $table): void {
        $table->id();
        $table->json('meta')->nullable();
        $table->softDeletes();
    });

    $createProfiles = require dirname(__DIR__, 2) . '/database/migrations/2026_07_16_000001_create_social_profiles_table.php';
    $createProfiles->up();

    $createPreferences = require dirname(__DIR__, 2) . '/database/migrations/2026_07_16_000002_create_social_site_preferences_table.php';
    $createPreferences->up();

    $addShareCustomised = require dirname(__DIR__, 2) . '/database/migrations/2026_08_31_000001_add_share_networks_customised_to_social_site_preferences.php';
    $addShareCustomised->up();

    $this->cacheEpoch = new SocialsCacheEpoch(new Repository(new ArrayStore));
    $this->action = new ImportLegacySocialProfilesAction(
        importLegacySocialsRegistry(),
        new HttpUrlValidator,
        $this->cacheEpoch,
    );
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

it('imports supported and labelled custom legacy profiles once without overwriting them on a later run', function (): void {
    $legacyMeta = [
        'social_links' => [
            ['type' => 'twitter', 'url' => '@capell', 'name' => 'Follow Capell'],
            ['type' => 'custom', 'url' => 'https://example.com/community', 'name' => 'Community'],
        ],
        'twitter' => '@ignored-fallback',
    ];

    Schema::getConnection()->table('sites')->insert(['id' => 1, 'meta' => json_encode($legacyMeta, JSON_THROW_ON_ERROR)]);

    $firstImport = $this->action->handle();
    $profiles = SocialProfile::query()->where('site_id', 1)->orderBy('sort_order')->get();
    $primaryProfile = $profiles->firstOrFail();
    $customProfile = $profiles->reverse()->firstOrFail();

    expect($firstImport->profilesImported)->toBe(2)
        ->and($profiles)->toHaveCount(2)
        ->and($primaryProfile->network_key)->toBe('x')
        ->and($primaryProfile->profile_value)->toBe('https://x.com/capell')
        ->and($primaryProfile->custom_label)->toBe('Follow Capell')
        ->and($customProfile->network_key)->toBeNull()
        ->and($customProfile->profile_value)->toBe('https://example.com/community')
        ->and($customProfile->custom_label)->toBe('Community')
        ->and($this->cacheEpoch->current(1))->toBe(2)
        ->and($this->invalidatedSurrogateKeys)->toBe([['site-1']]);

    $secondImport = $this->action->handle();

    expect($secondImport->profilesImported)->toBe(0)
        ->and($secondImport->skipped)->toBe(['Site 1 already has a Socials configuration.'])
        ->and(SocialProfile::query()->where('site_id', 1)->count())->toBe(2)
        ->and($this->cacheEpoch->current(1))->toBe(2)
        ->and($this->invalidatedSurrogateKeys)->toBe([['site-1']]);
});

it('treats saved preferences without profiles as an authoritative empty configuration', function (): void {
    $legacyMeta = [
        'social_links' => [
            ['type' => 'twitter', 'url' => '@capell', 'name' => 'Follow Capell'],
        ],
    ];

    Schema::getConnection()->table('sites')->insert(['id' => 1, 'meta' => json_encode($legacyMeta, JSON_THROW_ON_ERROR)]);
    SocialSitePreferences::query()->create([
        'site_id' => 1,
        'follow_label_style' => SocialLabelStyle::Icons,
        'follow_open_in_new_tab' => false,
        'share_network_keys' => [],
        'share_label_style' => SocialLabelStyle::Icons,
        'share_open_in_new_tab' => false,
    ]);

    $import = $this->action->handle();

    expect($import->profilesImported)->toBe(0)
        ->and($import->skipped)->toBe(['Site 1 already has a Socials configuration.'])
        ->and(SocialProfile::query()->where('site_id', 1)->exists())->toBeFalse()
        ->and($this->invalidatedSurrogateKeys)->toBe([]);
});

function importLegacySocialsRegistry(): SocialNetworkRegistry
{
    $registry = new SocialNetworkRegistry;

    foreach (BuiltInSocialNetworkDefinitions::all() as $definition) {
        $registry->register($definition);
    }

    return $registry;
}
