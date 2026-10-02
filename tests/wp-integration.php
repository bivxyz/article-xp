<?php
/** Integration assertions against real WordPress APIs and a 1.0.5 baseline. */
$checks = 0;
function seoblox_check( $condition, $message ) {
	global $checks;
	++$checks;
	if ( ! $condition ) throw new Exception( $message );
}
function seoblox_reset_output() {
	$property = new ReflectionProperty( 'SEOblox_Plugin', 'page_output_rendered' );
	$property->setAccessible( true );
	$property->setValue( SEOblox_Plugin::instance(), false );
}
function seoblox_normalize( $html ) {
	$html = preg_replace_callback( '/class="([^"]*)"/', static function ( $match ) {
		$classes = preg_split( '/\s+/', $match[1] );
		$classes = array_filter( $classes, static function ( $class ) { return 0 !== strpos( $class, 'seoblox-' ); } );
		return 'class="' . implode( ' ', $classes ) . '"';
	}, $html );
	return str_replace( 'seoblox-tldr-title-', 'aig-tldr-title-', $html );
}
$plugin   = SEOblox_Plugin::instance();
$baseline = get_option( 'seoblox_test_baseline' );
$id       = $baseline['id'];
wp_set_current_user( 1 );
$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $id ) );
$GLOBALS['post'] = get_post( $id );

seoblox_check( $baseline['settings'] === get_option( 'seoblox_settings' ), 'Upgrade initialization lost settings' );
seoblox_check( $baseline['settings'] === get_option( 'aig_settings' ), 'Legacy settings were altered' );
seoblox_check( '2.0.0' === get_option( 'seoblox_migration_version' ), 'Upgrade flag missing' );
$changed = array_merge( $baseline['settings'], array( 'words_per_minute' => 200 ) );
update_option( 'seoblox_settings', $changed );
SEOblox_Plugin::activate();
SEOblox_Plugin::maybe_upgrade();
seoblox_check( $changed === get_option( 'seoblox_settings' ), 'Migration repeated and overwrote edits' );
delete_option( 'seoblox_migration_version' );
SEOblox_Plugin::maybe_upgrade();
seoblox_check( $changed === get_option( 'seoblox_settings' ), 'Existing new settings lost on first migration' );
update_option( 'seoblox_settings', $baseline['settings'] );

foreach ( SEOblox_Plugin::legacy_meta_keys() as $key => $old ) {
	seoblox_check( ! metadata_exists( 'post', $id, $key ), 'Upgrade eagerly wrote post meta: ' . $key );
	seoblox_check( get_post_meta( $id, $old, true ) === $plugin->post_meta( $id, $key ), 'Legacy fallback failed: ' . $old );
}
seoblox_check( $baseline['details'] === seoblox_normalize( $plugin->render_details( $id ) ), 'Details changed from 1.0.5' );
seoblox_check( $baseline['tldr'] === seoblox_normalize( $plugin->render_tldr( $id ) ), 'Summary changed from 1.0.5' );
seoblox_check( $baseline['minutes'] === $plugin->get_reading_minutes( $id ), 'Reading time changed from 1.0.5' );

$registry = WP_Block_Type_Registry::get_instance();
foreach ( array( 'seoblox/article-details' => 'article-insights/details', 'seoblox/tldr' => 'article-insights/tldr' ) as $current => $legacy ) {
	seoblox_check( $registry->is_registered( $current ) && $registry->is_registered( $legacy ), 'Missing block registration' );
	seoblox_check( false === $registry->get_registered( $legacy )->supports['inserter'], 'Legacy alias visible in inserter' );
	$new = do_blocks( '<!-- wp:' . $current . ' /-->' );
	$old = do_blocks( '<!-- wp:' . $legacy . ' /-->' );
	seoblox_check( '' !== $new && $new === $old, 'Legacy block differs from new block' );
}
foreach ( array( 'seoblox' => 'article_xp', 'seoblox_details' => 'article_xp_details', 'seoblox_tldr' => 'article_xp_tldr' ) as $current => $legacy ) {
	seoblox_reset_output();
	$new = do_shortcode( '[' . $current . ']' );
	$old = do_shortcode( '[' . $legacy . ']' );
	seoblox_check( '' !== $new && $new === $old, 'Legacy shortcode differs: ' . $legacy );
	if ( 'seoblox' === $current ) seoblox_check( $baseline['combined'] === seoblox_normalize( $new ), 'Combined output differs from 1.0.5' );
}

