<?php

declare(strict_types=1);

namespace Capell\Socials\Contracts;

use Capell\Socials\Data\NormalizedSocialProfileData;

interface SocialProfileNormalizer
{
    public function normalize(string $value): NormalizedSocialProfileData;
}
