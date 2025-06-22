<?php
/**
 * WhatsApp Evolution API - Uninstaller
 * 
 * @package WhatsAppEvolutionAPI
 * @version 2.0.0
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN') || !current_user_can('delete_plugins')) {
    exit;
}

global $wpdb;

// Verificar si se debe realizar limpieza
$clean_uninstall = get_option('wp_wa_evolution_api_clean_uninstall');

if (!$clean_uninstall) {
    return;
}

// ======================
// 1. ELIMINAR OPCIONES
// ======================
$options = [
    'wp_wa_evolution_api_version',
    'wp_wa_evolution_api_url',
    'wp_wa_evolution_api_token',
    'wp_wa_evolution_api_clean_uninstall',
    'wp_wa_evolution_api_last_used'
];

// Eliminar opciones en single site y multisite
foreach ($options as $option) {
    delete_option($option);
    delete_site_option($option);
}

// ======================
// 2. ELIMINAR TRANSIENTES
// ======================
$transients = $wpdb->get_col(
    "SELECT option_name 
     FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_wa_evolution_%' 
     OR option_name LIKE '_transient_timeout_wa_evolution_%'"
);

foreach ($transients as $transient) {
    $name = str_replace('_transient_', '', $transient);
    delete_transient($name);
}

// ======================
// 3. ELIMINAR TABLAS PERSONALIZADAS
// ======================
$tables = [
    "{$wpdb->prefix}wa_evolution_logs",    // Ejemplo de tabla de logs
    "{$wpdb->prefix}wa_evolution_queue"   // Ejemplo de tabla de cola
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// ======================
// 4. LIMPIAR CRON JOBS
// ======================
$cron_events = [
    'wa_evolution_api_daily_cleanup',
    'wa_evolution_api_hourly_check'
];

foreach ($cron_events as $event) {
    wp_clear_scheduled_hook($event);
}

// ======================
// 5. LIMPIAR METADATA
// ======================
// Ejemplo: Eliminar metadatos de posts
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} 
     WHERE meta_key LIKE '_wa_evolution_%'"
);

// Ejemplo: Eliminar metadatos de usuarios
$wpdb->query(
    "DELETE FROM {$wpdb->usermeta} 
     WHERE meta_key LIKE 'wa_evolution_%'"
);
