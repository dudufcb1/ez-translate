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

/**
 * Admin class for EZ Translate
 *
 * @since 1.0.0
 */
class Admin {

    /**
     * Menu slug for the main admin page
     *
     * @var string
     * @since 1.0.0
     */
    const MENU_SLUG = 'ez-translate';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
        $this->init_sitemap_admin();
        $this->init_robots_admin();
        $this->init_seo_metadata_admin();
        $this->init_welcome_page();
        $this->init_dashboard_widget();
        Logger::info('Admin class initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Admin styles and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Landing Page column in pages list
        add_filter('manage_pages_columns', array($this, 'add_landing_page_column'));
        add_action('manage_pages_custom_column', array($this, 'show_landing_page_column_content'), 10, 2);

        // Landing Pages table below main pages list
        add_action('all_admin_notices', array($this, 'add_landing_pages_table'));
    }

    /**
     * Add admin menu
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            Logger::warning('User attempted to access admin menu without proper capabilities', array(
                'user_id' => get_current_user_id(),
                'user_login' => wp_get_current_user()->user_login
            ));
            return;
        }

        // Add main menu page
        $page_hook = add_menu_page(
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
            'page_hook' => $page_hook,
            'menu_slug' => self::MENU_SLUG
        ));
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix The current admin page hook suffix
     * @since 1.0.0
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on our admin pages
        if (strpos($hook_suffix, self::MENU_SLUG) === false) {
            return;
        }

        // Enqueue WordPress admin styles (we'll use native styling)
        wp_enqueue_style('wp-admin');
    }

    /**
     * Handle form submissions for language management
     *
     * @since 1.0.0
     */
    private function handle_form_submissions() {
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
            'ez_translate_nonce_messages'
        );

