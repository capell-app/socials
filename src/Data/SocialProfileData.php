<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

use Capell\Socials\Enums\SocialNetworkCapability;

final readonly class SocialProfileData
{
    /** @param list<SocialNetworkCapability> $capabilities */
    public function __construct(
        public string $networkKey,
        public string $label,
        public string $url,
        public ?string $handle,
        public string $icon,
        public array $capabilities,
    ) {}

    public function supports(SocialNetworkCapability $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}
