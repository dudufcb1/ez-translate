<?php
/**
 * Simple test runner for SEO Title functionality
 */

// Simulate WordPress environment
define('ABSPATH', 'e:/xampp/htdocs/plugins/');
define('WP_DEBUG', true);

// Include WordPress core files (minimal simulation)
if (!function_exists('wp_insert_post')) {
    function wp_insert_post($args) {
        static $post_id = 1000;
        return ++$post_id;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single = false) {
        static $meta_data = array();
        if (isset($meta_data[$post_id][$key])) {
            return $single ? $meta_data[$post_id][$key] : array($meta_data[$post_id][$key]);
        }
        return $single ? '' : array();
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value) {
        static $meta_data = array();
        $meta_data[$post_id][$key] = $value;
        return true;
    }
}

if (!function_exists('get_post')) {
    function get_post($post_id) {
        return (object) array(
            'ID' => $post_id,
            'post_title' => 'Test Post ' . $post_id,
            'post_content' => 'Test content',
            'post_status' => 'publish',
            'post_type' => 'page'
        );
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post($post_id, $force = false) {
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

if (!function_exists('is_singular')) {
    function is_singular() {
        return true;
    }
}

// Additional WordPress functions needed
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        static $options = array();
        return isset($options[$option]) ? $options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        static $options = array();
        $options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        return true;
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = array()) {
        return array();
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = array();

        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
            }
        }

        public function get_error_message() {
            return 'Test error';
        }

        public function has_errors() {
            return !empty($this->errors);
        }

        public function add($code, $message, $data = '') {
            $this->errors[$code][] = $message;
        }
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0) {
        return json_encode($data, $options);
    }
}

if (!function_exists('error_log')) {
    function error_log($message) {
        // Silent for tests
        return true;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        static $transients = array();
        return isset($transients[$transient]) ? $transients[$transient] : false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        static $transients = array();
        $transients[$transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('has_action')) {
    function has_action($hook, $callback = false) {
        return false;
    }
}

// Simple WPDB simulation class
class MockWPDB {
    public $prefix = 'wp_';
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';

    public function prepare($query, ...$args) {
        return $query;
    }

    public function get_results($query) {
        return array();
    }

    public function query($query) {
        return true;
    }

    public function esc_like($text) {
        return addcslashes($text, '_%\\');
    }
}

// Global database simulation
global $wpdb;
if (!isset($wpdb)) {
    $wpdb = new MockWPDB();
}

// Define EZ_TRANSLATE_PLUGIN_DIR
define('EZ_TRANSLATE_PLUGIN_DIR', __DIR__ . '/');

// Include required classes
require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-logger.php';
require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-frontend.php';

// Include the test file
require_once EZ_TRANSLATE_PLUGIN_DIR . 'tests/test-seo-title-functionality.php';

echo "Running SEO Title Functionality Tests...\n\n";

// Run the tests
$results = ez_translate_run_seo_title_tests();

// Display results
foreach ($results as $result) {
    $status_icon = $result['status'] === 'PASS' ? '✅' : '❌';
    echo $status_icon . ' ' . $result['test'] . ': ' . $result['message'] . "\n";
}

// Summary
$passed = array_filter($results, function($r) { return $r['status'] === 'PASS'; });
$total = count($results);
$passed_count = count($passed);

echo "\nTest Summary\n";
echo $passed_count . " of " . $total . " tests passed\n";

if ($passed_count === $total) {
    echo "All SEO title functionality tests are working correctly!\n";
} else {
    echo "Some tests failed. Please check the implementation.\n";
}
