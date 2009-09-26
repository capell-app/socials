<?php

declare(strict_types=1);

namespace Capell\Socials\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RunsExtensionMigration;

final class SocialsMigrationsContribution implements ExtensionContribution, RunsExtensionMigration
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
