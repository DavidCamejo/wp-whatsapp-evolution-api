<?php
/**
 * Handles Dokan specific hooks and vendor panel UI.
 *
 * @package WP_WhatsApp_Evolution_API
 * @subpackage WP_WhatsApp_Evolution_API/includes
 * @since 1.0.0-beta
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WWEA_Dokan_Integration {

    /**
     * @var WWEA_N8n_Client $n8n_client An instance of the n8n client.
     */
    private $n8n_client;

    /**
     * Constructor.
     *
     * @since 1.0.0-beta
     * @param WWEA_N8n_Client $n8n_client An instance of the n8n client.
     */
    public function __construct( WWEA_N8n_Client $n8n_client ) {
        $this->n8n_client = $n8n_client;
    }

    /**
     * Adds the WhatsApp tab to the Dokan vendor dashboard navigation.
     * Hooked to 'dokan_dashboard_nav_items'.
     *
     * @since 1.0.0-beta
     * @param array $urls Current navigation items.
     * @return array Modified navigation items.
     */
    public function add_whatsapp_tab( $urls ) {
        $urls['whatsapp'] = array(
            'title' => __( 'WhatsApp', WWEA_DOMAIN ),
            'icon'  => '<i class="fa fa-whatsapp"></i>', // Ensure Font Awesome or replace with custom icon/SVG
            'url'   => dokan_get_navigation_url( 'whatsapp' ),
            'pos'   => 55, // Position in the menu (adjust as needed)
        );
        return $urls;
    }

    /**
     * Loads the WhatsApp view for the Dokan vendor dashboard.
     * Hooked to 'dokan_load_views'.
     *
     * @since 1.0.0-beta
     * @param string $query_var The current query variable.
     * @param array $template   The template data.
     * @return array Modified template data.
     */
    public function load_whatsapp_tab_content( $query_var, $template ) {
        if ( 'whatsapp' === $query_var ) {
            $template_path = WWEA_PLUGIN_DIR . 'dokan/vendor-dashboard-whatsapp.php';
            if ( file_exists( $template_path ) ) {
                $template['file'] = $template_path;
            }
        }
        return $template;
    }

    /**
     * Enqueue scripts and styles for the Dokan vendor dashboard.
     * Hooked to 'wp_enqueue_scripts'.
     *
     * @since 1.0.0-beta
     */
    public function enqueue_vendor_scripts() {
        if ( function_exists( 'dokan_is_seller_dashboard' ) && dokan_is_seller_dashboard() ) {
            wp_enqueue_style( 'wwea-dokan-vendor-style', WWEA_PLUGIN_URL . 'assets/css/vendor-whatsapp.css', array(), WWEA_VERSION );
            wp_enqueue_script( 'wwea-dokan-vendor-script', WWEA_PLUGIN_URL . 'assets/js/vendor-whatsapp.js', array( 'jquery' ), WWEA_VERSION, true );

            // Localize script data for AJAX calls
            wp_localize_script( 'wwea-dokan-vendor-script', 'wwea_vars', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wwea_dokan_whatsapp_nonce' ),
                'text'     => array(
                    'generating_qr'   => __( 'Generating QR code...', WWEA_DOMAIN ),
                    'refreshing_status' => __( 'Refreshing status...', WWEA_DOMAIN ),
                    'connecting_whatsapp' => __( 'Connecting WhatsApp...', WWEA_DOMAIN ),
                    'connected'       => __( 'Connected', WWEA_DOMAIN ),
                    'disconnected'    => __( 'Disconnected', WWEA_DOMAIN ),
                    'scanning'        => __( 'Scanning', WWEA_DOMAIN ),
                    'error'           => __( 'Error', WWEA_DOMAIN ),
                    'unknown_status'  => __( 'Unknown Status', WWEA_DOMAIN ),
                    'api_error'       => __( 'API Error: ', WWEA_DOMAIN ),
                    'access_denied'   => __( 'Access denied. Please log in as a vendor.', WWEA_DOMAIN ),
                    'save_success'    => __( 'Number saved successfully!', WWEA_DOMAIN ),
                    'save_fail'       => __( 'Failed to save number.', WWEA_DOMAIN ),
                ),
            ) );
        }
    }

    /**
     * Retrieves all relevant WhatsApp settings for a given vendor.
     *
     * @since 1.0.0-beta
     * @param int $vendor_id The ID of the Dokan vendor (user ID).
     * @return array An associative array of WhatsApp settings.
     */
    public function get_vendor_whatsapp_settings( $vendor_id ) {
        $settings = array(
            'instance_name'       => $this->n8n_client->get_vendor_instance_name( $vendor_id ),
            'connection_status'   => get_user_meta( $vendor_id, '_wwea_whatsapp_connection_status', true ) ?: 'disconnected',
            'qr_code_data'        => get_user_meta( $vendor_id, '_wwea_whatsapp_qr_code_data', true ) ?: '',
            'whatsapp_number'     => get_user_meta( $vendor_id, '_wwea_whatsapp_number', true ) ?: '',
            'last_update_timestamp' => get_user_meta( $vendor_id, '_wwea_whatsapp_last_update_ts', true ) ?: 0,
            'connection_info'     => get_user_meta( $vendor_id, '_wwea_whatsapp_connection_info', true ) ?: [], // Detailed info from n8n/Evolution
        );
        return $settings;
    }

    /**
     * Updates a specific WhatsApp setting for a vendor.
     *
     * @since 1.0.0-beta
     * @param int    $vendor_id The ID of the Dokan vendor.
     * @param string $key       The meta key to update (e.g., '_wwea_whatsapp_connection_status').
     * @param mixed  $value     The value to set.
     * @return bool True on success, false on failure.
     */
    public function update_vendor_whatsapp_setting( $vendor_id, $key, $value ) {
        return update_user_meta( $vendor_id, $key, $value );
    }

    /**
     * AJAX handler to save vendor's WhatsApp number.
     * @since 1.0.0-beta
     */
    public function ajax_save_vendor_whatsapp_number() {
        check_ajax_referer( 'wwea_dokan_whatsapp_nonce', 'nonce' );

        if ( ! current_user_can( 'dokan_edit_product' ) || ! dokan_is_vendor( get_current_user_id() ) ) {
            wp_send_json_error( array( 'message' => __( 'Access denied.', WWEA_DOMAIN ) ) );
        }

        $vendor_id = get_current_user_id();
        $whatsapp_number = sanitize_text_field( $_POST['whatsapp_number'] ?? '' );

        if ( empty( $whatsapp_number ) ) {
            wp_send_json_error( array( 'message' => __( 'WhatsApp number cannot be empty.', WWEA_DOMAIN ) ) );
        }

        if ( $this->update_vendor_whatsapp_setting( $vendor_id, '_wwea_whatsapp_number', $whatsapp_number ) ) {
            wp_send_json_success( array( 'message' => __( 'WhatsApp number saved successfully!', WWEA_DOMAIN ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to save WhatsApp number.', WWEA_DOMAIN ) ) );
        }
    }
}
