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
     * Get all languages
     *
     * @param bool $use_cache Whether to use cached data
     * @return array Array of language objects
     * @since 1.0.0
     */
    public static function get_languages($use_cache = true) {
        Logger::debug('Getting languages', array('use_cache' => $use_cache));

        if ($use_cache) {
            $cached_languages = get_transient(self::CACHE_KEY);
            if ($cached_languages !== false) {
                Logger::debug('Languages retrieved from cache', array('count' => count($cached_languages)));
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
        Logger::debug('Getting language by code', array('code' => $code));

        if (empty($code)) {
            Logger::warning('Empty language code provided');
            return null;
        }

        $languages = self::get_languages();
        
        foreach ($languages as $language) {
            if (isset($language['code']) && $language['code'] === $code) {
                Logger::debug('Language found', array('code' => $code, 'name' => $language['name'] ?? 'Unknown'));
                return $language;
            }
        }

        Logger::debug('Language not found', array('code' => $code));
        return null;
    }

    /**
     * Add a new language
     *
     * @param array $language_data Language data
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    public static function add_language($language_data) {
        Logger::info('Adding new language', array('data' => $language_data));

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

        // Get current languages
        $languages = self::get_languages(false);

        // Add the new language
        $languages[] = $language_data;

        // Save to database
        $result = update_option(self::OPTION_NAME, $languages);

        if ($result) {
            // Clear cache
            delete_transient(self::CACHE_KEY);
            
            Logger::info('Language added successfully', array('code' => $language_data['code'], 'name' => $language_data['name']));
            Logger::log_db_operation('create', self::OPTION_NAME, $language_data);
            return true;
        } else {
            $error = new \WP_Error('save_failed', __('Failed to save language to database.', 'ez-translate'));
            Logger::error('Failed to save language', array('data' => $language_data));
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

        // Validate language data
        $validation_result = self::validate_language_data($language_data);
        if (is_wp_error($validation_result)) {
            Logger::error('Language validation failed for update', array('errors' => $validation_result->get_error_messages()));
            return $validation_result;
        }

        // Get current languages
        $languages = self::get_languages(false);
        $language_found = false;

        // Find and update the language
        foreach ($languages as $index => $language) {
            if (isset($language['code']) && $language['code'] === $code) {
                // If code is being changed, check for duplicates
                if ($language_data['code'] !== $code && self::language_code_exists($language_data['code'])) {
                    $error = new \WP_Error('duplicate_code', __('New language code already exists.', 'ez-translate'));
                    Logger::error('Duplicate language code on update', array('new_code' => $language_data['code']));
                    return $error;
                }

                // If slug is being changed, check for duplicates
                if ($language_data['slug'] !== $language['slug'] && self::language_slug_exists($language_data['slug'])) {
                    $error = new \WP_Error('duplicate_slug', __('New language slug already exists.', 'ez-translate'));
                    Logger::error('Duplicate language slug on update', array('new_slug' => $language_data['slug']));
                    return $error;
                }

                $languages[$index] = $language_data;
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

        // Remove the language
        foreach ($languages as $language) {
            if (isset($language['code']) && $language['code'] === $code) {
                $language_found = true;
                Logger::debug('Language found for deletion', array('code' => $code, 'name' => $language['name'] ?? 'Unknown'));
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
        $sanitized['rtl'] = isset($language_data['rtl']) ? (bool) $language_data['rtl'] : false;
        $sanitized['enabled'] = isset($language_data['enabled']) ? (bool) $language_data['enabled'] : true;

        Logger::debug('Language data sanitized', array('original' => $language_data, 'sanitized' => $sanitized));

        return $sanitized;
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

        Logger::debug('Retrieved enabled languages', array('count' => count($enabled_languages)));
        return $enabled_languages;
    }

    /**
     * Clear language cache
     *
     * @since 1.0.0
     */
    public static function clear_cache() {
        delete_transient(self::CACHE_KEY);
        Logger::debug('Language cache cleared');
    }
}
