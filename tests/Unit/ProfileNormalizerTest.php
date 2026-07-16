<?php

declare(strict_types=1);

use Capell\Socials\Support\BuiltIns\BuiltInSocialNetworkDefinitions;
use Capell\Socials\Support\HttpUrlValidator;

it('normalizes supported network handles and full URLs', function (string $networkKey, string $input, string $url, ?string $handle): void {
    $definition = collect(BuiltInSocialNetworkDefinitions::all())->firstWhere('key', $networkKey);

    expect($definition)->not->toBeNull()
        ->and($definition->normalizer->normalize($input)->url)->toBe($url)
        ->and($definition->normalizer->normalize($input)->handle)->toBe($handle);
})->with([
    ['x', '@capell', 'https://x.com/capell', 'capell'],
    ['x', 'https://twitter.com/capell', 'https://x.com/capell', 'capell'],
    ['facebook', 'capell', 'https://facebook.com/capell', 'capell'],
    ['instagram', '@capell', 'https://instagram.com/capell', 'capell'],
    ['linkedin', 'capell', 'https://linkedin.com/in/capell', 'capell'],
    ['youtube', '@capell', 'https://youtube.com/@capell', 'capell'],
    ['tiktok', '@capell', 'https://tiktok.com/@capell', 'capell'],
    ['pinterest', 'capell', 'https://pinterest.com/capell', 'capell'],
    ['whatsapp', '15551234567', 'https://wa.me/15551234567', '15551234567'],
    ['bluesky', 'capell.bsky.social', 'https://bsky.app/profile/capell.bsky.social', 'capell.bsky.social'],
    ['mastodon', '@capell@mastodon.social', 'https://mastodon.social/@capell', 'capell'],
    ['threads', '@capell', 'https://threads.net/@capell', 'capell'],
]);

it('normalizes accepted full URLs for every built-in social network', function (string $networkKey, string $input, string $url, ?string $handle): void {
    $definition = collect(BuiltInSocialNetworkDefinitions::all())->firstWhere('key', $networkKey);

    expect($definition)->not->toBeNull()
        ->and($definition->normalizer->normalize($input)->url)->toBe($url)
        ->and($definition->normalizer->normalize($input)->handle)->toBe($handle);
})->with([
    ['x', 'https://twitter.com/capell', 'https://x.com/capell', 'capell'],
    ['facebook', 'https://m.facebook.com/capell', 'https://facebook.com/capell', 'capell'],
    ['instagram', 'https://www.instagram.com/capell', 'https://instagram.com/capell', 'capell'],
    ['linkedin', 'https://www.linkedin.com/in/capell', 'https://linkedin.com/in/capell', 'capell'],
    ['youtube', 'https://m.youtube.com/@capell', 'https://youtube.com/@capell', 'capell'],
    ['tiktok', 'https://www.tiktok.com/@capell', 'https://tiktok.com/@capell', 'capell'],
    ['pinterest', 'https://www.pinterest.com/capell', 'https://pinterest.com/capell', 'capell'],
    ['whatsapp', 'https://api.whatsapp.com/15551234567', 'https://wa.me/15551234567', '15551234567'],
    ['bluesky', 'https://bsky.app/profile/capell.bsky.social', 'https://bsky.app/profile/capell.bsky.social', 'capell.bsky.social'],
    ['mastodon', 'https://mastodon.social/@capell', 'https://mastodon.social/%40capell', 'capell'],
    ['threads', 'https://www.threads.net/@capell', 'https://threads.net/@capell', 'capell'],
]);

it('rejects unsafe and unsupported profile URLs', function (string $value): void {
    (new HttpUrlValidator)->validate($value);
})->with([
    'javascript URL' => 'javascript:alert(1)',
    'data URL' => 'data:text/html,hello',
    'relative URL' => '/profile/capell',
    'ftp URL' => 'ftp://example.com/capell',
])->throws(InvalidArgumentException::class);

it('rejects a full URL on the wrong social host', function (): void {
    $definition = collect(BuiltInSocialNetworkDefinitions::all())->firstWhere('key', 'instagram');

    $definition->normalizer->normalize('https://evil.example/capell');
})->throws(InvalidArgumentException::class);

it('rejects non-profile routes for networks with root profile paths', function (string $url): void {
    $definition = collect(BuiltInSocialNetworkDefinitions::all())->firstWhere('key', 'x');

    $definition->normalizer->normalize($url);
})->with([
    'share intent' => 'https://x.com/intent/post',
    'status route' => 'https://x.com/user/status/123',
])->throws(InvalidArgumentException::class, 'The social profile URL is invalid.');

it('requires network-specific profile path prefixes', function (): void {
    $definition = collect(BuiltInSocialNetworkDefinitions::all())->firstWhere('key', 'linkedin');

    $definition->normalizer->normalize('https://linkedin.com/company/capell');
})->throws(InvalidArgumentException::class, 'The social profile URL is invalid.');
