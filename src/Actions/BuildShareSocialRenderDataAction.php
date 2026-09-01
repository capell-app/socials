<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Core\Models\Site;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\PreparedSocialSiteData;
use Capell\Socials\Data\SharePageContextData;
use Capell\Socials\Data\SocialShareRenderData;
use Capell\Socials\Data\SocialShareWidgetConfigData;
use Capell\Socials\Data\SocialSitePreferencesData;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\SocialNetworkRegistrySignature;
use Capell\Socials\Support\SocialsCacheEpoch;
use Capell\Socials\Support\SocialsFrontendRuntimeManifestContributor;
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
        $prepared = app()->bound(FrontendContextReader::class)
            ? resolve(FrontendContextReader::class)->getFrontendData(SocialsFrontendRuntimeManifestContributor::RENDER_DATA_KEY)
            : null;

        if ($prepared instanceof PreparedSocialSiteData) {
            return $this->renderData($context, $config, $prepared->shareNetworkKeys, $prepared->shareLabelStyle, $prepared->shareOpenInNewTab);
        }

        $siteId = SocialSiteId::from($site);
        $cacheKey = sprintf(
            'capell-socials:share:%d:%s:%d:%s:%s:%s:%s',
            $siteId,
            $context->locale,
            $this->cacheEpoch->current($siteId),
            hash('xxh128', $context->canonicalUrl),
            hash('xxh128', $context->title),
            hash('xxh128', serialize($config)),
            SocialNetworkRegistrySignature::for($this->networkRegistry, $context->locale),
        );

        return Cache::rememberForever($cacheKey, function () use ($site, $context, $config): SocialShareRenderData {
            $preferences = SocialSitePreferences::query()->firstWhere('site_id', $site->getKey())
                ?? new SocialSitePreferences;
            $networkKeys = $config->networkKeys ?? (($preferences->share_networks_customised ?? false)
                ? $preferences->share_network_keys
                : SocialSitePreferencesData::recommendedShareNetworkKeys($this->networkRegistry));
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
        return $this->renderData($context, $config, $preferences->shareNetworkKeys, $preferences->shareLabelStyle, $preferences->shareOpenInNewTab);
    }

    /** @param list<string> $defaultNetworkKeys */
    private function renderData(SharePageContextData $context, SocialShareWidgetConfigData $config, array $defaultNetworkKeys, SocialLabelStyle $labelStyle, bool $openInNewTab): SocialShareRenderData
    {
        $networkKeys = $config->networkKeys ?? $defaultNetworkKeys;
        $links = BuildShareLinksAction::run($context, $networkKeys, $this->networkRegistry)->links;

        return new SocialShareRenderData($config->heading, $config->labelStyle ?? $labelStyle, $config->openInNewTab ?? $openInNewTab, $config->alignment, $links);
    }
}
