<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

final readonly class SocialProfileChoicesData
{
    /** @param array<int, string> $options */
    public function __construct(public array $options) {}

    public function hasUnavailableSelections(mixed $selected): bool
    {
        if (! is_array($selected)) {
            return false;
        }

        return array_any($selected, fn (mixed $id): bool => (! is_int($id) && ! is_string($id)) || ! array_key_exists($id, $this->options));
    }
}
