<?php

declare(strict_types=1);

namespace Capell\Socials\Support;

use Illuminate\Contracts\Cache\Repository;
use LogicException;

final readonly class SocialsCacheEpoch
{
    public function __construct(private Repository $cache) {}

    public function current(int $siteId): int
    {
        return $this->integerValue($this->cache->get($this->key($siteId), 1));
    }

    public function increment(int $siteId): int
    {
        $key = $this->key($siteId);

        $this->cache->add($key, 1);

        return $this->integerValue($this->cache->increment($key));
    }

    public function key(int $siteId): string
    {
        return sprintf('capell-socials:site:%d:epoch', $siteId);
    }

    private function integerValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new LogicException('Socials cache epoch values must be integers.');
    }
}
