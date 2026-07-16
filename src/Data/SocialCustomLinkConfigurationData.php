<?php

declare(strict_types=1);

namespace Capell\Socials\Data;

use Capell\Socials\Support\HttpUrlValidator;
use InvalidArgumentException;

final readonly class SocialCustomLinkConfigurationData
{
    public function __construct(
        public string $label,
        public string $url,
    ) {
        if (trim($this->label) === '') {
            throw new InvalidArgumentException('Custom social links require a label.');
        }

        (new HttpUrlValidator)->validate($this->url);
    }
}
