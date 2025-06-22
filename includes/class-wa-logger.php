<?php
/**
 * Clase para el manejo de logs del plugin WP WhatsApp Evolution API.
 * Proporciona un sistema consistente para registrar mensajes en el log de errores de PHP.
 *
 * @package WP_Whatsapp_Evolution_API
 * @subpackage Includes
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class WA_Logger {

    /**
     * Registra un mensaje en el log de errores de PHP.
     * Los mensajes solo se registran si WP_DEBUG está activado.
     *
     * @param string $message El mensaje a registrar.
     * @param string $level   El nivel del log (e.g., 'info', 'warning', 'error', 'debug').
     * @param array  $context Un array asociativo de datos para añadir contexto al mensaje.
     */
    public static function log( $message, $level = 'info', $context = [] ) {
        // Solo registra si WP_DEBUG está activo para evitar logs excesivos en producción.
        if ( WP_DEBUG === true ) {
            $log_message = sprintf(
                "[WP WhatsApp Evolution API] [%s] %s: %s",
                strtoupper( $level ),
                current_time( 'mysql' ), // Hora actual del servidor WordPress
                $message
            );

            if ( ! empty( $context ) ) {
                $log_message .= ' Contexto: ' . wp_json_encode( $context );
            }

            error_log( $log_message );
        }

        // Permitir que otros plugins o temas se enganchen a los logs del plugin
        do_action( 'wp_whatsapp_evolution_api_log', $level, $message, $context );
    }
}
