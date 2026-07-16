<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Core\Models\Site;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Contracts\SocialProfilesResolver;
use Capell\Socials\Data\SocialProfileConfigurationData;
use Capell\Socials\Data\SocialProfileData;
use Capell\Socials\Data\SocialProfilesData;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\HttpUrlValidator;
use Capell\Socials\Support\SocialSiteId;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/** @method static SocialProfilesData run(Site $site, string $locale, ?list<int> $profileIds = null) */
final class ResolveSiteSocialProfilesAction implements SocialProfilesResolver
{
    use AsAction;

    public function __construct(
        private readonly SocialNetworkRegistry $networkRegistry,
        private readonly HttpUrlValidator $httpUrlValidator,
    ) {}

    /** @param list<int>|null $profileIds */
    public function resolve(Site $site, string $locale, ?array $profileIds = null): SocialProfilesData
    {
        return $this->handle($site, $locale, $profileIds);
    }

    public function hasConfiguration(Site $site): bool
    {
        $siteId = SocialSiteId::from($site);

        return SocialProfile::query()->where('site_id', $siteId)->exists()
            || SocialSitePreferences::query()->where('site_id', $siteId)->exists();
    }

    /** @param list<int>|null $profileIds */
    public function handle(Site $site, string $locale, ?array $profileIds = null): SocialProfilesData
    {
        $siteId = SocialSiteId::from($site);

        $profiles = SocialProfile::query()
            ->where('site_id', $siteId)
            ->where('is_enabled', true)
            ->when($profileIds !== null, static fn ($query) => $query->whereKey($profileIds))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $renderedProfiles = [];

        foreach ($profiles as $profile) {
            $renderedProfile = $this->toPublicData(
                $profile->network_key,
                $profile->profile_value,
                $profile->custom_label,
            );

            if ($renderedProfile !== null) {
                $renderedProfiles[] = $renderedProfile;
            }
        }

        return new SocialProfilesData($renderedProfiles);
    }

    public function resolveConfiguration(SocialProfileConfigurationData $profile): ?SocialProfileData
    {
        return $this->toPublicData($profile->networkKey, $profile->profileValue, $profile->customLabel);
    }

    private function toPublicData(?string $networkKey, string $profileValue, ?string $customLabel): ?SocialProfileData
    {
        if ($networkKey === null) {
            return $this->customProfileData($profileValue, $customLabel);
        }

        $network = $this->networkRegistry->get($networkKey);

        if ($network === null) {
            return null;
        }

        try {
            $normalized = $network->normalizer->normalize($profileValue);
        } catch (InvalidArgumentException) {
            return null;
        }

        return new SocialProfileData(
            networkKey: $network->key,
            label: $this->labelFor($customLabel, $network->label),
            url: $normalized->url,
            handle: $normalized->handle,
            icon: $network->icon,
            capabilities: $network->capabilities,
        );
    }

    private function customProfileData(string $profileValue, ?string $customLabel): ?SocialProfileData
    {
        $label = trim((string) $customLabel);
        $url = trim($profileValue);

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

    private function labelFor(?string $customLabel, string $defaultLabel): string
    {
        $customLabel = trim((string) $customLabel);

        return $customLabel === '' ? $defaultLabel : $customLabel;
    }
}
