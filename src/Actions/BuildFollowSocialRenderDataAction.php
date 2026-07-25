<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Core\Models\Site;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Socials\Data\PreparedSocialSiteData;
use Capell\Socials\Data\SocialFollowRenderData;
use Capell\Socials\Data\SocialFollowWidgetConfigData;
use Capell\Socials\Data\SocialProfileConfigurationData;
use Capell\Socials\Data\SocialProfileData;
use Capell\Socials\Data\SocialSitePreferencesData;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\SocialsCacheEpoch;
use Capell\Socials\Support\SocialsFrontendRuntimeManifestContributor;
use Capell\Socials\Support\SocialSiteId;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static SocialFollowRenderData run(Site $site, string $locale, SocialFollowWidgetConfigData $config) */
final class BuildFollowSocialRenderDataAction
{
    use AsFake;
    use AsObject;

    public function __construct(private readonly SocialsCacheEpoch $cacheEpoch) {}

    public function handle(Site $site, string $locale, SocialFollowWidgetConfigData $config): SocialFollowRenderData
    {
        $prepared = app()->bound(FrontendContextReader::class)
            ? resolve(FrontendContextReader::class)->getFrontendData(SocialsFrontendRuntimeManifestContributor::RENDER_DATA_KEY)
            : null;

        if ($prepared instanceof PreparedSocialSiteData) {
            return $this->renderData(
                $config,
                $prepared->followLabelStyle,
                $prepared->followOpenInNewTab,
                $prepared->profiles($config->profileIds),
            );
        }

        $siteId = SocialSiteId::from($site);
        $cacheKey = sprintf(
            'capell-socials:follow:%d:%s:%d:%s',
            $siteId,
            $locale,
            $this->cacheEpoch->current($siteId),
            hash('xxh128', serialize($config)),
        );

        return Cache::rememberForever($cacheKey, function () use ($site, $locale, $config): SocialFollowRenderData {
            $preferences = SocialSitePreferences::query()->firstWhere('site_id', $site->getKey())
                ?? new SocialSitePreferences;
            $profiles = ResolveSiteSocialProfilesAction::run($site, $locale, $config->profileIds)->profiles;

            foreach ($config->customLinks as $customLink) {
                $profiles[] = new SocialProfileData(
                    networkKey: 'custom',
                    label: $customLink->label,
                    url: $customLink->url,
                    handle: null,
                    icon: 'link',
                    capabilities: [],
                );
            }

            return new SocialFollowRenderData($config->heading, $config->labelStyle ?? $preferences->follow_label_style, $config->openInNewTab ?? $preferences->follow_open_in_new_tab, $config->alignment, $profiles);
        });
    }

    /**
     * @param  list<SocialProfileConfigurationData>  $profiles
     */
    public function preview(
        SocialFollowWidgetConfigData $config,
        SocialSitePreferencesData $preferences,
        array $profiles,
    ): SocialFollowRenderData {
        $renderedProfiles = [];

        foreach ($profiles as $profile) {
            if (! $profile->isEnabled) {
                continue;
            }

            $renderedProfile = ResolveSiteSocialProfilesAction::make()->resolveConfiguration($profile);

            if ($renderedProfile !== null) {
                $renderedProfiles[] = $renderedProfile;
            }
        }

        return $this->renderData($config, $preferences->followLabelStyle, $preferences->followOpenInNewTab, $renderedProfiles);
    }

    /** @param list<SocialProfileData> $profiles */
    private function renderData(SocialFollowWidgetConfigData $config, SocialLabelStyle $labelStyle, bool $openInNewTab, array $profiles): SocialFollowRenderData
    {
        foreach ($config->customLinks as $customLink) {
            $profiles[] = new SocialProfileData('custom', $customLink->label, $customLink->url, null, 'link', []);
        }

        return new SocialFollowRenderData($config->heading, $config->labelStyle ?? $labelStyle, $config->openInNewTab ?? $openInNewTab, $config->alignment, $profiles);
    }
}
