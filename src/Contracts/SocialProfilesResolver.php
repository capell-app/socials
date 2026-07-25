<?php

declare(strict_types=1);

namespace Capell\Socials\Contracts;

use Capell\Core\Models\Site;
use Capell\Socials\Data\SocialProfilesData;

interface SocialProfilesResolver
{
    /** @param list<int>|null $profileIds */
    public function resolve(Site $site, string $locale, ?array $profileIds = null): SocialProfilesData;

    public function hasConfiguration(Site $site): bool;
}
