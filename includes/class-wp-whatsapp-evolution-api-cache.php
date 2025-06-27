<?php
/**
 * Clase para manejar el sistema de caché del plugin WP WhatsApp Evolution API.
 * Proporciona métodos para guardar, recuperar y gestionar datos en caché 
 * con el fin de optimizar las llamadas a la API y reducir la carga del servidor.
 *
 * @package WP_Whatsapp_Evolution_API
 * @subpackage Includes
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_Whatsapp_Evolution_API_Cache {

    /**
     * Prefijo para las claves de caché.
     *
     * @var string
     */
    private $cache_prefix;

    /**
     * Tiempo de expiración predeterminado en segundos.
     *
     * @var int
     */
    private $default_expiration;

    /**
     * Grupo de transients para la caché.
     *
     * @var string
     */
    private $transient_group = 'wpwea_cache';

    /**
     * Constructor de la clase.
     *
     * @param string $prefix Prefijo para las claves de caché.
     * @param int    $expiration Tiempo de expiración predeterminado en segundos.
     */
    public function __construct($prefix = 'wpwea_', $expiration = 3600) {
        $this->cache_prefix = sanitize_key($prefix);
        $this->default_expiration = absint($expiration);

        // Registrar acciones de limpieza de caché
        add_action('wp_whatsapp_evolution_api_cleanup_cache', array($this, 'cleanup_expired_cache'));
        
        // Programar limpieza periódica si no está ya programada
        if (!wp_next_scheduled('wp_whatsapp_evolution_api_cleanup_cache')) {
            wp_schedule_event(time(), 'daily', 'wp_whatsapp_evolution_api_cleanup_cache');
        }
    }

    /**
     * Genera una clave de caché válida.
     *
     * @param string $key Clave base.
     * @return string Clave de caché completa con prefijo.
     */
    private function generate_cache_key($key) {
        $safe_key = sanitize_key($key);
        return $this->cache_prefix . $safe_key;
    }

    /**
     * Guarda un valor en la caché.
     *
     * @param string $key Clave de caché.
     * @param mixed  $value Valor a guardar.
     * @param int    $expiration Tiempo de expiración en segundos (opcional).
     * @return bool Verdadero si se almacenó correctamente, falso en caso contrario.
     */
    public function set($key, $value, $expiration = null) {
        if ($expiration === null) {
            $expiration = $this->default_expiration;
        }
        
        $cache_key = $this->generate_cache_key($key);
        
        // Guardar valor en la caché
        $result = set_transient($cache_key, $value, $expiration);
        
        // Registrar la clave en el grupo de caché para facilitar la gestión
        $this->register_cache_key($cache_key, $expiration);
        
        if ($result) {
            WA_Logger::log('Valor almacenado en caché.', 'debug', [
                'cache_key' => $cache_key,
                'expiration' => $expiration
            ]);
        } else {
            WA_Logger::log('Error al guardar en caché.', 'warning', [
                'cache_key' => $cache_key
            ]);
        }
        
        return $result;
    }

    /**
     * Recupera un valor de la caché.
     *
     * @param string $key Clave de caché.
     * @param mixed  $default Valor por defecto si la clave no existe.
     * @return mixed Valor almacenado o el valor por defecto.
     */
    public function get($key, $default = false) {
        $cache_key = $this->generate_cache_key($key);
        $value = get_transient($cache_key);
        
        if ($value === false) {
            WA_Logger::log('Caché no encontrada, devolviendo valor por defecto.', 'debug', [
                'cache_key' => $cache_key
            ]);
            return $default;
        }
        
        WA_Logger::log('Valor recuperado de caché.', 'debug', [
            'cache_key' => $cache_key
        ]);
        
        return $value;
    }

    /**
     * Elimina un valor de la caché.
     *
     * @param string $key Clave de caché.
     * @return bool Verdadero si se eliminó correctamente, falso en caso contrario.
     */
    public function delete($key) {
        $cache_key = $this->generate_cache_key($key);
        $result = delete_transient($cache_key);
        
        // Eliminar el registro de la clave en el grupo
        $this->unregister_cache_key($cache_key);
        
        WA_Logger::log('Eliminada entrada de caché.', 'debug', [
            'cache_key' => $cache_key,
            'success' => $result ? 'sí' : 'no'
        ]);
        
        return $result;
    }

    /**
     * Elimina todos los valores de caché que comienzan con un prefijo específico.
     *
     * @param string $prefix Prefijo de las claves a eliminar.
     * @return int Número de entradas eliminadas.
     */
    public function delete_by_prefix($prefix) {
        global $wpdb;
        $count = 0;
        
        // El prefijo completo a buscar en la base de datos
        $search_prefix = '_transient_' . $this->generate_cache_key($prefix);
        
        // Buscar claves de transient que coincidan con el prefijo
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $search_prefix . '%'
            )
        );
        
        if ($transients) {
            foreach ($transients as $transient) {
                // Extraer el nombre real del transient (sin _transient_)
                $transient_name = str_replace('_transient_', '', $transient->option_name);
                
                if (delete_transient($transient_name)) {
                    $count++;
                    
                    // Eliminar el registro de la clave en el grupo
                    $this->unregister_cache_key($transient_name);
                }
            }
        }
        
        WA_Logger::log('Eliminadas entradas de caché por prefijo.', 'info', [
            'prefix' => $prefix,
            'count' => $count
        ]);
        
        return $count;
    }

    /**
     * Verifica si una clave existe en la caché.
     *
     * @param string $key Clave de caché.
     * @return bool Verdadero si existe, falso en caso contrario.
     */
    public function exists($key) {
        $cache_key = $this->generate_cache_key($key);
        return get_transient($cache_key) !== false;
    }

    /**
     * Actualiza el valor de una clave en caché solo si ya existe.
     *
     * @param string $key Clave de caché.
     * @param mixed  $value Nuevo valor.
     * @param int    $expiration Tiempo de expiración en segundos (opcional).
     * @return bool Verdadero si se actualizó, falso si no existía o no se pudo actualizar.
     */
    public function update_if_exists($key, $value, $expiration = null) {
        if (!$this->exists($key)) {
            return false;
        }
        
        return $this->set($key, $value, $expiration);
    }

    /**
     * Obtiene un valor de la caché o lo establece si no existe.
     *
     * @param string   $key Clave de caché.
     * @param callable $callback Función para generar el valor si no existe.
     * @param int      $expiration Tiempo de expiración en segundos (opcional).
     * @return mixed Valor de la caché o resultado del callback.
     */
    public function remember($key, $callback, $expiration = null) {
        $cache_key = $this->generate_cache_key($key);
        $cached = $this->get($key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Si no está en caché, ejecutar callback y almacenar resultado
        $value = call_user_func($callback);
        $this->set($key, $value, $expiration);
        
        return $value;
    }

    /**
     * Registra una clave de caché en el grupo para facilitar la limpieza.
     *
     * @param string $cache_key Clave de caché completa.
     * @param int    $expiration Tiempo de expiración.
     */
    private function register_cache_key($cache_key, $expiration) {
        $registry = get_option($this->transient_group, []);
        
        $registry[$cache_key] = [
            'expires' => time() + $expiration,
            'created' => time()
        ];
        
        update_option($this->transient_group, $registry);
    }

    /**
     * Elimina una clave de caché del registro.
     *
     * @param string $cache_key Clave de caché completa.
     */
    private function unregister_cache_key($cache_key) {
        $registry = get_option($this->transient_group, []);
        
        if (isset($registry[$cache_key])) {
            unset($registry[$cache_key]);
            update_option($this->transient_group, $registry);
        }
    }

    /**
     * Limpia las entradas de caché expiradas según el registro.
     * Esta función es llamada por el cron programado.
     */
    public function cleanup_expired_cache() {
        $registry = get_option($this->transient_group, []);
        $now = time();
        $cleaned = 0;
        
        foreach ($registry as $cache_key => $info) {
            // Si la caché ha expirado y aún existe, eliminarla
            if ($info['expires'] < $now) {
                $key = str_replace($this->cache_prefix, '', $cache_key);
                if ($this->delete($key)) {
                    $cleaned++;
                }
            }
        }
        
        WA_Logger::log('Limpieza programada de caché completada.', 'info', [
            'entradas_limpiadas' => $cleaned
        ]);
        
        return $cleaned;
    }

    /**
     * Limpia toda la caché del plugin.
     *
     * @return int Número de entradas eliminadas.
     */
    public function flush_all() {
        $registry = get_option($this->transient_group, []);
        $count = 0;
        
        foreach ($registry as $cache_key => $info) {
            $key = str_replace($this->cache_prefix, '', $cache_key);
            if ($this->delete($key)) {
                $count++;
            }
        }
        
        // Reiniciar el registro
        update_option($this->transient_group, []);
        
        WA_Logger::log('Caché del plugin vaciada completamente.', 'info', [
            'entradas_eliminadas' => $count
        ]);
        
        return $count;
    }

    /**
     * Obtiene estadísticas sobre la caché actual.
     *
     * @return array Estadísticas de la caché.
     */
    public function get_stats() {
        $registry = get_option($this->transient_group, []);
        $now = time();
        $total = count($registry);
        $expired = 0;
        
        foreach ($registry as $info) {
            if ($info['expires'] < $now) {
                $expired++;
            }
        }
        
        $stats = [
            'total_entries' => $total,
            'expired_entries' => $expired,
            'active_entries' => $total - $expired,
            'cache_size_estimate' => $this->estimate_cache_size(),
            'last_cleanup' => get_option($this->transient_group . '_last_cleanup', 'never')
        ];
        
        return $stats;
    }

    /**
     * Estima el tamaño de la caché en bytes.
     *
     * @return int Tamaño estimado en bytes.
     */
    private function estimate_cache_size() {
        global $wpdb;
        
        $search_prefix = '_transient_' . $this->cache_prefix . '%';
        
        $size_query = $wpdb->prepare(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
            $search_prefix
        );
        
        $size = $wpdb->get_var($size_query);
        
        return is_null($size) ? 0 : (int) $size;
    }

    /**
     * Destructor de la clase.
     * Se asegura de guardar cualquier cambio pendiente en el registro.
     */
    public function __destruct() {
        // Realizar cualquier limpieza necesaria antes de que la instancia sea destruida
    }
}