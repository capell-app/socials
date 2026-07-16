<?php

declare(strict_types=1);

namespace Capell\Socials\Support\BuiltIns;

use Capell\Socials\Data\SocialNetworkDefinitionData;
use Capell\Socials\Enums\SocialNetworkCapability;
use Capell\Socials\Support\MastodonProfileNormalizer;
use Capell\Socials\Support\MessageShareUrlGenerator;
use Capell\Socials\Support\NetworkProfileNormalizer;
use Capell\Socials\Support\ShareUrlGenerator;

final class BuiltInSocialNetworkDefinitions
{
    /** @return list<SocialNetworkDefinitionData> */
    public static function all(): array
    {
        return [
            self::network('x', ['twitter'], 'x-mark', ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'], '/', [SocialNetworkCapability::Follow, SocialNetworkCapability::Share, SocialNetworkCapability::SameAs, SocialNetworkCapability::TwitterSite], new ShareUrlGenerator('https://x.com/intent/post', ['text' => 'title', 'url' => 'url'])),
            self::network('facebook', ['fb'], 'facebook', ['facebook.com', 'www.facebook.com', 'm.facebook.com'], '/', [SocialNetworkCapability::Follow, SocialNetworkCapability::Share, SocialNetworkCapability::SameAs], new ShareUrlGenerator('https://www.facebook.com/sharer/sharer.php', ['u' => 'url'])),
            self::network('instagram', ['ig'], 'instagram', ['instagram.com', 'www.instagram.com'], '/', [SocialNetworkCapability::Follow]),
            self::network('linkedin', ['linked-in'], 'linkedin', ['linkedin.com', 'www.linkedin.com'], '/in/', [SocialNetworkCapability::Follow, SocialNetworkCapability::Share, SocialNetworkCapability::SameAs], new ShareUrlGenerator('https://www.linkedin.com/sharing/share-offsite/', ['url' => 'url'])),
            self::network('youtube', ['yt'], 'youtube', ['youtube.com', 'www.youtube.com', 'm.youtube.com'], '/@', [SocialNetworkCapability::Follow]),
            self::network('tiktok', ['tik-tok'], 'tiktok', ['tiktok.com', 'www.tiktok.com'], '/@', [SocialNetworkCapability::Follow]),
            self::network('pinterest', ['pin'], 'pinterest', ['pinterest.com', 'www.pinterest.com'], '/', [SocialNetworkCapability::Follow, SocialNetworkCapability::Share, SocialNetworkCapability::SameAs], new ShareUrlGenerator('https://pinterest.com/pin/create/button/', ['url' => 'url', 'description' => 'title'])),
            self::network('whatsapp', ['wa'], 'whatsapp', ['wa.me', 'api.whatsapp.com', 'web.whatsapp.com'], '/', [SocialNetworkCapability::Follow, SocialNetworkCapability::Share], new MessageShareUrlGenerator('https://wa.me/')),
            self::network('bluesky', ['bsky'], 'bluesky', ['bsky.app'], '/profile/', [SocialNetworkCapability::Follow, SocialNetworkCapability::Share, SocialNetworkCapability::SameAs], new MessageShareUrlGenerator('https://bsky.app/intent/compose')),
            self::mastodon(),
            self::network('threads', ['threads-net'], 'threads', ['threads.net', 'www.threads.net'], '/@', [SocialNetworkCapability::Follow]),
        ];
    }

    /** @param list<string> $aliases @param list<string> $hosts @param list<SocialNetworkCapability> $capabilities */
    private static function network(string $key, array $aliases, string $icon, array $hosts, string $pathPrefix, array $capabilities, ShareUrlGenerator|MessageShareUrlGenerator|null $shareUrlGenerator = null): SocialNetworkDefinitionData
    {
        $normalizer = new NetworkProfileNormalizer($hosts, $hosts[0], $pathPrefix);

        return new SocialNetworkDefinitionData($key, $aliases, self::label($key), $icon, $capabilities, $normalizer, $normalizer, $shareUrlGenerator);
    }

    private static function mastodon(): SocialNetworkDefinitionData
    {
        $normalizer = new MastodonProfileNormalizer;

        return new SocialNetworkDefinitionData(
            'mastodon',
            ['fediverse'],
            self::label('mastodon'),
            'mastodon',
            [SocialNetworkCapability::Follow],
            $normalizer,
            $normalizer,
        );
    }

    private static function label(string $key): string
    {
        return __(sprintf('capell-socials::socials.networks.%s', $key));
    }
}
