# Socials

<!-- prettier-ignore-start -->

## What it does for you

Socials keeps each site's approved social profiles and follow/share defaults in one place. Editors can then add an explicit **Socials** block to a page for accessible follow links or page-specific share links.

The package does not add social links to a theme automatically. It also avoids social SDKs, tracking scripts, remote assets, and popup JavaScript on public pages.

## Where to find it

Open **Growth > Socials**. You need the install-generated `View:SocialsPage` permission, and the **Site** selector only lists sites your account is allowed to manage.

## Configure a site

1. Select the site before making changes.
2. Under **Social profiles**, choose **Add social profile**, pick a network (or **Custom link**), and enter the handle or address for that network. Each finished profile collapses to a short summary of its network, destination, and whether it is shown.
3. Reopen a profile to reorder it, hide it from visitors, or give it a custom public label. Custom links always need a label; registered networks only show the label field when you ask for one.
4. Under **Appearance and preview**, set the follow label style and link target. Sharing uses a recommended set of the networks that support sharing; turn on **Choose the share networks myself** to pick exact networks, label style, and link target.
5. The follow and share previews sit beside those controls and update from your unsaved changes.
6. Select **Save Socials**. The action reports unsaved, saving, saved, and error states. Changing **Site** with unsaved changes asks whether to save, discard, or stay.

Registered networks can appear once per site. A custom link needs a public label and an HTTP or HTTPS URL. Saving an invalid or duplicate profile does not partially replace the site's existing configuration.

## Add links to a page

Add the **Socials** block through the page's block or layout editor, then choose **Follow** or **Share** mode.

- **Follow** uses the site's enabled profiles. You can restrict the block to particular site profile IDs and add up to ten links that apply only to that block.
- **Share** uses the current page's canonical URL and title. Choose specific networks in the block or inherit the site's defaults.
- Both modes can inherit the site's label and link-target defaults or override them for that block. You can also set a heading and alignment.

The block renders nothing when it has no valid links. A share block also renders nothing when the current page has no canonical URL or title.

## Good to know

- The follow preview updates from unsaved profiles and defaults. The share preview uses the selected site's canonical homepage and is unavailable until that page has both a canonical URL and title.
- During installation, legacy site social links are imported only when that site has no Socials profiles. Existing legacy metadata is retained for rollback, so review imported profiles before publishing a block.
- Disabled, invalid, or unknown profiles are omitted from public output instead of breaking the page.
- Public follow and share links are ordinary anchors with safe relationship attributes; no visitor data is sent to a social network until the visitor follows a link.

---

For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
