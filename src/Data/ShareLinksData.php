<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

final readonly class ShareLinksData
{
    /** @param list<ShareLinkData> $links */
    public function __construct(public array $links) {}
}
