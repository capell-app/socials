<?php

declare(strict_types=1);

namespace Capell\Socials\Support;

use Capell\Socials\Contracts\SocialShareUrlGenerator;
use Capell\Socials\Data\SharePageContextData;

final readonly class ShareUrlGenerator implements SocialShareUrlGenerator
{
    /** @param array<string, 'url'|'title'> $parameters */
    public function __construct(private string $endpoint, private array $parameters) {}

    public function generate(SharePageContextData $context): string
    {
        $query = [];

        foreach ($this->parameters as $parameter => $contextProperty) {
            $query[$parameter] = $contextProperty === 'url' ? $context->canonicalUrl : $context->title;
        }

        return $this->endpoint . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
