<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Socials\Support\BuiltIns\BuiltInSocialNetworkDefinitions;
use Capell\Socials\Support\SocialNetworkRegistry;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class RegisterBuiltInSocialNetworksAction
{
    use AsFake;
    use AsObject;

    public function handle(SocialNetworkRegistry $registry): void
    {
        foreach (BuiltInSocialNetworkDefinitions::all() as $definition) {
            $registry->register($definition);
        }
    }
}
