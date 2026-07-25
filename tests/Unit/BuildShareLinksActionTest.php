<?php

declare(strict_types=1);

use Capell\Socials\Actions\BuildShareLinksAction;
use Capell\Socials\Data\SharePageContextData;
use Capell\Socials\Support\BuiltIns\BuiltInSocialNetworkDefinitions;
use Capell\Socials\Support\SocialNetworkRegistry;

it('builds deduplicated RFC 3986 share URLs without tracker parameters', function (): void {
    $registry = new SocialNetworkRegistry;

    foreach (BuiltInSocialNetworkDefinitions::all() as $definition) {
        $registry->register($definition);
    }

    $links = BuildShareLinksAction::run(
        new SharePageContextData('https://capell.test/hello?tag=one two', 'A title & more', 'en'),
        ['twitter', 'facebook', 'x', 'instagram', 'whatsapp', 'bluesky', 'pinterest'],
        $registry,
    );

    expect(array_column($links->links, 'networkKey'))->toBe(['x', 'facebook', 'whatsapp', 'bluesky', 'pinterest'])
        ->and($links->links[0]->url)->toBe('https://x.com/intent/post?text=A%20title%20%26%20more&url=https%3A%2F%2Fcapell.test%2Fhello%3Ftag%3Done%20two')
        ->and($links->links[2]->url)->toBe('https://wa.me/?text=A%20title%20%26%20more%20https%3A%2F%2Fcapell.test%2Fhello%3Ftag%3Done%20two')
        ->and($links->links[4]->url)->toBe('https://pinterest.com/pin/create/button/?url=https%3A%2F%2Fcapell.test%2Fhello%3Ftag%3Done%20two&description=A%20title%20%26%20more')
        ->and($links->links[0]->url)->not->toContain('utm_');
});

it('requires an absolute canonical URL with a host', function (): void {
    new SharePageContextData('https:///missing-host', 'Title', 'en');
})->throws(InvalidArgumentException::class, 'Share URLs require an absolute HTTP(S) canonical URL.');
