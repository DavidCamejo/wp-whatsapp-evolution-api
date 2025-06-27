<?php
/**
 * Clase para la validación y formateo avanzado de números de teléfono para WhatsApp.
 * Proporciona métodos para verificar y normalizar números de teléfono antes de 
 * enviarlos a la API de WhatsApp.
 *
 * @package WP_Whatsapp_Evolution_API
 * @subpackage Includes
 */

// Si este archivo es llamado directamente, abortar.
if (!defined('WPINC')) {
    die;
}

class WP_Whatsapp_Evolution_API_Phone_Validator {

    /**
     * Lista de códigos de país y sus longitudes de números (sin el código).
     * Sirve como referencia para validar mejor los números.
     *
     * @var array
     */
    private $country_codes = [
        '1' => [10], // EEUU/Canadá (10 dígitos sin código)
        '52' => [10], // México
        '55' => [10, 11], // Brasil (puede tener 10 u 11 dígitos)
        '34' => [9], // España
        '54' => [10], // Argentina
        '57' => [10], // Colombia
        '56' => [9], // Chile
        '51' => [9], // Perú
        '58' => [10], // Venezuela
        '593' => [9], // Ecuador
        '502' => [8], // Guatemala
        '503' => [8], // El Salvador
        '504' => [8], // Honduras
        '505' => [8], // Nicaragua
        '506' => [8], // Costa Rica
        '507' => [8], // Panamá
        '591' => [8], // Bolivia
        '595' => [9], // Paraguay
        '598' => [8], // Uruguay
        '39' => [9, 10], // Italia
        '44' => [10], // Reino Unido
        '33' => [9], // Francia
        '49' => [10, 11], // Alemania
        '351' => [9], // Portugal
        '91' => [10], // India
        '86' => [11], // China
        '81' => [10], // Japón
        '82' => [9, 10], // Corea del Sur
        '7' => [10], // Rusia
        '61' => [9], // Australia
        '64' => [9], // Nueva Zelanda
        '27' => [9], // Sudáfrica
    ];

    /**
     * Constructor de la clase.
     */
    public function __construct() {
        // Inicialización si es necesaria
    }

