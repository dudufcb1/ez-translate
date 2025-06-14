<?php

/**
 * EZ Translate Backup Manager
 *
 * Handles backup and import of language data including SEO metadata
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use WP_Error;

/**
 * Backup Manager Class
 *
 * @since 1.0.0
 */
class BackupManager
{

    /**
     * Backup file version for compatibility checking
     */
    const BACKUP_VERSION = '1.0.0';

    /**
     * Export language data to backup format
     *
     * @return array Backup data structure
     * @since 1.0.0
     */
    public static function export_language_data()
    {
        Logger::info('Starting language data export');

        try {
            // Ensure LanguageManager is loaded
            if (!class_exists('\EZTranslate\LanguageManager')) {
                require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
            }

            // Get all language data
            $languages = LanguageManager::get_languages(false);

            // Get default language metadata
            $default_metadata = LanguageManager::get_default_language_metadata();

            // Prepare backup structure
            $backup_data = array(
                'version' => self::BACKUP_VERSION,
                'timestamp' => \current_time('mysql'),
                'site_url' => \get_site_url(),
                'wp_version' => \get_bloginfo('version'),
                'plugin_version' => defined('EZ_TRANSLATE_VERSION') ? EZ_TRANSLATE_VERSION : '1.0.0',
                'data' => array(
                    'languages' => $languages,
                    'default_metadata' => $default_metadata
                )
            );

            Logger::info('Language data export completed', array(
                'languages_count' => count($languages),
                'has_default_metadata' => !empty($default_metadata)
            ));

            return $backup_data;
        } catch (\Exception $e) {
            Logger::error('Language data export failed', array(
                'error' => $e->getMessage()
            ));
            return new WP_Error('export_failed', \__('Export failed: ', 'ez-translate') . $e->getMessage());
        }
    }

    /**
     * Generate backup file for download
     *
     * @return array|WP_Error Array with file info or WP_Error on failure
     * @since 1.0.0
     */
    public static function generate_backup_file()
    {
        $backup_data = self::export_language_data();

        if (is_wp_error($backup_data)) {
            return $backup_data;
        }

        // Generate filename with timestamp
        $timestamp = gmdate('Y-m-d_H-i-s');
        $site_name = sanitize_file_name(get_bloginfo('name'));
        $filename = "ez-translate-backup_{$site_name}_{$timestamp}.json";

        // Convert to JSON
        $json_data = wp_json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json_data === false) {
            Logger::error('Failed to encode backup data to JSON');
            return new \WP_Error('json_encode_failed', __('Failed to encode backup data.', 'ez-translate'));
        }

