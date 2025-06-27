<?php
/**
 * Clase para gestionar las funcionalidades de seguridad del plugin WP WhatsApp Evolution API.
 * Proporciona métodos para encriptar/desencriptar datos, manejar opciones seguras y otras
 * funcionalidades relacionadas con la seguridad.
 *
 * @package WP_Whatsapp_Evolution_API
 * @subpackage Includes
 */

// Si este archivo es llamado directamente, abortar.
if (!defined('WPINC')) {
    die;
}

class WP_Whatsapp_Evolution_API_Security {

    /**
     * Prefijo para las claves de encriptación en las opciones de WordPress.
     *
     * @var string
     */
    private $encryption_key_prefix = 'wpwea_encryption_key_';

    /**
     * Vector de inicialización para la encriptación.
     *
     * @var string
     */
    private $encryption_iv_prefix = 'wpwea_encryption_iv_';

    /**
     * Método de encriptación a utilizar.
     *
     * @var string
     */
    private $encryption_method = 'aes-256-cbc';

    /**
     * Constructor de la clase.
     */
    public function __construct() {
        // Asegurar que tenemos keys adecuados para la encriptación
        $this->ensure_encryption_keys();
    }

    /**
     * Asegura que existan las claves de encriptación necesarias.
     * Si no existen, las genera y guarda en opciones.
     */
    private function ensure_encryption_keys() {
        // Verificar si tenemos la clave maestra
        $master_key = get_option('wpwea_master_encryption_key', false);

        if ($master_key === false) {
            // Generar y guardar una clave maestra única
            $master_key = bin2hex(openssl_random_pseudo_bytes(32)); // 256 bits
            update_option('wpwea_master_encryption_key', $master_key);

            // Registrar la generación de clave maestra
            WA_Logger::log('Generada nueva clave maestra de encriptación.', 'info');
        }
    }

    /**
     * Genera o recupera una clave de encriptación específica para un contexto.
     *
     * @param string $context Contexto para el cual generar la clave.
     * @return string Clave de encriptación.
     */
    private function get_encryption_key($context) {
        $key_option = $this->encryption_key_prefix . sanitize_key($context);
        $key = get_option($key_option, false);

        if ($key === false) {
            // Generar una clave derivada de la clave maestra para este contexto específico
            $master_key = get_option('wpwea_master_encryption_key');
            $context_salt = wp_salt('auth');
            
            // Usar HKDF para derivar una clave segura del master_key 
            $key = hash_hmac('sha256', $context . $context_salt, $master_key);
            
            update_option($key_option, $key);
            
            // Registrar generación de clave
            WA_Logger::log('Generada clave de encriptación para contexto específico.', 'info', [
                'context' => $context
            ]);
        }

        return $key;
    }

    /**
     * Genera o recupera un vector de inicialización para un contexto.
     *
     * @param string $context Contexto para el cual generar el IV.
     * @return string Vector de inicialización.
     */
    private function get_encryption_iv($context) {
        $iv_option = $this->encryption_iv_prefix . sanitize_key($context);
        $iv = get_option($iv_option, false);

        if ($iv === false) {
            // Generar un IV aleatorio para este contexto
            $iv_length = openssl_cipher_iv_length($this->encryption_method);
            $iv = bin2hex(openssl_random_pseudo_bytes($iv_length));
            
            update_option($iv_option, $iv);
            
            // Registrar generación de IV
            WA_Logger::log('Generado vector de inicialización para contexto específico.', 'info', [
                'context' => $context
            ]);
        }

        // IV debe ser binario para su uso en openssl
        return hex2bin(substr($iv, 0, openssl_cipher_iv_length($this->encryption_method) * 2));
    }

