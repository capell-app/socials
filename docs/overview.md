# Socials

Socials is Capell's free Foundation extension for managing trusted social profiles once per site and placing accessible follow or share links through an explicit widget.

Install it with `php artisan capell:socials-install`. The command runs the package migrations and imports legacy `sites.meta.social_links` and `sites.meta.twitter` only when a site has no package-owned profiles. It leaves the old metadata intact for rollback compatibility.

## Editor workflow

Use the Socials admin page to select an allowed site, order enabled registered profiles and custom follow links, and set follow/share defaults. Its preview tab renders the current unsaved form state through the same typed render-data actions used by the public widget. Share preview uses the selected site's canonical homepage; it intentionally remains unavailable if that homepage or its title is not available.

Registered networks are normalized and validated by the registry. X accepts the legacy `twitter` alias; custom links must have a public label and an HTTP(S) URL. Registered networks may appear once per site, while any number of custom follow links is allowed.

## Public rendering

Place the `socials` Block Library widget deliberately in a layout or page. It supports `follow` and `share` modes, preference overrides, selected site profiles, and page-only custom follow links. The public Blade receives only typed render data and renders ordinary accessible anchors:

- Registered follow links use `rel="me noopener noreferrer"`.
- Custom follow links use `rel="noopener noreferrer"`.
- Share links use `rel="nofollow noopener noreferrer"`.

No SDKs, trackers, remote assets, popup JavaScript, or automatic theme/footer insertion are included.

## Integration contract

Socials depends on Core, Admin, Frontend, and Block Library. It does not depend on SEO Suite or Layout Builder. SEO Suite may optionally consume the public `SocialProfilesResolver`; once Socials is installed, an empty Socials configuration is authoritative and SEO Suite does not fall back to stale legacy metadata.

The package cache uses a per-site epoch. Saving or importing profiles/preferences invalidates only that site's frontend surrogate key after commit.
