<?php

declare(strict_types=1);

namespace Capell\Socials\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class SocialProfileModelContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
