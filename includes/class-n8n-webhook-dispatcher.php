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
     * Instancia de la clase de caché.
     *
     * @var WP_Whatsapp_Evolution_API_Cache
     */
    private $cache;
    
    /**
     * Instancia de la clase de seguridad.
     *
     * @var WP_Whatsapp_Evolution_API_Security
     */
    private $security;
    
    /**
     * Instancia de la clase de eventos.
     *
     * @var WP_Whatsapp_Evolution_API_Events
     */
    private $events;

    /**
     * Constructor de la clase.
     * Recupera las opciones de configuración de n8n.
     */
    public function __construct() {
        // Inicializar instancias de utilidades
        $this->cache = new WP_Whatsapp_Evolution_API_Cache('n8n_', 300); // Cache con prefijo n8n_ y 5 min de expiración
        $this->security = new WP_Whatsapp_Evolution_API_Security();
        $this->events = WP_Whatsapp_Evolution_API_Events::get_instance();
        
        // Obtener configuración de n8n
        $this->n8n_base_url = get_option('wp_whatsapp_evolution_api_n8n_base_url', '');
        
        // Obtener token usando el sistema de seguridad para desencriptar
        $this->n8n_auth_token = $this->security->get_secure_option('wp_whatsapp_evolution_api_n8n_auth_token', '');
    }

    /**
     * Envía un evento a un Webhook de n8n.
     *
     * @param string $eventType El tipo de evento (e.g., 'qr_generation', 'message_send', 'session_status').
     * @param array  $payload Los datos a enviar en el cuerpo de la solicitud.
     * @param bool   $use_cache Indica si se debe usar caché para este evento.
     * @param int    $cache_expiration Tiempo de expiración para la caché en segundos.
     * @return array|WP_Error La respuesta de n8n decodificada o un objeto WP_Error en caso de fallo.
     */
    public function send_event($eventType, $payload, $use_cache = false, $cache_expiration = 300) {
        if (empty($this->n8n_base_url)) {
            // Usamos WA_Logger en lugar de error_log directamente
            WA_Logger::log('N8n Base URL no configurada.', 'error', ['event_type' => $eventType, 'payload' => $payload]);
            return new WP_Error('n8n_config_error', 'La URL base de n8n no está configurada en los ajustes del plugin.');
        }

        // Construye la URL completa del Webhook.
        // Asumimos que el Webhook en n8n es /webhook-url/eventType.
        $url = trailingslashit($this->n8n_base_url) . sanitize_title($eventType);
        
        // Disparar evento pre-envío usando el sistema de eventos
        $this->events->trigger_event('before_n8n_event_send', [
            'event_type' => $eventType,
            'payload' => $payload,
            'url' => $url
        ]);
        
        // Verificar si debemos usar caché y si hay una respuesta en caché disponible
        if ($use_cache && !$this->is_write_operation($eventType, $payload)) {
            $cache_key = $this->generate_cache_key($eventType, $payload);
            $cached_response = $this->cache->get($cache_key);
            
            if ($cached_response !== false) {
                WA_Logger::log('Usando respuesta en caché para evento n8n.', 'info', [
                    'event_type' => $eventType,
                    'cache_key' => $cache_key
                ]);
                
                return $cached_response;
            }
        }

        $args = [
            'body'        => wp_json_encode($payload), // Usar wp_json_encode para consistencia
            'headers'     => [
                'Content-Type' => 'application/json',
            ],
            'method'      => 'POST',
            'timeout'     => 30, // Tiempo de espera en segundos
            'data_format' => 'body', // Asegura que el cuerpo se envíe como JSON
        ];

        // Añadir el token de autenticación si está configurado.
        if (!empty($this->n8n_auth_token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->n8n_auth_token;
        }

        // Añadir nonce de seguridad para prevenir CSRF (si es necesario)
        $args['headers']['X-WP-Nonce'] = wp_create_nonce('wp_whatsapp_evolution_api_nonce');

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            WA_Logger::log('Error al enviar evento a n8n.', 'error', [
                'event_type' => $eventType,
                'payload'    => $payload,
                'error'      => $response->get_error_message(),
                'url'        => $url,
            ]);
            
            // Disparar evento de error
            $this->events->trigger_event('n8n_request_error', [
                'event_type' => $eventType,
                'payload' => $payload,
                'error' => $response->get_error_message(),
                'url' => $url
            ]);
            
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body      = wp_remote_retrieve_body($response);

        if ($http_code < 200 || $http_code >= 300) {
            WA_Logger::log('N8n devolvió un error HTTP.', 'error', [
                'event_type'    => $eventType,
                'payload'       => $payload,
                'http_code'     => $http_code,
                'response_body' => $body,
                'url'           => $url,
            ]);
            
            // Disparar evento de error HTTP
            $this->events->trigger_event('n8n_http_error', [
                'event_type' => $eventType,
                'payload' => $payload,
                'http_code' => $http_code,
                'response_body' => $body,
                'url' => $url
            ]);
            
            return new WP_Error('n8n_response_error', 'Error al procesar el evento en n8n.', ['status' => $http_code, 'body' => $body]);
        }

        $parsed_body = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            WA_Logger::log('Respuesta de n8n no es un JSON válido.', 'warning', [
                'event_type'    => $eventType,
                'payload'       => $payload,
                'http_code'     => $http_code,
                'response_body' => $body,
                'url'           => $url,
            ]);
            
            // Disparar evento de respuesta inválida
            $this->events->trigger_event('n8n_invalid_response', [
                'event_type' => $eventType,
                'payload' => $payload,
                'http_code' => $http_code,
                'response_body' => $body,
                'url' => $url
            ]);
            
            // Si no es JSON, devolvemos el cuerpo crudo.
            return $body;
        }

        // Almacenar en caché si corresponde
        if ($use_cache && !$this->is_write_operation($eventType, $payload)) {
            $cache_key = $this->generate_cache_key($eventType, $payload);
            $this->cache->set($cache_key, $parsed_body, $cache_expiration);
            
            WA_Logger::log('Respuesta almacenada en caché.', 'debug', [
                'event_type' => $eventType,
                'cache_key' => $cache_key,
                'expiration' => $cache_expiration
            ]);
        }

        WA_Logger::log('Evento enviado a n8n exitosamente.', 'info', [
            'event_type'    => $eventType,
            'payload'       => $payload,
            'response_body' => $parsed_body,
            'url'           => $url,
        ]);
        
        // Disparar evento de éxito
        $this->events->trigger_event('n8n_request_success', [
            'event_type' => $eventType,
            'payload' => $payload,
            'response' => $parsed_body,
            'url' => $url
        ]);
        
        return $parsed_body;
    }
    
    /**
     * Genera una clave de caché para un evento y payload específicos.
     *
     * @param string $event_type Tipo de evento.
     * @param array  $payload Datos del evento.
     * @return string Clave única para la caché.
     */
    private function generate_cache_key($event_type, $payload) {
        // Crear un hash único basado en el tipo de evento y los datos
        // Esto permite que solicitudes idénticas obtengan la misma clave de caché
        $payload_hash = md5(wp_json_encode($payload));
        return 'event_' . sanitize_key($event_type) . '_' . $payload_hash;
    }
    
    /**
     * Determina si un evento es una operación de escritura (que modifica datos).
     * Las operaciones de escritura no deberían cachearse.
     *
     * @param string $event_type Tipo de evento.
     * @param array  $payload Datos del evento.
     * @return bool True si es una operación de escritura, false en caso contrario.
     */
    private function is_write_operation($event_type, $payload) {
        // Lista de tipos de eventos que son operaciones de escritura
        $write_operations = [
            'send_message',
            'create_instance', 
            'delete_instance',
            'logout_instance',
            'update_profile',
            'group_create',
            'group_update',
            'group_leave',
            'restart',
            'update_settings',
            // Añadir más tipos de eventos que modifican datos según sea necesario
        ];
        
        // Verificar si el tipo de evento está en la lista de operaciones de escritura
        if (in_array($event_type, $write_operations)) {
            return true;
        }
        
        // Verificar por prefijos comunes de operaciones de escritura
        $write_prefixes = ['send_', 'create_', 'update_', 'delete_', 'modify_', 'set_'];
        foreach ($write_prefixes as $prefix) {
            if (strpos($event_type, $prefix) === 0) {
                return true;
            }
        }
        
        // Permitir filtrar esta decisión
        return apply_filters('wpwea_is_write_operation', false, $event_type, $payload);
    }
    
    /**
     * Invalida la caché para un tipo de evento específico o para todos los eventos.
     *
     * @param string $event_type Tipo de evento opcional. Si no se proporciona, se invalida toda la caché.
     * @return int Número de elementos de caché invalidados.
     */
    public function invalidate_cache($event_type = '') {
        if (empty($event_type)) {
            // Invalidar toda la caché de n8n
            return $this->cache->delete_by_prefix('');
        }
        
        // Invalidar solo la caché para un tipo de evento específico
        return $this->cache->delete_by_prefix('event_' . sanitize_key($event_type));
    }
    
    /**
     * Envía un evento a un Webhook de n8n, con gestión automática de caché.
     * Esta es una versión simplificada que decide automáticamente si usar caché basándose en el tipo de evento.
     *
     * @param string $event_type Tipo de evento.
     * @param array  $payload Datos del evento.
     * @return array|WP_Error Respuesta de n8n o error.
     */
    public function send_event_with_auto_cache($event_type, $payload) {
        // Determinar automáticamente si este evento debería usar caché
        $use_cache = !$this->is_write_operation($event_type, $payload);
        
        // Eventos de solo lectura comunes que deberían cachear por periodos más largos
        $long_cache_events = ['get_status', 'get_instance_info', 'get_qr_code'];
        
        // Tiempo de caché predeterminado (5 minutos) o más largo para ciertos eventos
        $cache_expiration = in_array($event_type, $long_cache_events) ? 900 : 300; // 15 o 5 minutos
        
        return $this->send_event($event_type, $payload, $use_cache, $cache_expiration);
    }
}
