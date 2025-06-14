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
                'timestamp' => current_time('mysql'),
                'site_url' => get_site_url(),
                'wp_version' => get_bloginfo('version'),
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
            Logger::error('Failed to export language data', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return new \WP_Error('export_failed', __('Failed to export language data: ', 'ez-translate') . $e->getMessage());
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

        // Ensure LanguageManager is loaded
        if (!class_exists('\EZTranslate\LanguageManager')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        }

        // Get current data
        $current_languages = LanguageManager::get_languages(false);
        $current_default_metadata = LanguageManager::get_default_language_metadata();

        $backup_languages = $backup_data['data']['languages'];
        $backup_default_metadata = $backup_data['data']['default_metadata'];

        $comparison = array(
            'languages' => array(
                'new' => array(),      // Languages in backup but not in current
                'existing' => array(), // Languages in both with differences
                'unchanged' => array() // Languages that are identical
            ),
            'default_metadata' => array(
                'changes' => array(),
                'unchanged' => array()
            ),
            'summary' => array(
                'total_backup_languages' => count($backup_languages),
                'total_current_languages' => count($current_languages),
                'new_languages_count' => 0,
                'updated_languages_count' => 0,
                'unchanged_languages_count' => 0
            )
        );

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

            // Update SEO data in the landing page
            $seo_data = array(
                'title' => isset($backup_language['site_title']) ? $backup_language['site_title'] : '',
                'description' => isset($backup_language['site_description']) ? $backup_language['site_description'] : ''
            );

            if (!empty($seo_data['title']) || !empty($seo_data['description'])) {
                // Actualizar directamente los metadatos SEO en la página de destino
                if (!empty($seo_data['title'])) {
                    update_post_meta($landing_page_id, '_ez_translate_seo_title', sanitize_text_field($seo_data['title']));
                }
                if (!empty($seo_data['description'])) {
                    update_post_meta($landing_page_id, '_ez_translate_seo_description', sanitize_textarea_field($seo_data['description']));
                }

                // También usar el método oficial para mantener coherencia
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

            // Update SEO data in the landing page
            $seo_data = array(
                'title' => isset($backup_language['site_title']) ? $backup_language['site_title'] : '',
                'description' => isset($backup_language['site_description']) ? $backup_language['site_description'] : ''
            );

            if (!empty($seo_data['title']) || !empty($seo_data['description'])) {
                // Actualizar directamente los metadatos SEO en la página de destino
                if (!empty($seo_data['title'])) {
                    update_post_meta($landing_page_id, '_ez_translate_seo_title', sanitize_text_field($seo_data['title']));
                }
                if (!empty($seo_data['description'])) {
                    update_post_meta($landing_page_id, '_ez_translate_seo_description', sanitize_textarea_field($seo_data['description']));
                }

                // También usar el método oficial para mantener coherencia
                $seo_update_result = LanguageManager::update_landing_page_seo($landing_page_id, $seo_data);

                if (is_wp_error($seo_update_result)) {
                    Logger::warning('Failed to update landing page SEO data from backup', array(
                        'code' => $language_code,
                        'landing_page_id' => $landing_page_id,
                        'error' => $seo_update_result->get_error_message()
                    ));
                    // Continue anyway as the language data was updated successfully
                } else {
                    Logger::info('Landing page SEO data updated from backup', array(
                        'code' => $language_code,
                        'landing_page_id' => $landing_page_id,
                        'seo_data' => $seo_data
                    ));
                }
            }
        }

        Logger::info('Language updated from backup', array(
            'code' => $language_code,
            'name' => $backup_language['name'],
            'result' => $result,
            'current_languages' => LanguageManager::get_languages(false)
        ));

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
}
