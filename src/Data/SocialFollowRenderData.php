<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

use Capell\Socials\Enums\SocialLabelStyle;

final readonly class SocialFollowRenderData
{
    /** @param list<SocialProfileData> $profiles */
    public function __construct(
        public ?string $heading,
        public SocialLabelStyle $labelStyle,
        public bool $openInNewTab,
        public string $alignment,
        public array $profiles,
    ) {}

    public function shouldRender(): bool
    {
        return $this->profiles !== [];
    }
}
