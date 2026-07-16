<?php

declare(strict_types=1);

namespace Capell\Socials\Contracts;

interface SocialProfileValidator
{
    public function validate(string $value): void;
}
