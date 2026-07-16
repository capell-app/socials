<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Core\Models\Site;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\SharePageContextData;
use Capell\Socials\Data\SocialShareRenderData;
use Capell\Socials\Data\SocialShareWidgetConfigData;
use Capell\Socials\Data\SocialSitePreferencesData;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\SocialsCacheEpoch;
use Capell\Socials\Support\SocialSiteId;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static SocialShareRenderData run(Site $site, SharePageContextData $context, SocialShareWidgetConfigData $config) */
final class BuildShareSocialRenderDataAction
{
    use AsFake;
    use AsObject;

    public function __construct(
        private readonly SocialNetworkRegistry $networkRegistry,
        private readonly SocialsCacheEpoch $cacheEpoch,
    ) {}

    public function handle(Site $site, SharePageContextData $context, SocialShareWidgetConfigData $config): SocialShareRenderData
    {
        $siteId = SocialSiteId::from($site);
        $cacheKey = sprintf(
            'capell-socials:share:%d:%s:%d:%s:%s:%s',
            $siteId,
            $context->locale,
            $this->cacheEpoch->current($siteId),
            hash('xxh128', $context->canonicalUrl),
            hash('xxh128', $context->title),
            hash('xxh128', serialize($config)),
        );

        return Cache::rememberForever($cacheKey, function () use ($site, $context, $config): SocialShareRenderData {
            $preferences = SocialSitePreferences::query()->firstWhere('site_id', $site->getKey())
                ?? new SocialSitePreferences;
            $networkKeys = $config->networkKeys ?? $preferences->share_network_keys;
            $links = BuildShareLinksAction::run($context, $networkKeys, $this->networkRegistry)->links;

            return new SocialShareRenderData(
                heading: $config->heading,
                labelStyle: $config->labelStyle ?? $preferences->share_label_style,
                openInNewTab: $config->openInNewTab ?? $preferences->share_open_in_new_tab,
                alignment: $config->alignment,
                links: $links,
            );
        });
    }

    public function preview(
        SharePageContextData $context,
        SocialShareWidgetConfigData $config,
        SocialSitePreferencesData $preferences,
    ): SocialShareRenderData {
        $networkKeys = $config->networkKeys ?? $preferences->shareNetworkKeys;
        $links = BuildShareLinksAction::run($context, $networkKeys, $this->networkRegistry)->links;

        return new SocialShareRenderData(
            heading: $config->heading,
            labelStyle: $config->labelStyle ?? $preferences->shareLabelStyle,
            openInNewTab: $config->openInNewTab ?? $preferences->shareOpenInNewTab,
            alignment: $config->alignment,
            links: $links,
        );
    }
}
