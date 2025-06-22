<?php
namespace WP_WhatsApp_Evolution_API\Integrations;

defined('ABSPATH') || exit;

class Dokan_Integration {
    public function __construct() {
        // 1. Notificar al vendedor cuando se crea un pedido
        add_action('woocommerce_order_status_processing', [$this, 'notify_seller_new_order'], 10, 2);

        // 2. Notificar al cliente cuando el vendedor actualiza el pedido
        add_action('dokan_order_status_changed', [$this, 'notify_customer_status_update'], 10, 4);
    }

    /**
     * Envía WhatsApp al vendedor cuando recibe un pedido
     */
    public function notify_seller_new_order($order_id, $order) {
        $vendor_id = dokan_get_seller_id_by_order($order_id);
        $vendor_data = get_userdata($vendor_id);
        $vendor_phone = get_user_meta($vendor_id, 'billing_phone', true);

        if (!$vendor_phone) return;

        $message = sprintf(
            __("¡Nuevo pedido #%s!\nCliente: %s\nTotal: %s\nVer pedido: %s", 'wp-whatsapp-evolution-api'),
            $order->get_order_number(),
            $order->get_billing_first_name(),
            $order->get_formatted_order_total(),
            dokan_get_navigation_url('orders')
        );

        do_action('wa_evolution_send', [
            'phone' => $vendor_phone,
            'message' => $message,
            'via_n8n' => true  // Flag para usar n8n
        ]);
    }

    /**
     * Notifica al cliente sobre actualizaciones de pedido
     */
    public function notify_customer_status_update($order_id, $status_from, $status_to, $order) {
        $customer_phone = $order->get_billing_phone();
        $vendor_name = dokan_get_vendor_by_order($order_id)->get_shop_name();

        $message = sprintf(
            __("Tu pedido #%s está ahora: %s\nVendedor: %s\nGracias por tu compra!", 'wp-whatsapp-evolution-api'),
            $order->get_order_number(),
            wc_get_order_status_name($status_to),
            $vendor_name
        );

        do_action('wa_evolution_send', [
            'phone' => $customer_phone,
            'message' => $message,
            'via_n8n' => true
        ]);
    }
}