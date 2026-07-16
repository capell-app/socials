<?php

declare(strict_types=1);

namespace Capell\Socials\Support;

use Capell\Socials\Contracts\SocialShareUrlGenerator;
use Capell\Socials\Data\SharePageContextData;

final readonly class MessageShareUrlGenerator implements SocialShareUrlGenerator
{
    public function __construct(private string $endpoint) {}

    public function generate(SharePageContextData $context): string
    {
        return $this->endpoint . '?' . http_build_query([
            'text' => trim($context->title . ' ' . $context->canonicalUrl),
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
