<?php
/**
 * Plugin Name:       Article Insights for GEO
 * Description:       Adds crawlable published/updated dates, reading time, and editor-approved TL;DR summaries to articles.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Avery
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       article-insights-for-geo
 *
 * @package ArticleInsightsForGEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIG_VERSION', '1.0.0' );
define( 'AIG_PLUGIN_FILE', __FILE__ );
define( 'AIG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AIG_PLUGIN_DIR . 'includes/class-aig-plugin.php';

register_activation_hook( __FILE__, array( 'AIG_Plugin', 'activate' ) );

AIG_Plugin::instance();
