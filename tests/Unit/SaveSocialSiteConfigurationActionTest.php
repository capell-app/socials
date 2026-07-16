<?php

declare(strict_types=1);

use Capell\Core\Events\FrontendSurrogateKeysInvalidated;
use Capell\Core\Models\Site;
use Capell\Socials\Actions\SaveSocialSiteConfigurationAction;
use Capell\Socials\Data\SocialProfileConfigurationData;
use Capell\Socials\Data\SocialSitePreferencesData;
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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
    $this->previousFacadeApplication = Facade::getFacadeApplication();
    $this->invalidatedSurrogateKeys = [];

    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $container = new Container;
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

    DB::table('sites')->insert([
        ['id' => 1],
        ['id' => 2],
    ]);

    $this->cacheEpoch = new SocialsCacheEpoch(new Repository(new ArrayStore));
    $this->action = new SaveSocialSiteConfigurationAction(
        saveSocialsRegistry(),
        new HttpUrlValidator,
        $this->cacheEpoch,
    );
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($this->previousFacadeApplication);
    Container::setInstance($this->previousContainer);
});

it('transactionally replaces a site configuration and invalidates only its cache epoch after commit', function (): void {
    SocialProfile::query()->create([
        'site_id' => 1,
        'network_key' => 'facebook',
        'profile_value' => 'previous',
        'sort_order' => 0,
        'is_enabled' => true,
    ]);

    $preferences = new SocialSitePreferencesData(
        saveSocialsRegistry(),
        SocialLabelStyle::Labels,
        true,
        ['twitter', 'linkedin'],
        SocialLabelStyle::IconsAndLabels,
        true,
    );

    $savedPreferences = $this->action->handle(saveSocialsSite(1), [
        new SocialProfileConfigurationData('twitter', ' @capell ', ' Follow Capell ', true),
        new SocialProfileConfigurationData(null, ' https://example.com/community ', ' Community ', false),
    ], $preferences);

    $profiles = SocialProfile::query()
        ->where('site_id', 1)
        ->orderBy('sort_order')
        ->get();

    expect($savedPreferences->site_id)->toBe(1)
        ->and($profiles)->toHaveCount(2)
        ->and($profiles[0]->network_key)->toBe('x')
        ->and($profiles[0]->profile_value)->toBe('@capell')
        ->and($profiles[0]->custom_label)->toBe('Follow Capell')
        ->and($profiles[0]->sort_order)->toBe(0)
        ->and($profiles[1]->network_key)->toBeNull()
        ->and($profiles[1]->profile_value)->toBe('https://example.com/community')
        ->and($profiles[1]->custom_label)->toBe('Community')
        ->and($profiles[1]->is_enabled)->toBeFalse()
        ->and(SocialSitePreferences::query()->where('site_id', 1)->sole()->share_network_keys)->toBe(['x', 'linkedin'])
        ->and($this->cacheEpoch->current(1))->toBe(2)
        ->and($this->cacheEpoch->current(2))->toBe(1)
        ->and($this->invalidatedSurrogateKeys)->toBe([['site-1']]);
});

it('rejects duplicate canonical networks and unsafe or incomplete custom links without changing a site', function (): void {
    SocialProfile::query()->create([
        'site_id' => 1,
        'network_key' => 'facebook',
        'profile_value' => 'capell',
        'sort_order' => 0,
        'is_enabled' => true,
    ]);

    $preferences = new SocialSitePreferencesData(saveSocialsRegistry());

    expect(fn (): SocialSitePreferences => $this->action->handle(saveSocialsSite(1), [
        new SocialProfileConfigurationData('x', 'capell'),
        new SocialProfileConfigurationData('twitter', 'capell'),
    ], $preferences))->toThrow(InvalidArgumentException::class);

    expect(fn (): SocialSitePreferences => $this->action->handle(saveSocialsSite(1), [
        new SocialProfileConfigurationData(null, 'javascript:alert(1)', 'Unsafe'),
    ], $preferences))->toThrow(InvalidArgumentException::class);

    expect(fn (): SocialSitePreferences => $this->action->handle(saveSocialsSite(1), [
        new SocialProfileConfigurationData(null, 'https://example.com', '  '),
    ], $preferences))->toThrow(InvalidArgumentException::class);

    expect(SocialProfile::query()->where('site_id', 1)->sole()->network_key)->toBe('facebook')
        ->and($this->cacheEpoch->current(1))->toBe(1)
        ->and($this->invalidatedSurrogateKeys)->toBe([]);
});

function saveSocialsRegistry(): SocialNetworkRegistry
{
    $registry = new SocialNetworkRegistry;

    foreach (BuiltInSocialNetworkDefinitions::all() as $definition) {
        $registry->register($definition);
    }

    return $registry;
}

function saveSocialsSite(int $siteId): Site
{
    $site = new Site;
    $site->setAttribute($site->getKeyName(), $siteId);

    return $site;
}
