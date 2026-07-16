<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\ShareLinkData;
use Capell\Socials\Data\ShareLinksData;
use Capell\Socials\Data\SharePageContextData;
use Capell\Socials\Enums\SocialNetworkCapability;
use Lorisleiva\Actions\Concerns\AsAction;

/** @method static ShareLinksData run(SharePageContextData $context, list<string> $networkKeys, SocialNetworkRegistry $registry) */
final class BuildShareLinksAction
{
    use AsAction;

    /** @param list<string> $networkKeys */
    public function handle(SharePageContextData $context, array $networkKeys, SocialNetworkRegistry $registry): ShareLinksData
    {
        $links = [];
        $seen = [];

        foreach ($networkKeys as $networkKey) {
            $network = $registry->get($networkKey);

            if ($network === null || isset($seen[$network->key]) || ! $network->supports(SocialNetworkCapability::Share) || $network->shareUrlGenerator === null) {
                continue;
            }

            $seen[$network->key] = true;
            $links[] = new ShareLinkData(
                networkKey: $network->key,
                label: $network->label,
                icon: $network->icon,
                url: $network->shareUrlGenerator->generate($context),
            );
        }

        return new ShareLinksData($links);
    }
}
