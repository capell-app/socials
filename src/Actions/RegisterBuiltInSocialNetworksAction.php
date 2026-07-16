<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Socials\Support\BuiltIns\BuiltInSocialNetworkDefinitions;
use Capell\Socials\Support\SocialNetworkRegistry;
use Lorisleiva\Actions\Concerns\AsAction;

final class RegisterBuiltInSocialNetworksAction
{
    use AsAction;

    public function handle(SocialNetworkRegistry $registry): void
    {
        foreach (BuiltInSocialNetworkDefinitions::all() as $definition) {
            $registry->register($definition);
        }
    }
}
