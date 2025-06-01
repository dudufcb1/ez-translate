<?php
/**
 * Plugin Name: EZ Translate
 * Plugin URI: https://github.com/your-username/ez-translate
 * Description: A comprehensive multilingual system for WordPress that simplifies managing content in multiple languages with advanced SEO optimization.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: ez-translate
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 *
 * @package EZTranslate
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EZ_TRANSLATE_VERSION', '1.0.0');
define('EZ_TRANSLATE_PLUGIN_FILE', __FILE__);
define('EZ_TRANSLATE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EZ_TRANSLATE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EZ_TRANSLATE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('EZ_TRANSLATE_TEXT_DOMAIN', 'ez-translate');

/**
 * Main EZ Translate Plugin Class
 *
 * @since 1.0.0
 */
final class EZTranslate {

    /**
     * Plugin instance
     *
     * @var EZTranslate
     * @since 1.0.0
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return EZTranslate
     * @since 1.0.0
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(EZ_TRANSLATE_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(EZ_TRANSLATE_PLUGIN_FILE, array($this, 'deactivate'));

        // Plugin initialization
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Load plugin dependencies
     *
     * @since 1.0.0
     */
    private function load_dependencies() {
        // Autoloader for classes
        spl_autoload_register(array($this, 'autoload'));

        // Load core files
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-logger.php';
    }

    /**
     * Autoloader for plugin classes
     *
     * @param string $class_name The class name to load
     * @since 1.0.0
     */
    public function autoload($class_name) {
        // Check if class belongs to our namespace
        if (strpos($class_name, 'EZTranslate\\') !== 0) {
            return;
        }

        // Convert namespace to file path
        $class_name = str_replace('EZTranslate\\', '', $class_name);
        $class_name = str_replace('_', '-', strtolower($class_name));
        $file_path = EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-' . $class_name . '.php';

        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }

    /**
     * Plugin activation
     *
     * @since 1.0.0
     */
    public function activate() {
        // Log activation
        $this->log_message('Plugin activated successfully', 'info');

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            deactivate_plugins(EZ_TRANSLATE_PLUGIN_BASENAME);
            wp_die(
                esc_html__('EZ Translate requires WordPress 5.8 or higher.', 'ez-translate'),
                esc_html__('Plugin Activation Error', 'ez-translate'),
                array('back_link' => true)
            );
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(EZ_TRANSLATE_PLUGIN_BASENAME);
            wp_die(
                esc_html__('EZ Translate requires PHP 7.4 or higher.', 'ez-translate'),
                esc_html__('Plugin Activation Error', 'ez-translate'),
                array('back_link' => true)
            );
        }

        // Initialize default options if they don't exist
        if (false === get_option('ez_translate_languages')) {
            add_option('ez_translate_languages', array());
            $this->log_message('Default language options initialized', 'info');
        }

        // Set activation flag for any initialization needed on first load
        add_option('ez_translate_activation_redirect', true);

        $this->log_message('Plugin activation completed', 'info');
    }

    /**
     * Plugin deactivation
     *
     * @since 1.0.0
     */
    public function deactivate() {
        $this->log_message('Plugin deactivated', 'info');

        // Clean up transients
        delete_transient('ez_translate_languages_cache');

        // Remove activation redirect flag
        delete_option('ez_translate_activation_redirect');

        $this->log_message('Plugin deactivation completed', 'info');
    }

    /**
     * Initialize plugin
     *
     * @since 1.0.0
     */
    public function init() {
        // Verify WordPress and PHP versions again
        if (!$this->check_requirements()) {
            return;
        }

        $this->log_message('Plugin initialization started', 'debug');

        // Initialize core components
        $this->init_core_components();

        $this->log_message('Plugin initialization completed', 'debug');
    }

    /**
     * Check plugin requirements
     *
     * @return bool
     * @since 1.0.0
     */
    private function check_requirements() {
        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            $this->log_message('WordPress version requirement not met', 'error');
            return false;
        }

        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $this->log_message('PHP version requirement not met', 'error');
            return false;
        }

        return true;
    }

    /**
     * Initialize core components
     *
     * @since 1.0.0
     */
    private function init_core_components() {
        // Initialize admin components
        if (is_admin()) {
            $this->init_admin();
        }

        // Initialize post meta manager for all contexts
        $this->init_post_meta_manager();

        $this->log_message('Core components initialized', 'debug');
    }

    /**
     * Initialize post meta manager
     *
     * @since 1.0.0
     */
    private function init_post_meta_manager() {
        // Load post meta manager class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-post-meta-manager.php';

        // Initialize post meta manager
        new \EZTranslate\PostMetaManager();

        $this->log_message('Post meta manager initialized', 'debug');
    }

    /**
     * Initialize admin components
     *
     * @since 1.0.0
     */
    private function init_admin() {
        // Load admin class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-admin.php';

        // Initialize admin
        new \EZTranslate\Admin();

        $this->log_message('Admin components initialized', 'debug');
    }

    /**
     * Load plugin text domain for internationalization
     *
     * @since 1.0.0
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            EZ_TRANSLATE_TEXT_DOMAIN,
            false,
            dirname(EZ_TRANSLATE_PLUGIN_BASENAME) . '/languages'
        );

        $this->log_message('Text domain loaded', 'debug');
    }

    /**
     * Log messages with EZ Translate prefix
     *
     * @param string $message The message to log
     * @param string $level   Log level (error, warning, info, debug)
     * @since 1.0.0
     */
    private function log_message($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $formatted_message = sprintf('[EZ-Translate] %s: %s', ucfirst($level), $message);
            error_log($formatted_message);
        }
    }

    /**
     * Get plugin version
     *
     * @return string
     * @since 1.0.0
     */
    public function get_version() {
        return EZ_TRANSLATE_VERSION;
    }

    /**
     * Get plugin directory path
     *
     * @return string
     * @since 1.0.0
     */
    public function get_plugin_dir() {
        return EZ_TRANSLATE_PLUGIN_DIR;
    }

    /**
     * Get plugin URL
     *
     * @return string
     * @since 1.0.0
     */
    public function get_plugin_url() {
        return EZ_TRANSLATE_PLUGIN_URL;
    }
}

/**
 * Initialize the plugin
 *
 * @return EZTranslate
 * @since 1.0.0
 */
function ez_translate() {
    return EZTranslate::get_instance();
}

// Initialize the plugin
ez_translate();
