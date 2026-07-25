<?php

declare(strict_types=1);

namespace Capell\Socials\Support;

use Capell\Core\Models\Site;
use InvalidArgumentException;

final class SocialSiteId
{
    public static function fromInput(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || $value !== trim($value)) {
            return null;
        }

        $siteId = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return is_int($siteId) ? $siteId : null;
    }

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
