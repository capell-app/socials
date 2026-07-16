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
     * @param  list<string>  $shareNetworkKeys
     */
    public function __construct(
        private SocialNetworkRegistry $networkRegistry,
        public SocialLabelStyle $followLabelStyle = SocialLabelStyle::Icons,
        public bool $followOpenInNewTab = false,
        array $shareNetworkKeys = [],
        public SocialLabelStyle $shareLabelStyle = SocialLabelStyle::Icons,
        public bool $shareOpenInNewTab = false,
    ) {
        $this->shareNetworkKeys = $this->normalizeShareNetworkKeys($shareNetworkKeys);
    }

    /**
     * @param  list<string>  $shareNetworkKeys
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

            if ($network === null) {
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
