<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

final readonly class SocialProfileConfigurationData
{
    public function __construct(
        public ?string $networkKey,
        public string $profileValue,
        public ?string $customLabel = null,
        public bool $isEnabled = true,
    ) {}
}
