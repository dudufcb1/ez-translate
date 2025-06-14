<?php
/**
 * Redirect Manager for EZ Translate
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
 * Redirect Manager class
 *
 * Handles URL redirection management, tracking, and database operations
 *
 * @since 1.0.0
 */
class RedirectManager {

    /**
     * Database table name for redirects
     *
     * @var string
     * @since 1.0.0
     */
    private static $table_name = null;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_database();
        $this->init_hooks();
//         Logger::info('RedirectManager initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // URL change tracking
        add_action('post_updated', array($this, 'track_url_changes'), 10, 3);

        // Content deletion tracking
        add_action('before_delete_post', array($this, 'track_content_deletion'));

        // Handle redirections in real-time
        add_action('template_redirect', array($this, 'handle_redirections'), 5);

        // Update linked redirects when posts change
        add_action('post_updated', array($this, 'update_linked_redirects'), 15, 3);

//         Logger::info('RedirectManager hooks initialized');
    }

    /**
     * Initialize database table name
     *
     * @since 1.0.0
     */
    private function init_database() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'ez_translate_redirects';
    }



    /**
     * Track URL changes when posts are updated
     *
     * @param int     $post_id     Post ID
     * @param WP_Post $post_after  Post object after update
     * @param WP_Post $post_before Post object before update
     * @since 1.0.0
     */
    public function track_url_changes($post_id, $post_after, $post_before) {
        // Handle post moved to trash
        if ($post_before->post_status === 'publish' && $post_after->post_status === 'trash') {
            $this->track_post_trashed($post_id, $post_before);
            return;
        }

        // Handle post restored from trash
        if ($post_before->post_status === 'trash' && $post_after->post_status === 'publish') {
            $this->track_post_restored($post_id, $post_after);
            return;
        }

        // Only track URL changes for published posts
        if ($post_after->post_status !== 'publish' || $post_before->post_status !== 'publish') {
            return;
        }

        // Check if slug actually changed
        if ($post_after->post_name === $post_before->post_name) {
            return;
        }

        $old_url = get_permalink($post_before);
        $new_url = get_permalink($post_after);

        // Don't track if URLs are the same (shouldn't happen, but safety check)
        if ($old_url === $new_url) {
            return;
        }

        $this->add_redirect_record(array(
            'old_url' => $old_url,
            'new_url' => $new_url,
            'redirect_type' => '301',
            'change_type' => 'changed',
            'post_id' => $post_id
        ));

        Logger::info('URL change tracked', array(
            'post_id' => $post_id,
            'old_url' => $old_url,
            'new_url' => $new_url,
            'post_title' => $post_after->post_title
        ));
    }

    /**
     * Track post moved to trash
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object before trashing
     * @since 1.0.0
     */
    private function track_post_trashed($post_id, $post) {
        $old_url = get_permalink($post);

        $this->add_redirect_record(array(
            'old_url' => $old_url,
            'new_url' => null,
            'redirect_type' => '410', // Gone
            'change_type' => 'trashed',
            'post_id' => $post_id
        ));

        Logger::info('Post moved to trash tracked', array(
            'post_id' => $post_id,
            'old_url' => $old_url,
            'post_title' => $post->post_title
        ));
    }

    /**
     * Track post restored from trash
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object after restoration
     * @since 1.0.0
     */
    private function track_post_restored($post_id, $post) {
        // Remove any existing trash redirect for this post
        global $wpdb;
        $table_name = $wpdb->prefix . 'ez_translate_redirects';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
        // Delete operation on custom redirects table - no cache needed for CRUD operations
        $deleted = $wpdb->delete(
            $table_name,
            array(
                'post_id' => $post_id,
                'change_type' => 'trashed'
            ),
            array('%d', '%s')
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

        if ($deleted) {
            Logger::info('Post restored from trash - redirect removed', array(
                'post_id' => $post_id,
                'post_title' => $post->post_title,
                'redirects_removed' => $deleted
            ));
        }
    }

    /**
     * Track content deletion
     *
     * @param int $post_id Post ID being deleted
     * @since 1.0.0
     */
    public function track_content_deletion($post_id) {
        $post = get_post($post_id);

        // Only track if post exists and was published or trashed
        if (!$post || !in_array($post->post_status, array('publish', 'trash'))) {
            return;
        }

        $old_url = get_permalink($post);

        $this->add_redirect_record(array(
            'old_url' => $old_url,
            'new_url' => null,
            'redirect_type' => '410', // Gone permanently
            'change_type' => 'deleted_permanently',
            'post_id' => $post_id
        ));

        Logger::info('Content permanently deleted tracked', array(
            'post_id' => $post_id,
            'old_url' => $old_url,
            'post_title' => $post->post_title,
            'previous_status' => $post->post_status
        ));
    }

    /**
     * Add redirect record to database
     *
     * @param array $data Redirect data
     * @return int|false Insert ID on success, false on failure
     * @since 1.0.0
     */
    public function add_redirect_record($data) {
        global $wpdb;

        $defaults = array(
            'old_url' => '',
            'new_url' => null,
            'redirect_type' => '301',
            'change_type' => 'manual',
            'post_id' => null,
            'wp_auto_redirect' => 0
        );

        $data = wp_parse_args($data, $defaults);

        // Validate required fields
        if (empty($data['old_url'])) {
            Logger::error('Cannot add redirect record: old_url is required');
            return false;
        }

        // Sanitize URLs
        $data['old_url'] = esc_url_raw($data['old_url']);
        if (!empty($data['new_url'])) {
            $data['new_url'] = esc_url_raw($data['new_url']);
        }

        // Validate redirect type
        $valid_types = array('301', '302', '307', '410');
        if (!in_array($data['redirect_type'], $valid_types)) {
            $data['redirect_type'] = '301';
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // Insert operation on custom redirects table - no cache needed for CRUD operations
        $result = $wpdb->insert(
            self::$table_name,
            array(
                'old_url' => $data['old_url'],
                'new_url' => $data['new_url'],
                'redirect_type' => $data['redirect_type'],
                'change_type' => sanitize_text_field($data['change_type']),
                'post_id' => intval($data['post_id']),
                'wp_auto_redirect' => intval($data['wp_auto_redirect'])
            ),
            array('%s', '%s', '%s', '%s', '%d', '%d')
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

        if ($result === false) {
            Logger::error('Failed to add redirect record', array(
                'data' => $data,
                'error' => $wpdb->last_error
            ));
            return false;
        }

        $insert_id = $wpdb->insert_id;
        Logger::info('Redirect record added successfully', array(
            'id' => $insert_id,
            'old_url' => $data['old_url'],
            'new_url' => $data['new_url'],
            'type' => $data['redirect_type']
        ));

        return $insert_id;
    }

    /**
     * Handle redirections in real-time
     *
     * @since 1.0.0
     */
    public function handle_redirections() {
        // Only handle 404 errors
        if (!is_404()) {
            return;
        }

        $current_url = $this->get_current_url();
        $redirect = $this->get_redirect_for_url($current_url);

        if (!$redirect) {
            return;
        }

        // Handle different redirect types
        switch ($redirect->redirect_type) {
            case '301':
                if (!empty($redirect->new_url)) {
                    wp_redirect($redirect->new_url, 301);
                    exit;
                }
                break;
            
            case '302':
                if (!empty($redirect->new_url)) {
                    wp_redirect($redirect->new_url, 302);
                    exit;
                }
                break;
            
            case '307':
                if (!empty($redirect->new_url)) {
                    wp_redirect($redirect->new_url, 307);
                    exit;
                }
                break;
            
            case '410':
                // Return 410 Gone
                status_header(410);
                include(get_query_template('404'));
                exit;
        }

        Logger::info('Redirect applied', array(
            'old_url' => $current_url,
            'new_url' => $redirect->new_url,
            'type' => $redirect->redirect_type
        ));
    }

    /**
     * Get current URL
     *
     * @return string Current URL
     * @since 1.0.0
     */
    private function get_current_url() {
        $protocol = is_ssl() ? 'https://' : 'http://';

        // Sanitize and validate $_SERVER variables
        $http_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        return $protocol . $http_host . $request_uri;
    }

    /**
     * Get redirect record for URL
     *
     * @param string $url URL to check
     * @return object|null Redirect record or null
     * @since 1.0.0
     */
    public function get_redirect_for_url($url) {
        global $wpdb;

        // Check cache first - critical for 404 performance
        $cache_key = 'ez_translate_redirect_' . md5($url);
        $redirect = wp_cache_get($cache_key, 'ez_translate');

        if ($redirect !== false) {
            return $redirect === 'none' ? null : $redirect;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
        // Critical frontend query for 404 redirects - cache implemented above
        $redirect = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}ez_translate_redirects` WHERE old_url = %s ORDER BY created_at DESC LIMIT 1",
            $url
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

        // Cache result for 15 minutes (longer for redirects as they're stable)
        wp_cache_set($cache_key, $redirect ? $redirect : 'none', 'ez_translate', 900);

        return $redirect;
    }

    /**
     * Update linked redirects when posts change
     *
     * @param int     $post_id     Post ID
     * @param WP_Post $post_after  Post object after update
     * @param WP_Post $post_before Post object before update (unused but required by hook)
     * @since 1.0.0
     */
    public function update_linked_redirects($post_id, $post_after, $post_before) {
        // Only update for published posts
        if ($post_after->post_status !== 'publish') {
            return;
        }

        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
        // Update operations on custom redirects table - no cache needed for CRUD operations

        // Update redirects where this post is the source
        $source_redirects = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}ez_translate_redirects` WHERE post_id = %d AND change_type = 'manual'",
            $post_id
        ));

        foreach ($source_redirects as $redirect) {
            $new_old_url = get_permalink($post_after);

            if ($redirect->old_url !== $new_old_url) {
                $wpdb->update(
                    $wpdb->prefix . 'ez_translate_redirects',
                    array('old_url' => $new_old_url),
                    array('id' => $redirect->id),
                    array('%s'),
                    array('%d')
                );

                Logger::info('Updated source URL for linked redirect', array(
                    'redirect_id' => $redirect->id,
                    'old_old_url' => $redirect->old_url,
                    'new_old_url' => $new_old_url,
                    'post_id' => $post_id
                ));
            }
        }

        // Update redirects where this post is the destination
        $destination_redirects = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}ez_translate_redirects` WHERE destination_post_id = %d AND change_type = 'manual'",
            $post_id
        ));

        foreach ($destination_redirects as $redirect) {
            $new_new_url = get_permalink($post_after);

            if ($redirect->new_url !== $new_new_url) {
                $wpdb->update(
                    $wpdb->prefix . 'ez_translate_redirects',
                    array('new_url' => $new_new_url),
                    array('id' => $redirect->id),
                    array('%s'),
                    array('%d')
                );

                Logger::info('Updated destination URL for linked redirect', array(
                    'redirect_id' => $redirect->id,
                    'old_new_url' => $redirect->new_url,
                    'new_new_url' => $new_new_url,
                    'post_id' => $post_id
                ));
            }
        }

        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
