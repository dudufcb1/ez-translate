<?php
/**
 * Catch-All Redirect Handler for EZ Translate
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use EZTranslate\Logger;

/**
 * Catch-All Redirect Handler class
 *
 * Handles fallback redirects for URLs that don't have specific redirects configured
 *
 * @since 1.0.0
 */
class CatchAllHandler {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
        Logger::info('CatchAllHandler initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Hook into template_redirect to catch 404s before they're displayed
        add_action('template_redirect', array($this, 'handle_404_redirect'), 1);
        
        Logger::info('CatchAllHandler hooks initialized');
    }

    /**
     * Handle 404 redirects with catch-all logic
     *
     * @since 1.0.0
     */
    public function handle_404_redirect() {
        // Only handle 404 errors
        if (!is_404()) {
            return;
        }

        // Get catch-all settings
        $settings = get_option('ez_translate_catchall_settings', array());
        
        // Check if catch-all is enabled
        if (empty($settings['enabled'])) {
            return;
        }

        $current_url = $this->get_current_url();
        
        Logger::info('CatchAll: Processing 404 for URL', array('url' => $current_url));

        // Check if this URL already has a specific redirect
        if ($this->has_specific_redirect($current_url)) {
            Logger::info('CatchAll: URL has specific redirect, skipping catch-all', array('url' => $current_url));
            return;
        }

        // Check if URL should be excluded from catch-all
        if ($this->should_exclude_url($current_url, $settings)) {
            Logger::info('CatchAll: URL excluded from catch-all', array('url' => $current_url));
            return;
        }

        // Get destination URL
        $destination_url = $this->get_destination_url($settings);
        
        if (!$destination_url) {
            Logger::error('CatchAll: No valid destination URL configured');
            return;
        }

        // Prevent infinite redirects
        if ($this->would_cause_infinite_redirect($current_url, $destination_url)) {
            Logger::error('CatchAll: Would cause infinite redirect', array(
                'current' => $current_url,
                'destination' => $destination_url
            ));
            return;
        }

        // Perform the redirect
        $redirect_type = intval($settings['redirect_type']);

        Logger::info('CatchAll: Performing redirect', array(
            'from' => $current_url,
            'to' => $destination_url,
            'type' => $redirect_type
        ));

        wp_redirect($destination_url, $redirect_type);
        exit;
    }

    /**
     * Get current URL
     *
     * @return string Current URL
     * @since 1.0.0
     */
    private function get_current_url() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * Check if URL has a specific redirect configured
     *
     * @param string $url URL to check
     * @return bool True if specific redirect exists
     * @since 1.0.0
     */
    private function has_specific_redirect($url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ez_translate_redirects';

        $redirect = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE old_url = %s LIMIT 1",
            $url
        ));

        return !empty($redirect);
    }

    /**
     * Check if URL should be excluded from catch-all
     *
     * @param string $url URL to check
     * @param array $settings Catch-all settings
     * @return bool True if URL should be excluded
     * @since 1.0.0
     */
    private function should_exclude_url($url, $settings) {
        // Default exclusions
        $default_exclusions = array(
            '/wp-admin/',
            '/wp-login.php',
            '/wp-content/',
            '/wp-includes/',
            '/xmlrpc.php',
            '/robots.txt',
            '/sitemap',
            '.xml',
            '.json',
            '.css',
            '.js',
            '.png',
            '.jpg',
            '.jpeg',
            '.gif',
            '.ico',
            '.svg',
            '.woff',
            '.woff2',
            '.ttf',
            '.eot'
        );

        // Check default exclusions
        foreach ($default_exclusions as $exclusion) {
            if (strpos($url, $exclusion) !== false) {
                return true;
            }
        }

        // Check custom exclusions from settings
        if (!empty($settings['exclude_patterns'])) {
            foreach ($settings['exclude_patterns'] as $pattern) {
                if (strpos($url, $pattern) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get destination URL based on settings
     *
     * @param array $settings Catch-all settings
     * @return string|false Destination URL or false if invalid
     * @since 1.0.0
     */
    private function get_destination_url($settings) {
        switch ($settings['destination_type']) {
            case 'page':
                if (!empty($settings['destination_page_id'])) {
                    $page_url = get_permalink($settings['destination_page_id']);
                    if ($page_url && $page_url !== false) {
                        return $page_url;
                    }
                }
                break;

            case 'url':
                if (!empty($settings['destination_url'])) {
                    return $settings['destination_url'];
                }
                break;

            case 'home':
            default:
                return home_url('/');
        }

        // Fallback to homepage
        return home_url('/');
    }

    /**
     * Check if redirect would cause infinite loop
     *
     * @param string $current_url Current URL
     * @param string $destination_url Destination URL
     * @return bool True if would cause infinite redirect
     * @since 1.0.0
     */
    private function would_cause_infinite_redirect($current_url, $destination_url) {
        // Normalize URLs for comparison
        $current_normalized = rtrim($current_url, '/');
        $destination_normalized = rtrim($destination_url, '/');

        return $current_normalized === $destination_normalized;
    }



    /**
     * Get catch-all configuration status
     *
     * @return array Configuration status data
     * @since 1.0.0
     */
    public function get_catchall_stats() {
        $settings = get_option('ez_translate_catchall_settings', array());

        return array(
            'enabled' => !empty($settings['enabled']),
            'redirect_type' => isset($settings['redirect_type']) ? $settings['redirect_type'] : '301',
            'destination_type' => isset($settings['destination_type']) ? $settings['destination_type'] : 'home',
            'destination_configured' => $this->is_destination_configured($settings)
        );
    }

    /**
     * Check if catch-all destination is properly configured
     *
     * @param array $settings Catch-all settings
     * @return bool True if destination is configured
     * @since 1.0.0
     */
    private function is_destination_configured($settings) {
        if (empty($settings['destination_type'])) {
            return false;
        }

        switch ($settings['destination_type']) {
            case 'page':
                return !empty($settings['destination_page_id']) && get_post($settings['destination_page_id']);
            case 'url':
                return !empty($settings['destination_url']);
            case 'home':
            default:
                return true; // Home URL is always available
        }
    }
}
