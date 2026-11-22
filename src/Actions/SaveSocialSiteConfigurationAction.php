<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Core\Events\FrontendSurrogateKeysInvalidated;
use Capell\Core\Models\Site;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\SocialNetworkDefinitionData;
use Capell\Socials\Data\SocialProfileConfigurationData;
use Capell\Socials\Data\SocialSitePreferencesData;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\HttpUrlValidator;
use Capell\Socials\Support\SocialsCacheEpoch;
use Capell\Socials\Support\SocialSiteId;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class SaveSocialSiteConfigurationAction
{
    use AsFake;
    use AsObject;

    public function __construct(
        private readonly SocialNetworkRegistry $networkRegistry,
        private readonly HttpUrlValidator $httpUrlValidator,
        private readonly SocialsCacheEpoch $cacheEpoch,
    ) {}

    /**
     * @param  list<SocialProfileConfigurationData>  $profiles
     */
    public function handle(Site $site, array $profiles, SocialSitePreferencesData $preferences): SocialSitePreferences
    {
        $siteId = SocialSiteId::from($site);

        $profiles = $this->validatedProfiles($profiles);

        return DB::transaction(function () use ($siteId, $profiles, $preferences): SocialSitePreferences {
            $this->syncProfiles($siteId, $profiles);

            $sitePreferences = SocialSitePreferences::query()->updateOrCreate(
                ['site_id' => $siteId],
                [
                    'follow_label_style' => $preferences->followLabelStyle,
                    'follow_open_in_new_tab' => $preferences->followOpenInNewTab,
                    'share_network_keys' => $preferences->shareNetworkKeys,
                    'share_networks_customised' => $preferences->shareNetworksCustomised,
                    'share_label_style' => $preferences->shareLabelStyle,
                    'share_open_in_new_tab' => $preferences->shareOpenInNewTab,
                ],
            );

            DB::afterCommit(function () use ($siteId): void {
                $this->cacheEpoch->increment($siteId);

                event(new FrontendSurrogateKeysInvalidated(['site-' . $siteId]));
            });

            return $sitePreferences;
        });
    }

    /**
     * Updates rows in place so blocks that store a profile by id keep resolving it
     * after every save; only rows the editor removed are deleted.
     *
     * @param  list<SocialProfileConfigurationData>  $profiles
     */
    private function syncProfiles(int $siteId, array $profiles): void
    {
        /** @var Collection<int, SocialProfile> $existing */
        $existing = SocialProfile::query()->where('site_id', $siteId)->get()->keyBy('id');
        $claimed = [];
        $matches = [];

        foreach ($profiles as $index => $profile) {
            $match = $this->matchExisting($existing, $claimed, $profile);

            if ($match instanceof SocialProfile) {
                $claimed[$match->id] = true;
                $matches[$index] = $match;
            }
        }

        $existing
            ->reject(static fn (SocialProfile $row): bool => isset($claimed[$row->id]))
            ->each(static fn (SocialProfile $row): ?bool => $row->delete());

        // Free every network key being moved first so two rows can swap networks
        // without tripping the unique (site, network) index mid-save.
        foreach ($profiles as $index => $profile) {
            $match = $matches[$index] ?? null;

            if ($match instanceof SocialProfile && $match->network_key !== null && $match->network_key !== $profile->networkKey) {
                $match->update(['network_key' => null]);
            }
        }

        foreach ($profiles as $sortOrder => $profile) {
            $attributes = [
                'network_key' => $profile->networkKey,
                'profile_value' => $profile->profileValue,
                'custom_label' => $profile->customLabel,
                'sort_order' => $sortOrder,
                'is_enabled' => $profile->isEnabled,
            ];
            $match = $matches[$sortOrder] ?? null;

            if ($match instanceof SocialProfile) {
                $match->update($attributes);

                continue;
            }

            SocialProfile::query()->create(['site_id' => $siteId, ...$attributes]);
        }
    }

    /**
     * @param  Collection<int, SocialProfile>  $existing
     * @param  array<int, true>  $claimed
     */
    private function matchExisting(Collection $existing, array $claimed, SocialProfileConfigurationData $profile): ?SocialProfile
    {
        $unclaimed = $existing->reject(static fn (SocialProfile $row): bool => isset($claimed[$row->id]));

        if ($profile->id !== null && $unclaimed->has($profile->id)) {
            return $unclaimed->get($profile->id);
        }

        if ($profile->networkKey !== null) {
            return $unclaimed->first(static fn (SocialProfile $row): bool => $row->network_key === $profile->networkKey);
        }

        return $unclaimed->first(static fn (SocialProfile $row): bool => $row->network_key === null
            && $row->profile_value === $profile->profileValue);
    }

    /**
     * @param  list<SocialProfileConfigurationData>  $profiles
     * @return list<SocialProfileConfigurationData>
     */
    private function validatedProfiles(array $profiles): array
    {
        $validatedProfiles = [];
        $registeredNetworkKeys = [];

        foreach ($profiles as $profile) {
            if (! $profile instanceof SocialProfileConfigurationData) {
                throw new InvalidArgumentException('Social profiles must use SocialProfileConfigurationData.');
            }

            $profileValue = trim($profile->profileValue);

            if ($profile->networkKey === null) {
                $validatedProfiles[] = $this->validatedCustomProfile($profile, $profileValue);

                continue;
            }

            $network = $this->networkRegistry->get($profile->networkKey);

            if (! $network instanceof SocialNetworkDefinitionData) {
                throw new InvalidArgumentException(sprintf('Social network [%s] is not registered.', trim($profile->networkKey)));
            }

            if (isset($registeredNetworkKeys[$network->key])) {
                throw new InvalidArgumentException(sprintf('Social network [%s] may only be configured once per site.', $network->key));
            }

            $network->validator->validate($profileValue);
            $network->normalizer->normalize($profileValue);
            $registeredNetworkKeys[$network->key] = true;

            $customLabel = $this->nullableLabel($profile->customLabel);

            $validatedProfiles[] = new SocialProfileConfigurationData(
                networkKey: $network->key,
                profileValue: $profileValue,
                customLabel: $customLabel,
                isEnabled: $profile->isEnabled,
                id: $profile->id,
            );
        }

        return $validatedProfiles;
    }

    private function validatedCustomProfile(SocialProfileConfigurationData $profile, string $profileValue): SocialProfileConfigurationData
    {
        $customLabel = $this->nullableLabel($profile->customLabel);

        if ($customLabel === null) {
            throw new InvalidArgumentException('Custom social profiles require a label.');
        }

        $this->httpUrlValidator->validate($profileValue);

        return new SocialProfileConfigurationData(
            networkKey: null,
            profileValue: $profileValue,
            customLabel: $customLabel,
            isEnabled: $profile->isEnabled,
            id: $profile->id,
        );
    }

    private function nullableLabel(?string $label): ?string
    {
        $label = $label === null ? null : trim($label);

        return $label === '' ? null : $label;
    }
}
