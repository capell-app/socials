<?php

declare(strict_types=1);

namespace Capell\Socials\Blocks;

use Capell\BlockLibrary\Contracts\BlockRenderer;
use Capell\BlockLibrary\Data\BlockDefinitionData;
use Capell\Core\Models\Site;
use Capell\Frontend\Actions\ResolvePageCanonicalUrlAction;
use Capell\Frontend\Facades\Frontend;
use Capell\Socials\Actions\BuildFollowSocialRenderDataAction;
use Capell\Socials\Actions\BuildShareSocialRenderDataAction;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\SharePageContextData;
use Capell\Socials\Data\SocialFollowWidgetConfigData;
use Capell\Socials\Data\SocialShareRenderData;
use Capell\Socials\Data\SocialShareWidgetConfigData;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use ValueError;

final class SocialsBlockRenderer implements BlockRenderer
{
    public function __construct(
        private readonly SocialNetworkRegistry $networkRegistry,
    ) {}

    /** @param array<string, mixed> $state */
    public function render(BlockDefinitionData $definition, array $state = []): Htmlable
    {
        $site = Frontend::site();
        $language = Frontend::language();

        if ($site === null || $language === null) {
            return new HtmlString('');
        }

        $state = array_replace($definition->defaults, $state);
        $mode = $state['mode'] ?? 'follow';

        try {
            $renderData = $mode === 'share'
                ? $this->shareRenderData($site, $language->code, $state)
                : BuildFollowSocialRenderDataAction::run($site, $language->code, SocialFollowWidgetConfigData::fromState($state));
        } catch (InvalidArgumentException|ValueError) {
            return new HtmlString('');
        }

        if (! $renderData->shouldRender()) {
            return new HtmlString('');
        }

        return new HtmlString(View::make($definition->publicViewName(), compact('renderData'))->render());
    }

    /** @param array<string, mixed> $state */
    private function shareRenderData(Site $site, string $locale, array $state): SocialShareRenderData
    {
        $page = Frontend::page();
        $language = Frontend::language();

        if ($page === null || $language === null) {
            throw new InvalidArgumentException('Share widgets require a page context.');
        }

        $canonicalUrl = ResolvePageCanonicalUrlAction::run($page, $language);
        $title = trim(strip_tags((string) $page->title));

        if ($canonicalUrl === null || $title === '') {
            throw new InvalidArgumentException('Share widgets require a canonical page URL and title.');
        }

        return BuildShareSocialRenderDataAction::run(
            $site,
            new SharePageContextData($canonicalUrl, $title, $locale),
            SocialShareWidgetConfigData::fromState($state, $this->networkRegistry),
        );
    }
}
