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
        
        Logger::debug('Admin hooks initialized');
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
            __('EZ Translate', 'ez-translate'),           // Page title
            __('EZ Translate', 'ez-translate'),           // Menu title
            'manage_options',                             // Capability
            self::MENU_SLUG,                             // Menu slug
            array($this, 'render_languages_page'),       // Callback function
            'dashicons-translation',                      // Icon
            21                                           // Position (after Pages which is 20)
        );

        // Add submenu page (Languages - same as main page)
        add_submenu_page(
            self::MENU_SLUG,                             // Parent slug
            __('Languages', 'ez-translate'),              // Page title
            __('Languages', 'ez-translate'),              // Menu title
            'manage_options',                             // Capability
            self::MENU_SLUG,                             // Menu slug (same as parent for main page)
            array($this, 'render_languages_page')        // Callback function
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
        
        Logger::debug('Admin assets enqueued', array(
            'hook_suffix' => $hook_suffix
        ));
    }

    /**
     * Handle form submissions for language management
     *
     * @since 1.0.0
     */
    private function handle_form_submissions() {
        // Check if this is a form submission
        if (!isset($_POST['ez_translate_action']) || !wp_verify_nonce($_POST['ez_translate_nonce'], 'ez_translate_admin')) {
            return;
        }

        // Load the language manager
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';

        $action = sanitize_text_field($_POST['ez_translate_action']);
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
        $language_data = \EZTranslate\LanguageManager::sanitize_language_data($_POST);

        // Add the language
        $result = \EZTranslate\LanguageManager::add_language($language_data);

        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $this->add_admin_notice(__('Language added successfully!', 'ez-translate'), 'success');
        }
    }

    /**
     * Handle editing a language
     *
     * @since 1.0.0
     */
    private function handle_edit_language() {
        $original_code = sanitize_text_field($_POST['original_code']);
        $language_data = \EZTranslate\LanguageManager::sanitize_language_data($_POST);

        $result = \EZTranslate\LanguageManager::update_language($original_code, $language_data);

        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
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
        $code = sanitize_text_field($_POST['language_code']);

        $result = \EZTranslate\LanguageManager::delete_language($code);

        if (is_wp_error($result)) {
            $this->add_admin_notice($result->get_error_message(), 'error');
        } else {
            $this->add_admin_notice(__('Language deleted successfully!', 'ez-translate'), 'success');
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
     * @return string HTML options for language select
     * @since 1.0.0
     */
    private function get_language_options() {
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
            wp_die(__('You do not have sufficient permissions to access this page.', 'ez-translate'));
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
            <div class="card">
                <h2><?php _e('Add New Language', 'ez-translate'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce'); ?>
                    <input type="hidden" name="ez_translate_action" value="add_language">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="language_code"><?php _e('Language Code', 'ez-translate'); ?> *</label>
                            </th>
                            <td>
                                <select id="language_code_select" class="regular-text" style="margin-bottom: 10px;">
                                    <option value=""><?php _e('Select a common language...', 'ez-translate'); ?></option>
                                    <?php echo $this->get_language_options(); ?>
                                </select>
                                <br>
                                <input type="text" id="language_code" name="code" class="regular-text"
                                       placeholder="<?php esc_attr_e('Or enter custom code (e.g., en, es, fr)', 'ez-translate'); ?>"
                                       pattern="[a-zA-Z0-9]{2,5}" maxlength="5" required>
                                <p class="description"><?php _e('Select from common languages above or enter a custom ISO 639-1 code (2-5 characters)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="language_name"><?php _e('Language Name', 'ez-translate'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="language_name" name="name" class="regular-text"
                                       placeholder="<?php esc_attr_e('e.g., English, Español, Français', 'ez-translate'); ?>" required>
                                <p class="description"><?php _e('Display name for the language', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="language_slug"><?php _e('Language Slug', 'ez-translate'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="language_slug" name="slug" class="regular-text"
                                       placeholder="<?php esc_attr_e('e.g., english, spanish, french', 'ez-translate'); ?>"
                                       pattern="[a-z0-9\-_]+" required>
                                <p class="description"><?php _e('URL-friendly slug (lowercase, no spaces)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="language_native_name"><?php _e('Native Name', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="language_native_name" name="native_name" class="regular-text"
                                       placeholder="<?php esc_attr_e('e.g., English, Español, Français', 'ez-translate'); ?>">
                                <p class="description"><?php _e('Name in the native language (optional)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="language_flag"><?php _e('Flag Emoji', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="language_flag" name="flag" class="small-text"
                                       placeholder="<?php esc_attr_e('🇺🇸', 'ez-translate'); ?>" maxlength="4">
                                <p class="description"><?php _e('Flag emoji for visual identification (optional)', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Text Direction', 'ez-translate'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="rtl" value="1">
                                    <?php _e('Right-to-left (RTL) language', 'ez-translate'); ?>
                                </label>
                                <p class="description"><?php _e('Check if this language reads from right to left', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Status', 'ez-translate'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enabled" value="1" checked>
                                    <?php _e('Enable this language', 'ez-translate'); ?>
                                </label>
                                <p class="description"><?php _e('Disabled languages are hidden from frontend', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('Add Language', 'ez-translate'), 'primary', 'submit', false); ?>
                </form>
            </div>

            <!-- Current Languages List -->
            <div class="card">
                <h2><?php _e('Current Languages', 'ez-translate'); ?></h2>
                <?php if (empty($languages)): ?>
                    <p><?php _e('No languages configured yet. Add your first language above.', 'ez-translate'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php _e('Code', 'ez-translate'); ?></th>
                                <th scope="col"><?php _e('Name', 'ez-translate'); ?></th>
                                <th scope="col"><?php _e('Slug', 'ez-translate'); ?></th>
                                <th scope="col"><?php _e('Native Name', 'ez-translate'); ?></th>
                                <th scope="col"><?php _e('Flag', 'ez-translate'); ?></th>
                                <th scope="col"><?php _e('RTL', 'ez-translate'); ?></th>
                                <th scope="col"><?php _e('Status', 'ez-translate'); ?></th>
                                <th scope="col"><?php _e('Actions', 'ez-translate'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($languages as $language): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($language['code']); ?></strong></td>
                                    <td><?php echo esc_html($language['name']); ?></td>
                                    <td><code><?php echo esc_html($language['slug']); ?></code></td>
                                    <td><?php echo esc_html($language['native_name'] ?? '—'); ?></td>
                                    <td><?php echo esc_html($language['flag'] ?? '—'); ?></td>
                                    <td><?php echo ($language['rtl'] ?? false) ? __('Yes', 'ez-translate') : __('No', 'ez-translate'); ?></td>
                                    <td>
                                        <?php if ($language['enabled'] ?? true): ?>
                                            <span class="ez-translate-status-enabled"><?php _e('Enabled', 'ez-translate'); ?></span>
                                        <?php else: ?>
                                            <span class="ez-translate-status-disabled"><?php _e('Disabled', 'ez-translate'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small ez-translate-edit-btn"
                                                data-language='<?php echo esc_attr(json_encode($language)); ?>'>
                                            <?php _e('Edit', 'ez-translate'); ?>
                                        </button>
                                        <form method="post" style="display: inline-block;"
                                              onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this language?', 'ez-translate'); ?>');">
                                            <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce'); ?>
                                            <input type="hidden" name="ez_translate_action" value="delete_language">
                                            <input type="hidden" name="language_code" value="<?php echo esc_attr($language['code']); ?>">
                                            <button type="submit" class="button button-small button-link-delete">
                                                <?php _e('Delete', 'ez-translate'); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Statistics -->
            <div class="card">
                <h2><?php _e('Statistics', 'ez-translate'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Total Languages', 'ez-translate'); ?></th>
                        <td><?php echo count($languages); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Enabled Languages', 'ez-translate'); ?></th>
                        <td><?php echo count(\EZTranslate\LanguageManager::get_enabled_languages()); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Plugin Version', 'ez-translate'); ?></th>
                        <td><?php echo esc_html(EZ_TRANSLATE_VERSION); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Testing Section -->
            <?php if (isset($_GET['run_ez_translate_tests']) && $_GET['run_ez_translate_tests'] === '1'): ?>
                <div class="card">
                    <h2><?php _e('Test Results', 'ez-translate'); ?></h2>
                    <?php
                    // Run Language Manager tests
                    if (file_exists(EZ_TRANSLATE_PLUGIN_DIR . 'tests/test-language-manager.php')) {
                        require_once EZ_TRANSLATE_PLUGIN_DIR . 'tests/test-language-manager.php';
                        \EZTranslateLanguageManagerTest::run_tests();
                    }

                    // Run Post Meta Manager tests
                    if (file_exists(EZ_TRANSLATE_PLUGIN_DIR . 'tests/test-post-meta-manager.php')) {
                        require_once EZ_TRANSLATE_PLUGIN_DIR . 'tests/test-post-meta-manager.php';
                        ez_translate_display_post_meta_tests();
                    }

                    // Run Gutenberg Integration tests
                    if (file_exists(EZ_TRANSLATE_PLUGIN_DIR . 'tests/test-gutenberg-integration.php')) {
                        require_once EZ_TRANSLATE_PLUGIN_DIR . 'tests/test-gutenberg-integration.php';
                        ez_translate_display_gutenberg_tests();
                    }
                    ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <h2><?php _e('Testing', 'ez-translate'); ?></h2>
                    <p><?php _e('Run automated tests to verify plugin functionality.', 'ez-translate'); ?></p>
                    <a href="<?php echo esc_url(add_query_arg('run_ez_translate_tests', '1')); ?>" class="button button-secondary">
                        <?php _e('Run All Tests', 'ez-translate'); ?>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg('run_ez_translate_gutenberg_tests', '1')); ?>" class="button button-secondary" style="margin-left: 10px;">
                        <?php _e('Run Gutenberg Tests', 'ez-translate'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Edit Language Modal -->
        <div id="ez-translate-edit-modal" style="display: none;">
            <div class="ez-translate-modal-content">
                <h2><?php _e('Edit Language', 'ez-translate'); ?></h2>
                <form method="post" action="" id="ez-translate-edit-form">
                    <?php wp_nonce_field('ez_translate_admin', 'ez_translate_nonce'); ?>
                    <input type="hidden" name="ez_translate_action" value="edit_language">
                    <input type="hidden" name="original_code" id="edit_original_code">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="edit_language_code"><?php _e('Language Code', 'ez-translate'); ?> *</label>
                            </th>
                            <td>
                                <select id="edit_language_code_select" class="regular-text" style="margin-bottom: 10px;">
                                    <option value=""><?php _e('Select a common language...', 'ez-translate'); ?></option>
                                    <?php echo $this->get_language_options(); ?>
                                </select>
                                <br>
                                <input type="text" id="edit_language_code" name="code" class="regular-text"
                                       pattern="[a-zA-Z0-9]{2,5}" maxlength="5" required>
                                <p class="description"><?php _e('Select from common languages above or enter a custom ISO 639-1 code', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_name"><?php _e('Language Name', 'ez-translate'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_name" name="name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_slug"><?php _e('Language Slug', 'ez-translate'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_slug" name="slug" class="regular-text"
                                       pattern="[a-z0-9\-_]+" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_native_name"><?php _e('Native Name', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_native_name" name="native_name" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit_language_flag"><?php _e('Flag Emoji', 'ez-translate'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit_language_flag" name="flag" class="small-text" maxlength="4">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Text Direction', 'ez-translate'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="edit_language_rtl" name="rtl" value="1">
                                    <?php _e('Right-to-left (RTL) language', 'ez-translate'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Status', 'ez-translate'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="edit_language_enabled" name="enabled" value="1">
                                    <?php _e('Enable this language', 'ez-translate'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Update Language', 'ez-translate'); ?></button>
                        <button type="button" class="button ez-translate-cancel-edit"><?php _e('Cancel', 'ez-translate'); ?></button>
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

            #ez-translate-edit-modal {
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

            $('#edit_language_code_select').on('change', function() {
                if (!$(this).val()) {
                    // Don't clear when editing, just reset the dropdown
                }
            });
        });
        </script>
        <?php

        Logger::debug('Languages admin page rendered successfully');
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
}
