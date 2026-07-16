<?php

declare(strict_types=1);

namespace Capell\Socials\Support;

use Capell\Socials\Contracts\SocialProfileNormalizer;
use Capell\Socials\Contracts\SocialProfileValidator;
use Capell\Socials\Data\NormalizedSocialProfileData;
use InvalidArgumentException;

final readonly class NetworkProfileNormalizer implements SocialProfileNormalizer, SocialProfileValidator
{
    /** @param list<string> $hosts */
    public function __construct(
        private array $hosts,
        private string $canonicalHost,
        private string $pathPrefix = '',
        private bool $allowHandle = true,
    ) {}

    public function normalize(string $value): NormalizedSocialProfileData
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('A social profile value is required.');
        }

        if ($this->isUrl($value)) {
            $this->validate($value);
            $path = (string) parse_url($value, PHP_URL_PATH);
            $handle = $this->handleFromPath($path);

            return new NormalizedSocialProfileData(
                url: 'https://' . $this->canonicalHost . $path . $this->queryAndFragment($value),
                handle: $handle,
            );
        }

        if (! $this->allowHandle) {
            throw new InvalidArgumentException('This social network requires a full profile URL.');
        }

        $handle = ltrim($value, '@');

        if (preg_match('/^[A-Za-z0-9._-]{1,100}$/', $handle) !== 1) {
            throw new InvalidArgumentException('The social profile handle is invalid.');
        }

        return new NormalizedSocialProfileData(
            url: 'https://' . $this->canonicalHost . $this->pathPrefix . rawurlencode($handle),
            handle: $handle,
        );
    }

    public function validate(string $value): void
    {
        $value = trim($value);

        if (! $this->isUrl($value)) {
            if (! $this->allowHandle || preg_match('/^@?[A-Za-z0-9._-]{1,100}$/', $value) !== 1) {
                throw new InvalidArgumentException('The social profile value is invalid.');
            }

            return;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $path = (string) parse_url($value, PHP_URL_PATH);

        if (! in_array(strtolower((string) $scheme), ['http', 'https'], true) || ! in_array($host, $this->hosts, true)) {
            throw new InvalidArgumentException('The social profile URL is invalid.');
        }

        $this->handleFromPath($path);
    }

    private function isUrl(string $value): bool
    {
        return preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) === 1;
    }

    private function handleFromPath(string $path): string
    {
        $profilePath = $this->profilePath($path);

        if ($profilePath === '' || str_contains($profilePath, '/')) {
            throw new InvalidArgumentException('The social profile URL is invalid.');
        }

        $handle = ltrim(rawurldecode($profilePath), '@');

        if ($handle === '' || preg_match('/^[A-Za-z0-9._-]{1,100}$/', $handle) !== 1) {
            throw new InvalidArgumentException('The social profile URL is invalid.');
        }

        return $handle;
    }

    private function profilePath(string $path): string
    {
        if ($this->pathPrefix === '' || $this->pathPrefix === '/') {
            return rtrim(ltrim($path, '/'), '/');
        }

        if (! str_starts_with($path, $this->pathPrefix)) {
            throw new InvalidArgumentException('The social profile URL is invalid.');
        }

        return rtrim(substr($path, strlen($this->pathPrefix)), '/');
    }

    private function queryAndFragment(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        return ($query === null ? '' : '?' . $query) . ($fragment === null ? '' : '#' . $fragment);
    }
}
