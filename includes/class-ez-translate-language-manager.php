<?php
/**
 * Language Manager class for EZ Translate
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
 * Language Manager class for handling CRUD operations on languages
 *
 * @since 1.0.0
 */
class LanguageManager {

    /**
     * Option name for storing languages
     *
     * @var string
     * @since 1.0.0
     */
    const OPTION_NAME = 'ez_translate_languages';

    /**
     * Cache key for languages transient
     *
     * @var string
     * @since 1.0.0
     */
    const CACHE_KEY = 'ez_translate_languages_cache';

    /**
     * Cache expiration time (1 hour)
     *
     * @var int
     * @since 1.0.0
     */
    const CACHE_EXPIRATION = 3600;

    /**
     * Option name for storing API settings
     *
     * @var string
     * @since 1.0.0
     */
    const API_OPTION_NAME = 'ez_translate_api_settings';

    /**
     * Get all languages
     *
     * @param bool $use_cache Whether to use cached data
     * @return array Array of language objects
     * @since 1.0.0
     */
    public static function get_languages($use_cache = true) {
        if ($use_cache) {
            $cached_languages = get_transient(self::CACHE_KEY);
            if ($cached_languages !== false) {
                return $cached_languages;
            }
        }

        $languages = get_option(self::OPTION_NAME, array());
        
        // Ensure languages is always an array
        if (!is_array($languages)) {
            $languages = array();
            Logger::warning('Languages option was not an array, resetting to empty array');
        }

        // Cache the result
        if ($use_cache) {
            set_transient(self::CACHE_KEY, $languages, self::CACHE_EXPIRATION);
        }

        Logger::log_db_operation('read', self::OPTION_NAME, array('count' => count($languages)));
        return $languages;
    }

    /**
     * Get a specific language by code
     *
     * @param string $code Language code
     * @return array|null Language data or null if not found
     * @since 1.0.0
     */
    public static function get_language($code) {
        if (empty($code)) {
            Logger::warning('Empty language code provided');
            return null;
        }

        $languages = self::get_languages();

        foreach ($languages as $language) {
            if (isset($language['code']) && $language['code'] === $code) {
                return $language;
            }
        }

        return null;
    }

    /**
     * Add a new language
     *
     * @param array $language_data Language data
     * @param array|null $landing_page_data Landing page data (optional, if not provided, auto-creates basic landing page)
     * @return bool|array|WP_Error True on success, array with landing_page_id if landing page created, WP_Error on failure
     * @since 1.0.0
     */
    public static function add_language($language_data, $landing_page_data = null) {
        Logger::info('Adding new language', array('data' => $language_data, 'auto_create_landing' => empty($landing_page_data)));

        // Validate language data
        $validation_result = self::validate_language_data($language_data);
        if (is_wp_error($validation_result)) {
            Logger::error('Language validation failed', array('errors' => $validation_result->get_error_messages()));
            return $validation_result;
        }

        // Check for duplicate code
        if (self::language_code_exists($language_data['code'])) {
            $error = new \WP_Error('duplicate_code', __('Language code already exists.', 'ez-translate'));
            Logger::error('Duplicate language code', array('code' => $language_data['code']));
            return $error;
        }

        // Check for duplicate slug
        if (self::language_slug_exists($language_data['slug'])) {
            $error = new \WP_Error('duplicate_slug', __('Language slug already exists.', 'ez-translate'));
            Logger::error('Duplicate language slug', array('slug' => $language_data['slug']));
            return $error;
        }

        // Auto-create landing page data if not provided
        if (empty($landing_page_data)) {
            $landing_page_data = self::generate_default_landing_page_data($language_data);
        }

        // Create landing page first (before saving language to ensure we have the ID)
        $landing_page_result = self::create_landing_page_for_language($language_data['code'], $landing_page_data);

        if (is_wp_error($landing_page_result)) {
            Logger::error('Failed to create landing page for new language', array(
                'language_code' => $language_data['code'],
                'error' => $landing_page_result->get_error_message()
            ));
            return $landing_page_result;
        }

        // Add landing page ID to language data
        $language_data['landing_page_id'] = $landing_page_result;

        // Get current languages
        $languages = self::get_languages(false);

        // Add the new language
        $languages[] = $language_data;

        // Save to database
        $result = update_option(self::OPTION_NAME, $languages);

        if ($result) {
            // Clear cache
            delete_transient(self::CACHE_KEY);

            Logger::info('Language added successfully with landing page', array(
                'code' => $language_data['code'],
                'name' => $language_data['name'],
                'landing_page_id' => $landing_page_result
            ));
            Logger::log_db_operation('create', self::OPTION_NAME, $language_data);

            return array('success' => true, 'landing_page_id' => $landing_page_result);
        } else {
            // If language save failed, clean up the created landing page
            wp_delete_post($landing_page_result, true);
            $error = new \WP_Error('save_failed', __('Failed to save language to database.', 'ez-translate'));
            Logger::error('Failed to save language, cleaned up landing page', array(
                'code' => $language_data['code'],
                'deleted_page_id' => $landing_page_result
            ));
            return $error;
        }
    }

