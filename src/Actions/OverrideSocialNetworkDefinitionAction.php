<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Socials\Data\SocialNetworkDefinitionData;
use Capell\Socials\Support\SocialNetworkRegistry;
use Lorisleiva\Actions\Concerns\AsAction;

final class OverrideSocialNetworkDefinitionAction
{
    use AsAction;

    public function handle(SocialNetworkDefinitionData $network, SocialNetworkRegistry $registry): void
    {
        $registry->replace($network);
    }
}
