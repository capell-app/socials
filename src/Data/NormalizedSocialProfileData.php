<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

final readonly class NormalizedSocialProfileData
{
    public function __construct(
        public string $url,
        public ?string $handle = null,
    ) {}
}