    /**
     * Update an existing language
     *
     * @param string $code Language code to update
     * @param array $language_data New language data
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public static function update_language($code, $language_data) {
        Logger::info('Updating language', array('code' => $code, 'data' => $language_data));

        if (empty($code)) {
            $error = new \WP_Error('empty_code', __('Language code cannot be empty.', 'ez-translate'));
            Logger::error('Empty language code for update');
            return $error;
        }

        // Sanitize only the allowed fields for updates
        $sanitized_data = self::sanitize_language_data_for_update($language_data);

        // Get current languages
        $languages = self::get_languages(false);
        $language_found = false;

        // Find and update the language
        foreach ($languages as $index => $language) {
            if (isset($language['code']) && $language['code'] === $code) {
                // For updates, preserve critical fields and only allow safe changes
                // Preserve: code, slug, landing_page_id (immutable for data integrity)
                // Allow: enabled, site_name, site_title, site_description, native_name, flag, rtl

                $updated_language = $language; // Start with existing data

                // Apply only the sanitized allowed fields
                foreach ($sanitized_data as $key => $value) {
                    $updated_language[$key] = $value;
                }

                $languages[$index] = $updated_language;
                $language_found = true;
                break;
            }
        }

        if (!$language_found) {
            $error = new \WP_Error('language_not_found', __('Language not found.', 'ez-translate'));
            Logger::error('Language not found for update', array('code' => $code));
            return $error;
        }

        // Save to database
        $result = update_option(self::OPTION_NAME, $languages);

        if ($result) {
            // Clear cache
            delete_transient(self::CACHE_KEY);
            
            Logger::info('Language updated successfully', array('code' => $code, 'new_data' => $language_data));
            Logger::log_db_operation('update', self::OPTION_NAME, $language_data);
            return true;
        } else {
            $error = new \WP_Error('save_failed', __('Failed to save updated language to database.', 'ez-translate'));
            Logger::error('Failed to save updated language', array('code' => $code, 'data' => $language_data));
            return $error;
        }
    }

    /**
     * Delete a language
     *
     * @param string $code Language code to delete
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public static function delete_language($code) {
        Logger::info('Deleting language', array('code' => $code));

        if (empty($code)) {
            $error = new \WP_Error('empty_code', __('Language code cannot be empty.', 'ez-translate'));
            Logger::error('Empty language code for deletion');
            return $error;
        }

        // Get current languages
        $languages = self::get_languages(false);
        $language_found = false;
        $updated_languages = array();
        $deleted_language = null;

        // Remove the language and store reference for cleanup
        foreach ($languages as $language) {
            if (isset($language['code']) && $language['code'] === $code) {
                $language_found = true;
                $deleted_language = $language;
            } else {
                $updated_languages[] = $language;
            }
        }

        if (!$language_found) {
            $error = new \WP_Error('language_not_found', __('Language not found.', 'ez-translate'));
            Logger::error('Language not found for deletion', array('code' => $code));
            return $error;
        }

        // Save to database
        $result = update_option(self::OPTION_NAME, $updated_languages);

        if ($result) {
            // Clear cache
            delete_transient(self::CACHE_KEY);

            // Delete associated landing page if it exists
            if ($deleted_language && !empty($deleted_language['landing_page_id'])) {
                $landing_page_id = $deleted_language['landing_page_id'];
                $delete_result = wp_delete_post($landing_page_id, true); // Force delete permanently

                if ($delete_result) {
                    Logger::info('Associated landing page deleted', array(
                        'language_code' => $code,
                        'landing_page_id' => $landing_page_id
                    ));
                } else {
                    Logger::warning('Failed to delete associated landing page', array(
                        'language_code' => $code,
                        'landing_page_id' => $landing_page_id
                    ));
                }
            }

            Logger::info('Language deleted successfully', array('code' => $code));
            Logger::log_db_operation('delete', self::OPTION_NAME, array('code' => $code));
            return true;
        } else {
            $error = new \WP_Error('save_failed', __('Failed to delete language from database.', 'ez-translate'));
            Logger::error('Failed to delete language', array('code' => $code));
            return $error;
        }
    }

    /**
     * Check if a language code exists
     *
     * @param string $code Language code to check
     * @return bool True if exists, false otherwise
     * @since 1.0.0
     */
    public static function language_code_exists($code) {
        $languages = self::get_languages();
        
        foreach ($languages as $language) {
            if (isset($language['code']) && $language['code'] === $code) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if a language slug exists
     *
     * @param string $slug Language slug to check
     * @return bool True if exists, false otherwise
     * @since 1.0.0
     */
    public static function language_slug_exists($slug) {
        $languages = self::get_languages();
        
        foreach ($languages as $language) {
            if (isset($language['slug']) && $language['slug'] === $slug) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validate language data
     *
     * @param array $language_data Language data to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     * @since 1.0.0
     */
    public static function validate_language_data($language_data) {
        $errors = new \WP_Error();

        // Required fields
        if (empty($language_data['code'])) {
            $errors->add('missing_code', __('Language code is required.', 'ez-translate'));
        }

        if (empty($language_data['name'])) {
            $errors->add('missing_name', __('Language name is required.', 'ez-translate'));
        }

        if (empty($language_data['slug'])) {
            $errors->add('missing_slug', __('Language slug is required.', 'ez-translate'));
        }

        // Validate code format (2-5 alphanumeric characters)
        if (!empty($language_data['code']) && !preg_match('/^[a-zA-Z0-9]{2,5}$/', $language_data['code'])) {
            $errors->add('invalid_code_format', __('Language code must be 2-5 alphanumeric characters.', 'ez-translate'));
        }

        // Validate slug format (URL-safe)
        if (!empty($language_data['slug']) && !preg_match('/^[a-z0-9\-_]+$/', $language_data['slug'])) {
            $errors->add('invalid_slug_format', __('Language slug must contain only lowercase letters, numbers, hyphens, and underscores.', 'ez-translate'));
        }

        // Validate RTL field (must be boolean)
        if (isset($language_data['rtl']) && !is_bool($language_data['rtl'])) {
            $errors->add('invalid_rtl', __('RTL field must be true or false.', 'ez-translate'));
        }

        // Validate enabled field (must be boolean)
        if (isset($language_data['enabled']) && !is_bool($language_data['enabled'])) {
            $errors->add('invalid_enabled', __('Enabled field must be true or false.', 'ez-translate'));
        }

        if ($errors->has_errors()) {
            return $errors;
        }

        return true;
    }

    /**
     * Sanitize language data
     *
     * @param array $language_data Raw language data
     * @return array Sanitized language data
     * @since 1.0.0
     */
    public static function sanitize_language_data($language_data) {
        $sanitized = array();

        // Required fields
        $sanitized['code'] = isset($language_data['code']) ? sanitize_text_field($language_data['code']) : '';
        $sanitized['name'] = isset($language_data['name']) ? sanitize_text_field($language_data['name']) : '';
        $sanitized['slug'] = isset($language_data['slug']) ? sanitize_title($language_data['slug']) : '';

        // Optional fields
        $sanitized['native_name'] = isset($language_data['native_name']) ? sanitize_text_field($language_data['native_name']) : '';
        $sanitized['flag'] = isset($language_data['flag']) ? sanitize_text_field($language_data['flag']) : '';

        // Handle boolean fields properly - convert string representations to actual booleans
        $sanitized['rtl'] = isset($language_data['rtl']) ? self::sanitize_boolean($language_data['rtl']) : false;
        $sanitized['enabled'] = isset($language_data['enabled']) ? self::sanitize_boolean($language_data['enabled']) : true;

        // Site metadata fields (MEJORA 2: Metadatos de Sitio por Idioma)
        $sanitized['site_name'] = isset($language_data['site_name']) ? sanitize_text_field($language_data['site_name']) : '';
        $sanitized['site_title'] = isset($language_data['site_title']) ? sanitize_text_field($language_data['site_title']) : '';
        $sanitized['site_description'] = isset($language_data['site_description']) ? sanitize_textarea_field($language_data['site_description']) : '';

        // Landing page ID (auto-created landing page reference)
        $sanitized['landing_page_id'] = isset($language_data['landing_page_id']) ? absint($language_data['landing_page_id']) : 0;

        return $sanitized;
    }

    /**
     * Sanitize language data for updates (only allowed fields)
     *
     * @param array $language_data Raw language data
     * @return array Sanitized language data with only updatable fields
     * @since 1.0.0
     */
    public static function sanitize_language_data_for_update($language_data) {
        $sanitized = array();

        // Only allow updating these fields for data integrity
        if (isset($language_data['enabled'])) {
            $sanitized['enabled'] = self::sanitize_boolean($language_data['enabled']);
        }
        if (isset($language_data['site_name'])) {
            $sanitized['site_name'] = sanitize_text_field($language_data['site_name']);
        }
        if (isset($language_data['site_title'])) {
            $sanitized['site_title'] = sanitize_text_field($language_data['site_title']);
        }
        if (isset($language_data['site_description'])) {
            $sanitized['site_description'] = sanitize_textarea_field($language_data['site_description']);
        }
        if (isset($language_data['native_name'])) {
            $sanitized['native_name'] = sanitize_text_field($language_data['native_name']);
        }
        if (isset($language_data['flag'])) {
            $sanitized['flag'] = sanitize_text_field($language_data['flag']);
        }
        if (isset($language_data['rtl'])) {
            $sanitized['rtl'] = self::sanitize_boolean($language_data['rtl']);
        }

        return $sanitized;
    }

    /**
     * Sanitize boolean values from various input types
     *
     * @param mixed $value Value to convert to boolean
     * @return bool Sanitized boolean value
     * @since 1.0.0
     */
    private static function sanitize_boolean($value) {
        // Handle string representations
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, array('false', '0', '', 'no', 'off'))) {
                return false;
            }
            if (in_array($value, array('true', '1', 'yes', 'on'))) {
                return true;
            }
        }

        // Handle numeric values
        if (is_numeric($value)) {
            return (bool) intval($value);
        }

        // Default boolean conversion
        return (bool) $value;
    }

    /**
     * Get enabled languages only
     *
     * @return array Array of enabled language objects
     * @since 1.0.0
     */
    public static function get_enabled_languages() {
        $all_languages = self::get_languages();
        $enabled_languages = array();

        foreach ($all_languages as $language) {
            if (isset($language['enabled']) && $language['enabled']) {
                $enabled_languages[] = $language;
            }
        }

        return $enabled_languages;
    }

    /**
     * Get language-specific site metadata
     *
     * @param string $language_code Language code
     * @return array Array with site_title and site_description, or empty array if not found
     * @since 1.0.0
     */
    public static function get_language_site_metadata($language_code) {
        // Check if this is the WordPress default language
        $wp_locale = get_locale();
        $wp_language_code = strstr($wp_locale, '_', true) ?: $wp_locale; // es_MX -> es

        if (empty($language_code) || $language_code === $wp_language_code) {
            // Return default language metadata
            return self::get_default_language_metadata();
        }

        $language = self::get_language($language_code);

        if (!$language) {
            return array();
        }

        $metadata = array(
            'site_name' => isset($language['site_name']) ? $language['site_name'] : '',
            'site_title' => isset($language['site_title']) ? $language['site_title'] : '',
            'site_description' => isset($language['site_description']) ? $language['site_description'] : ''
        );

        return $metadata;
    }

    /**
     * Get language with automatically synchronized landing page SEO data
     * This function ensures persistent synchronization by updating the language metadata in the database
     *
     * @param string $language_code Language code
     * @return array|null Language data with synchronized SEO data, or null if not found
     * @since 1.0.0
     */
    public static function get_language_with_current_seo($language_code) {
        $language = self::get_language($language_code);

        if (!$language) {
            return null;
        }

        // If language has a landing page, get current SEO data from it
        if (!empty($language['landing_page_id'])) {
            $landing_page_id = $language['landing_page_id'];
            $post = get_post($landing_page_id);

            if ($post && $post->post_type === 'page') {
                // Get current SEO data from landing page
                $current_seo_title = get_post_meta($landing_page_id, '_ez_translate_seo_title', true);
                $current_seo_description = get_post_meta($landing_page_id, '_ez_translate_seo_description', true);

                // Check if we need to sync data persistently
                $needs_sync = false;
                $sync_data = array();

                if (!empty($current_seo_title) && $current_seo_title !== ($language['site_title'] ?? '')) {
                    $language['site_title'] = $current_seo_title;
                    $sync_data['site_title'] = $current_seo_title;
                    $needs_sync = true;
                }

                if (!empty($current_seo_description) && $current_seo_description !== ($language['site_description'] ?? '')) {
                    $language['site_description'] = $current_seo_description;
                    $sync_data['site_description'] = $current_seo_description;
                    $needs_sync = true;
                }

                // Perform persistent synchronization if needed
                if ($needs_sync) {
                    $sync_result = self::update_language($language_code, $sync_data);

                    if (!is_wp_error($sync_result)) {
                        Logger::info('Language metadata synchronized persistently with landing page SEO', array(
                            'language_code' => $language_code,
                            'landing_page_id' => $landing_page_id,
                            'synced_fields' => array_keys($sync_data),
                            'seo_title' => $current_seo_title,
                            'seo_description' => $current_seo_description
                        ));
                    } else {
                        Logger::error('Failed to sync language metadata persistently', array(
                            'language_code' => $language_code,
                            'error' => $sync_result->get_error_message()
                        ));
                    }
                } else {
                    Logger::debug('Language data already synchronized with landing page SEO', array(
                        'language_code' => $language_code,
                        'landing_page_id' => $landing_page_id
                    ));
                }
            }
        }

        return $language;
    }

    /**
     * Synchronize all languages with their landing page SEO data
     * This function ensures all language metadata is up-to-date with landing page SEO
     *
     * @return array Results of synchronization process
     * @since 1.0.0
     */
    public static function sync_all_languages_with_landing_seo() {
        $languages = self::get_languages(false);
        $results = array(
            'total_languages' => count($languages),
            'synchronized' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => array()
        );

        foreach ($languages as $language) {
            $language_code = $language['code'];

            try {
                // Use the sync function which will update persistently if needed
                $synced_language = self::get_language_with_current_seo($language_code);

                if ($synced_language) {
                    $results['synchronized']++;
                    $results['details'][$language_code] = 'synchronized';
                } else {
                    $results['skipped']++;
                    $results['details'][$language_code] = 'skipped - no language found';
                }
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][$language_code] = 'error: ' . $e->getMessage();
                Logger::error('Error syncing language with landing SEO', array(
                    'language_code' => $language_code,
                    'error' => $e->getMessage()
                ));
            }
        }

        Logger::info('Bulk synchronization completed', $results);
        return $results;
    }

