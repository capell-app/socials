<?php

declare(strict_types=1);

namespace Capell\Socials\Support;

use Capell\Core\Models\Site;
use InvalidArgumentException;

final class SocialSiteId
{
    public static function from(Site $site): int
    {
        $key = $site->getKey();

        if (is_int($key) && $key > 0) {
            return $key;
        }

        if (is_string($key) && ctype_digit($key) && (int) $key > 0) {
            return (int) $key;
        }

        throw new InvalidArgumentException('A persisted site is required.');
    }
}
