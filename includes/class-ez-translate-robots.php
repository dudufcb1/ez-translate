<?php
/**
 * EZ Translate Robots Class
 *
 * Handles dynamic robots.txt generation and management
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
 * Robots class for EZ Translate
 *
 * @since 1.0.0
 */
class Robots {

    /**
     * Option name for storing robots settings
     *
     * @var string
     * @since 1.0.0
     */
    const OPTION_NAME = 'ez_translate_robots_settings';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
        Logger::info('Robots class initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Hook into robots.txt generation
        add_filter('robots_txt', array($this, 'generate_robots_txt'), 10, 2);

        // Hook into template redirect to handle robots.txt requests directly
        add_action('template_redirect', array($this, 'handle_robots_request'));

        // Hook into init to add rewrite rules
        add_action('init', array($this, 'add_robots_rewrite_rules'));

        // Hook into query vars to recognize robots.txt
        add_filter('query_vars', array($this, 'add_robots_query_vars'));

        Logger::info('Robots hooks initialized');
    }

    /**
     * Handle robots.txt requests directly
     *
     * @since 1.0.0
     */
    public function handle_robots_request() {
        // Check if this is a robots.txt request
        if (!$this->is_robots_request()) {
            return;
        }

        $settings = $this->get_robots_settings();

        // If robots.txt is disabled, let WordPress handle it normally
        if (!$settings['enabled']) {
            Logger::debug('Robots.txt generation disabled, letting WordPress handle normally');
            return;
        }

        Logger::info('Handling robots.txt request directly');

        // Generate content
        $content = $this->generate_robots_content();

        // Set proper headers
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow', true);

        // Output content and exit
        echo $content;
        exit;
    }

    /**
     * Check if current request is for robots.txt
     *
     * @return bool True if robots.txt request
     * @since 1.0.0
     */
    private function is_robots_request() {
        global $wp;

        // Check if we have the robots query var
        if (get_query_var('ez_robots')) {
            return true;
        }

        // Check if we're on the robots.txt URL
        if (isset($wp->request) && $wp->request === 'robots.txt') {
            return true;
        }

        // Alternative check for direct robots.txt access
        if (isset($_SERVER['REQUEST_URI']) &&
            (rtrim($_SERVER['REQUEST_URI'], '/') === '/robots.txt' ||
             $_SERVER['REQUEST_URI'] === '/robots.txt')) {
            return true;
        }

        return false;
    }

    /**
     * Add rewrite rules for robots.txt
     *
     * @since 1.0.0
     */
    public function add_robots_rewrite_rules() {
        add_rewrite_rule('^robots\.txt$', 'index.php?ez_robots=1', 'top');
    }

    /**
     * Add query vars for robots.txt
     *
     * @param array $vars Existing query vars
     * @return array Modified query vars
     * @since 1.0.0
     */
    public function add_robots_query_vars($vars) {
        $vars[] = 'ez_robots';
        return $vars;
    }

    /**
     * Generate dynamic robots.txt content
     *
     * @param string $output The default robots.txt output
     * @param string $public Whether the site is public
     * @return string Modified robots.txt content
     * @since 1.0.0
     */
    public function generate_robots_txt($output, $public) {
        $settings = $this->get_robots_settings();

        // If robots.txt is disabled, return default output
        if (!$settings['enabled']) {
            Logger::debug('Robots.txt generation disabled, returning default output');
            return $output;
        }

        // If site is not public, return default WordPress behavior
        if (!$public) {
            Logger::debug('Site is not public, returning default robots.txt');
            return $output;
        }

        Logger::info('Generating dynamic robots.txt content');

        // Start with empty content to have full control
        $robots_content = '';

        // Add default rules if any are enabled
        if (!empty($settings['default_rules']) && is_array($settings['default_rules'])) {
            $robots_content .= $this->get_default_rules($settings['default_rules']);
        }

        // Add custom rules
        if (!empty($settings['custom_rules'])) {
            $robots_content .= $this->generate_custom_rules($settings['custom_rules']);
        }

        // Add sitemap if enabled and sitemap functionality exists
        if ($settings['include_sitemap']) {
            $robots_content .= $this->generate_sitemap_directive();
        }

        // Add additional content if provided
        if (!empty($settings['additional_content'])) {
            $robots_content .= "\n" . $settings['additional_content'] . "\n";
        }

        Logger::debug('Generated robots.txt content', array(
            'content_length' => strlen($robots_content),
            'has_default_rules' => $settings['default_rules'],
            'custom_rules_count' => count($settings['custom_rules']),
            'includes_sitemap' => $settings['include_sitemap']
        ));

        return $robots_content;
    }

