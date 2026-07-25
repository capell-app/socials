<?php

declare(strict_types=1);

namespace Capell\Socials\Support;

use Capell\Socials\Contracts\SocialProfileValidator;
use InvalidArgumentException;

final class HttpUrlValidator implements SocialProfileValidator
{
    public function validate(string $value): void
    {
        $url = trim($value);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($scheme) || ! in_array(strtolower($scheme), ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException('A valid HTTP(S) URL is required.');
        }
    }
}
