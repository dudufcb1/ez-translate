<?php
/**
 * Debug Auto Landing Page Creation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once '../../../wp-load.php';
}

// Include required files
require_once 'includes/class-ez-translate-language-manager.php';
require_once 'includes/class-ez-translate-logger.php';

use EZTranslate\LanguageManager;

echo "<h1>Debug Auto Landing Page Creation</h1>";

// Clean up
LanguageManager::delete_language('debug-test');
echo "<p>✅ Cleaned up existing test data</p>";

// Step 1: Test generate_default_landing_page_data function
echo "<h2>Step 1: Test generate_default_landing_page_data</h2>";

$language_data = array(
    'code' => 'debug-test',
    'name' => 'Debug Test Language',
    'slug' => 'debug-test',
    'enabled' => true,
    'site_name' => 'Debug Site'
);

// Use reflection to access the private method
$reflection = new ReflectionClass('EZTranslate\LanguageManager');
$method = $reflection->getMethod('generate_default_landing_page_data');
$method->setAccessible(true);

try {
    $landing_page_data = $method->invoke(null, $language_data);
    echo "<p style='color: green;'>✅ generate_default_landing_page_data works</p>";
    echo "<pre>" . print_r($landing_page_data, true) . "</pre>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ generate_default_landing_page_data failed: " . $e->getMessage() . "</p>";
    exit;
}

// Step 2: Test create_landing_page_for_language directly
echo "<h2>Step 2: Test create_landing_page_for_language</h2>";

$landing_result = LanguageManager::create_landing_page_for_language('debug-test', $landing_page_data);

if (is_wp_error($landing_result)) {
    echo "<p style='color: red;'>❌ create_landing_page_for_language failed: " . $landing_result->get_error_message() . "</p>";
    exit;
} else {
    echo "<p style='color: green;'>✅ Landing page created with ID: " . $landing_result . "</p>";
    
    // Verify the post exists
    $post = get_post($landing_result);
    if ($post) {
        echo "<p style='color: green;'>✅ Post exists in database</p>";
        echo "<p><strong>Title:</strong> " . esc_html($post->post_title) . "</p>";
        echo "<p><strong>Content:</strong> " . esc_html(wp_strip_all_tags($post->post_content)) . "</p>";
        
        // Check metadata
        $language_meta = get_post_meta($landing_result, '_ez_translate_language', true);
        echo "<p><strong>Language Meta:</strong> " . esc_html($language_meta) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Post not found in database</p>";
        exit;
    }
}

// Step 3: Test the full add_language process
echo "<h2>Step 3: Test Full add_language Process</h2>";

// First clean up the test landing page
wp_delete_post($landing_result, true);
echo "<p>Cleaned up test landing page</p>";

// Now test the full process
$result = LanguageManager::add_language($language_data);

if (is_wp_error($result)) {
    echo "<p style='color: red;'>❌ add_language failed: " . $result->get_error_message() . "</p>";
    
    // Let's debug what's happening in the add_language function
    echo "<h3>Debug add_language process:</h3>";
    
    // Check if validation passes
    $validation_result = LanguageManager::validate_language_data($language_data);
    if (is_wp_error($validation_result)) {
        echo "<p style='color: red;'>❌ Validation failed: " . implode(', ', $validation_result->get_error_messages()) . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Validation passed</p>";
    }
    
    // Check for duplicates
    if (LanguageManager::language_code_exists($language_data['code'])) {
        echo "<p style='color: red;'>❌ Language code already exists</p>";
    } else {
        echo "<p style='color: green;'>✅ Language code is unique</p>";
    }
    
    if (LanguageManager::language_slug_exists($language_data['slug'])) {
        echo "<p style='color: red;'>❌ Language slug already exists</p>";
    } else {
        echo "<p style='color: green;'>✅ Language slug is unique</p>";
    }
    
} else {
    echo "<p style='color: green;'>✅ add_language succeeded!</p>";
    
    if (isset($result['landing_page_id'])) {
        echo "<p style='color: green;'>✅ Landing page ID returned: " . $result['landing_page_id'] . "</p>";
        
        // Verify language was saved with landing page ID
        $saved_language = LanguageManager::get_language('debug-test');
        if ($saved_language && isset($saved_language['landing_page_id'])) {
            echo "<p style='color: green;'>✅ Language saved with landing page ID: " . $saved_language['landing_page_id'] . "</p>";
        } else {
            echo "<p style='color: red;'>❌ Language not saved with landing page ID</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ No landing page ID in result</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    }
}

// Clean up
LanguageManager::delete_language('debug-test');
echo "<p>✅ Final cleanup completed</p>";

echo "<p><a href='wp-admin/admin.php?page=ez-translate'>← Back to EZ Translate Settings</a></p>";
?>