    /**
     * Get default language metadata
     *
     * @return array Array with site metadata for the default language
     * @since 1.0.0
     */
    public static function get_default_language_metadata() {
        $default_metadata = get_option('ez_translate_default_language_metadata', array());

        return array(
            'site_name' => $default_metadata['site_name'] ?? '',
            'site_title' => $default_metadata['site_title'] ?? '',
            'site_description' => $default_metadata['site_description'] ?? ''
        );
    }

    /**
     * Clear language cache
     *
     * @since 1.0.0
     */
    public static function clear_cache() {
        delete_transient(self::CACHE_KEY);
    }

    /**
     * Create a landing page for a specific language
     *
     * @param string $language_code Language code
     * @param array $landing_page_data Landing page data (title, description, slug, status)
     * @return int|WP_Error Post ID on success, WP_Error on failure
     * @since 1.0.0
     */
    public static function create_landing_page_for_language($language_code, $landing_page_data) {
        Logger::info('Creating landing page for language', array(
            'language_code' => $language_code,
            'data' => $landing_page_data
        ));

        // Validate language code format
        if (empty($language_code) || !preg_match('/^[a-zA-Z0-9]{2,5}$/', $language_code)) {
            $error = new \WP_Error('invalid_language_code', __('Invalid language code format.', 'ez-translate'));
            Logger::error('Invalid language code for landing page creation', array('code' => $language_code));
            return $error;
        }

        // Validate required landing page data
        if (empty($landing_page_data['title']) || empty($landing_page_data['description'])) {
            $error = new \WP_Error('missing_data', __('Landing page title and description are required.', 'ez-translate'));
            Logger::error('Missing required landing page data', array('data' => $landing_page_data));
            return $error;
        }

        // Generate slug if not provided
        $slug = !empty($landing_page_data['slug']) ? $landing_page_data['slug'] : sanitize_title($landing_page_data['title']);

        // Check if slug is unique
        $existing_post = get_page_by_path($slug);
        if ($existing_post) {
            // Append language code to make it unique
            $slug = $slug . '-' . $language_code;
        }

        // Prepare post data
        $post_data = array(
            'post_title' => sanitize_text_field($landing_page_data['title']),
            'post_content' => sprintf(
                '<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->',
                esc_html($landing_page_data['description'])
            ),
            'post_status' => in_array($landing_page_data['status'], array('draft', 'publish')) ? $landing_page_data['status'] : 'draft',
            'post_type' => 'page',
            'post_name' => $slug,
            'post_excerpt' => sanitize_textarea_field($landing_page_data['description']),
            'meta_input' => array(
                '_ez_translate_language' => $language_code,
                '_ez_translate_seo_title' => sanitize_text_field($landing_page_data['title']),
                '_ez_translate_seo_description' => sanitize_textarea_field($landing_page_data['description'])
            )
        );

        // Create the post
        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            Logger::error('Failed to create landing page', array(
                'language_code' => $language_code,
                'error' => $post_id->get_error_message()
            ));
            return $post_id;
        }

