<?php
/**
 * Plugin Name:       WP WhatsApp Evolution API
 * Plugin URI:        https://github.com/davidcamejo/wp-whatsapp-evolution-api
 * Description:       Integrates Dokan vendors' WhatsApp with Evolution API via n8n for simplified management.
 * Version:           1.0.0-beta
 * Author:            David Camejo
 * Author URI:        https://davidcamejo.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wwea
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'WWEA_VERSION', '1.0.0-beta' );
define( 'WWEA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WWEA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WWEA_DOMAIN', 'wwea' ); // Text Domain for i18n

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once WWEA_PLUGIN_DIR . 'includes/class-wwea-core.php';

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0-beta
 */
function run_wwea_plugin() {
    $plugin = new WWEA_Core();
    $plugin->run();
}
run_wwea_plugin();

// Add activation/deactivation hooks (optional, but good practice)
register_activation_hook( __FILE__, 'wwea_activate_plugin' );
register_deactivation_hook( __FILE__, 'wwea_deactivate_plugin' );

/**
 * What runs on plugin activation.
 */
function wwea_activate_plugin() {
    // Add any activation logic here, e.g., default options, database tables (if any)
    // For now, no specific activation logic is needed beyond ensuring classes are loaded.
}

/**
 * What runs on plugin deactivation.
 */
function wwea_deactivate_plugin() {
    // Add any deactivation logic here, e.g., cleanup options, transient data
}
