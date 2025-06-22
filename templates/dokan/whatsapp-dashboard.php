<?php
/**
 * Dokan WhatsApp Dashboard Template.
 * Displays QR code and connection status for the vendor.
 *
 * @package WP_Whatsapp_Evolution_API
 * @subpackage Templates
 */

// Asegúrate de que el usuario es un vendedor de Dokan y está logueado.
if ( ! function_exists( 'dokan_is_vendor' ) || ! dokan_is_vendor( get_current_user_id() ) ) {
    wp_die( esc_html__( 'Acceso denegado.', 'wp-whatsapp-evolution-api' ) );
}

$vendor_id = get_current_user_id();
$session_status = get_user_meta( $vendor_id, 'dokan_whatsapp_session_status', true );
$qr_code_url = get_user_meta( $vendor_id, 'dokan_whatsapp_qr_code_url', true );

// Establece un estado por defecto si no hay ninguno.
if ( empty( $session_status ) ) {
    $session_status = 'NOT CONNECTED';
}

?>

<div class="dokan-dashboard-wrap">
    <div class="dokan-dashboard-content">
        <article class="dokan-whatsapp-area dokan-w8">
            <h1 class="entry-title"><?php esc_html_e( 'WhatsApp Integration', 'wp-whatsapp-evolution-api' ); ?></h1>

            <div class="dokan-dashboard-content-area">

                <div class="dokan-section-box">
                    <div class="dokan-section-heading">
                        <h2><i class="dashicons dashicons-whatsapp" aria-hidden="true"></i> <?php esc_html_e( 'Your WhatsApp Connection', 'wp-whatsapp-evolution-api' ); ?></h2>
                    </div>

                    <div class="dokan-form-group">
                        <label for="whatsapp_connection_status"><?php esc_html_e( 'Connection Status:', 'wp-whatsapp-evolution-api' ); ?></label>
                        <span id="whatsapp_connection_status" class="dokan-label dokan-label-<?php echo esc_attr( strtolower( $session_status ) ); ?>">
                            <?php echo esc_html( strtoupper( $session_status ) ); ?>
                        </span>
                    </div>

                    <div class="dokan-form-group">
                        <button id="get_qr_button" class="dokan-btn dokan-btn-theme"><?php esc_html_e( 'Generate New QR Code', 'wp-whatsapp-evolution-api' ); ?></button>
                        <button id="check_status_button" class="dokan-btn dokan-btn-default"><?php esc_html_e( 'Check Status', 'wp-whatsapp-evolution-api' ); ?></button>
                    </div>

                    <div class="dokan-form-group" id="qr_code_container" style="display: <?php echo ! empty( $qr_code_url ) ? 'block' : 'none'; ?>;">
                        <label><?php esc_html_e( 'Scan QR Code:', 'wp-whatsapp-evolution-api' ); ?></label>
                        <?php if ( ! empty( $qr_code_url ) && ( strpos( $qr_code_url, 'data:image' ) === 0 || strpos( $qr_code_url, 'http' ) === 0 ) ) : ?>
                            <?php if ( strpos( $qr_code_url, 'data:image' ) === 0 ) : ?>
                                <img id="qr_image" src="<?php echo esc_attr( $qr_code_url ); ?>" alt="<?php esc_attr_e('WhatsApp QR Code', 'wp-whatsapp-evolution-api'); ?>" style="max-width: 250px; height: auto;">
                            <?php else : ?>
                                <img id="qr_image" src="<?php echo esc_url( $qr_code_url ); ?>" alt="<?php esc_attr_e('WhatsApp QR Code', 'wp-whatsapp-evolution-api'); ?>" style="max-width: 250px; height: auto;">
                            <?php endif; ?>
                            <p class="description"><?php esc_html_e( 'Scan this QR code with your WhatsApp app to connect.', 'wp-whatsapp-evolution-api' ); ?></p>
                        <?php endif; ?>
                        <p id="qr_message" style="display: <?php echo empty( $qr_code_url ) ? 'block' : 'none'; ?>;"><?php esc_html_e( 'Click "Generate New QR Code" to get started.', 'wp-whatsapp-evolution-api' ); ?></p>
                        <p id="qr_loading_message" style="display:none;"><?php esc_html_e('Waiting for QR scan...', 'wp-whatsapp-evolution-api'); ?></p>
                    </div>

                    <div id="whatsapp_message_status" class="dokan-alert"></div>
                    <div class="dokan-alert dokan-alert-danger" id="whatsapp_error_message" style="display:none;"></div>

                </div><div class="dokan-section-box mt-30">
                    <div class="dokan-section-heading">
                        <h2><i class="dashicons dashicons-email-alt" aria-hidden="true"></i> <?php esc_html_e( 'Send a Test Message', 'wp-whatsapp-evolution-api' ); ?></h2>
                        <p><?php esc_html_e('Use this to send a test message from your connected WhatsApp account.', 'wp-whatsapp-evolution-api'); ?></p>
                    </div>
                    <div class="dokan-form-group">
                        <label for="test_message_to"><?php esc_html_e( 'Recipient Number (e.g., 551199999999):', 'wp-whatsapp-evolution-api' ); ?></label>
                        <input type="text" id="test_message_to" class="dokan-form-control" placeholder="<?php esc_attr_e( 'Enter number with country code', 'wp-whatsapp-evolution-api' ); ?>">
                    </div>
                    <div class="dokan-form-group">
                        <label for="test_message_content"><?php esc_html_e( 'Message:', 'wp-whatsapp-evolution-api' ); ?></label>
                        <textarea id="test_message_content" class="dokan-form-control" rows="3" placeholder="<?php esc_attr_e( 'Enter your test message', 'wp-whatsapp-evolution-api' ); ?>"></textarea>
                    </div>
                    <div class="dokan-form-group">
                        <button id="send_test_message_button" class="dokan-btn dokan-btn-primary"><?php esc_html_e( 'Send Test Message', 'wp-whatsapp-evolution-api' ); ?></button>
                    </div>
                    <div id="test_message_response"></div>
                </div>

            </div></article>
    </div>
</div>
