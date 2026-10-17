<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Core\Models\Site;
use Capell\Core\Support\Permissions\SiteAccess;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\SocialProfileConfigurationData;
use Capell\Socials\Data\SocialSitePreferencesData;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SeedSocialsScreenshotFixtureAction
{
    private const string CaptureSeed = 'socials-screenshot-site';

    public static function run(): SocialSitePreferences
    {
        self::assertDisposableScreenshotEnvironment();

        $actorModel = config('auth.providers.users.model');
        throw_unless(is_string($actorModel) && is_subclass_of($actorModel, Model::class), RuntimeException::class, 'Socials screenshots require a configured capture actor.');
        $email = getenv('CAPELL_SCREENSHOT_ADMIN_EMAIL') ?: (getenv('CAPELL_ADMIN_EMAIL') ?: 'admin@example.com');
        $actor = $actorModel::query()->where('email', $email)->sole();
        throw_unless(
            $actor instanceof Authenticatable && $actor instanceof FilamentUser
                && $actor->canAccessPanel(Filament::getPanel('admin')),
            RuntimeException::class,
            'The configured Socials screenshot actor cannot access the admin panel.',
        );

        $site = SiteAccess::forActor($actor)->scope(Site::query(), 'id')->orderBy('name')->first();

        throw_unless(
            $site instanceof Site,
            RuntimeException::class,
            'Socials screenshot fixtures require a disposable site.',
        );

        return DB::transaction(static function () use ($site): SocialSitePreferences {
            $site = Site::query()->whereKey($site->getKey())->lockForUpdate()->firstOrFail();
            $meta = $site->meta ?? [];
            throw_unless(is_array($meta), RuntimeException::class, 'The Socials screenshot site metadata is invalid.');
            $marker = $meta['capture_seed_socials'] ?? null;
            throw_if($marker !== null && $marker !== self::CaptureSeed, RuntimeException::class, 'The Socials screenshot site marker belongs to another fixture.');

            $profiles = SocialProfile::query()->where('site_id', $site->getKey())->orderBy('sort_order')->lockForUpdate()->get();
            $preferences = SocialSitePreferences::query()->where('site_id', $site->getKey())->lockForUpdate()->first();
            if ($marker === self::CaptureSeed) {
                $actual = $profiles->map(static fn (SocialProfile $profile): array => [$profile->network_key, $profile->profile_value, $profile->custom_label, $profile->sort_order, $profile->is_enabled])->all();
                $expected = [
                    ['x', '@capell', null, 0, true],
                    ['linkedin', 'capell', 'Capell on LinkedIn', 1, true],
                    [null, 'https://example.test/community', 'Community', 2, true],
                ];
                throw_unless(
                    $actual === $expected && $preferences instanceof SocialSitePreferences
                        && $preferences->follow_label_style === SocialLabelStyle::IconsAndLabels
                        && $preferences->follow_open_in_new_tab
                        && $preferences->share_network_keys === ['x', 'linkedin']
                        && $preferences->share_label_style === SocialLabelStyle::Labels
                        && $preferences->share_open_in_new_tab && $preferences->share_networks_customised,
                    RuntimeException::class,
                    'The Socials screenshot site contains foreign profile or preference state.',
                );

                return $preferences;
            }

            throw_unless($profiles->isEmpty() && $preferences === null, RuntimeException::class, 'The Socials screenshot site already has profile or preference state.');
            $site->forceFill(['meta' => [...$meta, 'capture_seed_socials' => self::CaptureSeed]])->save();

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
        });
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

        $captureEmail = getenv('CAPELL_SCREENSHOT_ADMIN_EMAIL') ?: (getenv('CAPELL_ADMIN_EMAIL') ?: 'admin@example.com');
        $authenticated = auth()->user();
        throw_if(
            $authenticated !== null && data_get($authenticated, 'email') !== $captureEmail,
            RuntimeException::class,
            'Screenshot fixtures require the selected capture actor.',
        );
    }

    private static function isLocalOrTestingEnvironment(): bool
    {
        $environment = app()->bound('config') ? config('app.env') : getenv('APP_ENV');

        return in_array($environment, ['local', 'testing'], true);
    }
}
