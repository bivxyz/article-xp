<?php
/** Seed and render with the unmodified, pinned Article XP 1.0.5 plugin. */
$settings = array_merge( AIG_Plugin::defaults(), array(
	'words_per_minute' => 180,
	'published_label'  => 'First published',
	'modified_label'   => 'Reviewed',
	'read_label'       => '%s minutes',
	'background'       => '#E0E8FF',
	'border_radius'    => 17,
	'spacing'          => 'compact',
) );
update_option( 'aig_settings', $settings );
$id = wp_insert_post( array(
	'post_title' => 'Legacy article', 'post_status' => 'publish',
	'post_content' => str_repeat( 'A useful article. ', 150 ),
	'post_date' => '2025-01-01 12:00:00', 'post_date_gmt' => '2025-01-01 12:00:00',
) );
$meta = array( '_aig_tldr' => '<p>Approved <strong>legacy</strong> summary &amp; context.</p>', '_aig_tldr_format' => 'paragraph', '_aig_show_details' => 'show', '_aig_show_tldr' => 'show', '_aig_placement' => 'auto' );
foreach ( $meta as $key => $value ) update_post_meta( $id, $key, $value );
$GLOBALS['post'] = get_post( $id );
$plugin = AIG_Plugin::instance();
update_option( 'seoblox_test_baseline', array(
	'id' => $id, 'settings' => $settings,
	'details' => $plugin->render_details( $id ), 'tldr' => $plugin->render_tldr( $id ),
	'combined' => do_shortcode( '[article_xp]' ), 'minutes' => $plugin->get_reading_minutes( $id ),
	'meta' => get_post_meta( $id ),
) );
echo "PASS: Article XP 1.0.5 baseline captured with settings, TL;DR, overrides and caches\n";
