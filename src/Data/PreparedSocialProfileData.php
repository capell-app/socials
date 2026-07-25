<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

final readonly class PreparedSocialProfileData
{
    public function __construct(
        public int $id,
        public SocialProfileData $profile,
    ) {}
}
