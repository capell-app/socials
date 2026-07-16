<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

final readonly class LegacySocialImportResultData
{
    /** @param list<string> $skipped */
    public function __construct(
        public int $sitesConsidered,
        public int $profilesImported,
        public array $skipped,
    ) {}
}
