<?php
/**
 * Dokan Vendor Dashboard - WhatsApp Integration Tab Template
 *
 * @package WP_WhatsApp_Evolution_API
 * @subpackage WP_WhatsApp_Evolution_API/dokan
 * @since 1.0.0-beta
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$vendor_id = dokan_get_current_seller_id();

// Retrieve vendor's current WhatsApp settings
$whatsapp_settings = $this->get_vendor_whatsapp_settings( $vendor_id );
$connection_status = $whatsapp_settings['connection_status'];
$qr_code_data      = $whatsapp_settings['qr_code_data'];
$whatsapp_number   = $whatsapp_settings['whatsapp_number'];
$instance_name     = $whatsapp_settings['instance_name'];
$connection_info   = $whatsapp_settings['connection_info'];

?>
<div class="dokan-dash-sidebar-left">
    <?php dokan_get_template_part( 'dashboard/sidebar-nav' ); ?>
</div>

<div class="dokan-dash-content">
    <div class="dokan-dashboard-content-area">
        <div class="dokan-whatsapp-integration">
            <h2 class="dokan-whatsapp-header"><?php esc_html_e( 'WhatsApp Integration', WWEA_DOMAIN ); ?></h2>
            <p><?php esc_html_e( 'Connect your WhatsApp business account to receive messages and manage your orders directly. This integration uses n8n as a secure intermediary.', WWEA_DOMAIN ); ?></p>

            <div class="dokan-form-group dokan-whatsapp-number-config">
                <label for="wwea_vendor_whatsapp_number" class="dokan-w3 control-label"><?php esc_html_e( 'Your WhatsApp Number (with country code)', WWEA_DOMAIN ); ?></label>
                <div class="dokan-w5">
                    <input type="text" id="wwea_vendor_whatsapp_number" class="dokan-form-control" value="<?php echo esc_attr( $whatsapp_number ); ?>" placeholder="<?php esc_attr_e( 'e.g., +1234567890', WWEA_DOMAIN ); ?>">
                    <p class="description"><?php esc_html_e( 'Enter the WhatsApp number (including country code, e.g., +1234567890) you want to connect to this store instance.', WWEA_DOMAIN ); ?></p>
                    <button id="wwea_save_whatsapp_number" class="dokan-btn dokan-btn-sm dokan-btn-theme"><?php esc_html_e( 'Save Number', WWEA_DOMAIN ); ?></button>
                    <span id="wwea_number_save_status" class="dokan-message"></span>
                </div>
            </div>

            <hr>

            <h3 class="dokan-whatsapp-status-header"><?php esc_html_e( 'Connection Status', WWEA_DOMAIN ); ?>: <span id="wwea-whatsapp-status" class="wwea-status-<?php echo esc_attr( $connection_status ); ?>"><?php echo esc_html( ucfirst( $connection_status ) ); ?></span></h3>

            <div id="wwea-whatsapp-qr-area">
                <?php if ( 'connected' !== $connection_status ) : ?>
                    <p class="wwea-qr-instruction"><?php esc_html_e( 'Scan the QR code below with your WhatsApp mobile app to connect your account. Make sure your WhatsApp app is logged out before scanning.', WWEA_DOMAIN ); ?></p>
                    <button id="wwea-generate-qr" class="dokan-btn dokan-btn-sm dokan-btn-theme"><?php esc_html_e( 'Connect WhatsApp / Generate QR Code', WWEA_DOMAIN ); ?></button>
                    <p id="wwea-qr-loading" class="dokan-info" style="display: none;"><?php esc_html_e( 'Generating QR code, please wait...', WWEA_DOMAIN ); ?></p>
                    <div id="wwea-qr-code-display" class="wwea-qr-code-container">
                        <?php if ( ! empty( $qr_code_data ) ) : ?>
                            <img src="<?php echo esc_attr( $qr_code_data ); ?>" alt="<?php esc_attr_e( 'WhatsApp QR Code', WWEA_DOMAIN ); ?>">
                        <?php else : ?>
                            <p class="wwea-no-qr"><?php esc_html_e( 'No QR code available. Click "Connect WhatsApp / Generate QR Code" to get one.', WWEA_DOMAIN ); ?></p>
                        <?php endif; ?>
                    </div>
                    <p id="wwea-qr-error" class="dokan-error" style="display: none;"></p>
                <?php else : ?>
                    <p class="wwea-connected-message dokan-success"><?php esc_html_e( 'Your WhatsApp is connected!', WWEA_DOMAIN ); ?></p>
                    <button id="wwea-refresh-status" class="dokan-btn dokan-btn-sm dokan-btn-default"><?php esc_html_e( 'Refresh Status', WWEA_DOMAIN ); ?></button>
                    <?php endif; ?>
            </div>

            <div id="wwea-connection-details" style="margin-top: 20px;">
                <h4><?php esc_html_e( 'Basic Connection Details', WWEA_DOMAIN ); ?></h4>
                <p><strong><?php esc_html_e( 'WhatsApp Instance Name:', WWEA_DOMAIN ); ?></strong> <span id="wwea-display-instance-name"><?php echo esc_html( $instance_name ); ?></span></p>
                <p><strong><?php esc_html_e( 'Connected Number:', WWEA_DOMAIN ); ?></strong> <span id="wwea-display-whatsapp-number"><?php echo ! empty( $whatsapp_number ) ? esc_html( $whatsapp_number ) : __( 'Not saved yet', WWEA_DOMAIN ); ?></span></p>

                <div id="wwea-additional-info">
                    <?php if ( ! empty( $connection_info ) && is_array( $connection_info ) ) : ?>
                        <?php foreach ( $connection_info as $key => $value ) : ?>
                            <p><strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ); ?>:</strong>
                                <?php echo is_array( $value ) ? esc_html( json_encode( $value ) ) : esc_html( $value ); ?>
                            </p>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p><small><?php esc_html_e( 'No additional connection details available yet.', WWEA_DOMAIN ); ?></small></p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
