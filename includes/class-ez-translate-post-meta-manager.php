<?php

/**
 * Post Meta Manager class for EZ Translate
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
 * Post Meta Manager class for handling multilingual metadata
 *
 * @since 1.0.0
 */
class PostMetaManager
{

    /**
     * Meta key for language code
     *
     * @var string
     * @since 1.0.0
     */
    const META_LANGUAGE = '_ez_translate_language';

    /**
     * Meta key for translation group
     *
     * @var string
     * @since 1.0.0
     */
    const META_GROUP = '_ez_translate_group';

    /**
     * Meta key for landing page status
     *
     * @var string
     * @since 1.0.0
     */
    const META_IS_LANDING = '_ez_translate_is_landing';

    /**
     * Meta key for landing page language assignment
     *
     * @var string
     * @since 1.0.0
     */
    const META_LANDING_FOR_LANGUAGE = '_ez_translate_landing_for_language';

    /**
     * Meta key for SEO title
     *
     * @var string
     * @since 1.0.0
     */
    const META_SEO_TITLE = '_ez_translate_seo_title';

    /**
     * Meta key for SEO description
     *
     * @var string
     * @since 1.0.0
     */
    const META_SEO_DESCRIPTION = '_ez_translate_seo_description';

    /**
     * Meta key for OG title (Open Graph for social media)
     *
     * @var string
     * @since 1.0.0
     */
    const META_OG_TITLE = '_ez_translate_og_title';

    /**
     * Translation group prefix
     *
     * @var string
     * @since 1.0.0
     */
    const GROUP_PREFIX = 'tg_';

    /**
     * Landing pages group ID
     *
     * @var string
     * @since 1.0.0
     */
    const LANDING_PAGES_GROUP = 'landing_pages';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->init_hooks();
        Logger::info('Post Meta Manager initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // Hook into post save
        add_action('save_post', array($this, 'handle_post_save'), 10, 3);

        // Hook into post deletion
        add_action('before_delete_post', array($this, 'handle_post_delete'));
    }

    /**
     * Handle post save
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @param bool    $update  Whether this is an existing post being updated
     * @since 1.0.0
     */
    public function handle_post_save($post_id, $post, $update)
    {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Only process pages and posts for now
        if (!in_array($post->post_type, array('post', 'page'))) {
            return;
        }

        Logger::info('Processing post save', array(
            'post_id' => $post_id,
            'post_type' => $post->post_type,
            'post_title' => $post->post_title,
            'is_update' => $update
        ));
    }

    /**
     * Handle post deletion
     *
     * @param int $post_id Post ID being deleted
     * @since 1.0.0
     */
    public function handle_post_delete($post_id)
    {
        Logger::info('Processing post deletion', array('post_id' => $post_id));

        // Log current metadata before deletion
        $metadata = self::get_post_metadata($post_id);
        if (!empty($metadata)) {
            Logger::info('Post metadata before deletion', array(
                'post_id' => $post_id,
                'metadata' => $metadata
            ));
        }
    }



    /**
     * Get all multilingual metadata for a post
     *
     * @param int $post_id Post ID
     * @return array Array of metadata
     * @since 1.0.0
     */
    public static function get_post_metadata($post_id)
    {
        $metadata = array(
            'language' => get_post_meta($post_id, self::META_LANGUAGE, true),
            'group' => get_post_meta($post_id, self::META_GROUP, true),
            'is_landing' => get_post_meta($post_id, self::META_IS_LANDING, true),
            'landing_for_language' => get_post_meta($post_id, self::META_LANDING_FOR_LANGUAGE, true),
            'seo_title' => get_post_meta($post_id, self::META_SEO_TITLE, true),
            'seo_description' => get_post_meta($post_id, self::META_SEO_DESCRIPTION, true),
            'og_title' => get_post_meta($post_id, self::META_OG_TITLE, true),
        );

        // Filter out empty values
        $metadata = array_filter($metadata, function ($value) {
            return !empty($value);
        });

        return $metadata;
    }

