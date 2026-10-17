<?php

declare(strict_types=1);

namespace Capell\Socials\Providers;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Support\Packages\RegistersInstalledRuntime;
use Capell\Socials\Filament\Pages\SocialsPage;
use Illuminate\Support\ServiceProvider;
use Override;

final class AdminServiceProvider extends ServiceProvider
{
    use RegistersInstalledRuntime;

    #[Override]
    public function register(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'capell-socials');
        $this->registerInstalledRuntime(SocialsServiceProvider::$packageName, 'admin');
    }

    protected function bootInstalledRuntime(): void
    {
        CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::page(SocialsPage::class));
    }
}
