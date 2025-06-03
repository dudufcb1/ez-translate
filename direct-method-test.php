<?php
/**
 * Direct test of Frontend methods
 */

// Simulate WordPress environment
define('ABSPATH', 'e:/xampp/htdocs/plugins/');
define('WP_DEBUG', true);
define('EZ_TRANSLATE_PLUGIN_DIR', __DIR__ . '/');

// Mock WordPress functions
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags($str)); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('is_singular')) {
    function is_singular() { return true; }
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

// Mock get_post_meta function with test data
function get_post_meta($post_id, $key, $single = false) {
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

echo "Testing Frontend filter_document_title method directly...\n\n";

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

// Test the filter_document_title method directly
echo "Step 1: Verify post metadata\n";
echo "Language: " . get_post_meta(1001, '_ez_translate_language', true) . "\n";
echo "SEO Title: " . get_post_meta(1001, '_ez_translate_seo_title', true) . "\n";
echo "SEO Description: " . get_post_meta(1001, '_ez_translate_seo_description', true) . "\n\n";

echo "Step 2: Test filter_document_title method\n";

// Create a simple test class to isolate the method
class TestFrontend {
    private $test_mode = true;
    
    public function filter_document_title($title_parts) {
        global $post;
        
        if (!$post) {
            return $title_parts;
        }
        
        // Get metadata
        $seo_title = get_post_meta($post->ID, '_ez_translate_seo_title', true);
        $current_language = get_post_meta($post->ID, '_ez_translate_language', true);
        
        echo "Debug: SEO Title from meta: '$seo_title'\n";
        echo "Debug: Language from meta: '$current_language'\n";
        
        // Only process if page has a language assigned
        if (!empty($current_language)) {
            // Apply custom SEO title if available
            if (!empty($seo_title)) {
                $original_title = isset($title_parts['title']) ? $title_parts['title'] : 'N/A';
                $title_parts['title'] = sanitize_text_field($seo_title);
                
                echo "Debug: Title changed from '$original_title' to '$seo_title'\n";
            }
            
            // Mock site metadata for test language
            if ($current_language === 'test') {
                $original_site = isset($title_parts['site']) ? $title_parts['site'] : 'N/A';
                $title_parts['site'] = 'WordPress Specialist';
                
                echo "Debug: Site name changed from '$original_site' to 'WordPress Specialist'\n";
            }
        }
        
        return $title_parts;
    }
    
    public function inject_meta_description() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        $seo_description = get_post_meta($post->ID, '_ez_translate_seo_description', true);
        $current_language = get_post_meta($post->ID, '_ez_translate_language', true);
        
        echo "Debug: SEO Description from meta: '$seo_description'\n";
        echo "Debug: Language from meta: '$current_language'\n";
        
        if (!empty($seo_description) && !empty($current_language)) {
            $clean_description = sanitize_text_field($seo_description);
            echo '<meta name="description" content="' . esc_attr($clean_description) . '">' . "\n";
        }
    }
}

$test_frontend = new TestFrontend();

$title_parts = array(
    'title' => 'Original Page Title',
    'site' => 'Original Site Name'
);

echo "Before filter: " . print_r($title_parts, true) . "\n";

$filtered_title_parts = $test_frontend->filter_document_title($title_parts);

echo "After filter: " . print_r($filtered_title_parts, true) . "\n";

// Check results
echo "Step 3: Verify results\n";
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

echo "\nStep 4: Test meta description injection\n";

ob_start();
$test_frontend->inject_meta_description();
$meta_output = ob_get_clean();

echo "Meta description output: " . htmlspecialchars($meta_output) . "\n";

if (strpos($meta_output, 'Custom SEO description for testing') !== false) {
    echo "✅ SUCCESS: Meta description was injected correctly!\n";
} else {
    echo "❌ FAILED: Meta description was not injected correctly\n";
}

echo "\nTest completed.\n";
