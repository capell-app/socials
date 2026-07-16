<?php

declare(strict_types=1);

namespace Capell\Socials\Actions;

use Capell\Core\Events\FrontendSurrogateKeysInvalidated;
use Capell\Core\Models\Site;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\LegacySocialImportResultData;
use Capell\Socials\Models\SocialProfile;
use Capell\Socials\Support\HttpUrlValidator;
use Capell\Socials\Support\SocialsCacheEpoch;
use Capell\Socials\Support\SocialSiteId;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsObject;

final class ImportLegacySocialProfilesAction
{
    use AsObject;

    public function __construct(
        private readonly SocialNetworkRegistry $networkRegistry,
        private readonly HttpUrlValidator $httpUrlValidator,
        private readonly SocialsCacheEpoch $cacheEpoch,
    ) {}

    public function handle(?int $siteId = null, bool $dryRun = false): LegacySocialImportResultData
    {
        $sites = Site::query()
            ->when($siteId !== null, fn ($query) => $query->whereKey($siteId))
            ->get();

        $imported = 0;
        $skipped = [];

        foreach ($sites as $site) {
            $currentSiteId = SocialSiteId::from($site);

            if (SocialProfile::query()->where('site_id', $currentSiteId)->exists()) {
                $skipped[] = sprintf('Site %d already has Socials profiles.', $currentSiteId);

                continue;
            }

            $profiles = $this->profilesFor($site, $skipped);

            if ($profiles === [] || $dryRun) {
                continue;
            }

            $siteId = $currentSiteId;

            DB::transaction(function () use ($siteId, $profiles, &$imported): void {
                foreach ($profiles as $sortOrder => $profile) {
                    SocialProfile::query()->create([
                        'site_id' => $siteId,
                        'network_key' => $profile['network_key'],
                        'profile_value' => $profile['profile_value'],
                        'custom_label' => $profile['custom_label'],
                        'sort_order' => $sortOrder,
                        'is_enabled' => true,
                    ]);
                    $imported++;
                }

                DB::afterCommit(function () use ($siteId): void {
                    $this->cacheEpoch->increment($siteId);

                    event(new FrontendSurrogateKeysInvalidated(['site-' . $siteId]));
                });
            });
        }

        return new LegacySocialImportResultData($sites->count(), $imported, $skipped);
    }

    /**
     * @param  list<string>  $skipped
     * @return list<array{network_key:?string,profile_value:string,custom_label:?string}>
     */
    private function profilesFor(Site $site, array &$skipped): array
    {
        $siteId = SocialSiteId::from($site);
        $legacyLinks = $site->getMeta('social_links', []);
        $legacyLinks = is_array($legacyLinks) ? $legacyLinks : [];
        $profiles = [];
        $configuredNetworks = [];

        foreach ($legacyLinks as $legacyLink) {
            if (! is_array($legacyLink)) {
                continue;
            }

            $type = is_string($legacyLink['type'] ?? null) ? $legacyLink['type'] : null;
            $url = is_string($legacyLink['url'] ?? null) ? trim($legacyLink['url']) : '';
            $label = is_string($legacyLink['name'] ?? null) ? trim($legacyLink['name']) : null;

            if ($url === '') {
                continue;
            }

            $network = $type === null ? null : $this->networkRegistry->get($type);

            if ($network === null) {
                if ($label === null || $label === '') {
                    $skipped[] = sprintf('Skipped unlabeled custom legacy social link for site %d.', $siteId);

                    continue;
                }

                try {
                    $this->httpUrlValidator->validate($url);
                } catch (InvalidArgumentException) {
                    $skipped[] = sprintf('Skipped invalid legacy social link for site %d.', $siteId);

                    continue;
                }

                $profiles[] = ['network_key' => null, 'profile_value' => $url, 'custom_label' => $label];

                continue;
            }

            try {
                $normalized = $network->normalizer->normalize($url);
            } catch (InvalidArgumentException) {
                $skipped[] = sprintf('Skipped invalid %s profile for site %d.', $network->key, $siteId);

                continue;
            }

            if (isset($configuredNetworks[$network->key])) {
                continue;
            }

            $configuredNetworks[$network->key] = true;
            $profiles[] = ['network_key' => $network->key, 'profile_value' => $normalized->url, 'custom_label' => $label ?: null];
        }

        if (! isset($configuredNetworks['x'])) {
            $twitter = $site->getMeta('twitter');
            if (is_string($twitter) && trim($twitter) !== '') {
                $network = $this->networkRegistry->get('x');
                if ($network !== null) {
                    try {
                        $normalized = $network->normalizer->normalize($twitter);
                        $profiles[] = ['network_key' => 'x', 'profile_value' => $normalized->url, 'custom_label' => null];
                    } catch (InvalidArgumentException) {
                        $skipped[] = sprintf('Skipped invalid legacy Twitter profile for site %d.', $siteId);
                    }
                }
            }
        }

        return $profiles;
    }
}
