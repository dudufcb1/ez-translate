<?php
/**
 * Simple Auto Landing Page Test
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once '../../../wp-load.php';
}

// Include required files
require_once 'includes/class-ez-translate-language-manager.php';
require_once 'includes/class-ez-translate-logger.php';

use EZTranslate\LanguageManager;

echo "<h1>Simple Auto Landing Page Test</h1>";

// Clean up
LanguageManager::delete_language('simple-test');
echo "<p>✅ Cleaned up existing test data</p>";

// Test the new functionality
echo "<h2>Creating Language with Auto Landing Page</h2>";

$language_data = array(
    'code' => 'simple-test',
    'name' => 'Simple Test Language',
    'slug' => 'simple-test',
    'enabled' => true
);

echo "<p>Creating language: " . $language_data['name'] . "</p>";

$result = LanguageManager::add_language($language_data);

if (is_wp_error($result)) {
    echo "<p style='color: red;'>❌ Error: " . $result->get_error_message() . "</p>";
    
    // Debug: Let's see what's happening
    echo "<h3>Debug Information:</h3>";
    echo "<p>Language data being passed:</p>";
    echo "<pre>" . print_r($language_data, true) . "</pre>";
    
    // Try to create landing page directly
    echo "<h3>Testing Landing Page Creation Directly:</h3>";
    $landing_page_data = array(
        'title' => 'Test Landing Page',
        'description' => 'Test description',
        'slug' => 'test-landing',
        'status' => 'publish'
    );
    
    $landing_result = LanguageManager::create_landing_page_for_language('simple-test', $landing_page_data);
    if (is_wp_error($landing_result)) {
        echo "<p style='color: red;'>❌ Landing page creation failed: " . $landing_result->get_error_message() . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Landing page created directly with ID: " . $landing_result . "</p>";
        
        // Clean up the test page
        wp_delete_post($landing_result, true);
        echo "<p>Cleaned up test landing page</p>";
    }
    
} else {
    echo "<p style='color: green;'>✅ Language created successfully!</p>";
    
    if (isset($result['landing_page_id'])) {
        echo "<p style='color: green;'>✅ Landing page created with ID: " . $result['landing_page_id'] . "</p>";
        
        // Get the language to verify it has the landing page ID
        $language = LanguageManager::get_language('simple-test');
        if ($language && isset($language['landing_page_id'])) {
            echo "<p style='color: green;'>✅ Landing page ID stored in language data: " . $language['landing_page_id'] . "</p>";
        } else {
            echo "<p style='color: red;'>❌ Landing page ID not found in language data</p>";
        }
        
        // Get the actual post
        $post = get_post($result['landing_page_id']);
        if ($post) {
            echo "<p style='color: green;'>✅ Landing page post exists</p>";
            echo "<p><strong>Title:</strong> " . esc_html($post->post_title) . "</p>";
            echo "<p><strong>Slug:</strong> " . esc_html($post->post_name) . "</p>";
            echo "<p><strong>Status:</strong> " . esc_html($post->post_status) . "</p>";
        } else {
            echo "<p style='color: red;'>❌ Landing page post not found</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ No landing page ID in result</p>";
        echo "<p>Result: " . print_r($result, true) . "</p>";
    }
    
    // Clean up
    LanguageManager::delete_language('simple-test');
    echo "<p>✅ Cleaned up test data</p>";
}

echo "<p><a href='wp-admin/admin.php?page=ez-translate'>← Back to EZ Translate Settings</a></p>";
?>
