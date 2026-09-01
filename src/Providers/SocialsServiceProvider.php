<?php

declare(strict_types=1);

namespace Capell\Socials\Providers;

use Capell\BlockLibrary\Contracts\BlockDefinitionProvider;
use Capell\BlockLibrary\Support\BuilderBlockDiscovery;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\Frontend\Contracts\FrontendRuntimeManifestContributor;
use Capell\Socials\Actions\RegisterBuiltInSocialNetworksAction;
use Capell\Socials\Actions\ResolveSiteSocialProfilesAction;
use Capell\Socials\Blocks\SocialsBlockDefinitionProvider;
use Capell\Socials\Console\Commands\InstallSocialsCommand;
use Capell\Socials\Console\Commands\SeedSocialsScreenshotFixtureCommand;
use Capell\Socials\Contracts\SocialNetworkRegistry as SocialNetworkRegistryContract;
use Capell\Socials\Contracts\SocialProfilesResolver;
use Capell\Socials\Filament\BuilderBlocks\SocialsBuilderBlock;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Observers\InvalidateSocialsCacheObserver;
use Capell\Socials\Support\SocialNetworkRegistry;
use Capell\Socials\Support\SocialsCacheEpoch;
use Capell\Socials\Support\SocialsFrontendRuntimeManifestContributor;
use Spatie\LaravelPackageTools\Package;

final class SocialsServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-socials';

    public static string $packageName = 'capell-app/socials';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasTranslations()
            ->hasViews(self::$name)
            ->hasCommands([
                InstallSocialsCommand::class,
                SeedSocialsScreenshotFixtureCommand::class,
            ])
            ->hasMigrations([
                '2026_07_16_000001_create_social_profiles_table',
                '2026_07_16_000002_create_social_site_preferences_table',
                '2026_08_31_000001_add_share_networks_customised_to_social_site_preferences',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SocialNetworkRegistry::class);
        $this->app->singleton(SocialsCacheEpoch::class);
        $this->app->alias(SocialNetworkRegistry::class, SocialNetworkRegistryContract::class);
        $this->app->singleton(SocialProfilesResolver::class, ResolveSiteSocialProfilesAction::class);
        $this->app->tag([SocialsBlockDefinitionProvider::class], BlockDefinitionProvider::TAG);
        $this->app->tag([SocialsFrontendRuntimeManifestContributor::class], FrontendRuntimeManifestContributor::TAG);

        $this->callAfterResolving(BuilderBlockDiscovery::class, static function (BuilderBlockDiscovery $discovery): void {
            $discovery->register(SocialsBuilderBlock::class);
        });

        $this->app->afterResolving(SocialNetworkRegistry::class, static function (SocialNetworkRegistry $registry): void {
            if ($registry->all() === []) {
                RegisterBuiltInSocialNetworksAction::run($registry);
            }
        });
    }

    public function packageBooted(): void
    {
        SocialProfile::observe(InvalidateSocialsCacheObserver::class);
        SocialSitePreferences::observe(InvalidateSocialsCacheObserver::class);
    }
}
