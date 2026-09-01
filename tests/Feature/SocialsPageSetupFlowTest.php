<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Socials\Filament\Pages\SocialsPage;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Tests\Fixtures\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function loadSocialsSetupFlowTranslations(): void
{
    /** @var array<string, mixed> $messages */
    $messages = require dirname(__DIR__, 2) . '/resources/lang/en/socials.php';

    $lines = [];
    $flatten = static function (array $tree, string $prefix) use (&$flatten, &$lines): void {
        foreach ($tree as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $flatten($value, $dotted);

                continue;
            }

            $lines['socials.' . $dotted] = $value;
        }
    };
    $flatten($messages, '');

    resolve(Translator::class)->addLines($lines, 'en', 'capell-socials');
}

function migrateSocialsSetupFlowTables(): void
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

    loadSocialsSetupFlowTranslations();

    Permission::findOrCreate(SocialsPage::VIEW_PERMISSION, 'web');

    $authorizedUser = User::factory()->create();
    $authorizedUser->givePermissionTo(SocialsPage::VIEW_PERMISSION);
    $authorizedUser->assignRole('super_admin');

    test()->actingAs($authorizedUser);

    migrateSocialsSetupFlowTables();

    $languageA = Language::factory()->create();
    $languageB = Language::factory()->create();
    $this->siteA = Site::factory()->create(['name' => 'Alpha site', 'language_id' => $languageA->getKey()]);
    $this->siteB = Site::factory()->create(['name' => 'Bravo site', 'language_id' => $languageB->getKey()]);
    $this->siteAId = (int) $this->siteA->getKey();
    $this->siteBId = (int) $this->siteB->getKey();
});

it('saves a network-first registered profile with a normalised destination and no public label', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => 'x', 'profile_value' => '@capell', 'is_enabled' => true],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $profile = SocialProfile::query()->where('site_id', $this->siteAId)->sole();

    expect($profile->network_key)->toBe('x')
        ->and($profile->profile_value)->toBe('@capell')
        ->and($profile->custom_label)->toBeNull()
        ->and($profile->is_enabled)->toBeTrue();
});

it('keeps a custom link with its required public label and preserves added order', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => 'linkedin', 'profile_value' => 'capell', 'is_enabled' => true],
                ['network_key' => '__custom', 'profile_value' => 'https://example.com/community', 'custom_label' => 'Community', 'is_enabled' => true],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $profiles = SocialProfile::query()->where('site_id', $this->siteAId)->orderBy('sort_order')->get();
    $customProfile = SocialProfile::query()->where('site_id', $this->siteAId)->whereNull('network_key')->sole();

    expect($profiles->pluck('network_key')->all())->toBe(['linkedin', null])
        ->and($profiles->pluck('sort_order')->all())->toBe([0, 1])
        ->and($customProfile->custom_label)->toBe('Community');
});

it('rejects a custom link with no public label', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => '__custom', 'profile_value' => 'https://example.com/x', 'custom_label' => null, 'is_enabled' => true],
            ],
        ])
        ->call('save')
        ->assertHasErrors();

    expect(SocialProfile::query()->where('site_id', $this->siteAId)->exists())->toBeFalse();
});

it('rejects two profiles for the same registered network', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => 'x', 'profile_value' => 'capell', 'is_enabled' => true],
                ['network_key' => 'x', 'profile_value' => 'capellhq', 'is_enabled' => true],
            ],
        ])
        ->call('save')
        ->assertHasErrors();

    expect(SocialProfile::query()->where('site_id', $this->siteAId)->exists())->toBeFalse();
});

it('rejects a handle that fails the selected network validation and normalisation', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => 'x', 'profile_value' => 'https://facebook.com/capell', 'is_enabled' => true],
            ],
        ])
        ->call('save')
        ->assertHasErrors();

    expect(SocialProfile::query()->where('site_id', $this->siteAId)->exists())->toBeFalse();
});

it('collapses a completed profile to its network, normalised destination and enabled summary', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => 'x', 'profile_value' => '@capell', 'is_enabled' => false],
            ],
        ])
        ->assertSee('https://x.com/capell')
        ->assertSee('Hidden');
});

