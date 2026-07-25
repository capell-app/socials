<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

use Capell\Socials\Contracts\SocialNetworkRegistry;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Enums\SocialNetworkCapability;
use InvalidArgumentException;

final readonly class SocialShareWidgetConfigData
{
    /** @param list<string>|null $networkKeys */
    public function __construct(
        public ?string $heading,
        public ?SocialLabelStyle $labelStyle,
        public ?bool $openInNewTab,
        public string $alignment,
        public ?array $networkKeys,
    ) {
        if (! in_array($this->alignment, ['start', 'center', 'end'], true)) {
            throw new InvalidArgumentException('Social widget alignment is invalid.');
        }
    }

    /** @param array<string, mixed> $state */
    public static function fromState(array $state, SocialNetworkRegistry $registry): self
    {
        $heading = is_string($state['heading'] ?? null) ? trim($state['heading']) : null;
        $networkKeys = self::networkKeys($state['share_network_keys'] ?? null, $registry);

        return new self(
            heading: $heading === null || $heading === '' ? null : mb_substr($heading, 0, 120),
            labelStyle: self::labelStyle($state['label_style'] ?? 'inherit'),
            openInNewTab: self::linkTarget($state['link_target'] ?? 'inherit'),
            alignment: is_string($state['alignment'] ?? null) ? $state['alignment'] : 'start',
            networkKeys: $networkKeys,
        );
    }

    private static function labelStyle(mixed $value): ?SocialLabelStyle
    {
        if ($value === 'inherit' || $value === null) {
            return null;
        }

        return is_string($value) ? SocialLabelStyle::from($value) : throw new InvalidArgumentException('Social widget label style is invalid.');
    }

    private static function linkTarget(mixed $value): ?bool
    {
        return match ($value) {
            'inherit', null => null,
            'same_tab' => false,
            'new_tab' => true,
            default => throw new InvalidArgumentException('Social widget link target is invalid.'),
        };
    }

    /** @return list<string>|null */
    private static function networkKeys(mixed $value, SocialNetworkRegistry $registry): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('Social widget share network selection is invalid.');
        }

        $networkKeys = [];

        foreach ($value as $key) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Social widget share network selection is invalid.');
            }

            $network = $registry->get($key);

            if ($network === null || ! $network->supports(SocialNetworkCapability::Share)) {
                throw new InvalidArgumentException('Social widget share network selection is invalid.');
            }

            $networkKeys[$network->key] = true;
        }

        return array_keys($networkKeys);
    }
}