// Actual editor REST loading and saving, including old-only data and explicit clearing.
$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $id );
$request->set_param( 'context', 'edit' );
$response = rest_do_request( $request );
seoblox_check( 200 === $response->get_status(), 'REST editor load failed' );
$editor_meta = $response->get_data()['meta'];
seoblox_check( $editor_meta['_seoblox_tldr'] === get_post_meta( $id, '_aig_tldr', true ), 'Editor failed to preload legacy summary' );
seoblox_check( 'auto' === $editor_meta['_seoblox_placement'] && 'show' === $editor_meta['_seoblox_show_details'], 'Editor failed to preload overrides' );
$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $id );
$request->set_param( 'meta', array( '_seoblox_tldr' => '', '_seoblox_placement' => 'manual', '_seoblox_show_details' => 'hide' ) );
$response = rest_do_request( $request );
seoblox_check( 200 === $response->get_status(), 'REST editor save failed: ' . wp_json_encode( $response->get_data() ) );
seoblox_check( metadata_exists( 'post', $id, '_seoblox_tldr' ) && '' === $plugin->render_tldr( $id ), 'Cleared summary resurrected legacy content' );
foreach ( SEOblox_Plugin::legacy_meta_keys() as $key => $old ) {
	seoblox_check( metadata_exists( 'post', $id, $key ), 'Save failed to populate new key: ' . $key );
	seoblox_check( $baseline['meta'][$old] === get_post_meta( $id, $old ), 'Save changed legacy key: ' . $old );
}
$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $id );
$request->set_param( 'context', 'edit' );
$response = rest_do_request( $request );
seoblox_check( '' === $response->get_data()['meta']['_seoblox_tldr'] && 'manual' === $response->get_data()['meta']['_seoblox_placement'], 'Saved overrides did not reload' );
foreach ( SEOblox_Plugin::legacy_meta_keys() as $key => $old ) {
	update_post_meta( $id, $key, wp_slash( get_post_meta( $id, $old, true ) ) );
}
update_post_meta( $id, '_seoblox_tldr', '<li>First</li><li>Second</li>' );
update_post_meta( $id, '_seoblox_tldr_format', 'list' );
seoblox_check( false !== strpos( $plugin->render_tldr( $id ), '<ul><li>First</li><li>Second</li></ul>' ), 'List summary rendering regressed' );
update_post_meta( $id, '_seoblox_tldr', 'Plain paragraph' );
update_post_meta( $id, '_seoblox_tldr_format', 'paragraph' );
seoblox_check( false !== strpos( $plugin->render_tldr( $id ), '<p>Plain paragraph</p>' ), 'Paragraph summary rendering regressed' );
seoblox_check( false === strpos( $plugin->sanitize_tldr( '<script>alert(1)</script><p>Allowed <strong>bold</strong></p>' ), '<script' ), 'Summary sanitation regressed' );
update_post_meta( $id, '_seoblox_tldr', get_post_meta( $id, '_aig_tldr', true ) );

