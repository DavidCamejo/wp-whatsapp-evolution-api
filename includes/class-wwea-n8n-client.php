<?php
/**
 * Core class for n8n API interactions.
 *
 * @package WP_WhatsApp_Evolution_API
 * @subpackage WP_WhatsApp_Evolution_API/includes
 * @since 1.0.0-beta
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WWEA_N8n_Client {

    private $base_url;
    private $shared_secret;
    private $last_error_message;

    /**
     * Constructor.
     * Fetches n8n configuration from plugin settings.
     *
     * @since 1.0.0-beta
     */
    public function __construct() {
        $this->base_url      = trailingslashit( WWEA_Settings::get_n8n_base_url() );
        $this->shared_secret = WWEA_Settings::get_shared_secret();
        $this->last_error_message = '';
    }

    /**
     * Sends a request to an n8n webhook.
     *
     * @since 1.0.0-beta
     * @param string $endpoint The specific webhook endpoint after the base URL (e.g., 'get-qr-code').
     * @param array  $payload  The data to send to n8n.
     * @param string $method   The HTTP method (e.g., 'POST', 'GET'). Default is 'POST'.
     * @return array|WP_Error The n8n response as an associative array, or a WP_Error object on failure.
     */
    private function _make_request( $endpoint, $payload = array(), $method = 'POST' ) {
        if ( empty( $this->base_url ) ) {
            $this->last_error_message = __( 'n8n Base Webhook URL is not configured in plugin settings.', WWEA_DOMAIN );
            return new WP_Error( 'wwea_n8n_config_error', $this->last_error_message );
        }
        if ( empty( $this->shared_secret ) ) {
            $this->last_error_message = __( 'n8n Shared Secret is not configured in plugin settings. Please set it for secure communication.', WWEA_DOMAIN );
            return new WP_Error( 'wwea_n8n_config_error', $this->last_error_message );
        }

        $url = $this->base_url . $endpoint;

        $args = array(
            'headers'     => array(
                'Content-Type'   => 'application/json',
                'X-WWEA-SECRET'  => $this->shared_secret, // Custom header for shared secret authentication
            ),
            'method'      => $method,
            'timeout'     => 30, // In seconds
            'data_format' => 'body',
        );

        if ( 'POST' === $method ) {
            $args['body'] = json_encode( $payload );
        } elseif ( 'GET' === $method && ! empty( $payload ) ) {
            $url = add_query_arg( $payload, $url );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->last_error_message = $response->get_error_message();
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );
        $data      = json_decode( $body, true );

        if ( $http_code < 200 || $http_code >= 300 ) {
            $error_message = isset( $data['message'] ) ? $data['message'] : __( 'An unknown error occurred on n8n.', WWEA_DOMAIN );
            $this->last_error_message = sprintf( __( 'n8n API error (%d): %s', WWEA_DOMAIN ), $http_code, $error_message );
            return new WP_Error( 'wwea_n8n_api_error', $this->last_error_message, array( 'status' => $http_code, 'response' => $data ) );
        }

        return $data;
    }

    /**
     * Requests the QR code for a given vendor instance from n8n.
     *
     * @since 1.0.0-beta
     * @param int $vendor_id The ID of the Dokan vendor.
     * @return array|WP_Error Response containing QR code data, or WP_Error.
     */
    public function request_qr_code( $vendor_id ) {
        $instance_name = $this->get_vendor_instance_name( $vendor_id );
        return $this->_make_request( 'get-qr-code', array( 'vendor_id' => $vendor_id, 'instance_name' => $instance_name ) );
    }

    /**
     * Requests the connection status for a given vendor instance from n8n.
     *
     * @since 1.0.0-beta
     * @param int $vendor_id The ID of the Dokan vendor.
     * @return array|WP_Error Response containing status data, or WP_Error.
     */
    public function request_instance_status( $vendor_id ) {
        $instance_name = $this->get_vendor_instance_name( $vendor_id );
        return $this->_make_request( 'get-status', array( 'vendor_id' => $vendor_id, 'instance_name' => $instance_name ) );
    }

    /**
     * Helper to get a consistent instance name for a vendor.
     * This can be stored in user meta for consistency if needed,
     * but for now, we'll generate it on the fly.
     *
     * @since 1.0.0-beta
     * @param int $vendor_id The ID of the Dokan vendor.
     * @return string The unique instance name for the vendor's WhatsApp.
     */
    public function get_vendor_instance_name( $vendor_id ) {
        // You might want to store this in user_meta after first generation
        // for better consistency, especially if the generation logic changes.
        return 'dokan_vendor_' . $vendor_id . '_whatsapp_instance';
    }

    /**
     * Returns the last error message from an n8n request.
     *
     * @since 1.0.0-beta
     * @return string
     */
    public function get_last_error_message() {
        return $this->last_error_message;
    }
}
