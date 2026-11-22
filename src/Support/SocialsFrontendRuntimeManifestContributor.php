<?php

declare(strict_types=1);

namespace Capell\Socials\Support;

use Capell\Core\Data\Rendering\FrontendRuntimeManifestData;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Frontend\Contracts\FrontendRuntimeManifestContributor;
use Capell\Socials\Actions\PrepareSocialSiteRenderDataAction;

final class SocialsFrontendRuntimeManifestContributor implements FrontendRuntimeManifestContributor
{
    public const string RENDER_DATA_KEY = 'socials.site_render_data';

    public function contribute(FrontendContextReader $context, FrontendRuntimeManifestData $manifest): void
    {
        $site = $context->site();
        $language = $context->language();

        if (! $site instanceof Site || ! $language instanceof Language) {
            return;
        }

        $context->setFrontendData(
            self::RENDER_DATA_KEY,
            PrepareSocialSiteRenderDataAction::run($site, $language->code),
        );
    }
}
