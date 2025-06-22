<?php
/**
 * Clase para manejar la parte pública del plugin, incluyendo las rutas REST.
 *
 * @package WP_Whatsapp_Evolution_API
 * @subpackage Includes
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_Whatsapp_Evolution_API_Public {

    /**
     * Instancia del despachador de Webhooks de n8n.
     *
     * @var N8n_Webhook_Dispatcher
     */
    private $n8n_dispatcher;

    /**
     * Constructor de la clase.
     * Inicializa el despachador de n8n y registra las rutas REST.
     */
    public function __construct() {
        $this->n8n_dispatcher = new N8n_Webhook_Dispatcher();
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    /**
     * Helper para obtener el ID del vendedor Dokan actual.
     * Requiere que Dokan esté activo.
     *
     * @return int|false ID del vendedor si está logueado y es un vendedor de Dokan, false de lo contrario.
     */
    private function get_current_dokan_vendor_id() {
        if ( ! function_exists( 'dokan_is_vendor' ) ) {
            WA_Logger::log( 'Dokan no está activo al intentar obtener el ID del vendedor.', 'error' );
            return false; // Dokan no está activo o no es un vendedor.
        }

        $user_id = get_current_user_id();
        if ( $user_id && dokan_is_vendor( $user_id ) ) {
            return $user_id;
        }
        WA_Logger::log( 'Usuario actual no es un vendedor Dokan o no está logueado.', 'warning', [ 'user_id' => $user_id ] );
        return false;
    }

    /**
     * Callback de permisos para las rutas REST: solo permite el acceso a vendedores de Dokan.
     * También verifica el nonce.
     *
     * @param WP_REST_Request $request La solicitud REST.
     * @return bool|WP_Error True si está autenticado como vendedor de Dokan y el nonce es válido, WP_Error de lo contrario.
     */
    public function dokan_vendor_permission_callback( WP_REST_Request $request ) {
        $vendor_id = $this->get_current_dokan_vendor_id();

        if ( ! $vendor_id ) {
            return new WP_Error(
                'rest_forbidden_vendor',
                __( 'Only Dokan vendors are allowed to access this endpoint.', 'wp-whatsapp-evolution-api' ),
                [ 'status' => 401 ]
            );
        }

        // **Verificación de Nonce explícita para solicitudes REST.**
        // Aunque WP_REST_Server::check_authentication ya maneja nonces para usuarios logueados,
        // añadir una verificación explícita aquí asegura que el nonce sea el esperado por nuestro plugin
        // y añade una capa extra de seguridad para nuestras rutas custom.
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            WA_Logger::log( 'Nonce inválido o ausente en solicitud REST.', 'warning', [ 'user_id' => $vendor_id, 'nonce_received' => $nonce ] );
            return new WP_Error(
                'rest_nonce_invalid',
                __( 'Nonce verification failed.', 'wp-whatsapp-evolution-api' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Valida un número de teléfono.
     * Permite un '+' opcional al inicio seguido de solo dígitos.
     *
     * @param string $phone_number El número de teléfono a validar.
     * @return bool True si el número es válido, false en caso contrario.
     */
    private function validate_phone_number( $phone_number ) {
        // Expresión regular para permitir un '+' opcional al inicio, seguido de solo dígitos.
        // Mínimo de 7 dígitos para un número de teléfono real.
        return preg_match( '/^\+?\d{7,}$/', $phone_number );
    }

    /**
     * Registra las rutas personalizadas de la API REST de WordPress.
     */
    public function register_rest_routes() {
        // Ruta para obtener el código QR para la sesión de un vendedor específico
        register_rest_route( 'wp-whatsapp-evolution-api/v1/vendor', '/qr', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_vendor_qr_code' ],
            'permission_callback' => [ $this, 'dokan_vendor_permission_callback' ],
        ] );

        // Ruta para enviar un mensaje de WhatsApp (desde la sesión de un vendedor)
        register_rest_route( 'wp-whatsapp-evolution-api/v1/vendor', '/enviar-mensaje', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'send_vendor_whatsapp_message' ],
            'permission_callback' => [ $this, 'dokan_vendor_permission_callback' ],
            'args'                => [
                'to'           => [
                    'description'       => 'Número de teléfono del destinatario (ej. 551199999999).',
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => [ $this, 'validate_phone_number' ], // **Nueva validación**
                ],
                'message'      => [
                    'description'       => 'Contenido del mensaje a enviar.',
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        // Ruta para obtener el estado de la sesión de WhatsApp de un vendedor
        register_rest_route( 'wp-whatsapp-evolution-api/v1/vendor', '/estado-sesion', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_vendor_session_status' ],
            'permission_callback' => [ $this, 'dokan_vendor_permission_callback' ],
        ] );
    }

    /**
     * Genera un nombre de sesión único para un vendedor.
     *
     * @param int $vendor_id El ID del vendedor de Dokan.
     * @return string
     */
    private function get_vendor_session_name( $vendor_id ) {
        return 'dokan_vendor_' . $vendor_id;
    }

    /**
     * Callback para la ruta REST '/vendor/qr'.
     * Solicita el código QR para la sesión de WhatsApp del vendedor actual de Dokan.
     *
     * @param WP_REST_Request $request La solicitud REST.
     * @return WP_REST_Response La respuesta HTTP.
     */
    public function get_vendor_qr_code( WP_REST_Request $request ) {
        $vendor_id    = $this->get_current_dokan_vendor_id();
        $session_name = $this->get_vendor_session_name( $vendor_id );

        WA_Logger::log( 'Solicitud de QR para vendedor.', 'info', [ 'vendor_id' => $vendor_id, 'session_name' => $session_name ] );

        update_user_meta( $vendor_id, 'dokan_whatsapp_session_status', 'pending_qr_scan' );
        update_user_meta( $vendor_id, 'dokan_whatsapp_qr_code_url', '' ); // Limpia QR anterior

        $payload = [
            'eventType'   => 'qr_generation',
            'sessionName' => $session_name,
            'vendorId'    => $vendor_id,
        ];

        do_action( 'wp_whatsapp_evolution_api_before_qr_webhook', $payload );

        $response_from_n8n = $this->n8n_dispatcher->send_event( 'qr_generation', $payload );

        if ( is_wp_error( $response_from_n8n ) ) {
            WA_Logger::log( 'Error al solicitar QR a n8n.', 'error', [ 'vendor_id' => $vendor_id, 'error' => $response_from_n8n->get_error_message() ] );
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'Error al solicitar QR a n8n: ' . $response_from_n8n->get_error_message(),
                'code'    => $response_from_n8n->get_error_code(),
            ], 500 );
        }

        $qr_data = $response_from_n8n['data']['qrCodeUrl'] ?? ($response_from_n8n['data']['qrCodeImageBase64'] ?? '');

        if (!empty($qr_data)) {
            update_user_meta( $vendor_id, 'dokan_whatsapp_qr_code_url', $qr_data );
            WA_Logger::log( 'QR Code URL/data received and stored for vendor.', 'info', [ 'vendor_id' => $vendor_id, 'qr_data_length' => strlen($qr_data) ] );
        } else {
            WA_Logger::log( 'No QR Code URL/data received from n8n for vendor.', 'warning', [ 'vendor_id' => $vendor_id, 'n8n_response' => $response_from_n8n ] );
        }

        return new WP_REST_Response( [
            'success' => true,
            'message' => 'Solicitud de generación de QR enviada a n8n.',
            'data'    => $response_from_n8n,
        ], 200 );
    }

    /**
     * Callback para la ruta REST '/vendor/enviar-mensaje'.
     * Envía un mensaje de WhatsApp desde la sesión del vendedor actual de Dokan.
     *
     * @param WP_REST_Request $request La solicitud REST.
     * @return WP_REST_Response La respuesta HTTP.
     */
    public function send_vendor_whatsapp_message( WP_REST_Request $request ) {
        $vendor_id    = $this->get_current_dokan_vendor_id();
        $session_name = $this->get_vendor_session_name( $vendor_id );

        // Los parámetros 'to' y 'message' ya fueron validados y sanitizados por los 'args' de register_rest_route
        $to           = $request->get_param( 'to' );
        $message      = $request->get_param( 'message' );

        WA_Logger::log( 'Solicitud de envío de mensaje para vendedor.', 'info', [ 'vendor_id' => $vendor_id, 'to' => $to ] );

        $payload = [
            'eventType'   => 'message_send',
            'sessionName' => $session_name,
            'to'          => $to,
            'message'     => $message,
            'vendorId'    => $vendor_id,
        ];

        do_action( 'wp_whatsapp_evolution_api_before_message_webhook', $payload );

        $response_from_n8n = $this->n8n_dispatcher->send_event( 'message_send', $payload );

        if ( is_wp_error( $response_from_n8n ) ) {
            WA_Logger::log( 'Error al enviar mensaje a n8n.', 'error', [ 'vendor_id' => $vendor_id, 'error' => $response_from_n8n->get_error_message() ] );
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'Error al enviar mensaje a n8n: ' . $response_from_n8n->get_error_message(),
                'code'    => $response_from_n8n->get_error_code(),
            ], 500 );
        }

        WA_Logger::log( 'Mensaje enviado a n8n exitosamente.', 'info', [ 'vendor_id' => $vendor_id, 'n8n_response' => $response_from_n8n ] );
        return new WP_REST_Response( [
            'success' => true,
            'message' => 'Solicitud de envío de mensaje enviada a n8n.',
            'data'    => $response_from_n8n,
        ], 200 );
    }

    /**
     * Callback para la ruta REST '/vendor/estado-sesion'.
     * Obtiene el estado de la sesión de WhatsApp del vendedor actual de Dokan.
     *
     * @param WP_REST_Request $request La solicitud REST.
     * @return WP_REST_Response La respuesta HTTP.
     */
    public function get_vendor_session_status( WP_REST_Request $request ) {
        $vendor_id    = $this->get_current_dokan_vendor_id();
        $session_name = $this->get_vendor_session_name( $vendor_id );

        WA_Logger::log( 'Solicitud de estado de sesión para vendedor.', 'info', [ 'vendor_id' => $vendor_id, 'session_name' => $session_name ] );

        $payload = [
            'eventType'   => 'session_status',
            'sessionName' => $session_name,
            'vendorId'    => $vendor_id,
        ];

        do_action( 'wp_whatsapp_evolution_api_before_session_status_webhook', $payload );

        $response_from_n8n = $this->n8n_dispatcher->send_event( 'session_status', $payload );

        if ( is_wp_error( $response_from_n8n ) ) {
            WA_Logger::log( 'Error al solicitar estado de sesión a n8n.', 'error', [ 'vendor_id' => $vendor_id, 'error' => $response_from_n8n->get_error_message() ] );
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'Error al solicitar estado de sesión a n8n: ' . $response_from_n8n->get_error_message(),
                'code'    => $response_from_n8n->get_error_code(),
            ], 500 );
        }

        $session_status = $response_from_n8n['data']['status'] ?? 'unknown';
        update_user_meta( $vendor_id, 'dokan_whatsapp_session_status', sanitize_text_field( $session_status ) );

        if ( isset( $response_from_n8n['data']['qrCodeUrl'] ) && 'QRCODE' === $session_status ) {
            update_user_meta( $vendor_id, 'dokan_whatsapp_qr_code_url', $response_from_n8n['data']['qrCodeUrl'] );
            WA_Logger::log( 'Estado de sesión QRCODE y URL de QR recibida.', 'info', [ 'vendor_id' => $vendor_id, 'status' => $session_status ] );
        } else {
            update_user_meta( $vendor_id, 'dokan_whatsapp_qr_code_url', '' );
            WA_Logger::log( 'Estado de sesión actualizado.', 'info', [ 'vendor_id' => $vendor_id, 'status' => $session_status ] );
        }

        return new WP_REST_Response( [
            'success' => true,
            'message' => 'Solicitud de estado de sesión enviada a n8n.',
            'data'    => $response_from_n8n,
            'current_status' => $session_status,
        ], 200 );
    }
}
