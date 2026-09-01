<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Enums\SocialNetworkCapability;
use InvalidArgumentException;

final readonly class SocialSitePreferencesData
{
    /** @var list<string> */
    public array $shareNetworkKeys;

    /**
     * The capability-backed recommended share set for this site's registry,
     * exposed regardless of whether the editor has customised sharing.
     *
     * @var list<string>
     */
    public array $recommendedShareNetworkKeys;

    /**
     * @param  array<mixed>  $shareNetworkKeys
     */
    public function __construct(
        private SocialNetworkRegistry $networkRegistry,
        public SocialLabelStyle $followLabelStyle = SocialLabelStyle::Icons,
        public bool $followOpenInNewTab = false,
        array $shareNetworkKeys = [],
        public SocialLabelStyle $shareLabelStyle = SocialLabelStyle::Icons,
        public bool $shareOpenInNewTab = false,
        public bool $shareNetworksCustomised = false,
    ) {
        $this->recommendedShareNetworkKeys = self::recommendedShareNetworkKeys($networkRegistry);
        $this->shareNetworkKeys = $this->shareNetworksCustomised
            ? $this->normalizeShareNetworkKeys($shareNetworkKeys)
            : $this->recommendedShareNetworkKeys;
    }

    /**
     * Every registered network that declares the Share capability, in
     * registration order. Registering a new share-capable network extends the
     * recommended set without any per-site change.
     *
     * @return list<string>
     */
    public static function recommendedShareNetworkKeys(SocialNetworkRegistry $networkRegistry): array
    {
        $keys = [];

        foreach ($networkRegistry->all() as $network) {
            if ($network->supports(SocialNetworkCapability::Share)) {
                $keys[] = $network->key;
            }
        }

        return $keys;
    }

    /**
     * @param  array<mixed>  $shareNetworkKeys
     * @return list<string>
     */
    private function normalizeShareNetworkKeys(array $shareNetworkKeys): array
    {
        $normalizedKeys = [];

        foreach ($shareNetworkKeys as $shareNetworkKey) {
            if (! is_string($shareNetworkKey)) {
                throw new InvalidArgumentException('Share network keys must be strings.');
            }

            $normalizedKey = strtolower(trim($shareNetworkKey));

            if ($normalizedKey === '') {
                throw new InvalidArgumentException('Share network keys must not be empty.');
            }

            $network = $this->networkRegistry->get($normalizedKey);

            if (! $network instanceof SocialNetworkDefinitionData) {
                throw new InvalidArgumentException(sprintf('Social network [%s] is not registered.', $normalizedKey));
            }

            if (! $network->supports(SocialNetworkCapability::Share)) {
                throw new InvalidArgumentException(sprintf('Social network [%s] does not support sharing.', $network->key));
            }

            $normalizedKeys[$network->key] = true;
        }

        return array_keys($normalizedKeys);
    }
}