// Component suppression and builder fallback share the same guards for both generations.
ob_start(); do_action( 'wp_head' ); ob_end_clean();
foreach ( array( 'seoblox/article-details', 'article-insights/details' ) as $name ) {
	seoblox_reset_output();
	$output = $plugin->prepend_insights( '<!-- wp:' . $name . ' /-->' );
	seoblox_check( false === strpos( $output, 'class="seoblox-article-details ' ) && false !== strpos( $output, 'class="seoblox-tldr ' ), 'Details block suppression failed' );
}
foreach ( array( 'seoblox/tldr', 'article-insights/tldr' ) as $name ) {
	seoblox_reset_output();
	$output = $plugin->prepend_insights( '<!-- wp:' . $name . ' /-->' );
	seoblox_check( false === strpos( $output, 'class="seoblox-tldr ' ) && false !== strpos( $output, 'class="seoblox-article-details ' ), 'Summary block suppression failed' );
}
seoblox_reset_output();
$output = $plugin->prepend_insights( 'Body' );
seoblox_check( false !== strpos( $output, 'seoblox-article-details aig-article-details' ), 'Automatic output missing dual classes' );
seoblox_check( 'Again' === $plugin->prepend_insights( 'Again' ), 'Automatic output duplicated' );
ob_start(); $plugin->render_builder_fallback(); $fallback = ob_get_clean();
seoblox_check( '' === $fallback, 'Builder fallback duplicated automatic output' );
seoblox_reset_output();
ob_start(); $plugin->render_builder_fallback(); $fallback = ob_get_clean();
seoblox_check( false !== strpos( $fallback, 'class="seoblox-builder-fallback aig-builder-fallback" data-seoblox-builder-fallback' ), 'Builder fallback missing' );
update_post_meta( $id, '_seoblox_placement', 'manual' );
seoblox_reset_output();
seoblox_check( 'Body' === $plugin->prepend_insights( 'Body' ), 'Manual placement still prepends' );
ob_start(); $plugin->render_builder_fallback(); $fallback = ob_get_clean();
seoblox_check( '' === $fallback, 'Manual placement still falls back' );
update_post_meta( $id, '_seoblox_placement', 'auto' );
update_post_meta( $id, '_seoblox_show_details', 'hide' );
seoblox_check( '' === do_shortcode( '[seoblox_details]' ), 'Per-post hide ignored' );
update_post_meta( $id, '_seoblox_show_details', 'show' );
$hidden = array_merge( $baseline['settings'], array( 'show_details' => 0, 'show_tldr' => 0, 'auto_details' => 0, 'auto_tldr' => 0 ) );
update_option( 'seoblox_settings', $hidden );
seoblox_check( '' !== do_shortcode( '[seoblox_details]' ), 'Per-post show failed to override global hide' );
seoblox_reset_output();
seoblox_check( 'Body' === $plugin->prepend_insights( 'Body' ), 'Disabled automatic settings ignored' );
update_option( 'seoblox_settings', $baseline['settings'] );

// All seven legacy hooks actually fire at their original behavior entrypoints.
add_filter( 'deprecated_hook_trigger_error', '__return_false' );
$cases = array(
	'sanitized_tldr' => array( 'sanitize_tldr', array( '<p>Safe</p>' ), array() ),
	'words_per_minute' => array( 'get_reading_minutes', array( $id ), array( $id ) ),
	'reading_minutes' => array( 'get_reading_minutes', array( $id ), array( (int) get_post_meta( $id, '_seoblox_word_count', true ), $id ) ),
	'date_label' => array( 'render_details', array( $id ), array( (int) get_post_modified_time( 'U', true, $id ) > (int) get_post_time( 'U', true, $id ), $id ) ),
	'reading_label' => array( 'render_details', array( $id ), array( $plugin->get_reading_minutes( $id ), $id ) ),
	'article_details_html' => array( 'render_details', array( $id ), array( $id ) ),
	'tldr_html' => array( 'render_tldr', array( $id ), array( $id, $plugin->sanitize_tldr( get_post_meta( $id, '_seoblox_tldr', true ) ) ) ),
);
foreach ( $cases as $suffix => $case ) {
	$calls = array(); $old_result = null; $notice = null;
	$old = static function ( $value, ...$args ) use ( &$calls, &$old_result, $case, $suffix ) {
		seoblox_check( $args === $case[2], 'Old filter arguments changed' );
		$calls[] = 'old';
		$old_result = 'words_per_minute' === $suffix ? 50 : ( is_numeric( $value ) ? $value + 1 : $value . ' OLD' );
		return $old_result;
	};
	$new = static function ( $value, ...$args ) use ( &$calls, &$old_result, $case, $suffix ) {
		seoblox_check( $value === $old_result && $args === $case[2], 'New filter lost legacy result/arguments' );
		$calls[] = 'new';
		return 'words_per_minute' === $suffix ? 100 : ( is_numeric( $value ) ? $value + 2 : $value . ' NEW' );
	};
	$deprecated = static function ( $hook, $replacement, $version ) use ( &$notice, $suffix ) {
		if ( 'aig_' . $suffix === $hook ) $notice = array( $replacement, $version );
	};
	add_filter( 'aig_' . $suffix, $old, 10, 10 );
	add_filter( 'seoblox_' . $suffix, $new, 10, 10 );
	add_action( 'deprecated_hook_run', $deprecated, 10, 3 );
	$output = call_user_func_array( array( $plugin, $case[0] ), $case[1] );
	seoblox_check( array( 'old', 'new' ) === $calls, 'Filter ordering failed: ' . $suffix );
	seoblox_check( array( 'seoblox_' . $suffix, '2.0.0' ) === $notice, 'Deprecation signal failed: ' . $suffix );
	$expected_number = 'words_per_minute' === $suffix ? (int) ceil( (int) get_post_meta( $id, '_seoblox_word_count', true ) / 100 ) : $baseline['minutes'] + 3;
	seoblox_check( is_int( $output ) ? $output === $expected_number : false !== strpos( $output, 'OLD NEW' ), 'Filter return value ignored' );
	remove_filter( 'aig_' . $suffix, $old ); remove_filter( 'seoblox_' . $suffix, $new ); remove_action( 'deprecated_hook_run', $deprecated );
}

