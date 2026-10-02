<?php
/**
 * Plugin Name:       SEOblox
 * Description:       Adds crawlable published/updated dates, reading time, and editor-approved TL;DR summaries to articles.
 * Version:           2.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Nic Bivens
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seoblox
 *
 * @package SEOblox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEOBLOX_VERSION', '2.0.0' );
define( 'SEOBLOX_PLUGIN_FILE', __FILE__ );
define( 'SEOBLOX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOBLOX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SEOBLOX_PLUGIN_DIR . 'includes/class-seoblox-plugin.php';

register_activation_hook( __FILE__, array( 'SEOblox_Plugin', 'activate' ) );

/**
 * Whether the separate WooGEO plugin is loaded. No WooGEO code is imported.
 * Available for future schema blocks; intentionally unused by this release.
 *
 * @return bool
 */
function seoblox_is_woogeo_active() {
	return (bool) apply_filters( 'seoblox_woogeo_active', defined( 'WOOGEO_VERSION' ) );
}

SEOblox_Plugin::instance();