it('hides the public label field for a plain registered profile', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => 'x', 'profile_value' => '@capell', 'is_enabled' => true],
            ],
        ])
        ->assertDontSee('Public label shown to visitors');
});

it('shows the required public label field for a custom link', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => '__custom', 'profile_value' => 'https://example.com/x', 'custom_label' => 'Docs', 'is_enabled' => true],
            ],
        ])
        ->assertSee('Public label shown to visitors');
});

it('keeps an intentional public-label override on a registered profile', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => 'x', 'profile_value' => '@capell', 'show_public_label' => true, 'custom_label' => 'Follow us on X', 'is_enabled' => true],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(SocialProfile::query()->where('site_id', $this->siteAId)->sole()->custom_label)->toBe('Follow us on X');
});

it('preserves a registered label while its real Filament toggle is reopened and clears it only when saved off', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => 'x', 'profile_value' => '@capell', 'show_public_label' => true, 'custom_label' => 'Follow us on X', 'is_enabled' => true],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $component = Livewire::test(SocialsPage::class);
    $profileRows = $component->get('data.profiles');

    throw_unless(is_array($profileRows), RuntimeException::class, 'Expected the Socials repeater state to be an array.');

    $profileKey = array_key_first($profileRows);

    throw_unless(is_string($profileKey), RuntimeException::class, 'Expected Filament to key the stored Socials repeater row with a UUID.');

    $component
        ->assertSet("data.profiles.{$profileKey}.show_public_label", true)
        ->set("data.profiles.{$profileKey}.show_public_label", false)
        ->assertSet("data.profiles.{$profileKey}.custom_label", 'Follow us on X')
        ->set("data.profiles.{$profileKey}.show_public_label", true)
        ->call('save')
        ->assertHasNoErrors();

    expect(SocialProfile::query()->where('site_id', $this->siteAId)->sole()->custom_label)->toBe('Follow us on X');

    $component = Livewire::test(SocialsPage::class);
    $profileRows = $component->get('data.profiles');

    throw_unless(is_array($profileRows), RuntimeException::class, 'Expected the Socials repeater state to be an array.');

    $profileKey = array_key_first($profileRows);

    throw_unless(is_string($profileKey), RuntimeException::class, 'Expected Filament to key the stored Socials repeater row with a UUID.');

    $component
        ->set("data.profiles.{$profileKey}.show_public_label", false)
        ->call('save')
        ->assertHasNoErrors();

    expect(SocialProfile::query()->where('site_id', $this->siteAId)->sole()->custom_label)->toBeNull();
});

it('persists the capability-backed recommended share set by default and marks sharing uncustomised', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => 'x', 'profile_value' => 'capell', 'is_enabled' => true],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $preferences = SocialSitePreferences::query()->where('site_id', $this->siteAId)->sole();

    expect($preferences->share_networks_customised)->toBeFalse()
        ->and($preferences->share_network_keys)->toBe(['x', 'facebook', 'linkedin', 'pinterest', 'whatsapp', 'bluesky']);
});

it('persists only the chosen share networks once the editor customises sharing', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => 'x', 'profile_value' => 'capell', 'is_enabled' => true],
            ],
            'share_networks_customised' => true,
            'share_network_keys' => ['linkedin', 'x'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $preferences = SocialSitePreferences::query()->where('site_id', $this->siteAId)->sole();

    expect($preferences->share_networks_customised)->toBeTrue()
        ->and($preferences->share_network_keys)->toBe(['linkedin', 'x']);
});

it('renders the unsaved follow preview from the current form state', function (): void {
    $component = Livewire::test(SocialsPage::class);
    $component->fillForm([
        'site_id' => $this->siteAId,
        'profiles' => [
            ['network_key' => 'x', 'profile_value' => '@capell', 'is_enabled' => true],
            ['network_key' => 'linkedin', 'profile_value' => 'capell', 'is_enabled' => false],
        ],
    ]);

    /** @var SocialsPage $page */
    $page = $component->instance();
    $preview = $page->getFollowPreviewProperty();

    expect($preview->profiles)->toHaveCount(1)
        ->and($preview->profiles[0]->url)->toBe('https://x.com/capell')
        ->and(SocialProfile::query()->where('site_id', $this->siteAId)->exists())->toBeFalse();
});