// Classic saves copy storage verbatim without rerunning non-idempotent filters.
$migration_id = wp_insert_post( array( 'post_title' => 'Classic migration', 'post_status' => 'publish', 'post_content' => 'One two three.' ) );
$raw = '<p>Legacy "quotes" and \backslash</p>';
update_post_meta( $migration_id, '_aig_tldr', wp_slash( $raw ) );
$sanitize_calls = 0;
$append = static function ( $value ) use ( &$sanitize_calls ) { ++$sanitize_calls; return $value . '<p>Filtered</p>'; };
add_filter( 'aig_sanitized_tldr', $append );
wp_update_post( array( 'ID' => $migration_id, 'post_title' => 'Saved classic migration' ) );
seoblox_check( $raw === get_post_meta( $migration_id, '_seoblox_tldr', true ), 'Meta copy changed legacy markup/escaping' );
seoblox_check( 0 === $sanitize_calls, 'Migration reapplied a sanitization filter' );
seoblox_check( false !== strpos( $plugin->render_tldr( $migration_id ), '<p>Filtered</p>' ) && 1 === $sanitize_calls, 'Normal rendering skipped sanitizer filters' );
remove_filter( 'aig_sanitized_tldr', $append );

$schema = array( '@type' => 'Article', 'headline' => 'Existing graph', 'dateModified' => 'old', 'author' => array( '@type' => 'Person', 'name' => 'Editor' ) );
$expected = $schema; $expected['dateModified'] = get_post_modified_time( DATE_W3C, false, $id );
foreach ( array( 'BlogPosting', 'NewsArticle', 'TechArticle', 'LiveBlogPosting', 'MedicalScholarlyArticle' ) as $article_type ) {
	$subtype = $schema; $subtype['@type'] = $article_type;
	$subtype_expected = $subtype; $subtype_expected['dateModified'] = $expected['dateModified'];
	seoblox_check( $subtype_expected === $plugin->filter_article_schema( $subtype ), 'Article subtype compatibility lost' );
}
foreach ( array( 'wpseo_schema_article', 'rank_math/snippet/rich_snippet_article_entity' ) as $hook ) {
	seoblox_check( $expected === apply_filters( $hook, $schema ), 'Schema changed more than dateModified' );
}
foreach ( array( 'Product', 'ProductGroup', 'Offer', 'AggregateOffer', 'AggregateRating', 'Merchant', 'MerchantReturnPolicy', 'OnlineStore', 'Person' ) as $type ) {
	$commerce = array( '@type' => $type, 'dateModified' => 'untouched' );
	seoblox_check( $commerce === $plugin->filter_article_schema( $commerce ), 'Non-Article schema was touched: ' . $type );
}
$off = array_merge( $baseline['settings'], array( 'schema_mode' => 'off' ) );
update_option( 'seoblox_settings', $off );
seoblox_check( $schema === $plugin->filter_article_schema( $schema ), 'Schema off setting ignored' );
update_option( 'seoblox_settings', $baseline['settings'] );
seoblox_check( 'invalid' === $plugin->filter_article_schema( 'invalid' ), 'Invalid schema changed' );

