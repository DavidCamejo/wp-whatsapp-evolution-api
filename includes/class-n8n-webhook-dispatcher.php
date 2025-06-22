<?php
/**
 * Clase para despachar eventos a Webhooks de n8n.
 *
 * @package WP_Whatsapp_Evolution_API
 * @subpackage Includes
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class N8n_Webhook_Dispatcher {

    /**
     * URL base de la instancia de n8n para los Webhooks.
     *
     * @var string
     */
    private $n8n_base_url;

    /**
     * Token de autenticación opcional para n8n.
     *
     * @var string
     */
    private $n8n_auth_token;

    /**
     * Constructor de la clase.
     * Recupera las opciones de configuración de n8n.
     */
    public function __construct() {
        $this->n8n_base_url = get_option( 'wp_whatsapp_evolution_api_n8n_base_url', '' );
        $this->n8n_auth_token = get_option( 'wp_whatsapp_evolution_api_n8n_auth_token', '' );
    }

    /**
     * Envía un evento a un Webhook de n8n.
     *
     * @param string $eventType El tipo de evento (e.g., 'qr_generation', 'message_send', 'session_status').
     * @param array  $payload Los datos a enviar en el cuerpo de la solicitud.
     * @return array|WP_Error La respuesta de n8n decodificada o un objeto WP_Error en caso de fallo.
     */
    public function send_event( $eventType, $payload ) {
        if ( empty( $this->n8n_base_url ) ) {
            // Usamos WA_Logger en lugar de error_log directamente
            WA_Logger::log( 'N8n Base URL no configurada.', 'error', [ 'event_type' => $eventType, 'payload' => $payload ] );
            return new WP_Error( 'n8n_config_error', 'La URL base de n8n no está configurada en los ajustes del plugin.' );
        }

        // Construye la URL completa del Webhook.
        // Asumimos que el Webhook en n8n es /webhook-url/eventType.
        $url = trailingslashit( $this->n8n_base_url ) . sanitize_title( $eventType );

        $args = [
            'body'        => wp_json_encode( $payload ), // Usar wp_json_encode para consistencia
            'headers'     => [
                'Content-Type' => 'application/json',
            ],
            'method'      => 'POST',
            'timeout'     => 30, // Tiempo de espera en segundos
            'data_format' => 'body', // Asegura que el cuerpo se envíe como JSON
        ];

        // Añadir el token de autenticación si está configurado.
        if ( ! empty( $this->n8n_auth_token ) ) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->n8n_auth_token;
        }

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            WA_Logger::log( 'Error al enviar evento a n8n.', 'error', [
                'event_type' => $eventType,
                'payload'    => $payload,
                'error'      => $response->get_error_message(),
                'url'        => $url,
            ] );
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );

        if ( $http_code < 200 || $http_code >= 300 ) {
            WA_Logger::log( 'N8n devolvió un error HTTP.', 'error', [
                'event_type'    => $eventType,
                'payload'       => $payload,
                'http_code'     => $http_code,
                'response_body' => $body,
                'url'           => $url,
            ] );
            return new WP_Error( 'n8n_response_error', 'Error al procesar el evento en n8n.', [ 'status' => $http_code, 'body' => $body ] );
        }

        $parsed_body = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            WA_Logger::log( 'Respuesta de n8n no es un JSON válido.', 'warning', [
                'event_type'    => $eventType,
                'payload'       => $payload,
                'http_code'     => $http_code,
                'response_body' => $body,
                'url'           => $url,
            ] );
            // Si no es JSON, devolvemos el cuerpo crudo.
            return $body;
        }

        WA_Logger::log( 'Evento enviado a n8n exitosamente.', 'info', [
            'event_type'    => $eventType,
            'payload'       => $payload,
            'response_body' => $parsed_body,
            'url'           => $url,
        ] );
        return $parsed_body;
    }
}
