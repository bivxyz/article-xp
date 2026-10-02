<?php
/** Runs only in the disposable WordPress test site. */
$plugin = SEOblox_Plugin::instance();
if ( get_option( 'seoblox_settings' ) !== SEOblox_Plugin::defaults() || '2.0.0' !== get_option( 'seoblox_migration_version' ) ) {
	throw new Exception( 'Fresh activation defaults/migration flag failed' );
}
if ( false !== get_option( 'aig_settings' ) ) {
	throw new Exception( 'Fresh activation created legacy settings' );
}
$headers = get_file_data( SEOBLOX_PLUGIN_FILE, array( 'name' => 'Plugin Name', 'version' => 'Version', 'author' => 'Author', 'domain' => 'Text Domain' ) );
if ( $headers !== array( 'name' => 'SEOblox', 'version' => '2.0.0', 'author' => 'Nic Bivens', 'domain' => 'seoblox' ) ) {
	throw new Exception( 'Plugin headers failed' );
}
$id = wp_insert_post( array( 'post_title' => 'Fresh article', 'post_status' => 'publish', 'post_content' => 'Fresh content.' ) );
update_post_meta( $id, '_seoblox_tldr', '<p>Fresh summary</p>' );
$GLOBALS['post'] = get_post( $id );
if ( false === strpos( do_shortcode( '[seoblox]' ), 'Fresh summary' ) || ! metadata_exists( 'post', $id, '_seoblox_word_count' ) ) {
	throw new Exception( 'Fresh article rendering/cache failed' );
}
if ( metadata_exists( 'post', $id, '_aig_word_count' ) ) {
	throw new Exception( 'Fresh install wrote legacy meta' );
}
echo "PASS: fresh activation, headers, summary rendering and new-only cache writes\n";
