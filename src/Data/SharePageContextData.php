<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

use InvalidArgumentException;

final readonly class SharePageContextData
{
    public function __construct(
        public string $canonicalUrl,
        public string $title,
        public string $locale,
    ) {
        $scheme = parse_url($canonicalUrl, PHP_URL_SCHEME);
        $host = parse_url($canonicalUrl, PHP_URL_HOST);

        if (! is_string($scheme) || ! in_array(strtolower($scheme), ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException('Share URLs require an absolute HTTP(S) canonical URL.');
        }
    }
}
