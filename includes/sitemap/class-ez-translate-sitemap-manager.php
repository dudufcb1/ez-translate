<?php
/**
 * Sitemap Manager for EZ Translate
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate\Sitemap;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use EZTranslate\Logger;
use EZTranslate\LanguageManager;

/**
 * Sitemap Manager class
 *
 * Handles URL interception, rewrite rules, and sitemap generation coordination
 *
 * @since 1.0.0
 */
class SitemapManager {



    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
        $this->init_cache();
        Logger::info('SitemapManager initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Register rewrite rules on init
        add_action('init', array($this, 'register_sitemap_rewrite_rules'));

        // Ensure rewrite rules are properly set up (after init)
        add_action('init', array($this, 'ensure_rewrite_rules'), 20);

        // Handle sitemap requests
        add_action('template_redirect', array($this, 'handle_sitemap_request'));

        // Flush rewrite rules on activation (handled by main plugin)
        add_action('ez_translate_activated', array($this, 'flush_rewrite_rules'));

        // Cache invalidation hooks
        add_action('save_post', array($this, 'invalidate_cache_on_post_save'));
        add_action('deleted_post', array($this, 'invalidate_cache_on_post_delete'));
        add_action('created_term', array($this, 'invalidate_cache_on_term_change'));
        add_action('edited_term', array($this, 'invalidate_cache_on_term_change'));
        add_action('deleted_term', array($this, 'invalidate_cache_on_term_change'));

        // Scheduled rewrite rules flush
        add_action('ez_translate_flush_rewrite_rules', array($this, 'flush_rewrite_rules'));

        Logger::debug('SitemapManager hooks initialized');
    }

    /**
     * Register sitemap rewrite rules
     *
     * @since 1.0.0
     */
    public function register_sitemap_rewrite_rules() {
        // Main sitemap (redirects to index)
        add_rewrite_rule(
            '^sitemap\.xml$',
            'index.php?ez_translate_sitemap=index',
            'top'
        );

        // Sitemap index
        add_rewrite_rule(
            '^sitemap-index\.xml$',
            'index.php?ez_translate_sitemap=index',
            'top'
        );

        // Posts sitemap (all languages)
        add_rewrite_rule(
            '^sitemap-posts\.xml$',
            'index.php?ez_translate_sitemap=posts',
            'top'
        );

        // Pages sitemap (all languages)
        add_rewrite_rule(
            '^sitemap-pages\.xml$',
            'index.php?ez_translate_sitemap=pages',
            'top'
        );

        // Taxonomies sitemap (default language)
        add_rewrite_rule(
            '^sitemap-taxonomies\.xml$',
            'index.php?ez_translate_sitemap=taxonomies',
            'top'
        );

        // Language-specific sitemaps
        add_rewrite_rule(
            '^sitemap-posts-([a-z]{2,5})\.xml$',
            'index.php?ez_translate_sitemap=posts&ez_translate_language=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^sitemap-pages-([a-z]{2,5})\.xml$',
            'index.php?ez_translate_sitemap=pages&ez_translate_language=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^sitemap-taxonomies-([a-z]{2,5})\.xml$',
            'index.php?ez_translate_sitemap=taxonomies&ez_translate_language=$matches[1]',
            'top'
        );

        // Register query vars
        add_filter('query_vars', array($this, 'add_query_vars'));

        Logger::debug('Sitemap rewrite rules registered');
    }

    /**
     * Add custom query variables
     *
     * @param array $vars Existing query variables
     * @return array Modified query variables
     * @since 1.0.0
     */
    public function add_query_vars($vars) {
        $vars[] = 'ez_translate_sitemap';
        $vars[] = 'ez_translate_language';
        return $vars;
    }