    /**
     * Valida si un número de teléfono es válido para WhatsApp.
     *
     * @param string $phone_number El número de teléfono a validar.
     * @return bool True si el número es válido, false en caso contrario.
     */
    public function is_valid($phone_number) {
        // Eliminar todos los caracteres no numéricos excepto el signo +
        $clean_number = $this->clean_number($phone_number);
        
        // Verificar que hay al menos 7 dígitos (número mínimo razonable para un teléfono)
        if (strlen($clean_number) < 7) {
            WA_Logger::log('Número de teléfono demasiado corto.', 'warning', [
                'phone_number' => $phone_number,
                'clean_number' => $clean_number
            ]);
            return false;
        }
        
        // Verificar que no tenga más de 15 dígitos (estándar E.164)
        if (strlen($clean_number) > 15) {
            WA_Logger::log('Número de teléfono demasiado largo.', 'warning', [
                'phone_number' => $phone_number,
                'clean_number' => $clean_number
            ]);
            return false;
        }
        
        // Si el número comienza con +, verificar que el resto sean solo dígitos
        if (substr($clean_number, 0, 1) === '+') {
            if (!ctype_digit(substr($clean_number, 1))) {
                WA_Logger::log('Número de teléfono contiene caracteres no válidos.', 'warning', [
                    'phone_number' => $phone_number,
                    'clean_number' => $clean_number
                ]);
                return false;
            }
        } else {
            // Si no comienza con +, verificar que sean solo dígitos
            if (!ctype_digit($clean_number)) {
                WA_Logger::log('Número de teléfono contiene caracteres no válidos.', 'warning', [
                    'phone_number' => $phone_number,
                    'clean_number' => $clean_number
                ]);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Valida si un número es válido específicamente para WhatsApp.
     * Realiza validaciones más estrictas basadas en códigos de país.
     * 
     * @param string $phone_number El número de teléfono a validar.
     * @return bool True si el número es válido para WhatsApp, false en caso contrario.
     */
    public function is_valid_for_whatsapp($phone_number) {
        // Primero realizar la validación básica
        if (!$this->is_valid($phone_number)) {
            return false;
        }
        
        // Obtener el número limpio sin el signo +
        $clean_number = $this->clean_number($phone_number);
        $clean_number = ltrim($clean_number, '+');
        
        // Tratar de identificar el código de país
        $country_code = $this->detect_country_code($clean_number);
        
        // Si no pudimos identificar el código de país, asumimos que es válido
        // ya que pasó la validación básica
        if (!$country_code) {
            return true;
        }
        
        // Obtener la longitud del número sin el código de país
        $number_length = strlen($clean_number) - strlen($country_code);
        
        // Verificar si la longitud del número (sin código) es válida para ese país
        if (isset($this->country_codes[$country_code])) {
            $valid_lengths = $this->country_codes[$country_code];
            if (!in_array($number_length, $valid_lengths)) {
                WA_Logger::log('Longitud de número inválida para el código de país.', 'warning', [
                    'phone_number' => $phone_number,
                    'country_code' => $country_code,
                    'number_length' => $number_length,
                    'valid_lengths' => implode(', ', $valid_lengths)
                ]);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Limpia un número de teléfono eliminando caracteres no deseados.
     *
     * @param string $phone_number El número de teléfono a limpiar.
     * @return string El número de teléfono limpio.
     */
    public function clean_number($phone_number) {
        // Eliminar espacios, guiones, paréntesis y otros caracteres no deseados
        // pero mantener el signo + si está presente al principio
        $has_plus = substr($phone_number, 0, 1) === '+';
        $clean_number = preg_replace('/[^0-9]/', '', $phone_number);
        
        if ($has_plus) {
            $clean_number = '+' . $clean_number;
        }
        
        return $clean_number;
    }
    
    /**
     * Formatea un número de teléfono para WhatsApp.
     * Asegura que el número esté en formato internacional E.164.
     *
     * @param string $phone_number El número de teléfono a formatear.
     * @param string $default_country_code Código de país a usar si no se especifica (sin +).
     * @return string El número formateado para WhatsApp.
     */
    public function format($phone_number, $default_country_code = '34') {
        // Limpiar el número
        $clean_number = $this->clean_number($phone_number);
        
        // Si el número ya comienza con +, asumimos que ya está en formato internacional
        if (substr($clean_number, 0, 1) === '+') {
            return ltrim($clean_number, '+');
        }
        
        // Si el número comienza con 00 (formato internacional alternativo), reemplazar por +
        if (substr($clean_number, 0, 2) === '00') {
            return substr($clean_number, 2);
        }
        
        // Intentar detectar si el número ya incluye un código de país
        $country_code = $this->detect_country_code($clean_number);
        if ($country_code) {
            return $clean_number;
        }
        
        // Si no se detectó un código de país, agregar el predeterminado
        return $default_country_code . $clean_number;
    }
    
    /**
     * Detecta el código de país de un número de teléfono.
     *
     * @param string $phone_number El número de teléfono (sin +).
     * @return string|false El código de país detectado o false si no se pudo detectar.
     */
    public function detect_country_code($phone_number) {
        // Eliminar el signo + si está presente
        $phone_number = ltrim($phone_number, '+');
        
        // Ordenar códigos de país por longitud (primero los más largos)
        $codes = array_keys($this->country_codes);
        usort($codes, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        // Verificar si el número comienza con alguno de los códigos de país
        foreach ($codes as $code) {
            if (substr($phone_number, 0, strlen($code)) === $code) {
                return $code;
            }
        }
        
        return false;
    }
    
    /**
     * Obtiene el código de país a partir de un número de teléfono.
     *
     * @param string $phone_number El número de teléfono.
     * @return string El código de país (sin +) o cadena vacía si no se pudo detectar.
     */
    public function get_country_code($phone_number) {
        $clean_number = $this->clean_number($phone_number);
        $clean_number = ltrim($clean_number, '+');
        
        return $this->detect_country_code($clean_number) ?: '';
    }
    
    /**
     * Verifica si un número de teléfono pertenece a un país específico.
     *
     * @param string $phone_number El número de teléfono.
     * @param string $country_code El código de país a verificar.
     * @return bool True si el número pertenece al país especificado, false en caso contrario.
     */
    public function is_from_country($phone_number, $country_code) {
        $detected_code = $this->get_country_code($phone_number);
        
        return $detected_code === $country_code;
    }
    
    /**
     * Agrega el prefijo internacional si no lo tiene.
     *
     * @param string $phone_number El número de teléfono.
     * @param string $default_country_code Código de país por defecto si no se detecta.
     * @return string El número con prefijo internacional.
     */
    public function add_international_prefix($phone_number, $default_country_code = '34') {
        $formatted = $this->format($phone_number, $default_country_code);
        
        // Asegurarse de que tenga el signo +
        if (substr($formatted, 0, 1) !== '+') {
            return '+' . $formatted;
        }
        
        return $formatted;
    }
    
    /**
     * Obtiene una versión amigable para mostrar del número.
     * Ejemplo: +34 612 345 678
     *
     * @param string $phone_number El número de teléfono.
     * @return string Versión formateada para mostrar.
     */
    public function get_display_number($phone_number) {
        // Primero formatear al estándar E.164
        $formatted = $this->add_international_prefix($phone_number);
        
        // Detectar el código de país para el formato específico
        $country_code = $this->get_country_code($formatted);
        
        // Si no se puede detectar, devolver el formato estándar E.164
        if (!$country_code) {
            return $formatted;
        }
        
        // Eliminar el código de país para formatear el resto
        $national_number = substr($formatted, strlen($country_code) + 1);
        
        // Formatear según el país (solo algunos ejemplos)
        switch ($country_code) {
            case '1': // EEUU/Canadá
                return '+' . $country_code . ' ' . substr($national_number, 0, 3) . '-' . 
                       substr($national_number, 3, 3) . '-' . substr($national_number, 6);
                
            case '34': // España
                return '+' . $country_code . ' ' . substr($national_number, 0, 3) . ' ' . 
                       substr($national_number, 3, 3) . ' ' . substr($national_number, 6);
                
            default:
                // Formato genérico: agrupar en bloques de 3 dígitos
                $chunks = str_split($national_number, 3);
                return '+' . $country_code . ' ' . implode(' ', $chunks);
        }
    }
    
    /**
     * Valida un lote de números de teléfono.
     *
     * @param array $phone_numbers Lista de números de teléfono a validar.
     * @return array Resultados de la validación con índices de éxito y error.
     */
    public function validate_batch($phone_numbers) {
        $results = [
            'success' => [],
            'error' => []
        ];
        
        foreach ($phone_numbers as $index => $phone_number) {
            if ($this->is_valid_for_whatsapp($phone_number)) {
                $results['success'][$index] = [
                    'original' => $phone_number,
                    'formatted' => $this->format($phone_number)
                ];
            } else {
                $results['error'][$index] = [
                    'original' => $phone_number,
                    'reason' => 'Invalid phone number'
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Registra un nuevo código de país con sus longitudes válidas.
     *
     * @param string $country_code Código de país (sin +).
     * @param array $valid_lengths Lista de longitudes válidas para el número (sin el código).
     * @return bool True si se registró correctamente.
     */
    public function register_country_code($country_code, $valid_lengths) {
        if (!is_array($valid_lengths) || empty($valid_lengths)) {
            return false;
        }
        
        $this->country_codes[$country_code] = $valid_lengths;
        return true;
    }
}