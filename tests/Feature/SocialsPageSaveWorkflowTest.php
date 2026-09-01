<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Socials\Actions\BuildShareSocialRenderDataAction;
use Capell\Socials\Actions\PrepareSocialSiteRenderDataAction;
use Capell\Socials\Data\PreparedSocialSiteData;
use Capell\Socials\Data\SharePageContextData;
use Capell\Socials\Data\SocialNetworkDefinitionData;
use Capell\Socials\Data\SocialProfileData;
use Capell\Socials\Data\SocialShareWidgetConfigData;
use Capell\Socials\Enums\SocialNetworkCapability;
use Capell\Socials\Filament\Pages\SocialsPage;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\NetworkProfileNormalizer;
use Capell\Socials\Support\ShareUrlGenerator;
use Capell\Socials\Support\SocialNetworkRegistry;
use Capell\Socials\Support\SocialNetworkRegistrySignature;
use Capell\Socials\Support\SocialsCacheEpoch;
use Capell\Tests\Fixtures\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function migrateSocialsPageSaveWorkflowTables(): void
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

beforeEach(function (): void {
    $panel = Panel::make()
        ->id('admin')
        ->path('admin')
        ->default();
    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
    Filament::bootCurrentPanel();
    Filament::setServingStatus();

    Permission::findOrCreate(SocialsPage::VIEW_PERMISSION, 'web');

    $authorizedUser = User::factory()->create();
    $authorizedUser->givePermissionTo(SocialsPage::VIEW_PERMISSION);
    $authorizedUser->assignRole('super_admin');

    test()->actingAs($authorizedUser);

    migrateSocialsPageSaveWorkflowTables();

    $language = Language::factory()->create();
    $this->site = Site::factory()->default()->create(['language_id' => $language->id]);
});

it('prepares the current recommended share set for uncustomised public output', function (): void {
    DB::table('social_site_preferences')->insert([
        'site_id' => $this->site->getKey(),
        'share_network_keys' => json_encode(['x']),
        'share_networks_customised' => false,
    ]);

    $prepared = PrepareSocialSiteRenderDataAction::run($this->site, 'en');

    expect($prepared->shareNetworkKeys)->toBe(['x', 'facebook', 'linkedin', 'pinterest', 'whatsapp', 'bluesky']);
});

it('uses registry recommendations or exact customised keys in the direct share fallback', function (): void {
    $context = new SharePageContextData('https://capell.test/guide', 'Guide', 'en');
    $config = new SocialShareWidgetConfigData('Share', null, null, 'start', null);

    DB::table('social_site_preferences')->insert([
        'site_id' => $this->site->getKey(),
        'share_network_keys' => json_encode(['x'], JSON_THROW_ON_ERROR),
        'share_networks_customised' => false,
    ]);

    $recommended = BuildShareSocialRenderDataAction::run($this->site, $context, $config);

    expect(array_column($recommended->links, 'networkKey'))->toBe(['x', 'facebook', 'linkedin', 'pinterest', 'whatsapp', 'bluesky']);

    DB::table('social_site_preferences')
        ->where('site_id', $this->site->getKey())
        ->update(['share_network_keys' => json_encode(['linkedin']), 'share_networks_customised' => true]);
    resolve(SocialsCacheEpoch::class)->increment((int) $this->site->getKey());

    $customised = BuildShareSocialRenderDataAction::run($this->site, $context, $config);

    expect(array_column($customised->links, 'networkKey'))->toBe(['linkedin']);
});

