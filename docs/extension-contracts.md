# Extension and action examples

<!-- Maintained by scripts/generate-package-readmes.php -->

Use the action functions with records and Data objects supplied by your application.
They pass each argument to the package operation and return its result.

These adapters show container registration. Use the owning package registry when
a contract requires contributor discovery.

Contract adapters wrap an existing implementation. Call their registration function
from your service provider with that implementation; tagged contracts keep their declared tag.
Resolve the backend by its concrete class before registration so the replacement contract
does not resolve itself. Static contract metadata uses one backend class per adapter.

## Contract `Capell\Socials\Contracts\SocialNetworkRegistry`

<!-- example: contract Capell\Socials\Contracts\SocialNetworkRegistry -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\Socials;

final class SocialNetworkRegistryAdapter implements \Capell\Socials\Contracts\SocialNetworkRegistry
{
    public function __construct(private readonly \Capell\Socials\Contracts\SocialNetworkRegistry $backend) {}

    #[\Override]
    public function all(): array
    {
        return $this->backend->all();
    }

    #[\Override]
    public function get(string $key): ?\Capell\Socials\Data\SocialNetworkDefinitionData
    {
        return $this->backend->get($key);
    }

    #[\Override]
    public function register(\Capell\Socials\Data\SocialNetworkDefinitionData $network): void
    {
        $this->backend->register($network);
    }
}

function registerSocialNetworkRegistryAdapter(\Capell\Socials\Contracts\SocialNetworkRegistry $backend): void
{
    app()->bind(SocialNetworkRegistryAdapter::class, static fn (): SocialNetworkRegistryAdapter => new SocialNetworkRegistryAdapter($backend));
    app()->bind(\Capell\Socials\Contracts\SocialNetworkRegistry::class, SocialNetworkRegistryAdapter::class);
}
```

## Contract `Capell\Socials\Contracts\SocialProfileNormalizer`

<!-- example: contract Capell\Socials\Contracts\SocialProfileNormalizer -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\Socials;

final class SocialProfileNormalizerAdapter implements \Capell\Socials\Contracts\SocialProfileNormalizer
{
    public function __construct(private readonly \Capell\Socials\Contracts\SocialProfileNormalizer $backend) {}

    #[\Override]
    public function normalize(string $value): \Capell\Socials\Data\NormalizedSocialProfileData
    {
        return $this->backend->normalize($value);
    }
}

function registerSocialProfileNormalizerAdapter(\Capell\Socials\Contracts\SocialProfileNormalizer $backend): void
{
    app()->bind(SocialProfileNormalizerAdapter::class, static fn (): SocialProfileNormalizerAdapter => new SocialProfileNormalizerAdapter($backend));
    app()->bind(\Capell\Socials\Contracts\SocialProfileNormalizer::class, SocialProfileNormalizerAdapter::class);
}
```

## Contract `Capell\Socials\Contracts\SocialProfileValidator`

<!-- example: contract Capell\Socials\Contracts\SocialProfileValidator -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\Socials;

final class SocialProfileValidatorAdapter implements \Capell\Socials\Contracts\SocialProfileValidator
{
    public function __construct(private readonly \Capell\Socials\Contracts\SocialProfileValidator $backend) {}

    #[\Override]
    public function validate(string $value): void
    {
        $this->backend->validate($value);
    }
}

function registerSocialProfileValidatorAdapter(\Capell\Socials\Contracts\SocialProfileValidator $backend): void
{
    app()->bind(SocialProfileValidatorAdapter::class, static fn (): SocialProfileValidatorAdapter => new SocialProfileValidatorAdapter($backend));
    app()->bind(\Capell\Socials\Contracts\SocialProfileValidator::class, SocialProfileValidatorAdapter::class);
}
```

## Contract `Capell\Socials\Contracts\SocialProfilesResolver`

<!-- example: contract Capell\Socials\Contracts\SocialProfilesResolver -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\Socials;

final class SocialProfilesResolverAdapter implements \Capell\Socials\Contracts\SocialProfilesResolver
{
    public function __construct(private readonly \Capell\Socials\Contracts\SocialProfilesResolver $backend) {}

    #[\Override]
    public function hasConfiguration(\Capell\Core\Models\Site $site): bool
    {
        return $this->backend->hasConfiguration($site);
    }

    #[\Override]
    public function resolve(\Capell\Core\Models\Site $site, string $locale, ?array $profileIds = null): \Capell\Socials\Data\SocialProfilesData
    {
        return $this->backend->resolve($site, $locale, $profileIds);
    }
}

function registerSocialProfilesResolverAdapter(\Capell\Socials\Contracts\SocialProfilesResolver $backend): void
{
    app()->bind(SocialProfilesResolverAdapter::class, static fn (): SocialProfilesResolverAdapter => new SocialProfilesResolverAdapter($backend));
    app()->bind(\Capell\Socials\Contracts\SocialProfilesResolver::class, SocialProfilesResolverAdapter::class);
}
```

## Contract `Capell\Socials\Contracts\SocialShareUrlGenerator`

<!-- example: contract Capell\Socials\Contracts\SocialShareUrlGenerator -->

```php
<?php
declare(strict_types=1);

namespace App\CapellExamples\Socials;

final class SocialShareUrlGeneratorAdapter implements \Capell\Socials\Contracts\SocialShareUrlGenerator
{
    public function __construct(private readonly \Capell\Socials\Contracts\SocialShareUrlGenerator $backend) {}

    #[\Override]
    public function generate(\Capell\Socials\Data\SharePageContextData $context): string
    {
        return $this->backend->generate($context);
    }
}

function registerSocialShareUrlGeneratorAdapter(\Capell\Socials\Contracts\SocialShareUrlGenerator $backend): void
{
    app()->bind(SocialShareUrlGeneratorAdapter::class, static fn (): SocialShareUrlGeneratorAdapter => new SocialShareUrlGeneratorAdapter($backend));
    app()->bind(\Capell\Socials\Contracts\SocialShareUrlGenerator::class, SocialShareUrlGeneratorAdapter::class);
}
```
