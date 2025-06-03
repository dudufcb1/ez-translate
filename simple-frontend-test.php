<?php
/**
 * Simple test for Frontend methods without database dependencies
 */

// Simulate WordPress environment
define('ABSPATH', 'e:/xampp/htdocs/plugins/');
define('WP_DEBUG', true);
define('EZ_TRANSLATE_PLUGIN_DIR', __DIR__ . '/');

// Mock WordPress functions
function sanitize_text_field($str) { return trim(strip_tags($str)); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function is_singular() { return true; }
function get_post_meta($post_id, $key, $single = false) {
    // Simulate some test data
    static $meta_data = array(
        1001 => array(
            '_ez_translate_language' => 'test',
            '_ez_translate_seo_title' => 'Custom SEO Title for Testing',
            '_ez_translate_seo_description' => 'Custom SEO description for testing'
        )
    );
    
    if (isset($meta_data[$post_id][$key])) {
        return $single ? $meta_data[$post_id][$key] : array($meta_data[$post_id][$key]);
    }
    return $single ? '' : array();
}

if (!function_exists('error_log')) {
    function error_log($message) { return true; }
}
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { return true; }
}
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { return true; }
}

// Mock Logger class
namespace EZTranslate {
    class Logger {
        public static function debug($message, $context = array()) { return true; }
        public static function info($message, $context = array()) { return true; }
        public static function error($message, $context = array()) { return true; }
    }
    
    class LanguageManager {
        public static function get_language_site_metadata($language_code) {
            // Simulate site metadata for test language
            if ($language_code === 'test') {
                return array(
                    'site_name' => 'WordPress Specialist',
                    'site_title' => 'Test Site Title',
                    'site_description' => 'Test Site Description'
                );
            }
            return array();
        }
    }
}

// Include the Frontend class
require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-frontend.php';

echo "Testing Frontend Methods Directly...\n\n";

// Create a test post object
$test_post = (object) array(
    'ID' => 1001,
    'post_title' => 'Original Page Title',
    'post_content' => 'Test content',
    'post_status' => 'publish',
    'post_type' => 'page'
);

// Set global post
global $post;
$post = $test_post;

// Test 1: filter_document_title with SEO title
echo "Test 1: filter_document_title with SEO title\n";
$frontend = new \EZTranslate\Frontend(true); // test mode

$title_parts = array(
    'title' => 'Original Page Title',
    'site' => 'Original Site Name'
);

echo "Before filter: " . print_r($title_parts, true) . "\n";

$filtered_title_parts = $frontend->filter_document_title($title_parts);

echo "After filter: " . print_r($filtered_title_parts, true) . "\n";

// Check results
if ($filtered_title_parts['title'] === 'Custom SEO Title for Testing') {
    echo "✅ SUCCESS: SEO title was applied correctly!\n";
} else {
    echo "❌ FAILED: SEO title was not applied. Expected: 'Custom SEO Title for Testing', Got: '" . $filtered_title_parts['title'] . "'\n";
}

if ($filtered_title_parts['site'] === 'WordPress Specialist') {
    echo "✅ SUCCESS: Site name was translated correctly!\n";
} else {
    echo "❌ FAILED: Site name was not translated. Expected: 'WordPress Specialist', Got: '" . $filtered_title_parts['site'] . "'\n";
}

echo "\n";

// Test 2: inject_meta_description
echo "Test 2: inject_meta_description\n";

ob_start();
$frontend->inject_meta_description();
$meta_output = ob_get_clean();

echo "Meta description output: " . htmlspecialchars($meta_output) . "\n";

if (strpos($meta_output, 'Custom SEO description for testing') !== false) {
    echo "✅ SUCCESS: Meta description was injected correctly!\n";
} else {
    echo "❌ FAILED: Meta description was not injected correctly\n";
}

echo "\nTest completed.\n";
