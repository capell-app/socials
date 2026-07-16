<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

use Capell\Socials\Contracts\SocialProfileNormalizer;
use Capell\Socials\Contracts\SocialProfileValidator;
use Capell\Socials\Contracts\SocialShareUrlGenerator;
use Capell\Socials\Enums\SocialNetworkCapability;
use InvalidArgumentException;

final readonly class SocialNetworkDefinitionData
{
    /**
     * @param  list<string>  $aliases
     * @param  list<SocialNetworkCapability>  $capabilities
     */
    public function __construct(
        public string $key,
        public array $aliases,
        public string $label,
        public string $icon,
        public array $capabilities,
        public SocialProfileNormalizer $normalizer,
        public SocialProfileValidator $validator,
        public ?SocialShareUrlGenerator $shareUrlGenerator = null,
    ) {
        if (in_array(SocialNetworkCapability::Share, $this->capabilities, true) && $this->shareUrlGenerator === null) {
            throw new InvalidArgumentException('Social networks with the share capability must provide a share URL generator.');
        }
    }

    public function supports(SocialNetworkCapability $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}
