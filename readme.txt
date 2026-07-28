=== Article Insights for GEO ===
Contributors: avery
Tags: geo, seo, reading time, last updated, tldr
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds visible, crawlable article freshness, reading-time, and editor-approved TL;DR elements.

== Description ==

Article Insights for GEO renders useful article context in the initial server-generated HTML:

* A semantic published or last-updated date
* An estimated reading time
* An optional editor-approved TL;DR paragraph or list

Posts are enabled by default. Pages and other public post types can be enabled under Settings > Article Insights.

The plugin does not promise search rankings or citations. It makes useful reader-facing information explicit and machine-readable.

== Installation ==

1. Upload the `article-insights-for-geo` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open Settings > Article Insights to configure content types, labels, placement, and appearance.
4. Edit a post and open the Article Insights document panel to add a TL;DR or set per-post overrides.

== Automatic and manual placement ==

By default, Article Details and an available TL;DR are inserted before the main singular post content. Set a post to Manual placement to use the Article Details and TL;DR blocks instead.

The plugin suppresses automatic output for a component when its corresponding block is present.

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

= 1.0.0 =
* Initial release.