it('moves from idle to dirty to saved through one persistent save action', function (): void {
    $component = Livewire::test(SocialsPage::class);
    $component->assertSet('saveState', 'idle')
        ->assertSee('Saving…');

    /** @var SocialsPage $page */
    $page = $component->instance();
    expect($page->isDirty())->toBeFalse();

    $component->fillForm([
        'site_id' => $this->siteAId,
        'profiles' => [
            ['network_key' => 'x', 'profile_value' => 'capell', 'is_enabled' => true],
        ],
    ]);

    /** @var SocialsPage $page */
    $page = $component->instance();
    expect($page->isDirty())->toBeTrue();

    $component->assertSee('You have unsaved changes.');

    $component->call('save')
        ->assertHasNoErrors()
        ->assertSet('saveState', 'saved')
        ->assertSee('All changes saved.');

    /** @var SocialsPage $page */
    $page = $component->instance();
    expect($page->isDirty())->toBeFalse();

    $profileRows = $component->get('data.profiles');
    throw_unless(is_array($profileRows), RuntimeException::class, 'Expected the Socials repeater state to be an array.');
    $profileKey = array_key_first($profileRows);
    throw_unless(is_string($profileKey), RuntimeException::class, 'Expected a UUID-keyed Socials repeater row.');

    $component->set("data.profiles.{$profileKey}.profile_value", 'capellhq')
        ->assertSet('saveState', 'idle')
        ->assertSee('You have unsaved changes.');
});

it('reports an error state when a save is rejected', function (): void {
    Livewire::test(SocialsPage::class)
        ->fillForm([
            'site_id' => $this->siteAId,
            'profiles' => [
                ['network_key' => 'x', 'profile_value' => 'https://facebook.com/capell', 'is_enabled' => true],
            ],
        ])
        ->call('save')
        ->assertHasErrors()
        ->assertSet('saveState', 'error');

    expect(SocialProfile::query()->where('site_id', $this->siteAId)->exists())->toBeFalse();
});

it('prompts to save, discard or stay when switching site with unsaved changes', function (): void {
    $component = Livewire::test(SocialsPage::class);
    $component->fillForm([
        'site_id' => $this->siteAId,
        'profiles' => [
            ['network_key' => 'x', 'profile_value' => 'capell', 'is_enabled' => true],
        ],
    ])->set('data.site_id', (string) $this->siteBId);

    $component->assertSet('showSiteSwitchPrompt', true)
        ->assertSet('activeSiteId', (string) $this->siteAId);

    $component->call('stayOnCurrentSite')
        ->assertSet('showSiteSwitchPrompt', false)
        ->assertSet('data.site_id', (string) $this->siteAId)
        ->assertSet('pendingSiteId', null);

    /** @var SocialsPage $page */
    $page = $component->instance();
    expect($page->isDirty())->toBeTrue();
});

it('saves the current site before switching when the editor chooses save and switch', function (): void {
    $component = Livewire::test(SocialsPage::class);
    $component->fillForm([
        'site_id' => $this->siteAId,
        'profiles' => [
            ['network_key' => 'x', 'profile_value' => 'capell', 'is_enabled' => true],
        ],
    ])->set('data.site_id', (string) $this->siteBId)
        ->call('saveAndSwitchSite');

    expect(SocialProfile::query()->where('site_id', $this->siteAId)->count())->toBe(1);

    $component->assertSet('showSiteSwitchPrompt', false)
        ->assertSet('activeSiteId', (string) $this->siteBId)
        ->assertSet('pendingSiteId', null);

    /** @var SocialsPage $page */
    $page = $component->instance();
    expect($page->isDirty())->toBeFalse();
});

