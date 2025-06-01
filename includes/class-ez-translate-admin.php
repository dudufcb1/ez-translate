<?php
/**
 * EZ Translate Admin Class
 *
 * Handles all admin-related functionality for the EZ Translate plugin
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for EZ Translate
 *
 * @since 1.0.0
 */
class Admin {

    /**
     * Menu slug for the main admin page
     *
     * @var string
     * @since 1.0.0
     */
    const MENU_SLUG = 'ez-translate';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
        Logger::info('Admin class initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        Logger::debug('Admin hooks initialized');
    }

    /**
     * Add admin menu
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            Logger::warning('User attempted to access admin menu without proper capabilities', array(
                'user_id' => get_current_user_id(),
                'user_login' => wp_get_current_user()->user_login
            ));
            return;
        }

        // Add main menu page
        $page_hook = add_menu_page(
            __('EZ Translate', 'ez-translate'),           // Page title
            __('EZ Translate', 'ez-translate'),           // Menu title
            'manage_options',                             // Capability
            self::MENU_SLUG,                             // Menu slug
            array($this, 'render_languages_page'),       // Callback function
            'dashicons-translation',                      // Icon
            21                                           // Position (after Pages which is 20)
        );

        // Add submenu page (Languages - same as main page)
        add_submenu_page(
            self::MENU_SLUG,                             // Parent slug
            __('Languages', 'ez-translate'),              // Page title
            __('Languages', 'ez-translate'),              // Menu title
            'manage_options',                             // Capability
            self::MENU_SLUG,                             // Menu slug (same as parent for main page)
            array($this, 'render_languages_page')        // Callback function
        );

        Logger::info('Admin menu added successfully', array(
            'page_hook' => $page_hook,
            'menu_slug' => self::MENU_SLUG
        ));
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix The current admin page hook suffix
     * @since 1.0.0
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on our admin pages
        if (strpos($hook_suffix, self::MENU_SLUG) === false) {
            return;
        }

        // Enqueue WordPress admin styles (we'll use native styling)
        wp_enqueue_style('wp-admin');
        
        Logger::debug('Admin assets enqueued', array(
            'hook_suffix' => $hook_suffix
        ));
    }

    /**
     * Render the Languages admin page
     *
     * @since 1.0.0
     */
    public function render_languages_page() {
        // Verify user capabilities again
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ez-translate'));
        }

        Logger::info('Languages admin page accessed', array(
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login
        ));

        // Get current languages (for future use)
        $languages = get_option('ez_translate_languages', array());
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-info">
                <p>
                    <?php _e('Welcome to EZ Translate! This is the main languages management page.', 'ez-translate'); ?>
                </p>
            </div>

            <div class="ez-translate-admin-content">
                <div class="card">
                    <h2><?php _e('Language Management', 'ez-translate'); ?></h2>
                    <p><?php _e('Here you will be able to manage all languages for your multilingual site.', 'ez-translate'); ?></p>
                    
                    <h3><?php _e('Current Status', 'ez-translate'); ?></h3>
                    <ul>
                        <li><?php printf(__('Languages configured: %d', 'ez-translate'), count($languages)); ?></li>
                        <li><?php printf(__('Plugin version: %s', 'ez-translate'), EZ_TRANSLATE_VERSION); ?></li>
                        <li><?php printf(__('WordPress version: %s', 'ez-translate'), get_bloginfo('version')); ?></li>
                    </ul>

                    <div class="ez-translate-debug-info">
                        <h4><?php _e('Debug Information', 'ez-translate'); ?></h4>
                        <p><strong><?php _e('Plugin Directory:', 'ez-translate'); ?></strong> <?php echo esc_html(EZ_TRANSLATE_PLUGIN_DIR); ?></p>
                        <p><strong><?php _e('Plugin URL:', 'ez-translate'); ?></strong> <?php echo esc_html(EZ_TRANSLATE_PLUGIN_URL); ?></p>
                        <p><strong><?php _e('Text Domain:', 'ez-translate'); ?></strong> <?php echo esc_html(EZ_TRANSLATE_TEXT_DOMAIN); ?></p>
                        <p><strong><?php _e('Current User Can Manage Options:', 'ez-translate'); ?></strong> <?php echo current_user_can('manage_options') ? __('Yes', 'ez-translate') : __('No', 'ez-translate'); ?></p>
                    </div>
                </div>

                <div class="card">
                    <h2><?php _e('Next Steps', 'ez-translate'); ?></h2>
                    <p><?php _e('The following features will be implemented in upcoming steps:', 'ez-translate'); ?></p>
                    <ul>
                        <li><?php _e('Language CRUD operations (Create, Read, Update, Delete)', 'ez-translate'); ?></li>
                        <li><?php _e('Page metadata management', 'ez-translate'); ?></li>
                        <li><?php _e('Gutenberg integration', 'ez-translate'); ?></li>
                        <li><?php _e('SEO optimization features', 'ez-translate'); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <style>
            .ez-translate-admin-content {
                margin-top: 20px;
            }
            
            .ez-translate-admin-content .card {
                margin-bottom: 20px;
                padding: 20px;
            }
            
            .ez-translate-debug-info {
                background: #f9f9f9;
                padding: 15px;
                border-left: 4px solid #0073aa;
                margin-top: 15px;
            }
            
            .ez-translate-debug-info p {
                margin: 5px 0;
                font-family: monospace;
                font-size: 12px;
            }
        </style>
        <?php

        Logger::debug('Languages admin page rendered successfully');
    }

    /**
     * Get menu slug
     *
     * @return string
     * @since 1.0.0
     */
    public static function get_menu_slug() {
        return self::MENU_SLUG;
    }
}
