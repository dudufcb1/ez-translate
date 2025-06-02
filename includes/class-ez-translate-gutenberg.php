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
            'type' => 'boolean',
            'auth_callback' => array($this, 'meta_auth_callback'),
            'sanitize_callback' => array($this, 'sanitize_boolean'),
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
