<?php

declare(strict_types=1);

namespace Capell\Socials\Support;

use Capell\Socials\Contracts\SocialNetworkRegistry as SocialNetworkRegistryContract;
use Capell\Socials\Data\SocialNetworkDefinitionData;
use InvalidArgumentException;
use Throwable;

final class SocialNetworkRegistry implements SocialNetworkRegistryContract
{
    /** @var array<string, SocialNetworkDefinitionData> */
    private array $definitions = [];

    /** @var array<string, string> */
    private array $aliases = [];

    public function register(SocialNetworkDefinitionData $network): void
    {
        $key = $this->normalizeKey($network->key);

        if (isset($this->definitions[$key]) || isset($this->aliases[$key])) {
            throw new InvalidArgumentException(sprintf('Social network key [%s] is already registered.', $key));
        }

        $normalizedAliases = [];

        foreach ($network->aliases as $alias) {
            $normalizedAlias = $this->normalizeKey($alias);

            if ($normalizedAlias === $key || isset($normalizedAliases[$normalizedAlias]) || isset($this->definitions[$normalizedAlias]) || isset($this->aliases[$normalizedAlias])) {
                throw new InvalidArgumentException(sprintf('Social network alias [%s] collides with an existing social network key or alias.', $normalizedAlias));
            }

            $normalizedAliases[$normalizedAlias] = true;
        }

        $definition = new SocialNetworkDefinitionData(
            key: $key,
            aliases: array_keys($normalizedAliases),
            label: $network->label,
            icon: $network->icon,
            capabilities: $network->capabilities,
            normalizer: $network->normalizer,
            validator: $network->validator,
            shareUrlGenerator: $network->shareUrlGenerator,
            labelKey: $network->labelKey,
        );

        $this->definitions[$key] = $definition;

        foreach ($definition->aliases as $alias) {
            $this->aliases[$alias] = $key;
        }
    }

    public function get(string $key): ?SocialNetworkDefinitionData
    {
        $normalizedKey = $this->normalizeKey($key);

        return $this->definitions[$this->aliases[$normalizedKey] ?? $normalizedKey] ?? null;
    }

    /** @return array<string, SocialNetworkDefinitionData> */
    public function all(): array
    {
        return $this->definitions;
    }

    public function replace(SocialNetworkDefinitionData $network): void
    {
        $key = $this->normalizeKey($network->key);

        if (! isset($this->definitions[$key])) {
            throw new InvalidArgumentException(sprintf('Social network key [%s] is not registered.', $key));
        }

        $definitions = $this->definitions;
        $aliases = $this->aliases;

        try {
            unset($this->definitions[$key]);
            foreach ($this->aliases as $alias => $canonicalKey) {
                if ($canonicalKey === $key) {
                    unset($this->aliases[$alias]);
                }
            }

            $this->register($network);
        } catch (Throwable $throwable) {
            $this->definitions = $definitions;
            $this->aliases = $aliases;

            throw $throwable;
        }
    }

    private function normalizeKey(string $key): string
    {
        $normalizedKey = strtolower(trim($key));

        if ($normalizedKey === '') {
            throw new InvalidArgumentException('Social network keys and aliases must not be empty.');
        }

        return $normalizedKey;
    }
}
