<?php
/**
 * Handles global plugin settings (n8n URL, shared secret).
 *
 * @package WP_WhatsApp_Evolution_API
 * @subpackage WP_WhatsApp_Evolution_API/includes
 * @since 1.0.0-beta
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WWEA_Settings {

    /**
     * Constructor.
     *
     * @since 1.0.0-beta
     */
    public function __construct() {
        // Hooks are defined in WWEA_Core and passed to WWEA_Loader.
    }

    /**
     * Adds the plugin's admin menu page.
     *
     * @since 1.0.0-beta
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'WP WhatsApp Evolution API Settings', WWEA_DOMAIN ), // Page title
            __( 'WhatsApp n8n', WWEA_DOMAIN ), // Menu title
            'manage_options', // Capability
            'wwea-settings', // Menu slug
            array( $this, 'render_settings_page' ) // Callback function
        );
    }

    /**
     * Renders the settings page HTML.
     *
     * @since 1.0.0-beta
     */
    public function render_settings_page() {
        include_once WWEA_PLUGIN_DIR . 'admin/settings-page.php';
    }

    /**
     * Registers settings, sections, and fields.
     *
     * @since 1.0.0-beta
     */
    public function settings_init() {
        // Register the main settings group for the plugin options.
        register_setting( 'wwea_settings_group', 'wwea_n8n_base_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ) );
        register_setting( 'wwea_settings_group', 'wwea_n8n_shared_secret', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        // Add a settings section.
        add_settings_section(
            'wwea_n8n_section', // ID
            __( 'n8n Integration Settings', WWEA_DOMAIN ), // Title
            array( $this, 'n8n_section_callback' ), // Callback
            'wwea-settings' // Page slug
        );

        // Add fields to the section.
        add_settings_field(
            'wwea_n8n_base_url_field', // ID
            __( 'n8n Base Webhook URL', WWEA_DOMAIN ), // Title
            array( $this, 'n8n_base_url_callback' ), // Callback
            'wwea-settings', // Page slug
            'wwea_n8n_section' // Section ID
        );

        add_settings_field(
            'wwea_n8n_shared_secret_field', // ID
            __( 'n8n Shared Secret', WWEA_DOMAIN ), // Title
            array( $this, 'n8n_shared_secret_callback' ), // Callback
            'wwea-settings', // Page slug
            'wwea_n8n_section' // Section ID
        );
    }

    /**
     * Callback for the n8n settings section.
     *
     * @since 1.0.0-beta
     */
    public function n8n_section_callback() {
        echo '<p>' . esc_html__( 'Configure the base URL for your n8n webhooks and the shared secret for secure communication between your WordPress plugin and n8n.', WWEA_DOMAIN ) . '</p>';
    }

    /**
     * Callback for the n8n Base Webhook URL field.
     *
     * @since 1.0.0-beta
     */
    public function n8n_base_url_callback() {
        $url = self::get_n8n_base_url();
        echo '<input type="url" id="wwea_n8n_base_url" name="wwea_n8n_base_url" value="' . esc_url( $url ) . '" class="regular-text" placeholder="https://your-n8n.com/webhook/">';
        echo '<p class="description">' . esc_html__( 'The base URL for your n8n webhooks (e.g., https://your-n8n.com/webhook/).', WWEA_DOMAIN ) . '</p>';
    }

    /**
     * Callback for the n8n Shared Secret field.
     *
     * @since 1.0.0-beta
     */
    public function n8n_shared_secret_callback() {
        $secret = self::get_shared_secret();
        // Suggest a random password for easy generation
        $suggested_secret = wp_generate_password( 32, false );
        echo '<input type="text" id="wwea_n8n_shared_secret" name="wwea_n8n_shared_secret" value="' . esc_attr( $secret ) . '" class="regular-text" placeholder="' . esc_attr( $suggested_secret ) . '">';
        echo '<p class="description">' . esc_html__( 'A secret key shared between this plugin and your n8n workflows for authentication. Generate a strong random string and use it consistently in n8n.', WWEA_DOMAIN ) . '</p>';
    }

    /**
     * Static method to get the n8n base URL.
     *
     * @since 1.0.0-beta
     * @return string The n8n base webhook URL.
     */
    public static function get_n8n_base_url() {
        return get_option( 'wwea_n8n_base_url', '' );
    }

    /**
     * Static method to get the n8n shared secret.
     *
     * @since 1.0.0-beta
     * @return string The n8n shared secret.
     */
    public static function get_shared_secret() {
        return get_option( 'wwea_n8n_shared_secret', '' );
    }
}
