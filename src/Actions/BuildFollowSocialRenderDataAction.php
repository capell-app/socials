<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Core\Models\Site;
use Capell\Socials\Data\SocialFollowRenderData;
use Capell\Socials\Data\SocialFollowWidgetConfigData;
use Capell\Socials\Data\SocialProfileData;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\SocialsCacheEpoch;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

final class BuildFollowSocialRenderDataAction
{
    use AsAction;

    public function __construct(
        private readonly ResolveSiteSocialProfilesAction $profilesResolver,
        private readonly SocialsCacheEpoch $cacheEpoch,
    ) {}

    public function handle(Site $site, string $locale, SocialFollowWidgetConfigData $config): SocialFollowRenderData
    {
        $siteId = (int) $site->getKey();
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
            $profiles = $this->profilesResolver->handle($site, $locale, $config->profileIds)->profiles;

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

            return new SocialFollowRenderData(
                heading: $config->heading,
                labelStyle: $config->labelStyle ?? $preferences->follow_label_style,
                openInNewTab: $config->openInNewTab ?? $preferences->follow_open_in_new_tab,
                alignment: $config->alignment,
                profiles: $profiles,
            );
        });
    }
}