    /**
     * Generate robots.txt content (internal method)
     *
     * @return string Generated robots.txt content
     * @since 1.0.0
     */
    private function generate_robots_content() {
        $settings = $this->get_robots_settings();

        Logger::info('Generating robots.txt content internally');

        // Start with empty content to have full control
        $robots_content = '';

        // Add default rules if any are enabled
        if (!empty($settings['default_rules']) && is_array($settings['default_rules'])) {
            $robots_content .= $this->get_default_rules($settings['default_rules']);
        }

        // Add custom rules
        if (!empty($settings['custom_rules'])) {
            $robots_content .= $this->generate_custom_rules($settings['custom_rules']);
        }

        // Add sitemap if enabled and sitemap functionality exists
        if ($settings['include_sitemap']) {
            $robots_content .= $this->generate_sitemap_directive();
        }

        // Add additional content if provided
        if (!empty($settings['additional_content'])) {
            $robots_content .= "\n" . $settings['additional_content'] . "\n";
        }

        Logger::debug('Generated robots.txt content internally', array(
            'content_length' => strlen($robots_content),
            'has_default_rules' => $settings['default_rules'],
            'custom_rules_count' => count($settings['custom_rules']),
            'includes_sitemap' => $settings['include_sitemap']
        ));

        return $robots_content;
    }

    /**
     * Get default robots.txt rules based on configuration
     *
     * @param array $default_rules_config Configuration for default rules
     * @return string Default rules content
     * @since 1.0.0
     */
    private function get_default_rules($default_rules_config) {
        $rules = "User-agent: *\n";

        // WordPress Admin (always include Allow for admin-ajax.php if wp_admin is enabled)
        if (!empty($default_rules_config['wp_admin'])) {
            $rules .= "Disallow: /wp-admin/\n";
            $rules .= "Allow: /wp-admin/admin-ajax.php\n";
        }

        // WordPress Login
        if (!empty($default_rules_config['wp_login'])) {
            $rules .= "Disallow: /wp-login.php\n";
        }

        // WordPress Includes
        if (!empty($default_rules_config['wp_includes'])) {
            $rules .= "Disallow: /wp-includes/\n";
        }

        // WordPress Plugins
        if (!empty($default_rules_config['wp_plugins'])) {
            $rules .= "Disallow: /wp-content/plugins/\n";
        }

        // WordPress Themes
        if (!empty($default_rules_config['wp_themes'])) {
            $rules .= "Disallow: /wp-content/themes/\n";
        }

        // WordPress Uploads (Images, Media)
        if (!empty($default_rules_config['wp_uploads'])) {
            $rules .= "Disallow: /wp-content/uploads/\n";
        }

        // WordPress Config
        if (!empty($default_rules_config['wp_config'])) {
            $rules .= "Disallow: /wp-config.php\n";
        }

        // XML-RPC
        if (!empty($default_rules_config['xmlrpc'])) {
            $rules .= "Disallow: /xmlrpc.php\n";
        }

        // WordPress JSON API
        if (!empty($default_rules_config['wp_json'])) {
            $rules .= "Disallow: /wp-json/\n";
        }

        // WordPress Cron
        if (!empty($default_rules_config['wp_cron'])) {
            $rules .= "Disallow: /wp-cron.php\n";
        }

        // Feeds
        if (!empty($default_rules_config['feed'])) {
            $rules .= "Disallow: /feed/\n";
            $rules .= "Disallow: /*/feed/\n";
            $rules .= "Disallow: /comments/feed/\n";
        }

        // Search Results
        if (!empty($default_rules_config['search'])) {
            $rules .= "Disallow: /?s=\n";
            $rules .= "Disallow: /search/\n";
        }

        // Author Pages
        if (!empty($default_rules_config['author'])) {
            $rules .= "Disallow: /author/\n";
        }

        // Date Archives
        if (!empty($default_rules_config['date_archives'])) {
            $rules .= "Disallow: /20*/\n"; // Blocks year-based archives
        }

        // Tag Archives
        if (!empty($default_rules_config['tag_archives'])) {
            $rules .= "Disallow: /tag/\n";
        }

        // Attachment Pages
        if (!empty($default_rules_config['attachment'])) {
            $rules .= "Disallow: /attachment/\n";
        }

        // Trackback
        if (!empty($default_rules_config['trackback'])) {
            $rules .= "Disallow: /trackback/\n";
            $rules .= "Disallow: /*/trackback/\n";
        }

        // Private Pages (custom)
        if (!empty($default_rules_config['private_pages'])) {
            $rules .= "Disallow: /private/\n";
        }

        // Readme and License files
        if (!empty($default_rules_config['readme_files'])) {
            $rules .= "Disallow: /readme.html\n";
            $rules .= "Disallow: /license.txt\n";
            $rules .= "Disallow: /readme.txt\n";
            $rules .= "Disallow: /changelog.txt\n";
        }

        $rules .= "\n";
        return $rules;
    }

