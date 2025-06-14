<?php

/**
 * EZ Translate Admin Class
 *
 * Handles all admin-related functionality for the EZ Translate plugin
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load backup manager class
require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-backup-manager.php';

/**
 * Admin class for EZ Translate
 *
 * @since 1.0.0
 */
class Admin
{

    /**
     * Menu slug for the main admin page
     *
     * @var string
     * @since 1.0.0
     */
    const MENU_SLUG = 'ez-translate';

    /**
     * Backup comparison data for import preview
     *
     * @var array|null
     * @since 1.0.0
     */
    private $backup_comparison = null;

    /**
     * Hook para la página principal de administración
     *
     * @var string
     */
    private $_page_hook;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->init_hooks();
        $this->init_sitemap_admin();
        $this->init_robots_admin();
        $this->init_seo_metadata_admin();
        $this->init_welcome_page();
        $this->init_dashboard_widget();
        Logger::info('Admin class initialized');
    }

    private function ez_translate_recursive_sanitize($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->ez_translate_recursive_sanitize($value);
            }
            return $data;
        }
        return sanitize_text_field($data);
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Admin styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Backup download handler
        add_action('admin_init', array($this, 'handle_backup_download'));

        // Landing Page column in pages list
        add_filter('manage_pages_columns', array($this, 'add_landing_page_column'));
        add_action('manage_pages_custom_column', array($this, 'show_landing_page_column_content'), 10, 2);

        // Landing Pages table below main pages list
        add_action('all_admin_notices', array($this, 'add_landing_pages_table'));

        // AJAX handlers
        add_action('wp_ajax_ez_translate_import_backup', array($this, 'handle_ajax_import_backup'));
        add_action('wp_ajax_ez_translate_preview_backup', array($this, 'handle_ajax_preview_backup'));
    }

    /**
     * Add admin menu
     *
     * @since 1.0.0
     */
    public function add_admin_menu()
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            Logger::warning('User attempted to access admin menu without proper capabilities', array(
                'user_id' => get_current_user_id(),
                'user_login' => wp_get_current_user()->user_login
            ));
            return;
        }

        // Add main menu page
        $this->_page_hook = add_menu_page(
            esc_html__('EZ Translate', 'ez-translate'),           // Page title
            esc_html__('EZ Translate', 'ez-translate'),           // Menu title
            'manage_options',                             // Capability
            self::MENU_SLUG,                             // Menu slug
            array($this, 'render_languages_page'),       // Callback function
            'dashicons-translation',                      // Icon
            21                                           // Position (after Pages which is 20)
        );

        // Add submenu page (Languages - same as main page)
        add_submenu_page(
            self::MENU_SLUG,                             // Parent slug
            esc_html__('Languages', 'ez-translate'),              // Page title
            esc_html__('Languages', 'ez-translate'),              // Menu title
            'manage_options',                             // Capability
            self::MENU_SLUG,                             // Menu slug (same as parent for main page)
            array($this, 'render_languages_page')        // Callback function
        );

        // Add Language Detector submenu page
        add_submenu_page(
            self::MENU_SLUG,                             // Parent slug
            esc_html__('Language Detector', 'ez-translate'),      // Page title
            esc_html__('Language Detector', 'ez-translate'),      // Menu title
            'manage_options',                             // Capability
            'ez-translate-detector',                      // Menu slug
            array($this, 'render_detector_page')         // Callback function
        );

        Logger::info('Admin menu added successfully', array(
            'page_hook' => $this->_page_hook,
            'menu_slug' => self::MENU_SLUG
        ));
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix The current admin page hook suffix
     * @since 1.0.0
     */
    public function enqueue_admin_assets($hook_suffix)
    {
        // Only load on our admin pages
        if (strpos($hook_suffix, self::MENU_SLUG) === false) {
            return;
        }

        // Enqueue WordPress admin styles (we'll use native styling)
        wp_enqueue_style('wp-admin');

        // Backup import styles
        if ($hook_suffix === $this->_page_hook) {
            wp_enqueue_style(
                'ez-translate-backup-import',
                plugins_url('assets/css/backup-import.css', EZ_TRANSLATE_PLUGIN_FILE),
                array(),
                EZ_TRANSLATE_VERSION
            );
        }
    }

    /**
     * Handle form submissions for language management
     *
     * @since 1.0.0
     */
    private function handle_form_submissions()
    {
        // Check if this is a form submission
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification happens below
        if (!isset($_POST['ez_translate_action'])) {
            return;
        }

        // Verify nonce - check for any of the valid nonce names
        $nonce_verified = false;
        $nonce_fields = array(
            'ez_translate_nonce',
            'ez_translate_nonce_add',
            'ez_translate_nonce_api',
            'ez_translate_nonce_edit',
            'ez_translate_nonce_seo',
            'ez_translate_nonce_detector',
            'ez_translate_nonce_messages',
            'ez_translate_nonce_backup',
            'ez_translate_nonce_import'
        );

        foreach ($nonce_fields as $nonce_field) {
            if (isset($_POST[$nonce_field]) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_field])), 'ez_translate_admin')) {
                $nonce_verified = true;
                break;
            }
            // Also check for dynamic delete nonces
            if (
                strpos($nonce_field, 'ez_translate_nonce_delete_') === 0 &&
                isset($_POST[$nonce_field]) &&
                wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_field])), 'ez_translate_admin')
            ) {
                $nonce_verified = true;
                break;
            }
        }

        // Check for dynamic delete nonces
        if (!$nonce_verified) {
            foreach ($_POST as $key => $value) {
                if (
                    strpos($key, 'ez_translate_nonce_delete_') === 0 &&
                    wp_verify_nonce(sanitize_text_field(wp_unslash($value)), 'ez_translate_admin')
                ) {
                    $nonce_verified = true;
                    break;
                }
            }
        }

        if (!$nonce_verified) {
            return;
        }

        // Load the language manager
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';

        // Load backup manager for backup/import operations
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-backup-manager.php';

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $action = sanitize_text_field(wp_unslash($_POST['ez_translate_action']));
        Logger::info('Processing form submission', array('action' => $action));

        switch ($action) {
            case 'add_language':
                $this->handle_add_language();
                break;
            case 'edit_language':
                $this->handle_edit_language();
                break;
            case 'delete_language':
                $this->handle_delete_language();
                break;

            case 'update_landing_page_seo':
                $this->handle_update_landing_page_seo();
                break;
            case 'update_api_settings':
                $this->handle_update_api_settings();
                break;

            case 'repair_landing_pages':
                $this->handle_repair_landing_pages();
                break;
            case 'update_detector_settings':
                $this->handle_update_detector_settings();
                break;
            case 'update_detector_messages':
                $this->handle_update_detector_messages();
                break;
            case 'sync_all_languages_seo':
                $this->handle_sync_all_languages_seo();
                break;
            case 'export_backup':
                $this->handle_export_backup();
                break;
            case 'import_backup':
                $this->handle_import_backup();
                break;
            default:
                Logger::warning('Unknown form action', array('action' => $action));
                break;
        }
    }

    /**
     * Handle synchronization of all languages with landing page SEO
     *
     * @since 1.0.0
     */
    private function handle_sync_all_languages_seo()
    {
        Logger::info('Starting manual synchronization of all languages with landing page SEO');

        $sync_results = \EZTranslate\LanguageManager::sync_all_languages_with_landing_seo();

        if ($sync_results['errors'] > 0) {
            $this->add_admin_notice(
                sprintf(
                    /* translators: %1$d: synchronized count, %2$d: error count */
                    __('Synchronization completed with some issues. %1$d languages synchronized, %2$d errors occurred.', 'ez-translate'),
                    $sync_results['synchronized'],
                    $sync_results['errors']
                ),
                'warning'
            );
        } else {
            $this->add_admin_notice(
                sprintf(
                    /* translators: %1$d: synchronized count, %2$d: total count */
                    __('Synchronization completed successfully! %1$d of %2$d languages synchronized with their landing page SEO data.', 'ez-translate'),
                    $sync_results['synchronized'],
                    $sync_results['total_languages']
                ),
                'success'
            );
        }

        Logger::info('Manual synchronization completed', $sync_results);
    }

    /**
     * Handle backup export
     *
     * @since 1.0.0
     */
    private function handle_export_backup()
    {
        Logger::info('Processing backup export request');

        $backup_result = \EZTranslate\BackupManager::generate_backup_file();

        if (is_wp_error($backup_result)) {
            $this->add_admin_notice(
                sprintf(
                    /* translators: %s: error message */
                    __('Failed to generate backup: %s', 'ez-translate'),
                    $backup_result->get_error_message()
                ),
                'error'
            );
            Logger::error('Backup export failed', array(
                'error' => $backup_result->get_error_message()
            ));
            return;
        }

        // Store backup data in transient for download
        $download_key = 'ez_translate_backup_' . wp_generate_password(12, false);
        set_transient($download_key, $backup_result, 300); // 5 minutes

        // Redirect to download URL
        $download_url = add_query_arg(array(
            'ez_translate_download' => $download_key,
            'nonce' => wp_create_nonce('ez_translate_download')
        ), admin_url('admin.php'));

        Logger::info('Backup export prepared for download', array(
            'filename' => $backup_result['filename'],
            'size' => $backup_result['size'],
            'languages_count' => $backup_result['languages_count']
        ));

        // Redirect to trigger download
        wp_redirect($download_url);
        exit;
    }

    /**
     * Handle backup import
     *
     * @since 1.0.0
     */
    private function handle_import_backup()
    {
        Logger::info('Processing backup import request');

        // Ensure session is started for storing backup data
        if (!session_id()) {
            session_start();
        }

        // Check if this is a preview request or actual import
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $is_preview = isset($_POST['preview_import']) && $_POST['preview_import'] === '1';


        if ($is_preview) {
            $this->handle_import_preview();
            return;
        }

        // Check if this is the actual import with selected languages
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        if (!isset($_POST['confirm_import']) || $_POST['confirm_import'] !== '1') {
            $this->add_admin_notice(__('Import confirmation required.', 'ez-translate'), 'error');
            return;
        }

        // Get the backup data from session (stored during preview)
        if (!isset($_SESSION['ez_translate_backup_data'])) {
            $this->add_admin_notice(__('Backup data not found. Please upload the file again.', 'ez-translate'), 'error');
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $backup_data = $this->ez_translate_recursive_sanitize(wp_unslash($_SESSION['ez_translate_backup_data']));

        // Get import options
        $import_options = array(
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
            'selected_languages' => isset($_POST['selected_languages']) ? array_map('sanitize_text_field', wp_unslash($_POST['selected_languages'])) : array(),
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
            'import_default_metadata' => isset($_POST['import_default_metadata']) && $_POST['import_default_metadata'] === '1'
        );

        // Perform the import
        $import_result = \EZTranslate\BackupManager::import_language_data($backup_data, $import_options);

        if (is_wp_error($import_result)) {
            $this->add_admin_notice(
                sprintf(
                    /* translators: %s: error message */
                    __('Import failed: %s', 'ez-translate'),
                    $import_result->get_error_message()
                ),
                'error'
            );
            Logger::error('Backup import failed', array(
                'error' => $import_result->get_error_message()
            ));
        } else {
            // Generate success message with details
            $message = __('Import completed successfully!', 'ez-translate');

            if ($import_result['summary']['successful_operations'] > 0) {
                $message .= sprintf(
                    ' %d %s',
                    $import_result['summary']['successful_operations'],
                    __('operations completed.', 'ez-translate')
                );
            }

            if ($import_result['summary']['failed_operations'] > 0) {
                $message .= sprintf(
                    ' %d %s',
                    $import_result['summary']['failed_operations'],
                    __('operations failed.', 'ez-translate')
                );
            }

            $notice_type = $import_result['summary']['failed_operations'] > 0 ? 'warning' : 'success';
            $this->add_admin_notice($message, $notice_type);

            Logger::info('Backup import completed', $import_result['summary']);
        }

        // Clear the session data
        unset($_SESSION['ez_translate_backup_data']);
    }

    /**
     * Handle import preview
     *
     * @since 1.0.0
     */
    private function handle_import_preview()
    {
        // Ensure backup manager is loaded
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-backup-manager.php';

        // Validate file upload
        if (
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
            !isset($_FILES['backup_file']) ||
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
            !isset($_FILES['backup_file']['error']) ||
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
            $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK
        ) {
            $this->add_admin_notice(__('Please select a valid backup file.', 'ez-translate'), 'error');
            return;
        }
        // Parse the backup file
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        if (isset($_FILES['backup_file']['name'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
            $file_name = sanitize_file_name($_FILES['backup_file']['name']);
        }
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
        $comparison = \EZTranslate\BackupManager::compare_with_current($backup_data);

        // Store comparison data for display
        $this->backup_comparison = $comparison;

        Logger::info('Backup preview generated successfully', array(
            'backup_languages' => $comparison['summary']['total_backup_languages'],
            'new_languages' => $comparison['summary']['new_languages_count'],
            'updated_languages' => $comparison['summary']['updated_languages_count']
        ));
    }

    /**
     * Handle backup file download
     *
     * @since 1.0.0
     */
    public function handle_backup_download()
    {
        // Check if this is a download request        
        if (!isset($_GET['ez_translate_download']) || !isset($_GET['nonce'])) {
            return;
        }

        // Verify nonce
        $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';

        if (!wp_verify_nonce($nonce, 'ez_translate_download')) {
            wp_die(esc_html(__('Security check failed.', 'ez-translate')));
        }

        // Get download key
        $download_key = sanitize_text_field(wp_unslash($_GET['ez_translate_download']));

        // Get backup data from transient
        $backup_result = get_transient($download_key);

        if (!$backup_result) {
            wp_die(esc_html(__('Download link has expired. Please generate a new backup.', 'ez-translate')));
        }

        // Delete the transient (one-time use)
        delete_transient($download_key);

        // Clean any output buffers
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers for file download
        $filename = $backup_result['filename'];
        $content = $backup_result['content'];

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        header('Pragma: public');

        // Output the file content and exit
        echo wp_kses_data($content);
        Logger::info('Backup download completed successfully', array(
            'filename' => $filename,
            'size' => $backup_result['size'],
            'languages_count' => $backup_result['languages_count']
        ));

        exit;
    }

    /**
     * Handle adding a new language
     *
     * @since 1.0.0
     */
    private function handle_add_language()
    {
        // Sanitize input data
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $language_data = \EZTranslate\LanguageManager::sanitize_language_data($_POST);

        // Check if trying to add WordPress default language
        $wp_locale = get_locale();
        $wp_language_code = strstr($wp_locale, '_', true) ?: $wp_locale; // es_MX -> es

        if (!empty($language_data['code']) && $language_data['code'] === $wp_language_code) {
            $wp_language_names = array(
                'en' => 'English',
                'es' => 'Español',
                'pt' => 'Português',
                'fr' => 'Français',
                'de' => 'Deutsch',
                'it' => 'Italiano',
                'ja' => '日本語',
                'ko' => '한국어',
                'zh' => '中文',
                'ru' => 'Русский',
                'ar' => 'العربية'
            );
            $wp_language_name = isset($wp_language_names[$wp_language_code]) ? $wp_language_names[$wp_language_code] : $wp_language_code;

            $error_message = sprintf(
                /* translators: %1$s: language name, %2$s: language code */
                __('Cannot add "%1$s" (%2$s) as it is your site\'s default language. Configure its metadata in the "Site Default Language Metadata" section below instead.', 'ez-translate'),
                $wp_language_name,
                $wp_language_code
            );
            $this->add_admin_notice($error_message, 'error');
            return;
        }

        // Add the language (landing page will be created automatically)
        $result = \EZTranslate\LanguageManager::add_language($language_data);

        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            if (isset($result['landing_page_id'])) {
                $edit_url = admin_url('post.php?post=' . $result['landing_page_id'] . '&action=edit');
                $this->add_admin_notice(
                    sprintf(
                        /* translators: %s: URL to edit the landing page */
                        __('Language added successfully! Landing page created automatically: <a href="%s" target="_blank">Edit Landing Page</a>', 'ez-translate'),
                        esc_url($edit_url)
                    ),
                    'success'
                );
            } else {
                $this->add_admin_notice(__('Language added successfully!', 'ez-translate'), 'success');
            }
        }
    }

    /**
     * Handle editing a language
     *
     * @since 1.0.0
     */
    private function handle_edit_language()
    {
        // Validate required POST data exists
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        if (!isset($_POST['original_code'])) {
            $this->add_admin_notice(__('Missing original language code.', 'ez-translate'), 'error');
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $original_code = sanitize_text_field(wp_unslash($_POST['original_code']));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $language_data = \EZTranslate\LanguageManager::sanitize_language_data_for_update($_POST);

        // Note: Landing page creation is not allowed during updates for data integrity
        // To create or change landing pages, delete and recreate the language

        // Update the language first
        $result = \EZTranslate\LanguageManager::update_language($original_code, $language_data);

        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
            return;
        }

        // Language update completed successfully
        $this->add_admin_notice(__('Language updated successfully!', 'ez-translate'), 'success');
    }

    /**
     * Handle deleting a language
     *
     * @since 1.0.0
     */
    private function handle_delete_language()
    {
        // Validate required POST data exists
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        if (!isset($_POST['language_code'])) {
            $this->add_admin_notice(__('Missing language code.', 'ez-translate'), 'error');
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $code = sanitize_text_field(wp_unslash($_POST['language_code']));

        $result = \EZTranslate\LanguageManager::delete_language($code);

        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $this->add_admin_notice(__('Language deleted successfully!', 'ez-translate'), 'success');
        }
    }

    /**
     * Handle landing page SEO update
     *
     * @since 1.0.0
     */
    private function handle_update_landing_page_seo()
    {
        // Validate required POST data exists
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        if (!isset($_POST['language_code']) || !isset($_POST['seo_title']) || !isset($_POST['seo_description'])) {
            $this->add_admin_notice(__('Missing required SEO data.', 'ez-translate'), 'error');
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $language_code = sanitize_text_field(wp_unslash($_POST['language_code']));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $seo_title = sanitize_text_field(wp_unslash($_POST['seo_title']));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $seo_description = sanitize_textarea_field(wp_unslash($_POST['seo_description']));

        Logger::info('Processing landing page SEO update', array(
            'post_id' => $post_id,
            'language_code' => $language_code
        ));

        if (empty($post_id)) {
            $this->add_admin_notice(__('Invalid post ID.', 'ez-translate'), 'error');
            return;
        }

        $seo_data = array(
            'title' => $seo_title,
            'description' => $seo_description
        );

        $result = \EZTranslate\LanguageManager::update_landing_page_seo($post_id, $seo_data);

        if (is_wp_error($result)) {
            $this->add_admin_notice(
                sprintf(
                    /* translators: %s: error message */
                    __('Failed to update SEO: %s', 'ez-translate'),
                    $result->get_error_message()
                ),
                'error'
            );
            Logger::error('Failed to update landing page SEO', array(
                'post_id' => $post_id,
                'error' => $result->get_error_message()
            ));
        } else {
            $this->add_admin_notice(__('Landing page SEO updated successfully! Language metadata has been synchronized.', 'ez-translate'), 'success');
            Logger::info('Landing page SEO updated successfully', array(
                'post_id' => $post_id,
                'language_code' => $language_code
            ));

            // Add JavaScript to reload the page after successful update
            add_action('admin_footer', function () {
                echo '<script type="text/javascript">
                    jQuery(document).ready(function($) {
                        // Close the SEO modal if it exists
                        $("#ez-translate-seo-modal").hide();

                        // Reload the page to show updated data
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    });
                </script>';
            });
        }
    }

    /**
     * Handle API settings update
     *
     * @since 1.0.0
     */
    private function handle_update_api_settings()
    {
        Logger::info('Processing API settings update');

        // Sanitize input data
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $enabled = isset($_POST['api_enabled']) && $_POST['api_enabled'] === '1';

        $settings = array(
            'api_key' => $api_key,
            'enabled' => $enabled
        );

        $result = \EZTranslate\LanguageManager::update_api_settings($settings);

        if (is_wp_error($result)) {
            $this->add_admin_notice(
                sprintf(
                    /* translators: %s: error message */
                    __('Failed to update API settings: %s', 'ez-translate'),
                    $result->get_error_message()
                ),
                'error'
            );
            Logger::error('Failed to update API settings', array(
                'error' => $result->get_error_message()
            ));
        } else {
            $this->add_admin_notice(__('API settings updated successfully!', 'ez-translate'), 'success');
            Logger::info('API settings updated successfully', array(
                'has_api_key' => !empty($api_key),
                'enabled' => $enabled
            ));
        }
    }

    /**
     * Handle landing pages repair
     *
     * @since 1.0.0
     */
    private function handle_repair_landing_pages()
    {
        Logger::info('Processing landing pages repair');

        $result = \EZTranslate\LanguageManager::repair_missing_landing_pages();

        if ($result['successfully_repaired'] > 0) {
            $message = sprintf(
                /* translators: %1$d: number of repaired languages, %2$d: total checked */
                __('Landing page repair completed! Successfully repaired %1$d out of %2$d languages checked.', 'ez-translate'),
                $result['successfully_repaired'],
                $result['total_checked']
            );

            // Add details about repaired languages
            $details = array();
            foreach ($result['details'] as $detail) {
                if ($detail['status'] === 'repaired' || $detail['status'] === 'repaired_fallback') {
                    $details[] = sprintf(
                        /* translators: %1$s: language name, %2$s: page title */
                        __('%1$s → "%2$s"', 'ez-translate'),
                        esc_html($detail['language_name']),
                        esc_html($detail['post_title'])
                    );
                }
            }

            if (!empty($details)) {
                $message .= '<br><strong>' . __('Repaired languages:', 'ez-translate') . '</strong><br>' . implode('<br>', $details);
            }

            $this->add_admin_notice($message, 'success');
        } elseif ($result['found_missing'] > 0) {
            $message = sprintf(
                /* translators: %d: number of languages that couldn't be repaired */
                __('No landing pages could be repaired. %d languages still need manual attention.', 'ez-translate'),
                $result['failed_repairs']
            );

            // Add details about failed repairs
            $details = array();
            foreach ($result['details'] as $detail) {
                if ($detail['status'] === 'not_found') {
                    $details[] = sprintf(
                        /* translators: %s: language name */
                        __('%s (no landing page found)', 'ez-translate'),
                        esc_html($detail['language_name'])
                    );
                }
            }

            if (!empty($details)) {
                $message .= '<br><strong>' . __('Languages needing manual attention:', 'ez-translate') . '</strong><br>' . implode('<br>', $details);
            }

            $this->add_admin_notice($message, 'warning');
        } else {
            $this->add_admin_notice(__('All languages already have valid landing pages. No repair needed.', 'ez-translate'), 'info');
        }

        Logger::info('Landing pages repair completed', $result);
    }

    /**
     * Procesa el formulario de actualización de configuraciones del detector de idioma
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_update_detector_settings()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        if (!isset($_POST['detector_settings']) || !is_array($_POST['detector_settings'])) {
            $this->add_admin_notice(__('Invalid detector settings data.', 'ez-translate'), 'error');
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
            Logger::warning('Invalid detector settings data received.', array('post_data' => $_POST));
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $raw_settings = isset($_POST['detector_settings']) ? wp_unslash($_POST['detector_settings']) : array();
        $settings = array();
        
        // Asegúrate de que $raw_settings es un array
        $raw_settings = is_array($raw_settings) ? $raw_settings : array();
        
        // Sanitiza los datos
        foreach ($raw_settings as $key => $value) {
            // Sanitiza la clave
            $sanitized_key = sanitize_key($key);
            
            // Sanitiza el valor dependiendo del tipo de dato
            if (is_array($value)) {
                $settings[$sanitized_key] = array_map('sanitize_text_field', $value);
            } else {
                $settings[$sanitized_key] = sanitize_text_field($value);
            }
        }

        // Sanitize each setting
        $settings['enabled'] = isset($raw_settings['enabled']) ? (bool) $raw_settings['enabled'] : false;
        $settings['detection_method'] = isset($raw_settings['detection_method']) ? sanitize_text_field($raw_settings['detection_method']) : 'browser';
        $settings['show_notice'] = isset($raw_settings['show_notice']) ? (bool) $raw_settings['show_notice'] : true;
        $settings['notice_type'] = isset($raw_settings['notice_type']) ? sanitize_text_field($raw_settings['notice_type']) : 'bar';
        $settings['redirect_method'] = isset($raw_settings['redirect_method']) ? sanitize_text_field($raw_settings['redirect_method']) : 'automatic';
        $settings['cookie_lifespan'] = isset($raw_settings['cookie_lifespan']) ? absint($raw_settings['cookie_lifespan']) : 30;
        $settings['excluded_paths'] = isset($raw_settings['excluded_paths']) ? array_map('sanitize_text_field', $raw_settings['excluded_paths']) : array();
        // Ensure excluded_paths is an array of non-empty strings
        $settings['excluded_paths'] = array_filter($settings['excluded_paths']);


        if (update_option('ez_translate_detector_settings', $settings)) {
            $this->add_admin_notice(__('Detector settings updated successfully.', 'ez-translate'), 'success');
            Logger::info('Detector settings updated successfully.', array('settings' => $settings));

            // Clear cache if LanguageDetector class exists
            if (class_exists('\EZTranslate\LanguageDetector')) {
                \EZTranslate\LanguageDetector::clear_cache();
                Logger::info('Language detector cache cleared.');
            }
        } else {
            $this->add_admin_notice(__('Failed to update detector settings or settings unchanged.', 'ez-translate'), 'warning');
            Logger::warning('Failed to update detector settings or settings unchanged.', array('settings' => $settings));
        }
    }

    /**
     * Procesa el formulario de actualización de mensajes del detector de idioma
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_update_detector_messages()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        if (!isset($_POST['detector_messages']) || !is_array($_POST['detector_messages'])) {
            $this->add_admin_notice(__('Invalid detector messages data.', 'ez-translate'), 'error');
            Logger::warning('Invalid detector messages data received.', array('post_data' => $_POST));
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $raw_messages = wp_unslash($_POST['detector_messages']);
        $messages = array();

        // Sanitize each message, allowing HTML for some fields
        // Example: Iterate through expected languages and message types
        $installed_languages = \EZTranslate\LanguageManager::get_languages();
        $default_wp_lang_code = substr(get_locale(), 0, 2);
        $language_codes = array_column($installed_languages, 'code');
        if (!in_array($default_wp_lang_code, $language_codes, true)) {
            $language_codes[] = $default_wp_lang_code;
        }


        foreach ($language_codes as $lang_code) {
            $safe_lang_code = sanitize_key($lang_code); // Ensure lang_code is safe for array keys
            if (isset($raw_messages[$safe_lang_code])) {
                $messages[$safe_lang_code]['notice_message'] = isset($raw_messages[$safe_lang_code]['notice_message']) ? wp_kses_post($raw_messages[$safe_lang_code]['notice_message']) : '';
                $messages[$safe_lang_code]['confirm_button'] = isset($raw_messages[$safe_lang_code]['confirm_button']) ? sanitize_text_field($raw_messages[$safe_lang_code]['confirm_button']) : '';
                $messages[$safe_lang_code]['cancel_button'] = isset($raw_messages[$safe_lang_code]['cancel_button']) ? sanitize_text_field($raw_messages[$safe_lang_code]['cancel_button']) : '';
            }
        }

        if (update_option('ez_translate_detector_messages', $messages)) {
            $this->add_admin_notice(__('Detector messages updated successfully.', 'ez-translate'), 'success');
            Logger::info('Detector messages updated successfully.');
        } else {
            $this->add_admin_notice(__('Failed to update detector messages or messages unchanged.', 'ez-translate'), 'warning');
            Logger::warning('Failed to update detector messages or messages unchanged.');
        }
    }

    /**
     * Add admin notice
     *
     * @param string $message Notice message
     * @param string $type    Notice type (success, error, warning, info)
     * @since 1.0.0
     */
    private function add_admin_notice($message, $type = 'info')
    {
        add_action('admin_notices', function () use ($message, $type) {
            $class = 'notice notice-' . $type;
            if ($type === 'error') {
                $class .= ' is-dismissible';
            }
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        });
    }

    /**
     * Get language options for select dropdown
     *
     * @param string $exclude_language_code Language code to exclude from options
     * @return string HTML options for language select
     * @since 1.0.0
     */
    private function get_language_options($exclude_language_code = '')
    {
        $languages = array(
            // Major world languages (most spoken)
            'en' => array('English', 'English', '🇺🇸'),
            'zh' => array('Chinese (Mandarin)', '中文', '🇨🇳'),
            'hi' => array('Hindi', 'हिन्दी', '🇮🇳'),
            'es' => array('Spanish', 'Español', '🇪🇸'),
            'fr' => array('French', 'Français', '🇫🇷'),
            'ar' => array('Arabic', 'العربية', '🇸🇦'),
            'bn' => array('Bengali', 'বাংলা', '🇧🇩'),
            'ru' => array('Russian', 'Русский', '🇷🇺'),
            'pt' => array('Portuguese', 'Português', '🇵🇹'),
            'id' => array('Indonesian', 'Bahasa Indonesia', '🇮🇩'),

            // Major European languages
            'de' => array('German', 'Deutsch', '🇩🇪'),
            'it' => array('Italian', 'Italiano', '🇮🇹'),
            'nl' => array('Dutch', 'Nederlands', '🇳🇱'),
            'pl' => array('Polish', 'Polski', '🇵🇱'),
            'tr' => array('Turkish', 'Türkçe', '🇹🇷'),
            'sv' => array('Swedish', 'Svenska', '🇸🇪'),
            'da' => array('Danish', 'Dansk', '🇩🇰'),
            'no' => array('Norwegian', 'Norsk', '🇳🇴'),
            'fi' => array('Finnish', 'Suomi', '🇫🇮'),
            'el' => array('Greek', 'Ελληνικά', '🇬🇷'),

            // Other major languages
            'ja' => array('Japanese', '日本語', '🇯🇵'),
            'ko' => array('Korean', '한국어', '🇰🇷'),
            'th' => array('Thai', 'ไทย', '🇹🇭'),
            'vi' => array('Vietnamese', 'Tiếng Việt', '🇻🇳'),
            'he' => array('Hebrew', 'עברית', '🇮🇱'),
            'fa' => array('Persian', 'فارسی', '🇮🇷'),
            'ur' => array('Urdu', 'اردو', '🇵🇰'),
            'ta' => array('Tamil', 'தமிழ்', '🇮🇳'),
            'te' => array('Telugu', 'తెలుగు', '🇮🇳'),
            'mr' => array('Marathi', 'मराठी', '🇮🇳'),

            // Additional European languages
            'cs' => array('Czech', 'Čeština', '🇨🇿'),
            'sk' => array('Slovak', 'Slovenčina', '🇸🇰'),
            'hu' => array('Hungarian', 'Magyar', '🇭🇺'),
            'ro' => array('Romanian', 'Română', '🇷🇴'),
            'bg' => array('Bulgarian', 'Български', '🇧🇬'),
            'hr' => array('Croatian', 'Hrvatski', '🇭🇷'),
            'sr' => array('Serbian', 'Српски', '🇷🇸'),
            'sl' => array('Slovenian', 'Slovenščina', '🇸🇮'),
            'et' => array('Estonian', 'Eesti', '🇪🇪'),
            'lv' => array('Latvian', 'Latviešu', '🇱🇻'),
            'lt' => array('Lithuanian', 'Lietuvių', '🇱🇹'),

            // African languages
            'sw' => array('Swahili', 'Kiswahili', '🇰🇪'),
            'am' => array('Amharic', 'አማርኛ', '🇪🇹'),
            'zu' => array('Zulu', 'isiZulu', '🇿🇦'),
            'af' => array('Afrikaans', 'Afrikaans', '🇿🇦'),

            // Other languages
            'ms' => array('Malay', 'Bahasa Melayu', '🇲🇾'),
            'tl' => array('Filipino', 'Filipino', '🇵🇭'),
            'uk' => array('Ukrainian', 'Українська', '🇺🇦'),
            'be' => array('Belarusian', 'Беларуская', '🇧🇾'),
            'ka' => array('Georgian', 'ქართული', '🇬🇪'),
            'hy' => array('Armenian', 'Հայերեն', '🇦🇲'),
            'az' => array('Azerbaijani', 'Azərbaycan', '🇦🇿'),
            'kk' => array('Kazakh', 'Қазақша', '🇰🇿'),
            'ky' => array('Kyrgyz', 'Кыргызча', '🇰🇬'),
            'uz' => array('Uzbek', 'Oʻzbekcha', '🇺🇿'),
            'mn' => array('Mongolian', 'Монгол', '🇲🇳'),
        );

        // Load the language manager to check existing languages
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        $existing_languages = \EZTranslate\LanguageManager::get_languages();
        $existing_codes = array();
        foreach ($existing_languages as $lang) {
            $existing_codes[] = $lang['code'];
        }

        $options = '';
        foreach ($languages as $code => $data) {
            $name = $data[0];
            $native_name = $data[1];
            $flag = $data[2];

            // Skip if language already exists
            if (in_array($code, $existing_codes)) {
                continue;
            }

            // Skip if this is the WordPress default language
            if (!empty($exclude_language_code) && $code === $exclude_language_code) {
                continue;
            }

            $display_name = $flag . ' ' . $name;
            if ($native_name !== $name) {
                $display_name .= ' (' . $native_name . ')';
            }
            $display_name .= ' [' . $code . ']';

            $options .= sprintf(
                '<option value="%s" data-name="%s" data-native="%s" data-flag="%s">%s</option>',
                esc_attr($code),
                esc_attr($name),
                esc_attr($native_name),
                esc_attr($flag),
                esc_html($display_name)
            );
        }

        return $options;
    }

    /**
     * Render the Languages admin page
     *
     * @since 1.0.0
     */
    public function render_languages_page()
    {
        // Verify user capabilities again
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ez-translate'));
        }

        Logger::info('Languages admin page accessed', array(
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login
        ));

        // Handle form submissions
        $this->handle_form_submissions();

        // Load the language manager
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';

        // Get current languages
        $languages = \EZTranslate\LanguageManager::get_languages();

?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <!-- Add New Language Form -->
            <div class="card" style="max-width: 1200px; width: 100%;">
                <h2><?php esc_html_e('Add New Language', 'ez-translate'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce_add'); ?>
                    <input type="hidden" name="ez_translate_action" value="add_language">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="language_code"><?php esc_html_e('Language Code', 'ez-translate'); ?> *</label>
                            </th>
                            <td>
                                <?php
                                // Get WordPress default language
                                $wp_locale = get_locale();
                                $wp_language_code = strstr($wp_locale, '_', true) ?: $wp_locale; // es_MX -> es
                                ?>
                                <select id="language_code_select" class="regular-text" style="margin-bottom: 10px;">
                                    <option value=""><?php esc_html_e('Select a common language...', 'ez-translate'); ?></option>
                                    <?php
                                    $allowed_html = array(
                                        'option' => array(
                                            'value' => array(),
                                            'data-name' => array(),
                                            'data-native' => array(),
                                            'data-flag' => array(),
                                        )
                                    );
                                    echo wp_kses($this->get_language_options($wp_language_code), $allowed_html);
                                    ?>
                                </select>
                                <br>
                                <input type="text" id="language_code" name="code" class="regular-text"
                                    placeholder="<?php esc_attr_e('Or enter custom code (e.g., en, es, fr)', 'ez-translate'); ?>"
                                    pattern="[a-zA-Z0-9]{2,5}" maxlength="5" required>
                                <p class="description">
                                    <?php esc_html_e('Select from common languages above or enter a custom ISO 639-1 code (2-5 characters)', 'ez-translate'); ?>
                                    <br>
                                    <strong style="color: #d63638;">
                                        <?php
                                        /* translators: %s: language code */
                                        printf(esc_html__('Note: Your site default language (%s) is not available as it\'s already configured below.', 'ez-translate'), esc_html($wp_language_code)); ?>
                                    </strong>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="language_name"><?php esc_html_e('Language Name', 'ez-translate'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="language_name" name="name" class="regular-text"
                                    placeholder="<?php esc_attr_e('e.g., English, Español, Français', 'ez-translate'); ?>" required>
                                <p class="description"><?php esc_html_e('Display name for the language', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="language_slug"><?php esc_html_e('Language Slug', 'ez-translate'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="language_slug" name="slug" class="regular-text"
                                    placeholder="<?php esc_attr_e('e.g., english, spanish, french', 'ez-translate'); ?>"
                                    pattern="[a-z0-9\-_]+" required>
                                <p class="description"><?php esc_html_e('URL-friendly slug (lowercase, no spaces)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="language_native_name"><?php esc_html_e('Native Name', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="language_native_name" name="native_name" class="regular-text"
                                    placeholder="<?php esc_attr_e('e.g., English, Español, Français', 'ez-translate'); ?>">
                                <p class="description"><?php esc_html_e('Name in the native language (optional)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="language_flag"><?php esc_html_e('Flag Emoji', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="language_flag" name="flag" class="small-text"
                                    placeholder="<?php esc_attr_e('🇺🇸', 'ez-translate'); ?>" maxlength="4">
                                <p class="description"><?php esc_html_e('Flag emoji for visual identification (optional)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Text Direction', 'ez-translate'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="rtl" value="1">
                                    <?php esc_html_e('Right-to-left (RTL) language', 'ez-translate'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Check if this language reads from right to left', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Status', 'ez-translate'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enabled" value="1" checked>
                                    <?php esc_html_e('Enable this language', 'ez-translate'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Disabled languages are hidden from frontend', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="language_site_name"><?php esc_html_e('Site Name', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="language_site_name" name="site_name" class="regular-text"
                                    placeholder="<?php esc_attr_e('e.g., WordPress Specialist, Especialista en WordPress', 'ez-translate'); ?>">
                                <p class="description"><?php esc_html_e('Short site name for this language (used in page titles). Example: "WordPress Specialist" for English.', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="language_site_title"><?php esc_html_e('Site Title', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="language_site_title" name="site_title" class="regular-text"
                                    placeholder="<?php esc_attr_e('e.g., My Website - English Version', 'ez-translate'); ?>">
                                <p class="description"><?php esc_html_e('Full site title for this language (used in landing pages and SEO metadata)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="language_site_description"><?php esc_html_e('Site Description', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <textarea id="language_site_description" name="site_description" class="large-text" rows="3"
                                    placeholder="<?php esc_attr_e('Brief description of your website in this language...', 'ez-translate'); ?>"></textarea>
                                <p class="description"><?php esc_html_e('Site description for this language (used in landing pages and SEO metadata)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <!-- Landing Page Information Section -->
                    <div class="card" style="margin-top: 20px;">
                        <h3><?php esc_html_e('Landing Page', 'ez-translate'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Auto-creation', 'ez-translate'); ?></th>
                                <td>
                                    <p class="description">
                                        <span class="dashicons dashicons-info" style="color: #0073aa;"></span>
                                        <?php esc_html_e('A landing page will be created automatically for this language with default content. You can edit it later from the Pages section or from the language settings.', 'ez-translate'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>


                    </div>

                    <?php submit_button(esc_html__('Add Language', 'ez-translate'), 'primary', 'submit', false); ?>
                </form>
            </div>

            <!-- Current Languages List -->
            <div class="card" style="max-width: 1200px; width: 100%;">
                <h2><?php esc_html_e('Current Languages', 'ez-translate'); ?></h2>
                <?php $this->render_languages_table($languages); ?>
            </div>

            <!-- Landing Pages Repair Section -->
            <?php
            $languages_needing_repair = \EZTranslate\LanguageManager::get_languages_needing_repair();
            if (!empty($languages_needing_repair)):
            ?>
                <div class="card" style="max-width: 1200px; width: 100%; border-left: 4px solid #dc3232;">
                    <h2 style="color: #dc3232;">
                        <span class="dashicons dashicons-warning" style="margin-right: 8px;"></span>
                        <?php esc_html_e('Landing Pages Need Repair', 'ez-translate'); ?>
                    </h2>
                    <p><?php esc_html_e('Some languages have missing or invalid landing page references. This can happen when pages are deleted or when editing language settings incorrectly.', 'ez-translate'); ?></p>

                    <div style="background: #fff2cd; border: 1px solid #dba617; border-radius: 4px; padding: 15px; margin: 15px 0;">
                        <h4 style="margin-top: 0; color: #8a6914;">
                            <span class="dashicons dashicons-info" style="margin-right: 5px;"></span>
                            <?php esc_html_e('Languages needing repair:', 'ez-translate'); ?>
                        </h4>
                        <ul style="margin: 10px 0;">
                            <?php foreach ($languages_needing_repair as $language): ?>
                                <li>
                                    <strong><?php echo esc_html($language['name']); ?></strong>
                                    (<?php echo esc_html($language['code']); ?>) -
                                    <span style="color: #666;">
                                        <?php if ($language['current_landing_id'] > 0): ?>
                                            <?php
                                            /* translators: %d: page ID */
                                            printf(esc_html__('Invalid page ID: %d', 'ez-translate'), esc_html($language['current_landing_id']));
                                            ?>
                                        <?php else: ?>
                                            <?php esc_html_e('No landing page assigned', 'ez-translate'); ?>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div style="background: #e7f3ff; border: 1px solid #0073aa; border-radius: 4px; padding: 15px; margin: 15px 0;">
                        <h4 style="margin-top: 0; color: #0073aa;">
                            <span class="dashicons dashicons-admin-tools" style="margin-right: 5px;"></span>
                            <?php esc_html_e('Automatic Repair', 'ez-translate'); ?>
                        </h4>
                        <p><?php esc_html_e('The repair process will attempt to:', 'ez-translate'); ?></p>
                        <ul style="margin: 10px 0 15px 20px;">
                            <li><?php esc_html_e('Search for existing landing pages in the database using bidirectional metadata', 'ez-translate'); ?></li>
                            <li><?php esc_html_e('Look for pages marked as landing pages for each language', 'ez-translate'); ?></li>
                            <li><?php esc_html_e('Re-associate found pages with their corresponding languages', 'ez-translate'); ?></li>
                            <li><?php esc_html_e('Update the language configuration automatically', 'ez-translate'); ?></li>
                        </ul>

                        <form method="post" action="" style="margin-top: 15px;"
                            onsubmit="return confirm('<?php esc_attr_e('This operation will attempt to find and re-associate landing pages for languages with missing references. This is generally safe, but make sure you have a backup. Continue?', 'ez-translate'); ?>');">
                            <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce_repair'); ?>
                            <input type="hidden" name="ez_translate_action" value="repair_landing_pages">
                            <button type="submit" class="button button-primary" style="background: #0073aa;">
                                <span class="dashicons dashicons-admin-tools" style="margin-right: 5px; vertical-align: middle;"></span>
                                <?php
                                /* translators: %d: number of languages to repair */
                                printf(esc_html__('Repair %d Language(s)', 'ez-translate'), count($languages_needing_repair));
                                ?>
                            </button>
                        </form>
                    </div>

                    <details style="margin-top: 15px;">
                        <summary style="cursor: pointer; font-weight: 600; color: #0073aa;">
                            <?php esc_html_e('Advanced: Manual Repair Options', 'ez-translate'); ?>
                        </summary>
                        <div style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                            <p style="margin-top: 0;"><?php esc_html_e('If automatic repair doesn\'t work, you can:', 'ez-translate'); ?></p>
                            <ol style="margin: 10px 0 10px 20px;">
                                <li><?php esc_html_e('Delete the problematic language and recreate it (this will create a new landing page)', 'ez-translate'); ?></li>
                                <li><?php esc_html_e('Manually create a new page and assign it as the landing page for the language', 'ez-translate'); ?></li>
                                <li><?php esc_html_e('Check the Pages section for orphaned landing pages that might belong to these languages', 'ez-translate'); ?></li>
                            </ol>
                        </div>
                    </details>
                </div>
            <?php endif; ?>

            <!-- Default Language Configuration -->
            <div class="card">
                <h2><?php esc_html_e('Default Language (x-default)', 'ez-translate'); ?></h2>
                <p><?php esc_html_e('Configure the default language for hreflang x-default tags. This language will be shown to users when their preferred language is not available.', 'ez-translate'); ?></p>

                <?php
                // Handle form submission
                if (
                    isset($_POST['save_default_language']) &&
                    isset($_POST['ez_translate_default_language_nonce']) &&
                    wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ez_translate_default_language_nonce'])), 'ez_translate_save_default_language')
                ) {

                    $default_language = isset($_POST['ez_translate_default_language']) ?
                        sanitize_text_field(wp_unslash($_POST['ez_translate_default_language'])) : '';
                    update_option('ez_translate_default_language', $default_language);
                    echo '<div class="notice notice-success"><p>' . esc_html__('Default language saved successfully!', 'ez-translate') . '</p></div>';
                }
                ?>

                <form method="post" action="">
                    <?php wp_nonce_field('ez_translate_save_default_language', 'ez_translate_default_language_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Default Language', 'ez-translate'); ?></th>
                            <td>
                                <select name="ez_translate_default_language" id="ez_translate_default_language">
                                    <option value=""><?php esc_html_e('Auto-detect (English preferred)', 'ez-translate'); ?></option>
                                    <?php
                                    $current_default = get_option('ez_translate_default_language', '');
                                    $available_languages = array(
                                        'en' => 'English',
                                        'es' => 'Español',
                                        'pt' => 'Português',
                                        'fr' => 'Français',
                                        'de' => 'Deutsch',
                                        'it' => 'Italiano',
                                        'ja' => '日本語',
                                        'ko' => '한국어',
                                        'zh' => '中文',
                                        'ru' => 'Русский',
                                        'ar' => 'العربية'
                                    );

                                    foreach ($available_languages as $code => $name) {
                                        $selected = ($current_default === $code) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($code) . '" ' . esc_attr($selected) . '>' . esc_html($name) . ' (' . esc_html($code) . ')</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e('This language will be used for hreflang="x-default" tags. Choose the language that is most universally understood by your audience.', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Current Setting', 'ez-translate'); ?></th>
                            <td>
                                <?php
                                $current_default = get_option('ez_translate_default_language', '');
                                if (empty($current_default)) {
                                    echo '<code>' . esc_html__('Auto-detect (English preferred)', 'ez-translate') . '</code>';
                                } else {
                                    $language_name = isset($available_languages[$current_default]) ? $available_languages[$current_default] : $current_default;
                                    echo '<code>' . esc_html($language_name) . ' (' . esc_html($current_default) . ')</code>';
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="save_default_language" class="button-primary" value="<?php esc_attr_e('Save Default Language', 'ez-translate'); ?>">
                    </p>
                </form>
            </div>

            <!-- Default Language Metadata Configuration -->
            <?php
            // Get WordPress default language
            $wp_locale = get_locale();
            $wp_language_code = strstr($wp_locale, '_', true) ?: $wp_locale; // es_MX -> es
            $wp_language_names = array(
                'en' => 'English',
                'es' => 'Español',
                'pt' => 'Português',
                'fr' => 'Français',
                'de' => 'Deutsch',
                'it' => 'Italiano',
                'ja' => '日本語',
                'ko' => '한국어',
                'zh' => '中文',
                'ru' => 'Русский',
                'ar' => 'العربية'
            );
            $wp_language_name = isset($wp_language_names[$wp_language_code]) ? $wp_language_names[$wp_language_code] : $wp_language_code;
            ?>
            <div class="card">
                <h2><?php
                    /* translators: %s: language name and code */
                    printf(esc_html__('Site Default Language (%s) Metadata', 'ez-translate'), esc_html($wp_language_name . ' - ' . $wp_language_code)); ?></h2>
                <p><?php esc_html_e('Configure SEO metadata for your site\'s default language and select which page represents your main landing page.', 'ez-translate'); ?></p>

                <?php
                // Handle form submission for default language metadata
                if (
                    isset($_POST['save_default_metadata']) &&
                    isset($_POST['ez_translate_default_metadata_nonce']) &&
                    wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ez_translate_default_metadata_nonce'])), 'ez_translate_save_default_metadata')
                ) {

                    $default_metadata = array(
                        'site_name' => isset($_POST['default_site_name']) ?
                            sanitize_text_field(wp_unslash($_POST['default_site_name'])) : '',
                        'site_title' => isset($_POST['default_site_title']) ?
                            sanitize_text_field(wp_unslash($_POST['default_site_title'])) : '',
                        'site_description' => isset($_POST['default_site_description']) ?
                            sanitize_textarea_field(wp_unslash($_POST['default_site_description'])) : ''
                    );
                    update_option('ez_translate_default_language_metadata', $default_metadata);

                    // Handle main landing page selection
                    $selected_page_id = isset($_POST['main_landing_page_id']) ? intval($_POST['main_landing_page_id']) : 0;
                    $current_main_landing = get_option('ez_translate_main_landing_page_id', 0);

                    if ($selected_page_id !== $current_main_landing) {
                        // Remove landing page status from previous main landing page
                        if ($current_main_landing > 0) {
                            \EZTranslate\PostMetaManager::remove_landing_page_status($current_main_landing);
                        }

                        if ($selected_page_id > 0) {
                            // Directly update post meta for the selected main landing page
                            update_post_meta($selected_page_id, '_ez_translate_seo_title', $default_metadata['site_title']);
                            update_post_meta($selected_page_id, '_ez_translate_seo_description', $default_metadata['site_description']);
                            // Add other relevant meta keys from $default_metadata if needed
                            // Example: if $default_metadata might contain 'site_keywords'
                            // if (isset($default_metadata['site_keywords'])) {
                            //    update_post_meta($selected_page_id, '_ez_translate_seo_keywords', $default_metadata['site_keywords']);
                            // }
                            Logger::info('Updated metadata for main landing page.', array(
                                'page_id' => $selected_page_id,
                                'metadata_updated' => array(
                                    '_ez_translate_seo_title' => $default_metadata['site_title'],
                                    '_ez_translate_seo_description' => $default_metadata['site_description']
                                )
                            ));
                            update_option('ez_translate_main_landing_page_id', $selected_page_id);
                            echo '<div class="notice notice-success"><p>' . esc_html__('Main landing page updated and metadata applied successfully!', 'ez-translate') . '</p></div>';
                        } else {
                            // No page selected, just remove the option
                            delete_option('ez_translate_main_landing_page_id');
                            echo '<div class="notice notice-success"><p>' . esc_html__('Main landing page removed successfully!', 'ez-translate') . '</p></div>';
                        }
                    } else {
                        echo '<div class="notice notice-success"><p>' . esc_html__('Default language metadata saved successfully!', 'ez-translate') . '</p></div>';
                    }
                }

                // Get current default language metadata
                $default_metadata = get_option('ez_translate_default_language_metadata', array());
                $main_landing_page_id = get_option('ez_translate_main_landing_page_id', 0);
                ?>

                <form method="post" action="">
                    <?php wp_nonce_field('ez_translate_save_default_metadata', 'ez_translate_default_metadata_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="default_site_name"><?php esc_html_e('Site Name', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="default_site_name" name="default_site_name" class="regular-text"
                                    value="<?php echo esc_attr(isset($default_metadata['site_name']) ? $default_metadata['site_name'] : ''); ?>"
                                    placeholder="<?php esc_attr_e('e.g., WordPress Specialist, Especialista en WordPress', 'ez-translate'); ?>">
                                <p class="description"><?php esc_html_e('Short site name for your default language (used in page titles)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="default_site_title"><?php esc_html_e('Site Title', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="default_site_title" name="default_site_title" class="regular-text"
                                    value="<?php echo esc_attr(isset($default_metadata['site_title']) ? $default_metadata['site_title'] : ''); ?>"
                                    placeholder="<?php esc_attr_e('e.g., My Website - Professional Services', 'ez-translate'); ?>">
                                <p class="description"><?php esc_html_e('Full site title for your default language (used in homepage and SEO metadata)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="default_site_description"><?php esc_html_e('Site Description', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <textarea id="default_site_description" name="default_site_description" class="large-text" rows="3"
                                    placeholder="<?php esc_attr_e('Brief description of your website in your default language...', 'ez-translate'); ?>"><?php echo esc_textarea(isset($default_metadata['site_description']) ? $default_metadata['site_description'] : ''); ?></textarea>
                                <p class="description"><?php esc_html_e('Site description for your default language (used in homepage and SEO metadata)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="main_landing_page_id"><?php esc_html_e('Main Landing Page', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <?php
                                // Get all published pages
                                $pages = get_pages(array(
                                    'post_status' => 'publish',
                                    'sort_column' => 'post_title'
                                ));

                                // Get current front page
                                $front_page_id = get_option('page_on_front', 0);
                                ?>
                                <select id="main_landing_page_id" name="main_landing_page_id" class="regular-text">
                                    <option value="0"><?php esc_html_e('Select a page...', 'ez-translate'); ?></option>
                                    <?php foreach ($pages as $page): ?>
                                        <option value="<?php echo esc_attr($page->ID); ?>"
                                            <?php selected($main_landing_page_id, $page->ID); ?>>
                                            <?php echo esc_html($page->post_title); ?>
                                            <?php if ($page->ID == $front_page_id): ?>
                                                (<?php esc_html_e('Current Homepage', 'ez-translate'); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Select which page represents your main landing page for the default language. The configured metadata above will be transferred to this page.', 'ez-translate'); ?>
                                    <?php if ($front_page_id > 0): ?>
                                        <br><strong><?php esc_html_e('Recommended:', 'ez-translate'); ?></strong> <?php esc_html_e('Select your current homepage to integrate it with the multilingual system.', 'ez-translate'); ?>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Current WordPress Settings', 'ez-translate'); ?></th>
                            <td>
                                <p><strong><?php esc_html_e('Site Title:', 'ez-translate'); ?></strong> <code><?php echo esc_html(get_bloginfo('name')); ?></code></p>
                                <p><strong><?php esc_html_e('Tagline:', 'ez-translate'); ?></strong> <code><?php echo esc_html(get_bloginfo('description')); ?></code></p>
                                <p><strong><?php esc_html_e('Language:', 'ez-translate'); ?></strong> <code><?php echo esc_html($wp_locale); ?></code></p>
                                <p class="description"><?php esc_html_e('These are your current WordPress settings. The metadata above will override these for SEO purposes.', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="save_default_metadata" class="button-primary" value="<?php esc_attr_e('Save Default Language Metadata', 'ez-translate'); ?>">
                    </p>
                </form>
            </div>

            <!-- Statistics -->
            <div class="card">
                <h2><?php esc_html_e('Statistics', 'ez-translate'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Total Languages', 'ez-translate'); ?></th>
                        <td><?php echo esc_html(count($languages)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enabled Languages', 'ez-translate'); ?></th>
                        <td><?php echo esc_html(count(\EZTranslate\LanguageManager::get_enabled_languages())); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Plugin Version', 'ez-translate'); ?></th>
                        <td><?php echo esc_html(EZ_TRANSLATE_VERSION); ?></td>
                    </tr>
                </table>
            </div>

            <!-- SEO Synchronization Section -->
            <div class="card">
                <h2><?php esc_html_e('SEO Synchronization', 'ez-translate'); ?></h2>
                <p><?php esc_html_e('Synchronize language metadata with landing page SEO data to ensure consistency.', 'ez-translate'); ?></p>

                <div style="background: #e7f3ff; border: 1px solid #0073aa; border-radius: 4px; padding: 15px; margin: 15px 0;">
                    <h4 style="margin-top: 0; color: #0073aa;">
                        <span class="dashicons dashicons-update" style="margin-right: 5px;"></span>
                        <?php esc_html_e('Automatic Synchronization', 'ez-translate'); ?>
                    </h4>
                    <p><?php esc_html_e('When you edit the SEO data of a landing page, it automatically synchronizes with the language metadata. However, you can manually trigger a full synchronization if needed.', 'ez-translate'); ?></p>

                    <p><strong><?php esc_html_e('This synchronization will:', 'ez-translate'); ?></strong></p>
                    <ul style="margin: 10px 0 15px 20px;">
                        <li><?php esc_html_e('Read current SEO title and description from each landing page', 'ez-translate'); ?></li>
                        <li><?php esc_html_e('Compare with stored language metadata', 'ez-translate'); ?></li>
                        <li><?php esc_html_e('Update language site_title and site_description if different', 'ez-translate'); ?></li>
                        <li><?php esc_html_e('Ensure consistency between landing page SEO and language settings', 'ez-translate'); ?></li>
                    </ul>

                    <form method="post" action="" style="margin-top: 15px;"
                        onsubmit="return confirm('<?php esc_attr_e('This will synchronize all language metadata with their corresponding landing page SEO data. This operation is safe but will update language settings. Continue?', 'ez-translate'); ?>');">
                        <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce_sync_seo'); ?>
                        <input type="hidden" name="ez_translate_action" value="sync_all_languages_seo">
                        <button type="submit" class="button button-primary" style="background: #0073aa;">
                            <span class="dashicons dashicons-update" style="margin-right: 5px; vertical-align: middle;"></span>
                            <?php esc_html_e('Synchronize All Languages', 'ez-translate'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- AI Integration Section -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e('AI Integration', 'ez-translate'); ?></h2>
                </div>
                <div class="inside">
                    <p class="description">
                        <?php esc_html_e('Configure AI services for enhanced translation capabilities.', 'ez-translate'); ?>
                    </p>

                    <?php
                    $api_settings = \EZTranslate\LanguageManager::get_api_settings();
                    $has_api_key = !empty($api_settings['api_key']);
                    $api_enabled = $api_settings['enabled'];
                    ?>

                    <form method="post" action="" id="ez-translate-api-form">
                        <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce_api'); ?>
                        <input type="hidden" name="ez_translate_action" value="update_api_settings">

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="api_key"><?php esc_html_e('Gemini API Key', 'ez-translate'); ?></label>
                                </th>
                                <td>
                                    <div style="position: relative;">
                                        <input type="password"
                                            id="api_key"
                                            name="api_key"
                                            class="regular-text"
                                            value="<?php echo esc_attr($api_settings['api_key']); ?>"
                                            placeholder="<?php esc_attr_e('Enter your Gemini AI API key...', 'ez-translate'); ?>"
                                            autocomplete="off">
                                        <button type="button"
                                            id="toggle_api_key"
                                            class="button button-secondary"
                                            style="margin-left: 5px;">
                                            <?php esc_html_e('Show', 'ez-translate'); ?>
                                        </button>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e('Enter your Google Gemini API key for AI-powered translation features.', 'ez-translate'); ?>
                                        <a href="https://makersuite.google.com/app/apikey" target="_blank">
                                            <?php esc_html_e('Get API Key', 'ez-translate'); ?>
                                        </a>
                                    </p>

                                    <!-- API Key Status -->
                                    <div id="api_key_status" style="margin-top: 10px;">
                                        <?php if ($has_api_key): ?>
                                            <span class="ez-translate-status-enabled">
                                                ✅ <?php esc_html_e('API Key Configured', 'ez-translate'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="ez-translate-status-disabled">
                                                ❌ <?php esc_html_e('No API Key Configured', 'ez-translate'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('AI Features', 'ez-translate'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            id="api_enabled"
                                            name="api_enabled"
                                            value="1"
                                            <?php checked($api_enabled); ?>
                                            <?php disabled(!$has_api_key); ?>>
                                        <?php esc_html_e('Enable AI-powered features', 'ez-translate'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Enable AI features for automatic translation suggestions and content optimization.', 'ez-translate'); ?>
                                        <?php if (!$has_api_key): ?>
                                            <br><em style="color: #d63638;"><?php esc_html_e('Requires API key to be configured first.', 'ez-translate'); ?></em>
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                            <?php if (!empty($api_settings['last_updated'])): ?>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Last Updated', 'ez-translate'); ?></th>
                                    <td>
                                        <code><?php echo esc_html(gmdate('M j, Y \a\t g:i A', strtotime($api_settings['last_updated']))); ?></code>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>

                        <p class="submit">
                            <input type="submit"
                                name="save_api_settings"
                                class="button-primary"
                                value="<?php esc_attr_e('Save AI Settings', 'ez-translate'); ?>">
                        </p>
                    </form>
                </div>
            </div>

            <!-- Language Data Backup & Import Section -->
            <div class="card" style="max-width: 1200px; width: 100%;">
                <h2>
                    <span class="dashicons dashicons-database-export" style="margin-right: 8px;"></span>
                    <?php esc_html_e('Language Data Backup & Import', 'ez-translate'); ?>
                </h2>
                <p><?php esc_html_e('Backup and restore your language configurations including SEO metadata. This is especially valuable for preserving SEO keywords and language-specific settings.', 'ez-translate'); ?></p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <!-- Export Section -->
                    <div style="background: #e7f3ff; border: 1px solid #0073aa; border-radius: 4px; padding: 15px;">
                        <h3 style="margin-top: 0; color: #0073aa;">
                            <span class="dashicons dashicons-download" style="margin-right: 5px;"></span>
                            <?php esc_html_e('Export Backup', 'ez-translate'); ?>
                        </h3>
                        <p><?php esc_html_e('Create a backup file containing all your language configurations and SEO metadata.', 'ez-translate'); ?></p>

                        <div style="background: #fff; border-radius: 4px; padding: 10px; margin: 10px 0;">
                            <strong><?php esc_html_e('Backup includes:', 'ez-translate'); ?></strong>
                            <ul style="margin: 5px 0 0 20px;">
                                <li><?php esc_html_e('Language configurations (codes, names, flags, etc.)', 'ez-translate'); ?></li>
                                <li><?php esc_html_e('SEO metadata (site titles, descriptions)', 'ez-translate'); ?></li>
                                <li><?php esc_html_e('Default language settings', 'ez-translate'); ?></li>
                                <li><?php esc_html_e('Language-specific site metadata', 'ez-translate'); ?></li>
                            </ul>
                        </div>

                        <form method="post" action="">
                            <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce_backup'); ?>
                            <input type="hidden" name="ez_translate_action" value="export_backup">
                            <button type="submit" class="button button-primary" style="background: #0073aa;">
                                <span class="dashicons dashicons-download" style="margin-right: 5px; vertical-align: middle;"></span>
                                <?php esc_html_e('Download Backup', 'ez-translate'); ?>
                            </button>
                        </form>
                    </div>

                    <!-- Import Section -->
                    <div style="background: #fff2cd; border: 1px solid #dba617; border-radius: 4px; padding: 15px;">
                        <h3 style="margin-top: 0; color: #8a6914;">
                            <span class="dashicons dashicons-upload" style="margin-right: 5px;"></span>
                            <?php esc_html_e('Import Backup', 'ez-translate'); ?>
                        </h3>
                        <p><?php esc_html_e('Restore language configurations from a backup file. You can preview changes before applying them.', 'ez-translate'); ?></p>

                        <form method="post" action="" enctype="multipart/form-data" id="ez-translate-import-form">
                            <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce_import'); ?>
                            <input type="hidden" name="ez_translate_action" value="import_backup">
                            <input type="hidden" name="preview_import" value="1">

                            <div style="margin: 10px 0;">
                                <label for="backup_file" style="font-weight: 600;"><?php esc_html_e('Select Backup File:', 'ez-translate'); ?></label><br>
                                <input type="file" id="backup_file" name="backup_file" accept=".json" required style="margin-top: 5px;">
                                <p class="description"><?php esc_html_e('Select a JSON backup file created by EZ Translate.', 'ez-translate'); ?></p>
                            </div>

                            <button type="submit" class="button button-secondary">
                                <span class="dashicons dashicons-visibility" style="margin-right: 5px; vertical-align: middle;"></span>
                                <?php esc_html_e('Preview Import', 'ez-translate'); ?>
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($this->backup_comparison): ?>
                    <!-- Import Preview Section -->
                    <div style="background: #f0f6fc; border: 1px solid #0073aa; border-radius: 4px; padding: 20px; margin-top: 20px;">
                        <h3 style="margin-top: 0; color: #0073aa;">
                            <span class="dashicons dashicons-visibility" style="margin-right: 5px;"></span>
                            <?php esc_html_e('Import Preview', 'ez-translate'); ?>
                        </h3>

                        <?php $comparison = $this->backup_comparison; ?>

                        <!-- Summary -->
                        <div style="background: #fff; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                            <h4 style="margin-top: 0;"><?php esc_html_e('Import Summary', 'ez-translate'); ?></h4>
                            <ul>
                                <li><strong><?php echo esc_html($comparison['summary']['total_backup_languages']); ?></strong> <?php esc_html_e('languages in backup', 'ez-translate'); ?></li>
                                <li><strong><?php echo esc_html($comparison['summary']['new_languages_count']); ?></strong> <?php esc_html_e('new languages to create', 'ez-translate'); ?></li>
                                <li><strong><?php echo esc_html($comparison['summary']['updated_languages_count']); ?></strong> <?php esc_html_e('existing languages to update', 'ez-translate'); ?></li>
                                <li><strong><?php echo esc_html($comparison['summary']['unchanged_languages_count']); ?></strong> <?php esc_html_e('languages unchanged', 'ez-translate'); ?></li>
                            </ul>
                        </div>

                        <form method="post" action="" id="ez-translate-confirm-import-form">
                            <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce_import'); ?>
                            <input type="hidden" name="ez_translate_action" value="import_backup">
                            <input type="hidden" name="confirm_import" value="1">

                            <!-- New Languages -->
                            <?php if (!empty($comparison['languages']['new'])): ?>
                                <div style="background: #e7f3ff; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                                    <h4><?php esc_html_e('New Languages', 'ez-translate'); ?></h4>
                                    <ul class="language-list">
                                        <?php foreach ($comparison['languages']['new'] as $language) : ?>
                                            <li>
                                                <label>
                                                    <input type="checkbox" name="import_languages[]" value="<?php echo esc_attr($language['code']); ?>" checked>
                                                    <?php echo esc_html($language['name']); ?> (<?php echo esc_html($language['code']); ?>)
                                                </label>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Updated Languages -->
                            <?php if (!empty($comparison['languages']['existing'])): ?>
                                <div style="background: #fff2cd; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                                    <h4><?php esc_html_e('Languages to Update', 'ez-translate'); ?></h4>
                                    <ul class="language-list">
                                        <?php foreach ($comparison['languages']['existing'] as $language) : ?>
                                            <li>
                                                <label>
                                                    <input type="checkbox" name="import_languages[]" value="<?php echo esc_attr($language['code']); ?>" checked>
                                                    <?php echo esc_html($language['name']); ?> (<?php echo esc_attr($language['code']); ?>)
                                                </label>
                                                <div class="changes-preview">
                                                    <?php foreach ($language['differences'] as $field => $values) : ?>
                                                        <div class="field-change">
                                                            <strong><?php echo esc_html($field); ?>:</strong>
                                                            <span class="current"><?php echo esc_html($values['current']); ?></span>
                                                            →
                                                            <span class="new"><?php echo esc_html($values['backup']); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Default Metadata Changes -->
                            <?php if (!empty($comparison['default_metadata']['changes'])): ?>
                                <div style="background: #f0f6fc; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                                    <h4><?php esc_html_e('Default Language Metadata Changes', 'ez-translate'); ?></h4>
                                    <label style="display: block; margin-bottom: 10px;">
                                        <input type="checkbox" name="import_default_metadata" value="1" checked>
                                        <?php esc_html_e('Update default language metadata', 'ez-translate'); ?>
                                    </label>

                                    <ul style="margin: 5px 0 0 20px;">
                                        <?php foreach ($comparison['default_metadata']['changes'] as $field => $change): ?>
                                            <li>
                                                <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $field))); ?>:</strong>
                                                <br><span style="color: #d63638;"><?php esc_html_e('Current:', 'ez-translate'); ?> "<?php echo esc_html($change['current']); ?>"</span>
                                                <br><span style="color: #00a32a;"><?php esc_html_e('Backup:', 'ez-translate'); ?> "<?php echo esc_html($change['backup']); ?>"</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <div style="background: #fff; border-radius: 4px; padding: 15px; text-align: center;">
                                <p style="margin-bottom: 15px;"><strong><?php esc_html_e('Ready to import?', 'ez-translate'); ?></strong></p>
                                <button type="submit" class="button button-primary" style="background: #00a32a; margin-right: 10px;">
                                    <span class="dashicons dashicons-upload" style="margin-right: 5px; vertical-align: middle;"></span>
                                    <?php esc_html_e('Confirm Import', 'ez-translate'); ?>
                                </button>
                                <button type="button" class="button" onclick="location.reload();">
                                    <?php esc_html_e('Cancel', 'ez-translate'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Important Notes -->
                <div style="background: #f9f9f9; border-left: 4px solid #0073aa; padding: 15px; margin-top: 20px;">
                    <h4 style="margin-top: 0; color: #0073aa;"><?php esc_html_e('Important Notes', 'ez-translate'); ?></h4>
                    <ul style="margin: 0;">
                        <li><?php esc_html_e('Backup files contain language configurations and SEO metadata, but not the actual landing page content.', 'ez-translate'); ?></li>
                        <li><?php esc_html_e('New languages will automatically create landing pages with default content.', 'ez-translate'); ?></li>
                        <li><?php esc_html_e('Existing languages will be updated with backup data, preserving their landing page IDs.', 'ez-translate'); ?></li>
                        <li><?php esc_html_e('Always create a backup before importing to ensure you can restore if needed.', 'ez-translate'); ?></li>
                    </ul>
                </div>
            </div>


        </div>

        <!-- Edit Language Modal -->
        <div id="ez-translate-edit-modal" style="display: none;">
            <div class="ez-translate-modal-content">
                <h2><?php esc_html_e('Edit Language', 'ez-translate'); ?></h2>
                <form method="post" action="" id="ez-translate-edit-form">
                    <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce_edit'); ?>
                    <input type="hidden" name="ez_translate_action" value="edit_language">
                    <input type="hidden" name="original_code" id="edit_original_code">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="edit_language_code"><?php esc_html_e('Language Code', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_code" name="code" class="regular-text" readonly
                                    style="background-color: #f1f1f1; color: #666;">
                                <p class="description"><?php esc_html_e('Language code cannot be changed for data integrity. Delete and recreate the language to change this.', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_name"><?php esc_html_e('Language Name', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_name" name="name" class="regular-text" readonly
                                    style="background-color: #f1f1f1; color: #666;">
                                <p class="description"><?php esc_html_e('Language name cannot be changed for data integrity. Delete and recreate the language to change this.', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_slug"><?php esc_html_e('Language Slug', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_slug" name="slug" class="regular-text" readonly
                                    style="background-color: #f1f1f1; color: #666;">
                                <p class="description"><?php esc_html_e('Language slug cannot be changed for data integrity. Delete and recreate the language to change this.', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_native_name"><?php esc_html_e('Native Name', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_native_name" name="native_name" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_flag"><?php esc_html_e('Flag Emoji', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_flag" name="flag" class="small-text" maxlength="4">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Text Direction', 'ez-translate'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="edit_language_rtl" name="rtl" value="1">
                                    <?php esc_html_e('Right-to-left (RTL) language', 'ez-translate'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Status', 'ez-translate'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="edit_language_enabled" name="enabled" value="1">
                                    <?php esc_html_e('Enable this language', 'ez-translate'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_site_name"><?php esc_html_e('Site Name', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_site_name" name="site_name" class="regular-text"
                                    placeholder="<?php esc_attr_e('e.g., WordPress Specialist, Especialista en WordPress', 'ez-translate'); ?>">
                                <p class="description"><?php esc_html_e('Short site name for this language (used in page titles). Example: "WordPress Specialist" for English.', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_site_title"><?php esc_html_e('Site Title', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_site_title" name="site_title" class="regular-text"
                                    placeholder="<?php esc_attr_e('e.g., My Website - English Version', 'ez-translate'); ?>">
                                <p class="description"><?php esc_html_e('Full site title for this language (used in landing pages and SEO metadata)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_site_description"><?php esc_html_e('Site Description', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <textarea id="edit_language_site_description" name="site_description" class="large-text" rows="3"
                                    placeholder="<?php esc_attr_e('Brief description of your website in this language...', 'ez-translate'); ?>"></textarea>
                                <p class="description"><?php esc_html_e('Site description for this language (used in landing pages and SEO metadata)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <!-- Note: Landing page creation removed from edit mode for data integrity -->
                    <!-- To create or modify landing pages, delete and recreate the language -->

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Update Language', 'ez-translate'); ?></button>
                        <button type="button" class="button ez-translate-cancel-edit"><?php esc_html_e('Cancel', 'ez-translate'); ?></button>
                    </p>
                </form>
            </div>
        </div>

        <!-- Edit SEO Modal -->
        <div id="ez-translate-seo-modal" style="display: none;">
            <div class="ez-translate-modal-content">
                <h2><?php esc_html_e('Edit Landing Page SEO', 'ez-translate'); ?></h2>
                <form method="post" action="" id="ez-translate-seo-form">
                    <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce_seo'); ?>
                    <input type="hidden" name="ez_translate_action" value="update_landing_page_seo">
                    <input type="hidden" name="post_id" id="seo_post_id">
                    <input type="hidden" name="language_code" id="seo_language_code">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="seo_title"><?php esc_html_e('SEO Title', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="seo_title" name="seo_title" class="large-text" maxlength="60">
                                <p class="description"><?php esc_html_e('Recommended: 50-60 characters. This will be used in the page title tag and social media.', 'ez-translate'); ?></p>
                                <div id="seo_title_counter" style="font-size: 11px; color: #666; margin-top: 4px;"></div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="seo_description"><?php esc_html_e('SEO Description', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <textarea id="seo_description" name="seo_description" class="large-text" rows="3" maxlength="160"></textarea>
                                <p class="description"><?php esc_html_e('Recommended: 150-160 characters. This will be used in meta description and social media previews.', 'ez-translate'); ?></p>
                                <div id="seo_description_counter" style="font-size: 11px; color: #666; margin-top: 4px;"></div>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Update SEO', 'ez-translate'); ?></button>
                        <button type="button" class="button ez-translate-cancel-seo"><?php esc_html_e('Cancel', 'ez-translate'); ?></button>
                    </p>
                </form>
            </div>
        </div>

        <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            }

            .ez-translate-status-enabled {
                color: #00a32a;
                font-weight: 600;
            }

            .ez-translate-status-disabled {
                color: #d63638;
                font-weight: 600;
            }

            #ez-translate-edit-modal,
            #ez-translate-seo-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                z-index: 100000;
            }

            .ez-translate-modal-content {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #fff;
                padding: 20px;
                border-radius: 4px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
            }

            .ez-translate-modal-content h2 {
                margin-top: 0;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                // Language selector change handler for add form
                $('#language_code_select').on('change', function() {
                    var selectedOption = $(this).find('option:selected');
                    if (selectedOption.val()) {
                        var code = selectedOption.val();
                        var name = selectedOption.data('name');
                        var nativeName = selectedOption.data('native');
                        var flag = selectedOption.data('flag');

                        // Auto-populate fields
                        $('#language_code').val(code);
                        $('#language_name').val(name);
                        $('#language_native_name').val(nativeName);
                        $('#language_flag').val(flag);

                        // Generate slug from name
                        var slug = name.toLowerCase()
                            .replace(/[^a-z0-9\s\-_]/g, '')
                            .replace(/\s+/g, '-')
                            .replace(/\-+/g, '-')
                            .replace(/^-|-$/g, '');
                        $('#language_slug').val(slug);

                        // Set RTL for known RTL languages
                        var rtlLanguages = ['ar', 'he', 'fa', 'ur'];
                        $('#language_rtl').prop('checked', rtlLanguages.includes(code));
                    }
                });

                // Note: Language selector removed from edit form - fields are now read-only

                // Edit button click handler
                $('.ez-translate-edit-btn').on('click', function() {
                    var languageData = $(this).data('language');

                    // Note: No dropdown to reset in edit mode

                    // Populate the edit form with real synchronized data
                    $('#edit_original_code').val(languageData.code);
                    $('#edit_language_code').val(languageData.code);
                    $('#edit_language_name').val(languageData.name);
                    $('#edit_language_slug').val(languageData.slug);
                    $('#edit_language_native_name').val(languageData.native_name || '');
                    $('#edit_language_flag').val(languageData.flag || '');
                    $('#edit_language_rtl').prop('checked', languageData.rtl || false);
                    $('#edit_language_enabled').prop('checked', languageData.enabled !== false);
                    $('#edit_language_site_name').val(languageData.site_name || '');
                    $('#edit_language_site_title').val(languageData.site_title || '');
                    $('#edit_language_site_description').val(languageData.site_description || '');

                    // Note: Landing page creation removed from edit mode

                    // Show the modal
                    $('#ez-translate-edit-modal').show();
                });

                // Cancel edit button
                $('.ez-translate-cancel-edit').on('click', function() {
                    $('#ez-translate-edit-modal').hide();
                });

                // Close modal when clicking outside
                $('#ez-translate-edit-modal').on('click', function(e) {
                    if (e.target === this) {
                        $(this).hide();
                    }
                });

                // Auto-generate slug from name (manual input)
                $('#language_name').on('input', function() {
                    // Only auto-generate if no language was selected from dropdown
                    if (!$('#language_code_select').val()) {
                        var name = $(this).val();
                        var slug = name.toLowerCase()
                            .replace(/[^a-z0-9\s\-_]/g, '')
                            .replace(/\s+/g, '-')
                            .replace(/\-+/g, '-')
                            .replace(/^-|-$/g, '');
                        $('#language_slug').val(slug);
                    }
                });

                // Note: Auto-generation removed from edit mode - fields are read-only

                // Clear form when language selector is reset
                $('#language_code_select').on('change', function() {
                    if (!$(this).val()) {
                        $('#language_code, #language_name, #language_native_name, #language_flag').val('');
                        $('#language_slug').val('');
                        $('#language_rtl').prop('checked', false);
                    }
                });

                // Landing page creation toggle
                $('#create_landing_page').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('#landing_page_fields').show();
                        // Auto-populate landing page title from language name
                        var languageName = $('#language_name').val();
                        if (languageName && !$('#landing_page_title').val()) {
                            $('#landing_page_title').val('Welcome to Our Site - ' + languageName);
                        }
                    } else {
                        $('#landing_page_fields').hide();
                        // Clear landing page fields
                        $('#landing_page_title, #landing_page_description, #landing_page_slug').val('');
                        $('input[name="landing_page_status"][value="draft"]').prop('checked', true);
                    }
                });

                // Auto-populate landing page title when language name changes
                $('#language_name').on('input', function() {
                    if ($('#create_landing_page').is(':checked') && !$('#landing_page_title').val()) {
                        var languageName = $(this).val();
                        if (languageName) {
                            $('#landing_page_title').val('Welcome to Our Site - ' + languageName);
                        }
                    }
                });

                // Auto-generate landing page slug from title
                $('#landing_page_title').on('input', function() {
                    if (!$('#landing_page_slug').val()) {
                        var title = $(this).val();
                        var slug = title.toLowerCase()
                            .replace(/[^a-z0-9\s\-_]/g, '')
                            .replace(/\s+/g, '-')
                            .replace(/\-+/g, '-')
                            .replace(/^-|-$/g, '');
                        $('#landing_page_slug').val(slug);
                    }
                });

                // Note: Landing page creation JavaScript removed from edit mode for data integrity

                // Note: Edit language dropdown handlers removed - fields are now read-only

                // SEO Edit button click handler
                $('.ez-translate-edit-seo-btn').on('click', function() {
                    var postId = $(this).data('post-id');
                    var language = $(this).data('language');
                    var title = $(this).data('title');
                    var description = $(this).data('description');

                    // Populate the SEO form
                    $('#seo_post_id').val(postId);
                    $('#seo_language_code').val(language);
                    $('#seo_title').val(title);
                    $('#seo_description').val(description);

                    // Update character counters
                    updateSeoCounters();

                    // Show the modal
                    $('#ez-translate-seo-modal').show();
                });

                // Cancel SEO edit button
                $('.ez-translate-cancel-seo').on('click', function() {
                    $('#ez-translate-seo-modal').hide();
                });

                // Close SEO modal when clicking outside
                $('#ez-translate-seo-modal').on('click', function(e) {
                    if (e.target === this) {
                        $(this).hide();
                    }
                });

                // Character counters for SEO fields
                function updateSeoCounters() {
                    var titleLength = $('#seo_title').val().length;
                    var descLength = $('#seo_description').val().length;

                    var titleColor = titleLength > 60 ? '#d63638' : (titleLength > 50 ? '#dba617' : '#00a32a');
                    var descColor = descLength > 160 ? '#d63638' : (descLength > 150 ? '#dba617' : '#00a32a');

                    $('#seo_title_counter').html(titleLength + '/60 characters').css('color', titleColor);
                    $('#seo_description_counter').html(descLength + '/160 characters').css('color', descColor);
                }

                // Update counters on input
                $('#seo_title, #seo_description').on('input', updateSeoCounters);

                // API Key Show/Hide toggle
                $('#toggle_api_key').on('click', function() {
                    var apiKeyField = $('#api_key');
                    var button = $(this);

                    if (apiKeyField.attr('type') === 'password') {
                        apiKeyField.attr('type', 'text');
                        button.text('<?php esc_html_e('Hide', 'ez-translate'); ?>');
                    } else {
                        apiKeyField.attr('type', 'password');
                        button.text('<?php esc_html_e('Show', 'ez-translate'); ?>');
                    }
                });

                // API Key validation and status update
                $('#api_key').on('input', function() {
                    var apiKey = $(this).val().trim();
                    var statusDiv = $('#api_key_status');
                    var enabledCheckbox = $('#api_enabled');

                    if (apiKey.length === 0) {
                        statusDiv.html('<span class="ez-translate-status-disabled">❌ <?php esc_html_e('No API Key Configured', 'ez-translate'); ?></span>');
                        enabledCheckbox.prop('disabled', true).prop('checked', false);
                    } else if (apiKey.length < 20) {
                        statusDiv.html('<span class="ez-translate-status-disabled">⚠️ <?php esc_html_e('API Key too short', 'ez-translate'); ?></span>');
                        enabledCheckbox.prop('disabled', true).prop('checked', false);
                    } else {
                        statusDiv.html('<span class="ez-translate-status-enabled">✅ <?php esc_html_e('API Key Configured', 'ez-translate'); ?></span>');
                        enabledCheckbox.prop('disabled', false);
                    }
                });
            });
        </script>
    <?php
    }

    /**
     * Renderiza la tabla de idiomas para la interfaz de administración
     *
     * @param array $_languages Array con los datos de los idiomas
     * 
     * @since 1.0.0
     * @return void
     */
    public function render_languages_table($_languages)
    {
        // Ensure LanguageManager is available
        if (!class_exists('\EZTranslate\LanguageManager')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        }
?>
        <?php if (empty($_languages)) : ?>
            <p><?php esc_html_e('No languages configured yet. Add your first language above.', 'ez-translate'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped ez-translate-languages-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Code', 'ez-translate'); ?></th>
                        <th scope="col"><?php esc_html_e('Name', 'ez-translate'); ?></th>
                        <th scope="col"><?php esc_html_e('Native Name', 'ez-translate'); ?></th>
                        <th scope="col"><?php esc_html_e('URL', 'ez-translate'); ?></th>
                        <th scope="col"><?php esc_html_e('Landing Page', 'ez-translate'); ?></th>
                        <th scope="col"><?php esc_html_e('Status', 'ez-translate'); ?></th>
                        <th scope="col"><?php esc_html_e('Actions', 'ez-translate'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($_languages as $language) : ?>
                        <?php
                        // Get landing page info for this language
                        $landing_page = \EZTranslate\LanguageManager::get_landing_page_for_language($language['code']);

                        // Construct the language URL
                        $current_language_data = \EZTranslate\LanguageManager::get_language($language['code']);
                        $_slug = '';
                        if ($current_language_data && isset($current_language_data['slug'])) {
                            $_slug = $current_language_data['slug'];
                        } elseif (isset($language['slug'])) { // Fallback if _language itself has slug
                            $_slug = $language['slug'];
                        }

                        $language_url = '';
                        if (!empty($_slug)) {
                            $language_url = home_url('/' . $_slug . '/');
                        }

                        // Get language data with automatically synchronized SEO data
                        $language_with_current_seo = \EZTranslate\LanguageManager::get_language_with_current_seo($language['code']);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($language['code']); ?></strong></td>
                            <td>
                                <?php echo esc_html(isset($language['flag']) ? $language['flag'] : ''); ?>
                                <?php echo esc_html($language['name']); ?>
                            </td>
                            <td><?php echo esc_html(isset($language['native_name']) ? $language['native_name'] : '—'); ?></td>
                            <td>
                                <?php
                                $display_slug = !empty($_slug) ? $_slug : $language['code'];
                                if ($language_url) : ?>
                                    <a href="<?php echo esc_url($language_url); ?>" target="_blank">
                                        <code><?php echo esc_html($display_slug); ?></code>
                                    </a>
                                <?php else : ?>
                                    <code><?php echo esc_html($display_slug); ?></code>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($landing_page) : ?>
                                    <div style="margin-bottom: 5px;">
                                        <a href="<?php echo esc_url(get_edit_post_link($landing_page['post_id'])); ?>" target="_blank">
                                            <?php echo esc_html(get_the_title($landing_page['post_id'])); ?>
                                        </a>
                                        <br>
                                        <small style="color: #666;">
                                            <a href="<?php echo esc_url(get_edit_post_link($landing_page['post_id'])); ?>" target="_blank"><?php esc_html_e('Edit', 'ez-translate'); ?></a> |
                                            <a href="<?php echo esc_url(get_permalink($landing_page['post_id'])); ?>" target="_blank"><?php esc_html_e('View', 'ez-translate'); ?></a>
                                        </small>
                                    </div>
                                    <button type="button" class="button button-small ez-translate-edit-seo-btn" data-post-id="<?php echo esc_attr($landing_page['post_id']); ?>" data-language="<?php echo esc_attr($language['code']); ?>" data-title="<?php echo esc_attr($landing_page['seo_title']); ?>" data-description="<?php echo esc_attr($landing_page['seo_description']); ?>">
                                        <?php esc_html_e('Edit SEO', 'ez-translate'); ?>
                                    </button>
                                <?php else : ?>
                                    <span style="color: #999;"><?php esc_html_e('No landing page', 'ez-translate'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($language['enabled']) ? $language['enabled'] : true) : ?>
                                    <span class="ez-translate-status-enabled"><?php esc_html_e('Enabled', 'ez-translate'); ?></span>
                                <?php else : ?>
                                    <span class="ez-translate-status-disabled"><?php esc_html_e('Disabled', 'ez-translate'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small ez-translate-edit-btn" data-language='<?php echo esc_attr(json_encode($language_with_current_seo)); ?>'>
                                    <?php esc_html_e('Edit', 'ez-translate'); ?>
                                </button>
                                <form method="post" style="display: inline-block;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this language?', 'ez-translate'); ?>');">
                                    <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce_delete_' . $language['code']); ?>
                                    <input type="hidden" name="ez_translate_action" value="delete_language">
                                    <input type="hidden" name="language_code" value="<?php echo esc_attr($language['code']); ?>">
                                    <button type="submit" class="button button-small button-link-delete">
                                        <?php esc_html_e('Delete', 'ez-translate'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php
    }

    /**
     * Add Landing Page column to pages list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     * @since 1.0.0
     */
    public function add_landing_page_column($columns)
    {
        // Insert after the 'title' column
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['ez_translate_landing'] = __('Landing Page', 'ez-translate');
            }
        }
        return $new_columns;
    }

    /**
     * Show Landing Page column content
     *
     * @param string $column_name Column name
     * @param int    $post_id     Post ID
     * @since 1.0.0
     */
    public function show_landing_page_column_content($column_name, $post_id)
    {
        if ($column_name === 'ez_translate_landing') {
            // Get all languages to check if this page is a landing page
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
            $languages = \EZTranslate\LanguageManager::get_languages();

            // Check if this post ID matches any landing_page_id
            foreach ($languages as $language) {
                if (!empty($language['landing_page_id']) && $language['landing_page_id'] == $post_id) {
                    $language_code = strtoupper(esc_html($language['code']));
                    echo '<strong style="color: #0073aa;">LP-' . esc_html($language_code) . '</strong>';
                    return;
                }
            }
            // If not a landing page, show nothing (empty column)
        }
    }

    /**
     * Add Landing Pages table below main pages list
     *
     * @since 1.0.0
     */
    public function add_landing_pages_table()
    {
        global $typenow, $pagenow;

        // Only on the pages edit screen
        if ($pagenow !== 'edit.php' || $typenow !== 'page') {
            return;
        }

        // Get all landing pages
        $landing_pages = $this->get_all_landing_pages();

        if (empty($landing_pages)) {
            return;
        }

        // Render the table
        $this->render_landing_pages_table($landing_pages);
    }

    /**
     * Get all landing pages
     *
     * @return array Array of landing page data
     * @since 1.0.0
     */
    private function get_all_landing_pages()
    {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        $languages = \EZTranslate\LanguageManager::get_languages();

        $landing_pages = array();

        foreach ($languages as $language) {
            if (!empty($language['landing_page_id'])) {
                $post = get_post($language['landing_page_id']);

                if ($post && $post->post_type === 'page') {
                    $landing_pages[] = array(
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'language' => $language['code'],
                        'language_name' => $language['name'],
                        'seo_title' => get_post_meta($post->ID, '_ez_translate_seo_title', true),
                        'seo_description' => get_post_meta($post->ID, '_ez_translate_seo_description', true),
                        'status' => $post->post_status,
                        'edit_url' => get_edit_post_link($post->ID),
                        'view_url' => get_permalink($post->ID),
                        'last_modified' => $post->post_modified
                    );
                }
            }
        }

        // Sort by language code
        usort($landing_pages, function ($a, $b) {
            return strcmp($a['language'], $b['language']);
        });

        return $landing_pages;
    }

    /**
     * Render Landing Pages table
     *
     * @param array $landing_pages Array of landing page data
     * @since 1.0.0
     */
    private function render_landing_pages_table($landing_pages)
    {
    ?>
        <div class="ez-translate-landing-pages-section" style="margin: 20px 0; clear: both;">
            <div class="postbox" style="margin-top: 20px;">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e('Landing Pages', 'ez-translate'); ?></h2>
                </div>
                <div class="inside">
                    <p class="description" style="margin-bottom: 15px;">
                        <?php esc_html_e('All pages configured as Landing Pages for different languages.', 'ez-translate'); ?>
                    </p>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 35%;"><?php esc_html_e('Title', 'ez-translate'); ?></th>
                                <th scope="col" style="width: 15%;"><?php esc_html_e('Language', 'ez-translate'); ?></th>
                                <th scope="col" style="width: 15%;"><?php esc_html_e('Status', 'ez-translate'); ?></th>
                                <th scope="col" style="width: 20%;"><?php esc_html_e('Last Modified', 'ez-translate'); ?></th>
                                <th scope="col" style="width: 15%;"><?php esc_html_e('Actions', 'ez-translate'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($landing_pages as $page): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url($page['edit_url']); ?>">
                                                <?php echo esc_html($page['title']); ?>
                                            </a>
                                        </strong>
                                        <?php if (!empty($page['seo_title'])): ?>
                                            <br><small style="color: #666;">
                                                SEO: <?php echo esc_html($page['seo_title']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="ez-translate-language-badge" style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600;">
                                            <?php echo esc_html(strtoupper($page['language'])); ?>
                                        </span>
                                        <br><small style="color: #666;">
                                            <?php echo esc_html($page['language_name']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                        $status_colors = array(
                                            'publish' => '#00a32a',
                                            'draft' => '#d63638',
                                            'private' => '#dba617'
                                        );
                                        $status_color = $status_colors[$page['status']] ?? '#666';
                                        ?>
                                        <span style="color: <?php echo esc_attr($status_color); ?>; font-weight: 600;">
                                            <?php echo esc_html(ucfirst($page['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo esc_html(gmdate('M j, Y \a\t g:i A', strtotime($page['last_modified']))); ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($page['edit_url']); ?>" class="button button-small">
                                            <?php esc_html_e('Edit', 'ez-translate'); ?>
                                        </a>
                                        <?php if ($page['status'] === 'publish'): ?>
                                            <a href="<?php echo esc_url($page['view_url']); ?>" class="button button-small" target="_blank">
                                                <?php esc_html_e('View', 'ez-translate'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p style="margin-top: 15px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ez-translate')); ?>" class="button button-primary">
                            <?php esc_html_e('Manage Languages', 'ez-translate'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <style>
            .ez-translate-landing-pages-section {
                max-width: none !important;
            }

            .ez-translate-landing-pages-section .postbox {
                max-width: none !important;
            }
        </style>
    <?php
    }

    /**
     * Transfiere los metadatos de una página de destino específica a la página principal
     *
     * @param int   $_source_page_id      ID de la página de origen
     * @param int   $_destination_page_id ID de la página de destino
     * @param array $_metadata_keys       Array con las claves de metadatos a transferir
     * 
     * @since 1.0.0
     * @return bool|\WP_Error True en caso de éxito, WP_Error en caso de error
     */
    private function transfer_metadata_to_main_landing_page($_source_page_id, $_destination_page_id, $_metadata_keys = array())
    {
        $source_page = get_post($_source_page_id);
        $destination_page = get_post($_destination_page_id);

        if (!$_source_page_id || !$source_page || $source_page->post_type !== 'page') {
            Logger::error('Invalid source page ID for metadata transfer.', array('source_page_id' => $_source_page_id));
            return new \WP_Error('invalid_source_page', __('Invalid source page ID provided for metadata transfer.', 'ez-translate'));
        }

        if (!$destination_page || $destination_page->post_type !== 'page') {
            Logger::error('Invalid destination page ID for metadata transfer.', array('destination_page_id' => $_destination_page_id));
            return new \WP_Error('invalid_destination_page', __('Invalid destination page ID provided for metadata transfer.', 'ez-translate'));
        }

        if (empty($_metadata_keys)) {
            $_metadata_keys = array(
                '_ez_translate_seo_title',
                '_ez_translate_seo_description',
                '_ez_translate_seo_keywords',
                '_ez_translate_social_image',
                '_ez_translate_canonical_url',
            );
        }

        $transferred_count = 0;
        foreach ($_metadata_keys as $meta_key) {
            $value = get_post_meta($_source_page_id, $meta_key, true);
            if ($value !== '') { // Also transfer empty strings if that's the stored value, but perhaps skip if not found
                update_post_meta($_destination_page_id, $meta_key, $value);
                $transferred_count++;
            }
        }

        Logger::info(
            'Metadata transfer attempt completed.',
            array(
                'source_page_id' => $_source_page_id,
                'destination_page_id' => $_destination_page_id,
                'metadata_keys' => $_metadata_keys,
                'transferred_count' => $transferred_count
            )
        );

        return true;
    }

    /**
     * Get menu slug
     *
     * @return string
     * @since 1.0.0
     */
    public static function get_menu_slug()
    {
        return self::MENU_SLUG;
    }

    /**
     * Initialize sitemap admin
     *
     * @since 1.0.0
     */
    private function init_sitemap_admin()
    {
        // Load sitemap admin class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/admin/class-ez-translate-sitemap-admin.php';

        // Initialize sitemap admin
        new \EZTranslate\Admin\SitemapAdmin();

        Logger::debug('Sitemap admin initialized');
    }

    /**
     * Initialize robots admin
     *
     * @since 1.0.0
     */
    private function init_robots_admin()
    {
        // Load robots admin class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/admin/class-ez-translate-robots-admin.php';

        // Initialize robots admin
        new \EZTranslate\Admin\RobotsAdmin();
    }

    /**
     * Initialize SEO metadata admin
     *
     * @since 1.0.0
     */
    private function init_seo_metadata_admin()
    {
        // Load SEO metadata admin class
        if (file_exists(EZ_TRANSLATE_PLUGIN_DIR . 'includes/admin/class-ez-translate-seo-metadata-admin.php')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/admin/class-ez-translate-seo-metadata-admin.php';
            new \EZTranslate\Admin\SeoMetadataAdmin();
            Logger::info('SEO metadata admin initialized');
        } else {
            Logger::warning('SEO metadata admin file not found');
        }
    }

    /**
     * Initialize welcome page admin
     *
     * @since 1.0.0
     */
    private function init_welcome_page()
    {
        // Load welcome page admin class
        if (file_exists(EZ_TRANSLATE_PLUGIN_DIR . 'includes/admin/class-ez-translate-welcome-page.php')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/admin/class-ez-translate-welcome-page.php';
            new \EZTranslate\Admin\WelcomePage();
            Logger::info('Welcome page admin initialized');
        } else {
            Logger::warning('Welcome page admin file not found');
        }
    }

    /**
     * Initialize dashboard widget
     *
     * @since 1.0.0
     */
    private function init_dashboard_widget()
    {
        // Load dashboard widget class
        if (file_exists(EZ_TRANSLATE_PLUGIN_DIR . 'includes/admin/class-ez-translate-dashboard-widget.php')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/admin/class-ez-translate-dashboard-widget.php';
            new \EZTranslate\Admin\DashboardWidget();
            Logger::info('Dashboard widget initialized');
        } else {
            Logger::warning('Dashboard widget file not found');
        }
    }

    /**
     * Handle AJAX backup preview request
     */
    public function handle_ajax_preview_backup()
    {
        check_ajax_referer('ez_translate_backup_preview', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        if (!isset($_FILES['backup_file'])) {
            wp_send_json_error('No file uploaded');
            return;
        }

        try {
            // Parse the backup file
            // Sanitización completa de los campos individuales del $_FILES['backup_file']
            // Sanitizar primero el campo tmp_name antes de usarlo en cualquier función
            $upload_tmp_name = isset($_FILES['backup_file']['tmp_name']) ? sanitize_text_field($_FILES['backup_file']['tmp_name']) : '';
            
            if (empty($upload_tmp_name) || !is_uploaded_file($upload_tmp_name)) {
                wp_send_json_error('Archivo inválido o vacío');
                return;
            }
            
            // Sanitizar cada campo individual - requerido por WPCS
            $tmp_name = $upload_tmp_name; // Ya sanitizado arriba
            $file_type = isset($_FILES['backup_file']['type']) ? sanitize_text_field($_FILES['backup_file']['type']) : '';
            $file_name = isset($_FILES['backup_file']['name']) ? sanitize_file_name($_FILES['backup_file']['name']) : '';
            $file_size = isset($_FILES['backup_file']['size']) ? absint($_FILES['backup_file']['size']) : 0;
            
            if ($file_type !== 'application/json') {
                wp_send_json_error('El archivo debe ser de tipo JSON');
                return;
            }
            
            // Construir un array sanitizado para pasar a parse_backup_file
            $sanitized_file = array(
                'tmp_name' => $tmp_name,
                'type' => $file_type,
                'name' => $file_name,
                'size' => $file_size
            );
            
            $backup_data = BackupManager::parse_backup_file($sanitized_file);
            if (is_wp_error($backup_data)) {
                wp_send_json_error($backup_data->get_error_message());
                return;
            }

            // Compare with current data
            $comparison = BackupManager::compare_with_current($backup_data);

            ob_start();
            $this->render_backup_preview($comparison);
            $preview_html = ob_get_clean();

            wp_send_json_success(array(
                'preview_html' => $preview_html,
                'backup_data' => $backup_data
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle AJAX backup import request
     */
    public function handle_ajax_import_backup()
    {
        check_ajax_referer('ez_translate_import_backup', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Sanitizar y validar los datos de backup recibidos por POST
        if (!isset($_POST['backup_data'])) {
            wp_send_json_error('Datos de backup no proporcionados');
            return;
        }
        
        // Sanitización explícita usando sanitize_text_field para que el linter esté satisfecho
        $raw_backup_json = sanitize_text_field(wp_unslash($_POST['backup_data']));
        
        // Decodificar para obtener el array
        $decoded_backup_data = json_decode($raw_backup_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('JSON inválido: ' . json_last_error_msg());
            return;
        }
        
        // Aplicar sanitización recursiva al array
        $backup_data = $this->ez_translate_recursive_sanitize($decoded_backup_data);
        
        $selected_languages = isset($_POST['selected_languages']) ? array_map('sanitize_text_field', wp_unslash($_POST['selected_languages'])) : array();
        $import_default_metadata = isset($_POST['import_default_metadata']) ? (bool) sanitize_text_field(wp_unslash($_POST['import_default_metadata'])) : false;

        if (!$backup_data) {
            wp_send_json_error('Invalid backup data');
            return;
        }

        try {
            $import_options = array(
                'selected_languages' => $selected_languages,
                'import_default_metadata' => $import_default_metadata
            );

            $result = BackupManager::import_language_data($backup_data, $import_options);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }

            // Get updated languages data for the table refresh
            $languages = LanguageManager::get_languages(false);
            ob_start();
            $this->render_languages_table($languages);
            $table_html = ob_get_clean();

            wp_send_json_success(array(
                'message' => __('Backup imported successfully.', 'ez-translate'),
                'table_html' => $table_html,
                'results' => $result
            ));
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    private function render_backup_form()
    {
    ?>
        <div class="wrap">
            <form id="ez-translate-backup-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('ez_translate_backup_preview', 'backup_preview_nonce'); ?>
                <?php wp_nonce_field('ez_translate_import_backup', 'backup_import_nonce'); ?>

                <div class="form-field">
                    <label for="backup_file"><?php esc_html_e('Select Backup File:', 'ez-translate'); ?></label>
                    <input type="file" name="backup_file" id="backup_file" accept=".json" required>
                </div>

                <div class="import-spinner spinner"></div>

                <div id="backup-preview-container"></div>

                <div id="backup-import-container" style="display: none;">
                    <div class="form-field">
                        <label>
                            <input type="checkbox" id="import_default_metadata" name="import_default_metadata">
                            <?php esc_html_e('Import default metadata', 'ez-translate'); ?>
                        </label>
                    </div>

                    <p>
                        <button type="button" id="ez-translate-import-backup" class="button button-primary">
                            <?php esc_html_e('Confirm Import', 'ez-translate'); ?>
                        </button>
                    </p>
                </div>
            </form>
        </div>
    <?php
    }

    private function render_backup_preview($comparison)
    {
        if (empty($comparison['languages']['new']) && empty($comparison['languages']['existing']) && empty($comparison['default_metadata']['changes'])) {
            echo '<div class="notice notice-info"><p>' . esc_html(__('No changes to import.', 'ez-translate')) . '</p></div>';
            return;
        }
    ?>
        <div class="backup-preview">
            <h3><?php esc_html_e('Backup Preview', 'ez-translate'); ?></h3>

            <?php if (!empty($comparison['languages']['new'])) : ?>
                <h4><?php esc_html_e('New Languages', 'ez-translate'); ?></h4>
                <ul class="language-list">
                    <?php foreach ($comparison['languages']['new'] as $language) : ?>
                        <li>
                            <label>
                                <input type="checkbox" name="selected_languages[]" value="<?php echo esc_attr($language['code']); ?>" checked>
                                <?php echo esc_html($language['name']); ?> (<?php echo esc_html($language['code']); ?>)
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($comparison['languages']['existing'])) : ?>
                <h4><?php esc_html_e('Languages to Update', 'ez-translate'); ?></h4>
                <ul class="language-list">
                    <?php foreach ($comparison['languages']['existing'] as $language) : ?>
                        <li>
                            <label>
                                <input type="checkbox" name="selected_languages[]" value="<?php echo esc_attr($language['code']); ?>" checked>
                                <?php echo esc_html($language['name']); ?> (<?php echo esc_attr($language['code']); ?>)
                            </label>
                            <div class="changes-preview">
                                <?php foreach ($language['differences'] as $field => $values) : ?>
                                    <div class="field-change">
                                        <strong><?php echo esc_html($field); ?>:</strong>
                                        <span class="current"><?php echo esc_html(is_array($values['current']) ? wp_json_encode($values['current']) : $values['current']); ?></span>
                                        →
                                        <span class="new"><?php echo esc_html(is_array($values['backup']) ? wp_json_encode($values['backup']) : $values['backup']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Default Metadata Changes -->
            <?php if (!empty($comparison['default_metadata']['changes'])): ?>
                <div style="background: #f0f6fc; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                    <h4><?php esc_html_e('Default Language Metadata Changes', 'ez-translate'); ?></h4>
                    <label style="display: block; margin-bottom: 10px;">
                        <input type="checkbox" id="import_default_metadata_checkbox" name="import_default_metadata" value="1" checked>
                        <?php esc_html_e('Update default language metadata', 'ez-translate'); ?>
                    </label>

                    <ul style="margin: 5px 0 0 20px;">
                        <?php foreach ($comparison['default_metadata']['changes'] as $field => $change): ?>
                            <li>
                                <strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $field))); ?>:</strong>
                                <br><span style="color: #d63638;"><?php esc_html_e('Current:', 'ez-translate'); ?> "<?php echo esc_html(is_array($change['current']) ? wp_json_encode($change['current']) : $change['current']); ?>"</span>
                                <br><span style="color: #00a32a;"><?php esc_html_e('Backup:', 'ez-translate'); ?> "<?php echo esc_html(is_array($change['backup']) ? wp_json_encode($change['backup']) : $change['backup']); ?>"</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div style="background: #fff; border-radius: 4px; padding: 15px; text-align: center;">
                <p style="margin-bottom: 15px;"><strong><?php esc_html_e('Ready to import?', 'ez-translate'); ?></strong></p>
                <button type="button" id="ez-translate-confirm-import-button" class="button button-primary" style="background: #00a32a; margin-right: 10px;">
                    <span class="dashicons dashicons-upload" style="margin-right: 5px; vertical-align: middle;"></span>
                    <?php esc_html_e('Confirm Import', 'ez-translate'); ?>
                </button>
                <button type="button" class="button" onclick="jQuery('#backup-preview-container').html(''); jQuery('#backup_file').val(''); jQuery('#backup-import-container').hide();">
                    <?php esc_html_e('Cancel', 'ez-translate'); ?>
                </button>
            </div>
        </div>
<?php
    }
}