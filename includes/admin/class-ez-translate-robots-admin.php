<?php
/**
 * EZ Translate Robots Admin Class
 *
 * Handles admin interface for robots.txt management
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
 * Robots Admin class
 *
 * @since 1.0.0
 */
class RobotsAdmin {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
        \EZTranslate\Logger::info('Robots Admin class initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Add submenu page
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        
        // Handle form submissions
        add_action('admin_post_ez_translate_update_robots_settings', array($this, 'handle_settings_update'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Add robots submenu to EZ Translate menu
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        add_submenu_page(
            'ez-translate',
            __('Robots.txt Settings', 'ez-translate'),
            __('Robots.txt', 'ez-translate'),
            'manage_options',
            'ez-translate-robots',
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
        // Only load on our robots admin page
        if ($hook_suffix !== 'ez-translate_page_ez-translate-robots') {
            return;
        }

        // Enqueue WordPress admin styles
        wp_enqueue_style('wp-admin');
        
        // Add custom styles for robots admin
        $custom_css = '
            .ez-translate-robots-preview {
                background: #f9f9f9;
                border: 1px solid #ddd;
                padding: 15px;
                font-family: monospace;
                white-space: pre-wrap;
                max-height: 400px;
                overflow-y: auto;
            }
            .ez-translate-custom-rule {
                margin-bottom: 10px;
                padding: 10px;
                border: 1px solid #ddd;
                background: #f9f9f9;
            }
            .ez-translate-custom-rule input,
            .ez-translate-custom-rule select {
                margin-right: 10px;
            }
            .ez-robots-group {
                border: 1px solid #ddd;
                border-radius: 8px;
                margin-bottom: 20px;
                background: #fff;
                overflow: hidden;
            }
            .ez-robots-group-header {
                background: #f8f9fa;
                padding: 15px;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .ez-robots-group-title {
                font-size: 16px;
                font-weight: 600;
                margin: 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .ez-robots-group-actions {
                display: flex;
                gap: 8px;
            }
            .ez-robots-group-btn {
                padding: 4px 12px;
                font-size: 12px;
                border-radius: 4px;
                border: 1px solid #ccd0d4;
                background: #fff;
                cursor: pointer;
                transition: all 0.2s;
            }
            .ez-robots-group-btn:hover {
                background: #f0f0f1;
            }
            .ez-robots-group-btn.select-all {
                color: #00a32a;
                border-color: #00a32a;
            }
            .ez-robots-group-btn.select-none {
                color: #d63638;
                border-color: #d63638;
            }
            .ez-robots-group-content {
                padding: 20px;
            }
            .ez-robots-option {
                display: flex;
                align-items: flex-start;
                margin-bottom: 12px;
                padding: 8px;
                border-radius: 4px;
                transition: background 0.2s;
            }
            .ez-robots-option:hover {
                background: #f8f9fa;
            }
            .ez-robots-option input[type="checkbox"] {
                margin: 2px 12px 0 0;
                flex-shrink: 0;
            }
            .ez-robots-option-content {
                flex: 1;
            }
            .ez-robots-option-label {
                font-weight: 500;
                margin-bottom: 4px;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .ez-robots-option-desc {
                font-size: 13px;
                color: #666;
                line-height: 1.4;
            }
            .ez-robots-recommendation {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                margin-left: 8px;
            }
            .ez-robots-rec-recommended {
                background: #d1e7dd;
                color: #0f5132;
            }
            .ez-robots-rec-optional {
                background: #fff3cd;
                color: #664d03;
            }
            .ez-robots-rec-careful {
                background: #f8d7da;
                color: #721c24;
            }
            .ez-robots-warning {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
                padding: 8px 12px;
                margin-top: 8px;
                font-size: 12px;
                color: #856404;
            }
            .ez-robots-group-description {
                background: #f0f6fc;
                border: 1px solid #c3d9ff;
                border-radius: 4px;
                padding: 12px;
                margin-bottom: 15px;
                font-size: 13px;
                color: #0969da;
            }
        ';
        wp_add_inline_style('wp-admin', $custom_css);

        // Enqueue JavaScript for dynamic functionality
        wp_enqueue_script('jquery');
        
        $custom_js = '
            jQuery(document).ready(function($) {
                // Group selection functions
                function selectAllInGroup(groupName) {
                    $(".ez-robots-group[data-group=\"" + groupName + "\"] input[type=\"checkbox\"]").prop("checked", true);
                    updatePreview();
                }

                function selectNoneInGroup(groupName) {
                    $(".ez-robots-group[data-group=\"" + groupName + "\"] input[type=\"checkbox\"]").prop("checked", false);
                    updatePreview();
                }

                // Group button handlers
                $(document).on("click", ".ez-robots-group-btn.select-all", function() {
                    var groupName = $(this).closest(".ez-robots-group").data("group");
                    selectAllInGroup(groupName);
                });

                $(document).on("click", ".ez-robots-group-btn.select-none", function() {
                    var groupName = $(this).closest(".ez-robots-group").data("group");
                    selectNoneInGroup(groupName);
                });

                // Preset configurations
                $("#ez-robots-preset-blog").click(function() {
                    // Blog/News site preset
                    selectNoneInGroup("security"); // Start fresh
                    selectNoneInGroup("content");

                    // Security (all recommended)
                    selectAllInGroup("security");

                    // Content (selective for blogs)
                    $("input[name=\"default_rules[wp_uploads]\"]").prop("checked", false); // Allow images
                    $("input[name=\"default_rules[feed]\"]").prop("checked", false); // Allow feeds
                    $("input[name=\"default_rules[search]\"]").prop("checked", true); // Block search
                    $("input[name=\"default_rules[date_archives]\"]").prop("checked", true); // Block date archives
                    $("input[name=\"default_rules[author]\"]").prop("checked", false); // Allow authors

                    updatePreview();
                });

                $("#ez-robots-preset-ecommerce").click(function() {
                    // E-commerce preset
                    selectNoneInGroup("security");
                    selectNoneInGroup("content");

                    // Security (all except API)
                    selectAllInGroup("security");
                    $("input[name=\"default_rules[wp_json]\"]").prop("checked", false); // Allow API for integrations

                    // Content (minimal for products)
                    $("input[name=\"default_rules[wp_uploads]\"]").prop("checked", false); // Allow product images
                    $("input[name=\"default_rules[search]\"]").prop("checked", true); // Block search
                    $("input[name=\"default_rules[author]\"]").prop("checked", true); // Block authors
                    $("input[name=\"default_rules[date_archives]\"]").prop("checked", true); // Block date archives
                    $("input[name=\"default_rules[tag_archives]\"]").prop("checked", true); // Block tags

                    updatePreview();
                });

                $("#ez-robots-preset-portfolio").click(function() {
                    // Portfolio/Photography preset
                    selectNoneInGroup("security");
                    selectNoneInGroup("content");

                    // Security (all recommended)
                    selectAllInGroup("security");

                    // Content (allow visual content)
                    $("input[name=\"default_rules[wp_uploads]\"]").prop("checked", false); // Allow images
                    $("input[name=\"default_rules[attachment]\"]").prop("checked", false); // Allow attachment pages
                    $("input[name=\"default_rules[author]\"]").prop("checked", false); // Allow author page
                    $("input[name=\"default_rules[search]\"]").prop("checked", true); // Block search

                    updatePreview();
                });

                // Add new custom rule
                $("#add-custom-rule").click(function() {
                    var ruleHtml = $("#custom-rule-template").html();
                    $("#custom-rules-container").append(ruleHtml);
                });

                // Remove custom rule
                $(document).on("click", ".remove-custom-rule", function() {
                    $(this).closest(".ez-translate-custom-rule").remove();
                });

                // Update preview when settings change
                function updatePreview() {
                    // This would be enhanced to show real-time preview
                    console.log("Settings changed - preview would update here");
                }

                $("input, select, textarea").on("change", updatePreview);
            });
        ';
        wp_add_inline_script('jquery', $custom_js);
    }

    /**
     * Render the robots admin page
     *
     * @since 1.0.0
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ez-translate'));
        }

        // Get current settings
        $settings = \EZTranslate\Robots::get_robots_settings();

        // Handle success/error messages (only for admin users)
        $message = '';
        if (current_user_can('manage_options')) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameters for admin messages only
            if (isset($_GET['updated']) && sanitize_text_field(wp_unslash($_GET['updated'])) === 'true') {
                $message = '<div class="notice notice-success"><p>' . esc_html__('Robots.txt settings saved successfully!', 'ez-translate') . '</p></div>';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameters for admin messages only
            } elseif (isset($_GET['error'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameters for admin messages only
                $error_code = sanitize_text_field(wp_unslash($_GET['error']));
                $message = '<div class="notice notice-error"><p>' . esc_html__('Error saving settings: ', 'ez-translate') . esc_html($error_code) . '</p></div>';
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Robots.txt Settings', 'ez-translate'); ?></h1>

            <?php echo wp_kses_post($message); ?>

            <p><?php esc_html_e('Configure dynamic robots.txt generation for your multilingual site. This will override any existing robots.txt file.', 'ez-translate'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ez_translate_robots_settings', 'ez_translate_robots_nonce'); ?>
                <input type="hidden" name="action" value="ez_translate_update_robots_settings">

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Dynamic Robots.txt', 'ez-translate'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?>>
                                <?php esc_html_e('Enable dynamic robots.txt generation', 'ez-translate'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('When enabled, this will override any existing robots.txt file with dynamically generated content.', 'ez-translate'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" style="vertical-align: top; padding-top: 20px;"><?php esc_html_e('Default Protection Rules', 'ez-translate'); ?></th>
                        <td>
                            <p class="description" style="margin-bottom: 20px;"><?php esc_html_e('Configure which default WordPress protection rules to include. Use the group buttons for quick selection, or customize individual options.', 'ez-translate'); ?></p>

                            <!-- Quick Presets -->
                            <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
                                <h4 style="margin: 0 0 10px 0; color: #495057;"><?php esc_html_e('🚀 Quick Presets', 'ez-translate'); ?></h4>
                                <p style="margin: 0 0 12px 0; font-size: 13px; color: #6c757d;"><?php esc_html_e('Apply recommended configurations for different types of websites:', 'ez-translate'); ?></p>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <button type="button" id="ez-robots-preset-blog" class="button button-secondary">📰 <?php esc_html_e('Blog/News Site', 'ez-translate'); ?></button>
                                    <button type="button" id="ez-robots-preset-ecommerce" class="button button-secondary">🛍️ <?php esc_html_e('E-commerce', 'ez-translate'); ?></button>
                                    <button type="button" id="ez-robots-preset-portfolio" class="button button-secondary">🎨 <?php esc_html_e('Portfolio/Photography', 'ez-translate'); ?></button>
                                </div>
                            </div>

                            <!-- Security Group -->
                            <div class="ez-robots-group" data-group="security">
                                <div class="ez-robots-group-header">
                                    <h4 class="ez-robots-group-title">
                                        🔒 <?php esc_html_e('Core WordPress Security', 'ez-translate'); ?>
                                        <span class="ez-robots-recommendation ez-robots-rec-recommended"><?php esc_html_e('Recommended', 'ez-translate'); ?></span>
                                    </h4>
                                    <div class="ez-robots-group-actions">
                                        <button type="button" class="ez-robots-group-btn select-all"><?php esc_html_e('Select All', 'ez-translate'); ?></button>
                                        <button type="button" class="ez-robots-group-btn select-none"><?php esc_html_e('Select None', 'ez-translate'); ?></button>
                                    </div>
                                </div>
                                <div class="ez-robots-group-content">
                                    <div class="ez-robots-group-description">
                                        <strong><?php esc_html_e('💡 Recommendation:', 'ez-translate'); ?></strong> <?php esc_html_e('All these options are recommended for security. They protect WordPress core files and admin areas from search engine crawling.', 'ez-translate'); ?>
                                    </div>

                                    <?php $this->render_robots_option('wp_admin', $settings, '🛡️', __('WordPress Admin Area', 'ez-translate'), __('Blocks /wp-admin/ (allows admin-ajax.php for functionality)', 'ez-translate'), 'recommended'); ?>

                                    <?php $this->render_robots_option('wp_login', $settings, '🔐', __('Login Page', 'ez-translate'), __('Blocks /wp-login.php to prevent login page indexing', 'ez-translate'), 'recommended'); ?>

                                    <?php $this->render_robots_option('wp_includes', $settings, '📁', __('WordPress Core Files', 'ez-translate'), __('Blocks /wp-includes/ directory with WordPress core files', 'ez-translate'), 'recommended'); ?>

                                    <?php $this->render_robots_option('wp_plugins', $settings, '🔌', __('Plugin Files', 'ez-translate'), __('Blocks /wp-content/plugins/ directory', 'ez-translate'), 'recommended'); ?>

                                    <?php $this->render_robots_option('wp_themes', $settings, '🎨', __('Theme Files', 'ez-translate'), __('Blocks /wp-content/themes/ directory', 'ez-translate'), 'recommended'); ?>

                                    <?php $this->render_robots_option('wp_config', $settings, '⚙️', __('Configuration File', 'ez-translate'), __('Blocks /wp-config.php (critical security file)', 'ez-translate'), 'recommended'); ?>

                                    <?php $this->render_robots_option('xmlrpc', $settings, '📡', __('XML-RPC', 'ez-translate'), __('Blocks /xmlrpc.php (often targeted by attacks)', 'ez-translate'), 'recommended'); ?>

                                    <?php $this->render_robots_option('wp_cron', $settings, '⏰', __('WordPress Cron', 'ez-translate'), __('Blocks /wp-cron.php (scheduled tasks file)', 'ez-translate'), 'recommended'); ?>

                                    <?php $this->render_robots_option('readme_files', $settings, '📄', __('Readme & License Files', 'ez-translate'), __('Blocks readme.html, license.txt, changelog.txt', 'ez-translate'), 'recommended'); ?>
                                </div>
                            </div>

                            <!-- Content & SEO Group -->
                            <div class="ez-robots-group" data-group="content">
                                <div class="ez-robots-group-header">
                                    <h4 class="ez-robots-group-title">
                                        📄 <?php esc_html_e('Content & SEO Options', 'ez-translate'); ?>
                                        <span class="ez-robots-recommendation ez-robots-rec-optional"><?php esc_html_e('Customize', 'ez-translate'); ?></span>
                                    </h4>
                                    <div class="ez-robots-group-actions">
                                        <button type="button" class="ez-robots-group-btn select-all"><?php esc_html_e('Block All', 'ez-translate'); ?></button>
                                        <button type="button" class="ez-robots-group-btn select-none"><?php esc_html_e('Allow All', 'ez-translate'); ?></button>
                                    </div>
                                </div>
                                <div class="ez-robots-group-content">
                                    <div class="ez-robots-group-description">
                                        <strong><?php esc_html_e('⚠️ Important:', 'ez-translate'); ?></strong> <?php esc_html_e('These options affect SEO and functionality. Unchecked items will be indexed by search engines. Choose based on your site type and SEO strategy.', 'ez-translate'); ?>
                                    </div>

                                    <?php $this->render_robots_option('wp_uploads', $settings, '🖼️', __('Media & Images', 'ez-translate'), __('Blocks /wp-content/uploads/ - UNCHECKED = Images indexed by Google', 'ez-translate'), 'careful', __('⚠️ For image SEO, leave unchecked so Google can index your images', 'ez-translate')); ?>

                                    <?php $this->render_robots_option('wp_json', $settings, '🔌', __('REST API', 'ez-translate'), __('Blocks /wp-json/ - May affect Gutenberg, plugins, mobile apps', 'ez-translate'), 'careful', __('⚠️ May break Gutenberg editor, WooCommerce, forms, and many modern plugins', 'ez-translate')); ?>

                                    <?php $this->render_robots_option('feed', $settings, '📡', __('RSS Feeds', 'ez-translate'), __('Blocks /feed/ and comment feeds - UNCHECKED = Feeds indexed', 'ez-translate'), 'optional', __('💡 Usually better to allow feeds for subscribers and feed readers', 'ez-translate')); ?>

                                    <?php $this->render_robots_option('search', $settings, '🔍', __('Search Results', 'ez-translate'), __('Blocks /?s= and /search/ pages to avoid duplicate content', 'ez-translate'), 'recommended', __('✅ Recommended to prevent duplicate content issues', 'ez-translate')); ?>

                                    <?php $this->render_robots_option('author', $settings, '👤', __('Author Pages', 'ez-translate'), __('Blocks /author/ pages - Consider your content strategy', 'ez-translate'), 'optional'); ?>

                                    <?php $this->render_robots_option('date_archives', $settings, '📅', __('Date Archives', 'ez-translate'), __('Blocks /2024/ style date archives to reduce duplicate content', 'ez-translate'), 'optional', __('💡 Often blocked to avoid duplicate content, especially for news sites', 'ez-translate')); ?>

                                    <?php $this->render_robots_option('tag_archives', $settings, '🏷️', __('Tag Archives', 'ez-translate'), __('Blocks /tag/ pages - Depends on your tagging strategy', 'ez-translate'), 'optional'); ?>

                                    <?php $this->render_robots_option('attachment', $settings, '📎', __('Attachment Pages', 'ez-translate'), __('Blocks /attachment/ pages - UNCHECKED = Media pages indexed', 'ez-translate'), 'optional', __('💡 For portfolios, leave unchecked to show individual media pages', 'ez-translate')); ?>

                                    <?php $this->render_robots_option('trackback', $settings, '🔗', __('Trackbacks', 'ez-translate'), __('Blocks /trackback/ - Usually safe to block', 'ez-translate'), 'recommended'); ?>

                                    <?php $this->render_robots_option('private_pages', $settings, '🔒', __('Private Content', 'ez-translate'), __('Blocks /private/ - Custom private content areas', 'ez-translate'), 'recommended'); ?>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Include Sitemap', 'ez-translate'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_sitemap" value="1" <?php checked($settings['include_sitemap']); ?>>
                                <?php esc_html_e('Automatically include sitemap reference', 'ez-translate'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Adds "Sitemap: [your-site]/sitemap.xml" to robots.txt', 'ez-translate'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Custom Rules', 'ez-translate'); ?></h2>
                <p><?php esc_html_e('Add custom Allow/Disallow rules for specific user agents and paths.', 'ez-translate'); ?></p>

                <div id="custom-rules-container">
                    <?php if (!empty($settings['custom_rules'])): ?>
                        <?php foreach ($settings['custom_rules'] as $index => $rule): ?>
                            <div class="ez-translate-custom-rule">
                                <select name="custom_rules[<?php echo esc_attr($index); ?>][user_agent]">
                                    <option value="*" <?php selected($rule['user_agent'], '*'); ?>>All User Agents (*)</option>
                                    <option value="Googlebot" <?php selected($rule['user_agent'], 'Googlebot'); ?>>Googlebot</option>
                                    <option value="Bingbot" <?php selected($rule['user_agent'], 'Bingbot'); ?>>Bingbot</option>
                                    <option value="facebookexternalhit" <?php selected($rule['user_agent'], 'facebookexternalhit'); ?>>Facebook</option>
                                </select>

                                <select name="custom_rules[<?php echo esc_attr($index); ?>][directive]">
                                    <option value="Disallow" <?php selected($rule['directive'], 'Disallow'); ?>>Disallow</option>
                                    <option value="Allow" <?php selected($rule['directive'], 'Allow'); ?>>Allow</option>
                                </select>

                                <input type="text" name="custom_rules[<?php echo esc_attr($index); ?>][path]" value="<?php echo esc_attr($rule['path']); ?>" placeholder="/path/" style="width: 200px;">

                                <button type="button" class="button remove-custom-rule"><?php esc_html_e('Remove', 'ez-translate'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button type="button" id="add-custom-rule" class="button"><?php esc_html_e('Add Custom Rule', 'ez-translate'); ?></button>

                <h2><?php esc_html_e('Additional Content', 'ez-translate'); ?></h2>
                <p><?php esc_html_e('Add any additional content to be included in robots.txt (crawl-delay, host directive, etc.)', 'ez-translate'); ?></p>
                
                <textarea name="additional_content" rows="5" cols="80" class="large-text"><?php echo esc_textarea($settings['additional_content']); ?></textarea>

                <?php submit_button(__('Save Robots.txt Settings', 'ez-translate')); ?>
            </form>

            <!-- Preview Section -->
            <h2><?php esc_html_e('Current Robots.txt Preview', 'ez-translate'); ?></h2>
            <div class="ez-translate-robots-preview">
                <?php echo esc_html($this->generate_preview($settings)); ?>
            </div>

            <!-- Template for new custom rules -->
            <script type="text/html" id="custom-rule-template">
                <div class="ez-translate-custom-rule">
                    <select name="custom_rules[new][user_agent]">
                        <option value="*">All User Agents (*)</option>
                        <option value="Googlebot">Googlebot</option>
                        <option value="Bingbot">Bingbot</option>
                        <option value="facebookexternalhit">Facebook</option>
                    </select>

                    <select name="custom_rules[new][directive]">
                        <option value="Disallow">Disallow</option>
                        <option value="Allow">Allow</option>
                    </select>

                    <input type="text" name="custom_rules[new][path]" placeholder="/path/" style="width: 200px;">

                    <button type="button" class="button remove-custom-rule"><?php esc_html_e('Remove', 'ez-translate'); ?></button>
                </div>
            </script>
        </div>
        <?php
    }

    /**
     * Generate preview of robots.txt content
     *
     * @param array $settings Current settings
     * @return string Preview content
     * @since 1.0.0
     */
    private function generate_preview($settings) {
        if (!$settings['enabled']) {
            return __('Dynamic robots.txt is disabled. Default WordPress robots.txt will be used.', 'ez-translate');
        }

        // Generate preview using the same logic as the actual robots.txt
        $robots_content = '';

        // Add default rules if any are enabled
        if (!empty($settings['default_rules']) && is_array($settings['default_rules'])) {
            $robots_content .= $this->get_default_rules_preview($settings['default_rules']);
        }

        // Add custom rules
        if (!empty($settings['custom_rules'])) {
            $robots_content .= $this->generate_custom_rules_preview($settings['custom_rules']);
        }

        // Add sitemap if enabled
        if ($settings['include_sitemap']) {
            $robots_content .= "Sitemap: " . home_url('/sitemap.xml') . "\n\n";
        }

        // Add additional content if provided
        if (!empty($settings['additional_content'])) {
            $robots_content .= "\n" . $settings['additional_content'] . "\n";
        }

        return !empty($robots_content) ? $robots_content : __('No content configured.', 'ez-translate');
    }

    /**
     * Get default rules for preview based on configuration
     *
     * @param array $default_rules_config Configuration for default rules
     * @return string Default rules content
     * @since 1.0.0
     */
    private function get_default_rules_preview($default_rules_config) {
        $rules = "User-agent: *\n";

        // Use the same logic as the main class
        if (!empty($default_rules_config['wp_admin'])) {
            $rules .= "Disallow: /wp-admin/\n";
            $rules .= "Allow: /wp-admin/admin-ajax.php\n";
        }

        if (!empty($default_rules_config['wp_login'])) {
            $rules .= "Disallow: /wp-login.php\n";
        }

        if (!empty($default_rules_config['wp_includes'])) {
            $rules .= "Disallow: /wp-includes/\n";
        }

        if (!empty($default_rules_config['wp_plugins'])) {
            $rules .= "Disallow: /wp-content/plugins/\n";
        }

        if (!empty($default_rules_config['wp_themes'])) {
            $rules .= "Disallow: /wp-content/themes/\n";
        }

        if (!empty($default_rules_config['wp_uploads'])) {
            $rules .= "Disallow: /wp-content/uploads/\n";
        }

        if (!empty($default_rules_config['wp_config'])) {
            $rules .= "Disallow: /wp-config.php\n";
        }

        if (!empty($default_rules_config['xmlrpc'])) {
            $rules .= "Disallow: /xmlrpc.php\n";
        }

        if (!empty($default_rules_config['wp_json'])) {
            $rules .= "Disallow: /wp-json/\n";
        }

        if (!empty($default_rules_config['wp_cron'])) {
            $rules .= "Disallow: /wp-cron.php\n";
        }

        if (!empty($default_rules_config['feed'])) {
            $rules .= "Disallow: /feed/\n";
            $rules .= "Disallow: /*/feed/\n";
            $rules .= "Disallow: /comments/feed/\n";
        }

        if (!empty($default_rules_config['search'])) {
            $rules .= "Disallow: /?s=\n";
            $rules .= "Disallow: /search/\n";
        }

        if (!empty($default_rules_config['author'])) {
            $rules .= "Disallow: /author/\n";
        }

        if (!empty($default_rules_config['date_archives'])) {
            $rules .= "Disallow: /20*/\n";
        }

        if (!empty($default_rules_config['tag_archives'])) {
            $rules .= "Disallow: /tag/\n";
        }

        if (!empty($default_rules_config['attachment'])) {
            $rules .= "Disallow: /attachment/\n";
        }

        if (!empty($default_rules_config['trackback'])) {
            $rules .= "Disallow: /trackback/\n";
            $rules .= "Disallow: /*/trackback/\n";
        }

        if (!empty($default_rules_config['private_pages'])) {
            $rules .= "Disallow: /private/\n";
        }

        if (!empty($default_rules_config['readme_files'])) {
            $rules .= "Disallow: /readme.html\n";
            $rules .= "Disallow: /license.txt\n";
            $rules .= "Disallow: /readme.txt\n";
            $rules .= "Disallow: /changelog.txt\n";
        }

        $rules .= "\n";
        return $rules;
    }

    /**
     * Generate custom rules preview
     *
     * @param array $custom_rules Array of custom rules
     * @return string Generated custom rules content
     * @since 1.0.0
     */
    private function generate_custom_rules_preview($custom_rules) {
        $content = '';
        $current_user_agent = '';

        foreach ($custom_rules as $rule) {
            if (!isset($rule['user_agent']) || !isset($rule['directive']) || !isset($rule['path'])) {
                continue;
            }

            // Add user-agent line if it changed
            if ($current_user_agent !== $rule['user_agent']) {
                if (!empty($current_user_agent)) {
                    $content .= "\n";
                }
                $content .= "User-agent: " . $rule['user_agent'] . "\n";
                $current_user_agent = $rule['user_agent'];
            }

            // Add directive
            $content .= $rule['directive'] . ": " . $rule['path'] . "\n";
        }

        if (!empty($content)) {
            $content .= "\n";
        }

        return $content;
    }

    /**
     * Handle settings form submission
     *
     * @since 1.0.0
     */
    public function handle_settings_update() {
        // Verify nonce
        if (!isset($_POST['ez_translate_robots_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ez_translate_robots_nonce'])), 'ez_translate_robots_settings')) {
            wp_die(esc_html__('Security check failed. Please try again.', 'ez-translate'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'ez-translate'));
        }

        // Prepare settings data
        $settings = array(
            'enabled' => isset($_POST['enabled']),
            'include_sitemap' => isset($_POST['include_sitemap']),
            'custom_rules' => array(),
            'additional_content' => isset($_POST['additional_content']) ? sanitize_textarea_field(wp_unslash($_POST['additional_content'])) : ''
        );

        // Process default rules (granular configuration)
        $settings['default_rules'] = array();
        $valid_rules = array(
            'wp_admin', 'wp_login', 'wp_includes', 'wp_plugins', 'wp_themes',
            'wp_uploads', 'readme_files', 'wp_config', 'xmlrpc', 'wp_json',
            'feed', 'trackback', 'wp_cron', 'search', 'author', 'date_archives',
            'tag_archives', 'attachment', 'private_pages'
        );

        foreach ($valid_rules as $rule) {
            $settings['default_rules'][$rule] = isset($_POST['default_rules'][$rule]);
        }

        // Process custom rules
        if (isset($_POST['custom_rules']) && is_array($_POST['custom_rules'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized field by field below
            $custom_rules_raw = wp_unslash($_POST['custom_rules']);
            foreach ($custom_rules_raw as $rule) {
                if (is_array($rule) && !empty($rule['path'])) {
                    $settings['custom_rules'][] = array(
                        'user_agent' => isset($rule['user_agent']) ? sanitize_text_field($rule['user_agent']) : '*',
                        'directive' => isset($rule['directive']) ? sanitize_text_field($rule['directive']) : 'Disallow',
                        'path' => sanitize_text_field($rule['path'])
                    );
                }
            }
        }

        // Update settings
        $result = \EZTranslate\Robots::update_robots_settings($settings);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg(array(
                'page' => 'ez-translate-robots',
                'error' => $result->get_error_code()
            ), admin_url('admin.php')));
        } else {
            wp_redirect(add_query_arg(array(
                'page' => 'ez-translate-robots',
                'updated' => 'true'
            ), admin_url('admin.php')));
        }

        exit;
    }

    /**
     * Render a robots option with consistent styling
     *
     * @param string $option_name The option name
     * @param array $settings Current settings
     * @param string $icon Icon for the option
     * @param string $label Label text
     * @param string $description Description text
     * @param string $recommendation Recommendation type (recommended, optional, careful)
     * @param string $warning Optional warning text
     * @since 1.0.0
     */
    private function render_robots_option($option_name, $settings, $icon, $label, $description, $recommendation = 'optional', $warning = '') {
        $checked = !empty($settings['default_rules'][$option_name]);
        $rec_class = 'ez-robots-rec-' . $recommendation;

        $rec_text = '';
        switch ($recommendation) {
            case 'recommended':
                $rec_text = __('Recommended', 'ez-translate');
                break;
            case 'careful':
                $rec_text = __('Be Careful', 'ez-translate');
                break;
            case 'optional':
                $rec_text = __('Optional', 'ez-translate');
                break;
        }

        ?>
        <div class="ez-robots-option">
            <input type="checkbox" name="default_rules[<?php echo esc_attr($option_name); ?>]" value="1" <?php checked($checked); ?> id="ez_robots_<?php echo esc_attr($option_name); ?>">
            <div class="ez-robots-option-content">
                <div class="ez-robots-option-label">
                    <label for="ez_robots_<?php echo esc_attr($option_name); ?>">
                        <?php echo wp_kses_post($icon); ?> <?php echo esc_html($label); ?>
                    </label>
                    <?php if ($rec_text): ?>
                        <span class="ez-robots-recommendation <?php echo esc_attr($rec_class); ?>"><?php echo esc_html($rec_text); ?></span>
                    <?php endif; ?>
                </div>
                <div class="ez-robots-option-desc">
                    <?php echo esc_html($description); ?>
                    <?php if ($warning): ?>
                        <div class="ez-robots-warning">
                            <?php echo esc_html($warning); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