    /**
     * Generate custom rules from settings
     *
     * @param array $custom_rules Array of custom rules
     * @return string Generated custom rules content
     * @since 1.0.0
     */
    private function generate_custom_rules($custom_rules) {
        $content = '';
        $current_user_agent = '';

        foreach ($custom_rules as $rule) {
            if (!isset($rule['user_agent']) || !isset($rule['directive']) || !isset($rule['path'])) {
                continue;
            }

            // Add user-agent line if it changed
            if ($current_user_agent !== $rule['user_agent']) {
                if (!empty($current_user_agent)) {
                    $content .= "\n";
                }
                $content .= "User-agent: " . $rule['user_agent'] . "\n";
                $current_user_agent = $rule['user_agent'];
            }

            // Add directive
            $content .= $rule['directive'] . ": " . $rule['path'] . "\n";
        }

        if (!empty($content)) {
            $content .= "\n";
        }

        return $content;
    }

    /**
     * Generate sitemap directive
     *
     * @return string Sitemap directive content
     * @since 1.0.0
     */
    private function generate_sitemap_directive() {
        $sitemap_url = home_url('/sitemap.xml');
        return "Sitemap: " . $sitemap_url . "\n\n";
    }

    /**
     * Get robots settings with defaults
     *
     * @return array Robots settings
     * @since 1.0.0
     */
    public static function get_robots_settings() {
        $defaults = array(
            'enabled' => false,
            'include_sitemap' => true,
            'default_rules' => array(
                'wp_admin' => true,           // Disallow: /wp-admin/ (except admin-ajax.php)
                'wp_login' => true,           // Disallow: /wp-login.php
                'wp_includes' => true,        // Disallow: /wp-includes/
                'wp_plugins' => true,         // Disallow: /wp-content/plugins/
                'wp_themes' => true,          // Disallow: /wp-content/themes/
                'wp_uploads' => false,        // Disallow: /wp-content/uploads/ (FALSE = allow images by default)
                'readme_files' => true,       // Disallow: /readme.html, /license.txt
                'wp_config' => true,          // Disallow: /wp-config.php
                'xmlrpc' => true,             // Disallow: /xmlrpc.php
                'wp_json' => false,           // Disallow: /wp-json/ (FALSE = allow API by default)
                'feed' => false,              // Disallow: /feed/ (FALSE = allow feeds by default)
                'trackback' => true,          // Disallow: /trackback/
                'wp_cron' => true,            // Disallow: /wp-cron.php
                'search' => false,            // Disallow: /?s= (FALSE = allow search by default)
                'author' => false,            // Disallow: /author/ (FALSE = allow author pages by default)
                'date_archives' => false,     // Disallow: /2024/ (FALSE = allow date archives by default)
                'tag_archives' => false,      // Disallow: /tag/ (FALSE = allow tag archives by default)
                'attachment' => false,        // Disallow: /attachment/ (FALSE = allow attachment pages by default)
                'private_pages' => true       // Disallow: /private/ (custom private content)
            ),
            'custom_rules' => array(),
            'additional_content' => '',
            'last_updated' => ''
        );

        $settings = get_option(self::OPTION_NAME, array());
        
        // Ensure settings is always an array
        if (!is_array($settings)) {
            $settings = array();
            Logger::warning('Robots settings option was not an array, resetting to defaults');
        }

        // Merge with defaults to ensure all keys exist
        $settings = array_merge($defaults, $settings);

        Logger::debug('Robots settings retrieved', array(
            'enabled' => $settings['enabled'],
            'include_sitemap' => $settings['include_sitemap'],
            'default_rules' => $settings['default_rules'],
            'custom_rules_count' => count($settings['custom_rules'])
        ));

        return $settings;
    }

