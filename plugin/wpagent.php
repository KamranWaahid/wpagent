<?php
/**
 * Plugin Name:       WPAgent
 * Plugin URI:        https://github.com/KamranWaahid/wpagent
 * Description:       Secure MCP bridge so AI assistants can manage this WordPress site through an allowlisted, capability-checked REST API.
 * Version:           0.12.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Kamran Wahid
 * Author URI:        https://github.com/KamranWaahid
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpagent
 * Domain Path:       /languages
 *
 * @package WPAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAGENT_VERSION', '0.12.0' );
define( 'WPAGENT_SLUG', 'wpagent' );
define( 'WPAGENT_FILE', __FILE__ );
define( 'WPAGENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAGENT_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAGENT_REST_NAMESPACE', 'wpagent/v1' );
define( 'WPAGENT_DB_VERSION', '1' );

require_once WPAGENT_DIR . 'includes/class-wpagent-autoloader.php';
WPAgent_Autoloader::register();

/**
 * Boot the plugin.
 */
function wpagent_boot(): void {
	$plugin = WPAgent::instance();
	$plugin->run();
}
add_action( 'plugins_loaded', 'wpagent_boot' );

register_activation_hook( __FILE__, array( 'WPAgent_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPAgent_Deactivator', 'deactivate' ) );
