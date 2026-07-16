<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

use Capell\Socials\Enums\SocialLabelStyle;
use InvalidArgumentException;

final readonly class SocialFollowWidgetConfigData
{
    /**
     * @param  list<int>|null  $profileIds
     * @param  list<SocialCustomLinkConfigurationData>  $customLinks
     */
    public function __construct(
        public ?string $heading,
        public ?SocialLabelStyle $labelStyle,
        public ?bool $openInNewTab,
        public string $alignment,
        public ?array $profileIds,
        public array $customLinks,
    ) {
        if (! in_array($this->alignment, ['start', 'center', 'end'], true)) {
            throw new InvalidArgumentException('Social widget alignment is invalid.');
        }

        if (count($this->customLinks) > 10) {
            throw new InvalidArgumentException('Social widgets support at most ten custom links.');
        }
    }

    /** @param array<string, mixed> $state */
    public static function fromState(array $state): self
    {
        $heading = is_string($state['heading'] ?? null) ? trim($state['heading']) : null;
        $labelStyle = self::labelStyle($state['label_style'] ?? 'inherit');
        $profileIds = self::profileIds($state['profile_ids'] ?? null);
        $customLinks = self::customLinks($state['custom_links'] ?? []);

        return new self(
            heading: $heading === null || $heading === '' ? null : mb_substr($heading, 0, 120),
            labelStyle: $labelStyle,
            openInNewTab: self::linkTarget($state['link_target'] ?? 'inherit'),
            alignment: is_string($state['alignment'] ?? null) ? $state['alignment'] : 'start',
            profileIds: $profileIds,
            customLinks: $customLinks,
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

    /** @return list<int>|null */
    private static function profileIds(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('Social widget profile selection is invalid.');
        }

        $profileIds = [];

        foreach ($value as $profileId) {
            if (! is_int($profileId) && ! (is_string($profileId) && ctype_digit($profileId))) {
                throw new InvalidArgumentException('Social widget profile selection is invalid.');
            }

            $profileIds[(int) $profileId] = true;
        }

        return array_keys($profileIds);
    }

    /** @return list<SocialCustomLinkConfigurationData> */
    private static function customLinks(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Social widget custom links are invalid.');
        }

        $links = [];

        foreach ($value as $link) {
            if (! is_array($link) || ! is_string($link['label'] ?? null) || ! is_string($link['url'] ?? null)) {
                throw new InvalidArgumentException('Social widget custom links are invalid.');
            }

            $links[] = new SocialCustomLinkConfigurationData(trim($link['label']), trim($link['url']));
        }

        return $links;
    }
}