it('invalidates prepared and direct-share caches when output-affecting registry state changes', function (): void {
    SocialProfile::query()->create([
        'site_id' => $this->site->getKey(),
        'network_key' => 'x',
        'profile_value' => 'capell',
        'sort_order' => 0,
        'is_enabled' => true,
    ]);

    $context = new SharePageContextData('https://capell.test/guide', 'Guide', 'en');
    $config = new SocialShareWidgetConfigData(
        heading: 'Share',
        labelStyle: null,
        openInNewTab: null,
        alignment: 'start',
        networkKeys: ['x'],
    );
    $preparedBefore = PrepareSocialSiteRenderDataAction::run($this->site, 'en');
    $shareBefore = BuildShareSocialRenderDataAction::run($this->site, $context, $config);
    $registry = resolve(SocialNetworkRegistry::class);
    $original = $registry->get('x');

    expect($original)->toBeInstanceOf(SocialNetworkDefinitionData::class);

    $normalizer = new NetworkProfileNormalizer(['social.example'], 'social.example', '/');
    $registry->replace(new SocialNetworkDefinitionData(
        key: 'x',
        aliases: ['twitter'],
        label: 'X alternative',
        icon: 'alternative-x-mark',
        capabilities: [SocialNetworkCapability::Follow, SocialNetworkCapability::Share],
        normalizer: $normalizer,
        validator: $normalizer,
        shareUrlGenerator: new ShareUrlGenerator('https://social.example/share', ['url' => 'url']),
    ));

    $preparedAfter = PrepareSocialSiteRenderDataAction::run($this->site, 'en');
    $shareAfter = BuildShareSocialRenderDataAction::run($this->site, $context, $config);

    expect($preparedBefore->profiles(null)[0]->url)->toBe('https://x.com/capell')
        ->and($preparedAfter->profiles(null)[0]->url)->toBe('https://social.example/capell')
        ->and($preparedAfter->profiles(null)[0]->label)->toBe('X alternative')
        ->and($preparedAfter->profiles(null)[0]->icon)->toBe('alternative-x-mark')
        ->and($shareBefore->links[0]->url)->toStartWith('https://x.com/intent/post?')
        ->and($shareAfter->links[0]->url)->toStartWith('https://social.example/share?');
});

it('saves valid profiles and preferences through the Livewire form', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->site->getKey(),
            'profiles' => [
                [
                    'network_key' => 'x',
                    'profile_value' => 'capell',
                    'custom_label' => null,
                    'is_enabled' => true,
                ],
                [
                    'network_key' => '__custom',
                    'profile_value' => 'https://example.com/community',
                    'custom_label' => 'Community',
                    'is_enabled' => true,
                ],
            ],
            'follow_label_style' => 'icons',
            'share_label_style' => 'icons',
            'share_network_keys' => ['x'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(SocialProfile::query()->where('site_id', $this->site->getKey())->count())->toBe(2)
        ->and(SocialSitePreferences::query()->where('site_id', $this->site->getKey())->exists())->toBeTrue();
});

it('rotates the cache epoch when Socials models are written directly', function (): void {
    $cacheEpoch = resolve(SocialsCacheEpoch::class);
    $siteId = (int) $this->site->getKey();

    $initialEpoch = $cacheEpoch->current($siteId);

    $profile = SocialProfile::query()->create([
        'site_id' => $siteId,
        'network_key' => 'x',
        'profile_value' => 'capell',
        'custom_label' => null,
        'sort_order' => 0,
        'is_enabled' => true,
    ]);
    $epochAfterCreate = $cacheEpoch->current($siteId);

    $profile->update(['profile_value' => 'capellhq']);
    $epochAfterUpdate = $cacheEpoch->current($siteId);

    $profile->delete();

    expect($epochAfterCreate)->toBeGreaterThan($initialEpoch)
        ->and($epochAfterUpdate)->toBeGreaterThan($epochAfterCreate)
        ->and($cacheEpoch->current($siteId))->toBeGreaterThan($epochAfterUpdate);
});

it('rejects a duplicate network with a validation error instead of an exception', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->site->getKey(),
            'profiles' => [
                ['network_key' => 'x', 'profile_value' => 'capell', 'custom_label' => null, 'is_enabled' => true],
                ['network_key' => 'x', 'profile_value' => 'capellhq', 'custom_label' => null, 'is_enabled' => true],
            ],
            'follow_label_style' => 'icons',
            'share_label_style' => 'icons',
        ])
        ->call('save')
        ->assertHasErrors();

    expect(SocialProfile::query()->where('site_id', $this->site->getKey())->exists())->toBeFalse();
});

