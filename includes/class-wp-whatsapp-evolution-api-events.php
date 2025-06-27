<?php
/**
 * Clase para el sistema de eventos del plugin WP WhatsApp Evolution API.
 * Implementa un patrón de observador para permitir que otros plugins y temas
 * se conecten a diferentes eventos disparados por este plugin.
 *
 * @package WP_Whatsapp_Evolution_API
 * @subpackage Includes
 */

// Si este archivo es llamado directamente, abortar.
if (!defined('WPINC')) {
    die;
}

class WP_Whatsapp_Evolution_API_Events {

    /**
     * Instancia única de la clase (patrón Singleton).
     *
     * @var WP_Whatsapp_Evolution_API_Events
     */
    private static $instance = null;

    /**
     * Lista de observadores registrados para los eventos.
     *
     * @var array
     */
    private $observers = [];

    /**
     * Eventos estándar soportados por el plugin.
     *
     * @var array
     */
    private $standard_events = [
        'before_message_send',     // Antes de enviar un mensaje
        'message_sent',            // Mensaje enviado con éxito
        'message_send_error',      // Error al enviar un mensaje
        'before_qr_generation',    // Antes de generar QR
        'qr_generated',            // QR generado con éxito
        'qr_generation_error',     // Error al generar QR
        'before_status_check',     // Antes de verificar estado
        'status_check_complete',   // Verificación de estado completada
        'status_check_error',      // Error al verificar estado
        'session_connected',       // Sesión conectada
        'session_disconnected',    // Sesión desconectada
        'webhook_received',        // Webhook recibido de n8n
        'webhook_processed',       // Webhook procesado con éxito
        'webhook_error',           // Error al procesar webhook
        'cache_hit',               // Caché encontrada
        'cache_miss',              // Caché no encontrada
        'cache_set',               // Valor establecido en caché
        'cache_cleared',           // Caché limpiada
    ];

    /**
     * Constructor privado para implementar el patrón Singleton.
     */
    private function __construct() {
        // Inicializar el array de observadores para cada evento estándar
        foreach ($this->standard_events as $event) {
            $this->observers[$event] = [];
        }

        // Registramos acciones y filtros para integrar con WordPress
        $this->register_wp_hooks();
    }

    /**
     * Registra los hooks de WordPress para la integración con acciones y filtros.
     */
    private function register_wp_hooks() {
        // Permitir que los eventos también disparen acciones de WordPress
        add_action('wpwea_event_triggered', array($this, 'maybe_trigger_wp_action'), 10, 2);
        
        // Registrar la acción para desencadenar eventos desde otras partes del código
        add_action('wp_whatsapp_evolution_api_trigger_event', array($this, 'trigger_event_from_action'), 10, 2);
    }

    /**
     * Obtiene la instancia única de la clase (Singleton).
     *
     * @return WP_Whatsapp_Evolution_API_Events
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registra un observador para un evento específico.
     *
     * @param string   $event    Nombre del evento a observar.
     * @param callable $callback Función de callback que se ejecutará cuando ocurra el evento.
     * @param int      $priority Prioridad del observador (más bajo = más prioritario).
     * @return bool True si se registró correctamente, false en caso contrario.
     */
    public function add_observer($event, $callback, $priority = 10) {
        // Verificar si el evento es válido
        if (!$this->is_valid_event($event)) {
            WA_Logger::log('Intento de registrar observador para evento inválido.', 'warning', [
                'event' => $event
            ]);
            return false;
        }

        // Verificar si el callback es invocable
        if (!is_callable($callback)) {
            WA_Logger::log('Callback no invocable registrado como observador.', 'warning', [
                'event' => $event
            ]);
            return false;
        }

        // Registrar el observador con su prioridad
        $this->observers[$event][] = [
            'callback' => $callback,
            'priority' => $priority
        ];

        // Ordenar los observadores por prioridad
        $this->sort_observers($event);

        WA_Logger::log('Observador registrado con éxito.', 'debug', [
            'event' => $event,
            'priority' => $priority
        ]);

        return true;
    }

    /**
     * Elimina un observador de un evento específico.
     *
     * @param string   $event    Nombre del evento.
     * @param callable $callback Función de callback a eliminar.
     * @return bool True si se eliminó correctamente, false en caso contrario.
     */
    public function remove_observer($event, $callback) {
        if (!$this->is_valid_event($event)) {
            return false;
        }

        foreach ($this->observers[$event] as $key => $observer) {
            if ($observer['callback'] === $callback) {
                unset($this->observers[$event][$key]);
                
                WA_Logger::log('Observador eliminado con éxito.', 'debug', [
                    'event' => $event
                ]);
                
                return true;
            }
        }

        return false;
    }