    /**
     * Update robots settings
     *
     * @param array $new_settings New settings to save
     * @return bool|\WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public static function update_robots_settings($new_settings) {
        Logger::info('Updating robots settings', $new_settings);

        // Get current settings
        $current_settings = self::get_robots_settings();

        // Sanitize new settings
        $sanitized_settings = self::sanitize_robots_settings($new_settings);
        if (is_wp_error($sanitized_settings)) {
            return $sanitized_settings;
        }

        // Merge with current settings
        $updated_settings = array_merge($current_settings, $sanitized_settings);
        $updated_settings['last_updated'] = current_time('mysql');

        // Save to database
        $result = update_option(self::OPTION_NAME, $updated_settings);

        if ($result) {
            Logger::info('Robots settings updated successfully', array(
                'enabled' => $updated_settings['enabled'],
                'custom_rules_count' => count($updated_settings['custom_rules'])
            ));
            return true;
        } else {
            $error = new \WP_Error('save_failed', __('Failed to save robots settings to database.', 'ez-translate'));
            Logger::error('Failed to save robots settings');
            return $error;
        }
    }

    /**
     * Sanitize robots settings
     *
     * @param array $settings Settings to sanitize
     * @return array|\WP_Error Sanitized settings or WP_Error on validation failure
     * @since 1.0.0
     */
    private static function sanitize_robots_settings($settings) {
        $sanitized = array();

        // Sanitize enabled flag
        if (isset($settings['enabled'])) {
            $sanitized['enabled'] = (bool) $settings['enabled'];
        }

        // Sanitize include_sitemap flag
        if (isset($settings['include_sitemap'])) {
            $sanitized['include_sitemap'] = (bool) $settings['include_sitemap'];
        }

        // Sanitize default_rules configuration
        if (isset($settings['default_rules'])) {
            if (is_array($settings['default_rules'])) {
                $sanitized['default_rules'] = array();
                $valid_rules = array(
                    'wp_admin', 'wp_login', 'wp_includes', 'wp_plugins', 'wp_themes',
                    'wp_uploads', 'readme_files', 'wp_config', 'xmlrpc', 'wp_json',
                    'feed', 'trackback', 'wp_cron', 'search', 'author', 'date_archives',
                    'tag_archives', 'attachment', 'private_pages'
                );

                foreach ($valid_rules as $rule) {
                    $sanitized['default_rules'][$rule] = !empty($settings['default_rules'][$rule]);
                }
            } else {
                // Backward compatibility: if it's a boolean, convert to array
                $enabled = (bool) $settings['default_rules'];
                $sanitized['default_rules'] = array(
                    'wp_admin' => $enabled,
                    'wp_login' => $enabled,
                    'wp_includes' => $enabled,
                    'wp_plugins' => $enabled,
                    'wp_themes' => $enabled,
                    'wp_uploads' => false, // Keep uploads accessible by default
                    'readme_files' => $enabled,
                    'wp_config' => $enabled,
                    'xmlrpc' => $enabled,
                    'wp_json' => false, // Keep API accessible by default
                    'feed' => false, // Keep feeds accessible by default
                    'trackback' => $enabled,
                    'wp_cron' => $enabled,
                    'search' => false, // Keep search accessible by default
                    'author' => false, // Keep author pages accessible by default
                    'date_archives' => false, // Keep date archives accessible by default
                    'tag_archives' => false, // Keep tag archives accessible by default
                    'attachment' => false, // Keep attachment pages accessible by default
                    'private_pages' => $enabled
                );
            }
        }

        // Sanitize custom rules
        if (isset($settings['custom_rules']) && is_array($settings['custom_rules'])) {
            $sanitized['custom_rules'] = array();
            foreach ($settings['custom_rules'] as $rule) {
                if (is_array($rule) && isset($rule['user_agent']) && isset($rule['directive']) && isset($rule['path'])) {
                    $sanitized_rule = array(
                        'user_agent' => sanitize_text_field($rule['user_agent']),
                        'directive' => in_array($rule['directive'], array('Allow', 'Disallow')) ? $rule['directive'] : 'Disallow',
                        'path' => sanitize_text_field($rule['path'])
                    );
                    
                    // Validate path starts with /
                    if (!empty($sanitized_rule['path']) && $sanitized_rule['path'][0] !== '/') {
                        $sanitized_rule['path'] = '/' . $sanitized_rule['path'];
                    }
                    
                    $sanitized['custom_rules'][] = $sanitized_rule;
                }
            }
        }

        // Sanitize additional content
        if (isset($settings['additional_content'])) {
            $sanitized['additional_content'] = sanitize_textarea_field($settings['additional_content']);
        }

        Logger::debug('Robots settings sanitized', array(
            'original_count' => count($settings),
            'sanitized_count' => count($sanitized)
        ));

        return $sanitized;
    }

    /**
     * Check if robots.txt is enabled
     *
     * @return bool True if enabled, false otherwise
     * @since 1.0.0
     */
    public static function is_robots_enabled() {
        $settings = self::get_robots_settings();
        return $settings['enabled'];
    }
}
