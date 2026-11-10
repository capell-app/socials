# Socials reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/socials`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Socials is an **Available**, **Schema-owning** Capell package in the **Capell Essentials** product group. It ships as `capell-app/socials` and extends these surfaces: admin, frontend, console, shared.

- Status: Available
- Tier: free
- Bundle: foundation
- Composer package: `capell-app/socials`
- Namespace: `Capell\Socials`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\Socials\Providers\MigrationMetadataServiceProvider`
- `Capell\Socials\Providers\SocialsServiceProvider`
- `Capell\Socials\Providers\AdminServiceProvider`

### Migrations

- `packages/socials/database/migrations/2026_07_16_000001_create_social_profiles_table.php`
- `packages/socials/database/migrations/2026_07_16_000002_create_social_site_preferences_table.php`
- `packages/socials/database/migrations/2026_08_31_000001_add_share_networks_customised_to_social_site_preferences.php`
- `packages/socials/database/migrations/2026_09_07_000002_make_share_network_keys_nullable.php`

### Models

- `SocialProfile`
- `SocialSitePreferences`

### Filament classes

- `SocialsBuilderBlock`
- `SocialsPage`

### Extension contracts

- `SocialNetworkRegistry`
- `SocialProfileNormalizer`
- `SocialProfileValidator`
- `SocialProfilesResolver`
- `SocialShareUrlGenerator`

### Actions

- `BuildFollowSocialRenderDataAction`
- `BuildShareLinksAction`
- `BuildShareSocialRenderDataAction`
- `ImportLegacySocialProfilesAction`
- `OverrideSocialNetworkDefinitionAction`
- `PrepareSocialSiteRenderDataAction`
- `RegisterBuiltInSocialNetworksAction`
- `ResolveSiteSocialProfilesAction`
- `ResolveSocialProfileChoicesAction`
- `SaveSocialSiteConfigurationAction`
- `SeedSocialsScreenshotFixtureAction`

### Data objects

- `LegacySocialImportResultData`
- `NormalizedSocialProfileData`
- `PreparedSocialProfileData`
- `PreparedSocialSiteData`
- `ShareLinkData`
- `ShareLinksData`
- `SharePageContextData`
- `SocialCustomLinkConfigurationData`
- `SocialFollowRenderData`
- `SocialFollowWidgetConfigData`
- `SocialNetworkDefinitionData`
- `SocialProfileChoicesData`
- `SocialProfileConfigurationData`
- `SocialProfileData`
- `SocialProfilesData`
- `SocialShareRenderData`
- `SocialShareWidgetConfigData`
- `SocialSitePreferencesData`

### Command signatures

- `capell:socials-install`

### Console command classes

- `InstallSocialsCommand`
- `SeedSocialsScreenshotFixtureCommand`

### Manifest contributions

- `admin-page: Capell\Socials\Manifest\SocialsAdminPageContribution`
- `frontend-component: Capell\Socials\Manifest\SocialsWidgetContribution`
- `migration: Capell\Socials\Manifest\SocialsMigrationsContribution`
- `model: Capell\Socials\Manifest\SocialProfileModelContribution`
- `model: Capell\Socials\Manifest\SocialSitePreferencesModelContribution`

### Health checks

- `Capell\Socials\Health\SocialsHealthCheck`

### Blade views

- `packages/socials/resources/views/blocks/icon.blade.php`
- `packages/socials/resources/views/blocks/socials.blade.php`
- `packages/socials/resources/views/filament/pages/socials.blade.php`
- `packages/socials/resources/views/filament/partials/follow-preview.blade.php`
- `packages/socials/resources/views/filament/partials/share-preview.blade.php`

### Cache tags

- `socials`


## Data Model

- Required tables: `social_profiles`, `social_site_preferences`.
- Models: `SocialProfile`, `SocialSitePreferences`.
- Core record references in migrations: `sites via site_id`.
- Migration files: `2026_07_16_000001_create_social_profiles_table.php`, `2026_07_16_000002_create_social_site_preferences_table.php`, `2026_08_31_000001_add_share_networks_customised_to_social_site_preferences.php`, `2026_09_07_000002_make_share_network_keys_nullable.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare cascade-on-delete relationships; no timed pruning or retention schedule is declared in `capell.json`.

<!-- prettier-ignore-end -->