    /**
     * Set language for a post
     *
     * @param int    $post_id      Post ID
     * @param string $language_code Language code
     * @return bool Success status
     * @since 1.0.0
     */
    public static function set_post_language($post_id, $language_code)
    {
        // Validate language code
        $language_code = sanitize_text_field($language_code);
        if (empty($language_code)) {
            Logger::error('Invalid language code provided', array('post_id' => $post_id, 'code' => $language_code));
            return false;
        }

        // Verify language exists
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        $language = \EZTranslate\LanguageManager::get_language($language_code);
        if (!$language) {
            Logger::error('Language not found', array('post_id' => $post_id, 'code' => $language_code));
            return false;
        }

        $result = update_post_meta($post_id, self::META_LANGUAGE, $language_code);

        if ($result) {
            Logger::info('Post language set', array('post_id' => $post_id, 'language' => $language_code));
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            // These are logging parameters, not database queries
            Logger::log_db_operation('update', 'post_meta', array(
                'post_id' => $post_id,
                'meta_key' => self::META_LANGUAGE,
                'meta_value' => $language_code
            ));
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        } else {
            Logger::error('Failed to set post language', array('post_id' => $post_id, 'language' => $language_code));
        }

        return $result;
    }

    /**
     * Set translation group for a post
     *
     * @param int    $post_id Post ID
     * @param string $group_id Group ID (optional, will generate if not provided)
     * @return string|false Group ID on success, false on failure
     * @since 1.0.0
     */
    public static function set_post_group($post_id, $group_id = null)
    {
        // Generate group ID if not provided
        if (empty($group_id)) {
            $group_id = self::generate_group_id();
        }

        // Validate group ID format
        if (!self::validate_group_id($group_id)) {
            Logger::error('Invalid group ID format', array('post_id' => $post_id, 'group_id' => $group_id));
            return false;
        }

        $result = update_post_meta($post_id, self::META_GROUP, $group_id);

        if ($result) {
            Logger::info('Post translation group set', array('post_id' => $post_id, 'group_id' => $group_id));
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            // These are logging parameters, not database queries
            Logger::log_db_operation('update', 'post_meta', array(
                'post_id' => $post_id,
                'meta_key' => self::META_GROUP,
                'meta_value' => $group_id
            ));
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            return $group_id;
        } else {
            Logger::error('Failed to set post translation group', array('post_id' => $post_id, 'group_id' => $group_id));
            return false;
        }
    }



    /**
     * Set SEO title for a post
     *
     * @param int    $post_id   Post ID
     * @param string $seo_title SEO title
     * @return bool Success status
     * @since 1.0.0
     */
    public static function set_post_seo_title($post_id, $seo_title)
    {
        $seo_title = sanitize_text_field($seo_title);

        $result = update_post_meta($post_id, self::META_SEO_TITLE, $seo_title);

        if ($result) {
            Logger::info('Post SEO title set', array('post_id' => $post_id, 'seo_title' => $seo_title));
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            // These are logging parameters, not database queries
            Logger::log_db_operation('update', 'post_meta', array(
                'post_id' => $post_id,
                'meta_key' => self::META_SEO_TITLE,
                'meta_value' => $seo_title
            ));
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        } else {
            Logger::error('Failed to set post SEO title', array('post_id' => $post_id, 'seo_title' => $seo_title));
        }

        return $result;
    }

    /**
     * Set SEO description for a post
     *
     * @param int    $post_id        Post ID
     * @param string $seo_description SEO description
     * @return bool Success status
     * @since 1.0.0
     */
    public static function set_post_seo_description($post_id, $seo_description)
    {
        $seo_description = sanitize_textarea_field($seo_description);

        $result = update_post_meta($post_id, self::META_SEO_DESCRIPTION, $seo_description);

        if ($result) {
            Logger::info('Post SEO description set', array('post_id' => $post_id, 'seo_description' => $seo_description));
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            // These are logging parameters, not database queries
            Logger::log_db_operation('update', 'post_meta', array(
                'post_id' => $post_id,
                'meta_key' => self::META_SEO_DESCRIPTION,
                'meta_value' => $seo_description
            ));
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        } else {
            Logger::error('Failed to set post SEO description', array('post_id' => $post_id, 'seo_description' => $seo_description));
        }

        return $result;
    }

    /**
     * Set OG title for a post (Open Graph for social media)
     *
     * @param int    $post_id  Post ID
     * @param string $og_title OG title
     * @return bool Success status
     * @since 1.0.0
     */
    public static function set_post_og_title($post_id, $og_title)
    {
        $og_title = sanitize_text_field($og_title);

        $result = update_post_meta($post_id, self::META_OG_TITLE, $og_title);

        if ($result) {
            Logger::info('Post OG title set', array('post_id' => $post_id, 'og_title' => $og_title));
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            // These are logging parameters, not database queries
            Logger::log_db_operation('update', 'post_meta', array(
                'post_id' => $post_id,
                'meta_key' => self::META_OG_TITLE,
                'meta_value' => $og_title
            ));
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        } else {
            Logger::error('Failed to set post OG title', array('post_id' => $post_id, 'og_title' => $og_title));
        }

        return $result;
    }