it('rejects a network-specific URL entered for a different network', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->site->getKey(),
            'profiles' => [
                ['network_key' => 'x', 'profile_value' => 'https://facebook.com/capell', 'custom_label' => null, 'is_enabled' => true],
            ],
            'follow_label_style' => 'icons',
            'share_label_style' => 'icons',
        ])
        ->call('save')
        ->assertHasErrors();

    expect(SocialProfile::query()->where('site_id', $this->site->getKey())->exists())->toBeFalse();
});

it('caches prepared social render data as a serialization-stable array, never a hydrated DTO', function (): void {
    $siteId = (int) $this->site->getKey();

    SocialProfile::query()->create([
        'site_id' => $siteId,
        'network_key' => 'x',
        'profile_value' => 'capell',
        'custom_label' => null,
        'sort_order' => 0,
        'is_enabled' => true,
    ]);

    Cache::flush();

    // First call populates the cache; second call reads it back.
    $first = PrepareSocialSiteRenderDataAction::run($this->site, 'en');
    $second = PrepareSocialSiteRenderDataAction::run($this->site, 'en');

    // Locate the raw cached payload and prove it is a plain array, not a typed
    // object. Storing a hydrated DTO is exactly what poisons the cache
    // (__PHP_Incomplete_Class after an autoload-map change).
    $epoch = resolve(SocialsCacheEpoch::class)->current($siteId);
    $registryFingerprint = SocialNetworkRegistrySignature::for(resolve(Capell\Socials\Contracts\SocialNetworkRegistry::class), 'en');
    $cacheKey = sprintf('capell-socials:prepared:%s:%d:%s:%d:%s', 'v2-array', $siteId, 'en', $epoch, $registryFingerprint);
    $rawCached = Cache::get($cacheKey);

    expect($rawCached)->toBeArray()
        ->and(is_object($rawCached))->toBeFalse()
        ->and($first)->toBeInstanceOf(PreparedSocialSiteData::class)
        ->and($second)->toBeInstanceOf(PreparedSocialSiteData::class)
        ->and($second->hasConfiguration)->toBeTrue()
        ->and($second->profiles)->toHaveCount(1)
        ->and($second->profiles(null)[0]->networkKey)->toBe('x')
        ->and($second->followLabelStyle)->toBe($first->followLabelStyle);
});

it('rehydrates a usable prepared social DTO from a cached array across repopulation', function (): void {
    $siteId = (int) $this->site->getKey();

    SocialProfile::query()->create([
        'site_id' => $siteId,
        'network_key' => 'x',
        'profile_value' => 'capell',
        'custom_label' => null,
        'sort_order' => 0,
        'is_enabled' => true,
    ]);

    Cache::flush();

    // Prime the cache, then re-read purely from the cached array (DB rows are
    // irrelevant to the second read — it must rehydrate from the stored array).
    PrepareSocialSiteRenderDataAction::run($this->site, 'en');
    SocialProfile::query()->where('site_id', $siteId)->update(['is_enabled' => false]);

    $fromCache = PrepareSocialSiteRenderDataAction::run($this->site, 'en');

    // Still reflects the primed state (enabled profile) because it came from the
    // cached array, and it is a fully usable typed DTO — not Incomplete_Class.
    expect($fromCache)->toBeInstanceOf(PreparedSocialSiteData::class)
        ->and($fromCache->profiles)->toHaveCount(1)
        ->and($fromCache->profiles(null)[0])->toBeInstanceOf(SocialProfileData::class)
        ->and($fromCache->profiles(null)[0]->networkKey)->toBe('x');
});

it('rejects a custom link that is not an http URL', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->site->getKey(),
            'profiles' => [
                ['network_key' => '__custom', 'profile_value' => 'javascript:alert(1)', 'custom_label' => 'Nope', 'is_enabled' => true],
            ],
            'follow_label_style' => 'icons',
            'share_label_style' => 'icons',
        ])
        ->call('save')
        ->assertHasErrors();

    expect(SocialProfile::query()->where('site_id', $this->site->getKey())->exists())->toBeFalse();
});
