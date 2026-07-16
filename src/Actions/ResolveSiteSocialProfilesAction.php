<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Core\Models\Site;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Contracts\SocialProfilesResolver;
use Capell\Socials\Data\SocialProfileData;
use Capell\Socials\Data\SocialProfilesData;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Support\HttpUrlValidator;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class ResolveSiteSocialProfilesAction implements SocialProfilesResolver
{
    use AsAction;

    public function __construct(
        private readonly SocialNetworkRegistry $networkRegistry,
        private readonly HttpUrlValidator $httpUrlValidator,
    ) {}

    public function resolve(Site $site, string $locale): SocialProfilesData
    {
        return $this->handle($site, $locale);
    }

    /** @param list<int>|null $profileIds */
    public function handle(Site $site, string $locale, ?array $profileIds = null): SocialProfilesData
    {
        $siteId = (int) $site->getKey();

        $profiles = SocialProfile::query()
            ->where('site_id', $siteId)
            ->where('is_enabled', true)
            ->when($profileIds !== null, static fn ($query) => $query->whereKey($profileIds))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $renderedProfiles = [];

        foreach ($profiles as $profile) {
            $renderedProfile = $this->toPublicData($profile);

            if ($renderedProfile !== null) {
                $renderedProfiles[] = $renderedProfile;
            }
        }

        return new SocialProfilesData($renderedProfiles);
    }

    private function toPublicData(SocialProfile $profile): ?SocialProfileData
    {
        if ($profile->network_key === null) {
            return $this->customProfileData($profile);
        }

        $network = $this->networkRegistry->get($profile->network_key);

        if ($network === null) {
            return null;
        }

        try {
            $normalized = $network->normalizer->normalize($profile->profile_value);
        } catch (InvalidArgumentException) {
            return null;
        }

        return new SocialProfileData(
            networkKey: $network->key,
            label: $this->labelFor($profile, $network->label),
            url: $normalized->url,
            handle: $normalized->handle,
            icon: $network->icon,
            capabilities: $network->capabilities,
        );
    }

    private function customProfileData(SocialProfile $profile): ?SocialProfileData
    {
        $label = trim((string) $profile->custom_label);
        $url = trim($profile->profile_value);

        if ($label === '') {
            return null;
        }

        try {
            $this->httpUrlValidator->validate($url);
        } catch (InvalidArgumentException) {
            return null;
        }

        return new SocialProfileData(
            networkKey: 'custom',
            label: $label,
            url: $url,
            handle: null,
            icon: 'link',
            capabilities: [],
        );
    }

    private function labelFor(SocialProfile $profile, string $defaultLabel): string
    {
        $customLabel = trim((string) $profile->custom_label);

        return $customLabel === '' ? $defaultLabel : $customLabel;
    }
}
