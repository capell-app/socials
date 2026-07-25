<?php

declare(strict_types=1);

namespace Capell\Socials\Contracts;

use Capell\Socials\Data\SharePageContextData;

interface SocialShareUrlGenerator
{
    public function generate(SharePageContextData $context): string;
}
