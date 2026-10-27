<?php

declare(strict_types=1);

use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Socials\Actions\BuildFollowSocialRenderDataAction;
use Capell\Socials\Data\SocialFollowWidgetConfigData;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Tests\Fixtures\SocialsBuilderForm;
use Capell\Tests\Fixtures\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);
    foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.php') ?: [] as $path) {
        (require $path)->up();
    }

    $this->siteA = Site::factory()->create();
    $this->siteB = Site::factory()->create();
    $this->profileA = SocialProfile::query()->create([
        'site_id' => $this->siteA->getKey(), 'network_key' => 'linkedin', 'profile_value' => 'capell',
        'custom_label' => 'Studio team', 'is_enabled' => true, 'sort_order' => 0,
    ]);
    $this->profileB = SocialProfile::query()->create([
        'site_id' => $this->siteB->getKey(), 'network_key' => 'x', 'profile_value' => 'foreign-team',
        'custom_label' => 'Foreign team sentinel', 'is_enabled' => true, 'sort_order' => 0,
    ]);
});

it('offers named current site choices and renders only the selected public destination', function (): void {
    $component = Livewire::test(SocialsBuilderForm::class, ['siteId' => $this->siteA->getKey()]);
    $key = array_key_first($component->get('data.blocks'));
    $component->set("data.blocks.{$key}.data.profile_selection", 'choose')
        ->assertSee('Studio team')
        ->assertDontSee('Foreign team sentinel')
        ->set("data.blocks.{$key}.data.profile_ids", [(string) $this->profileA->id])
        ->call('save')->assertHasNoErrors();
    $state = array_values($component->get('saved.blocks'))[0]['data'];
    expect($state['profile_ids'])->toBe([$this->profileA->id]);
    $rendered = BuildFollowSocialRenderDataAction::run($this->siteA, 'en', SocialFollowWidgetConfigData::fromState($state));
    $html = view('capell-socials::blocks.socials', ['renderData' => $rendered])->render();
    expect($html)->toContain('https://linkedin.com/in/capell')
        ->not->toContain('foreign-team', 'profile_ids', 'site_id', 'wire:', 'data-model-id');
});

it('preserves inherited all and intentional empty selections through form hydration and saving', function (?array $ids): void {
    $component = Livewire::test(SocialsBuilderForm::class, [
        'siteId' => $this->siteA->getKey(), 'state' => ['profile_ids' => $ids],
    ])->call('save')->assertHasNoErrors();
    $state = array_values($component->get('saved.blocks'))[0]['data'];
    expect($state['profile_ids'])->toBe($ids);
    $rendered = BuildFollowSocialRenderDataAction::run($this->siteA, 'en', SocialFollowWidgetConfigData::fromState($state));
    expect($rendered->profiles)->toHaveCount($ids === null ? 1 : 0);
})->with(['all' => [null], 'empty' => [[]]]);

it('warns about deleted or disabled selections and rejects foreign profile IDs', function (string $kind): void {
    $selectedId = $this->profileA->getKey();
    if ($kind === 'deleted') {
        $this->profileA->delete();
    } elseif ($kind === 'disabled') {
        $this->profileA->update(['is_enabled' => false]);
    } else {
        $selectedId = $this->profileB->getKey();
    }

    $component = Livewire::test(SocialsBuilderForm::class, [
        'siteId' => $this->siteA->getKey(), 'state' => ['profile_ids' => [$selectedId]],
    ])->assertSee(__('capell-socials::socials.widget.profiles_unavailable'));
    $key = array_key_first($component->get('data.blocks'));
    $component->call('save')->assertHasErrors(["data.blocks.{$key}.data.profile_ids.0"]);
    $rendered = BuildFollowSocialRenderDataAction::run($this->siteA, 'en', SocialFollowWidgetConfigData::fromState(['profile_ids' => [$selectedId]]));
    expect($rendered->profiles)->toBe([]);
})->with(['deleted', 'disabled', 'foreign']);

it('does not disclose profile choices to an actor without access to the current site', function (): void {
    $this->actingAs(User::factory()->create());
    $component = Livewire::test(SocialsBuilderForm::class, ['siteId' => $this->siteA->getKey(), 'state' => ['profile_ids' => []]]);
    $key = array_key_first($component->get('data.blocks'));
    $component->assertDontSee('Studio team')->assertDontSee('Foreign team sentinel')
        ->set("data.blocks.{$key}.data.profile_ids", [$this->profileA->getKey()])
        ->call('save')->assertHasErrors(["data.blocks.{$key}.data.profile_ids.0"]);
});

it('changes between all profiles and an intentional empty selection without changing the stored contract', function (): void {
    $component = Livewire::test(SocialsBuilderForm::class, [
        'siteId' => $this->siteA->getKey(), 'state' => ['profile_ids' => [$this->profileA->getKey()]],
    ]);
    $key = array_key_first($component->get('data.blocks'));
    $component->set("data.blocks.{$key}.data.profile_selection", 'all')->call('save')->assertHasNoErrors();
    expect(array_values($component->get('saved.blocks'))[0]['data']['profile_ids'])->toBeNull();
    $component->set("data.blocks.{$key}.data.profile_selection", 'choose')->call('save')->assertHasNoErrors();
    expect(array_values($component->get('saved.blocks'))[0]['data']['profile_ids'])->toBe([]);
});

it('takes the owning page site ahead of another editor context site', function (): void {
    $page = Page::factory()->create(['site_id' => $this->siteA->getKey()]);
    $component = Livewire::test(SocialsBuilderForm::class, [
        'siteId' => $this->siteB->getKey(), 'pageId' => $page->getKey(), 'state' => ['profile_ids' => []],
    ])->assertSee('Studio team')->assertDontSee('Foreign team sentinel');
    $key = array_key_first($component->get('data.blocks'));
    $component->set("data.blocks.{$key}.data.profile_ids", [$this->profileA->getKey()])->call('save')->assertHasNoErrors();
    expect(array_values($component->get('saved.blocks'))[0]['data']['profile_ids'])->toBe([$this->profileA->id]);
});
