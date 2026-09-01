<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Core\Models\Site;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\SocialProfileConfigurationData;
use Capell\Socials\Data\SocialSitePreferencesData;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Models\SocialSitePreferences;
use RuntimeException;

final class SeedSocialsScreenshotFixtureAction
{
    public static function run(): SocialSitePreferences
    {
        self::assertDisposableScreenshotEnvironment();

        $site = Site::query()->orderBy('id')->first();

        throw_unless(
            $site instanceof Site,
            RuntimeException::class,
            'Socials screenshot fixtures require a disposable site.',
        );

        /** @var SocialNetworkRegistry $networkRegistry */
        $networkRegistry = resolve(SocialNetworkRegistry::class);

        return SaveSocialSiteConfigurationAction::run(
            $site,
            [
                new SocialProfileConfigurationData('x', '@capell'),
                new SocialProfileConfigurationData('linkedin', 'capell', 'Capell on LinkedIn'),
                new SocialProfileConfigurationData(null, 'https://example.test/community', 'Community'),
            ],
            new SocialSitePreferencesData(
                $networkRegistry,
                SocialLabelStyle::IconsAndLabels,
                true,
                ['x', 'linkedin'],
                SocialLabelStyle::Labels,
                true,
                shareNetworksCustomised: true,
            ),
        );
    }

    private static function assertDisposableScreenshotEnvironment(): void
    {
        $configuredAppPath = getenv('CAPELL_SCREENSHOT_APP_PATH');
        $basePath = realpath(base_path());
        $appPath = is_string($configuredAppPath) ? realpath($configuredAppPath) : false;

        throw_unless(
            self::isLocalOrTestingEnvironment()
                && in_array(getenv('CAPELL_SCREENSHOT_FIXTURE'), ['1', 'true', 'record-state'], true)
                && is_string($basePath)
                && is_string($appPath)
                && $basePath === $appPath,
            RuntimeException::class,
            'Socials screenshot fixtures require the explicit disposable local screenshot environment.',
        );
    }

    private static function isLocalOrTestingEnvironment(): bool
    {
        $environment = app()->bound('config') ? config('app.env') : getenv('APP_ENV');

        return in_array($environment, ['local', 'testing'], true);
    }
}