    /**
     * Generate a unique translation group ID
     *
     * @param bool $is_landing Whether this is for a landing page
     * @return string Generated group ID
     * @since 1.0.0
     */
    public static function generate_group_id($is_landing = false)
    {
        // For landing pages, use the hardcoded group ID
        if ($is_landing) {
            return self::LANDING_PAGES_GROUP;
        }

        // Generate 16 random alphanumeric characters
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $group_id = self::GROUP_PREFIX;

        for ($i = 0; $i < 16; $i++) {
            $group_id .= $characters[wp_rand(0, strlen($characters) - 1)];
        }

        return $group_id;
    }

    /**
     * Validate translation group ID format
     *
     * @param string $group_id Group ID to validate
     * @return bool True if valid, false otherwise
     * @since 1.0.0
     */
    public static function validate_group_id($group_id)
    {
        // Special case for landing pages group
        if ($group_id === self::LANDING_PAGES_GROUP) {
            return true;
        }

        // Must start with prefix and be exactly 19 characters total (tg_ + 16 chars)
        $pattern = '/^' . preg_quote(self::GROUP_PREFIX, '/') . '[a-z0-9]{16}$/';
        $is_valid = preg_match($pattern, $group_id);

        return $is_valid;
    }



    /**
     * Get all posts in a translation group
     *
     * @param string $group_id Translation group ID
     * @param array $post_statuses Post statuses to include (default: ['publish'])
     * @return array Array of post IDs
     * @since 1.0.0
     */
    public static function get_posts_in_group($group_id, $post_statuses = array('publish'))
    {
        // Ensure post_statuses is an array
        if (!is_array($post_statuses)) {
            $post_statuses = array($post_statuses);
        }

        // Sanitize inputs
        $group_id = sanitize_text_field($group_id);
        $post_statuses = array_map('sanitize_text_field', $post_statuses);

        // Check cache first
        $cache_key = 'ez_translate_posts_in_group_' . md5($group_id . serialize($post_statuses));
        $cached_result = wp_cache_get($cache_key, 'ez_translate');

        if ($cached_result !== false) {
            return $cached_result;
        }

        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        // Meta query is necessary for translation group functionality
        // Use WP_Query approach for complex IN clauses
        $query_args = array(
            'post_type' => array('post', 'page'),
            'post_status' => $post_statuses,
            'meta_query' => array(
                array(
                    'key' => self::META_GROUP,
                    'value' => $group_id,
                    'compare' => '='
                )
            ),
            'fields' => 'ids',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $query = new \WP_Query($query_args);
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

        $result = array_map('intval', $query->posts);

        // Cache for 10 minutes (translation groups don't change frequently)
        wp_cache_set($cache_key, $result, 'ez_translate', 600);

        return $result;
    }

    /**
     * Get posts by language
     *
     * @param string $language_code Language code
     * @param array  $args          Additional query arguments
     * @return array Array of post IDs
     * @since 1.0.0
     */
    public static function get_posts_by_language($language_code, $args = array())
    {
        $defaults = array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'limit' => -1
        );
        $args = wp_parse_args($args, $defaults);

        // Sanitize inputs
        $language_code = sanitize_text_field($language_code);
        $args['post_status'] = sanitize_text_field($args['post_status']);

        if (!is_array($args['post_type'])) {
            $args['post_type'] = array($args['post_type']);
        }
        $args['post_type'] = array_map('sanitize_text_field', $args['post_type']);

        // Check cache first
        $cache_key = 'ez_translate_posts_by_language_' . md5($language_code . serialize($args));
        $cached_result = wp_cache_get($cache_key, 'ez_translate');

        if ($cached_result !== false) {
            return $cached_result;
        }

        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        // Meta query is necessary for language-based post filtering
        // Use WP_Query approach for safe querying
        $query_args = array(
            'post_type' => $args['post_type'],
            'post_status' => $args['post_status'],
            'meta_query' => array(
                array(
                    'key' => self::META_LANGUAGE,
                    'value' => $language_code,
                    'compare' => '='
                )
            ),
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC'
        );

        // Handle limit
        if ($args['limit'] > 0) {
            $query_args['posts_per_page'] = intval($args['limit']);
        } else {
            $query_args['posts_per_page'] = -1;
        }

        $query = new \WP_Query($query_args);
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

        $result = array_map('intval', $query->posts);

        // Cache for 10 minutes (language-based posts don't change frequently)
        wp_cache_set($cache_key, $result, 'ez_translate', 600);

        return $result;
    }

