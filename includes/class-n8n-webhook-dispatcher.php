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
            $this->log_event( 'error', 'N8n Base URL no configurada.', [ 'event_type' => $eventType, 'payload' => $payload ] );
            return new WP_Error( 'n8n_config_error', 'La URL base de n8n no está configurada en los ajustes del plugin.' );
        }

        // Construye la URL completa del Webhook.
        // Asumimos que el Webhook en n8n es /webhook-url/eventType.
        $url = trailingslashit( $this->n8n_base_url ) . sanitize_title( $eventType );

        $args = [
            'body'        => json_encode( $payload ),
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
            $this->log_event( 'error', 'Error al enviar evento a n8n.', [
                'event_type' => $eventType,
                'payload'    => $payload,
                'error'      => $response->get_error_message(),
            ] );
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );

        if ( $http_code < 200 || $http_code >= 300 ) {
            $this->log_event( 'error', 'N8n devolvió un error HTTP.', [
                'event_type'    => $eventType,
                'payload'       => $payload,
                'http_code'     => $http_code,
                'response_body' => $body,
            ] );
            return new WP_Error( 'n8n_response_error', 'Error al procesar el evento en n8n.', [ 'status' => $http_code, 'body' => $body ] );
        }

        $parsed_body = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->log_event( 'warning', 'Respuesta de n8n no es un JSON válido.', [
                'event_type'    => $eventType,
                'payload'       => $payload,
                'http_code'     => $http_code,
                'response_body' => $body,
            ] );
            // Si no es JSON, devolvemos el cuerpo crudo.
            return $body;
        }

        $this->log_event( 'info', 'Evento enviado a n8n exitosamente.', [
            'event_type'    => $eventType,
            'payload'       => $payload,
            'response_body' => $parsed_body,
        ] );
        return $parsed_body;
    }

    /**
     * Helper para generar logs del plugin.
     *
     * @param string $level Nivel del log (info, warning, error).
     * @param string $message Mensaje del log.
     * @param array $context Contexto adicional para el log.
     */
    private function log_event( $level, $message, $context = [] ) {
        $log_message = sprintf( "WP-WhatsApp-Evolution-API n8n Dispatcher [%s]: %s", strtoupper( $level ), $message );
        if ( ! empty( $context ) ) {
            $log_message .= ' Contexto: ' . json_encode( $context );
        }

        error_log( $log_message );
        do_action( 'wp_whatsapp_evolution_api_log', $level, $message, $context );
    }
}
