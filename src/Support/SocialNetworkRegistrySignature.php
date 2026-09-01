<?php

declare(strict_types=1);

namespace Capell\Socials\Support;

use BackedEnum;
use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Data\SocialNetworkDefinitionData;
use Capell\Socials\Enums\SocialNetworkCapability;
use Closure;
use ReflectionClass;
use ReflectionFunction;
use SplObjectStorage;
use Throwable;
use UnitEnum;

final class SocialNetworkRegistrySignature
{
    public static function for(SocialNetworkRegistry $registry, string $locale): string
    {
        return hash('xxh128', json_encode(array_map(
            static fn (SocialNetworkDefinitionData $network): array => [
                'key' => $network->key,
                'aliases' => $network->aliases,
                'label' => $network->label,
                'label_key' => $network->labelKey,
                'resolved_label' => $network->resolveLabel($locale),
                'icon' => $network->icon,
                'capabilities' => array_map(
                    static fn (SocialNetworkCapability $capability): string => $capability->value,
                    $network->capabilities,
                ),
                'normalizer' => self::objectState($network->normalizer),
                'validator' => self::objectState($network->validator),
                'share_url_generator' => self::objectState($network->shareUrlGenerator),
            ],
            $registry->all(),
        ), JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed>|null */
    private static function objectState(?object $object): ?array
    {
        if ($object === null) {
            return null;
        }

        try {
            return [
                'class' => $object::class,
                'serialized' => base64_encode(serialize($object)),
            ];
        } catch (Throwable) {
            /** @var SplObjectStorage<object, int> $seen */
            $seen = new SplObjectStorage;

            return [
                'class' => $object::class,
                'state' => self::normalizeValue($object, $seen),
            ];
        }
    }

    /** @param SplObjectStorage<object, int> $seen */
    private static function normalizeValue(mixed $value, SplObjectStorage $seen): mixed
    {
        if ($value instanceof BackedEnum) {
            return ['enum' => $value::class, 'value' => $value->value];
        }

        if ($value instanceof UnitEnum) {
            return ['enum' => $value::class, 'name' => $value->name];
        }

        if ($value instanceof Closure) {
            $reflection = new ReflectionFunction($value);

            return [
                'closure' => [
                    'file' => $reflection->getFileName(),
                    'start' => $reflection->getStartLine(),
                    'end' => $reflection->getEndLine(),
                    'scope' => $reflection->getClosureScopeClass()?->getName(),
                    'static_variables' => self::normalizeValue($reflection->getStaticVariables(), $seen),
                ],
            ];
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[] = [
                    'key' => $key,
                    'value' => self::normalizeValue($item, $seen),
                ];
            }

            return $normalized;
        }

        if (! is_object($value)) {
            return is_resource($value) ? ['resource' => get_resource_type($value)] : $value;
        }

        if ($seen->contains($value)) {
            return ['reference' => $seen[$value]];
        }

        $seen[$value] = $seen->count();
        $properties = [];
        $reflection = new ReflectionClass($value);

        do {
            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic() || ! $property->isInitialized($value)) {
                    continue;
                }

                $key = $property->getDeclaringClass()->getName() . '::' . $property->getName();
                $properties[$key] = self::normalizeValue($property->getValue($value), $seen);
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection instanceof ReflectionClass);

        ksort($properties);

        return [
            'class' => $value::class,
            'properties' => $properties,
        ];
    }
}
