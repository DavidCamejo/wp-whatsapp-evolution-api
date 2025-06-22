<?php
/**
 * Plugin Name: WP WhatsApp Evolution API (Dokan Vendor Integration with n8n)
 * Plugin URI:  https://github.com/DavidCamejo/wp-whatsapp-evolution-api/
 * Description: Integra WhatsApp con vendedores de Dokan utilizando n8n como intermediario para Evolution API.
 * Version:     1.0.0
 * Author:      David Camejo (Refactorizado por Gemini)
 * Author URI:  https://davidcamejo.com/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-whatsapp-evolution-api
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * La clase principal del plugin.
 */
final class WP_Whatsapp_Evolution_API {

    /**
     * Instancia única del plugin.
     *
     * @var WP_Whatsapp_Evolution_API
     */
    protected static $instance = null;

    /**
     * Constructor privado para asegurar una instancia única (Singleton).
     */
    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->hooks();
    }

    /**
     * Define las constantes del plugin.
     */
    private function define_constants() {
        define( 'WP_WHATSAPP_EVOLUTION_API_VERSION', '1.0.0' );
        define( 'WP_WHATSAPP_EVOLUTION_API_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'WP_WHATSAPP_EVOLUTION_API_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    }

    /**
     * Incluye los archivos necesarios.
     */
    private function includes() {
        require_once WP_WHATSAPP_EVOLUTION_API_PLUGIN_DIR . 'includes/class-n8n-webhook-dispatcher.php';
        require_once WP_WHATSAPP_EVOLUTION_API_PLUGIN_DIR . 'includes/class-wp-whatsapp-evolution-api-public.php';

        if ( is_admin() ) {
            require_once WP_WHATSAPP_EVOLUTION_API_PLUGIN_DIR . 'admin/class-wp-whatsapp-evolution-api-admin.php';
        }
    }

    /**
     * Registra los hooks de WordPress.
     */
    private function hooks() {
        // Inicializar la parte pública del plugin
        new WP_Whatsapp_Evolution_API_Public();

        // Inicializar la parte administrativa del plugin
        if ( is_admin() ) {
            new WP_Whatsapp_Evolution_API_Admin();
        }

        // Hooks para la integración con Dokan Dashboard
        add_filter( 'dokan_get_dashboard_nav', [ $this, 'add_whatsapp_menu_to_dokan_dashboard' ] );
        add_action( 'dokan_load_custom_template', [ $this, 'load_whatsapp_dokan_template' ], 10, 1 );

        // Hook para activar el plugin (por si hay lógica de DB, etc.)
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        // Hook para desactivar el plugin
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    /**
     * Obtiene la única instancia de la clase.
     *
     * @return WP_Whatsapp_Evolution_API
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Lógica a ejecutar cuando el plugin se activa.
     */
    public function activate() {
        // No hay lógica de DB compleja por ahora, pero se podría añadir aquí.
        // Por ejemplo, asegurar que los user_meta para vendedores estén limpios o inicializados.
    }

    /**
     * Lógica a ejecutar cuando el plugin se desactiva.
     */
    public function deactivate() {
        // Opcional: limpiar user_meta o transients relacionados con WhatsApp si es necesario.
    }

    /**
     * Añade un nuevo elemento al menú del dashboard de Dokan para WhatsApp.
     *
     * @param array $urls Array de elementos del menú existentes.
     * @return array Array modificado de elementos del menú.
     */
    public function add_whatsapp_menu_to_dokan_dashboard( $urls ) {
        // Asegúrate de que Dokan esté activo y que el usuario actual sea un vendedor.
        if ( function_exists( 'dokan_is_vendor' ) && dokan_is_vendor( get_current_user_id() ) ) {
            $urls['whatsapp'] = [
                'title'      => esc_html__( 'WhatsApp', 'wp-whatsapp-evolution-api' ),
                'icon'       => '<i class="fa fa-whatsapp"></i>', // Requiere Font Awesome o un ícono personalizado
                'url'        => dokan_get_navigation_url( 'whatsapp' ),
                'pos'        => 70, // Ajusta la posición del menú según sea necesario
                'active'     => ( get_query_var( 'whatsapp' ) == 'whatsapp' ) ? true : false,
            ];
        }
        return $urls;
    }

    /**
     * Carga el archivo de plantilla personalizado para el menú de WhatsApp en Dokan.
     *
     * @param array $query_vars Las variables de consulta actuales del dashboard de Dokan.
     */
    public function load_whatsapp_dokan_template( $query_vars ) {
        if ( isset( $query_vars['whatsapp'] ) && $query_vars['whatsapp'] == 'whatsapp' ) {
            // Asegúrate de que solo los vendedores puedan ver esta página.
            if ( ! function_exists( 'dokan_is_vendor' ) || ! dokan_is_vendor( get_current_user_id() ) ) {
                wp_die( esc_html__( 'Acceso denegado.', 'wp-whatsapp-evolution-api' ) );
            }

            // Encola los scripts y estilos necesarios para la página del dashboard de WhatsApp.
            wp_enqueue_script( 'wpwea-dokan-whatsapp-script', WP_WHATSAPP_EVOLUTION_API_PLUGIN_URL . 'assets/js/dokan-whatsapp.js', ['jquery', 'wp-api'], WP_WHATSAPP_EVOLUTION_API_VERSION, true );
            wp_localize_script( 'wpwea-dokan-whatsapp-script', 'wpweaDokan', [
                'rest_url' => get_rest_url( null, 'wp-whatsapp-evolution-api/v1/vendor/' ),
                'nonce'    => wp_create_nonce( 'wp_rest' ), // Nonce para autenticar solicitudes REST
                'i18n'     => [
                    'sendingMessage'      => esc_html__( 'Sending message...', 'wp-whatsapp-evolution-api' ),
                    'messageSentSuccess'  => esc_html__( 'Message sent successfully!', 'wp-whatsapp-evolution-api' ),
                    'failedToSendMessage' => esc_html__( 'Failed to send message:', 'wp-whatsapp-evolution-api' ),
                    'error'               => esc_html__( 'Error:', 'wp-whatsapp-evolution-api' ),
                    'requiredFields'      => esc_html__( 'Please enter both recipient number and message.', 'wp-whatsapp-evolution-api' ),
                    'generatingQr'        => esc_html__( 'Generating QR code...', 'wp-whatsapp-evolution-api' ),
                    'qrGeneratedScan'     => esc_html__( 'QR code generated. Scan with your WhatsApp app.', 'wp-whatsapp-evolution-api' ),
                    'failedToGenerateQr'  => esc_html__( 'Failed to generate QR code.', 'wp-whatsapp-evolution-api' ),
                    'checkingStatus'      => esc_html__( 'Checking status...', 'wp-whatsapp-evolution-api' ),
                    'failedToGetStatus'   => esc_html__( 'Failed to get session status.', 'wp-whatsapp-evolution-api' ),
                    'notConnectedGenerateQr' => esc_html__( 'Not connected. Generate a new QR code to connect.', 'wp-whatsapp-evolution-api' ),
                ],
            ] );
            wp_enqueue_style( 'wpwea-dokan-whatsapp-style', WP_WHATSAPP_EVOLUTION_API_PLUGIN_URL . 'assets/css/dokan-whatsapp.css', [], WP_WHATSAPP_EVOLUTION_API_VERSION );

            // Carga la plantilla para el contenido del dashboard de WhatsApp.
            // Esto reemplazará el contenido principal del dashboard para esta URL.
            require_once WP_WHATSAPP_EVOLUTION_API_PLUGIN_DIR . 'templates/dokan/whatsapp-dashboard.php';
        }
    }
}

/**
 * Inicia el plugin.
 *
 * @return WP_Whatsapp_Evolution_API
 */
function wp_whatsapp_evolution_api_run() {
    return WP_Whatsapp_Evolution_API::get_instance();
}

// Inicia el plugin al cargar WordPress.
add_action( 'plugins_loaded', 'wp_whatsapp_evolution_api_run' );
