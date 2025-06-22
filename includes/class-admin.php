<?php
namespace WP_WhatsApp_Evolution_API;

defined('ABSPATH') || exit;

class Admin {
    private $plugin_name;
    private $version;

    public function __construct() {
        $this->plugin_name = 'wp-whatsapp-evolution-api';
        $this->version = WP_WA_EVOLUTION_API_VERSION;

        $this->init_hooks();
    }

    /**
     * Inicializa los hooks del admin
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_pages']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'show_admin_notices']);
    }

    /**
     * Añade las páginas de administración
     */
    public function add_admin_pages() {
        add_menu_page(
            __('WhatsApp Evolution API', 'wp-whatsapp-evolution-api'),
            __('WhatsApp API', 'wp-whatsapp-evolution-api'),
            'manage_options',
            $this->plugin_name,
            [$this, 'render_main_page'],
            'dashicons-whatsapp',
            80
        );

        add_submenu_page(
            $this->plugin_name,
            __('Configuración', 'wp-whatsapp-evolution-api'),
            __('Configuración', 'wp-whatsapp-evolution-api'),
            'manage_options',
            $this->plugin_name . '-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            $this->plugin_name,
            __('Registro de Mensajes', 'wp-whatsapp-evolution-api'),
            __('Registro', 'wp-whatsapp-evolution-api'),
            'manage_options',
            $this->plugin_name . '-logs',
            [$this, 'render_logs_page']
        );
    }

    /**
     * Registra las opciones de configuración
     */
    public function register_settings() {
        // Configuración principal
        register_setting(
            'wa_evolution_api_settings',
            'wp_wa_evolution_api_url',
            [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => ''
            ]
        );

        register_setting(
            'wa_evolution_api_settings',
            'wp_wa_evolution_api_token',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ]
        );

        // Configuración n8n
        register_setting(
            'wa_evolution_api_settings',
            'wp_wa_evolution_api_n8n_webhook',
            [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => ''
            ]
        );

        register_setting(
            'wa_evolution_api_settings',
            'wp_wa_evolution_api_n8n_enabled',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false
            ]
        );

        // Sección principal
        add_settings_section(
            'wa_evolution_api_main_section',
            __('Configuración de la API', 'wp-whatsapp-evolution-api'),
            [$this, 'render_main_section'],
            $this->plugin_name . '-settings'
        );

        // Sección n8n
        add_settings_section(
            'wa_evolution_api_n8n_section',
            __('Configuración n8n', 'wp-whatsapp-evolution-api'),
            [$this, 'render_n8n_section'],
            $this->plugin_name . '-settings'
        );

        // Campos principales
        add_settings_field(
            'api_url',
            __('Endpoint de la API', 'wp-whatsapp-evolution-api'),
            [$this, 'render_api_url_field'],
            $this->plugin_name . '-settings',
            'wa_evolution_api_main_section'
        );

        add_settings_field(
            'api_token',
            __('Token de Acceso', 'wp-whatsapp-evolution-api'),
            [$this, 'render_api_token_field'],
            $this->plugin_name . '-settings',
            'wa_evolution_api_main_section'
        );

        // Campos n8n
        add_settings_field(
            'n8n_enabled',
            __('Habilitar n8n', 'wp-whatsapp-evolution-api'),
            [$this, 'render_n8n_enabled_field'],
            $this->plugin_name . '-settings',
            'wa_evolution_api_n8n_section'
        );

        add_settings_field(
            'n8n_webhook',
            __('Webhook n8n', 'wp-whatsapp-evolution-api'),
            [$this, 'render_n8n_webhook_field'],
            $this->plugin_name . '-settings',
            'wa_evolution_api_n8n_section'
        );
    }

    /**
     * Renderiza la página principal
     */
    public function render_main_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes.', 'wp-whatsapp-evolution-api'));
        }

        include $this->plugin_path . 'templates/admin/main-page.php';
    }

    /**
     * Renderiza la página de configuración
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes.', 'wp-whatsapp-evolution-api'));
        }

        include $this->plugin_path . 'templates/admin/settings-page.php';
    }

    /**
     * Renderiza la página de logs
     */
    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes.', 'wp-whatsapp-evolution-api'));
        }

        include $this->plugin_path . 'templates/admin/logs-page.php';
    }

    /**
     * Carga los assets del admin
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, $this->plugin_name) === false) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . '-admin',
            plugin_dir_url(__FILE__) . '../assets/css/admin.css',
            [],
            $this->version
        );

        wp_enqueue_script(
            $this->plugin_name . '-admin',
            plugin_dir_url(__FILE__) . '../assets/js/admin.js',
            ['jquery'],
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name . '-admin',
            'waEvolutionAdmin',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wa_evolution_admin_nonce'),
                'i18n' => [
                    'confirm_reset' => __('¿Estás seguro de querer resetear la configuración?', 'wp-whatsapp-evolution-api')
                ]
            ]
        );
    }

    /**************************
     * Métodos para renderizar campos
     **************************/

    public function render_main_section() {
        echo '<p>' . __('Configura los parámetros principales de conexión con WhatsApp Evolution API.', 'wp-whatsapp-evolution-api') . '</p>';
    }

    public function render_n8n_section() {
        echo '<p>' . __('Configura la integración con n8n para el enrutamiento de mensajes.', 'wp-whatsapp-evolution-api') . '</p>';
    }

    public function render_api_url_field() {
        $value = get_option('wp_wa_evolution_api_url');
        echo '<input type="url" name="wp_wa_evolution_api_url" value="' . esc_url($value) . '" class="regular-text" placeholder="https://api.evolution-api.com/instance123/send">';
        echo '<p class="description">' . __('URL completa proporcionada por Evolution API.', 'wp-whatsapp-evolution-api') . '</p>';
    }

    public function render_api_token_field() {
        $value = get_option('wp_wa_evolution_api_token');
        echo '<input type="password" name="wp_wa_evolution_api_token" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">' . __('Token de autenticación Bearer.', 'wp-whatsapp-evolution-api') . '</p>';
    }

    public function render_n8n_enabled_field() {
        $value = get_option('wp_wa_evolution_api_n8n_enabled');
        echo '<label><input type="checkbox" name="wp_wa_evolution_api_n8n_enabled" value="1" ' . checked(1, $value, false) . '> ';
        echo __('Usar n8n como intermediario', 'wp-whatsapp-evolution-api') . '</label>';
    }

    public function render_n8n_webhook_field() {
        $value = get_option('wp_wa_evolution_api_n8n_webhook');
        $disabled = !get_option('wp_wa_evolution_api_n8n_enabled') ? 'disabled' : '';
        
        echo '<input type="url" name="wp_wa_evolution_api_n8n_webhook" value="' . esc_url($value) . '" class="regular-text" ' . $disabled . ' placeholder="https://tun8n.instance.com/webhook">';
        echo '<p class="description">' . __('URL del webhook en tu instancia n8n.', 'wp-whatsapp-evolution-api') . '</p>';
    }

    /**
     * Muestra notificaciones en el admin
     */
    public function show_admin_notices() {
        if (!get_option('wp_wa_evolution_api_url')) {
            echo '<div class="notice notice-warning"><p>';
            printf(
                __('Por favor configura el endpoint de WhatsApp Evolution API en %s.', 'wp-whatsapp-evolution-api'),
                '<a href="' . admin_url('admin.php?page=wp-whatsapp-evolution-api-settings') . '">' . __('la página de configuración', 'wp-whatsapp-evolution-api') . '</a>'
            );
            echo '</p></div>';
        }
    }
}