    /**
     * Remove all multilingual metadata for a post
     *
     * @param int $post_id Post ID
     * @return bool Success status
     * @since 1.0.0
     */
    public static function remove_post_metadata($post_id)
    {
        $meta_keys = array(
            self::META_LANGUAGE,
            self::META_GROUP,
            self::META_IS_LANDING,
            self::META_LANDING_FOR_LANGUAGE,
            self::META_SEO_TITLE,
            self::META_SEO_DESCRIPTION,
            self::META_OG_TITLE
        );

        $success = true;
        foreach ($meta_keys as $meta_key) {
            $result = delete_post_meta($post_id, $meta_key);
            if (!$result) {
                $success = false;
                // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                // This is a logging parameter, not a database query
                Logger::warning('Failed to remove post meta', array('post_id' => $post_id, 'meta_key' => $meta_key));
                // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            }
        }

        if ($success) {
            Logger::info('All multilingual metadata removed', array('post_id' => $post_id));
        } else {
            Logger::error('Some metadata could not be removed', array('post_id' => $post_id));
        }

        return $success;
    }

    /**
     * Get post language
     *
     * @param int $post_id Post ID
     * @return string|null Language code or null if not set
     * @since 1.0.0
     */
    public static function get_post_language($post_id)
    {
        $language = get_post_meta($post_id, self::META_LANGUAGE, true);
        return !empty($language) ? $language : null;
    }

    /**
     * Get post translation group
     *
     * @param int $post_id Post ID
     * @return string|null Group ID or null if not set
     * @since 1.0.0
     */
    public static function get_post_group($post_id)
    {
        $group = get_post_meta($post_id, self::META_GROUP, true);
        return !empty($group) ? $group : null;
    }





    /**
     * Set complete post metadata
     *
     * @param int   $post_id Post ID
     * @param array $metadata Metadata array
     * @return bool Success status
     * @since 1.0.0
     */
    public static function set_post_metadata($post_id, $metadata)
    {
        $success = true;

        // Set language
        if (isset($metadata['language'])) {
            $result = self::set_post_language($post_id, $metadata['language']);
            if (!$result) $success = false;
        }

        // Set or generate group
        if (isset($metadata['group'])) {
            $result = self::set_post_group($post_id, $metadata['group']);
            if (!$result) $success = false;
        }

        // Landing page functionality removed - skip is_landing metadata

        // Set SEO title
        if (isset($metadata['seo_title'])) {
            $result = self::set_post_seo_title($post_id, $metadata['seo_title']);
            if (!$result) $success = false;
        }

        // Set SEO description
        if (isset($metadata['seo_description'])) {
            $result = self::set_post_seo_description($post_id, $metadata['seo_description']);
            if (!$result) $success = false;
        }

        // Set OG title
        if (isset($metadata['og_title'])) {
            $result = self::set_post_og_title($post_id, $metadata['og_title']);
            if (!$result) $success = false;
        }

        Logger::info('Post metadata update completed', array(
            'post_id' => $post_id,
            'success' => $success,
            'metadata' => $metadata
        ));

        return $success;
    }

    /**
     * Set a post as landing page with the landing pages group
     *
     * @param int    $post_id       Post ID
     * @param string $language_code Language code this page is landing for
     * @return bool Success status
     * @since 1.0.0
     */
    public static function set_as_landing_page($post_id, $language_code = '')
    {
        // Mark as landing page
        $result1 = update_post_meta($post_id, self::META_IS_LANDING, true);

        // Assign to landing pages group
        $result2 = self::set_post_group($post_id, self::LANDING_PAGES_GROUP);

        // Set bidirectional relationship if language code provided
        $result3 = true;
        if (!empty($language_code)) {
            $result3 = update_post_meta($post_id, self::META_LANDING_FOR_LANGUAGE, sanitize_text_field($language_code));
        }

        $success = $result1 && $result2 && $result3;

        if ($success) {
            Logger::info('Post set as landing page', array(
                'post_id' => $post_id,
                'group_id' => self::LANDING_PAGES_GROUP,
                'language_code' => $language_code
            ));
        } else {
            Logger::error('Failed to set post as landing page', array('post_id' => $post_id));
        }

        return $success;
    }

    /**
     * Remove landing page status and group from a post
     *
     * @param int $post_id Post ID
     * @return bool Success status
     * @since 1.0.0
     */
    public static function remove_landing_page_status($post_id)
    {
        // Remove landing page status
        $result1 = delete_post_meta($post_id, self::META_IS_LANDING);

        // Remove bidirectional language relationship
        $result2 = delete_post_meta($post_id, self::META_LANDING_FOR_LANGUAGE);

        // Remove from landing pages group (only if it's currently in that group)
        $current_group = get_post_meta($post_id, self::META_GROUP, true);
        $result3 = true;

        if ($current_group === self::LANDING_PAGES_GROUP) {
            $result3 = delete_post_meta($post_id, self::META_GROUP);
        }

        $success = $result1 && $result2 && $result3;

        if ($success) {
            Logger::info('Landing page status removed', array(
                'post_id' => $post_id,
                'previous_group' => $current_group
            ));
        } else {
            Logger::error('Failed to remove landing page status', array('post_id' => $post_id));
        }

        return $success;
    }

    /**
     * Check if a post is in the landing pages group
     *
     * @param int $post_id Post ID
     * @return bool True if in landing pages group, false otherwise
     * @since 1.0.0
     */
    public static function is_in_landing_pages_group($post_id)
    {
        $group_id = get_post_meta($post_id, self::META_GROUP, true);
        return $group_id === self::LANDING_PAGES_GROUP;
    }

    /**
     * Find landing page for a specific language
     *
     * @param string $language_code Language code
     * @return int|null Post ID of landing page or null if not found
     * @since 1.0.0
     */
    public static function find_landing_page_for_language($language_code)
    {
        $language_code = sanitize_text_field($language_code);

        // Check cache first
        $cache_key = 'ez_translate_landing_for_' . $language_code;
        $cached_result = wp_cache_get($cache_key, 'ez_translate');

        if ($cached_result !== false) {
            return $cached_result;
        }

        // Query for pages with the bidirectional metadata
        $query_args = array(
            'post_type' => 'page',
            'post_status' => array('publish', 'draft'),
            //phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query' => array(
                array(
                    'key' => self::META_LANDING_FOR_LANGUAGE,
                    'value' => $language_code,
                    'compare' => '='
                )
            ),
            //phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'fields' => 'ids',
            'posts_per_page' => 1
        );

        $query = new \WP_Query($query_args);
        $result = !empty($query->posts) ? intval($query->posts[0]) : null;

        // Cache for 10 minutes
        wp_cache_set($cache_key, $result, 'ez_translate', 600);

        if ($result) {
            Logger::info('Landing page found for language', array(
                'language_code' => $language_code,
                'post_id' => $result
            ));
        }

        return $result;
    }

