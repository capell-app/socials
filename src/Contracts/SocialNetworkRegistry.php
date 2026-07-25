<?php

declare(strict_types=1);

namespace Capell\Socials\Contracts;

use Capell\Socials\Data\SocialNetworkDefinitionData;

interface SocialNetworkRegistry
{
    public function register(SocialNetworkDefinitionData $network): void;

    public function get(string $key): ?SocialNetworkDefinitionData;

    /** @return array<string, SocialNetworkDefinitionData> */
    public function all(): array;
}
