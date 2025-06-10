<?php
/**
 * EZ Translate SEO Metadata Admin Class
 *
 * Handles the SEO metadata configuration interface
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEO Metadata Admin class for EZ Translate
 *
 * @since 1.0.0
 */
class SeoMetadataAdmin {

    /**
     * Option name for SEO metadata settings
     *
     * @var string
     * @since 1.0.0
     */
    const OPTION_NAME = 'ez_translate_seo_metadata_settings';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
        \EZTranslate\Logger::info('SEO Metadata Admin class initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Add submenu page
        add_action('admin_menu', array($this, 'add_admin_menu'), 25);
        
        // Handle form submissions
        add_action('admin_post_ez_translate_update_seo_metadata_settings', array($this, 'handle_settings_update'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Add SEO metadata submenu to EZ Translate menu
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        add_submenu_page(
            'ez-translate',
            __('SEO Metadata', 'ez-translate'),
            __('SEO Metadata', 'ez-translate'),
            'manage_options',
            'ez-translate-seo-metadata',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix The current admin page hook suffix
     * @since 1.0.0
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on our SEO metadata admin page
        if ($hook_suffix !== 'ez-translate_page_ez-translate-seo-metadata') {
            return;
        }

        // Enqueue WordPress admin styles
        wp_enqueue_style('wp-admin');
        
        // Enqueue custom admin styles
        wp_enqueue_style(
            'ez-translate-seo-metadata-admin',
            EZ_TRANSLATE_PLUGIN_URL . 'assets/css/seo-metadata-admin.css',
            array(),
            EZ_TRANSLATE_VERSION
        );

        // Enqueue custom admin scripts
        wp_enqueue_script(
            'ez-translate-seo-metadata-admin',
            EZ_TRANSLATE_PLUGIN_URL . 'assets/js/seo-metadata-admin.js',
            array('jquery'),
            EZ_TRANSLATE_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('ez-translate-seo-metadata-admin', 'ezTranslateSeoMetadata', array(
            'nonce' => wp_create_nonce('ez_translate_seo_metadata_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'strings' => array(
                'confirmReset' => __('Are you sure you want to reset all settings to default values?', 'ez-translate'),
                'saved' => __('Settings saved', 'ez-translate'),
                'error' => __('Error saving settings', 'ez-translate')
            )
        ));
    }

    /**
     * Get default SEO metadata settings
     *
     * @return array Default settings
     * @since 1.0.0
     */
    public static function get_default_settings() {
        return array(
            // Control general
            'enabled' => true,
            
            // Metadatos básicos
            'document_title' => true,
            'meta_description' => true,
            'canonical_urls' => true,
            
            // Redes sociales
            'open_graph' => true,
            'twitter_cards' => true,
            'featured_images' => true,
            
            // Multiidioma
            'hreflang_tags' => true,
            'language_alternates' => true,
            
            // Datos estructurados
            'json_ld_schema' => true,
            'json_ld_articles' => true,
            'json_ld_landing_pages' => true,
            'json_ld_homepage' => true,
            'article_metadata' => true,
            
            // Configuración avanzada
            'override_other_plugins' => true,
            'priority_level' => 1,

            // Schema type defaults
            'default_schema_type' => 'auto', // auto, article, webpage, website
            'landing_page_schema_type' => 'webpage', // webpage, article, website
            'homepage_schema_type' => 'website', // website, webpage, article
            'post_schema_type' => 'article', // article, webpage
            'page_schema_type' => 'webpage' // webpage, article
        );
    }

    /**
     * Get current SEO metadata settings
     *
     * @return array Current settings
     * @since 1.0.0
     */
    public static function get_settings() {
        $default_settings = self::get_default_settings();
        $settings = get_option(self::OPTION_NAME, $default_settings);

        // Ensure settings is always an array with required keys
        if (!is_array($settings)) {
            $settings = $default_settings;
            \EZTranslate\Logger::warning('SEO metadata settings option was not an array, resetting to defaults');
        }

        // Merge with defaults to ensure all keys exist
        $settings = array_merge($default_settings, $settings);

        return $settings;
    }

    /**
     * Update SEO metadata settings
     *
     * @param array $settings New settings
     * @return bool Success status
     * @since 1.0.0
     */
    public static function update_settings($settings) {
        $default_settings = self::get_default_settings();
        
        // Validate and sanitize settings
        $validated_settings = array();
        foreach ($default_settings as $key => $default_value) {
            if (isset($settings[$key])) {
                if (is_bool($default_value)) {
                    $validated_settings[$key] = (bool) $settings[$key];
                } elseif (is_int($default_value)) {
                    $validated_settings[$key] = (int) $settings[$key];
                } else {
                    $validated_settings[$key] = sanitize_text_field($settings[$key]);
                }
            } else {
                $validated_settings[$key] = $default_value;
            }
        }

        $result = update_option(self::OPTION_NAME, $validated_settings);
        
        if ($result) {
            \EZTranslate\Logger::info('SEO metadata settings updated', $validated_settings);
        } else {
            \EZTranslate\Logger::error('Failed to update SEO metadata settings');
        }

        return $result;
    }

    /**
     * Check if a specific metadata type is enabled
     *
     * @param string $metadata_type The metadata type to check
     * @return bool Whether the metadata type is enabled
     * @since 1.0.0
     */
    public static function is_metadata_enabled($metadata_type) {
        $settings = self::get_settings();
        
        // If SEO metadata is globally disabled, return false
        if (!$settings['enabled']) {
            return false;
        }
        
        // Check specific metadata type
        return isset($settings[$metadata_type]) ? (bool) $settings[$metadata_type] : false;
    }

    /**
     * Handle settings form submission
     *
     * @since 1.0.0
     */
    public function handle_settings_update() {
        // Verify nonce
        if (!isset($_POST['ez_translate_seo_metadata_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ez_translate_seo_metadata_nonce'])), 'ez_translate_seo_metadata_settings')) {
            wp_die(esc_html__('Security check failed.', 'ez-translate'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'ez-translate'));
        }

        // Get default settings structure
        $default_settings = self::get_default_settings();
        $new_settings = array();

        // Process each setting
        foreach ($default_settings as $key => $default_value) {
            if (is_bool($default_value)) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
                $new_settings[$key] = isset($_POST[$key]) && $_POST[$key] === '1';
            } elseif (is_int($default_value)) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
                $new_settings[$key] = isset($_POST[$key]) ? (int) $_POST[$key] : $default_value;
            } else {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above
                $new_settings[$key] = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : $default_value;
            }
        }

        // Update settings
        $result = self::update_settings($new_settings);

        // Redirect with status
        $redirect_args = array(
            'page' => 'ez-translate-seo-metadata'
        );

        if ($result) {
            $redirect_args['updated'] = 'true';
        } else {
            $redirect_args['error'] = 'save_failed';
        }

        wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    /**
     * Render the SEO metadata admin page
     *
     * @since 1.0.0
     */
    public function render_admin_page() {
        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ez-translate'));
        }

        \EZTranslate\Logger::info('SEO metadata admin page accessed', array(
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login
        ));

        // Get current settings
        $settings = self::get_settings();

        // Handle admin notices
        if (isset($_GET['updated']) && $_GET['updated'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 esc_html__('SEO metadata settings updated successfully.', 'ez-translate') .
                 '</p></div>';
        }

        if (isset($_GET['error']) && $_GET['error'] === 'save_failed') {
            echo '<div class="notice notice-error is-dismissible"><p>' .
                 esc_html__('Failed to save SEO metadata settings.', 'ez-translate') .
                 '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="ez-translate-seo-metadata-admin">
                <div class="card" style="max-width: 1200px; width: 100%;">
                    <h2><?php esc_html_e('SEO Metadata Configuration', 'ez-translate'); ?></h2>
                    <p><?php esc_html_e('Control which SEO metadata EZ Translate generates. This allows you to use other SEO plugins for specific functions while maintaining translation functionality.', 'ez-translate'); ?></p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="seo-metadata-form">
                        <?php wp_nonce_field('ez_translate_seo_metadata_settings', 'ez_translate_seo_metadata_nonce'); ?>
                        <input type="hidden" name="action" value="ez_translate_update_seo_metadata_settings">

                        <!-- General Control -->
                        <div class="seo-metadata-section">
                            <h3>🔧 <?php esc_html_e('General Control', 'ez-translate'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="enabled"><?php esc_html_e('Enable SEO Metadata', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="enabled" name="enabled" value="1"
                                                   <?php checked($settings['enabled']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Globally controls whether EZ Translate generates SEO metadata. If disabled, no metadata will be generated.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="override_other_plugins"><?php esc_html_e('Priority over other plugins', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="override_other_plugins" name="override_other_plugins" value="1"
                                                   <?php checked($settings['override_other_plugins']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('When enabled, EZ Translate will take priority over other SEO plugins to avoid duplicates.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Basic Metadata -->
                        <div class="seo-metadata-section">
                            <h3>📝 <?php esc_html_e('Basic Metadata', 'ez-translate'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="document_title"><?php esc_html_e('Document Title (SEO Title)', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="document_title" name="document_title" value="1"
                                                   <?php checked($settings['document_title']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Controls the document title (&lt;title&gt; tag) with custom SEO titles.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="meta_description"><?php esc_html_e('Meta Description', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="meta_description" name="meta_description" value="1"
                                                   <?php checked($settings['meta_description']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Generates custom meta description tags for translated pages.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="canonical_urls"><?php esc_html_e('Canonical URLs', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="canonical_urls" name="canonical_urls" value="1"
                                                   <?php checked($settings['canonical_urls']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Generates canonical links to avoid duplicate content between languages.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Social Media -->
                        <div class="seo-metadata-section">
                            <h3>📱 <?php esc_html_e('Social Media', 'ez-translate'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="open_graph"><?php esc_html_e('Open Graph (Facebook)', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="open_graph" name="open_graph" value="1"
                                                   <?php checked($settings['open_graph']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Generates Open Graph metadata (og:title, og:description, og:type, og:url, og:locale, og:image).', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="twitter_cards"><?php esc_html_e('Twitter Cards', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="twitter_cards" name="twitter_cards" value="1"
                                                   <?php checked($settings['twitter_cards']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Generates Twitter Cards metadata (twitter:card, twitter:title, twitter:description, twitter:image).', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="featured_images"><?php esc_html_e('Featured Images', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="featured_images" name="featured_images" value="1"
                                                   <?php checked($settings['featured_images']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Automatically integrates featured images into social media metadata.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Multilingual -->
                        <div class="seo-metadata-section">
                            <h3>🔗 <?php esc_html_e('Multilingual', 'ez-translate'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="hreflang_tags"><?php esc_html_e('Hreflang Tags', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="hreflang_tags" name="hreflang_tags" value="1"
                                                   <?php checked($settings['hreflang_tags']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Generates bidirectional hreflang links between translations + configurable x-default.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="language_alternates"><?php esc_html_e('Language Alternates', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="language_alternates" name="language_alternates" value="1"
                                                   <?php checked($settings['language_alternates']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Generates language alternate links for search engines.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Structured Data -->
                        <div class="seo-metadata-section">
                            <h3>📊 <?php esc_html_e('Structured Data', 'ez-translate'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="json_ld_schema"><?php esc_html_e('JSON-LD Schema (Global)', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="json_ld_schema" name="json_ld_schema" value="1"
                                                   <?php checked($settings['json_ld_schema']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Master control for all JSON-LD structured data generation.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="json_ld_articles"><?php esc_html_e('JSON-LD for Articles', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="json_ld_articles" name="json_ld_articles" value="1"
                                                   <?php checked($settings['json_ld_articles']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Generates Article schema for posts and articles with author, dates, categories, and tags.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="json_ld_landing_pages"><?php esc_html_e('JSON-LD for Landing Pages', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="json_ld_landing_pages" name="json_ld_landing_pages" value="1"
                                                   <?php checked($settings['json_ld_landing_pages']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Generates WebPage schema for landing pages with breadcrumbs and site information.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="json_ld_homepage"><?php esc_html_e('JSON-LD for Homepage', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="json_ld_homepage" name="json_ld_homepage" value="1"
                                                   <?php checked($settings['json_ld_homepage']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Generates WebSite schema for homepage with search functionality and organization data.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="article_metadata"><?php esc_html_e('Article Metadata', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="article_metadata" name="article_metadata" value="1"
                                                   <?php checked($settings['article_metadata']); ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('Generates article-specific metadata with author, dates and language.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Schema Type Configuration -->
                        <div class="seo-metadata-section">
                            <h3>⚙️ <?php esc_html_e('Schema Type Configuration', 'ez-translate'); ?></h3>
                            <p class="description" style="margin-bottom: 15px;">
                                <?php esc_html_e('Configure which JSON-LD schema types to use for different page types. This gives you full control over how your content appears in search results.', 'ez-translate'); ?>
                            </p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="default_schema_type"><?php esc_html_e('Default Schema Type', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <select id="default_schema_type" name="default_schema_type">
                                            <option value="auto" <?php selected($settings['default_schema_type'], 'auto'); ?>><?php esc_html_e('Auto-detect (Recommended)', 'ez-translate'); ?></option>
                                            <option value="article" <?php selected($settings['default_schema_type'], 'article'); ?>><?php esc_html_e('Article Schema', 'ez-translate'); ?></option>
                                            <option value="webpage" <?php selected($settings['default_schema_type'], 'webpage'); ?>><?php esc_html_e('WebPage Schema', 'ez-translate'); ?></option>
                                            <option value="website" <?php selected($settings['default_schema_type'], 'website'); ?>><?php esc_html_e('WebSite Schema', 'ez-translate'); ?></option>
                                        </select>
                                        <p class="description">
                                            <?php esc_html_e('Fallback schema type when auto-detection cannot determine the appropriate type.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="landing_page_schema_type"><?php esc_html_e('Landing Pages Schema', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <select id="landing_page_schema_type" name="landing_page_schema_type">
                                            <option value="webpage" <?php selected($settings['landing_page_schema_type'], 'webpage'); ?>><?php esc_html_e('WebPage Schema (Recommended)', 'ez-translate'); ?></option>
                                            <option value="article" <?php selected($settings['landing_page_schema_type'], 'article'); ?>><?php esc_html_e('Article Schema', 'ez-translate'); ?></option>
                                            <option value="website" <?php selected($settings['landing_page_schema_type'], 'website'); ?>><?php esc_html_e('WebSite Schema', 'ez-translate'); ?></option>
                                        </select>
                                        <p class="description">
                                            <?php esc_html_e('Schema type for pages marked as landing pages. WebPage is recommended for navigation pages.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="homepage_schema_type"><?php esc_html_e('Homepage Schema', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <select id="homepage_schema_type" name="homepage_schema_type">
                                            <option value="website" <?php selected($settings['homepage_schema_type'], 'website'); ?>><?php esc_html_e('WebSite Schema (Recommended)', 'ez-translate'); ?></option>
                                            <option value="webpage" <?php selected($settings['homepage_schema_type'], 'webpage'); ?>><?php esc_html_e('WebPage Schema', 'ez-translate'); ?></option>
                                            <option value="article" <?php selected($settings['homepage_schema_type'], 'article'); ?>><?php esc_html_e('Article Schema', 'ez-translate'); ?></option>
                                        </select>
                                        <p class="description">
                                            <?php esc_html_e('Schema type for your homepage. WebSite is recommended as it includes search functionality.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="post_schema_type"><?php esc_html_e('Posts Schema', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <select id="post_schema_type" name="post_schema_type">
                                            <option value="article" <?php selected($settings['post_schema_type'], 'article'); ?>><?php esc_html_e('Article Schema (Recommended)', 'ez-translate'); ?></option>
                                            <option value="webpage" <?php selected($settings['post_schema_type'], 'webpage'); ?>><?php esc_html_e('WebPage Schema', 'ez-translate'); ?></option>
                                        </select>
                                        <p class="description">
                                            <?php esc_html_e('Schema type for blog posts. Article is recommended for editorial content.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="page_schema_type"><?php esc_html_e('Pages Schema', 'ez-translate'); ?></label>
                                    </th>
                                    <td>
                                        <select id="page_schema_type" name="page_schema_type">
                                            <option value="webpage" <?php selected($settings['page_schema_type'], 'webpage'); ?>><?php esc_html_e('WebPage Schema (Recommended)', 'ez-translate'); ?></option>
                                            <option value="article" <?php selected($settings['page_schema_type'], 'article'); ?>><?php esc_html_e('Article Schema', 'ez-translate'); ?></option>
                                        </select>
                                        <p class="description">
                                            <?php esc_html_e('Schema type for static pages. WebPage is recommended for informational pages.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Action Buttons -->
                        <div class="seo-metadata-actions">
                            <div class="button-group">
                                <?php submit_button(esc_html__('Save Settings', 'ez-translate'), 'primary', 'submit', false); ?>
                                <button type="button" id="enable-all" class="button button-secondary">
                                    <?php esc_html_e('Enable All', 'ez-translate'); ?>
                                </button>
                                <button type="button" id="disable-all" class="button button-secondary">
                                    <?php esc_html_e('Disable All', 'ez-translate'); ?>
                                </button>
                                <button type="button" id="reset-defaults" class="button button-link-delete">
                                    <?php esc_html_e('Reset to Defaults', 'ez-translate'); ?>
                                </button>
                            </div>
                        </div>

                    </form>
                </div>

                <!-- Current Status -->
                <div class="card" style="max-width: 1200px; width: 100%; margin-top: 20px;">
                    <h2><?php esc_html_e('Current Status', 'ez-translate'); ?></h2>
                    <div class="seo-metadata-status">
                        <?php
                        $enabled_count = 0;
                        $total_count = count($settings) - 2; // Exclude 'enabled' and 'override_other_plugins'

                        foreach ($settings as $key => $value) {
                            if ($key !== 'enabled' && $key !== 'override_other_plugins' && $key !== 'priority_level' && $value) {
                                $enabled_count++;
                            }
                        }
                        ?>

                        <div class="status-overview">
                            <div class="status-item <?php echo $settings['enabled'] ? 'enabled' : 'disabled'; ?>">
                                <span class="status-icon"><?php echo $settings['enabled'] ? '✅' : '❌'; ?></span>
                                <span class="status-text">
                                    <?php echo $settings['enabled'] ?
                                        esc_html__('SEO Metadata: ENABLED', 'ez-translate') :
                                        esc_html__('SEO Metadata: DISABLED', 'ez-translate'); ?>
                                </span>
                            </div>

                            <?php if ($settings['enabled']): ?>
                            <div class="status-item">
                                <span class="status-icon">📊</span>
                                <span class="status-text">
                                    <?php printf(
                                        esc_html__('Active features: %d of %d', 'ez-translate'),
                                        $enabled_count,
                                        $total_count
                                    ); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($settings['enabled']): ?>
                        <div class="active-features">
                            <h4><?php esc_html_e('Active Features:', 'ez-translate'); ?></h4>
                            <ul class="feature-list">
                                <?php
                                $feature_labels = array(
                                    'document_title' => __('Document Title', 'ez-translate'),
                                    'meta_description' => __('Meta Description', 'ez-translate'),
                                    'canonical_urls' => __('Canonical URLs', 'ez-translate'),
                                    'open_graph' => __('Open Graph', 'ez-translate'),
                                    'twitter_cards' => __('Twitter Cards', 'ez-translate'),
                                    'featured_images' => __('Featured Images', 'ez-translate'),
                                    'hreflang_tags' => __('Hreflang Tags', 'ez-translate'),
                                    'language_alternates' => __('Language Alternates', 'ez-translate'),
                                    'json_ld_schema' => __('JSON-LD Schema (Global)', 'ez-translate'),
                                    'json_ld_articles' => __('JSON-LD Articles', 'ez-translate'),
                                    'json_ld_landing_pages' => __('JSON-LD Landing Pages', 'ez-translate'),
                                    'json_ld_homepage' => __('JSON-LD Homepage', 'ez-translate'),
                                    'article_metadata' => __('Article Metadata', 'ez-translate')
                                );

                                foreach ($feature_labels as $key => $label) {
                                    if (isset($settings[$key]) && $settings[$key]) {
                                        echo '<li class="feature-enabled">✅ ' . esc_html($label) . '</li>';
                                    }
                                }
                                ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Compatibility Information -->
                <div class="card" style="max-width: 1200px; width: 100%; margin-top: 20px;">
                    <h2><?php esc_html_e('Compatibility with Other SEO Plugins', 'ez-translate'); ?></h2>
                    <div class="compatibility-info">
                        <p><?php esc_html_e('This configuration allows you to use EZ Translate alongside other SEO plugins:', 'ez-translate'); ?></p>

                        <div class="compatibility-examples">
                            <div class="example">
                                <h4>🔧 <?php esc_html_e('Example: Use with Yoast SEO', 'ez-translate'); ?></h4>
                                <ul>
                                    <li><?php esc_html_e('Disable "Document Title" and "Meta Description" in EZ Translate', 'ez-translate'); ?></li>
                                    <li><?php esc_html_e('Keep "Hreflang Tags" and "Open Graph" enabled for multilingual functionality', 'ez-translate'); ?></li>
                                    <li><?php esc_html_e('Yoast will handle titles and descriptions, EZ Translate will handle translations', 'ez-translate'); ?></li>
                                </ul>
                            </div>

                            <div class="example">
                                <h4>📱 <?php esc_html_e('Example: Use with RankMath', 'ez-translate'); ?></h4>
                                <ul>
                                    <li><?php esc_html_e('Disable "JSON-LD Schema" in EZ Translate if RankMath handles it', 'ez-translate'); ?></li>
                                    <li><?php esc_html_e('Keep "Twitter Cards" and "Open Graph" for translated content', 'ez-translate'); ?></li>
                                    <li><?php esc_html_e('Enable "Priority over other plugins" to avoid duplicates', 'ez-translate'); ?></li>
                                </ul>
                            </div>
                        </div>

                        <div class="warning-box">
                            <p><strong>⚠️ <?php esc_html_e('Important:', 'ez-translate'); ?></strong></p>
                            <p><?php esc_html_e('If you experience duplicate metadata, enable "Priority over other plugins" or disable the specific functions that are causing conflicts.', 'ez-translate'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