// Commerce guard applies even if an old settings option had selected products.
$types = array( 'product', 'product_variation', 'shop_order', 'shop_order_refund', 'shop_order_placehold' );
foreach ( $types as $type ) register_post_type( $type, array( 'public' => true, 'show_in_rest' => true ) );
$with_commerce = array_merge( $baseline['settings'], array( 'post_types' => array_merge( array( 'post' ), $types ) ) );
update_option( 'seoblox_settings', $with_commerce );
seoblox_check( array( 'post' ) === $plugin->enabled_post_types(), 'Commerce post types remained eligible' );
foreach ( $types as $type ) {
	$product_id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => 'Commerce record', 'post_content' => 'Commerce data' ) );
	seoblox_check( array() === get_post_meta( $product_id ), 'Commerce save received SEOblox meta' );
	update_post_meta( $product_id, '_aig_tldr', 'Must not render or migrate' );
	wp_update_post( array( 'ID' => $product_id, 'post_title' => 'Updated commerce record' ) );
	seoblox_check( ! metadata_exists( 'post', $product_id, '_seoblox_tldr' ), 'Commerce meta migrated' );
	seoblox_check( '' === $plugin->render_details( $product_id ) && '' === $plugin->render_tldr( $product_id ) && 0 === $plugin->get_reading_minutes( $product_id ), 'Commerce rendered via direct API' );
	seoblox_check( false === $plugin->can_edit_meta( true, '_seoblox_tldr', $product_id ), 'Commerce meta writable' );
	$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $product_id, 'post_type' => $type ) );
	$GLOBALS['post'] = get_post( $product_id );
	seoblox_reset_output();
	seoblox_check( '' === do_shortcode( '[seoblox]' ) && '' === do_shortcode( '[article_xp]' ), 'Commerce rendered via shortcode' );
	seoblox_check( $schema === $plugin->filter_article_schema( $schema ), 'Product Article schema touched' );
	seoblox_check( 'Body' === $plugin->prepend_insights( 'Body' ), 'Commerce received auto output' );
	ob_start(); $plugin->render_builder_fallback(); $fallback = ob_get_clean();
	seoblox_check( '' === $fallback, 'Commerce received builder fallback' );
}
update_option( 'seoblox_settings', $baseline['settings'] );
$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $id ) );
$GLOBALS['post'] = get_post( $id );
$revision = wp_insert_post( array( 'post_type' => 'revision', 'post_parent' => $id, 'post_title' => 'Revision', 'post_content' => 'Revision content' ) );
seoblox_check( ! metadata_exists( 'post', $revision, '_seoblox_word_count' ), 'Revision wrote reading cache' );
seoblox_check( 7 === $plugin->count_words( 'One &amp; two 中文 テスト <!-- hidden words -->' ), 'Unicode counting regressed' );

seoblox_check( false === seoblox_is_woogeo_active(), 'False-positive WooGEO detection' );
add_filter( 'seoblox_woogeo_active', '__return_true' );
seoblox_check( true === seoblox_is_woogeo_active(), 'WooGEO detection filter ignored' );
remove_filter( 'seoblox_woogeo_active', '__return_true' );
define( 'WOOGEO_VERSION', 'test' );
seoblox_check( true === seoblox_is_woogeo_active(), 'WooGEO constant not detected' );

require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once SEOBLOX_PLUGIN_DIR . 'includes/class-seoblox-settings.php';
$settings = new SEOblox_Settings( $plugin );
$sanitized = $settings->sanitize( $with_commerce );
seoblox_check( array( 'post' ) === $sanitized['post_types'], 'Settings accepted commerce types' );
ob_start(); $settings->render_post_types(); $choices = ob_get_clean();
seoblox_check( false === strpos( $choices, 'value="product"' ), 'Settings displayed commerce choices' );
$settings->register_settings();
ob_start(); $settings->render_page(); $page = ob_get_clean();
seoblox_check( false !== strpos( $page, '<h1>SEOblox</h1>' ) && false !== strpos( $page, 'seoblox_settings_group' ), 'Settings page names failed' );
seoblox_check( false !== strpos( $page, 'seoblox-settings-preview aig-settings-preview' ), 'Preview lost legacy CSS class' );
seoblox_check( false !== strpos( implode( '', $plugin->settings_link( array() ) ), 'page=seoblox' ), 'Settings link incorrect' );
seoblox_check( $baseline['settings'] === get_option( 'aig_settings' ), 'Legacy settings changed during tests' );
echo "PASS: {$checks} WordPress migration, meta/REST, aliases, placement, filters, schema and boundary assertions\n";
