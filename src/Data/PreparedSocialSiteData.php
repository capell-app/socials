<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

use Capell\Socials\Enums\SocialLabelStyle;

final readonly class PreparedSocialSiteData
{
    /**
     * @param  list<string>  $shareNetworkKeys
     * @param  list<PreparedSocialProfileData>  $profiles
     */
    public function __construct(
        public SocialLabelStyle $followLabelStyle,
        public bool $followOpenInNewTab,
        public array $shareNetworkKeys,
        public SocialLabelStyle $shareLabelStyle,
        public bool $shareOpenInNewTab,
        public array $profiles,
        public bool $hasConfiguration,
    ) {}

    /**
     * @param  list<int>|null  $profileIds
     * @return list<SocialProfileData>
     */
    public function profiles(?array $profileIds): array
    {
        return array_values(array_map(
            static fn (PreparedSocialProfileData $profile): SocialProfileData => $profile->profile,
            array_filter(
                $this->profiles,
                static fn (PreparedSocialProfileData $profile): bool => $profileIds === null
                    || in_array($profile->id, $profileIds, true),
            ),
        ));
    }
}
