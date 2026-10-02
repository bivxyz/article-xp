=== SEOblox ===
Contributors: bivxyz
Tags: geo, seo, reading time, last updated, tldr
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds visible, crawlable article freshness, reading-time, and editor-approved TL;DR elements.

== Description ==

SEOblox is the content-layer GEO block library for WordPress. This release preserves the two existing blocks: Article Details and TL;DR. It renders useful article context in the initial server-generated HTML:

* A semantic published or last-updated date
* An estimated reading time
* An optional editor-approved TL;DR paragraph or list

Posts are enabled by default. Pages and other public post types can be enabled under Settings > SEOblox.

The plugin does not promise search rankings or citations. It makes useful reader-facing information explicit and machine-readable.

== Installation ==

1. Upload the `seoblox` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open Settings > SEOblox to configure content types, labels, placement, and appearance.
4. Edit a post and open the SEOblox document panel to add a TL;DR or set per-post overrides.

== Upgrading from Article XP 1.0.5 ==

1. Back up your site and deactivate Article XP.
2. Install the new `seoblox` folder and activate SEOblox (`seoblox.php`).
3. Check Settings > SEOblox and an existing article before removing the old plugin files.

The folder and main-file rename changes WordPress's plugin identity. SEOblox does not silently activate/deactivate plugins or rewrite the active plugin list. Do not run both plugins together.

Settings are copied from `aig_settings` once, without overwriting existing `seoblox_settings`. The `seoblox_migration_version` flag prevents repeated copies. Old options remain stored. Post meta reads new keys first, falls back to old keys, and writes new keys on saves; old meta remains stored.

Legacy `article-insights/details` and `article-insights/tldr` blocks remain registered and editable as deprecated aliases. Automatic suppression recognizes old and new blocks. `[article_xp]`, `[article_xp_details]`, and `[article_xp_tldr]` remain aliases for the new shortcodes. Existing saved content does not need rewriting.

Old `aig-` CSS classes remain on the same elements for the 2.0 release. New classes use `seoblox-`. Legacy `--aig-*` custom-property overrides continue to work and take precedence over the matching new variables while compatibility is retained. HTML IDs, data attributes, asset handles, and the editor global use the new names; update integrations that address those directly.

An approved WordPress.org listing cannot change its slug. Publishing under `seoblox` would require a new listing, without automatic transfer of listing history or users. This release does not submit a listing.

== Automatic and manual placement ==

By default, Article Details and an available TL;DR are inserted before the main singular post content. Set a post to Manual placement to use the Article Details and TL;DR blocks instead.

The plugin suppresses automatic output for a component when its corresponding block is present.

For page builders, the plugin includes automatic fallback placement for Oxygen, Elementor, Divi, Beaver Builder, Bricks, Breakdance, and common theme content containers when a template bypasses WordPress's content filter. A generic article fallback is used when no known content container exists. A template may also place `[seoblox]`, `[seoblox_details]`, or `[seoblox_tldr]` explicitly through a Shortcode element.

== Structured data ==

Auto mode only adjusts `dateModified` in an Article node already produced by Yoast SEO or Rank Math. It never emits a second Article graph. Turn compatibility mode off to use semantic HTML only.

SEOblox never outputs Product, Offer, AggregateRating-on-product, or Merchant schema and never accesses commerce post data. WooGEO owns commerce and remains separate. Product, variation, order, and refund post types cannot be enabled.

== Developer filters ==

* `seoblox_words_per_minute`
* `seoblox_reading_minutes`
* `seoblox_date_label`
* `seoblox_reading_label`
* `seoblox_sanitized_tldr`
* `seoblox_article_details_html`
* `seoblox_tldr_html`

Each former `aig_*` filter fires via `apply_filters_deprecated()` (deprecated in 2.0.0) with its original arguments, before the corresponding `seoblox_*` filter. The new filter receives the legacy result and has final control. Migrate callbacks to the new hook names.

`seoblox_is_woogeo_active()` detects the separate plugin's `WOOGEO_VERSION` constant and exposes the result through `seoblox_woogeo_active`. Detection is available for future schema blocks and does not change current output.

== Theme migration ==

If the active theme already inserts an article details bar with `the_content`, disable that theme hook after activating this plugin to avoid duplicate UI.

== Changelog ==

= 2.0.0 =
* 2.0.0: renamed from Article XP to SEOblox.
* Migrates settings once, reads legacy post meta, and saves new SEOblox keys.
* Retains legacy blocks and shortcodes, deprecated filters, and old CSS classes.
* Enforces the content/commerce boundary; WooGEO remains a separate plugin.

= 1.0.5 =
* Corrected the plugin author attribution to Nic Bivens.

= 1.0.4 =
* Expanded automatic placement support for Elementor, Divi, Beaver Builder, Bricks, and Breakdance.
* Added a generic semantic article fallback for custom builder templates.
* Added automated placement regression tests for supported builder containers.

= 1.0.3 =
* Added automatic fallback placement for Oxygen and other builders that bypass the_content.
* Added combined and component-specific shortcodes for visual-builder templates.
* Relaxed content-filter guards while retaining singular-post and duplicate protections.

= 1.0.2 =
* Renamed the plugin to Article XP while preserving existing settings and block compatibility.

= 1.0.1 =
* Replaced native color pickers with six-digit HEX fields.
* Added a live settings preview for colors, spacing, radius, and labels.

= 1.0.0 =
* Initial release.
