<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Core\Events\FrontendSurrogateKeysInvalidated;
use Capell\Core\Models\Site;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\SocialProfileConfigurationData;
use Capell\Socials\Data\SocialSitePreferencesData;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\HttpUrlValidator;
use Capell\Socials\Support\SocialsCacheEpoch;
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
        $siteId = (int) $site->getKey();

        if ($siteId < 1) {
            throw new InvalidArgumentException('A persisted site is required to save social configuration.');
        }

        $profiles = $this->validatedProfiles($profiles);

        return DB::transaction(function () use ($siteId, $profiles, $preferences): SocialSitePreferences {
            SocialProfile::query()
                ->where('site_id', $siteId)
                ->delete();

            foreach ($profiles as $sortOrder => $profile) {
                SocialProfile::query()->create([
                    'site_id' => $siteId,
                    'network_key' => $profile->networkKey,
                    'profile_value' => $profile->profileValue,
                    'custom_label' => $profile->customLabel,
                    'sort_order' => $sortOrder,
                    'is_enabled' => $profile->isEnabled,
                ]);
            }

            $sitePreferences = SocialSitePreferences::query()->updateOrCreate(
                ['site_id' => $siteId],
                [
                    'follow_label_style' => $preferences->followLabelStyle,
                    'follow_open_in_new_tab' => $preferences->followOpenInNewTab,
                    'share_network_keys' => $preferences->shareNetworkKeys,
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

            if ($network === null) {
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
        );
    }

    private function nullableLabel(?string $label): ?string
    {
        $label = $label === null ? null : trim($label);

        return $label === '' ? null : $label;
    }
}