it('discards unsaved changes when the editor chooses discard and switch', function (): void {
    $component = Livewire::test(SocialsPage::class);
    $component->fillForm([
        'site_id' => $this->siteAId,
        'profiles' => [
            ['network_key' => 'x', 'profile_value' => 'capell', 'is_enabled' => true],
        ],
    ])->set('data.site_id', (string) $this->siteBId)
        ->call('discardAndSwitchSite');

    expect(SocialProfile::query()->where('site_id', $this->siteAId)->exists())->toBeFalse();

    $component->assertSet('activeSiteId', (string) $this->siteBId)
        ->assertSet('showSiteSwitchPrompt', false)
        ->assertSet('pendingSiteId', null);

    /** @var SocialsPage $page */
    $page = $component->instance();
    expect($page->isDirty())->toBeFalse();
});

it('stays on the current site with dirty state intact when save and switch fails validation', function (): void {
    $component = Livewire::test(SocialsPage::class);
    $component->fillForm([
        'site_id' => $this->siteAId,
        'profiles' => [
            ['network_key' => 'x', 'profile_value' => 'https://facebook.com/capell', 'is_enabled' => true],
        ],
    ])->set('data.site_id', (string) $this->siteBId)
        ->call('saveAndSwitchSite')
        ->assertHasErrors()
        ->assertSet('saveState', 'error')
        ->assertSet('activeSiteId', (string) $this->siteAId)
        ->assertSet('pendingSiteId', (string) $this->siteBId)
        ->assertSet('showSiteSwitchPrompt', true);

    expect(SocialProfile::query()->where('site_id', $this->siteAId)->exists())->toBeFalse();

    /** @var SocialsPage $page */
    $page = $component->instance();
    expect($page->isDirty())->toBeTrue();
});

it('switches site immediately when there are no unsaved changes', function (): void {
    Livewire::test(SocialsPage::class)
        ->assertSet('activeSiteId', (string) $this->siteAId)
        ->set('data.site_id', (string) $this->siteBId)
        ->assertSet('showSiteSwitchPrompt', false)
        ->assertSet('activeSiteId', (string) $this->siteBId);
});

it('only lists sites the actor may manage and refuses a save for an unassigned site', function (): void {
    test()->actingAs(socialsSetupFlowScopedUser(collect([$this->siteAId])));

    $component = Livewire::test(SocialsPage::class);
    $component->assertSet('activeSiteId', (string) $this->siteAId);

    $component->set('data.site_id', (string) $this->siteBId)
        ->call('save')
        ->assertHasErrors();

    expect(SocialProfile::query()->where('site_id', $this->siteBId)->exists())->toBeFalse();
});

it('loads a site configuration with a bounded number of queries', function (): void {
    SocialProfile::query()->insert([
        ['site_id' => $this->siteAId, 'network_key' => 'x', 'profile_value' => '@capell', 'custom_label' => null, 'sort_order' => 0, 'is_enabled' => true],
        ['site_id' => $this->siteAId, 'network_key' => 'linkedin', 'profile_value' => 'capell', 'custom_label' => null, 'sort_order' => 1, 'is_enabled' => true],
        ['site_id' => $this->siteAId, 'network_key' => null, 'profile_value' => 'https://example.com/x', 'custom_label' => 'Extra', 'sort_order' => 2, 'is_enabled' => true],
    ]);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    Livewire::test(SocialsPage::class);

    expect($queries)->toBeLessThan(25);
});

/**
 * @param  SupportCollection<int, int>  $assignedSiteIds
 */
function socialsSetupFlowScopedUser(SupportCollection $assignedSiteIds): User
{
    $user = new class extends User
    {
        /** @var SupportCollection<int, int> */
        public SupportCollection $assignedSiteIds;

        public function isGlobalAdmin(): bool
        {
            return false;
        }

        /** @return SupportCollection<int, int> */
        public function getAssignedSiteIds(): SupportCollection
        {
            return $this->assignedSiteIds;
        }

        /**
         * @param  iterable<string>|string  $abilities
         * @param  array<mixed>|mixed  $arguments
         */
        public function can($abilities, $arguments = []): bool
        {
            return $abilities === SocialsPage::VIEW_PERMISSION;
        }
    };

    $user->assignedSiteIds = $assignedSiteIds;

    return $user;
}
