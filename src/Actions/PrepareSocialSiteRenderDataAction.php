<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Core\Models\Site;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\PreparedSocialProfileData;
use Capell\Socials\Data\PreparedSocialSiteData;
use Capell\Socials\Data\SocialProfileConfigurationData;
use Capell\Socials\Data\SocialProfileData;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Enums\SocialNetworkCapability;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Models\SocialSitePreferences;
use Capell\Socials\Support\SocialsCacheEpoch;
use Capell\Socials\Support\SocialSiteId;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static PreparedSocialSiteData run(Site $site, string $locale) */
final class PrepareSocialSiteRenderDataAction
{
    use AsFake;
    use AsObject;

    /**
     * Cache-shape version. Bump when the cached array structure changes so that
     * previously cached entries (including legacy entries that stored a hydrated
     * DTO object, which unserialize to __PHP_Incomplete_Class when the autoload
     * map shifts) are bypassed rather than read back and rehydrated.
     */
    private const string CACHE_SHAPE = 'v2-array';

    public function __construct(
        private readonly SocialNetworkRegistry $networkRegistry,
        private readonly SocialsCacheEpoch $cacheEpoch,
    ) {}

    public function handle(Site $site, string $locale): PreparedSocialSiteData
    {
        $siteId = SocialSiteId::from($site);
        $cacheKey = sprintf(
            'capell-socials:prepared:%s:%d:%s:%d',
            self::CACHE_SHAPE,
            $siteId,
            $locale,
            $this->cacheEpoch->current($siteId),
        );

        // Cache a serialization-stable plain array — never the hydrated DTO
        // object. Storing typed objects poisons the cache: a later autoload-map
        // change makes them unserialize to __PHP_Incomplete_Class. Rehydrate the
        // DTO after reading the array back.
        $payload = Cache::rememberForever(
            $cacheKey,
            fn (): array => $this->toCacheArray($this->build($siteId, $locale)),
        );

        return $this->fromCacheArray(is_array($payload) ? $payload : $this->toCacheArray($this->build($siteId, $locale)));
    }

    /**
     * @return array<string, mixed>
     */
    private function toCacheArray(PreparedSocialSiteData $data): array
    {
        return [
            'followLabelStyle' => $data->followLabelStyle->value,
            'followOpenInNewTab' => $data->followOpenInNewTab,
            'shareNetworkKeys' => $data->shareNetworkKeys,
            'shareLabelStyle' => $data->shareLabelStyle->value,
            'shareOpenInNewTab' => $data->shareOpenInNewTab,
            'profiles' => array_map(static fn (PreparedSocialProfileData $profile): array => [
                'id' => $profile->id,
                'profile' => [
                    'networkKey' => $profile->profile->networkKey,
                    'label' => $profile->profile->label,
                    'url' => $profile->profile->url,
                    'handle' => $profile->profile->handle,
                    'icon' => $profile->profile->icon,
                    'capabilities' => array_map(
                        static fn (SocialNetworkCapability $capability): string => $capability->value,
                        $profile->profile->capabilities,
                    ),
                ],
            ], $data->profiles),
            'hasConfiguration' => $data->hasConfiguration,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fromCacheArray(array $payload): PreparedSocialSiteData
    {
        /** @var list<array<string, mixed>> $rawProfiles */
        $rawProfiles = is_array($payload['profiles'] ?? null) ? $payload['profiles'] : [];

        $profiles = array_map(function (array $raw): PreparedSocialProfileData {
            /** @var array<string, mixed> $rawProfile */
            $rawProfile = is_array($raw['profile'] ?? null) ? $raw['profile'] : [];
            /** @var list<string> $rawCapabilities */
            $rawCapabilities = is_array($rawProfile['capabilities'] ?? null) ? $rawProfile['capabilities'] : [];

            $handle = $rawProfile['handle'] ?? null;

            return new PreparedSocialProfileData(
                is_int($raw['id'] ?? null) ? $raw['id'] : 0,
                new SocialProfileData(
                    $this->stringValue($rawProfile['networkKey'] ?? null),
                    $this->stringValue($rawProfile['label'] ?? null),
                    $this->stringValue($rawProfile['url'] ?? null),
                    is_string($handle) ? $handle : null,
                    $this->stringValue($rawProfile['icon'] ?? null),
                    array_map(
                        static fn (string $capability): SocialNetworkCapability => SocialNetworkCapability::from($capability),
                        $rawCapabilities,
                    ),
                ),
            );
        }, $rawProfiles);

        /** @var list<string> $shareNetworkKeys */
        $shareNetworkKeys = is_array($payload['shareNetworkKeys'] ?? null) ? $payload['shareNetworkKeys'] : [];

        return new PreparedSocialSiteData(
            SocialLabelStyle::from($this->stringValue($payload['followLabelStyle'] ?? null)),
            (bool) ($payload['followOpenInNewTab'] ?? false),
            $shareNetworkKeys,
            SocialLabelStyle::from($this->stringValue($payload['shareLabelStyle'] ?? null)),
            (bool) ($payload['shareOpenInNewTab'] ?? false),
            $profiles,
            (bool) ($payload['hasConfiguration'] ?? false),
        );
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function build(int $siteId, string $locale): PreparedSocialSiteData
    {
        $storedPreferences = Schema::hasTable('social_site_preferences')
            ? SocialSitePreferences::query()->firstWhere('site_id', $siteId)
            : null;
        $hasPreferences = $storedPreferences instanceof SocialSitePreferences;
        $preferences = $hasPreferences ? $storedPreferences : new SocialSitePreferences;
        $followLabelStyle = $preferences->follow_label_style ?? SocialLabelStyle::Icons;
        $followOpenInNewTab = $preferences->follow_open_in_new_tab ?? false;
        $shareNetworkKeys = $this->releasedShareNetworkKeys($preferences->share_network_keys ?? []);
        $shareLabelStyle = $preferences->share_label_style ?? SocialLabelStyle::Icons;
        $shareOpenInNewTab = $preferences->share_open_in_new_tab ?? false;

        if (! Schema::hasTable('social_profiles')) {
            return new PreparedSocialSiteData($followLabelStyle, $followOpenInNewTab, $shareNetworkKeys, $shareLabelStyle, $shareOpenInNewTab, [], $hasPreferences);
        }

        $resolver = ResolveSiteSocialProfilesAction::make();
        $profiles = [];
        $storedProfiles = SocialProfile::query()
            ->where('site_id', $siteId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($storedProfiles as $profile) {
            if (! $profile->is_enabled) {
                continue;
            }

            $renderData = $resolver->resolveConfiguration(new SocialProfileConfigurationData(
                $profile->network_key,
                $profile->profile_value,
                $profile->custom_label,
            ), $locale);

            if ($renderData !== null) {
                $profiles[] = new PreparedSocialProfileData($profile->id, $renderData);
            }
        }

        return new PreparedSocialSiteData($followLabelStyle, $followOpenInNewTab, $shareNetworkKeys, $shareLabelStyle, $shareOpenInNewTab, $profiles, $hasPreferences || $storedProfiles->isNotEmpty());
    }

    /**
     * @param  list<string>  $networkKeys
     * @return list<string>
     */
    private function releasedShareNetworkKeys(array $networkKeys): array
    {
        return array_values(array_filter($networkKeys, function (string $networkKey): bool {
            $network = $this->networkRegistry->get($networkKey);

            return $network !== null && $network->supports(SocialNetworkCapability::Share);
        }));
    }
}
