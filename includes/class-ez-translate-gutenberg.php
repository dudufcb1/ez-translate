<?php
/**
 * Gutenberg Integration for EZ Translate
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
 * Gutenberg Integration class
 *
 * @since 1.0.0
 */
class Gutenberg {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('init', array($this, 'register_meta_fields'));

        // Hook into meta updates to ensure proper sanitization
        add_filter('update_post_metadata', array($this, 'intercept_landing_page_meta'), 10, 5);

        // Hook into REST API to intercept meta updates from Gutenberg
        add_filter('rest_pre_update_post_meta', array($this, 'intercept_rest_meta_update'), 10, 4);

        // Hook into all REST API requests to see what's happening
        add_filter('rest_pre_dispatch', array($this, 'log_rest_requests'), 10, 3);

        Logger::info('Gutenberg integration initialized');
    }

    /**
     * Register meta fields for Gutenberg
     *
     * @since 1.0.0
     */
    public function register_meta_fields() {
        // Register language meta field
        register_meta('post', '_ez_translate_language', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => array($this, 'meta_auth_callback'),
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // Register group meta field
        register_meta('post', '_ez_translate_group', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => array($this, 'meta_auth_callback'),
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // Register landing page meta field
        register_meta('post', '_ez_translate_is_landing', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => array($this, 'meta_auth_callback'),
            'sanitize_callback' => array($this, 'sanitize_landing_page'),
        ));

        // Register SEO title meta field
        register_meta('post', '_ez_translate_seo_title', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => array($this, 'meta_auth_callback'),
            'sanitize_callback' => 'sanitize_text_field',
        ));

        // Register SEO description meta field
        register_meta('post', '_ez_translate_seo_description', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => array($this, 'meta_auth_callback'),
            'sanitize_callback' => 'sanitize_textarea_field',
        ));

        Logger::debug('Gutenberg meta fields registered');
    }

    /**
     * Authorization callback for meta fields
     *
     * @param bool   $allowed   Whether the user can add the post meta
     * @param string $meta_key  The meta key
     * @param int    $post_id   Post ID
     * @param int    $user_id   User ID
     * @param string $cap       Capability name
     * @param array  $caps      User capabilities
     * @return bool
     * @since 1.0.0
     */
    public function meta_auth_callback($allowed, $meta_key, $post_id, $user_id, $cap, $caps) {
        // Suppress unused parameter warnings
        unset($allowed, $meta_key, $user_id, $cap, $caps);

        return current_user_can('edit_post', $post_id);
    }

    /**
     * Sanitize boolean values
     *
     * @param mixed $value Value to sanitize
     * @return bool
     * @since 1.0.0
     */
    public function sanitize_boolean($value) {
        if (is_string($value)) {
            $value = strtolower($value);
            if ($value === 'false' || $value === '0' || $value === '') {
                return false;
            }
            return true;
        }
        return (bool) $value;
    }

    /**
     * Sanitize landing page value (convert boolean to string for consistency)
     *
     * @param mixed $value Value to sanitize
     * @return string '1' for true, '0' for false
     * @since 1.0.0
     */
    public function sanitize_landing_page($value) {
        // Simple error_log for immediate visibility
        error_log('[EZ-Translate] SANITIZE_LANDING_PAGE CALLED: ' . var_export($value, true) . ' (type: ' . gettype($value) . ')');

        \EZTranslate\Logger::debug('Gutenberg: sanitize_landing_page called', array(
            'input_value' => $value,
            'input_type' => gettype($value),
            'is_true' => ($value === true),
            'is_1' => ($value === 1),
            'is_string_1' => ($value === '1'),
            'is_string_true' => ($value === 'true')
        ));

        // Convert various truthy/falsy values to '1' or '0'
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            error_log('[EZ-Translate] SANITIZE_LANDING_PAGE RETURNING: 1');
            \EZTranslate\Logger::debug('Gutenberg: sanitize_landing_page returning 1');
            return '1';
        }
        error_log('[EZ-Translate] SANITIZE_LANDING_PAGE RETURNING: 0');
        \EZTranslate\Logger::debug('Gutenberg: sanitize_landing_page returning 0');
        return '0';
    }

    /**
     * Intercept landing page meta updates to ensure proper sanitization
     *
     * @param null|bool $check      Whether to allow updating metadata for the given type
     * @param int       $object_id  ID of the object metadata is for
     * @param string    $meta_key   Metadata key
     * @param mixed     $meta_value Metadata value
     * @param mixed     $prev_value Previous value to check before updating
     * @return null|bool Null to continue with normal flow, bool to override
     * @since 1.0.0
     */
    public function intercept_landing_page_meta($check, $object_id, $meta_key, $meta_value, $prev_value) {
        // Only intercept our landing page meta
        if ($meta_key !== '_ez_translate_is_landing') {
            return $check;
        }

        // Simple error_log for immediate visibility
        error_log('[EZ-Translate] INTERCEPT_LANDING_PAGE_META CALLED: ' . $meta_key . ' = ' . var_export($meta_value, true));

        \EZTranslate\Logger::debug('Gutenberg: intercept_landing_page_meta called', array(
            'object_id' => $object_id,
            'meta_key' => $meta_key,
            'meta_value' => $meta_value,
            'meta_value_type' => gettype($meta_value),
            'prev_value' => $prev_value
        ));

        // Sanitize the value using our function
        $sanitized_value = $this->sanitize_landing_page($meta_value);

        \EZTranslate\Logger::debug('Gutenberg: intercepted meta update, sanitized value', array(
            'original_value' => $meta_value,
            'sanitized_value' => $sanitized_value
        ));

        // Update with sanitized value
        $result = update_post_meta($object_id, $meta_key, $sanitized_value, $prev_value);

        \EZTranslate\Logger::debug('Gutenberg: intercepted update result', array(
            'object_id' => $object_id,
            'result' => $result,
            'final_value' => $sanitized_value
        ));

        // Return true to prevent WordPress from doing its own update
        return true;
    }

    /**
     * Intercept REST API meta updates for landing page
     *
     * @param mixed           $value     The meta value being updated
     * @param WP_Post         $object    The post object
     * @param string          $meta_key  The meta key being updated
     * @param WP_REST_Request $request   The REST request object
     * @return mixed The filtered meta value
     * @since 1.0.0
     */
    public function intercept_rest_meta_update($value, $object, $meta_key, $request) {
        // Only intercept our landing page meta
        if ($meta_key !== '_ez_translate_is_landing') {
            return $value;
        }

        // Simple error_log for immediate visibility
        error_log('[EZ-Translate] INTERCEPT_REST_META_UPDATE CALLED: ' . $meta_key . ' = ' . var_export($value, true));

        \EZTranslate\Logger::debug('Gutenberg: intercept_rest_meta_update called', array(
            'post_id' => $object->ID,
            'meta_key' => $meta_key,
            'meta_value' => $value,
            'meta_value_type' => gettype($value)
        ));

        // Sanitize the value using our function
        $sanitized_value = $this->sanitize_landing_page($value);

        \EZTranslate\Logger::debug('Gutenberg: REST meta update sanitized', array(
            'original_value' => $value,
            'sanitized_value' => $sanitized_value
        ));

        error_log('[EZ-Translate] INTERCEPT_REST_META_UPDATE SANITIZED: ' . var_export($sanitized_value, true));

        return $sanitized_value;
    }

    /**
     * Log REST API requests to debug Gutenberg behavior
     *
     * @param mixed           $result  Response to replace the requested version with
     * @param WP_REST_Server  $server  Server instance
     * @param WP_REST_Request $request Request used to generate the response
     * @return mixed The result
     * @since 1.0.0
     */
    public function log_rest_requests($result, $server, $request) {
        $route = $request->get_route();
        $method = $request->get_method();

        // Only log post-related requests
        if (strpos($route, '/wp/v2/posts/') !== false || strpos($route, '/wp/v2/pages/') !== false) {
            $body = $request->get_body();
            $params = $request->get_params();

            // Check if this request contains our meta
            if (isset($params['meta']) && isset($params['meta']['_ez_translate_is_landing'])) {
                error_log('[EZ-Translate] REST REQUEST WITH LANDING META: ' . $method . ' ' . $route);
                error_log('[EZ-Translate] META VALUE: ' . var_export($params['meta']['_ez_translate_is_landing'], true));
                error_log('[EZ-Translate] ALL META: ' . var_export($params['meta'], true));
            }
        }

        return $result;
    }

    /**
     * Enqueue block editor assets
     *
     * @since 1.0.0
     */
    public function enqueue_block_editor_assets() {
        // Check if we're in the block editor
        if (!$this->is_gutenberg_page()) {
            return;
        }

        $asset_file = EZ_TRANSLATE_PLUGIN_DIR . 'assets/js/gutenberg-sidebar.asset.php';
        $asset_data = file_exists($asset_file) ? include $asset_file : array(
            'dependencies' => array(
                'wp-plugins',
                'wp-edit-post',
                'wp-element',
                'wp-components',
                'wp-data',
                'wp-api-fetch',
                'wp-i18n'
            ),
            'version' => EZ_TRANSLATE_VERSION
        );

        // Enqueue the sidebar script
        wp_enqueue_script(
            'ez-translate-gutenberg-sidebar',
            EZ_TRANSLATE_PLUGIN_URL . 'assets/js/gutenberg-sidebar.js',
            $asset_data['dependencies'],
            $asset_data['version'],
            true
        );

        // Get WordPress language setting
        $wp_language = get_locale();
        // Convert from locale (en_US) to language code (en)
        $language_code = substr($wp_language, 0, 2);

        // Localize script with data
        wp_localize_script('ez-translate-gutenberg-sidebar', 'ezTranslateGutenberg', array(
            'apiUrl' => rest_url('ez-translate/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'postId' => get_the_ID(),
            'pluginUrl' => EZ_TRANSLATE_PLUGIN_URL,
            'textDomain' => 'ez-translate',
            'wpLanguage' => $language_code,
            'wpLocale' => $wp_language,
        ));

        // Enqueue styles if they exist
        $style_path = EZ_TRANSLATE_PLUGIN_DIR . 'assets/css/gutenberg-sidebar.css';
        if (file_exists($style_path)) {
            wp_enqueue_style(
                'ez-translate-gutenberg-sidebar',
                EZ_TRANSLATE_PLUGIN_URL . 'assets/css/gutenberg-sidebar.css',
                array(),
                EZ_TRANSLATE_VERSION
            );
        }

        Logger::debug('Gutenberg assets enqueued', array(
            'script_handle' => 'ez-translate-gutenberg-sidebar',
            'post_id' => get_the_ID()
        ));
    }

    /**
     * Check if we're on a Gutenberg page
     *
     * @return bool
     * @since 1.0.0
     */
    private function is_gutenberg_page() {
        global $current_screen;

        if (!isset($current_screen)) {
            return false;
        }

        // Check if we're in the block editor
        if (method_exists($current_screen, 'is_block_editor') && $current_screen->is_block_editor()) {
            return true;
        }

        // Note: is_gutenberg_page() function was removed in newer WordPress versions

        // Check for post edit screen with block editor
        if ($current_screen->base === 'post' && $current_screen->action !== 'add') {
            $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
            if ($post_id && use_block_editor_for_post($post_id)) {
                return true;
            }
        }

        // Check for new post screen with block editor
        if ($current_screen->base === 'post' && $current_screen->action === 'add') {
            if (use_block_editor_for_post_type($current_screen->post_type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get available languages for JavaScript
     *
     * @return array
     * @since 1.0.0
     */
    public static function get_languages_for_js() {
        $languages = \EZTranslate\LanguageManager::get_enabled_languages();
        
        // Format for JavaScript consumption
        $formatted_languages = array();
        foreach ($languages as $code => $language) {
            $formatted_languages[] = array(
                'value' => $code,
                'label' => $language['name'] . ($language['native_name'] ? ' (' . $language['native_name'] . ')' : ''),
                'flag' => $language['flag'] ?? '',
                'rtl' => $language['rtl'] ?? false,
            );
        }

        return $formatted_languages;
    }
}