    /**
     * Encripta datos sensibles.
     *
     * @param string $context Contexto para el cual se encriptan los datos.
     * @param string $data Datos a encriptar.
     * @return string|false Datos encriptados o false si hay error.
     */
    public function encrypt($context, $data) {
        if (empty($data)) {
            return '';
        }

        try {
            $key = $this->get_encryption_key($context);
            $iv = $this->get_encryption_iv($context);
            
            // Encriptar los datos
            $encrypted = openssl_encrypt(
                $data,
                $this->encryption_method,
                $key,
                0,
                $iv
            );

            if ($encrypted === false) {
                WA_Logger::log('Error al encriptar datos.', 'error', [
                    'context' => $context,
                    'openssl_error' => openssl_error_string()
                ]);
                return false;
            }
            
            // Añadir un prefijo para identificar que está encriptado
            return 'enc:' . $encrypted;
        } catch (Exception $e) {
            WA_Logger::log('Excepción al encriptar datos.', 'error', [
                'context' => $context,
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Desencripta datos sensibles.
     *
     * @param string $context Contexto para el cual se desencriptan los datos.
     * @param string $encrypted_data Datos encriptados.
     * @return string|false Datos desencriptados o false si hay error.
     */
    public function decrypt($context, $encrypted_data) {
        if (empty($encrypted_data)) {
            return '';
        }

        // Verificar si los datos están encriptados
        if (strpos($encrypted_data, 'enc:') !== 0) {
            // No está encriptado, devolver como está
            return $encrypted_data;
        }

        try {
            // Eliminar el prefijo para obtener los datos encriptados
            $encrypted_data = substr($encrypted_data, 4);
            
            $key = $this->get_encryption_key($context);
            $iv = $this->get_encryption_iv($context);
            
            // Desencriptar los datos
            $decrypted = openssl_decrypt(
                $encrypted_data,
                $this->encryption_method,
                $key,
                0,
                $iv
            );

            if ($decrypted === false) {
                WA_Logger::log('Error al desencriptar datos.', 'error', [
                    'context' => $context,
                    'openssl_error' => openssl_error_string()
                ]);
                return false;
            }
            
            return $decrypted;
        } catch (Exception $e) {
            WA_Logger::log('Excepción al desencriptar datos.', 'error', [
                'context' => $context,
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Guarda una opción en WordPress de forma segura (encriptada).
     *
     * @param string $option_name Nombre de la opción.
     * @param mixed $option_value Valor a guardar.
     * @param bool $autoload Si debe autocargar la opción.
     * @return bool True si se guardó correctamente, false en caso contrario.
     */
    public function update_secure_option($option_name, $option_value, $autoload = true) {
        $context = 'option_' . $option_name;
        $encrypted = $this->encrypt($context, $option_value);
        
        if ($encrypted === false) {
            return false;
        }
        
        return update_option($option_name, $encrypted, $autoload);
    }

    /**
     * Recupera una opción encriptada de WordPress.
     *
     * @param string $option_name Nombre de la opción.
     * @param mixed $default Valor por defecto si la opción no existe.
     * @return mixed Valor desencriptado de la opción o el valor por defecto.
     */
    public function get_secure_option($option_name, $default = '') {
        $encrypted = get_option($option_name, null);
        
        // Si la opción no existe, devolver el valor por defecto
        if ($encrypted === null) {
            return $default;
        }
        
        $context = 'option_' . $option_name;
        $decrypted = $this->decrypt($context, $encrypted);
        
        // Si no se pudo desencriptar, devolver el valor por defecto
        if ($decrypted === false) {
            return $default;
        }
        
        return $decrypted;
    }

    /**
     * Genera un hash seguro para un valor.
     *
     * @param string $value Valor a hashear.
     * @param string $salt Sal adicional para el hash.
     * @return string Hash generado.
     */
    public function generate_hash($value, $salt = '') {
        $wp_salt = wp_salt('auth');
        return hash('sha256', $value . $salt . $wp_salt);
    }

    /**
     * Verifica un hash contra un valor.
     *
     * @param string $value Valor a verificar.
     * @param string $hash Hash a comparar.
     * @param string $salt Sal adicional usada originalmente.
     * @return bool True si el hash coincide, false en caso contrario.
     */
    public function verify_hash($value, $hash, $salt = '') {
        $generated_hash = $this->generate_hash($value, $salt);
        return hash_equals($generated_hash, $hash);
    }

    /**
     * Genera un token seguro para uso en formularios o APIs.
     *
     * @param string $action Acción asociada al token.
     * @param int $expiration Tiempo de expiración en segundos.
     * @return array Token generado con información asociada.
     */
    public function generate_token($action, $expiration = 3600) {
        $token = bin2hex(openssl_random_pseudo_bytes(16));
        $expiry_time = time() + $expiration;
        
        $token_data = [
            'token' => $token,
            'action' => $action,
            'expiry' => $expiry_time
        ];
        
        // Almacenar el token en transients para validación posterior
        set_transient('wpwea_token_' . $token, $token_data, $expiration);
        
        return $token_data;
    }

    /**
     * Valida un token previamente generado.
     *
     * @param string $token Token a validar.
     * @param string $action Acción esperada.
     * @return bool True si el token es válido, false en caso contrario.
     */
    public function validate_token($token, $action) {
        $token_data = get_transient('wpwea_token_' . $token);
        
        if (!$token_data) {
            return false;
        }
        
        // Verificar acción y expiración
        if ($token_data['action'] !== $action || $token_data['expiry'] < time()) {
            delete_transient('wpwea_token_' . $token);
            return false;
        }
        
        return true;
    }

    /**
     * Invalida un token para que no pueda ser usado nuevamente.
     *
     * @param string $token Token a invalidar.
     * @return bool True si se invalidó correctamente.
     */
    public function invalidate_token($token) {
        return delete_transient('wpwea_token_' . $token);
    }

    /**
     * Sanitiza un número de teléfono para evitar inyecciones.
     *
     * @param string $phone_number Número de teléfono a sanitizar.
     * @return string Número de teléfono sanitizado.
     */
    public function sanitize_phone_number($phone_number) {
        // Eliminar todos los caracteres no numéricos excepto +
        return preg_replace('/[^\d+]/', '', $phone_number);
    }

    /**
     * Sanitiza una ruta para evitar traversal de directorios y otras vulnerabilidades.
     *
     * @param string $path Ruta a sanitizar.
     * @return string Ruta sanitizada.
     */
    public function sanitize_path($path) {
        // Eliminar secuencias de caracteres peligrosas
        $path = str_replace('..', '', $path);
        $path = str_replace('../', '', $path);
        $path = str_replace('./', '', $path);
        
        // Asegurar que no hay comandos ni caracteres especiales
        $path = preg_replace('/[;|&`\'"]/', '', $path);
        
        return $path;
    }
}