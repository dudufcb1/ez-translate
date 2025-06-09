<?php
/**
 * Redirect Admin Interface for EZ Translate
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use EZTranslate\Logger;

/**
 * Redirect Admin class
 *
 * Handles administrative interface for redirect management
 *
 * @since 1.0.0
 */
class RedirectAdmin {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
        Logger::info('RedirectAdmin initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Handle form submissions
        add_action('admin_post_ez_translate_redirect_action', array($this, 'handle_redirect_action'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers
        add_action('wp_ajax_ez_translate_add_redirect', array($this, 'ajax_add_redirect'));
        add_action('wp_ajax_ez_translate_update_redirect', array($this, 'ajax_update_redirect'));
        add_action('wp_ajax_ez_translate_get_redirect', array($this, 'ajax_get_redirect'));
        add_action('wp_ajax_ez_translate_delete_redirect', array($this, 'ajax_delete_redirect'));
        add_action('wp_ajax_ez_translate_check_wp_redirects', array($this, 'ajax_check_wp_redirects'));
        add_action('wp_ajax_ez_translate_cleanup_redirects', array($this, 'ajax_cleanup_redirects'));
        add_action('wp_ajax_ez_translate_test_redirect_system', array($this, 'ajax_test_redirect_system'));

        // Admin post handlers
        add_action('admin_post_ez_translate_save_catchall_settings', array($this, 'handle_save_catchall_settings'));

        Logger::info('RedirectAdmin hooks initialized');
    }

    /**
     * Add admin menu page
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        add_submenu_page(
            'ez-translate',
            __('URL Redirections', 'ez-translate'),
            __('Redirections', 'ez-translate'),
            'manage_options',
            'ez-translate-redirects',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook_suffix Current admin page hook suffix
     * @since 1.0.0
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on our redirect admin page
        if ($hook_suffix !== 'ez-translate_page_ez-translate-redirects') {
            return;
        }

        wp_enqueue_style(
            'ez-translate-redirect-admin',
            EZ_TRANSLATE_PLUGIN_URL . 'assets/css/redirect-admin.css',
            array(),
            EZ_TRANSLATE_VERSION
        );

        wp_enqueue_script(
            'ez-translate-redirect-admin',
            EZ_TRANSLATE_PLUGIN_URL . 'assets/js/redirect-admin.js',
            array('jquery'),
            EZ_TRANSLATE_VERSION,
            true
        );

        wp_localize_script('ez-translate-redirect-admin', 'ezTranslateRedirect', array(
            'nonce' => wp_create_nonce('ez_translate_redirect_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this redirect?', 'ez-translate'),
                'confirm_bulk_delete' => __('Are you sure you want to delete the selected redirects?', 'ez-translate')
            )
        ));
    }

    /**
     * Render admin page
     *
     * @since 1.0.0
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ez-translate'));
        }

        // Handle messages
        $this->display_admin_notices();

        // Get redirects data
        $redirects = $this->get_redirects_for_display();
        $stats = $this->get_redirect_statistics();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('URL Redirections Management', 'ez-translate'); ?></h1>
            
            <div class="ez-translate-redirect-stats">
                <div class="stats-grid">
                    <div class="stat-box">
                        <h3><?php echo esc_html($stats['total']); ?></h3>
                        <p><?php esc_html_e('Total Redirects', 'ez-translate'); ?></p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo esc_html($stats['wp_auto']); ?></h3>
                        <p><?php esc_html_e('WordPress Auto', 'ez-translate'); ?></p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo esc_html($stats['manual']); ?></h3>
                        <p><?php esc_html_e('Manual Redirects', 'ez-translate'); ?></p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo esc_html($stats['changed']); ?></h3>
                        <p><?php esc_html_e('URL Changes', 'ez-translate'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Catch-All Redirect Settings -->
            <div class="ez-translate-catchall-settings">
                <h2><?php esc_html_e('Catch-All Redirect Settings', 'ez-translate'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Configure a fallback redirect for URLs that don\'t have specific redirects configured. This helps handle deleted pages, broken links, and other 404 errors. Note: Catch-all redirects work in real-time without creating database records, preventing potential security vulnerabilities from automated attacks.', 'ez-translate'); ?>
                </p>

                <?php
                $catchall_settings = get_option('ez_translate_catchall_settings', array(
                    'enabled' => false,
                    'redirect_type' => '301',
                    'destination_type' => 'page',
                    'destination_page_id' => '',
                    'destination_url' => '',
                    'exclude_patterns' => array()
                ));
                ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="catchall-settings-form">
                    <?php wp_nonce_field('ez_translate_catchall_settings', 'ez_translate_catchall_nonce'); ?>
                    <input type="hidden" name="action" value="ez_translate_save_catchall_settings">

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Catch-All Redirect', 'ez-translate'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="catchall_enabled" value="1"
                                           <?php checked($catchall_settings['enabled']); ?>>
                                    <?php esc_html_e('Enable automatic redirect for unhandled 404 errors', 'ez-translate'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, any 404 error that doesn\'t have a specific redirect will be automatically redirected to the destination below. Redirects are processed in real-time without creating database records.', 'ez-translate'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Redirect Type', 'ez-translate'); ?></th>
                            <td>
                                <select name="catchall_redirect_type">
                                    <option value="301" <?php selected($catchall_settings['redirect_type'], '301'); ?>>
                                        <?php esc_html_e('301 - Permanent Redirect', 'ez-translate'); ?>
                                    </option>
                                    <option value="302" <?php selected($catchall_settings['redirect_type'], '302'); ?>>
                                        <?php esc_html_e('302 - Temporary Redirect', 'ez-translate'); ?>
                                    </option>
                                    <option value="307" <?php selected($catchall_settings['redirect_type'], '307'); ?>>
                                        <?php esc_html_e('307 - Temporary (Preserve Method)', 'ez-translate'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Destination Type', 'ez-translate'); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="catchall_destination_type" value="page"
                                           <?php checked($catchall_settings['destination_type'], 'page'); ?>>
                                    <?php esc_html_e('Redirect to a specific page', 'ez-translate'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="catchall_destination_type" value="url"
                                           <?php checked($catchall_settings['destination_type'], 'url'); ?>>
                                    <?php esc_html_e('Redirect to a custom URL', 'ez-translate'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="catchall_destination_type" value="home"
                                           <?php checked($catchall_settings['destination_type'], 'home'); ?>>
                                    <?php esc_html_e('Redirect to homepage', 'ez-translate'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr id="catchall-page-section" style="<?php echo $catchall_settings['destination_type'] !== 'page' ? 'display: none;' : ''; ?>">
                            <th scope="row"><?php esc_html_e('Destination Page', 'ez-translate'); ?></th>
                            <td>
                                <select name="catchall_destination_page_id" class="regular-text">
                                    <option value=""><?php esc_html_e('Select a page...', 'ez-translate'); ?></option>
                                    <?php
                                    $pages = get_pages(array('post_status' => 'publish'));
                                    foreach ($pages as $page) {
                                        echo '<option value="' . esc_attr($page->ID) . '" ' .
                                             selected($catchall_settings['destination_page_id'], $page->ID, false) . '>' .
                                             esc_html($page->post_title) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Choose a page to redirect 404 errors to (e.g., a custom 404 page, contact page, or sitemap).', 'ez-translate'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr id="catchall-url-section" style="<?php echo $catchall_settings['destination_type'] !== 'url' ? 'display: none;' : ''; ?>">
                            <th scope="row"><?php esc_html_e('Destination URL', 'ez-translate'); ?></th>
                            <td>
                                <input type="url" name="catchall_destination_url" class="regular-text"
                                       value="<?php echo esc_attr($catchall_settings['destination_url']); ?>">
                                <p class="description">
                                    <?php esc_html_e('Enter a custom URL to redirect 404 errors to.', 'ez-translate'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Catch-All Settings', 'ez-translate'); ?>">
                    </p>
                </form>
            </div>

            <div class="ez-translate-redirect-actions">
                <button type="button" class="button" id="add-new-redirect">
                    <?php esc_html_e('Add New Redirect', 'ez-translate'); ?>
                </button>
                <button type="button" class="button" id="check-wp-redirects">
                    <?php esc_html_e('Check WordPress Redirects', 'ez-translate'); ?>
                </button>
                <button type="button" class="button" id="cleanup-old-redirects">
                    <?php esc_html_e('Cleanup Old Redirects', 'ez-translate'); ?>
                </button>
                <button type="button" class="button button-primary" id="test-redirect-system">
                    <?php esc_html_e('Test System', 'ez-translate'); ?>
                </button>
                <button type="button" class="button" id="debug-edit-system">
                    <?php esc_html_e('Debug Edit', 'ez-translate'); ?>
                </button>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ez_translate_redirect_bulk_action', 'ez_translate_redirect_nonce'); ?>
                <input type="hidden" name="action" value="ez_translate_redirect_action">
                <input type="hidden" name="bulk_action" value="">

                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action_select" id="bulk-action-selector-top">
                            <option value=""><?php esc_html_e('Bulk Actions', 'ez-translate'); ?></option>
                            <option value="delete"><?php esc_html_e('Delete', 'ez-translate'); ?></option>
                            <option value="change_type_301"><?php esc_html_e('Change to 301', 'ez-translate'); ?></option>
                            <option value="change_type_302"><?php esc_html_e('Change to 302', 'ez-translate'); ?></option>
                            <option value="change_type_410"><?php esc_html_e('Change to 410', 'ez-translate'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'ez-translate'); ?>">
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1">
                            </td>
                            <th class="manage-column"><?php esc_html_e('Old URL', 'ez-translate'); ?></th>
                            <th class="manage-column"><?php esc_html_e('New URL', 'ez-translate'); ?></th>
                            <th class="manage-column"><?php esc_html_e('Type', 'ez-translate'); ?></th>
                            <th class="manage-column"><?php esc_html_e('Change Type', 'ez-translate'); ?></th>
                            <th class="manage-column"><?php esc_html_e('WP Auto', 'ez-translate'); ?></th>
                            <th class="manage-column"><?php esc_html_e('Date', 'ez-translate'); ?></th>
                            <th class="manage-column"><?php esc_html_e('Actions', 'ez-translate'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($redirects)) : ?>
                            <tr>
                                <td colspan="8" class="no-items">
                                    <?php esc_html_e('No redirects found.', 'ez-translate'); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($redirects as $redirect) : ?>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" name="redirect_ids[]" value="<?php echo esc_attr($redirect->id); ?>">
                                    </th>
                                    <td class="old-url">
                                        <strong><?php echo esc_html($this->truncate_url($redirect->old_url)); ?></strong>
                                        <div class="row-actions">
                                            <span class="view">
                                                <a href="<?php echo esc_url($redirect->old_url); ?>" target="_blank">
                                                    <?php esc_html_e('Test', 'ez-translate'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="new-url">
                                        <?php if ($redirect->new_url) : ?>
                                            <a href="<?php echo esc_url($redirect->new_url); ?>" target="_blank">
                                                <?php echo esc_html($this->truncate_url($redirect->new_url)); ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="no-redirect"><?php esc_html_e('No redirect', 'ez-translate'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="redirect-type">
                                        <span class="redirect-type-badge redirect-type-<?php echo esc_attr($redirect->redirect_type); ?>">
                                            <?php echo esc_html($redirect->redirect_type); ?>
                                        </span>
                                    </td>
                                    <td class="change-type">
                                        <span class="change-type-badge change-type-<?php echo esc_attr($redirect->change_type); ?>">
                                            <?php echo esc_html(ucfirst($redirect->change_type)); ?>
                                        </span>
                                    </td>
                                    <td class="wp-auto">
                                        <?php if ($redirect->wp_auto_redirect) : ?>
                                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-minus" style="color: #ddd;"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="date">
                                        <?php echo esc_html(mysql2date('Y/m/d g:i a', $redirect->created_at)); ?>
                                    </td>
                                    <td class="actions">
                                        <button type="button" class="button button-small edit-redirect" 
                                                data-id="<?php echo esc_attr($redirect->id); ?>">
                                            <?php esc_html_e('Edit', 'ez-translate'); ?>
                                        </button>
                                        <button type="button" class="button button-small delete-redirect" 
                                                data-id="<?php echo esc_attr($redirect->id); ?>">
                                            <?php esc_html_e('Delete', 'ez-translate'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <!-- Add/Edit Redirect Modal -->
        <div id="add-redirect-modal" class="ez-translate-modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2 id="modal-title"><?php esc_html_e('Add New Redirect', 'ez-translate'); ?></h2>
                <form id="add-redirect-form">
                    <input type="hidden" name="redirect_id" value="">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Redirect Type', 'ez-translate'); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="redirect_method" value="manual" checked>
                                    <?php esc_html_e('Manual URL Entry', 'ez-translate'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="redirect_method" value="post_selection">
                                    <?php esc_html_e('Select from Existing Posts', 'ez-translate'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr id="manual-url-section">
                            <th scope="row"><?php esc_html_e('Old URL', 'ez-translate'); ?></th>
                            <td><input type="url" name="old_url" class="regular-text"></td>
                        </tr>
                        <tr id="manual-new-url-section">
                            <th scope="row"><?php esc_html_e('New URL', 'ez-translate'); ?></th>
                            <td><input type="url" name="new_url" class="regular-text"></td>
                        </tr>
                        <tr id="post-selection-section" style="display: none;">
                            <th scope="row"><?php esc_html_e('Select Post', 'ez-translate'); ?></th>
                            <td>
                                <select name="source_post_id" class="regular-text">
                                    <option value=""><?php esc_html_e('Select a post...', 'ez-translate'); ?></option>
                                    <?php
                                    $posts = get_posts(array(
                                        'numberposts' => 100,
                                        'post_status' => array('publish', 'draft', 'private', 'trash'),
                                        'post_type' => array('post', 'page'),
                                        'orderby' => 'date',
                                        'order' => 'DESC'
                                    ));
                                    foreach ($posts as $post) {
                                        $status_label = $post->post_status === 'trash' ? ' - TRASHED' : ' - ' . $post->post_status;
                                        echo '<option value="' . esc_attr($post->ID) . '">' .
                                             esc_html($post->post_title) . ' (' . esc_html($post->post_type) . esc_html($status_label) . ')</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e('Select the post that this redirect should point to. The redirect will automatically update if the post URL changes.', 'ez-translate'); ?></p>
                            </td>
                        </tr>
                        <tr id="destination-section" style="display: none;">
                            <th scope="row"><?php esc_html_e('Redirect To', 'ez-translate'); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="destination_type" value="post" checked>
                                    <?php esc_html_e('Another Post', 'ez-translate'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="destination_type" value="url">
                                    <?php esc_html_e('Custom URL', 'ez-translate'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="destination_type" value="gone">
                                    <?php esc_html_e('Gone (410)', 'ez-translate'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr id="destination-post-section" style="display: none;">
                            <th scope="row"><?php esc_html_e('Destination Post', 'ez-translate'); ?></th>
                            <td>
                                <select name="destination_post_id" class="regular-text">
                                    <option value=""><?php esc_html_e('Select destination post...', 'ez-translate'); ?></option>
                                    <?php
                                    foreach ($posts as $post) {
                                        // For destination, prefer published posts but show others too
                                        $status_indicator = '';
                                        if ($post->post_status !== 'publish') {
                                            $status_indicator = ' - ' . strtoupper($post->post_status);
                                        }
                                        echo '<option value="' . esc_attr($post->ID) . '">' .
                                             esc_html($post->post_title) . ' (' . esc_html($post->post_type) . esc_html($status_indicator) . ')</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr id="destination-url-section" style="display: none;">
                            <th scope="row"><?php esc_html_e('Destination URL', 'ez-translate'); ?></th>
                            <td><input type="url" name="destination_url" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('HTTP Status', 'ez-translate'); ?></th>
                            <td>
                                <select name="redirect_type">
                                    <option value="301"><?php esc_html_e('301 - Permanent Redirect', 'ez-translate'); ?></option>
                                    <option value="302"><?php esc_html_e('302 - Temporary Redirect', 'ez-translate'); ?></option>
                                    <option value="307"><?php esc_html_e('307 - Temporary (Preserve Method)', 'ez-translate'); ?></option>
                                    <option value="410"><?php esc_html_e('410 - Gone', 'ez-translate'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button-primary" id="submit-redirect" value="<?php esc_attr_e('Add Redirect', 'ez-translate'); ?>">
                        <button type="button" class="button cancel-modal"><?php esc_html_e('Cancel', 'ez-translate'); ?></button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Display admin notices
     *
     * @since 1.0.0
     */
    private function display_admin_notices() {
        // Only show messages on our admin page and if user has proper capabilities
        // Verify nonce for GET parameters to prevent CSRF
        if (isset($_GET['message']) && current_user_can('manage_options') &&
            isset($_GET['page']) && $_GET['page'] === 'ez-translate-redirects' &&
            isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ez_translate_admin_notice')) {

            $message = sanitize_text_field(wp_unslash($_GET['message']));
            $type = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : 'success';

            // Validate type to prevent XSS
            $allowed_types = array('success', 'error', 'warning', 'info');
            if (!in_array($type, $allowed_types)) {
                $type = 'success';
            }

            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Get redirects for display in admin table
     *
     * @return array Array of redirect objects
     * @since 1.0.0
     */
    private function get_redirects_for_display() {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
        // Admin panel query for custom redirects table - no cache needed for admin operations
        $redirects = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}ez_translate_redirects` ORDER BY created_at DESC LIMIT %d",
            50
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

        return $redirects ? $redirects : array();
    }

    /**
     * Get redirect statistics
     *
     * @return array Statistics data
     * @since 1.0.0
     */
    private function get_redirect_statistics() {
        // Use RedirectTracker if available
        if (class_exists('EZTranslate\RedirectTracker')) {
            $tracker = new RedirectTracker();
            return $tracker->get_redirect_stats();
        }

        // Fallback basic stats
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
        // Admin statistics for custom redirects table - no cache needed for admin dashboard
        return array(
            'total' => $wpdb->get_var(
                "SELECT COUNT(*) FROM `{$wpdb->prefix}ez_translate_redirects`"
            ),
            'wp_auto' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$wpdb->prefix}ez_translate_redirects` WHERE wp_auto_redirect = %d", 1
            )),
            'manual' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$wpdb->prefix}ez_translate_redirects` WHERE wp_auto_redirect = %d", 0
            )),
            'changed' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$wpdb->prefix}ez_translate_redirects` WHERE change_type = %s", 'changed'
            ))
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Truncate URL for display
     *
     * @param string $url URL to truncate
     * @param int    $length Maximum length
     * @return string Truncated URL
     * @since 1.0.0
     */
    private function truncate_url($url, $length = 60) {
        if (strlen($url) <= $length) {
            return $url;
        }

        return substr($url, 0, $length - 3) . '...';
    }

    /**
     * Handle redirect actions from admin forms
     *
     * @since 1.0.0
     */
    public function handle_redirect_action() {
        // Verify nonce
        if (!isset($_POST['ez_translate_redirect_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ez_translate_redirect_nonce'])), 'ez_translate_redirect_bulk_action')) {
            wp_die(esc_html__('Security check failed.', 'ez-translate'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'ez-translate'));
        }

        $action = isset($_POST['bulk_action']) ? sanitize_text_field(wp_unslash($_POST['bulk_action'])) : '';
        $redirect_ids = isset($_POST['redirect_ids']) ? array_map('intval', wp_unslash($_POST['redirect_ids'])) : array();

        $message = '';
        $type = 'success';

        switch ($action) {
            case 'delete':
                $deleted = $this->delete_redirects($redirect_ids);
                /* translators: %d: number of redirects deleted */
                $message = sprintf(__('%d redirects deleted successfully.', 'ez-translate'), $deleted);
                break;

            case 'change_type_301':
            case 'change_type_302':
            case 'change_type_410':
                $new_type = str_replace('change_type_', '', $action);
                $updated = $this->update_redirect_types($redirect_ids, $new_type);
                /* translators: %1$d: number of redirects updated, %2$s: redirect type */
                $message = sprintf(__('%1$d redirects updated to type %2$s.', 'ez-translate'), $updated, $new_type);
                break;

            default:
                $message = __('Invalid action.', 'ez-translate');
                $type = 'error';
        }

        // Redirect back with message
        $redirect_url = add_query_arg(array(
            'page' => 'ez-translate-redirects',
            'message' => urlencode($message),
            'type' => $type,
            '_wpnonce' => wp_create_nonce('ez_translate_admin_notice')
        ), admin_url('admin.php'));

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Delete redirects by IDs
     *
     * @param array $redirect_ids Array of redirect IDs
     * @return int Number of deleted redirects
     * @since 1.0.0
     */
    private function delete_redirects($redirect_ids) {
        if (empty($redirect_ids)) {
            return 0;
        }

        global $wpdb;

        // Sanitize IDs
        $sanitized_ids = array_map('intval', $redirect_ids);

        // Delete each redirect individually to avoid IN clause interpolation
        $deleted_count = 0;
        foreach ($sanitized_ids as $redirect_id) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
            // Admin delete operation on custom redirects table - no cache needed
            $result = $wpdb->delete(
                $wpdb->prefix . 'ez_translate_redirects',
                array('id' => $redirect_id),
                array('%d')
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

            if ($result !== false && $result > 0) {
                $deleted_count++;
            }
        }

        Logger::info('Redirects deleted via admin', array(
            'deleted_count' => $deleted_count,
            'redirect_ids' => $redirect_ids
        ));

        return $deleted_count;
    }

    /**
     * Update redirect types by IDs
     *
     * @param array  $redirect_ids Array of redirect IDs
     * @param string $new_type     New redirect type
     * @return int Number of updated redirects
     * @since 1.0.0
     */
    private function update_redirect_types($redirect_ids, $new_type) {
        if (empty($redirect_ids)) {
            return 0;
        }

        global $wpdb;

        // Sanitize inputs
        $sanitized_ids = array_map('intval', $redirect_ids);
        $new_type = sanitize_text_field($new_type);

        // Update each redirect individually to avoid IN clause interpolation
        $updated_count = 0;
        foreach ($sanitized_ids as $redirect_id) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
            // Admin update operation on custom redirects table - no cache needed
            $result = $wpdb->update(
                $wpdb->prefix . 'ez_translate_redirects',
                array('redirect_type' => $new_type),
                array('id' => $redirect_id),
                array('%s'),
                array('%d')
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

            if ($result !== false && $result > 0) {
                $updated_count++;
            }
        }

        Logger::info('Redirect types updated via admin', array(
            'updated_count' => $updated_count,
            'new_type' => $new_type,
            'redirect_ids' => $redirect_ids
        ));

        return $updated_count;
    }

    /**
     * AJAX handler for adding new redirect
     *
     * @since 1.0.0
     */
    public function ajax_add_redirect() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ez_translate_redirect_nonce')) {
            wp_die('Security check failed.');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        $method = isset($_POST['redirect_method']) ? sanitize_text_field(wp_unslash($_POST['redirect_method'])) : '';
        $redirect_type = isset($_POST['redirect_type']) ? sanitize_text_field(wp_unslash($_POST['redirect_type'])) : '';

        // Validate redirect type
        $valid_types = array('301', '302', '307', '410');
        if (!in_array($redirect_type, $valid_types)) {
            wp_send_json_error('Invalid redirect type.');
        }

        $redirect_data = array(
            'redirect_type' => $redirect_type,
            'change_type' => 'manual'
        );

        if ($method === 'manual') {
            // Manual URL entry
            $old_url = isset($_POST['old_url']) ? sanitize_url(wp_unslash($_POST['old_url'])) : '';
            $new_url = !empty($_POST['new_url']) ? sanitize_url(wp_unslash($_POST['new_url'])) : null;

            if (empty($old_url)) {
                wp_send_json_error('Old URL is required.');
            }

            $redirect_data['old_url'] = $old_url;
            $redirect_data['new_url'] = $new_url;

        } else {
            // Post selection method
            $source_post_id = isset($_POST['source_post_id']) ? intval(wp_unslash($_POST['source_post_id'])) : 0;
            $destination_type = isset($_POST['destination_type']) ? sanitize_text_field(wp_unslash($_POST['destination_type'])) : '';

            if ($source_post_id <= 0) {
                wp_send_json_error('Please select a source post.');
            }

            $source_post = get_post($source_post_id);
            if (!$source_post) {
                wp_send_json_error('Source post not found.');
            }

            $redirect_data['old_url'] = get_permalink($source_post);
            $redirect_data['post_id'] = $source_post_id;

            if ($destination_type === 'post') {
                $destination_post_id = isset($_POST['destination_post_id']) ? intval(wp_unslash($_POST['destination_post_id'])) : 0;
                if ($destination_post_id <= 0) {
                    wp_send_json_error('Please select a destination post.');
                }

                $destination_post = get_post($destination_post_id);
                if (!$destination_post || $destination_post->post_status !== 'publish') {
                    wp_send_json_error('Destination post not found or not published.');
                }

                $redirect_data['new_url'] = get_permalink($destination_post);
                $redirect_data['destination_post_id'] = $destination_post_id;

            } else if ($destination_type === 'url') {
                $destination_url = isset($_POST['destination_url']) ? sanitize_url(wp_unslash($_POST['destination_url'])) : '';
                if (empty($destination_url)) {
                    wp_send_json_error('Destination URL is required.');
                }

                $redirect_data['new_url'] = $destination_url;

            } else if ($destination_type === 'gone') {
                $redirect_data['new_url'] = null;
                $redirect_data['redirect_type'] = '410';
            }
        }

        // Add redirect using RedirectManager
        if (class_exists('EZTranslate\RedirectManager')) {
            $manager = new RedirectManager();
            $result = $manager->add_redirect_record($redirect_data);

            if ($result) {
                wp_send_json_success('Redirect added successfully.');
            } else {
                wp_send_json_error('Failed to add redirect.');
            }
        } else {
            wp_send_json_error('RedirectManager not available.');
        }
    }

    /**
     * AJAX handler for getting redirect data
     *
     * @since 1.0.0
     */
    public function ajax_get_redirect() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ez_translate_redirect_nonce')) {
            wp_send_json_error('Security check failed.');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        $redirect_id = isset($_POST['redirect_id']) ? intval(wp_unslash($_POST['redirect_id'])) : 0;

        if ($redirect_id <= 0) {
            wp_send_json_error('Invalid redirect ID: ' . $redirect_id);
        }

        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
        // Admin AJAX operations on custom redirects table - no cache needed for admin operations

        // First, check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->prefix . 'ez_translate_redirects'
        ));
        if (!$table_exists) {
            wp_send_json_error('Redirects table does not exist.');
        }

        // Try simple query first
        $redirect = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}ez_translate_redirects` WHERE id = %d",
            $redirect_id
        ));

        if (!$redirect) {
            // Check what redirects exist
            $existing_redirects = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM `{$wpdb->prefix}ez_translate_redirects` ORDER BY id DESC LIMIT %d",
                10
            ));
            $existing_ids = array_map(function($r) { return $r->id; }, $existing_redirects);

            wp_send_json_error('Redirect not found. ID: ' . $redirect_id . '. Existing IDs: ' . implode(', ', $existing_ids));
        }

        // Now get additional data
        $redirect_with_posts = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*,
                    sp.post_title as source_post_title,
                    dp.post_title as destination_post_title
             FROM `{$wpdb->prefix}ez_translate_redirects` r
             LEFT JOIN {$wpdb->posts} sp ON r.post_id = sp.ID
             LEFT JOIN {$wpdb->posts} dp ON r.destination_post_id = dp.ID
             WHERE r.id = %d",
            $redirect_id
        ));

        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

        if ($redirect_with_posts) {
            $redirect = $redirect_with_posts;
        }

        wp_send_json_success($redirect);
    }

    /**
     * AJAX handler for updating redirect
     *
     * @since 1.0.0
     */
    public function ajax_update_redirect() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ez_translate_redirect_nonce')) {
            wp_die('Security check failed.');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        $redirect_id = isset($_POST['redirect_id']) ? intval(wp_unslash($_POST['redirect_id'])) : 0;

        if ($redirect_id <= 0) {
            wp_send_json_error('Invalid redirect ID.');
        }

        $method = isset($_POST['redirect_method']) ? sanitize_text_field(wp_unslash($_POST['redirect_method'])) : '';
        $redirect_type = isset($_POST['redirect_type']) ? sanitize_text_field(wp_unslash($_POST['redirect_type'])) : '';

        // Validate redirect type
        $valid_types = array('301', '302', '307', '410');
        if (!in_array($redirect_type, $valid_types)) {
            wp_send_json_error('Invalid redirect type.');
        }

        $update_data = array(
            'redirect_type' => $redirect_type
        );

        if ($method === 'manual') {
            // Manual URL entry
            $old_url = isset($_POST['old_url']) ? sanitize_url(wp_unslash($_POST['old_url'])) : '';
            $new_url = !empty($_POST['new_url']) ? sanitize_url(wp_unslash($_POST['new_url'])) : null;

            if (empty($old_url)) {
                wp_send_json_error('Old URL is required.');
            }

            $update_data['old_url'] = $old_url;
            $update_data['new_url'] = $new_url;
            $update_data['post_id'] = null;
            $update_data['destination_post_id'] = null;

        } else {
            // Post selection method
            $source_post_id = isset($_POST['source_post_id']) ? intval(wp_unslash($_POST['source_post_id'])) : 0;
            $destination_type = isset($_POST['destination_type']) ? sanitize_text_field(wp_unslash($_POST['destination_type'])) : '';

            if ($source_post_id <= 0) {
                wp_send_json_error('Please select a source post.');
            }

            $source_post = get_post($source_post_id);
            if (!$source_post) {
                wp_send_json_error('Source post not found.');
            }

            $update_data['old_url'] = get_permalink($source_post);
            $update_data['post_id'] = $source_post_id;

            if ($destination_type === 'post') {
                $destination_post_id = isset($_POST['destination_post_id']) ? intval(wp_unslash($_POST['destination_post_id'])) : 0;
                if ($destination_post_id <= 0) {
                    wp_send_json_error('Please select a destination post.');
                }

                $destination_post = get_post($destination_post_id);
                if (!$destination_post || $destination_post->post_status !== 'publish') {
                    wp_send_json_error('Destination post not found or not published.');
                }

                $update_data['new_url'] = get_permalink($destination_post);
                $update_data['destination_post_id'] = $destination_post_id;

            } else if ($destination_type === 'url') {
                $destination_url = isset($_POST['destination_url']) ? sanitize_url(wp_unslash($_POST['destination_url'])) : '';
                if (empty($destination_url)) {
                    wp_send_json_error('Destination URL is required.');
                }

                $update_data['new_url'] = $destination_url;
                $update_data['destination_post_id'] = null;

            } else if ($destination_type === 'gone') {
                $update_data['new_url'] = null;
                $update_data['redirect_type'] = '410';
                $update_data['destination_post_id'] = null;
            }
        }

        // Update redirect in database
        global $wpdb;

        // Build format array dynamically based on update_data
        $format = array();
        foreach ($update_data as $key => $value) {
            if (in_array($key, array('post_id', 'destination_post_id'))) {
                $format[] = is_null($value) ? '%s' : '%d';
            } else {
                $format[] = '%s';
            }
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
        // Admin update operation on custom redirects table - no cache needed
        $result = $wpdb->update(
            $wpdb->prefix . 'ez_translate_redirects',
            $update_data,
            array('id' => $redirect_id),
            $format,
            array('%d')
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

        if ($result !== false) {
            wp_send_json_success('Redirect updated successfully.');
        } else {
            wp_send_json_error('Failed to update redirect.');
        }
    }

    /**
     * AJAX handler for deleting redirect
     *
     * @since 1.0.0
     */
    public function ajax_delete_redirect() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ez_translate_redirect_nonce')) {
            wp_die('Security check failed.');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        $redirect_id = isset($_POST['redirect_id']) ? intval(wp_unslash($_POST['redirect_id'])) : 0;

        if ($redirect_id <= 0) {
            wp_send_json_error('Invalid redirect ID.');
        }

        $deleted = $this->delete_redirects(array($redirect_id));

        if ($deleted > 0) {
            wp_send_json_success('Redirect deleted successfully.');
        } else {
            wp_send_json_error('Failed to delete redirect.');
        }
    }

    /**
     * AJAX handler for checking WordPress redirects
     *
     * @since 1.0.0
     */
    public function ajax_check_wp_redirects() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ez_translate_redirect_nonce')) {
            wp_die('Security check failed.');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        if (class_exists('EZTranslate\RedirectTracker')) {
            $tracker = new RedirectTracker();
            $results = $tracker->force_check_all_redirects();
            wp_send_json_success($results);
        } else {
            wp_send_json_error('RedirectTracker not available.');
        }
    }

    /**
     * AJAX handler for cleaning up old redirects
     *
     * @since 1.0.0
     */
    public function ajax_cleanup_redirects() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ez_translate_redirect_nonce')) {
            wp_die('Security check failed.');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        if (class_exists('EZTranslate\RedirectTracker')) {
            $tracker = new RedirectTracker();
            $deleted_count = $tracker->cleanup_old_redirects(90); // 90 days old
            wp_send_json_success($deleted_count);
        } else {
            wp_send_json_error('RedirectTracker not available.');
        }
    }

    /**
     * AJAX handler for testing redirect system
     *
     * @since 1.0.0
     */
    public function ajax_test_redirect_system() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ez_translate_redirect_nonce')) {
            wp_die('Security check failed.');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        $results = array(
            'created' => 0,
            'errors' => 0
        );

        try {
            // 1. Create test redirects
            $test_redirects = array(
                array(
                    'old_url' => home_url('/test-redirect-1-' . time()),
                    'new_url' => home_url('/new-test-redirect-1-' . time()),
                    'redirect_type' => '301',
                    'change_type' => 'test_system'
                ),
                array(
                    'old_url' => home_url('/test-redirect-2-' . time()),
                    'new_url' => home_url('/new-test-redirect-2-' . time()),
                    'redirect_type' => '302',
                    'change_type' => 'test_system'
                ),
                array(
                    'old_url' => home_url('/test-deleted-' . time()),
                    'new_url' => null,
                    'redirect_type' => '410',
                    'change_type' => 'test_deletion'
                )
            );

            foreach ($test_redirects as $test_data) {
                if (class_exists('EZTranslate\RedirectManager')) {
                    $manager = new RedirectManager();
                    $result = $manager->add_redirect_record($test_data);

                    if ($result) {
                        $results['created']++;
                    } else {
                        $results['errors']++;
                    }
                }
            }

            // 2. Test URL change simulation
            $post_id = wp_insert_post(array(
                'post_title' => 'Test Post for Redirect System - ' . time(),
                'post_content' => 'This is a test post to verify redirect system functionality.',
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_name' => 'test-redirect-system-' . time()
            ));

            if (!is_wp_error($post_id)) {
                // Simulate URL change
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_name' => 'test-redirect-system-updated-' . time()
                ));

                // Check if redirect was created
                global $wpdb;
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
                // Admin test operation on custom redirects table - no cache needed
                $redirect = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM `{$wpdb->prefix}ez_translate_redirects` WHERE post_id = %d AND change_type = %s ORDER BY created_at DESC LIMIT 1",
                    $post_id,
                    'changed'
                ));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

                if ($redirect) {
                    $results['created']++;
                }

                // Clean up test post
                wp_delete_post($post_id, true);
            }

            wp_send_json_success($results);

        } catch (\Exception $e) {
            wp_send_json_error('Test failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle saving catch-all redirect settings
     *
     * @since 1.0.0
     */
    public function handle_save_catchall_settings() {
        // Verify nonce
        if (!isset($_POST['ez_translate_catchall_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ez_translate_catchall_nonce'])), 'ez_translate_catchall_settings')) {
            wp_die(esc_html__('Security check failed.', 'ez-translate'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'ez-translate'));
        }

        // Sanitize and validate input
        $settings = array(
            'enabled' => isset($_POST['catchall_enabled']) && sanitize_text_field(wp_unslash($_POST['catchall_enabled'])) === '1',
            'redirect_type' => isset($_POST['catchall_redirect_type']) ? sanitize_text_field(wp_unslash($_POST['catchall_redirect_type'])) : '301',
            'destination_type' => isset($_POST['catchall_destination_type']) ? sanitize_text_field(wp_unslash($_POST['catchall_destination_type'])) : 'home',
            'destination_page_id' => isset($_POST['catchall_destination_page_id']) ? intval(wp_unslash($_POST['catchall_destination_page_id'])) : 0,
            'destination_url' => isset($_POST['catchall_destination_url']) ? sanitize_url(wp_unslash($_POST['catchall_destination_url'])) : '',
            'exclude_patterns' => array() // For future use
        );

        // Validate redirect type
        $valid_redirect_types = array('301', '302', '307');
        if (!in_array($settings['redirect_type'], $valid_redirect_types)) {
            $settings['redirect_type'] = '301';
        }

        // Validate destination type
        $valid_destination_types = array('page', 'url', 'home');
        if (!in_array($settings['destination_type'], $valid_destination_types)) {
            $settings['destination_type'] = 'home';
        }

        // Validate destination based on type
        if ($settings['destination_type'] === 'page') {
            if ($settings['destination_page_id'] <= 0) {
                $message = __('Please select a destination page.', 'ez-translate');
                $type = 'error';
            } else {
                $page = get_post($settings['destination_page_id']);
                if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') {
                    $message = __('Selected page is not valid or not published.', 'ez-translate');
                    $type = 'error';
                }
            }
        } elseif ($settings['destination_type'] === 'url') {
            if (empty($settings['destination_url'])) {
                $message = __('Please enter a destination URL.', 'ez-translate');
                $type = 'error';
            }
        }

        // Save settings if no errors
        if (!isset($message)) {
            $result = update_option('ez_translate_catchall_settings', $settings);

            if ($result !== false) {
                $message = __('Catch-all redirect settings saved successfully.', 'ez-translate');
                $type = 'success';

                Logger::info('Catch-all redirect settings updated', array(
                    'enabled' => $settings['enabled'],
                    'redirect_type' => $settings['redirect_type'],
                    'destination_type' => $settings['destination_type']
                ));
            } else {
                $message = __('Failed to save catch-all redirect settings.', 'ez-translate');
                $type = 'error';
            }
        }

        // Redirect back with message
        $redirect_url = add_query_arg(array(
            'page' => 'ez-translate-redirects',
            'message' => urlencode($message),
            'type' => $type
        ), admin_url('admin.php'));

        wp_redirect($redirect_url);
        exit;
    }
}
