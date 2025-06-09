<?php
/**
 * Redirect Tracker for EZ Translate
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
 * Redirect Tracker class
 *
 * Handles verification of WordPress automatic redirections and status checking
 *
 * @since 1.0.0
 */
class RedirectTracker {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
        Logger::info('RedirectTracker initialized');
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Check for WordPress automatic redirections periodically
        add_action('wp_loaded', array($this, 'schedule_redirect_verification'));
        
        // Custom cron job for checking redirects
        add_action('ez_translate_check_redirects', array($this, 'verify_wordpress_redirects'));
        
        Logger::info('RedirectTracker hooks initialized');
    }

    /**
     * Schedule redirect verification if not already scheduled
     *
     * @since 1.0.0
     */
    public function schedule_redirect_verification() {
        if (!wp_next_scheduled('ez_translate_check_redirects')) {
            wp_schedule_event(time(), 'hourly', 'ez_translate_check_redirects');
            Logger::info('Redirect verification scheduled');
        }
    }

    /**
     * Verify WordPress automatic redirections
     *
     * @since 1.0.0
     */
    public function verify_wordpress_redirects() {
        $redirects = $this->get_unverified_redirects();
        
        if (empty($redirects)) {
            Logger::info('No unverified redirects to check');
            return;
        }

        foreach ($redirects as $redirect) {
            $this->check_single_redirect($redirect);
        }

        Logger::info('WordPress redirect verification completed', array(
            'checked_count' => count($redirects)
        ));
    }

    /**
     * Get unverified redirects from database
     *
     * @return array Array of redirect objects
     * @since 1.0.0
     */
    private function get_unverified_redirects() {
        global $wpdb;
        // Construct the table name.
        $raw_table_name = $wpdb->prefix . 'ez_translate_redirects';

        // Construct the SQL query string using sprintf for the table name.
        $sql = sprintf(
            "SELECT * FROM %s
             WHERE change_type = %%s
             AND wp_auto_redirect = %%d
             AND new_url IS NOT NULL
             ORDER BY created_at DESC
             LIMIT 10",
            $raw_table_name // Pass table name to sprintf
        );

        // Prepare the SQL query with placeholders for values.
        // Note the double %% for sprintf to output single % for prepare.
        $redirects = $wpdb->get_results($wpdb->prepare(
            $sql,
            'changed',
            0
        ));

        return $redirects ? $redirects : array();
    }

    /**
     * Check single redirect for WordPress automatic handling
     *
     * @param object $redirect Redirect record
     * @since 1.0.0
     */
    private function check_single_redirect($redirect) {
        $response = $this->test_url_redirect($redirect->old_url);
        
        if ($response && $this->is_wordpress_redirect($response, $redirect->new_url)) {
            $this->mark_as_wordpress_redirect($redirect->id);
            
            Logger::info('WordPress automatic redirect detected', array(
                'redirect_id' => $redirect->id,
                'old_url' => $redirect->old_url,
                'new_url' => $redirect->new_url,
                'response_code' => $response['response_code']
            ));
        } else {
            Logger::debug('No WordPress automatic redirect found', array(
                'redirect_id' => $redirect->id,
                'old_url' => $redirect->old_url,
                'response' => $response
            ));
        }
    }

    /**
     * Test URL for redirect response
     *
     * @param string $url URL to test
     * @return array|false Response data or false on failure
     * @since 1.0.0
     */
    private function test_url_redirect($url) {
        $args = array(
            'timeout' => 10,
            'redirection' => 0, // Don't follow redirects
            'user-agent' => 'EZ-Translate-Redirect-Checker/1.0',
            'sslverify' => false
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            Logger::warning('Failed to test URL redirect', array(
                'url' => $url,
                'error' => $response->get_error_message()
            ));
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $location = wp_remote_retrieve_header($response, 'location');

        return array(
            'response_code' => $response_code,
            'location' => $location,
            'headers' => wp_remote_retrieve_headers($response)
        );
    }

    /**
     * Check if response indicates WordPress automatic redirect
     *
     * @param array  $response    HTTP response data
     * @param string $expected_url Expected redirect URL
     * @return bool True if WordPress redirect detected
     * @since 1.0.0
     */
    private function is_wordpress_redirect($response, $expected_url) {
        // Check for 301 redirect
        if ($response['response_code'] !== 301) {
            return false;
        }

        // Check if location header matches expected URL
        if (empty($response['location'])) {
            return false;
        }

        // Normalize URLs for comparison
        $location = trailingslashit($response['location']);
        $expected = trailingslashit($expected_url);

        return $location === $expected;
    }

    /**
     * Mark redirect as WordPress automatic
     *
     * @param int $redirect_id Redirect ID
     * @return bool Success status
     * @since 1.0.0
     */
    private function mark_as_wordpress_redirect($redirect_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ez_translate_redirects';
        
        $result = $wpdb->update(
            $table_name,
            array('wp_auto_redirect' => 1),
            array('id' => $redirect_id),
            array('%d'),
            array('%d')
        );

        if ($result === false) {
            Logger::error('Failed to mark redirect as WordPress automatic', array(
                'redirect_id' => $redirect_id,
                'error' => $wpdb->last_error
            ));
            return false;
        }

        return true;
    }

    /**
     * Get redirect statistics
     *
     * @return array Statistics data
     * @since 1.0.0
     */
    public function get_redirect_stats() {
        global $wpdb;

        $raw_table_name = $wpdb->prefix . 'ez_translate_redirects';

        $stats = array();

        // Total redirects
        $sql_total = sprintf("SELECT COUNT(*) FROM %s", $raw_table_name);
        $stats['total'] = $wpdb->get_var($wpdb->prepare($sql_total));

        // WordPress automatic redirects
        $stats['wp_auto'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$raw_table_name} WHERE wp_auto_redirect = %d",
            1
        ));

        // Manual redirects
        $stats['manual'] = $stats['total'] - $stats['wp_auto'];

        // By change type
        $stats['changed'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$raw_table_name} WHERE change_type = %s",
            'changed'
        ));

        $stats['trashed'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$raw_table_name} WHERE change_type = %s",
            'trashed'
        ));

        $stats['deleted'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$raw_table_name} WHERE change_type = %s",
            'deleted_permanently'
        ));

        // By redirect type
        $redirect_types = $wpdb->get_results(
            $wpdb->prepare("SELECT redirect_type, COUNT(*) as count FROM %s GROUP BY redirect_type", $raw_table_name)
        );

        $stats['by_type'] = array();
        foreach ($redirect_types as $type) {
            $stats['by_type'][$type->redirect_type] = $type->count;
        }

        return $stats;
    }

    /**
     * Clean up old redirect records
     *
     * @param int $days_old Days old to consider for cleanup (default: 90)
     * @return int Number of records cleaned up
     * @since 1.0.0
     */
    public function cleanup_old_redirects($days_old = 90) {
        global $wpdb;

        $raw_table_name = $wpdb->prefix . 'ez_translate_redirects';

        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$days_old} days"));

        // Construct the SQL query string using sprintf for the table name.
        // Remember to escape literal % signs for sprintf if they need to reach prepare.
        $sql = sprintf(
            "DELETE FROM %s WHERE created_at < %%s AND change_type = %%s",
            $raw_table_name // Pass table name to sprintf
        );

        // Prepare the SQL query with placeholders for values.
        $result = $wpdb->query($wpdb->prepare(
            $sql,
            $cutoff_date,
            'changed'
        ));

        if ($result !== false) {
            Logger::info('Old redirect records cleaned up', array(
                'deleted_count' => $result,
                'cutoff_date' => $cutoff_date
            ));
        } else {
            Logger::error('Failed to clean up old redirect records', array(
                'error' => $wpdb->last_error
            ));
        }

        return $result !== false ? $result : 0;
    }

    /**
     * Force check all unverified redirects
     *
     * @return array Results of the check
     * @since 1.0.0
     */
    public function force_check_all_redirects() {
        $redirects = $this->get_unverified_redirects();
        $results = array(
            'checked' => 0,
            'wp_auto_found' => 0,
            'errors' => 0
        );

        foreach ($redirects as $redirect) {
            $results['checked']++;
            
            try {
                $response = $this->test_url_redirect($redirect->old_url);
                
                if ($response && $this->is_wordpress_redirect($response, $redirect->new_url)) {
                    $this->mark_as_wordpress_redirect($redirect->id);
                    $results['wp_auto_found']++;
                }
            } catch (\Exception $e) {
                $results['errors']++;
                Logger::error('Error checking redirect', array(
                    'redirect_id' => $redirect->id,
                    'error' => $e->getMessage()
                ));
            }
        }

        Logger::info('Force check completed', $results);
        return $results;
    }
}
