<?php
/**
 * Handles AJAX requests from Dokan dashboard.
 *
 * @package WP_WhatsApp_Evolution_API
 * @subpackage WP_WhatsApp_Evolution_API/includes
 * @since 1.0.0-beta
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WWEA_AJAX_Handler {

    /**
     * @var WWEA_N8n_Client $n8n_client An instance of the n8n client.
     */
    private $n8n_client;

    /**
     * @var WWEA_Dokan_Integration $dokan_integration An instance of the Dokan integration class.
     */
    private $dokan_integration;

    /**
     * Constructor.
     *
     * @since 1.0.0-beta
     * @param WWEA_N8n_Client $n8n_client An instance of the n8n client.
     */
    public function __construct( WWEA_N8n_Client $n8n_client ) {
        $this->n8n_client = $n8n_client;
        // The DokanIntegration class is instantiated by Core,
        // but we need a way to access its methods.
        // We'll instantiate it here or pass it if dependencies grow complex.
        $this->dokan_integration = new WWEA_Dokan_Integration( $n8n_client );
    }

    /**
     * Handles AJAX request to get WhatsApp data (QR Code and Status).
     * Hooked to 'wp_ajax_wwea_dokan_get_whatsapp_data'.
     *
     * @since 1.0.0-beta
     */
    public function handle_get_whatsapp_data() {
        check_ajax_referer( 'wwea_dokan_whatsapp_nonce', 'nonce' );

        // Ensure the current user is a Dokan vendor
        if ( ! current_user_can( 'dokan_edit_product' ) || ! dokan_is_vendor( get_current_user_id() ) ) {
            wp_send_json_error( array( 'message' => __( 'Access denied. You must be a Dokan vendor.', WWEA_DOMAIN ) ) );
        }

        $vendor_id = get_current_user_id();

        // Initialize responses
        $qr_code_data = '';
        $instance_status = 'disconnected';
        $connection_info = [];
        $error_message = '';

        // 1. Request QR Code
        $qr_response = $this->n8n_client->request_qr_code( $vendor_id );

        if ( is_wp_error( $qr_response ) ) {
            $error_message .= __( 'Failed to get QR Code: ', WWEA_DOMAIN ) . $this->n8n_client->get_last_error_message() . ' ';
            // Optionally, set status to 'error' if QR fails.
            $instance_status = 'error';
        } else {
            // Assuming n8n returns 'qr_code' in the response
            $qr_code_data = $qr_response['qr_code'] ?? '';
            // Update QR code data in user meta
            $this->dokan_integration->update_vendor_whatsapp_setting( $vendor_id, '_wwea_whatsapp_qr_code_data', $qr_code_data );
            // If QR is obtained, status is likely 'scanning' or 'authenticated' (waiting for scan)
            if (!empty($qr_code_data) && $instance_status !== 'connected') { // Don't override 'connected' if it was already.
                $instance_status = 'scanning';
            }
        }

        // 2. Request Instance Status
        $status_response = $this->n8n_client->request_instance_status( $vendor_id );

        if ( is_wp_error( $status_response ) ) {
            $error_message .= __( 'Failed to get status: ', WWEA_DOMAIN ) . $this->n8n_client->get_last_error_message();
            // Only set to error if no QR was obtained, otherwise QR error already covers it.
            if( empty($qr_code_data) ) {
                $instance_status = 'error';
            }
        } else {
            // Assuming n8n returns 'status' and 'connection_info'
            $instance_status = $status_response['status'] ?? $instance_status; // Use status from n8n if available
            $connection_info = $status_response['connection_info'] ?? []; // Other details
        }

        // Update status and connection info in user meta (always update with latest)
        $this->dokan_integration->update_vendor_whatsapp_setting( $vendor_id, '_wwea_whatsapp_connection_status', $instance_status );
        $this->dokan_integration->update_vendor_whatsapp_setting( $vendor_id, '_wwea_whatsapp_connection_info', $connection_info );
        $this->dokan_integration->update_vendor_whatsapp_setting( $vendor_id, '_wwea_whatsapp_last_update_ts', current_time( 'timestamp' ) );


        // Send back consolidated response to frontend
        if ( ! empty( $error_message ) ) {
            wp_send_json_error( array(
                'message'         => trim( $error_message ),
                'qr_code'         => $qr_code_data, // Still send QR if partially successful
                'status'          => $instance_status,
                'connection_info' => $connection_info,
            ) );
        } else {
            wp_send_json_success( array(
                'message'         => __( 'WhatsApp data retrieved successfully.', WWEA_DOMAIN ),
                'qr_code'         => $qr_code_data,
                'status'          => $instance_status,
                'connection_info' => $connection_info,
            ) );
        }
    }
}
