<?php
namespace WP_WhatsApp_Evolution_API;

class Api_Client {
    private $api_url;
    private $api_token;

    public function __construct() {
        $this->api_url = get_option('wp_wa_evolution_api_url');
        $this->api_token = get_option('wp_wa_evolution_api_token');

        add_action('wp_ajax_wa_evolution_send', [$this, 'handle_ajax_request']);
    }

    public function handle_ajax_request() {
        try {
            check_ajax_referer('wa_evolution_nonce', 'security');

            if (!current_user_can('edit_posts')) {
                throw new \Exception(__('Permisos insuficientes', 'wp-whatsapp-evolution-api'), 403);
            }

            $phone = sanitize_text_field($_POST['phone'] ?? '');
            $message = sanitize_textarea_field($_POST['message'] ?? '');

            $response = $this->send_message($phone, $message);

            wp_send_json_success($response);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage(), $e->getCode());
        }
    }

    public function send_message($phone, $message) {
        $args = [
            'body' => json_encode([
                'number' => $phone,
                'text' => $message,
                'delayMessage' => 1200 // Ejemplo de parámetro adicional
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_token
            ],
            'timeout' => 20,
            'sslverify' => false // Solo para desarrollo
        ];

        $response = wp_remote_post($this->api_url, $args);

        if (is_wp_error($response)) {
            throw new \Exception(
                __('Error en la API: ', 'wp-whatsapp-evolution-api') . $response->get_error_message(),
                500
            );
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}