    /**
     * Dispara un evento notificando a todos los observadores registrados.
     *
     * @param string $event Nombre del evento a disparar.
     * @param array  $data  Datos asociados al evento.
     * @return int Número de observadores notificados.
     */
    public function trigger_event($event, $data = []) {
        if (!$this->is_valid_event($event)) {
            WA_Logger::log('Intento de disparar evento inválido.', 'warning', [
                'event' => $event
            ]);
            return 0;
        }

        $count = 0;
        
        // Registrar el disparo del evento
        WA_Logger::log('Evento disparado.', 'debug', [
            'event' => $event,
            'data' => $data
        ]);

        // Notificar a cada observador registrado
        foreach ($this->observers[$event] as $observer) {
            try {
                call_user_func($observer['callback'], $data);
                $count++;
            } catch (Exception $e) {
                WA_Logger::log('Error al notificar a un observador.', 'error', [
                    'event' => $event,
                    'exception' => $e->getMessage()
                ]);
            }
        }

        // Disparar una acción de WordPress con el mismo evento
        do_action('wpwea_event_triggered', $event, $data);

        return $count;
    }

    /**
     * Callback para la acción de WordPress wp_whatsapp_evolution_api_trigger_event.
     * Permite disparar eventos desde otras partes del código utilizando acciones de WP.
     *
     * @param string $event Nombre del evento a disparar.
     * @param array  $data  Datos asociados al evento.
     */
    public function trigger_event_from_action($event, $data = []) {
        $this->trigger_event($event, $data);
    }

    /**
     * Verifica si un evento está registrado como válido.
     *
     * @param string $event Nombre del evento a verificar.
     * @return bool True si el evento es válido, false en caso contrario.
     */
    public function is_valid_event($event) {
        // Verificar si el evento es uno de los estándar
        $is_standard = in_array($event, $this->standard_events);
        
        // Verificar si el evento es personalizado pero ya fue registrado
        $is_registered = isset($this->observers[$event]);
        
        return $is_standard || $is_registered;
    }

    /**
     * Registra un nuevo tipo de evento personalizado.
     *
     * @param string $event Nombre del evento personalizado.
     * @return bool True si se registró correctamente, false si ya existía.
     */
    public function register_custom_event($event) {
        // Validar el nombre del evento
        if (!preg_match('/^[a-z0-9_]+$/', $event)) {
            WA_Logger::log('Nombre de evento personalizado inválido.', 'warning', [
                'event' => $event
            ]);
            return false;
        }
        
        // Verificar si el evento ya existe
        if (isset($this->observers[$event])) {
            return false;
        }
        
        // Registrar el nuevo evento
        $this->observers[$event] = [];
        
        WA_Logger::log('Evento personalizado registrado.', 'debug', [
            'event' => $event
        ]);
        
        return true;
    }

    /**
     * Obtiene todos los eventos disponibles (estándar y personalizados).
     *
     * @return array Lista de nombres de eventos.
     */
    public function get_available_events() {
        return array_keys($this->observers);
    }

    /**
     * Dispara una acción de WordPress correspondiente al evento.
     * Se usa internamente por el gancho wpwea_event_triggered.
     *
     * @param string $event Nombre del evento.
     * @param array  $data  Datos del evento.
     */
    public function maybe_trigger_wp_action($event, $data) {
        // Disparar una acción específica para este evento
        do_action("wp_whatsapp_evolution_api_{$event}", $data);
    }

    /**
     * Ordena los observadores de un evento según su prioridad.
     *
     * @param string $event Nombre del evento.
     */
    private function sort_observers($event) {
        if (!isset($this->observers[$event])) {
            return;
        }

        usort($this->observers[$event], function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
    }

    /**
     * Registra múltiples observadores de una vez.
     *
     * @param array $observers Array asociativo de eventos y callbacks.
     * @return int Número de observadores registrados con éxito.
     */
    public function add_multiple_observers($observers) {
        $count = 0;
        
        foreach ($observers as $event => $callback) {
            if ($this->add_observer($event, $callback)) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Obtiene el número de observadores registrados para un evento específico.
     *
     * @param string $event Nombre del evento.
     * @return int Número de observadores.
     */
    public function get_observer_count($event) {
        if (!$this->is_valid_event($event)) {
            return 0;
        }
        
        return count($this->observers[$event]);
    }

    /**
     * Verifica si un evento tiene observadores registrados.
     *
     * @param string $event Nombre del evento.
     * @return bool True si hay observadores, false en caso contrario.
     */
    public function has_observers($event) {
        return $this->get_observer_count($event) > 0;
    }

    /**
     * Elimina todos los observadores de un evento específico.
     *
     * @param string $event Nombre del evento.
     * @return int Número de observadores eliminados.
     */
    public function clear_observers($event) {
        if (!$this->is_valid_event($event)) {
            return 0;
        }
        
        $count = count($this->observers[$event]);
        $this->observers[$event] = [];
        
        WA_Logger::log('Observadores eliminados para evento.', 'debug', [
            'event' => $event,
            'count' => $count
        ]);
        
        return $count;
    }
}