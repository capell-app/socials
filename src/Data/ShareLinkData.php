<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

final readonly class ShareLinkData
{
    public function __construct(
        public string $networkKey,
        public string $label,
        public string $icon,
        public string $url,
    ) {}
}