        foreach ($nonce_fields as $nonce_field) {
            if (isset($_POST[$nonce_field]) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_field])), 'ez_translate_admin')) {
                $nonce_verified = true;
                break;
            }
            // Also check for dynamic delete nonces
            if (strpos($nonce_field, 'ez_translate_nonce_delete_') === 0 &&
                isset($_POST[$nonce_field]) &&
                wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_field])), 'ez_translate_admin')) {
                $nonce_verified = true;
                break;
            }
        }

        // Check for dynamic delete nonces
        if (!$nonce_verified) {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'ez_translate_nonce_delete_') === 0 &&
                    wp_verify_nonce(sanitize_text_field(wp_unslash($value)), 'ez_translate_admin')) {
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
            case 'update_detector_settings':
                $this->handle_update_detector_settings();
                break;
            case 'update_detector_messages':
                $this->handle_update_detector_messages();
                break;
            default:
                Logger::warning('Unknown form action', array('action' => $action));
                break;
        }
    }

    /**
     * Handle adding a new language
     *
     * @since 1.0.0
     */
    private function handle_add_language() {
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
    private function handle_edit_language() {
        // Validate required POST data exists
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        if (!isset($_POST['original_code'])) {
            $this->add_admin_notice(__('Missing original language code.', 'ez-translate'), 'error');
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $original_code = sanitize_text_field(wp_unslash($_POST['original_code']));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $language_data = \EZTranslate\LanguageManager::sanitize_language_data($_POST);

        // Check if landing page creation is requested
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $create_landing_page = isset($_POST['create_landing_page']) && $_POST['create_landing_page'] === '1';
        $landing_page_data = null;

        if ($create_landing_page) {
            // Sanitize landing page data
            $landing_page_data = array(
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
                'title' => sanitize_text_field(wp_unslash($_POST['landing_page_title'] ?? '')),
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
                'description' => sanitize_textarea_field(wp_unslash($_POST['landing_page_description'] ?? '')),
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
                'slug' => sanitize_title(wp_unslash($_POST['landing_page_slug'] ?? '')),
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
                'status' => isset($_POST['landing_page_status']) && in_array(sanitize_text_field(wp_unslash($_POST['landing_page_status'])), array('draft', 'publish')) ? sanitize_text_field(wp_unslash($_POST['landing_page_status'])) : 'draft'
            );

            // Validate required fields for landing page
            if (empty($landing_page_data['title']) || empty($landing_page_data['description'])) {
                $this->add_admin_notice(__('Landing page title and description are required when creating a landing page.', 'ez-translate'), 'error');
                return;
            }
        }

        // Update the language first
        $result = \EZTranslate\LanguageManager::update_language($original_code, $language_data);

        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
            return;
        }

        // Create landing page if requested
        if ($create_landing_page && $landing_page_data) {
            $landing_page_result = \EZTranslate\LanguageManager::create_landing_page_for_language($language_data['code'], $landing_page_data);

            if (is_wp_error($landing_page_result)) {
                $this->add_admin_notice(
                    sprintf(
                        /* translators: %s: error message */
                        __('Language updated successfully, but landing page creation failed: %s', 'ez-translate'),
                        $landing_page_result->get_error_message()
                    ),
                    'warning'
                );
            } else {
                $edit_url = admin_url('post.php?post=' . $landing_page_result . '&action=edit');
                $this->add_admin_notice(
                    sprintf(
                        /* translators: %s: URL to edit the landing page */
                        __('Language updated successfully! Landing page created: <a href="%s" target="_blank">Edit Landing Page</a>', 'ez-translate'),
                        esc_url($edit_url)
                    ),
                    'success'
                );
            }
        } else {
            $this->add_admin_notice(__('Language updated successfully!', 'ez-translate'), 'success');
        }
    }

    /**
     * Handle deleting a language
     *
     * @since 1.0.0
     */
    private function handle_delete_language() {
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
    private function handle_update_landing_page_seo() {
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
            $this->add_admin_notice(__('Landing page SEO updated successfully!', 'ez-translate'), 'success');
            Logger::info('Landing page SEO updated successfully', array(
                'post_id' => $post_id,
                'language_code' => $language_code
            ));
        }
    }

    /**
     * Handle API settings update
     *
     * @since 1.0.0
     */
    private function handle_update_api_settings() {
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
     * Add admin notice
     *
     * @param string $message Notice message
     * @param string $type    Notice type (success, error, warning, info)
     * @since 1.0.0
     */
    private function add_admin_notice($message, $type = 'info') {
        add_action('admin_notices', function() use ($message, $type) {
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
    private function get_language_options($exclude_language_code = '') {
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
    public function render_languages_page() {
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
                                    <?php echo wp_kses_post($this->get_language_options($wp_language_code)); ?>
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
                <?php if (empty($languages)): ?>
                    <p><?php esc_html_e('No languages configured yet. Add your first language above.', 'ez-translate'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped ez-translate-languages-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Code', 'ez-translate'); ?></th>
                                <th scope="col"><?php esc_html_e('Name', 'ez-translate'); ?></th>
                                <th scope="col"><?php esc_html_e('Slug', 'ez-translate'); ?></th>
                                <th scope="col"><?php esc_html_e('Native Name', 'ez-translate'); ?></th>
                                <th scope="col"><?php esc_html_e('Flag', 'ez-translate'); ?></th>
                                <th scope="col"><?php esc_html_e('RTL', 'ez-translate'); ?></th>
                                <th scope="col"><?php esc_html_e('Landing Page', 'ez-translate'); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'ez-translate'); ?></th>
                                <th scope="col"><?php esc_html_e('Actions', 'ez-translate'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($languages as $language): ?>
                                <?php
                                // Get landing page info for this language
                                $landing_page = \EZTranslate\LanguageManager::get_landing_page_for_language($language['code']);
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($language['code']); ?></strong></td>
                                    <td><?php echo esc_html($language['name']); ?></td>
                                    <td><code><?php echo esc_html(isset($language['slug']) ? $language['slug'] : $language['code']); ?></code></td>
                                    <td><?php echo esc_html(isset($language['native_name']) ? $language['native_name'] : '—'); ?></td>
                                    <td><?php echo esc_html(isset($language['flag']) ? $language['flag'] : '—'); ?></td>
                                    <td><?php echo (isset($language['rtl']) && $language['rtl']) ? esc_html__('Yes', 'ez-translate') : esc_html__('No', 'ez-translate'); ?></td>
                                    <td>
                                        <?php if ($landing_page): ?>
                                            <div style="margin-bottom: 5px;">
                                                <strong><?php echo esc_html($landing_page['title']); ?></strong>
                                                <br>
                                                <small style="color: #666;">
                                                    <?php echo esc_html($landing_page['status']); ?> |
                                                    <a href="<?php echo esc_url($landing_page['edit_url']); ?>" target="_blank"><?php esc_html_e('Edit', 'ez-translate'); ?></a> |
                                                    <a href="<?php echo esc_url($landing_page['view_url']); ?>" target="_blank"><?php esc_html_e('View', 'ez-translate'); ?></a>
                                                </small>
                                            </div>
                                            <button type="button" class="button button-small ez-translate-edit-seo-btn"
                                                    data-post-id="<?php echo esc_attr($landing_page['post_id']); ?>"
                                                    data-language="<?php echo esc_attr($language['code']); ?>"
                                                    data-title="<?php echo esc_attr($landing_page['seo_title']); ?>"
                                                    data-description="<?php echo esc_attr($landing_page['seo_description']); ?>">
                                                <?php esc_html_e('Edit SEO', 'ez-translate'); ?>
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #999;"><?php esc_html_e('No landing page', 'ez-translate'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($language['enabled']) ? $language['enabled'] : true): ?>
                                            <span class="ez-translate-status-enabled"><?php esc_html_e('Enabled', 'ez-translate'); ?></span>
                                        <?php else: ?>
                                            <span class="ez-translate-status-disabled"><?php esc_html_e('Disabled', 'ez-translate'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small ez-translate-edit-btn"
                                                data-language='<?php echo esc_attr(json_encode($language)); ?>'>
                                            <?php esc_html_e('Edit', 'ez-translate'); ?>
                                        </button>
                                        <form method="post" style="display: inline-block;"
                                              onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this language?', 'ez-translate'); ?>');">
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
            </div>

            <!-- Default Language Configuration -->
            <div class="card">
                <h2><?php esc_html_e('Default Language (x-default)', 'ez-translate'); ?></h2>
                <p><?php esc_html_e('Configure the default language for hreflang x-default tags. This language will be shown to users when their preferred language is not available.', 'ez-translate'); ?></p>

                <?php
                // Handle form submission
                if (isset($_POST['save_default_language']) &&
                    isset($_POST['ez_translate_default_language_nonce']) &&
                    wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ez_translate_default_language_nonce'])), 'ez_translate_save_default_language')) {

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
                if (isset($_POST['save_default_metadata']) &&
                    isset($_POST['ez_translate_default_metadata_nonce']) &&
                    wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ez_translate_default_metadata_nonce'])), 'ez_translate_save_default_metadata')) {

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

                    if ($selected_page_id > 0 && $selected_page_id !== $current_main_landing) {
                        // Transfer metadata to selected page
                        $this->transfer_metadata_to_main_landing_page($selected_page_id, $default_metadata, $wp_language_code);
                        update_option('ez_translate_main_landing_page_id', $selected_page_id);
                        echo '<div class="notice notice-success"><p>' . esc_html__('Main landing page updated and metadata transferred successfully!', 'ez-translate') . '</p></div>';
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
                                <label for="edit_language_code"><?php esc_html_e('Language Code', 'ez-translate'); ?> *</label>
                            </th>
                            <td>
                                <select id="edit_language_code_select" class="regular-text" style="margin-bottom: 10px;">
                                    <option value=""><?php esc_html_e('Select a common language...', 'ez-translate'); ?></option>
                                    <?php echo wp_kses_post($this->get_language_options()); ?>
                                </select>
                                <br>
                                <input type="text" id="edit_language_code" name="code" class="regular-text"
                                       pattern="[a-zA-Z0-9]{2,5}" maxlength="5" required>
                                <p class="description"><?php esc_html_e('Select from common languages above or enter a custom ISO 639-1 code', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_name"><?php esc_html_e('Language Name', 'ez-translate'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_name" name="name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_slug"><?php esc_html_e('Language Slug', 'ez-translate'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_slug" name="slug" class="regular-text"
                                       pattern="[a-z0-9\-_]+" required>
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

                    <!-- Landing Page Creation Section for Edit -->
                    <div class="card" style="margin-top: 20px;">
                        <h3><?php esc_html_e('Landing Page Creation', 'ez-translate'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Create Landing Page', 'ez-translate'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="edit_create_landing_page" name="create_landing_page" value="1">
                                        <?php esc_html_e('Create a landing page for this language', 'ez-translate'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('Create a new WordPress page configured as the landing page for this language.', 'ez-translate'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <div id="edit_landing_page_fields" style="display: none;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="edit_landing_page_title"><?php esc_html_e('Landing Page Title', 'ez-translate'); ?> *</label>
                                    </th>
                                    <td>
                                        <input type="text" id="edit_landing_page_title" name="landing_page_title" class="regular-text"
                                               placeholder="<?php esc_attr_e('e.g., Welcome to Our Site', 'ez-translate'); ?>">
                                        <p class="description"><?php esc_html_e('Title for the landing page (will also be used as SEO title)', 'ez-translate'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="edit_landing_page_description"><?php esc_html_e('Landing Page Description', 'ez-translate'); ?> *</label>
                                    </th>
                                    <td>
                                        <textarea id="edit_landing_page_description" name="landing_page_description" class="large-text" rows="3"
                                                  placeholder="<?php esc_attr_e('Brief description of your site for this language...', 'ez-translate'); ?>"></textarea>
                                        <p class="description"><?php esc_html_e('SEO description for the landing page (used in meta description and social media)', 'ez-translate'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="edit_landing_page_slug"><?php esc_html_e('Landing Page Slug', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="edit_landing_page_slug" name="landing_page_slug" class="regular-text"
                                               placeholder="<?php esc_attr_e('e.g., home, inicio, accueil', 'ez-translate'); ?>"
                                               pattern="[a-z0-9\-_]+">
                                        <p class="description"><?php esc_html_e('URL slug for the landing page (optional - will be auto-generated if empty)', 'ez-translate'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Page Status', 'ez-translate'); ?></th>
                                    <td>
                                        <label>
                                            <input type="radio" name="landing_page_status" value="draft" checked>
                                            <?php esc_html_e('Draft', 'ez-translate'); ?>
                                        </label>
                                        <label style="margin-left: 15px;">
                                            <input type="radio" name="landing_page_status" value="publish">
                                            <?php esc_html_e('Published', 'ez-translate'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Create as draft to edit content first, or publish immediately', 'ez-translate'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

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
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
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
                background: rgba(0,0,0,0.7);
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

            // Language selector change handler for edit form
            $('#edit_language_code_select').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                if (selectedOption.val()) {
                    var code = selectedOption.val();
                    var name = selectedOption.data('name');
                    var nativeName = selectedOption.data('native');
                    var flag = selectedOption.data('flag');

                    // Auto-populate fields
                    $('#edit_language_code').val(code);
                    $('#edit_language_name').val(name);
                    $('#edit_language_native_name').val(nativeName);
                    $('#edit_language_flag').val(flag);

                    // Generate slug from name
                    var slug = name.toLowerCase()
                        .replace(/[^a-z0-9\s\-_]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/\-+/g, '-')
                        .replace(/^-|-$/g, '');
                    $('#edit_language_slug').val(slug);

                    // Set RTL for known RTL languages
                    var rtlLanguages = ['ar', 'he', 'fa', 'ur'];
                    $('#edit_language_rtl').prop('checked', rtlLanguages.includes(code));
                }
            });

            // Edit button click handler
            $('.ez-translate-edit-btn').on('click', function() {
                var languageData = $(this).data('language');

                // Reset the select dropdown
                $('#edit_language_code_select').val('');

                // Populate the edit form
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

                // Reset landing page creation fields
                $('#edit_create_landing_page').prop('checked', false);
                $('#edit_landing_page_fields').hide();
                $('#edit_landing_page_title, #edit_landing_page_description, #edit_landing_page_slug').val('');
                $('input[name="landing_page_status"][value="draft"]').prop('checked', true);

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

            $('#edit_language_name').on('input', function() {
                // Only auto-generate if no language was selected from dropdown
                if (!$('#edit_language_code_select').val()) {
                    var name = $(this).val();
                    var slug = name.toLowerCase()
                        .replace(/[^a-z0-9\s\-_]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/\-+/g, '-')
                        .replace(/^-|-$/g, '');
                    $('#edit_language_slug').val(slug);
                }
            });

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

            // Landing page creation toggle for EDIT modal
            $('#edit_create_landing_page').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#edit_landing_page_fields').show();
                    // Auto-populate landing page title from language name
                    var languageName = $('#edit_language_name').val();
                    if (languageName && !$('#edit_landing_page_title').val()) {
                        $('#edit_landing_page_title').val('Welcome to Our Site - ' + languageName);
                    }
                } else {
                    $('#edit_landing_page_fields').hide();
                    // Clear landing page fields
                    $('#edit_landing_page_title, #edit_landing_page_description, #edit_landing_page_slug').val('');
                    $('input[name="landing_page_status"][value="draft"]').prop('checked', true);
                }
            });

            // Auto-populate landing page title when language name changes in EDIT modal
            $('#edit_language_name').on('input', function() {
                if ($('#edit_create_landing_page').is(':checked') && !$('#edit_landing_page_title').val()) {
                    var languageName = $(this).val();
                    if (languageName) {
                        $('#edit_landing_page_title').val('Welcome to Our Site - ' + languageName);
                    }
                }
            });

            // Auto-generate landing page slug from title in EDIT modal
            $('#edit_landing_page_title').on('input', function() {
                if (!$('#edit_landing_page_slug').val()) {
                    var title = $(this).val();
                    var slug = title.toLowerCase()
                        .replace(/[^a-z0-9\s\-_]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/\-+/g, '-')
                        .replace(/^-|-$/g, '');
                    $('#edit_landing_page_slug').val(slug);
                }
            });

            $('#edit_language_code_select').on('change', function() {
                if (!$(this).val()) {
                    // Don't clear when editing, just reset the dropdown
                }
            });

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
     * Add Landing Page column to pages list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     * @since 1.0.0
     */
    public function add_landing_page_column($columns) {
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
    public function show_landing_page_column_content($column_name, $post_id) {
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
    public function add_landing_pages_table() {
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
    private function get_all_landing_pages() {
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
        usort($landing_pages, function($a, $b) {
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
    private function render_landing_pages_table($landing_pages) {
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
     * Get menu slug
     *
     * @return string
     * @since 1.0.0
     */
    public static function get_menu_slug() {
        return self::MENU_SLUG;
    }

    /**
     * Initialize sitemap admin
     *
     * @since 1.0.0
     */
    private function init_sitemap_admin() {
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
    private function init_robots_admin() {
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
    private function init_seo_metadata_admin() {
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
     * Transfer metadata to main landing page
     *
     * @param int $page_id Page ID to transfer metadata to
     * @param array $metadata Metadata to transfer
     * @param string $language_code Language code
     * @since 1.0.0
     */
    private function transfer_metadata_to_main_landing_page($page_id, $metadata, $language_code) {
        // Load required classes
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-post-meta-manager.php';

        // Set language metadata
        update_post_meta($page_id, '_ez_translate_language', $language_code);

        // Transfer SEO metadata if available
        if (!empty($metadata['site_title'])) {
            update_post_meta($page_id, '_ez_translate_seo_title', $metadata['site_title']);
        }

        if (!empty($metadata['site_description'])) {
            update_post_meta($page_id, '_ez_translate_seo_description', $metadata['site_description']);
        }

        // Mark as landing page
        update_post_meta($page_id, '_ez_translate_is_landing', true);

        // Create or get translation group for landing pages
        $existing_group = get_post_meta($page_id, '_ez_translate_group', true);
        if (empty($existing_group)) {
            $group_id = \EZTranslate\PostMetaManager::generate_group_id();
            update_post_meta($page_id, '_ez_translate_group', $group_id);
        }

        // Update language configuration to include this page as landing page
        $languages = \EZTranslate\LanguageManager::get_languages();

        // Find if default language already exists in configuration
        $default_language_exists = false;
        foreach ($languages as $index => $language) {
            if ($language['code'] === $language_code) {
                $languages[$index]['landing_page_id'] = $page_id;
                $default_language_exists = true;
                break;
            }
        }

        // If default language doesn't exist in configuration, add it
        if (!$default_language_exists) {
            $languages[] = array(
                'code' => $language_code,
                'name' => $this->get_language_name($language_code),
                'enabled' => true,
                'landing_page_id' => $page_id,
                'site_name' => $metadata['site_name'] ?? '',
                'site_title' => $metadata['site_title'] ?? '',
                'site_description' => $metadata['site_description'] ?? ''
            );
        }

        // Save updated languages configuration
        update_option('ez_translate_languages', $languages);

        Logger::info('Metadata transferred to main landing page', array(
            'page_id' => $page_id,
            'language_code' => $language_code,
            'metadata' => $metadata,
            'default_language_exists' => $default_language_exists
        ));
    }

    /**
     * Get language name from code
     *
     * @param string $code Language code
     * @return string Language name
     * @since 1.0.0
     */
    private function get_language_name($code) {
        $language_names = array(
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

        return $language_names[$code] ?? ucfirst($code);
    }

    /**
     * Handle detector settings update
     *
     * @since 1.0.0
     */
    private function handle_update_detector_settings() {
        Logger::info('Processing detector settings update');

        // Load language detector class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-detector.php';

        // Sanitize input data
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $enabled = isset($_POST['detector_enabled']) && $_POST['detector_enabled'] === '1';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $auto_redirect = isset($_POST['auto_redirect']) && $_POST['auto_redirect'] === '1';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $show_helper = isset($_POST['show_helper']) && $_POST['show_helper'] === '1';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $restrict_navigation = isset($_POST['restrict_navigation']) && $_POST['restrict_navigation'] === '1';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $position = isset($_POST['position']) && in_array(wp_unslash($_POST['position']), array('bottom-right', 'bottom-left', 'top-right', 'top-left'))
                   // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
                   ? sanitize_text_field(wp_unslash($_POST['position'])) : 'bottom-right';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        $delay = max(0, min(10000, intval(isset($_POST['delay']) ? wp_unslash($_POST['delay']) : 2000)));

        $settings = array(
            'enabled' => $enabled,
            'auto_redirect' => $auto_redirect,
            'show_helper' => $show_helper,
            'restrict_navigation' => $restrict_navigation,
            'position' => $position,
            'delay' => $delay
        );

        $result = \EZTranslate\LanguageDetector::update_detector_config($settings);

        if ($result) {
            $this->add_admin_notice(__('Language detector settings updated successfully!', 'ez-translate'), 'success');
            Logger::info('Detector settings updated successfully', array(
                'enabled' => $enabled,
                'position' => $position,
                'delay' => $delay
            ));
        } else {
            $this->add_admin_notice(__('Failed to update detector settings.', 'ez-translate'), 'error');
            Logger::error('Failed to update detector settings');
        }
    }

    /**
     * Handle detector messages update
     *
     * @since 1.0.0
     */
    private function handle_update_detector_messages() {
        Logger::info('Processing detector messages update');

        // Load language detector class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-detector.php';

        // Get current config
        $current_config = \EZTranslate\LanguageDetector::get_detector_config();

        // Sanitize and process messages
        $messages = array();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submissions()
        if (isset($_POST['messages']) && is_array($_POST['messages'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in handle_form_submissions(), data sanitized below
            $raw_messages = wp_unslash($_POST['messages']);
            foreach ($raw_messages as $lang_code => $lang_messages) {
                if (is_array($lang_messages)) {
                    $messages[sanitize_text_field($lang_code)] = array(
                        'dropdown_title' => sanitize_text_field(isset($lang_messages['dropdown_title']) ? $lang_messages['dropdown_title'] : ''),
                        'translation_available' => sanitize_text_field(isset($lang_messages['translation_available']) ? $lang_messages['translation_available'] : ''),
                        'landing_available' => sanitize_text_field(isset($lang_messages['landing_available']) ? $lang_messages['landing_available'] : ''),
                        'translation_label' => sanitize_text_field(isset($lang_messages['translation_label']) ? $lang_messages['translation_label'] : ''),
                        'landing_label' => sanitize_text_field(isset($lang_messages['landing_label']) ? $lang_messages['landing_label'] : ''),
                        'current_language' => sanitize_text_field(isset($lang_messages['current_language']) ? $lang_messages['current_language'] : ''),
                        // Keep existing popup messages
                        'title' => isset($current_config['messages'][$lang_code]['title']) ? $current_config['messages'][$lang_code]['title'] : '',
                        'description' => isset($current_config['messages'][$lang_code]['description']) ? $current_config['messages'][$lang_code]['description'] : '',
                        'confirm_button' => isset($current_config['messages'][$lang_code]['confirm_button']) ? $current_config['messages'][$lang_code]['confirm_button'] : '',
                        'stay_button' => isset($current_config['messages'][$lang_code]['stay_button']) ? $current_config['messages'][$lang_code]['stay_button'] : '',
                        'free_navigation' => isset($current_config['messages'][$lang_code]['free_navigation']) ? $current_config['messages'][$lang_code]['free_navigation'] : ''
                    );
                }
            }
        }

        // Update configuration
        $updated_config = array_merge($current_config, array('messages' => $messages));
        $result = \EZTranslate\LanguageDetector::update_detector_config($updated_config);

        if ($result) {
            $this->add_admin_notice(__('Language detector messages updated successfully!', 'ez-translate'), 'success');
            Logger::info('Detector messages updated successfully', array(
                'languages_updated' => array_keys($messages)
            ));
        } else {
            $this->add_admin_notice(__('Failed to update detector messages.', 'ez-translate'), 'error');
            Logger::error('Failed to update detector messages');
        }
    }

    /**
     * Render the Language Detector admin page
     *
     * @since 1.0.0
     */
    public function render_detector_page() {
        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ez-translate'));
        }

        Logger::info('Language detector admin page accessed', array(
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login
        ));

        // Handle form submissions
        $this->handle_form_submissions();

        // Load language detector class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-detector.php';

        // Get current settings
        $config = \EZTranslate\LanguageDetector::get_detector_config();

        // Debug log
        Logger::debug('Detector page rendering', array(
            'config_enabled' => $config['enabled'],
            'messages_count' => isset($config['messages']) ? count($config['messages']) : 0
        ));

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="card" style="max-width: 1200px; width: 100%;">
                <h2><?php esc_html_e('Language Detector Configuration', 'ez-translate'); ?></h2>
                <p><?php esc_html_e('Configure the automatic language detection and redirection system for your visitors.', 'ez-translate'); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce_detector'); ?>
                    <input type="hidden" name="ez_translate_action" value="update_detector_settings">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="detector_enabled"><?php esc_html_e('Enable Language Detector', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="detector_enabled" name="detector_enabled" value="1"
                                           <?php checked($config['enabled']); ?>>
                                    <?php esc_html_e('Enable automatic language detection and redirection', 'ez-translate'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, the detector will analyze visitor browser language and offer appropriate redirections.', 'ez-translate'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="position"><?php esc_html_e('Detector Position', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <select id="position" name="position" class="regular-text">
                                    <option value="bottom-right" <?php selected($config['position'], 'bottom-right'); ?>>
                                        <?php esc_html_e('Bottom Right', 'ez-translate'); ?>
                                    </option>
                                    <option value="bottom-left" <?php selected($config['position'], 'bottom-left'); ?>>
                                        <?php esc_html_e('Bottom Left', 'ez-translate'); ?>
                                    </option>
                                    <option value="top-right" <?php selected($config['position'], 'top-right'); ?>>
                                        <?php esc_html_e('Top Right', 'ez-translate'); ?>
                                    </option>
                                    <option value="top-left" <?php selected($config['position'], 'top-left'); ?>>
                                        <?php esc_html_e('Top Left', 'ez-translate'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Choose where the language detector will appear on the page.', 'ez-translate'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="delay"><?php esc_html_e('Display Delay', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="delay" name="delay" value="<?php echo esc_attr($config['delay']); ?>"
                                       min="0" max="10000" step="500" class="small-text">
                                <span><?php esc_html_e('milliseconds', 'ez-translate'); ?></span>
                                <p class="description">
                                    <?php esc_html_e('Delay before showing the language detector popup (0 = immediate, 2000 = 2 seconds).', 'ez-translate'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Behavior Options', 'ez-translate'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="auto_redirect" value="1"
                                               <?php checked($config['auto_redirect']); ?>>
                                        <?php esc_html_e('Enable automatic redirection', 'ez-translate'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Automatically redirect users to their preferred language without asking.', 'ez-translate'); ?>
                                    </p>

                                    <label>
                                        <input type="checkbox" name="show_helper" value="1"
                                               <?php checked($config['show_helper']); ?>>
                                        <?php esc_html_e('Show helper button', 'ez-translate'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Show a small helper button when translation is available in user\'s language.', 'ez-translate'); ?>
                                    </p>

                                    <label>
                                        <input type="checkbox" name="restrict_navigation" value="1"
                                               <?php checked($config['restrict_navigation']); ?>>
                                        <?php esc_html_e('Restrict navigation to selected language', 'ez-translate'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Keep users within their selected language unless they choose "free navigation".', 'ez-translate'); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(esc_html__('Save Detector Settings', 'ez-translate')); ?>
                </form>
            </div>

            <!-- Messages Configuration Section -->
            <!-- DEBUG: Messages section should appear here -->
            <div class="card" style="max-width: 1200px; width: 100%; margin-top: 20px;">
                <h2><?php esc_html_e('Custom Messages Configuration', 'ez-translate'); ?></h2>
                <p><?php esc_html_e('Customize the messages shown in the language detector for each language. If not configured, English defaults will be used.', 'ez-translate'); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce_messages'); ?>
                    <input type="hidden" name="ez_translate_action" value="update_detector_messages">

                    <?php
                    $available_languages = array('es', 'en', 'pt', 'fr');
                    $language_names = array(
                        'es' => 'Español',
                        'en' => 'English',
                        'pt' => 'Português',
                        'fr' => 'Français'
                    );

                    foreach ($available_languages as $lang_code):
                        $lang_messages = isset($config['messages'][$lang_code]) ? $config['messages'][$lang_code] : array();
                    ?>
                    <h3><?php echo esc_html($language_names[$lang_code]); ?> (<?php echo esc_html($lang_code); ?>)</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="dropdown_title_<?php echo esc_attr($lang_code); ?>"><?php esc_html_e('Dropdown Title', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="dropdown_title_<?php echo esc_attr($lang_code); ?>"
                                       name="messages[<?php echo esc_attr($lang_code); ?>][dropdown_title]"
                                       value="<?php echo esc_attr(isset($lang_messages['dropdown_title']) ? $lang_messages['dropdown_title'] : ''); ?>"
                                       class="regular-text">
                                <p class="description"><?php esc_html_e('Title shown in the language selector dropdown', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="translation_available_<?php echo esc_attr($lang_code); ?>"><?php esc_html_e('Translation Available Message', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="translation_available_<?php echo esc_attr($lang_code); ?>"
                                       name="messages[<?php echo esc_attr($lang_code); ?>][translation_available]"
                                       value="<?php echo esc_attr(isset($lang_messages['translation_available']) ? $lang_messages['translation_available'] : ''); ?>"
                                       class="regular-text">
                                <p class="description"><?php esc_html_e('Message when a translation is available (e.g., "We have this version in")', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="landing_available_<?php echo esc_attr($lang_code); ?>"><?php esc_html_e('Landing Page Available Message', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="landing_available_<?php echo esc_attr($lang_code); ?>"
                                       name="messages[<?php echo esc_attr($lang_code); ?>][landing_available]"
                                       value="<?php echo esc_attr(isset($lang_messages['landing_available']) ? $lang_messages['landing_available'] : ''); ?>"
                                       class="regular-text">
                                <p class="description"><?php esc_html_e('Message when only landing page is available (e.g., "We have homepage in")', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="translation_label_<?php echo esc_attr($lang_code); ?>"><?php esc_html_e('Translation Label', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="translation_label_<?php echo esc_attr($lang_code); ?>"
                                       name="messages[<?php echo esc_attr($lang_code); ?>][translation_label]"
                                       value="<?php echo esc_attr(isset($lang_messages['translation_label']) ? $lang_messages['translation_label'] : ''); ?>"
                                       class="small-text">
                                <input type="text" id="landing_label_<?php echo esc_attr($lang_code); ?>"
                                       name="messages[<?php echo esc_attr($lang_code); ?>][landing_label]"
                                       value="<?php echo esc_attr(isset($lang_messages['landing_label']) ? $lang_messages['landing_label'] : ''); ?>"
                                       class="small-text" placeholder="<?php esc_attr_e('Landing Page', 'ez-translate'); ?>">
                                <p class="description"><?php esc_html_e('Labels for "Translation" and "Landing Page" in dropdown', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php endforeach; ?>

                    <?php submit_button(esc_html__('Save Custom Messages', 'ez-translate')); ?>
                </form>
            </div>

            <!-- Preview Section -->
            <div class="card" style="max-width: 1200px; width: 100%; margin-top: 20px;">
                <h2><?php esc_html_e('Preview', 'ez-translate'); ?></h2>
                <p><?php esc_html_e('The language detector will appear based on your configuration when visitors access your site.', 'ez-translate'); ?></p>

                <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h3><?php esc_html_e('How it works:', 'ez-translate'); ?></h3>
                    <ol>
                        <li><strong><?php esc_html_e('Fold Mode (Passive):', 'ez-translate'); ?></strong> <?php esc_html_e('Small tab when user is in correct language', 'ez-translate'); ?></li>
                        <li><strong><?php esc_html_e('Unfold Mode (Active):', 'ez-translate'); ?></strong> <?php esc_html_e('Prominent popup when language mismatch detected', 'ez-translate'); ?></li>
                        <li><strong><?php esc_html_e('Helper Mode (Assistant):', 'ez-translate'); ?></strong> <?php esc_html_e('Small button when translation exists in user\'s language', 'ez-translate'); ?></li>
                    </ol>
                </div>

                <?php if ($config['enabled']): ?>
                    <p style="color: #0073aa; font-weight: 600;">
                        ✅ <?php esc_html_e('Language detector is currently ENABLED and will appear on your frontend.', 'ez-translate'); ?>
                    </p>
                <?php else: ?>
                    <p style="color: #d63638; font-weight: 600;">
                        ❌ <?php esc_html_e('Language detector is currently DISABLED.', 'ez-translate'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Initialize welcome page admin
     *
     * @since 1.0.0
     */
    private function init_welcome_page() {
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
    private function init_dashboard_widget() {
        // Load dashboard widget class
        if (file_exists(EZ_TRANSLATE_PLUGIN_DIR . 'includes/admin/class-ez-translate-dashboard-widget.php')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/admin/class-ez-translate-dashboard-widget.php';
            new \EZTranslate\Admin\DashboardWidget();
            Logger::info('Dashboard widget initialized');
        } else {
            Logger::warning('Dashboard widget file not found');
        }
    }
}