        return array(
            'filename' => $filename,
            'content' => $json_data,
            'size' => strlen($json_data),
            'languages_count' => count($backup_data['data']['languages'])
        );
    }

    /**
     * Validate backup file structure
     *
     * @param array $backup_data Backup data to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     * @since 1.0.0
     */
    public static function validate_backup_structure($backup_data)
    {
        $errors = new \WP_Error();

        // Check required top-level fields
        $required_fields = array('version', 'timestamp', 'data');
        foreach ($required_fields as $field) {
            if (!isset($backup_data[$field])) {
                /* translators: %s: name of the missing field */
                $errors->add('missing_field', sprintf(__('Missing required field: %s', 'ez-translate'), $field));
            }
        }

        // Check data structure
        if (isset($backup_data['data'])) {
            if (!isset($backup_data['data']['languages']) || !is_array($backup_data['data']['languages'])) {
                $errors->add('invalid_languages', __('Invalid or missing languages data.', 'ez-translate'));
            }

            if (!isset($backup_data['data']['default_metadata']) || !is_array($backup_data['data']['default_metadata'])) {
                $errors->add('invalid_default_metadata', __('Invalid or missing default metadata.', 'ez-translate'));
            }
        }

        // Check version compatibility
        if (isset($backup_data['version'])) {
            $backup_version = $backup_data['version'];
            if (version_compare($backup_version, self::BACKUP_VERSION, '>')) {
                $errors->add(
                    'version_mismatch',
                    sprintf(
                        /* translators: %1$s: backup version number, %2$s: supported version number */
                        __('Backup version %1$s is newer than the current plugin version %2$s. Please update the plugin.', 'ez-translate'),
                        $backup_version,
                        self::BACKUP_VERSION
                    )
                );
            }
        }

        if ($errors->has_errors()) {
            return $errors;
        }

        return true;
    }

    /**
     * Compare backup data with current data
     *
     * @param array $backup_data Backup data to compare
     * @return array Comparison results
     * @since 1.0.0
     */
    public static function compare_with_current($backup_data)
    {
        Logger::info('Starting backup comparison with current data');

        // Define a default empty comparison structure
        $default_comparison = array(
            'languages' => array(
                'new' => array(),
                'existing' => array(),
                'unchanged' => array()
            ),
            'default_metadata' => array(
                'changes' => array(),
                'unchanged' => true // Assuming true means no changes detected or applicable
            ),
            'summary' => array(
                'total_backup_languages' => 0,
                'total_current_languages' => 0, // Will be updated if possible
                'new_languages_count' => 0,
                'updated_languages_count' => 0,
                'unchanged_languages_count' => 0
            )
        );

        // Initial validation of $backup_data
        if (null === $backup_data || !is_array($backup_data)) {
            Logger::error('Invalid backup_data provided to compare_with_current: data is null or not an array.');
            // Try to get current languages count if LanguageManager is available
            if (class_exists('\EZTranslate\LanguageManager')) {
                $current_languages_for_summary = LanguageManager::get_languages(false);
                $default_comparison['summary']['total_current_languages'] = count($current_languages_for_summary);
            }
            return $default_comparison;
        }

        // Ensure LanguageManager is loaded
        if (!class_exists('\EZTranslate\LanguageManager')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        }

        // Nested data validation
        if (!isset($backup_data['data']) || !is_array($backup_data['data'])) {
            Logger::error("Backup data is missing 'data' array or it's not an array.");
            if (class_exists('\EZTranslate\LanguageManager')) {
                $current_languages_for_summary = LanguageManager::get_languages(false);
                $default_comparison['summary']['total_current_languages'] = count($current_languages_for_summary);
            }
            return $default_comparison;
        }

        if (!isset($backup_data['data']['languages']) || !is_array($backup_data['data']['languages'])) {
            Logger::error("Backup data is missing 'data[languages]' array or it's not an array.");
            if (class_exists('\EZTranslate\LanguageManager')) {
                $current_languages_for_summary = LanguageManager::get_languages(false);
                $default_comparison['summary']['total_current_languages'] = count($current_languages_for_summary);
            }
            return $default_comparison;
        }

        if (!isset($backup_data['data']['default_metadata']) || !is_array($backup_data['data']['default_metadata'])) {
            Logger::error("Backup data is missing 'data[default_metadata]' array or it's not an array.");
            if (class_exists('\EZTranslate\LanguageManager')) {
                $current_languages_for_summary = LanguageManager::get_languages(false);
                $default_comparison['summary']['total_current_languages'] = count($current_languages_for_summary);
            }
            return $default_comparison;
        }

        // Get current data
        $current_languages = LanguageManager::get_languages(false);
        $current_default_metadata = LanguageManager::get_default_language_metadata();

        $backup_languages = $backup_data['data']['languages'];
        $backup_default_metadata = $backup_data['data']['default_metadata'];

        // Initialize comparison array (can reuse parts of default_comparison for structure)
        $comparison = $default_comparison;
        // Update total_current_languages as it's now available
        $comparison['summary']['total_current_languages'] = count($current_languages);
        // Update total_backup_languages as it's now validated and available
        $comparison['summary']['total_backup_languages'] = count($backup_languages);


        // Reset counts as we are proceeding with actual comparison
        $comparison['summary']['new_languages_count'] = 0;
        $comparison['summary']['updated_languages_count'] = 0;
        $comparison['summary']['unchanged_languages_count'] = 0;
        // $comparison['languages'] is already initialized from $default_comparison.
        // $comparison['default_metadata'] is already initialized from $default_comparison.
        // $comparison['summary'] is already initialized and partially populated.
        // The individual counts above are correctly reset.

        // Compare languages
        foreach ($backup_languages as $backup_lang) {
            $backup_code = $backup_lang['code'];
            $current_lang = self::find_language_by_code($current_languages, $backup_code);

            if (!$current_lang) {
                // New language
                $comparison['languages']['new'][] = array(
                    'code' => $backup_code,
                    'name' => $backup_lang['name'],
                    'data' => $backup_lang
                );
                $comparison['summary']['new_languages_count']++;
            } else {
                // Compare existing language
                $differences = self::compare_language_data($current_lang, $backup_lang);

                if (!empty($differences)) {
                    $comparison['languages']['existing'][] = array(
                        'code' => $backup_code,
                        'name' => $backup_lang['name'],
                        'current' => $current_lang,
                        'backup' => $backup_lang,
                        'differences' => $differences
                    );
                    $comparison['summary']['updated_languages_count']++;
                } else {
                    $comparison['languages']['unchanged'][] = array(
                        'code' => $backup_code,
                        'name' => $backup_lang['name']
                    );
                    $comparison['summary']['unchanged_languages_count']++;
                }
            }
        }

        // Compare default metadata
        $metadata_differences = self::compare_metadata($current_default_metadata, $backup_default_metadata);
        if (!empty($metadata_differences)) {
            $comparison['default_metadata']['changes'] = $metadata_differences;
        } else {
            $comparison['default_metadata']['unchanged'] = true;
        }

        Logger::info('Backup comparison completed', $comparison['summary']);

        return $comparison;
    }

    /**
     * Find language by code in array
     *
     * @param array $languages Array of languages
     * @param string $code Language code to find
     * @return array|null Language data or null if not found
     * @since 1.0.0
     */
    private static function find_language_by_code($languages, $code)
    {
        foreach ($languages as $language) {
            if (isset($language['code']) && $language['code'] === $code) {
                return $language;
            }
        }
        return null;
    }

    /**
     * Compare two language data arrays
     *
     * @param array $current Current language data
     * @param array $backup Backup language data
     * @return array Array of differences
     * @since 1.0.0
     */
    private static function compare_language_data($current, $backup)
    {
        $differences = array();

        // Fields to compare (excluding immutable fields like landing_page_id)
        $comparable_fields = array(
            'name',
            'native_name',
            'flag',
            'rtl',
            'enabled',
            'site_name',
            'site_title',
            'site_description'
        );

        foreach ($comparable_fields as $field) {
            $current_value = isset($current[$field]) ? $current[$field] : '';
            $backup_value = isset($backup[$field]) ? $backup[$field] : '';

            // Ensure we detect changes in SEO fields even if they're empty strings
            if ($field === 'site_title' || $field === 'site_description' || $current_value !== $backup_value) {
                $differences[$field] = array(
                    'current' => $current_value,
                    'backup' => $backup_value
                );
                
                // Log the difference for debugging
                Logger::debug('Found difference in language field', array(
                    'field' => $field,
                    'current' => $current_value,
                    'backup' => $backup_value
                ));
            }
        }

        // Force check SEO metadata differences
        if (!empty($current['landing_page_id'])) {
            $landing_page_id = $current['landing_page_id'];
            $current_seo = array(
                'site_title' => get_post_meta($landing_page_id, '_ez_translate_seo_title', true),
                'site_description' => get_post_meta($landing_page_id, '_ez_translate_seo_description', true)
            );

            $backup_seo = array(
                'site_title' => isset($backup['site_title']) ? $backup['site_title'] : '',
                'site_description' => isset($backup['site_description']) ? $backup['site_description'] : ''
            );

            // Add SEO differences
            if ($current_seo['site_title'] !== $backup_seo['site_title']) {
                $differences['site_title'] = array(
                    'current' => $current_seo['site_title'],
                    'backup' => $backup_seo['site_title']
                );
            }
            if ($current_seo['site_description'] !== $backup_seo['site_description']) {
                $differences['site_description'] = array(
                    'current' => $current_seo['site_description'],
                    'backup' => $backup_seo['site_description']
                );
            }

            Logger::debug('SEO metadata comparison', array(
                'landing_page_id' => $landing_page_id,
                'current_seo' => $current_seo,
                'backup_seo' => $backup_seo,
                'has_differences' => !empty($differences)
            ));
        }

        return $differences;
    }

    /**
     * Compare metadata arrays
     *
     * @param array $current Current metadata
     * @param array $backup Backup metadata
     * @return array Array of differences
     * @since 1.0.0
     */
    private static function compare_metadata($current, $backup)
    {
        $differences = array();

        $fields = array('site_name', 'site_title', 'site_description');

        foreach ($fields as $field) {
            $current_value = isset($current[$field]) ? $current[$field] : '';
            $backup_value = isset($backup[$field]) ? $backup[$field] : '';

            if ($current_value !== $backup_value) {
                $differences[$field] = array(
                    'current' => $current_value,
                    'backup' => $backup_value
                );
            }
        }

        return $differences;
    }

    /**
     * Import language data from backup
     *
     * @param array $backup_data Backup data to import
     * @param array $import_options Import options (which languages to import, etc.)
     * @return array|WP_Error Import results or WP_Error on failure
     * @since 1.0.0
     */
    public static function import_language_data($backup_data, $import_options = array())
    {
        Logger::info('Starting language data import', array('options' => $import_options));

        // Ensure LanguageManager is loaded
        if (!class_exists('\EZTranslate\LanguageManager')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        }

        // Validate backup structure first
        $validation_result = self::validate_backup_structure($backup_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        $results = array(
            'success' => true,
            'languages' => array(
                'created' => array(),
                'updated' => array(),
                'skipped' => array(),
                'errors' => array()
            ),
            'default_metadata' => array(
                'updated' => false,
                'error' => null
            ),
            'summary' => array(
                'total_processed' => 0,
                'successful_operations' => 0,
                'failed_operations' => 0
            )
        );

        try {
            // Import default metadata if requested
            if (isset($import_options['import_default_metadata']) && $import_options['import_default_metadata']) {
                $metadata_result = self::import_default_metadata($backup_data['data']['default_metadata']);
                if (is_wp_error($metadata_result)) {
                    $results['default_metadata']['error'] = $metadata_result->get_error_message();
                    $results['summary']['failed_operations']++;
                } else {
                    $results['default_metadata']['updated'] = true;
                    $results['summary']['successful_operations']++;
                }
            }

            // Import languages
            $backup_languages = $backup_data['data']['languages'];
            $selected_languages = isset($import_options['selected_languages']) ? $import_options['selected_languages'] : array();

            foreach ($backup_languages as $backup_language) {
                $language_code = $backup_language['code'];
                $results['summary']['total_processed']++;

                // Skip if not selected for import
                if (!empty($selected_languages) && !in_array($language_code, $selected_languages)) {
                    $results['languages']['skipped'][] = array(
                        'code' => $language_code,
                        'reason' => 'not_selected'
                    );
                    continue;
                }

                // Check if language exists
                $existing_language = LanguageManager::get_language($language_code);

                if (!$existing_language) {
                    // Create new language
                    $create_result = self::create_language_from_backup($backup_language);
                    if (is_wp_error($create_result)) {
                        $results['languages']['errors'][] = array(
                            'code' => $language_code,
                            'operation' => 'create',
                            'error' => $create_result->get_error_message()
                        );
                        $results['summary']['failed_operations']++;
                    } else {
                        $results['languages']['created'][] = array(
                            'code' => $language_code,
                            'name' => $backup_language['name'],
                            'landing_page_id' => $create_result['landing_page_id'] ?? null
                        );
                        $results['summary']['successful_operations']++;
                    }
                } else {
                    // Update existing language
                    $update_result = self::update_language_from_backup($language_code, $backup_language);
                    if (is_wp_error($update_result)) {
                        $results['languages']['errors'][] = array(
                            'code' => $language_code,
                            'operation' => 'update',
                            'error' => $update_result->get_error_message()
                        );
                        $results['summary']['failed_operations']++;
                    } else {
                        $results['languages']['updated'][] = array(
                            'code' => $language_code,
                            'name' => $backup_language['name']
                        );
                        $results['summary']['successful_operations']++;
                    }
                }
            }

            // Determine overall success
            $results['success'] = $results['summary']['failed_operations'] === 0;

            Logger::info('Language data import completed', $results['summary']);

            return $results;
        } catch (\Exception $e) {
            Logger::error('Language data import failed', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return new \WP_Error('import_failed', __('Import failed: ', 'ez-translate') . $e->getMessage());
        }
    }

    /**
     * Import default metadata
     *
     * @param array $metadata Default metadata to import
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    private static function import_default_metadata($metadata)
    {
        $sanitized_metadata = array(
            'site_name' => isset($metadata['site_name']) ? sanitize_text_field($metadata['site_name']) : '',
            'site_title' => isset($metadata['site_title']) ? sanitize_text_field($metadata['site_title']) : '',
            'site_description' => isset($metadata['site_description']) ? sanitize_textarea_field($metadata['site_description']) : ''
        );

        $result = update_option('ez_translate_default_language_metadata', $sanitized_metadata);

        if (!$result) {
            return new \WP_Error('metadata_update_failed', __('Failed to update default metadata.', 'ez-translate'));
        }

        Logger::info('Default metadata imported successfully', $sanitized_metadata);
        return true;
    }

    /**
     * Create new language from backup data
     *
     * @param array $backup_language Backup language data
     * @return array|WP_Error Result array or WP_Error on failure
     * @since 1.0.0
     */
    private static function create_language_from_backup($backup_language)
    {
        // Prepare language data for creation
        $language_data = array(
            'code' => $backup_language['code'],
            'name' => $backup_language['name'],
            'slug' => isset($backup_language['slug']) ? $backup_language['slug'] : $backup_language['code'],
            'native_name' => isset($backup_language['native_name']) ? $backup_language['native_name'] : '',
            'flag' => isset($backup_language['flag']) ? $backup_language['flag'] : '',
            'rtl' => isset($backup_language['rtl']) ? (bool) $backup_language['rtl'] : false,
            'enabled' => isset($backup_language['enabled']) ? (bool) $backup_language['enabled'] : true,
            'site_name' => isset($backup_language['site_name']) ? $backup_language['site_name'] : '',
            'site_title' => isset($backup_language['site_title']) ? $backup_language['site_title'] : '',
            'site_description' => isset($backup_language['site_description']) ? $backup_language['site_description'] : ''
        );

        // Prepare landing page data with SEO information
        $landing_page_data = array(
            'title' => isset($backup_language['site_title']) ? $backup_language['site_title'] : $backup_language['name'],
            'description' => isset($backup_language['site_description']) ? $backup_language['site_description'] : '',
            'slug' => isset($backup_language['slug']) ? $backup_language['slug'] : $backup_language['code'],
            'status' => 'publish'
        );

        // Create the language with landing page data
        $result = LanguageManager::add_language($language_data, $landing_page_data);

        if (is_wp_error($result)) {
            Logger::error('Failed to create language from backup', array(
                'code' => $backup_language['code'],
                'error' => $result->get_error_message()
            ));
            return $result;
        }

        // Ensure SEO data is set in the landing page
        if (isset($result['landing_page_id']) && $result['landing_page_id'] > 0) {
            $landing_page_id = $result['landing_page_id'];

            // Update SEO data in the landing page using the official method only
            $seo_data = array(
                'title' => isset($backup_language['site_title']) ? $backup_language['site_title'] : '',
                'description' => isset($backup_language['site_description']) ? $backup_language['site_description'] : ''
            );

            if (!empty($seo_data['title']) || !empty($seo_data['description'])) {
                // Usar solo el método oficial para actualizar los metadatos SEO
                $seo_update_result = LanguageManager::update_landing_page_seo($landing_page_id, $seo_data);

                if (is_wp_error($seo_update_result)) {
                    Logger::warning('Failed to set landing page SEO data for new language', array(
                        'code' => $backup_language['code'],
                        'landing_page_id' => $landing_page_id,
                        'error' => $seo_update_result->get_error_message()
                    ));
                    // Continue anyway as the language was created successfully
                } else {
                    Logger::info('Landing page SEO data set for new language', array(
                        'code' => $backup_language['code'],
                        'landing_page_id' => $landing_page_id,
                        'seo_data' => $seo_data
                    ));
                }
            }
        }

        Logger::info('Language created from backup', array(
            'code' => $backup_language['code'],
            'name' => $backup_language['name'],
            'landing_page_created' => isset($result['landing_page_id'])
        ));

        return $result;
    }

    /**
     * Update existing language from backup data
     *
     * @param string $language_code Language code to update
     * @param array $backup_language Backup language data
     * @return bool|WP_Error True on success, WP_Error on failure
     * @since 1.0.0
     */
    private static function update_language_from_backup($language_code, $backup_language)
    {
        Logger::info('Starting update from backup', array(
            'code' => $language_code,
            'backup_data' => $backup_language,
            'memory_usage' => memory_get_usage()
        ));

        // Prepare update data (excluding immutable fields like code, slug, landing_page_id)
        $update_data = array(
            'name' => isset($backup_language['name']) ? $backup_language['name'] : '',
            'native_name' => isset($backup_language['native_name']) ? $backup_language['native_name'] : '',
            'flag' => isset($backup_language['flag']) ? $backup_language['flag'] : '',
            'rtl' => isset($backup_language['rtl']) ? (bool) $backup_language['rtl'] : false,
            'enabled' => isset($backup_language['enabled']) ? (bool) $backup_language['enabled'] : true,
            'site_name' => isset($backup_language['site_name']) ? $backup_language['site_name'] : '',
            'site_title' => isset($backup_language['site_title']) ? $backup_language['site_title'] : '',
            'site_description' => isset($backup_language['site_description']) ? $backup_language['site_description'] : ''
        );

        Logger::info('Prepared update data', array(
            'code' => $language_code,
            'update_data' => $update_data
        ));

        // Update the language
        $result = LanguageManager::update_language($language_code, $update_data);

        if (is_wp_error($result)) {
            Logger::error('Failed to update language from backup', array(
                'code' => $language_code,
                'error' => $result->get_error_message(),
                'error_data' => $result->get_error_data()
            ));
            return $result;
        }

        // Now update the landing page SEO data if available
        $current_language = LanguageManager::get_language($language_code);
        if ($current_language && !empty($current_language['landing_page_id'])) {
            $landing_page_id = $current_language['landing_page_id'];

            // Always update SEO data from backup, regardless of current values
            $seo_data = array(
                'title' => isset($backup_language['site_title']) ? $backup_language['site_title'] : '',
                'description' => isset($backup_language['site_description']) ? $backup_language['site_description'] : ''
            );

            // Force update SEO metadata
            update_post_meta($landing_page_id, '_ez_translate_seo_title', $seo_data['title']);
            update_post_meta($landing_page_id, '_ez_translate_seo_description', $seo_data['description']);

            // Also update using the official method to maintain consistency
            $seo_update_result = LanguageManager::update_landing_page_seo($landing_page_id, $seo_data);

            if (is_wp_error($seo_update_result)) {
                Logger::warning('Failed to update landing page SEO data from backup using official method', array(
                    'code' => $language_code,
                    'landing_page_id' => $landing_page_id,
                    'error' => $seo_update_result->get_error_message()
                ));
                // Continue anyway as we've already updated the meta directly
            } else {
                Logger::info('Landing page SEO data updated from backup', array(
                    'code' => $language_code,
                    'landing_page_id' => $landing_page_id,
                    'seo_data' => $seo_data
                ));
            }
        }

        return true;
    }

    /**
     * Parse uploaded backup file
     *
     * @param array $file Uploaded file data with sanitized fields
     * @return array|WP_Error Parsed backup data or WP_Error on failure
     * @since 1.0.0
     */
    public static function parse_backup_file($file)
    {
        // Validate file upload
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new \WP_Error('invalid_file', __('Invalid file upload.', 'ez-translate'));
        }

        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return new \WP_Error('file_too_large', __('File is too large. Maximum size is 5MB.', 'ez-translate'));
        }

        // Check file extension - use sanitized name
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_extension !== 'json') {
            return new \WP_Error('invalid_extension', __('File must be a JSON file.', 'ez-translate'));
        }

        // Read file content
        $file_content = file_get_contents($file['tmp_name']);
        if ($file_content === false) {
            return new \WP_Error('read_failed', __('Failed to read file content.', 'ez-translate'));
        }

        // Parse JSON
        $backup_data = json_decode($file_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('invalid_json', sprintf(
            /* translators: %s: JSON error message */
                __('Invalid JSON format: %s', 'ez-translate'),
                json_last_error_msg()
            ));
        }

        // Validate structure
        $validation_result = self::validate_backup_structure($backup_data);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        Logger::info('Backup file parsed successfully', array(
            'filename' => $file['name'],
            'size' => $file['size'],
            'languages_count' => count($backup_data['data']['languages'])
        ));

        return $backup_data;
    }

    private function handle_import_preview()
    {
        Logger::info('Starting backup preview generation');

        // Validate file upload
        if (
            !isset($_FILES['backup_file']) ||
            !isset($_FILES['backup_file']['error']) ||
            $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK
        ) {
            $this->add_admin_notice(__('Please select a valid backup file.', 'ez-translate'), 'error');
            return;
        }

        // Parse backup file
        $backup_data = self::parse_backup_file($_FILES['backup_file']);

        if (is_wp_error($backup_data)) {
            $this->add_admin_notice(
                sprintf(
                    /* translators: %s: error message */
                    __('Failed to parse backup file: %s', 'ez-translate'),
                    $backup_data->get_error_message()
                ),
                'error'
            );
            return;
        }

        // Store backup data in session for later use
        if (!session_id()) {
            session_start();
        }
        $_SESSION['ez_translate_backup_data'] = $backup_data;

        // Compare with current data
        $comparison = self::compare_with_current($backup_data);

        // Force check SEO metadata differences
        foreach ($comparison['languages']['existing'] as &$language) {
            $current_lang = self::find_language_by_code(LanguageManager::get_languages(false), $language['code']);
            if ($current_lang) {
                // Get current SEO data from landing page
                $landing_page_id = $current_lang['landing_page_id'];
                $current_seo = array(
                    'site_title' => get_post_meta($landing_page_id, '_ez_translate_seo_title', true),
                    'site_description' => get_post_meta($landing_page_id, '_ez_translate_seo_description', true)
                );

                // Get backup SEO data
                $backup_seo = array(
                    'site_title' => $language['backup']['site_title'] ?? '',
                    'site_description' => $language['backup']['site_description'] ?? ''
                );

                // Add SEO differences
                if ($current_seo['site_title'] !== $backup_seo['site_title']) {
                    $language['differences']['site_title'] = array(
                        'current' => $current_seo['site_title'],
                        'backup' => $backup_seo['site_title']
                    );
                }
                if ($current_seo['site_description'] !== $backup_seo['site_description']) {
                    $language['differences']['site_description'] = array(
                        'current' => $current_seo['site_description'],
                        'backup' => $backup_seo['site_description']
                    );
                }

                Logger::debug('SEO metadata comparison', array(
                    'language_code' => $language['code'],
                    'current_seo' => $current_seo,
                    'backup_seo' => $backup_seo,
                    'has_differences' => !empty($language['differences'])
                ));
            }
        }

        // Store comparison data for display
        $this->backup_comparison = $comparison;

        Logger::info('Backup preview generated successfully', array(
            'backup_languages' => $comparison['summary']['total_backup_languages'],
            'new_languages' => $comparison['summary']['new_languages_count'],
            'updated_languages' => $comparison['summary']['updated_languages_count'],
            'has_seo_changes' => !empty($comparison['languages']['existing'])
        ));
    }
}
