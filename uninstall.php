<?php
/**
 * EZ Translate Uninstall Script
 *
 * This file is executed when the plugin is deleted from WordPress admin.
 * It cleans up all plugin data from the database.
 *
 * @package EZTranslate
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin data on uninstall
 *
 * @since 1.0.0
 */
function ez_translate_uninstall_cleanup() {
    // Log the uninstall process
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[EZ-Translate] Uninstall: Starting cleanup process');
    }

    // Remove plugin options
    delete_option('ez_translate_languages');
    delete_option('ez_translate_activation_redirect');
    delete_option('ez_translate_version');
    delete_option('ez_translate_robots_settings');
    delete_option('ez_translate_sitemap_settings');

    // Clean up transients
    delete_transient('ez_translate_languages_cache');

    // Remove all post meta related to EZ Translate
    $meta_keys = array(
        '_ez_translate_language',
        '_ez_translate_group',
        '_ez_translate_is_landing',
        '_ez_translate_seo_title',
        '_ez_translate_seo_description',
    );

    global $wpdb;

    foreach ($meta_keys as $meta_key) {
        $wpdb->delete(
            $wpdb->postmeta,
            array('meta_key' => $meta_key),
            array('%s')
        );
    }

    // Clean up any remaining transients with our prefix
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_ez_translate_%'
        )
    );

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_ez_translate_%'
        )
    );

    // Drop custom tables
    $redirect_table = $wpdb->prefix . 'ez_translate_redirects';
    $wpdb->query("DROP TABLE IF EXISTS {$redirect_table}");

    // Clear any scheduled cron jobs
    wp_clear_scheduled_hook('ez_translate_check_redirects');

    // Log completion
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[EZ-Translate] Uninstall: Cleanup completed successfully');
    }
}

// Execute cleanup
ez_translate_uninstall_cleanup();
