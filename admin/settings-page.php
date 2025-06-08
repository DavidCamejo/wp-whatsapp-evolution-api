<div class="wrap">
    <h1><?php esc_html_e( 'WP WhatsApp Evolution API Settings', WWEA_DOMAIN ); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'wwea_settings_group' ); // Outputs nonce, action, and option_page fields
        do_settings_sections( 'wwea-settings' ); // Outputs settings sections and fields
        submit_button(); // Outputs a submit button
        ?>
    </form>
</div>