    /**
     * Get all orphaned landing pages (pages marked as landing but not in language config)
     *
     * @return array Array of post IDs
     * @since 1.0.0
     */
    public static function get_orphaned_landing_pages()
    {
        // Get all pages marked as landing pages
        $query_args = array(
            'post_type' => 'page',
            'post_status' => array('publish', 'draft'),
            //phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query' => array(
                array(
                    'key' => self::META_IS_LANDING,
                    'value' => true,
                    'compare' => '='
                )
            ),
            //phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'fields' => 'ids',
            'posts_per_page' => -1
        );

        $query = new \WP_Query($query_args);
        $landing_pages = $query->posts;

        if (empty($landing_pages)) {
            return array();
        }

        // Get all language configurations
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        $languages = \EZTranslate\LanguageManager::get_languages();
        $configured_landing_ids = array();

        foreach ($languages as $language) {
            if (!empty($language['landing_page_id']) && $language['landing_page_id'] > 0) {
                $configured_landing_ids[] = intval($language['landing_page_id']);
            }
        }

        // Also check main landing page
        $main_landing_id = get_option('ez_translate_main_landing_page_id', 0);
        if ($main_landing_id > 0) {
            $configured_landing_ids[] = intval($main_landing_id);
        }

        // Find orphaned pages (marked as landing but not in any language config)
        $orphaned = array_diff(array_map('intval', $landing_pages), $configured_landing_ids);

        return array_values($orphaned);
    }
}
