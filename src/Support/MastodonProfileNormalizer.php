<?php

declare(strict_types=1);

namespace Capell\Socials\Support;

use Capell\Socials\Contracts\SocialProfileNormalizer;
use Capell\Socials\Contracts\SocialProfileValidator;
use Capell\Socials\Data\NormalizedSocialProfileData;
use InvalidArgumentException;

final class MastodonProfileNormalizer implements SocialProfileNormalizer, SocialProfileValidator
{
    public function normalize(string $value): NormalizedSocialProfileData
    {
        $value = trim($value);

        if (preg_match('/^@?([A-Za-z0-9._-]+)@([A-Za-z0-9.-]+)$/', $value, $matches) === 1) {
            return new NormalizedSocialProfileData('https://' . strtolower($matches[2]) . '/@' . rawurlencode($matches[1]), $matches[1]);
        }

        $this->validate($value);
        $path = trim((string) parse_url($value, PHP_URL_PATH), '/');
        $handle = str_starts_with($path, '@') ? substr($path, 1) : $path;

        return new NormalizedSocialProfileData(
            'https://' . strtolower((string) parse_url($value, PHP_URL_HOST)) . '/' . rawurlencode($path),
            $handle === '' || str_contains($handle, '/') ? null : $handle,
        );
    }

    public function validate(string $value): void
    {
        if (preg_match('/^@?[A-Za-z0-9._-]+@[A-Za-z0-9.-]+$/', trim($value)) === 1) {
            return;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);
        $path = trim((string) parse_url($value, PHP_URL_PATH), '/');

        if (! in_array(strtolower((string) $scheme), ['http', 'https'], true) || ! is_string($host) || $host === '' || $path === '') {
            throw new InvalidArgumentException('A valid Mastodon profile is required.');
        }
    }
}
