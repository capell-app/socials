<?php

declare(strict_types=1);

namespace Capell\Socials\Providers;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Socials\Filament\Pages\SocialsPage;
use Illuminate\Support\ServiceProvider;

final class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! CapellCore::isPackageInstalled(SocialsServiceProvider::$packageName)) {
            return;
        }

        CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::page(SocialsPage::class));
    }
}
