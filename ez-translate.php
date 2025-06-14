<?php
/**
 * Plugin Name: EZ Translate
 * Plugin URI: https://especialistaenwp.com/plugins/ez-translate
 * Description: 🌍 Transform your WP site into a multilingual powerhouse! Advanced translation system with SEO optimization, automatic redirects, and Gutenberg integration. Perfect for global businesses and content creators.
 * Version: 1.1.2
 * Author: EspecialistaEnWP - WordPress Expert
 * Author URI: https://especialistaenwp.com
 * Text Domain: ez-translate
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * 🚀 Need custom development? Visit https://especialistaenwp.com
 * ✨ WP Themes & Plugins | ⚡ Laravel | ⚛️ React/Vue | 🐍 Python | 🤖 AI Integration
 *
 * @package EZTranslate
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Start output buffering to prevent header issues
if (!defined('DOING_AJAX') && !defined('DOING_CRON')) {
    ob_start();
}

// Define plugin constants
define('EZ_TRANSLATE_VERSION', '1.1.2');
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

        // Remove namespace prefix
        $class_name = str_replace('EZTranslate\\', '', $class_name);
        
        // Split remaining namespace parts
        $parts = explode('\\', $class_name);
        $class_file = array_pop($parts); // Get the actual class name
        
        // Convert class name to filename
        $class_file = str_replace('_', '-', strtolower($class_file));
        $class_file = 'class-ez-translate-' . $class_file . '.php';
        
        // Build the path based on namespace
        $subdir = '';
        if (!empty($parts)) {
            $subdir = strtolower(implode('/', $parts)) . '/';
        }
        
        // Try to find the file in the includes directory
        $file_path = EZ_TRANSLATE_PLUGIN_DIR . 'includes/' . $subdir . $class_file;
        
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

        // Initialize robots settings if they don't exist
        if (false === get_option('ez_translate_robots_settings')) {
            add_option('ez_translate_robots_settings', array(
                'enabled' => false,
                'include_sitemap' => true,
                'default_rules' => array(
                    'wp_admin' => true,           // Block WordPress Admin
                    'wp_login' => true,           // Block Login Page
                    'wp_includes' => true,        // Block WordPress Core Files
                    'wp_plugins' => true,         // Block Plugin Files
                    'wp_themes' => true,          // Block Theme Files
                    'wp_uploads' => false,        // Allow Media/Images by default
                    'readme_files' => true,       // Block Readme Files
                    'wp_config' => true,          // Block Config File
                    'xmlrpc' => true,             // Block XML-RPC
                    'wp_json' => false,           // Allow REST API by default
                    'feed' => false,              // Allow RSS Feeds by default
                    'trackback' => true,          // Block Trackbacks
                    'wp_cron' => true,            // Block WordPress Cron
                    'search' => false,            // Allow Search Results by default
                    'author' => false,            // Allow Author Pages by default
                    'date_archives' => false,     // Allow Date Archives by default
                    'tag_archives' => false,      // Allow Tag Archives by default
                    'attachment' => false,        // Allow Attachment Pages by default
                    'private_pages' => true       // Block Private Content
                ),
                'custom_rules' => array(),
                'additional_content' => '',
                'last_updated' => ''
            ));
            $this->log_message('Default robots settings initialized', 'info');
        }

        // Initialize SEO metadata settings if they don't exist
        if (false === get_option('ez_translate_seo_metadata_settings')) {
            // Load SEO metadata admin class to get defaults
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/admin/class-ez-translate-seo-metadata-admin.php';
            $default_seo_settings = \EZTranslate\Admin\SeoMetadataAdmin::get_default_settings();
            add_option('ez_translate_seo_metadata_settings', $default_seo_settings);
            $this->log_message('Default SEO metadata settings initialized', 'info');
        }

        // Flush rewrite rules to ensure robots.txt works
        flush_rewrite_rules();

        // Create redirect database table
        $this->create_redirect_table();

        // Set initial database version for new installations
        $this->set_initial_database_version();

        // Set activation flag for any initialization needed on first load
        add_option('ez_translate_activation_redirect', true);

        // Fire custom activation hook
        do_action('ez_translate_activated');

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
     * Create redirect database table
     *
     * @since 1.0.0
     */
    private function create_redirect_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ez_translate_redirects';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE " . $table_name . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            old_url varchar(2048) NOT NULL,
            new_url varchar(2048) DEFAULT NULL,
            redirect_type varchar(10) NOT NULL DEFAULT '301',
            change_type varchar(20) NOT NULL,
            post_id bigint(20) unsigned DEFAULT NULL,
            destination_post_id bigint(20) unsigned DEFAULT NULL,
            wp_auto_redirect tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY old_url_index (old_url(191)),
            KEY post_id_index (post_id),
            KEY destination_post_id_index (destination_post_id),
            KEY change_type_index (change_type),
            KEY created_at_index (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        if ($result) {
            $this->log_message('Redirects database table created successfully', 'info');
        } else {
            $this->log_message('Failed to create redirects database table: ' . $wpdb->last_error, 'error');
        }
    }

    /**
     * Set initial database version for new installations
     *
     * @since 1.0.0
     */
    private function set_initial_database_version() {
        $current_db_version = get_option('ez_translate_db_version', '0');

        // Only set version if it's a new installation (version is 0)
        if ($current_db_version === '0') {
            update_option('ez_translate_db_version', '1.0.1');
            $this->log_message('Database version set to 1.0.1 for new installation', 'info');
        }
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

        // Initialize core components
        $this->init_core_components();
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

        // Initialize REST API
        $this->init_rest_api();

        // Initialize Gutenberg integration
        $this->init_gutenberg();

        // Initialize frontend components (only on frontend)
        if (!is_admin()) {
            $this->init_frontend();
        }

        // Initialize sitemap manager for all contexts
        $this->init_sitemap_manager();

        // Initialize robots manager for all contexts
        $this->init_robots_manager();

        // Initialize redirect manager for all contexts
        $this->init_redirect_manager();

        // Initialize language detector for frontend
        if (!is_admin()) {
            $this->init_language_detector();
        }
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
    }

    /**
     * Initialize redirect manager
     *
     * @since 1.0.0
     */
    private function init_redirect_manager() {
        // Load redirect manager classes
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-redirect-manager.php';
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-redirect-tracker.php';

        // Initialize redirect manager
        new \EZTranslate\RedirectManager();
        new \EZTranslate\RedirectTracker();

        // Initialize redirect admin if in admin context
        if (is_admin()) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-redirect-admin.php';
            new \EZTranslate\RedirectAdmin();
        }

        // Initialize catch-all handler for frontend
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-catchall-handler.php';
        new \EZTranslate\CatchAllHandler();
    }

    /**
     * Initialize REST API
     *
     * @since 1.0.0
     */
    private function init_rest_api() {
        // Ensure dependencies are loaded
        if (!class_exists('EZTranslate\LanguageManager')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        }

        if (!class_exists('EZTranslate\PostMetaManager')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-post-meta-manager.php';
        }

        // Load REST API class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-rest-api.php';

        // Initialize REST API
        new \EZTranslate\RestAPI();
    }

    /**
     * Initialize Gutenberg integration
     *
     * @since 1.0.0
     */
    private function init_gutenberg() {
        // Load Gutenberg class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-gutenberg.php';

        // Initialize Gutenberg integration
        new \EZTranslate\Gutenberg();
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
    }

    /**
     * Initialize frontend components
     *
     * @since 1.0.0
     */
    private function init_frontend() {
        // Load frontend class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-frontend.php';

        // Initialize frontend
        new \EZTranslate\Frontend();
    }

    /**
     * Initialize sitemap manager
     *
     * @since 1.0.0
     */
    private function init_sitemap_manager() {
        // Load sitemap manager class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-manager.php';

        // Initialize sitemap manager
        new \EZTranslate\Sitemap\SitemapManager();
    }

    /**
     * Initialize robots manager
     *
     * @since 1.0.0
     */
    private function init_robots_manager() {
        // Load robots class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-robots.php';

        // Initialize robots manager
        new \EZTranslate\Robots();
    }

    /**
     * Initialize language detector
     *
     * @since 1.0.0
     */
    private function init_language_detector() {
        // Load language detector class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-detector.php';

        // Language detector is initialized via Frontend class
        // No need to instantiate here as it's handled by Frontend hooks
        $this->log_message('Language detector initialized', 'debug');
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
    }    /**
     * Log messages with EZ Translate prefix
     *
     * @param string $message The message to log
     * @param string $level   Log level (error, warning, info, debug)
     * @since 1.0.0
     */
    private function log_message($message, $level = 'info') {
        if (class_exists('\EZTranslate\Logger')) {
            switch ($level) {
                case 'error':
                    \EZTranslate\Logger::error($message);
                    break;
                case 'warning':
                    \EZTranslate\Logger::warning($message);
                    break;
                case 'debug':
                    \EZTranslate\Logger::debug($message);
                    break;
                case 'info':
                default:
                    \EZTranslate\Logger::info($message);
                    break;
            }
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
