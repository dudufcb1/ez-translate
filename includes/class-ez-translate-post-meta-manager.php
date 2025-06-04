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
class PostMetaManager {

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
     * Translation group prefix
     *
     * @var string
     * @since 1.0.0
     */
    const GROUP_PREFIX = 'tg_';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
        Logger::info('Post Meta Manager initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
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
    public function handle_post_save($post_id, $post, $update) {
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
    public function handle_post_delete($post_id) {
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
    public static function get_post_metadata($post_id) {
        $metadata = array(
            'language' => get_post_meta($post_id, self::META_LANGUAGE, true),
            'group' => get_post_meta($post_id, self::META_GROUP, true),
            'is_landing' => get_post_meta($post_id, self::META_IS_LANDING, true),
            'seo_title' => get_post_meta($post_id, self::META_SEO_TITLE, true),
            'seo_description' => get_post_meta($post_id, self::META_SEO_DESCRIPTION, true),
        );

        // Filter out empty values
        $metadata = array_filter($metadata, function($value) {
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
    public static function set_post_language($post_id, $language_code) {
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
            Logger::log_db_operation('update', 'post_meta', array(
                'post_id' => $post_id,
                'meta_key' => self::META_LANGUAGE,
                'meta_value' => $language_code
            ));
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
    public static function set_post_group($post_id, $group_id = null) {
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
            Logger::log_db_operation('update', 'post_meta', array(
                'post_id' => $post_id,
                'meta_key' => self::META_GROUP,
                'meta_value' => $group_id
            ));
            return $group_id;
        } else {
            Logger::error('Failed to set post translation group', array('post_id' => $post_id, 'group_id' => $group_id));
            return false;
        }
    }

    /**
     * Set landing page status for a post (LEGACY - REMOVED)
     * This method is kept as a stub for backward compatibility
     *
     * @param int  $post_id    Post ID
     * @param bool $is_landing Landing page status
     * @return bool Always returns true for compatibility
     * @since 1.0.0
     */
    public static function set_post_landing_status($post_id, $is_landing) {
        // Legacy method - landing page functionality removed
        // Return true for backward compatibility with existing tests

        return true;
    }

    /**
     * Set SEO title for a post
     *
     * @param int    $post_id   Post ID
     * @param string $seo_title SEO title
     * @return bool Success status
     * @since 1.0.0
     */
    public static function set_post_seo_title($post_id, $seo_title) {
        $seo_title = sanitize_text_field($seo_title);

        $result = update_post_meta($post_id, self::META_SEO_TITLE, $seo_title);

        if ($result) {
            Logger::info('Post SEO title set', array('post_id' => $post_id, 'seo_title' => $seo_title));
            Logger::log_db_operation('update', 'post_meta', array(
                'post_id' => $post_id,
                'meta_key' => self::META_SEO_TITLE,
                'meta_value' => $seo_title
            ));
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
    public static function set_post_seo_description($post_id, $seo_description) {
        $seo_description = sanitize_textarea_field($seo_description);

        $result = update_post_meta($post_id, self::META_SEO_DESCRIPTION, $seo_description);

        if ($result) {
            Logger::info('Post SEO description set', array('post_id' => $post_id, 'seo_description' => $seo_description));
            Logger::log_db_operation('update', 'post_meta', array(
                'post_id' => $post_id,
                'meta_key' => self::META_SEO_DESCRIPTION,
                'meta_value' => $seo_description
            ));
        } else {
            Logger::error('Failed to set post SEO description', array('post_id' => $post_id, 'seo_description' => $seo_description));
        }

        return $result;
    }

    /**
     * Generate a unique translation group ID
     *
     * @return string Generated group ID
     * @since 1.0.0
     */
    public static function generate_group_id() {
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
    public static function validate_group_id($group_id) {
        // Must start with prefix and be exactly 19 characters total (tg_ + 16 chars)
        $pattern = '/^' . preg_quote(self::GROUP_PREFIX, '/') . '[a-z0-9]{16}$/';
        $is_valid = preg_match($pattern, $group_id);

        return $is_valid;
    }

    /**
     * Get landing page for a specific language (LEGACY - REMOVED)
     * This method is kept as a stub for backward compatibility
     *
     * @param string $language_code Language code
     * @return int|null Always returns null (no landing pages)
     * @since 1.0.0
     */
    public static function get_landing_page_for_language($language_code) {
        // Legacy method - landing page functionality removed
        // Return null for backward compatibility with existing tests

        return null;
    }

    /**
     * Get all posts in a translation group
     *
     * @param string $group_id Translation group ID
     * @return array Array of post IDs
     * @since 1.0.0
     */
    public static function get_posts_in_group($group_id) {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_status = 'publish'
            AND pm.meta_key = %s
            AND pm.meta_value = %s
            ORDER BY p.post_date DESC
        ", self::META_GROUP, $group_id);

        $results = $wpdb->get_col($query);

        return array_map('intval', $results);
    }

    /**
     * Get posts by language
     *
     * @param string $language_code Language code
     * @param array  $args          Additional query arguments
     * @return array Array of post IDs
     * @since 1.0.0
     */
    public static function get_posts_by_language($language_code, $args = array()) {
        global $wpdb;

        $defaults = array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'limit' => -1
        );
        $args = wp_parse_args($args, $defaults);

        $post_types = is_array($args['post_type']) ? $args['post_type'] : array($args['post_type']);
        $post_types_placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $query = $wpdb->prepare("
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_status = %s
            AND p.post_type IN ($post_types_placeholders)
            AND pm.meta_key = %s
            AND pm.meta_value = %s
            ORDER BY p.post_date DESC
        ", array_merge(array($args['post_status']), $post_types, array(self::META_LANGUAGE, $language_code)));

        if ($args['limit'] > 0) {
            $query .= $wpdb->prepare(" LIMIT %d", $args['limit']);
        }

        $results = $wpdb->get_col($query);

        return array_map('intval', $results);
    }

    /**
     * Remove all multilingual metadata for a post
     *
     * @param int $post_id Post ID
     * @return bool Success status
     * @since 1.0.0
     */
    public static function remove_post_metadata($post_id) {
        $meta_keys = array(
            self::META_LANGUAGE,
            self::META_GROUP,
            self::META_IS_LANDING,
            self::META_SEO_TITLE,
            self::META_SEO_DESCRIPTION
        );

        $success = true;
        foreach ($meta_keys as $meta_key) {
            $result = delete_post_meta($post_id, $meta_key);
            if (!$result) {
                $success = false;
                Logger::warning('Failed to remove post meta', array('post_id' => $post_id, 'meta_key' => $meta_key));
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
    public static function get_post_language($post_id) {
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
    public static function get_post_group($post_id) {
        $group = get_post_meta($post_id, self::META_GROUP, true);
        return !empty($group) ? $group : null;
    }

    /**
     * Check if post is a landing page (LEGACY - REMOVED)
     * This method is kept as a stub for backward compatibility
     *
     * @param int $post_id Post ID
     * @return bool Always returns false (no landing pages)
     * @since 1.0.0
     */
    public static function is_post_landing_page($post_id) {
        // Legacy method - landing page functionality removed
        // Return false for backward compatibility with existing tests

        return false;
    }

    /**
     * Get post SEO title
     *
     * @param int $post_id Post ID
     * @return string|null SEO title or null if not set
     * @since 1.0.0
     */
    public static function get_post_seo_title($post_id) {
        $seo_title = get_post_meta($post_id, self::META_SEO_TITLE, true);
        return !empty($seo_title) ? $seo_title : null;
    }

    /**
     * Get post SEO description
     *
     * @param int $post_id Post ID
     * @return string|null SEO description or null if not set
     * @since 1.0.0
     */
    public static function get_post_seo_description($post_id) {
        $seo_description = get_post_meta($post_id, self::META_SEO_DESCRIPTION, true);
        return !empty($seo_description) ? $seo_description : null;
    }

    /**
     * Set complete post metadata
     *
     * @param int   $post_id Post ID
     * @param array $metadata Metadata array
     * @return bool Success status
     * @since 1.0.0
     */
    public static function set_post_metadata($post_id, $metadata) {
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

        // Set landing page status
        if (isset($metadata['is_landing'])) {
            $result = self::set_post_landing_status($post_id, $metadata['is_landing']);
            if (!$result) $success = false;
        }

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

        Logger::info('Post metadata update completed', array(
            'post_id' => $post_id,
            'success' => $success,
            'metadata' => $metadata
        ));

        return $success;
    }
}