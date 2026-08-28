<?php

declare(strict_types=1);

use Capell\Socials\Actions\OverrideSocialNetworkDefinitionAction;
use Capell\Socials\Data\SocialNetworkDefinitionData;
use Capell\Socials\Enums\SocialNetworkCapability;
use Capell\Socials\Support\BuiltIns\BuiltInSocialNetworkDefinitions;
use Capell\Socials\Support\NetworkProfileNormalizer;
use Capell\Socials\Support\SocialNetworkRegistry;
use Illuminate\Container\Container;

it('resolves built-in aliases to their canonical definition', function (): void {
    $registry = new SocialNetworkRegistry;

    foreach (BuiltInSocialNetworkDefinitions::all() as $definition) {
        $registry->register($definition);
    }

    expect($registry->get('TWITTER')?->key)->toBe('x')
        ->and(array_keys($registry->all()))->toBe([
            'x', 'facebook', 'instagram', 'linkedin', 'youtube', 'tiktok', 'pinterest', 'whatsapp', 'bluesky', 'mastodon', 'threads',
        ]);
});

it('rejects canonical key and alias collisions', function (): void {
    $registry = new SocialNetworkRegistry;
    $normalizer = new NetworkProfileNormalizer(['example.com'], 'example.com');

    $registry->register(new SocialNetworkDefinitionData('alpha', ['first'], 'Alpha', 'alpha', [SocialNetworkCapability::Follow], $normalizer, $normalizer));
    $registry->register(new SocialNetworkDefinitionData('FIRST', [], 'First', 'first', [SocialNetworkCapability::Follow], $normalizer, $normalizer));
})->throws(InvalidArgumentException::class, 'Social network key [first] is already registered.');

it('rejects alias collisions', function (): void {
    $registry = new SocialNetworkRegistry;
    $normalizer = new NetworkProfileNormalizer(['example.com'], 'example.com');

    $registry->register(new SocialNetworkDefinitionData('alpha', ['first'], 'Alpha', 'alpha', [SocialNetworkCapability::Follow], $normalizer, $normalizer));
    $registry->register(new SocialNetworkDefinitionData('beta', ['FIRST'], 'Beta', 'beta', [SocialNetworkCapability::Follow], $normalizer, $normalizer));
})->throws(InvalidArgumentException::class, 'Social network alias [first] collides with an existing social network key or alias.');

it('keeps networks without stable identity support follow-only', function (): void {
    $definitions = collect(BuiltInSocialNetworkDefinitions::all())->keyBy('key');

    foreach (['instagram', 'youtube', 'tiktok', 'mastodon', 'threads'] as $key) {
        $definition = $definitions->firstOrFail(
            fn (SocialNetworkDefinitionData $definition): bool => $definition->key === $key,
        );

        expect($definition->supports(SocialNetworkCapability::Follow))->toBeTrue()
            ->and($definition->supports(SocialNetworkCapability::SameAs))->toBeFalse()
            ->and($definition->supports(SocialNetworkCapability::Share))->toBeFalse();
    }
});

it('requires a share URL generator when a network supports sharing', function (): void {
    $normalizer = new NetworkProfileNormalizer(['example.com'], 'example.com');

    new SocialNetworkDefinitionData('alpha', [], 'Alpha', 'alpha', [SocialNetworkCapability::Share], $normalizer, $normalizer);
})->throws(InvalidArgumentException::class, 'Social networks with the share capability must provide a share URL generator.');

it('keeps the current definition when an override is invalid', function (): void {
    $registry = new SocialNetworkRegistry;
    $normalizer = new NetworkProfileNormalizer(['example.com'], 'example.com');
    $registry->register(new SocialNetworkDefinitionData('alpha', ['first'], 'Alpha', 'alpha', [SocialNetworkCapability::Follow], $normalizer, $normalizer));
    $registry->register(new SocialNetworkDefinitionData('beta', [], 'Beta', 'beta', [SocialNetworkCapability::Follow], $normalizer, $normalizer));

    expect(fn () => OverrideSocialNetworkDefinitionAction::run(
        new SocialNetworkDefinitionData('alpha', ['beta'], 'Replacement', 'alpha', [SocialNetworkCapability::Follow], $normalizer, $normalizer),
        $registry,
    ))->toThrow(InvalidArgumentException::class)
        ->and($registry->get('alpha')?->label)->toBe('Alpha')
        ->and($registry->get('first')?->key)->toBe('alpha');
});

it('resolves registry labels per locale instead of freezing the registration-time translation', function (): void {
    $translator = Container::getInstance()->make('translator');
    $translator->addLines(['socials.networks.x' => 'X'], 'en', 'capell-socials');
    $translator->addLines(['socials.networks.x' => 'Zwitscher'], 'de', 'capell-socials');

    $registry = new SocialNetworkRegistry;

    foreach (BuiltInSocialNetworkDefinitions::all() as $definition) {
        $registry->register($definition);
    }

    $network = $registry->get('x');

    expect($network?->resolveLabel())->toBe('X')
        ->and($network?->resolveLabel('de'))->toBe('Zwitscher')
        ->and($network?->resolveLabel('en'))->toBe('X');
});
