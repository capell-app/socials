<?php

declare(strict_types=1);

namespace Capell\Socials\Support;

use Illuminate\Contracts\Cache\Repository;

final readonly class SocialsCacheEpoch
{
    public function __construct(private Repository $cache) {}

    public function current(int $siteId): int
    {
        return (int) $this->cache->get($this->key($siteId), 1);
    }

    public function increment(int $siteId): int
    {
        $key = $this->key($siteId);

        $this->cache->add($key, 1);

        return (int) $this->cache->increment($key);
    }

    public function key(int $siteId): string
    {
        return sprintf('capell-socials:site:%d:epoch', $siteId);
    }
}
