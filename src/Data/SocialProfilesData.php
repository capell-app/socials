<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

use Capell\Socials\Enums\SocialNetworkCapability;

final readonly class SocialProfilesData
{
    /** @param list<SocialProfileData> $profiles */
    public function __construct(public array $profiles) {}

    public function forNetwork(string $key): ?SocialProfileData
    {
        $normalizedKey = strtolower(trim($key));

        foreach ($this->profiles as $profile) {
            if ($profile->networkKey === $normalizedKey) {
                return $profile;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function sameAsUrls(): array
    {
        return array_values(array_map(
            static fn (SocialProfileData $profile): string => $profile->url,
            array_filter(
                $this->profiles,
                static fn (SocialProfileData $profile): bool => $profile->supports(SocialNetworkCapability::SameAs),
            ),
        ));
    }
}
