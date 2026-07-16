<?php

declare(strict_types=1);

namespace Capell\Socials\Enums;

enum SocialNetworkCapability: string
{
    case Follow = 'follow';
    case Share = 'share';
    case SameAs = 'same_as';
    case TwitterSite = 'twitter_site';
}
