=== Article XP ===
Contributors: bivxyz
Tags: geo, seo, reading time, last updated, tldr
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds visible, crawlable article freshness, reading-time, and editor-approved TL;DR elements.

== Description ==

Article XP renders useful article context in the initial server-generated HTML:

* A semantic published or last-updated date
* An estimated reading time
* An optional editor-approved TL;DR paragraph or list

Posts are enabled by default. Pages and other public post types can be enabled under Settings > Article XP.

The plugin does not promise search rankings or citations. It makes useful reader-facing information explicit and machine-readable.

== Installation ==

1. Upload the `article-insights-for-geo` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open Settings > Article XP to configure content types, labels, placement, and appearance.
4. Edit a post and open the Article XP document panel to add a TL;DR or set per-post overrides.

== Automatic and manual placement ==

By default, Article Details and an available TL;DR are inserted before the main singular post content. Set a post to Manual placement to use the Article Details and TL;DR blocks instead.

The plugin suppresses automatic output for a component when its corresponding block is present.

For page builders, the plugin includes automatic fallback placement for Oxygen, Elementor, Divi, Beaver Builder, Bricks, Breakdance, and common theme content containers when a template bypasses WordPress's content filter. A generic article fallback is used when no known content container exists. A template may also place `[article_xp]`, `[article_xp_details]`, or `[article_xp_tldr]` explicitly through a Shortcode element.

== Structured data ==

Auto mode only adjusts `dateModified` in an Article node already produced by Yoast SEO or Rank Math. It never emits a second Article graph. Turn compatibility mode off to use semantic HTML only.

== Developer filters ==

* `aig_words_per_minute`
* `aig_reading_minutes`
* `aig_date_label`
* `aig_reading_label`
* `aig_sanitized_tldr`
* `aig_article_details_html`
* `aig_tldr_html`

== Theme migration ==

If the active theme already inserts an article details bar with `the_content`, disable that theme hook after activating this plugin to avoid duplicate UI.

== Changelog ==

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
