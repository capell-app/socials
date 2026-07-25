<?php

declare(strict_types=1);

use Capell\Socials\Filament\Pages\SocialsPage;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Spatie\Permission\Models\Permission;

uses(CreatesAdminUser::class);

it('limits the Socials page to its install-generated Filament permission', function (): void {
    Permission::findOrCreate(SocialsPage::VIEW_PERMISSION, 'web');

    expect(SocialsPage::canAccess())->toBeFalse();

    test()->actingAsUser();

    expect(SocialsPage::canAccess())->toBeFalse();

    $authorizedUser = User::factory()->create();
    $authorizedUser->givePermissionTo(SocialsPage::VIEW_PERMISSION);

    test()->actingAs($authorizedUser);

    expect(SocialsPage::canAccess())->toBeTrue();
});
