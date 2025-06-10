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
    // Log the uninstall process (removed error_log for production compliance)

    // Remove plugin options
    delete_option('ez_translate_languages');
    delete_option('ez_translate_activation_redirect');
    delete_option('ez_translate_version');
    delete_option('ez_translate_db_version');
    delete_option('ez_translate_robots_settings');
    delete_option('ez_translate_sitemap_settings');
    delete_option('ez_translate_catchall_settings');
    delete_option('ez_translate_api_settings');

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
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        // Direct database query is necessary for plugin uninstall cleanup
        $wpdb->delete(
            $wpdb->postmeta,
            array('meta_key' => $meta_key),
            array('%s')
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
    }

    // Clean up any remaining transients with our prefix
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
    // Direct database query is necessary for plugin uninstall cleanup
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_ez_translate_%'
        )
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
    // Direct database query is necessary for plugin uninstall cleanup
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_ez_translate_%'
        )
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

    // Drop custom tables
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
    // Direct database query is necessary for custom table cleanup during uninstall
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "ez_translate_redirects");
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange

    // Clear any scheduled cron jobs
    wp_clear_scheduled_hook('ez_translate_check_redirects');

    // Cleanup completed (removed error_log for production compliance)
}

// Execute cleanup
ez_translate_uninstall_cleanup();