    /**
     * Handle sitemap requests
     *
     * @since 1.0.0
     */
    public function handle_sitemap_request() {
        $sitemap_type = get_query_var('ez_translate_sitemap');
        
        if (empty($sitemap_type)) {
            return;
        }

        $language = get_query_var('ez_translate_language');
        
        Logger::info('Sitemap request detected', array(
            'type' => $sitemap_type,
            'language' => $language,
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ));

        // Verify sitemap is enabled
        if (!$this->is_sitemap_enabled()) {
            Logger::warning('Sitemap request but sitemap is disabled');
            status_header(404);
            exit;
        }

        // Validate language if provided
        if (!empty($language) && !$this->is_valid_language($language)) {
            Logger::warning('Invalid language in sitemap request', array('language' => $language));
            status_header(404);
            exit;
        }

        try {
            // Generate and serve sitemap
            $this->serve_sitemap($sitemap_type, $language);
        } catch (\Exception $e) {
            Logger::error('Error serving sitemap', array(
                'error' => $e->getMessage(),
                'type' => $sitemap_type,
                'language' => $language
            ));
            status_header(500);
            exit;
        }
    }

    /**
     * Serve sitemap XML
     *
     * @param string $type Sitemap type
     * @param string $language Language code (optional)
     * @since 1.0.0
     */
    private function serve_sitemap($type, $language = '') {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-cache.php';

        // Check if cached version exists
        $cached_xml = \EZTranslate\Sitemap\SitemapCache::get_cached($type, $language);

        if ($cached_xml !== false) {
            // Serve from cache
            $this->set_sitemap_headers();
            echo $cached_xml;
            exit;
        }

        // Generate fresh sitemap
        switch ($type) {
            case 'index':
                $xml = $this->generate_sitemap_index();
                break;
            case 'posts':
                $xml = $this->generate_posts_sitemap($language);
                break;
            case 'pages':
                $xml = $this->generate_pages_sitemap($language);
                break;
            case 'taxonomies':
                $xml = $this->generate_taxonomies_sitemap($language);
                break;
            default:
                Logger::warning('Unknown sitemap type requested', array('type' => $type));
                status_header(404);
                exit;
        }

        // Cache the generated sitemap
        \EZTranslate\Sitemap\SitemapCache::cache_sitemap($type, $xml, $language);

        // Set headers and output XML
        $this->set_sitemap_headers();
        echo $xml;

        Logger::info('Sitemap served successfully', array(
            'type' => $type,
            'language' => $language,
            'size' => strlen($xml),
            'from_cache' => false
        ));

        exit;
    }

    /**
     * Set appropriate HTTP headers for sitemap
     *
     * @since 1.0.0
     */
    private function set_sitemap_headers() {
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: max-age=3600');
        
        Logger::debug('Sitemap headers set');
    }

    /**
     * Check if sitemap functionality is enabled
     *
     * @return bool
     * @since 1.0.0
     */
    private function is_sitemap_enabled() {
        $settings = get_option('ez_translate_sitemap_settings', array());
        return isset($settings['enabled']) ? (bool) $settings['enabled'] : true; // Default enabled
    }

