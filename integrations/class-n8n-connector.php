<?php
namespace WP_WhatsApp_Evolution_API\Integrations;

defined('ABSPATH') || exit;

class N8N_Connector {
    private $webhook_url;

    public function __construct() {
        $this->webhook_url = get_option('wp_wa_evolution_api_n8n_webhook');

        add_filter('wa_evolution_api_before_send', [$this, 'maybe_route_via_n8n'], 10, 2);
    }

    /**
     * Rutea mensajes a través de n8n si corresponde
     */
    public function maybe_route_via_n8n($message_data, $is_n8n) {
        if (!$is_n8n || empty($this->webhook_url)) {
            return $message_data;
        }

        return [
            'send_via' => 'n8n',
            'payload' => [
                'phone' => $message_data['phone'],
                'message' => $message_data['message'],
                'template' => $message_data['template'] ?? null,
                'media_url' => $message_data['media']['url'] ?? null
            ]
        ];
    }

    /**
     * Envía datos a n8n
     */
    public function send_to_n8n($data) {
        $response = wp_remote_post($this->webhook_url, [
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WP-Plugin' => 'WhatsApp Evolution API'
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            error_log('[WhatsApp API] Error n8n: ' . $response->get_error_message());
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}