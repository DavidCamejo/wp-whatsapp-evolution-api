<?php
/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 *
 * @package WP_WhatsApp_Evolution_API
 * @subpackage WP_WhatsApp_Evolution_API/includes
 * @since 1.0.0-beta
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WWEA_Core {

    /**
     * The loader that holds and registers all of the hooks.
     *
     * @since    1.0.0-beta
     * @access   protected
     * @var      WWEA_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * Initializes the plugin and sets up the dependencies.
     *
     * @since    1.0.0-beta
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_dokan_hooks();
        $this->define_ajax_hooks();
        $this->define_rest_api_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0-beta
     * @access   private
     */
    private function load_dependencies() {
        // The class responsible for orchestrating the hooks of the plugin.
        require_once WWEA_PLUGIN_DIR . 'includes/class-wwea-loader.php';
        $this->loader = new WWEA_Loader();

        // Classes responsible for specific functionalities.
        require_once WWEA_PLUGIN_DIR . 'includes/class-wwea-settings.php';
        require_once WWEA_PLUGIN_DIR . 'includes/class-wwea-n8n-client.php';
        require_once WWEA_PLUGIN_DIR . 'includes/class-wwea-dokan-integration.php';
        require_once WWEA_PLUGIN_DIR . 'includes/class-wwea-ajax-handler.php';
        require_once WWEA_PLUGIN_DIR . 'includes/class-wwea-rest-api.php';
    }

    /**
     * Define the hooks for the admin area.
     *
     * @since    1.0.0-beta
     * @access   private
     */
    private function define_admin_hooks() {
        $settings = new WWEA_Settings();
        $this->loader->add_action( 'admin_menu', $settings, 'add_admin_menu' );
        $this->loader->add_action( 'admin_init', $settings, 'settings_init' );
    }

    /**
     * Define the hooks for Dokan integration.
     *
     * @since    1.0.0-beta
     * @access   private
     */
    private function define_dokan_hooks() {
        // Instantiate n8n client and Dokan integration.
        // Settings for n8n client will be fetched internally.
        $n8n_client = new WWEA_N8n_Client(); // Client instance for Dokan integration to use.
        $dokan_integration = new WWEA_Dokan_Integration( $n8n_client );

        $this->loader->add_action( 'dokan_dashboard_nav_items', $dokan_integration, 'add_whatsapp_tab' );
        $this->loader->add_filter( 'dokan_load_views', $dokan_integration, 'load_whatsapp_tab_content', 10, 2 );
        $this->loader->add_action( 'wp_enqueue_scripts', $dokan_integration, 'enqueue_vendor_scripts' ); // Enqueue for frontend/Dokan dashboard
        // Add AJAX hook for saving vendor WhatsApp number (future phase or explicit request)
        $this->loader->add_action( 'wp_ajax_wwea_save_vendor_whatsapp_number', $dokan_integration, 'ajax_save_vendor_whatsapp_number' );
    }

    /**
     * Define the hooks for AJAX interactions.
     *
     * @since    1.0.0-beta
     * @access   private
     */
    private function define_ajax_hooks() {
        // AJAX Handler will use an instance of WWEA_N8n_Client
        $n8n_client = new WWEA_N8n_Client();
        $ajax_handler = new WWEA_AJAX_Handler( $n8n_client );

        // This hook is for getting QR and Status data from the vendor panel
        $this->loader->add_action( 'wp_ajax_wwea_dokan_get_whatsapp_data', $ajax_handler, 'handle_get_whatsapp_data' );
        // No-priv hook if any public-facing AJAX is needed (unlikely for this specific feature)
        // $this->loader->add_action( 'wp_ajax_nopriv_wwea_dokan_get_whatsapp_data', $ajax_handler, 'handle_get_whatsapp_data' );
    }

    /**
     * Define the hooks for REST API interactions (incoming webhooks from n8n).
     *
     * @since    1.0.0-beta
     * @access   private
     */
    private function define_rest_api_hooks() {
        $rest_api = new WWEA_REST_API();
        $this->loader->add_action( 'rest_api_init', $rest_api, 'register_routes' );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0-beta
     */
    public function run() {
        $this->loader->run();
    }
}

// Basic loader to run the actions/filters
require_once WWEA_PLUGIN_DIR . 'includes/class-wwea-loader.php';
