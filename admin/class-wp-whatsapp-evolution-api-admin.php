<?php
/**
 * Clase para manejar la interfaz de administración del plugin.
 *
 * @package WP_Whatsapp_Evolution_API
 * @subpackage Admin
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_Whatsapp_Evolution_API_Admin {

    /**
     * Constructor de la clase.
     * Registra los hooks para añadir el menú de administración y los ajustes.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Añade la página de ajustes del plugin al menú de administración.
     */
    public function add_plugin_admin_menu() {
        add_options_page(
            esc_html__( 'WP WhatsApp Evolution API Settings', 'wp-whatsapp-evolution-api' ), // Título de la página
            esc_html__( 'WP WhatsApp Evolution API', 'wp-whatsapp-evolution-api' ), // Título del menú
            'manage_options', // Capacidad requerida para acceder
            'wp-whatsapp-evolution-api', // Slug del menú
            [ $this, 'display_settings_page' ] // Función callback para mostrar el contenido de la página
        );
    }

    /**
     * Registra los ajustes del plugin en WordPress.
     */
    public function register_settings() {
        // Instancia de seguridad para manejar datos sensibles
        $security = new WP_Whatsapp_Evolution_API_Security();
        
        // Registrar la opción para la URL base de n8n
        register_setting(
            'wp_whatsapp_evolution_api_group', // Nombre del grupo de ajustes
            'wp_whatsapp_evolution_api_n8n_base_url', // Nombre de la opción en la base de datos
            [
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw', // Sanitiza la URL para seguridad
                'default'           => '',
                'show_in_rest'      => false, // No exponer en la API REST
            ]
        );

        // Registrar la opción para el token de autenticación de n8n (encriptada)
        register_setting(
            'wp_whatsapp_evolution_api_group',
            'wp_whatsapp_evolution_api_n8n_auth_token',
            [
                'type'              => 'string',
                'sanitize_callback' => function($token) use ($security) {
                    // Sanitizar y luego encriptar el token para almacenamiento seguro
                    $sanitized = sanitize_text_field($token);
                    return $security->encrypt('n8n_auth_token', $sanitized);
                },
                'default'           => '',
                'show_in_rest'      => false,
            ]
        );

        // Añadir una sección a la página de ajustes
        add_settings_section(
            'wp_whatsapp_evolution_api_n8n_section', // ID de la sección
            esc_html__( 'Configuración de n8n Webhooks', 'wp-whatsapp-evolution-api' ), // Título de la sección
            [ $this, 'n8n_section_callback' ], // Función callback para el contenido de la sección
            'wp-whatsapp-evolution-api' // Página a la que pertenece la sección
        );

        // Añadir un campo para la URL base de n8n
        add_settings_field(
            'n8n_base_url_field', // ID del campo
            esc_html__( 'URL Base de n8n Webhook', 'wp-whatsapp-evolution-api' ), // Título del campo
            [ $this, 'n8n_base_url_callback' ], // Función callback para renderizar el campo
            'wp-whatsapp-evolution-api', // Página a la que pertenece el campo
            'wp_whatsapp_evolution_api_n8n_section' // Sección a la que pertenece el campo
        );

        // Añadir un campo para el token de autenticación de n8n
        add_settings_field(
            'n8n_auth_token_field',
            esc_html__( 'Token de Autenticación n8n (opcional)', 'wp-whatsapp-evolution-api' ),
            [ $this, 'n8n_auth_token_callback' ],
            'wp-whatsapp-evolution-api',
            'wp_whatsapp_evolution_api_n8n_section'
        );

        // Registrar la opción para eliminar datos al desinstalar
        register_setting(
            'wp_whatsapp_evolution_api_group',
            'wp_whatsapp_evolution_api_delete_data_on_uninstall',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field', // 'yes' o 'no'
                'default'           => 'no',
                'show_in_rest'      => false,
            ]
        );

        // Añadir una sección para las opciones de desinstalación (opcional, o añadirlo a la sección existente)
        add_settings_section(
            'wp_whatsapp_evolution_api_uninstall_section', // Nuevo ID de sección
            esc_html__( 'Opciones de Desinstalación', 'wp-whatsapp-evolution-api' ), // Título de la sección
            [ $this, 'uninstall_section_callback' ], // Función callback
            'wp-whatsapp-evolution-api' // Página
        );

        // Añadir el campo para la opción de eliminar datos
        add_settings_field(
            'delete_data_on_uninstall_field', // ID del campo
            esc_html__( 'Eliminar datos al desinstalar', 'wp-whatsapp-evolution-api' ), // Título
            [ $this, 'delete_data_on_uninstall_callback' ], // Callback
            'wp-whatsapp-evolution-api', // Página
            'wp_whatsapp_evolution_api_uninstall_section' // Sección
        );
    }

    /**
     * Contenido descriptivo para la sección de n8n.
     */
    public function n8n_section_callback() {
        echo '<p>' . esc_html__( 'Configura la URL base para tus Webhooks de n8n. Por ejemplo: ', 'wp-whatsapp-evolution-api' ) . '<code>https://your-n8n-instance.com/webhook/</code></p>';
        echo '<p>' . esc_html__( 'El plugin añadirá el tipo de evento al final de esta URL (ej: ', 'wp-whatsapp-evolution-api' ) . '<code>.../webhook/qr_generation</code>' . esc_html__( ').', 'wp-whatsapp-evolution-api' ) . '</p>';
    }

    /**
     * Renderiza el campo de entrada para la URL base de n8n.
     */
    public function n8n_base_url_callback() {
        $n8n_base_url = get_option( 'wp_whatsapp_evolution_api_n8n_base_url', '' );
        ?>
        <input type="url" name="wp_whatsapp_evolution_api_n8n_base_url" value="<?php echo esc_attr( $n8n_base_url ); ?>" class="regular-text" placeholder="https://your-n8n-instance.com/webhook/" />
        <p class="description"><?php esc_html_e( 'Asegúrate de que la URL termine con una barra (/).', 'wp-whatsapp-evolution-api' ); ?></p>
        <?php
    }

    /**
     * Renderiza el campo de entrada para el token de autenticación de n8n.
     */
    public function n8n_auth_token_callback() {
        // Usar la clase de seguridad para obtener el token desencriptado
        $security = new WP_Whatsapp_Evolution_API_Security();
        $n8n_auth_token = $security->get_secure_option('wp_whatsapp_evolution_api_n8n_auth_token', '');
        ?>
        <input type="text" name="wp_whatsapp_evolution_api_n8n_auth_token" value="<?php echo esc_attr( $n8n_auth_token ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Opcional: tu token de seguridad de n8n', 'wp-whatsapp-evolution-api' ); ?>" />
        <p class="description"><?php esc_html_e( 'Si tu webhook de n8n requiere un token de seguridad (ej. para autenticación Bearer), ingrésalo aquí. Se almacena de forma encriptada para mayor seguridad.', 'wp-whatsapp-evolution-api' ); ?></p>
        <?php
    }

    /**
     * Muestra la página completa de ajustes del plugin.
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Ajustes de WP WhatsApp Evolution API', 'wp-whatsapp-evolution-api' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wp_whatsapp_evolution_api_group' ); // Agrupa los ajustes
                do_settings_sections( 'wp-whatsapp-evolution-api' ); // Muestra todas las secciones y campos registrados para esta página
                submit_button(); // Botón de guardar cambios
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Contenido descriptivo para la sección de desinstalación.
     */
    public function uninstall_section_callback() {
        echo '<p>' . esc_html__( 'Configura cómo el plugin debe limpiar sus datos cuando se desinstale.', 'wp-whatsapp-evolution-api' ) . '</p>';
    }

    /**
     * Renderiza el campo de entrada para la opción de eliminar datos al desinstalar.
     */
    public function delete_data_on_uninstall_callback() {
        $delete_data = get_option( 'wp_whatsapp_evolution_api_delete_data_on_uninstall', 'no' );
        ?>
        <label for="wp_whatsapp_evolution_api_delete_data_on_uninstall">
            <input type="checkbox" name="wp_whatsapp_evolution_api_delete_data_on_uninstall" id="wp_whatsapp_evolution_api_delete_data_on_uninstall" value="yes" <?php checked( 'yes', $delete_data ); ?> />
            <?php esc_html_e( 'Sí, eliminar todos los datos del plugin (opciones y datos de vendedores) al desinstalar.', 'wp-whatsapp-evolution-api' ); ?>
        </label>
        <p class="description"><?php esc_html_e( '¡Advertencia! Esta acción es irreversible. Todos los ajustes y datos de conexión de WhatsApp de los vendedores se eliminarán permanentemente.', 'wp-whatsapp-evolution-api' ); ?></p>
        <?php
    }
}