        // Set as landing page with proper metadata and bidirectional relationship
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-post-meta-manager.php';
        \EZTranslate\PostMetaManager::set_as_landing_page($post_id, $language_code);

        Logger::info('Landing page created successfully', array(
            'language_code' => $language_code,
            'post_id' => $post_id,
            'slug' => $slug,            
        ));

        return $post_id;
    }

    /**
     * Get landing page for a specific language
     *
     * @param string $language_code Language code
     * @return array|null Landing page data or null if not found
     * @since 1.0.0
     */
    public static function get_landing_page_for_language($language_code) {
        if (empty($language_code)) {
            return null;
        }

        // Check if this is the default language and if main landing page is configured
        $wp_locale = get_locale();
        $wp_language_code = strstr($wp_locale, '_', true) ?: $wp_locale;
        $is_default_language = $language_code === $wp_language_code;

        if ($is_default_language) {
            $main_landing_page_id = get_option('ez_translate_main_landing_page_id', 0);
            if ($main_landing_page_id > 0) {
                $post = get_post($main_landing_page_id);
                if ($post && $post->post_type === 'page') {
                    $landing_page_data = array(
                        'post_id' => $post->ID,
                        'title' => $post->post_title,
                        'slug' => $post->post_name,
                        'status' => $post->post_status,
                        'edit_url' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                        'view_url' => get_permalink($post->ID),
                        'seo_title' => get_post_meta($post->ID, '_ez_translate_seo_title', true),
                        'seo_description' => get_post_meta($post->ID, '_ez_translate_seo_description', true),
                        'group_id' => get_post_meta($post->ID, '_ez_translate_group', true)
                    );

                    return $landing_page_data;
                }
            }
        }

        // First, try to get landing page ID from language configuration
        $language = self::get_language($language_code);
        if ($language && !empty($language['landing_page_id'])) {
            $post = get_post($language['landing_page_id']);

            // Verify the post exists and is still valid
            if ($post && $post->post_type === 'page') {
                $landing_page_data = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'slug' => $post->post_name,
                    'status' => $post->post_status,
                    'edit_url' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                    'view_url' => get_permalink($post->ID),
                    'seo_title' => get_post_meta($post->ID, '_ez_translate_seo_title', true),
                    'seo_description' => get_post_meta($post->ID, '_ez_translate_seo_description', true),
                    'group_id' => get_post_meta($post->ID, '_ez_translate_group', true)
                );

                return $landing_page_data;
            } else {
                Logger::warning('Stored landing page ID is invalid, falling back to search', array(
                    'language_code' => $language_code,
                    'stored_id' => $language['landing_page_id']
                ));
            }
        }

        // Check cache first for legacy fallback query
        $cache_key = 'ez_translate_landing_page_fallback_' . md5($language_code);
        $cached_post = wp_cache_get($cache_key, 'ez_translate');

        if ($cached_post !== false) {
            if ($cached_post === 'not_found') {
                return null;
            }
            $post = $cached_post;
        } else {
            // Fallback: Query for pages with this language (legacy support)
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            // Meta query is necessary for legacy landing page fallback when no specific landing page is configured
            $posts = get_posts(array(
                'post_type' => 'page',
                'post_status' => array('draft', 'publish', 'private'),
                'meta_query' => array(
                    array(
                        'key' => '_ez_translate_language',
                        'value' => $language_code,
                        'compare' => '='
                    )
                ),
                'numberposts' => 1, // Only need the first match for fallback
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

            if (empty($posts)) {
                // Cache the "not found" result to avoid repeated queries
                wp_cache_set($cache_key, 'not_found', 'ez_translate', 300); // 5 minutes cache
                return null;
            }

            // Return the first page found
            $post = $posts[0];

            // Cache the result for 5 minutes (landing pages don't change frequently)
            wp_cache_set($cache_key, $post, 'ez_translate', 300);
        }

        $landing_page_data = array(
            'post_id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'edit_url' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
            'view_url' => get_permalink($post->ID),
            'seo_title' => get_post_meta($post->ID, '_ez_translate_seo_title', true),
            'seo_description' => get_post_meta($post->ID, '_ez_translate_seo_description', true),
            'group_id' => get_post_meta($post->ID, '_ez_translate_group', true)
        );

        return $landing_page_data;
    }

    /**
     * Update landing page SEO metadata
     *
     * @param int $post_id Post ID
     * @param array $seo_data SEO data (title, description)
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public static function update_landing_page_seo($post_id, $seo_data) {
        Logger::info('Updating landing page SEO', array(
            'post_id' => $post_id,
            'seo_data' => $seo_data
        ));

        if (empty($post_id) || !is_numeric($post_id)) {
            $error = new \WP_Error('invalid_post_id', __('Invalid post ID.', 'ez-translate'));
            Logger::error('Invalid post ID for SEO update', array('post_id' => $post_id));
            return $error;
        }

        // Verify post exists and has language metadata
        $post = get_post($post_id);
        if (!$post) {
            $error = new \WP_Error('post_not_found', __('Post not found.', 'ez-translate'));
            Logger::error('Post not found for SEO update', array('post_id' => $post_id));
            return $error;
        }

        $language = get_post_meta($post_id, '_ez_translate_language', true);
        if (empty($language)) {
            $error = new \WP_Error('not_translation_page', __('This page is not configured as a translation page.', 'ez-translate'));
            Logger::error('Page is not a translation page', array('post_id' => $post_id));
            return $error;
        }

        // Update SEO metadata on the post
        if (isset($seo_data['title'])) {
            update_post_meta($post_id, '_ez_translate_seo_title', sanitize_text_field($seo_data['title']));
        }

        if (isset($seo_data['description'])) {
            update_post_meta($post_id, '_ez_translate_seo_description', sanitize_textarea_field($seo_data['description']));
        }

        // Check if this is a landing page and sync with language metadata
        $is_landing_page = get_post_meta($post_id, '_ez_translate_is_landing_page', true);
        if ($is_landing_page) {
            Logger::info('Syncing landing page SEO with language metadata', array(
                'post_id' => $post_id,
                'language' => $language
            ));

            // Prepare language metadata update
            $language_metadata_update = array();

            if (isset($seo_data['title'])) {
                $language_metadata_update['site_title'] = sanitize_text_field($seo_data['title']);
            }

            if (isset($seo_data['description'])) {
                $language_metadata_update['site_description'] = sanitize_textarea_field($seo_data['description']);
            }

            // Update language metadata if we have data to update
            if (!empty($language_metadata_update)) {
                $update_result = self::update_language($language, $language_metadata_update);

                if (is_wp_error($update_result)) {
                    Logger::warning('Failed to sync language metadata', array(
                        'language' => $language,
                        'error' => $update_result->get_error_message()
                    ));
                } else {
                    Logger::info('Language metadata synced successfully', array(
                        'language' => $language,
                        'updated_fields' => array_keys($language_metadata_update)
                    ));
                }
            }
        }

        Logger::info('Landing page SEO updated successfully', array(
            'post_id' => $post_id,
            'language' => $language
        ));

        return true;
    }

    /**
     * Generate default landing page data for a language
     *
     * @param array $language_data Language data
     * @return array Default landing page data
     * @since 1.0.0
     */
    private static function generate_default_landing_page_data($language_data) {
        // Priority order for title: site_title > site_name > site bloginfo
        $default_title = '';
        if (!empty($language_data['site_title'])) {
            // Use the specific site title for this language
            $default_title = $language_data['site_title'];
        } elseif (!empty($language_data['site_name'])) {
            // Use the site name for this language
            $default_title = $language_data['site_name'];
        } else {
            // Fallback to WordPress site name with language
            $site_name = get_bloginfo('name');
            $language_name = $language_data['name'];
            $default_title = sprintf(
                /* translators: %1$s: site name, %2$s: language name */
                __('%1$s - %2$s', 'ez-translate'),
                $site_name,
                $language_name
            );
        }

        // Priority order for description: site_description > generated description
        $default_description = '';
        if (!empty($language_data['site_description'])) {
            // Use the specific site description for this language
            $default_description = $language_data['site_description'];
        } else {
            // Generate a default description
            $site_name = !empty($language_data['site_name']) ? $language_data['site_name'] : get_bloginfo('name');
            $language_name = $language_data['name'];
            $default_description = sprintf(
                /* translators: %1$s: site name, %2$s: language name */
                __('Welcome to %1$s in %2$s. Explore our content and discover what we have to offer.', 'ez-translate'),
                $site_name,
                $language_name
            );
        }

        // Generate slug from language code (ISO format is cleaner)
        $default_slug = $language_data['code'];

        $landing_page_data = array(
            'title' => $default_title,
            'description' => $default_description,
            'slug' => $default_slug,
            'status' => 'publish' // Auto-publish landing pages by default
        );

        return $landing_page_data;
    }

    /**
     * Get API settings
     *
     * @return array API settings array
     * @since 1.0.0
     */
    public static function get_api_settings() {
        $default_settings = array(
            'api_key' => '',
            'enabled' => false,
            'last_updated' => ''
        );

        $settings = get_option(self::API_OPTION_NAME, $default_settings);

        // Ensure settings is always an array with required keys
        if (!is_array($settings)) {
            $settings = $default_settings;
            Logger::warning('API settings option was not an array, resetting to defaults');
        }

        // Merge with defaults to ensure all keys exist
        $settings = array_merge($default_settings, $settings);

        Logger::info('API settings retrieved', array(
            'has_api_key' => !empty($settings['api_key']),
            'enabled' => $settings['enabled'],
            'last_updated' => $settings['last_updated']
        ));

        return $settings;
    }

    /**
     * Update API settings
     *
     * @param array $settings API settings to update
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public static function update_api_settings($settings) {
        Logger::info('Updating API settings', array('has_api_key' => !empty($settings['api_key'])));

        // Validate input
        if (!is_array($settings)) {
            $error = new \WP_Error('invalid_settings', __('API settings must be an array.', 'ez-translate'));
            Logger::error('Invalid API settings format provided');
            return $error;
        }

        // Get current settings
        $current_settings = self::get_api_settings();

        // Sanitize and validate new settings
        $sanitized_settings = self::sanitize_api_settings($settings);
        if (is_wp_error($sanitized_settings)) {
            return $sanitized_settings;
        }

        // Merge with current settings
        $updated_settings = array_merge($current_settings, $sanitized_settings);
        $updated_settings['last_updated'] = current_time('mysql');

        // Save to database
        $result = update_option(self::API_OPTION_NAME, $updated_settings);

        if ($result) {
            Logger::info('API settings updated successfully', array(
                'has_api_key' => !empty($updated_settings['api_key']),
                'enabled' => $updated_settings['enabled']
            ));
            return true;
        } else {
            $error = new \WP_Error('save_failed', __('Failed to save API settings to database.', 'ez-translate'));
            Logger::error('Failed to save API settings');
            return $error;
        }
    }

    /**
     * Sanitize API settings
     *
     * @param array $settings Raw API settings
     * @return array|WP_Error Sanitized settings or WP_Error on validation failure
     * @since 1.0.0
     */
    private static function sanitize_api_settings($settings) {
        $sanitized = array();

        // Sanitize API key
        if (isset($settings['api_key'])) {
            $api_key = sanitize_text_field($settings['api_key']);

            // Validate API key format if not empty
            if (!empty($api_key) && !self::validate_api_key($api_key)) {
                $error = new \WP_Error('invalid_api_key', __('Invalid API key format.', 'ez-translate'));
                Logger::error('Invalid API key format provided');
                return $error;
            }

            $sanitized['api_key'] = $api_key;
        }

        // Sanitize enabled flag
        if (isset($settings['enabled'])) {
            $sanitized['enabled'] = (bool) $settings['enabled'];
        }

        return $sanitized;
    }

    /**
     * Validate API key format
     *
     * @param string $api_key API key to validate
     * @return bool True if valid, false otherwise
     * @since 1.0.0
     */
    private static function validate_api_key($api_key) {
        if (empty($api_key)) {
            return true; // Allow empty
        }

        // Basic validation: length and character set
        if (strlen($api_key) < 20 || strlen($api_key) > 100) {
            return false;
        }

        // Allow alphanumeric characters, hyphens, and underscores
        return preg_match('/^[A-Za-z0-9_-]+$/', $api_key);
    }

    /**
     * Check if API is configured and enabled
     *
     * @return bool True if API is ready to use
     * @since 1.0.0
     */
    public static function is_api_enabled() {
        $settings = self::get_api_settings();
        return !empty($settings['api_key']) && $settings['enabled'];
    }

    /**
     * Get API key (for internal use)
     *
     * @return string API key or empty string if not configured
     * @since 1.0.0
     */
    public static function get_api_key() {
        $settings = self::get_api_settings();
        return $settings['api_key'];
    }

    /**
     * Repair languages with missing landing page IDs
     *
     * @return array Repair results with details
     * @since 1.0.0
     */
    public static function repair_missing_landing_pages()
    {
        Logger::info('Starting landing page repair process');

        $languages = self::get_languages(false);
        $repair_results = array(
            'total_checked' => 0,
            'found_missing' => 0,
            'successfully_repaired' => 0,
            'failed_repairs' => 0,
            'details' => array()
        );

        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-post-meta-manager.php';

        foreach ($languages as $index => $language) {
            $repair_results['total_checked']++;

            // Skip if landing page ID is already set and valid
            if (!empty($language['landing_page_id']) && $language['landing_page_id'] > 0) {
                // Verify the page still exists
                $post = get_post($language['landing_page_id']);
                if ($post && $post->post_type === 'page') {
                    continue; // This language is fine
                }
            }

            // This language has missing or invalid landing page ID
            $repair_results['found_missing']++;
            $language_code = $language['code'];

            Logger::info('Attempting to repair language', array('code' => $language_code));

            // Try to find landing page using bidirectional metadata
            $found_post_id = \EZTranslate\PostMetaManager::find_landing_page_for_language($language_code);

            if ($found_post_id) {
                // Found a page with the bidirectional metadata
                $languages[$index]['landing_page_id'] = $found_post_id;
                $repair_results['successfully_repaired']++;

                $repair_results['details'][] = array(
                    'language_code' => $language_code,
                    'language_name' => $language['name'],
                    'status' => 'repaired',
                    'found_post_id' => $found_post_id,
                    'post_title' => get_the_title($found_post_id)
                );

                Logger::info('Language repaired successfully', array(
                    'code' => $language_code,
                    'post_id' => $found_post_id
                ));
            } else {
                // Try fallback: search for pages with this language code
                $fallback_posts = get_posts(array(
                    'post_type' => 'page',
                    'post_status' => array('publish', 'draft'),
                    //phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    'meta_query' => array(
                        array(
                            'key' => '_ez_translate_language',
                            'value' => $language_code,
                            'compare' => '='
                        ),
                        array(
                            'key' => '_ez_translate_is_landing',
                            'value' => true,
                            'compare' => '='
                        )
                    ),
                    //phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    'numberposts' => 1
                ));

                if (!empty($fallback_posts)) {
                    $found_post_id = $fallback_posts[0]->ID;

                    // Update the page with bidirectional metadata
                    \EZTranslate\PostMetaManager::set_as_landing_page($found_post_id, $language_code);

                    // Update language configuration
                    $languages[$index]['landing_page_id'] = $found_post_id;
                    $repair_results['successfully_repaired']++;

                    $repair_results['details'][] = array(
                        'language_code' => $language_code,
                        'language_name' => $language['name'],
                        'status' => 'repaired_fallback',
                        'found_post_id' => $found_post_id,
                        'post_title' => get_the_title($found_post_id)
                    );

                    Logger::info('Language repaired using fallback method', array(
                        'code' => $language_code,
                        'post_id' => $found_post_id
                    ));
                } else {
                    // No landing page found for this language
                    $repair_results['failed_repairs']++;

                    $repair_results['details'][] = array(
                        'language_code' => $language_code,
                        'language_name' => $language['name'],
                        'status' => 'not_found',
                        'found_post_id' => null,
                        'post_title' => null
                    );

                    Logger::warning('No landing page found for language', array('code' => $language_code));
                }
            }
        }

        // Save updated language configuration if any repairs were made
        if ($repair_results['successfully_repaired'] > 0) {
            $save_result = update_option(self::OPTION_NAME, $languages);
            if (!$save_result) {
                Logger::error('Failed to save repaired language configuration');
                // Mark all repairs as failed
                foreach ($repair_results['details'] as &$detail) {
                    if ($detail['status'] === 'repaired' || $detail['status'] === 'repaired_fallback') {
                        $detail['status'] = 'save_failed';
                        $repair_results['successfully_repaired']--;
                        $repair_results['failed_repairs']++;
                    }
                }
            }
        }

        Logger::info('Landing page repair process completed', $repair_results);

        return $repair_results;
    }

    /**
     * Get languages that need landing page repair
     *
     * @return array Array of languages with missing landing pages
     * @since 1.0.0
     */
    public static function get_languages_needing_repair()
    {
        $languages = self::get_languages(false);
        $needing_repair = array();

        foreach ($languages as $language) {
            $needs_repair = false;

            // Check if landing page ID is missing or invalid
            if (empty($language['landing_page_id']) || $language['landing_page_id'] <= 0) {
                $needs_repair = true;
            } else {
                // Check if the page still exists
                $post = get_post($language['landing_page_id']);
                if (!$post || $post->post_type !== 'page') {
                    $needs_repair = true;
                }
            }

            if ($needs_repair) {
                $needing_repair[] = array(
                    'code' => $language['code'],
                    'name' => $language['name'],
                    'current_landing_id' => $language['landing_page_id'] ?? 0
                );
            }
        }

        return $needing_repair;
    }
}
