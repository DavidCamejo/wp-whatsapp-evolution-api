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
            return false; // Dokan no está activo o no es un vendedor.
        }

        $user_id = get_current_user_id();
        if ( $user_id && dokan_is_vendor( $user_id ) ) {
            return $user_id;
        }
        return false;
    }

    /**
     * Callback de permisos para las rutas REST: solo permite el acceso a vendedores de Dokan.
     *
     * @param WP_REST_Request $request La solicitud REST.
     * @return bool|WP_Error True si está autenticado como vendedor de Dokan, WP_Error de lo contrario.
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
        return true;
    }

    /**
     * Registra las rutas personalizadas de la API REST de WordPress.
     * Cada ruta actuará como un punto de entrada para los eventos de n8n.
     */
    public function register_rest_routes() {
        // Ruta para obtener el código QR para la sesión de un vendedor específico
        register_rest_route( 'wp-whatsapp-evolution-api/v1/vendor', '/qr', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_vendor_qr_code' ],
            'permission_callback' => [ $this, 'dokan_vendor_permission_callback' ],
            // session_name ahora se deriva del ID del vendedor, ya no es un argumento directo.
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
                ],
                'message'      => [
                    'description'       => 'Contenido del mensaje a enviar.',
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                // session_name se deriva del ID del vendedor, ya no es un argumento directo.
            ],
        ] );

        // Ruta para obtener el estado de la sesión de WhatsApp de un vendedor
        register_rest_route( 'wp-whatsapp-evolution-api/v1/vendor', '/estado-sesion', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_vendor_session_status' ],
            'permission_callback' => [ $this, 'dokan_vendor_permission_callback' ],
            // session_name se deriva del ID del vendedor, ya no es un argumento directo.
        ] );
    }

    /**
     * Genera un nombre de sesión único para un vendedor.
     *
     * @param int $vendor_id El ID del vendedor de Dokan.
     * @return string
     */
    private function get_vendor_session_name( $vendor_id ) {
        // Usa un prefijo para evitar conflictos e identificar sesiones fácilmente en n8n/Evolution API.
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

        // Actualiza el estado de la sesión del vendedor a 'scanning_qr' o similar.
        update_user_meta( $vendor_id, 'dokan_whatsapp_session_status', 'pending_qr_scan' );
        update_user_meta( $vendor_id, 'dokan_whatsapp_qr_code_url', '' ); // Limpia QR anterior

        $payload = [
            'eventType'   => 'qr_generation',
            'sessionName' => $session_name,
            'vendorId'    => $vendor_id, // Pasa el ID del vendedor para el registro/identificación en n8n
        ];

        do_action( 'wp_whatsapp_evolution_api_before_qr_webhook', $payload );

        $response_from_n8n = $this->n8n_dispatcher->send_event( 'qr_generation', $payload );

        if ( is_wp_error( $response_from_n8n ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'Error al solicitar QR a n8n: ' . $response_from_n8n->get_error_message(),
                'code'    => $response_from_n8n->get_error_code(),
            ], 500 );
        }

        // Asumiendo que n8n devuelve una 'qrCodeUrl' o 'qrCodeImageBase64' en su respuesta.
        $qr_data = $response_from_n8n['data']['qrCodeUrl'] ?? ($response_from_n8n['data']['qrCodeImageBase64'] ?? '');

        if (!empty($qr_data)) {
            // Almacena la URL/datos del QR temporalmente para mostrarlo en el dashboard del vendedor.
            update_user_meta( $vendor_id, 'dokan_whatsapp_qr_code_url', $qr_data );
        }

        return new WP_REST_Response( [
            'success' => true,
            'message' => 'Solicitud de generación de QR enviada a n8n.',
            'data'    => $response_from_n8n, // Contiene la respuesta procesada de n8n (ej. URL del QR, datos de la imagen)
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

        $to           = $request->get_param( 'to' );
        $message      = $request->get_param( 'message' );

        if ( empty( $to ) || empty( $message ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'Los campos "to" y "message" son obligatorios.',
            ], 400 );
        }

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
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'Error al enviar mensaje a n8n: ' . $response_from_n8n->get_error_message(),
                'code'    => $response_from_n8n->get_error_code(),
            ], 500 );
        }

        return new WP_REST_Response( [
            'success' => true,
            'message' => 'Solicitud de envío de mensaje enviada a n8n.',
            'data'    => $response_from_n8n, // Contiene la respuesta de n8n sobre el envío
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

        $payload = [
            'eventType'   => 'session_status',
            'sessionName' => $session_name,
            'vendorId'    => $vendor_id,
        ];

        do_action( 'wp_whatsapp_evolution_api_before_session_status_webhook', $payload );

        $response_from_n8n = $this->n8n_dispatcher->send_event( 'session_status', $payload );

        if ( is_wp_error( $response_from_n8n ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => 'Error al solicitar estado de sesión a n8n: ' . $response_from_n8n->get_error_message(),
                'code'    => $response_from_n8n->get_error_code(),
            ], 500 );
        }

        // Asumiendo que n8n devuelve un campo 'status' (ej. 'CONNECTED', 'DISCONNECTED', 'QRCODE', etc.)
        $session_status = $response_from_n8n['data']['status'] ?? 'unknown';
        update_user_meta( $vendor_id, 'dokan_whatsapp_session_status', sanitize_text_field( $session_status ) );

        // Si el estado es 'QRCODE', el QR podría refrescarse, de lo contrario, se limpia.
        if ( isset( $response_from_n8n['data']['qrCodeUrl'] ) && 'QRCODE' === $session_status ) {
            update_user_meta( $vendor_id, 'dokan_whatsapp_qr_code_url', $response_from_n8n['data']['qrCodeUrl'] );
        } else {
            update_user_meta( $vendor_id, 'dokan_whatsapp_qr_code_url', '' ); // Limpia QR si no es QRCODE
        }


        return new WP_REST_Response( [
            'success' => true,
            'message' => 'Solicitud de estado de sesión enviada a n8n.',
            'data'    => $response_from_n8n, // Contiene la respuesta de n8n sobre el estado de la sesión
            'current_status' => $session_status, // Para visualización inmediata en el frontend
        ], 200 );
    }
}
