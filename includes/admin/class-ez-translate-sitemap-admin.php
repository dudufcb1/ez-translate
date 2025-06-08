<?php
/**
 * Sitemap Admin Interface for EZ Translate
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use EZTranslate\Logger;
use EZTranslate\LanguageManager;

/**
 * Sitemap Admin class
 *
 * Handles the administrative interface for sitemap configuration
 *
 * @since 1.0.0
 */
class SitemapAdmin {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
        Logger::debug('SitemapAdmin initialized');
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
        add_action('admin_post_ez_translate_update_sitemap_settings', array($this, 'handle_settings_update'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers for cache management
        add_action('wp_ajax_ez_translate_clear_sitemap_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_ez_translate_cleanup_sitemap_cache', array($this, 'ajax_cleanup_cache'));
    }

    /**
     * Add sitemap submenu to EZ Translate menu
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        add_submenu_page(
            'ez-translate',
            __('Sitemap Settings', 'ez-translate'),
            __('Sitemap', 'ez-translate'),
            'manage_options',
            'ez-translate-sitemap',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our sitemap settings page
        if ($hook !== 'ez-translate_page_ez-translate-sitemap') {
            return;
        }

        wp_enqueue_style(
            'ez-translate-sitemap-admin',
            EZ_TRANSLATE_PLUGIN_URL . 'assets/css/sitemap-admin.css',
            array(),
            EZ_TRANSLATE_VERSION
        );

        wp_enqueue_script(
            'ez-translate-sitemap-admin',
            EZ_TRANSLATE_PLUGIN_URL . 'assets/js/sitemap-admin.js',
            array('jquery'),
            EZ_TRANSLATE_VERSION,
            true
        );
    }

    /**
     * Render the sitemap admin page
     *
     * @since 1.0.0
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ez-translate'));
        }

        $settings = $this->get_sitemap_settings();
        $languages = LanguageManager::get_enabled_languages();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php $this->show_admin_notices(); ?>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ez_translate_sitemap_settings', 'ez_translate_sitemap_nonce'); ?>
                <input type="hidden" name="action" value="ez_translate_update_sitemap_settings">
                
                <div class="ez-translate-admin-container">
                    
                    <!-- General Settings -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php esc_html_e('General Settings', 'ez-translate'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Enable Sitemap', 'ez-translate'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?>>
                                            <?php esc_html_e('Generate XML sitemaps for your multilingual content', 'ez-translate'); ?>
                                        </label>
                                        <p class="description">
                                            <?php esc_html_e('When enabled, EZ Translate will generate XML sitemaps for all your content in different languages.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Cache Duration', 'ez-translate'); ?></th>
                                    <td>
                                        <select name="cache_duration">
                                            <option value="3600" <?php selected($settings['cache_duration'], 3600); ?>><?php esc_html_e('1 Hour', 'ez-translate'); ?></option>
                                            <option value="21600" <?php selected($settings['cache_duration'], 21600); ?>><?php esc_html_e('6 Hours', 'ez-translate'); ?></option>
                                            <option value="43200" <?php selected($settings['cache_duration'], 43200); ?>><?php esc_html_e('12 Hours', 'ez-translate'); ?></option>
                                            <option value="86400" <?php selected($settings['cache_duration'], 86400); ?>><?php esc_html_e('24 Hours', 'ez-translate'); ?></option>
                                            <option value="604800" <?php selected($settings['cache_duration'], 604800); ?>><?php esc_html_e('1 Week', 'ez-translate'); ?></option>
                                        </select>
                                        <p class="description">
                                            <?php esc_html_e('How long to cache generated sitemaps before regenerating them.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Content Types -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php esc_html_e('Content Types', 'ez-translate'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Include in Sitemap', 'ez-translate'); ?></th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" name="post_types[]" value="post" <?php checked(in_array('post', $settings['post_types'])); ?>>
                                                <?php esc_html_e('Posts', 'ez-translate'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="post_types[]" value="page" <?php checked(in_array('page', $settings['post_types'])); ?>>
                                                <?php esc_html_e('Pages', 'ez-translate'); ?>
                                            </label>
                                        </fieldset>
                                        <p class="description">
                                            <?php esc_html_e('Select which content types to include in your sitemaps.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Taxonomies', 'ez-translate'); ?></th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input type="checkbox" name="taxonomies[]" value="category" <?php checked(in_array('category', $settings['taxonomies'])); ?>>
                                                <?php esc_html_e('Categories', 'ez-translate'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="taxonomies[]" value="post_tag" <?php checked(in_array('post_tag', $settings['taxonomies'])); ?>>
                                                <?php esc_html_e('Tags', 'ez-translate'); ?>
                                            </label>
                                        </fieldset>
                                        <p class="description">
                                            <?php esc_html_e('Select which taxonomies to include in your sitemaps.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Priority Settings -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php esc_html_e('Priority Settings', 'ez-translate'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Content Priorities', 'ez-translate'); ?></th>
                                    <td>
                                        <table class="widefat">
                                            <thead>
                                                <tr>
                                                    <th><?php esc_html_e('Content Type', 'ez-translate'); ?></th>
                                                    <th><?php esc_html_e('Priority', 'ez-translate'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><?php esc_html_e('Landing Pages', 'ez-translate'); ?></td>
                                                    <td>
                                                        <input type="number" name="priorities[landing_page]" value="<?php echo esc_attr($settings['priorities']['landing_page']); ?>" min="0" max="1" step="0.1" style="width: 80px;">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><?php esc_html_e('Pages', 'ez-translate'); ?></td>
                                                    <td>
                                                        <input type="number" name="priorities[page]" value="<?php echo esc_attr($settings['priorities']['page']); ?>" min="0" max="1" step="0.1" style="width: 80px;">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><?php esc_html_e('Posts', 'ez-translate'); ?></td>
                                                    <td>
                                                        <input type="number" name="priorities[post]" value="<?php echo esc_attr($settings['priorities']['post']); ?>" min="0" max="1" step="0.1" style="width: 80px;">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><?php esc_html_e('Categories', 'ez-translate'); ?></td>
                                                    <td>
                                                        <input type="number" name="priorities[category]" value="<?php echo esc_attr($settings['priorities']['category']); ?>" min="0" max="1" step="0.1" style="width: 80px;">
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><?php esc_html_e('Tags', 'ez-translate'); ?></td>
                                                    <td>
                                                        <input type="number" name="priorities[post_tag]" value="<?php echo esc_attr($settings['priorities']['post_tag']); ?>" min="0" max="1" step="0.1" style="width: 80px;">
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <p class="description">
                                            <?php esc_html_e('Set the priority for different content types (0.0 to 1.0). Higher values indicate more important content.', 'ez-translate'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Sitemap URLs -->
                    <?php if ($settings['enabled']): ?>
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php esc_html_e('Sitemap URLs', 'ez-translate'); ?></h2>
                        </div>
                        <div class="inside">
                            <p><?php esc_html_e('Your sitemaps are available at the following URLs:', 'ez-translate'); ?></p>
                            <?php $this->render_sitemap_urls($languages); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Cache Management -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php esc_html_e('Cache Management', 'ez-translate'); ?></h2>
                        </div>
                        <div class="inside">
                            <?php $this->render_cache_management(); ?>
                        </div>
                    </div>

                </div>

                <?php submit_button(esc_html__('Save Settings', 'ez-translate')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get sitemap settings with defaults
     *
     * @return array
     * @since 1.0.0
     */
    private function get_sitemap_settings() {
        $defaults = array(
            'enabled' => true,
            'post_types' => array('post', 'page'),
            'taxonomies' => array('category', 'post_tag'),
            'languages' => array(),
            'excluded_urls' => array(),
            'cache_duration' => 86400,
            'priorities' => array(
                'post' => 0.8,
                'page' => 0.9,
                'landing_page' => 1.0,
                'category' => 0.6,
                'post_tag' => 0.5
            )
        );

        $settings = get_option('ez_translate_sitemap_settings', array());
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Handle settings form submission
     *
     * @since 1.0.0
     */
    public function handle_settings_update() {
        // Verify nonce
        if (!isset($_POST['ez_translate_sitemap_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ez_translate_sitemap_nonce'])), 'ez_translate_sitemap_settings')) {
            wp_die(esc_html__('Security check failed.', 'ez-translate'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'ez-translate'));
        }

        $settings = array(
            'enabled' => isset($_POST['enabled']) ? true : false,
            'cache_duration' => isset($_POST['cache_duration']) ? intval($_POST['cache_duration']) : 3600,
            'post_types' => isset($_POST['post_types']) ? array_map('sanitize_text_field', wp_unslash($_POST['post_types'])) : array(),
            'taxonomies' => isset($_POST['taxonomies']) ? array_map('sanitize_text_field', wp_unslash($_POST['taxonomies'])) : array(),
            'priorities' => array()
        );

        // Sanitize priorities
        if (isset($_POST['priorities']) && is_array($_POST['priorities'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized field by field below
            $priorities_raw = wp_unslash($_POST['priorities']);
            foreach ($priorities_raw as $type => $priority) {
                $settings['priorities'][sanitize_text_field($type)] = floatval($priority);
            }
        }

        // Update settings
        $result = update_option('ez_translate_sitemap_settings', $settings);

        if ($result) {
            // Clear cache when settings change
            if (class_exists('EZTranslate\Sitemap\SitemapCache')) {
                require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-cache.php';
                \EZTranslate\Sitemap\SitemapCache::invalidate('all');
            }

            Logger::info('Sitemap settings updated', $settings);
            
            wp_redirect(add_query_arg(array(
                'page' => 'ez-translate-sitemap',
                'updated' => 'true'
            ), admin_url('admin.php')));
        } else {
            wp_redirect(add_query_arg(array(
                'page' => 'ez-translate-sitemap',
                'error' => 'save_failed'
            ), admin_url('admin.php')));
        }
        
        exit;
    }

    /**
     * Show admin notices
     *
     * @since 1.0.0
     */
    private function show_admin_notices() {
        // Only show notices for admin users
        if (current_user_can('manage_options')) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameters for admin messages only
            if (isset($_GET['updated']) && sanitize_text_field(wp_unslash($_GET['updated'])) === 'true') {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                     esc_html__('Sitemap settings saved successfully!', 'ez-translate') . '</p></div>';
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET parameters for admin messages only
            if (isset($_GET['error']) && sanitize_text_field(wp_unslash($_GET['error'])) === 'save_failed') {
                echo '<div class="notice notice-error is-dismissible"><p>' .
                     esc_html__('Failed to save sitemap settings. Please try again.', 'ez-translate') . '</p></div>';
            }
        }
    }

    /**
     * Render sitemap URLs section
     *
     * @param array $languages Available languages
     * @since 1.0.0
     */
    private function render_sitemap_urls($languages) {
        $site_url = get_site_url();

        echo '<div class="sitemap-urls">';

        // Main sitemap
        echo '<p><strong>' . esc_html__('Main Sitemap:', 'ez-translate') . '</strong></p>';
        echo '<p><a href="' . esc_url($site_url . '/sitemap.xml') . '" target="_blank">' .
             esc_url($site_url . '/sitemap.xml') . '</a></p>';

        if (!empty($languages)) {
            echo '<p><strong>' . esc_html__('Language-specific Sitemaps:', 'ez-translate') . '</strong></p>';
            echo '<ul>';

            foreach ($languages as $language) {
                echo '<li>';
                echo '<strong>' . esc_html($language['name']) . ' (' . esc_html($language['code']) . '):</strong><br>';
                echo '<a href="' . esc_url($site_url . '/sitemap-posts-' . $language['code'] . '.xml') . '" target="_blank">Posts</a> | ';
                echo '<a href="' . esc_url($site_url . '/sitemap-pages-' . $language['code'] . '.xml') . '" target="_blank">Pages</a>';
                echo '</li>';
            }

            echo '</ul>';
        }

        echo '</div>';
    }

    /**
     * Render cache management section
     *
     * @since 1.0.0
     */
    private function render_cache_management() {
        // Get cache statistics
        $stats = array('total_files' => 0, 'valid_files' => 0, 'expired_files' => 0, 'total_size' => 0);

        if (class_exists('EZTranslate\Sitemap\SitemapCache')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-cache.php';
            $stats = \EZTranslate\Sitemap\SitemapCache::get_cache_stats();
        }

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Cache Statistics', 'ez-translate') . '</th>';
        echo '<td>';
        echo '<p><strong>' . esc_html__('Total cached files:', 'ez-translate') . '</strong> ' . esc_html($stats['total_files']) . '</p>';
        echo '<p><strong>' . esc_html__('Valid files:', 'ez-translate') . '</strong> ' . esc_html($stats['valid_files']) . '</p>';
        echo '<p><strong>' . esc_html__('Expired files:', 'ez-translate') . '</strong> ' . esc_html($stats['expired_files']) . '</p>';
        echo '<p><strong>' . esc_html__('Total cache size:', 'ez-translate') . '</strong> ' . esc_html(size_format($stats['total_size'])) . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Cache Actions', 'ez-translate') . '</th>';
        echo '<td>';
        echo '<button type="button" class="button" onclick="ezTranslateClearCache()">' .
             esc_html__('Clear All Cache', 'ez-translate') . '</button> ';
        echo '<button type="button" class="button" onclick="ezTranslateCleanupCache()">' .
             esc_html__('Cleanup Old Files', 'ez-translate') . '</button>';
        echo '<p class="description">' .
             esc_html__('Clear cache to force regeneration of all sitemaps, or cleanup to remove only expired files.', 'ez-translate') .
             '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        // Add AJAX handlers
        echo '<script>
        function ezTranslateClearCache() {
            if (confirm("' . esc_js(__('Are you sure you want to clear all sitemap cache?', 'ez-translate')) . '")) {
                jQuery.post(ajaxurl, {
                    action: "ez_translate_clear_sitemap_cache",
                    nonce: "' . esc_js(wp_create_nonce('ez_translate_cache_action')) . '"
                }, function(response) {
                    if (response.success) {
                        alert("' . esc_js(__('Cache cleared successfully!', 'ez-translate')) . '");
                        location.reload();
                    } else {
                        alert("' . esc_js(__('Failed to clear cache.', 'ez-translate')) . '");
                    }
                });
            }
        }

        function ezTranslateCleanupCache() {
            jQuery.post(ajaxurl, {
                action: "ez_translate_cleanup_sitemap_cache",
                nonce: "' . esc_js(wp_create_nonce('ez_translate_cache_action')) . '"
            }, function(response) {
                if (response.success) {
                    alert("' . esc_js(__('Cleanup completed! Files removed: ', 'ez-translate')) . '" + response.data.files_removed);
                    location.reload();
                } else {
                    alert("' . esc_js(__('Failed to cleanup cache.', 'ez-translate')) . '");
                }
            });
        }
        </script>';
    }

    /**
     * AJAX handler for clearing sitemap cache
     *
     * @since 1.0.0
     */
    public function ajax_clear_cache() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ez_translate_cache_action')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $result = false;
        if (class_exists('EZTranslate\Sitemap\SitemapCache')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-cache.php';
            $files_deleted = \EZTranslate\Sitemap\SitemapCache::invalidate('all');
            $result = $files_deleted >= 0; // Even 0 is success (no files to delete)
        }

        if ($result) {
            wp_send_json_success(array('message' => 'Cache cleared successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to clear cache'));
        }
    }

    /**
     * AJAX handler for cleaning up old cache files
     *
     * @since 1.0.0
     */
    public function ajax_cleanup_cache() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ez_translate_cache_action')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $files_removed = 0;
        if (class_exists('EZTranslate\Sitemap\SitemapCache')) {
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/sitemap/class-ez-translate-sitemap-cache.php';
            $files_removed = \EZTranslate\Sitemap\SitemapCache::cleanup_old_files();
        }

        wp_send_json_success(array(
            'message' => 'Cleanup completed',
            'files_removed' => $files_removed
        ));
    }
}
