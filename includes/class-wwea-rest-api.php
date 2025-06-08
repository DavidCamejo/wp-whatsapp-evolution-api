<?php
/**
 * Handles REST API endpoint for n8n webhooks (incoming data to WP).
 *
 * @package WP_WhatsApp_Evolution_API
 * @subpackage WP_WhatsApp_Evolution_API/includes
 * @since 1.0.0-beta
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WWEA_REST_API {

    /**
     * Constructor.
     *
     * @since 1.0.0-beta
     */
    public function __construct() {
        // Dependencies like DokanIntegration will be instantiated within the route callback if needed.
    }

    /**
     * Registers custom REST API routes for n8n webhooks.
     * Hooked to 'rest_api_init'.
     *
     * @since 1.0.0-beta
     */
    public function register_routes() {
        register_rest_route( 'dokan-whatsapp/v1', '/status-update', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_status_update' ),
            'permission_callback' => array( $this, 'authenticate_n8n_webhook' ), // Secure webhook with shared secret
            'args'                => array(
                'instance_name' => array(
                    'description' => __( 'Unique name of the WhatsApp instance.', WWEA_DOMAIN ),
                    'type'        => 'string',
                    'required'    => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'status' => array(
                    'description' => __( 'New connection status (e.g., connected, disconnected, qr_scanned).', WWEA_DOMAIN ),
                    'type'        => 'string',
                    'required'    => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'connection_info' => array(
                    'description' => __( 'Optional: Detailed connection information from Evolution API.', WWEA_DOMAIN ),
                    'type'        => 'array',
                    'required'    => false,
                    'validate_callback' => function( $value, $request, $key ) {
                        return is_array( $value ); // Basic validation, can be more detailed
                    },
                ),
                // Add other potential fields from n8n/Evolution API webhook here
            ),
        ) );
    }

    /**
     * Handles incoming status update webhooks from n8n.
     *
     * @since 1.0.0-beta
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response
     */
    public function handle_status_update( WP_REST_Request $request ) {
        $instance_name   = $request->get_param( 'instance_name' );
        $new_status      = $request->get_param( 'status' );
        $connection_info = $request->get_param( 'connection_info' ) ?? [];

        // For this preliminary version, we directly instantiate DokanIntegration.
        // In a more complex setup, you might pass it through the core loader.
        $dokan_integration = new WWEA_Dokan_Integration( new WWEA_N8n_Client() );

        // Find the vendor ID associated with this instance name.
        // This is crucial. For many vendors, consider an efficient lookup table or dedicated meta key.
        // For now, we assume instance_name is 'dokan_vendor_{ID}_whatsapp_instance'.
        if ( preg_match( '/^dokan_vendor_(\d+)_whatsapp_instance$/', $instance_name, $matches ) ) {
            $vendor_id = intval( $matches[1] );
        } else {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => __( 'Invalid instance name format.', WWEA_DOMAIN ),
                    'instance_name' => $instance_name,
                ),
                400
            );
        }

        // Check if vendor exists and is a Dokan vendor.
        if ( ! get_user_by( 'ID', $vendor_id ) || ! function_exists( 'dokan_is_vendor' ) || ! dokan_is_vendor( $vendor_id ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => __( 'Vendor not found or not a Dokan vendor for this instance.', WWEA_DOMAIN ),
                    'vendor_id' => $vendor_id,
                ),
                404
            );
        }

        // Update vendor's WhatsApp connection status and info
        $dokan_integration->update_vendor_whatsapp_setting( $vendor_id, '_wwea_whatsapp_connection_status', $new_status );
        $dokan_integration->update_vendor_whatsapp_setting( $vendor_id, '_wwea_whatsapp_connection_info', $connection_info );
        $dokan_integration->update_vendor_whatsapp_setting( $vendor_id, '_wwea_whatsapp_last_update_ts', current_time( 'timestamp' ) );

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => __( 'WhatsApp status updated successfully.', WWEA_DOMAIN ),
                'vendor_id' => $vendor_id,
                'new_status' => $new_status,
            ),
            200
        );
    }

    /**
     * Authenticates the incoming webhook request from n8n using a shared secret.
     * Hooked to 'permission_callback' for the REST route.
     *
     * @since 1.0.0-beta
     * @param WP_REST_Request $request The REST API request object.
     * @return bool|WP_Error True if authenticated, WP_Error otherwise.
     */
    public function authenticate_n8n_webhook( WP_REST_Request $request ) {
        $stored_shared_secret = WWEA_Settings::get_shared_secret();
        // Headers are typically lowercased and hyphens converted to underscores by WordPress.
        $received_secret = $request->get_header( 'x_wwea_secret' );

        if ( empty( $stored_shared_secret ) ) {
            // Log error for admin to see
            error_log( sprintf( '[%s] n8n Shared Secret is not configured in plugin settings. Webhook cannot be authenticated.', WWEA_DOMAIN ) );
            return new WP_Error( 'wwea_auth_error', __( 'n8n Shared Secret not configured on WordPress side.', WWEA_DOMAIN ), array( 'status' => 401 ) );
        }

        if ( empty( $received_secret ) || $received_secret !== $stored_shared_secret ) {
            // Log error for admin to see
            error_log( sprintf( '[%s] Unauthorized webhook access: Invalid or missing X-WWEA-SECRET header. Received: %s', WWEA_DOMAIN, $received_secret ) );
            return new WP_Error( 'wwea_auth_error', __( 'Unauthorized access. Invalid or missing secret.', WWEA_DOMAIN ), array( 'status' => 401 ) );
        }

        return true;
    }
}