    /**
     * Validate language code
     *
     * @param string $language Language code to validate
     * @return bool
     * @since 1.0.0
     */
    private function is_valid_language($language) {
        $languages = LanguageManager::get_enabled_languages();
        
        foreach ($languages as $lang) {
            if ($lang['code'] === $language) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate sitemap index XML
     *
     * @return string XML content
     * @since 1.0.0
     */
    private function generate_sitemap_index() {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-generator.php';
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-index.php';

        $generator = new \EZTranslate\Sitemap\SitemapIndex();
        return $generator->generate();
    }

    /**
     * Generate posts sitemap XML
     *
     * @param string $language Language code (optional)
     * @return string XML content
     * @since 1.0.0
     */
    private function generate_posts_sitemap($language = '') {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-generator.php';
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-posts.php';

        $generator = new \EZTranslate\Sitemap\SitemapPosts();
        return $generator->generate($language);
    }

    /**
     * Generate pages sitemap XML
     *
     * @param string $language Language code (optional)
     * @return string XML content
     * @since 1.0.0
     */
    private function generate_pages_sitemap($language = '') {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-generator.php';
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-pages.php';

        $generator = new \EZTranslate\Sitemap\SitemapPages();
        return $generator->generate($language);
    }

    /**
     * Generate taxonomies sitemap XML
     *
     * @param string $language Language code (optional)
     * @return string XML content
     * @since 1.0.0
     */
    private function generate_taxonomies_sitemap($language = '') {
        // Placeholder - will be implemented in next step
        Logger::debug('Generating taxonomies sitemap', array('language' => $language));
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= '<!-- Generated by EZ Translate Plugin -->' . "\n";
        $xml .= '</urlset>';
        
        return $xml;
    }

    /**
     * Initialize cache system
     *
     * @since 1.0.0
     */
    private function init_cache() {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-cache.php';
        \EZTranslate\Sitemap\SitemapCache::init();
    }

    /**
     * Ensure rewrite rules are properly registered
     *
     * @since 1.0.0
     */
    public function ensure_rewrite_rules() {
        // Check if WordPress rewrite system is available
        global $wp_rewrite;
        if (!$wp_rewrite || !is_object($wp_rewrite)) {
            Logger::debug('wp_rewrite not available yet, skipping rewrite rules check');
            return;
        }

        // Check if our rewrite rules exist
        $rules = $wp_rewrite->wp_rewrite_rules();

        $sitemap_rule_exists = false;
        if (is_array($rules)) {
            foreach ($rules as $pattern => $rewrite) {
                if (strpos($pattern, 'sitemap') !== false && strpos($rewrite, 'ez_translate_sitemap') !== false) {
                    $sitemap_rule_exists = true;
                    break;
                }
            }
        }

        // If rules don't exist, register them and flush
        if (!$sitemap_rule_exists) {
            Logger::warning('Sitemap rewrite rules not found, registering and flushing');

            // Only flush if we're not in admin and not during AJAX requests
            if (!is_admin() && !wp_doing_ajax()) {
                flush_rewrite_rules();
                Logger::info('Rewrite rules flushed for sitemap');
            } else {
                // Schedule a flush for later
                if (!wp_next_scheduled('ez_translate_flush_rewrite_rules')) {
                    wp_schedule_single_event(time() + 5, 'ez_translate_flush_rewrite_rules');
                    Logger::debug('Scheduled rewrite rules flush');
                }
            }
        } else {
            Logger::debug('Sitemap rewrite rules found and working');
        }
    }

    /**
     * Invalidate cache when post is saved
     *
     * @param int $post_id Post ID
     * @since 1.0.0
     */
    public function invalidate_cache_on_post_save($post_id) {
        $post = get_post($post_id);

        if (!$post || !in_array($post->post_status, array('publish', 'private'))) {
            return;
        }

        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-cache.php';

        if ($post->post_type === 'post') {
            \EZTranslate\Sitemap\SitemapCache::invalidate('posts', 'all');
            \EZTranslate\Sitemap\SitemapCache::invalidate('index');
        } elseif ($post->post_type === 'page') {
            \EZTranslate\Sitemap\SitemapCache::invalidate('pages', 'all');
            \EZTranslate\Sitemap\SitemapCache::invalidate('index');
        }

        Logger::debug('Cache invalidated for post save', array(
            'post_id' => $post_id,
            'post_type' => $post->post_type
        ));
    }

    /**
     * Invalidate cache when post is deleted
     *
     * @param int $post_id Post ID
     * @since 1.0.0
     */
    public function invalidate_cache_on_post_delete($post_id) {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-cache.php';

        // Invalidate all caches since we don't know the post type at this point
        \EZTranslate\Sitemap\SitemapCache::invalidate('all');

        Logger::debug('Cache invalidated for post deletion', array('post_id' => $post_id));
    }

    /**
     * Invalidate cache when taxonomy terms change
     *
     * @since 1.0.0
     */
    public function invalidate_cache_on_term_change() {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-cache.php';

        \EZTranslate\Sitemap\SitemapCache::invalidate('taxonomies', 'all');
        \EZTranslate\Sitemap\SitemapCache::invalidate('index');

        Logger::debug('Cache invalidated for term change');
    }

    /**
     * Flush rewrite rules
     *
     * @since 1.0.0
     */
    public function flush_rewrite_rules() {
        flush_rewrite_rules();
        Logger::info('Sitemap rewrite rules flushed');
    }
}
