<?php

declare(strict_types=1);

namespace Capell\Socials\Providers;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Socials\Filament\Pages\SocialsPage;
use Illuminate\Support\ServiceProvider;
use Override;

final class AdminServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'capell-socials');
    }

    public function boot(): void
    {
        if (! CapellCore::isPackageInstalled(SocialsServiceProvider::$packageName)) {
            return;
        }

        CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::page(SocialsPage::class));
    }
}
