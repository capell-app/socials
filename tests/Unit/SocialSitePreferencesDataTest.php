<?php

declare(strict_types=1);

use Capell\Socials\Data\SocialSitePreferencesData;
use Capell\Socials\Enums\SocialLabelStyle;
use Capell\Socials\Support\BuiltIns\BuiltInSocialNetworkDefinitions;
use Capell\Socials\Support\SocialNetworkRegistry;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;

beforeEach(function (): void {
    app()->instance('translator', new Translator(new ArrayLoader, 'en'));
});

function socialNetworkRegistryWithBuiltIns(): SocialNetworkRegistry
{
    $registry = new SocialNetworkRegistry;

    foreach (BuiltInSocialNetworkDefinitions::all() as $definition) {
        $registry->register($definition);
    }

    return $registry;
}

it('normalizes unique share network keys while retaining the supplied preference values', function (): void {
    $preferences = new SocialSitePreferencesData(
        networkRegistry: socialNetworkRegistryWithBuiltIns(),
        followLabelStyle: SocialLabelStyle::Labels,
        followOpenInNewTab: true,
        shareNetworkKeys: [' X ', 'facebook', 'twitter'],
        shareLabelStyle: SocialLabelStyle::IconsAndLabels,
        shareOpenInNewTab: true,
    );

    expect($preferences->followLabelStyle)->toBe(SocialLabelStyle::Labels)
        ->and($preferences->followOpenInNewTab)->toBeTrue()
        ->and($preferences->shareNetworkKeys)->toBe(['x', 'facebook'])
        ->and($preferences->shareLabelStyle)->toBe(SocialLabelStyle::IconsAndLabels)
        ->and($preferences->shareOpenInNewTab)->toBeTrue();
});

it('rejects invalid share network keys', function (array $shareNetworkKeys, string $message): void {
    expect(fn (): SocialSitePreferencesData => new SocialSitePreferencesData(
        networkRegistry: socialNetworkRegistryWithBuiltIns(),
        shareNetworkKeys: $shareNetworkKeys,
    ))
        ->toThrow(InvalidArgumentException::class, $message);
})->with(/** @return array<string, array{list<mixed>, string}> */ fn (): array => [
    'empty key' => [[''], 'Share network keys must not be empty.'],
    'non-string key' => [[123], 'Share network keys must be strings.'],
    'follow-only network' => [['mastodon'], 'Social network [mastodon] does not support sharing.'],
    'unknown network' => [['made-up'], 'Social network [made-up] is not registered.'],
]);
