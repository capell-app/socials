<?php

declare(strict_types=1);

namespace Capell\Socials\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionFrontendComponent;

final class SocialsWidgetContribution implements ExtensionContribution, RegistersExtensionFrontendComponent
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
