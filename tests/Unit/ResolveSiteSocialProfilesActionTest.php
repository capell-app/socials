<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Socials\Actions\ResolveSiteSocialProfilesAction;
use Capell\Socials\Contracts\SocialProfilesResolver;
use Capell\Socials\Data\SocialProfileConfigurationData;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\BuiltIns\BuiltInSocialNetworkDefinitions;
use Capell\Socials\Support\HttpUrlValidator;
use Capell\Socials\Support\SocialNetworkRegistry;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
    $this->previousFacadeApplication = Facade::getFacadeApplication();

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
});

afterEach(function (): void {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($this->previousFacadeApplication);
    Container::setInstance($this->previousContainer);
});

it('returns an empty result when the optional socials tables are not installed', function (): void {
    Schema::drop('social_site_preferences');
    Schema::drop('social_profiles');

    $action = new ResolveSiteSocialProfilesAction(socialResolverRegistry(), new HttpUrlValidator);
    $site = socialResolverSite(1);

    expect($action->hasConfiguration($site))->toBeFalse()
        ->and($action->resolve($site, 'en')->profiles)->toBe([]);
});

it('implements the public resolver contract and returns only enabled ordered public-safe profiles', function (): void {
    $registry = socialResolverRegistry();
    $action = new ResolveSiteSocialProfilesAction($registry, new HttpUrlValidator);
    $site = socialResolverSite(1);

    SocialProfile::query()->insert([
        [
            'site_id' => 1,
            'network_key' => 'missing-network',
            'profile_value' => 'ignored',
            'custom_label' => null,
            'sort_order' => 0,
            'is_enabled' => true,
        ],
        [
            'site_id' => 1,
            'network_key' => 'twitter',
            'profile_value' => '@capell',
            'custom_label' => 'Follow Capell on X',
            'sort_order' => 1,
            'is_enabled' => true,
        ],
        [
            'site_id' => 1,
            'network_key' => null,
            'profile_value' => 'https://example.com/community',
            'custom_label' => 'Community',
            'sort_order' => 2,
            'is_enabled' => true,
        ],
        [
            'site_id' => 1,
            'network_key' => 'facebook',
            'profile_value' => 'capell',
            'custom_label' => null,
            'sort_order' => 3,
            'is_enabled' => false,
        ],
        [
            'site_id' => 1,
            'network_key' => null,
            'profile_value' => 'javascript:alert(1)',
            'custom_label' => 'Unsafe',
            'sort_order' => 4,
            'is_enabled' => true,
        ],
    ]);

    $profiles = $action->resolve($site, 'en');

    expect($action)->toBeInstanceOf(SocialProfilesResolver::class)
        ->and($profiles->profiles)->toHaveCount(2)
        ->and($profiles->profiles[0]->networkKey)->toBe('x')
        ->and($profiles->profiles[0]->label)->toBe('Follow Capell on X')
        ->and($profiles->profiles[0]->url)->toBe('https://x.com/capell')
        ->and($profiles->profiles[0]->handle)->toBe('capell')
        ->and($profiles->profiles[1]->networkKey)->toBe('custom')
        ->and($profiles->profiles[1]->label)->toBe('Community')
        ->and($profiles->profiles[1]->icon)->toBe('link')
        ->and($profiles->profiles[1]->capabilities)->toBe([])
        ->and($profiles->forNetwork('x')?->url)->toBe('https://x.com/capell')
        ->and($profiles->sameAsUrls())->toBe(['https://x.com/capell']);
});

it('omits invalid registered and incomplete custom profiles without breaking the site result', function (): void {
    $action = new ResolveSiteSocialProfilesAction(socialResolverRegistry(), new HttpUrlValidator);

    SocialProfile::query()->insert([
        [
            'site_id' => 2,
            'network_key' => 'instagram',
            'profile_value' => 'https://evil.example/capell',
            'custom_label' => null,
            'sort_order' => 0,
            'is_enabled' => true,
        ],
        [
            'site_id' => 2,
            'network_key' => null,
            'profile_value' => 'https://example.com/no-label',
            'custom_label' => null,
            'sort_order' => 1,
            'is_enabled' => true,
        ],
    ]);

    expect(runBoundAction(ResolveSiteSocialProfilesAction::class, $action, socialResolverSite(2), 'en')->profiles)->toBe([]);
});

it('distinguishes an untouched site from an intentionally empty package configuration', function (): void {
    $action = new ResolveSiteSocialProfilesAction(socialResolverRegistry(), new HttpUrlValidator);
    $site = socialResolverSite(3);

    expect($action->hasConfiguration($site))->toBeFalse();

    SocialSitePreferences::query()->insert(['site_id' => 3]);

    expect($action->hasConfiguration($site))->toBeTrue();
});

it('builds public-safe preview data from unsaved configuration through the same resolver', function (): void {
    $action = new ResolveSiteSocialProfilesAction(socialResolverRegistry(), new HttpUrlValidator);

    $registered = $action->resolveConfiguration(new SocialProfileConfigurationData('twitter', '@capell', 'Follow Capell'));
    $custom = $action->resolveConfiguration(new SocialProfileConfigurationData(null, 'https://example.com/community', 'Community'));
    $invalid = $action->resolveConfiguration(new SocialProfileConfigurationData(null, 'javascript:alert(1)', 'Unsafe'));

    expect($registered?->networkKey)->toBe('x')
        ->and($registered?->url)->toBe('https://x.com/capell')
        ->and($registered?->label)->toBe('Follow Capell')
        ->and($custom?->networkKey)->toBe('custom')
        ->and($custom?->icon)->toBe('link')
        ->and($invalid)->toBeNull();
});

function socialResolverRegistry(): SocialNetworkRegistry
{
    $registry = new SocialNetworkRegistry;

    foreach (BuiltInSocialNetworkDefinitions::all() as $definition) {
        $registry->register($definition);
    }

    return $registry;
}

function socialResolverSite(int $siteId): Site
{
    $site = new Site;
    $site->setAttribute($site->getKeyName(), $siteId);

    return $site;
}
