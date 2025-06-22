<?php
namespace WP_WhatsApp_Evolution_API;

defined('ABSPATH') || exit;

class Core {
    private $version;
    private $plugin_path;

    public function __construct() {
        $this->version = WP_WA_EVOLUTION_API_VERSION;
        $this->plugin_path = plugin_dir_path(dirname(__FILE__));

        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Inicializa todos los hooks principales
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_shortcodes']);
    }

    /**
     * Carga las dependencias del plugin
     */
    private function load_dependencies() {
        // Carga las clases principales
        require_once $this->plugin_path . 'includes/class-admin.php';
        require_once $this->plugin_path . 'includes/class-api-handler.php';
        
        // Carga integraciones
        require_once $this->plugin_path . 'integrations/class-dokan.php';
        require_once $this->plugin_path . 'integrations/class-n8n-connector.php';

        new Admin();
        new API_Handler();
        new Integrations\Dokan_Integration();
        new Integrations\N8N_Connector();
    }

    /**
     * Registra los shortcodes del plugin
     */
    public function register_shortcodes() {
        add_shortcode('wa_evolution_button', [$this, 'render_wa_button']);
        add_shortcode('wa_evolution_form', [$this, 'render_wa_form']);
    }

    /**
     * Renderiza el shortcode del botón
     */
    public function render_wa_button($atts) {
        $atts = shortcode_atts([
            'phone' => '',
            'text' => __('Enviar mensaje', 'wp-whatsapp-evolution-api'),
            'class' => '',
            'via_n8n' => 'yes'
        ], $atts);

        wp_enqueue_script('wa-evolution-frontend');

        return sprintf(
            '<button class="wa-button %s" data-phone="%s" data-via-n8n="%s">%s</button>',
            esc_attr($atts['class']),
            esc_attr($atts['phone']),
            esc_attr($atts['via_n8n']),
            esc_html($atts['text'])
        );
    }

    /**
     * Activa el plugin
     */
    public function activate() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        update_option('wp_wa_evolution_api_version', $this->version);

        // Crear tablas personalizadas si es necesario
        $this->create_tables();
    }

    /**
     * Crea tablas personalizadas
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'wa_evolution_logs';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            message text NOT NULL,
            status varchar(20) NOT NULL,
            sent_via varchar(10) NOT NULL DEFAULT 'direct',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Desactiva el plugin
     */
    public function deactivate() {
        // Limpiar eventos programados
        wp_clear_scheduled_hook('wa_evolution_daily_cleanup');
    }

    /**
     * Carga el dominio de texto para traducciones
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-whatsapp-evolution-api',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
}