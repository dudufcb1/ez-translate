<?php
/**
 * Test Basic Auto Landing Functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once '../../../wp-load.php';
}

// Include required files
require_once 'includes/class-ez-translate-language-manager.php';
require_once 'includes/class-ez-translate-logger.php';

use EZTranslate\LanguageManager;

echo "<h1>Test Basic Auto Landing Functionality</h1>";

// Clean up any existing test data
LanguageManager::delete_language('basic-test');
echo "<p>✅ Cleaned up existing test data</p>";

// Test 1: Create a simple landing page manually first
echo "<h2>Test 1: Manual Landing Page Creation</h2>";

$manual_landing_data = array(
    'title' => 'Manual Test Landing Page',
    'description' => 'This is a manual test landing page description.',
    'slug' => 'manual-test-landing',
    'status' => 'publish'
);

$manual_result = LanguageManager::create_landing_page_for_language('basic-test', $manual_landing_data);

if (is_wp_error($manual_result)) {
    echo "<p style='color: red;'>❌ Manual landing page creation failed: " . $manual_result->get_error_message() . "</p>";
} else {
    echo "<p style='color: green;'>✅ Manual landing page created successfully with ID: " . $manual_result . "</p>";
    
    // Verify the post
    $post = get_post($manual_result);
    if ($post) {
        echo "<p style='color: green;'>✅ Post verified in database</p>";
        echo "<p><strong>Title:</strong> " . esc_html($post->post_title) . "</p>";
        echo "<p><strong>Slug:</strong> " . esc_html($post->post_name) . "</p>";
        echo "<p><strong>Status:</strong> " . esc_html($post->post_status) . "</p>";
        
        // Check metadata
        $language_meta = get_post_meta($manual_result, '_ez_translate_language', true);
        $seo_title = get_post_meta($manual_result, '_ez_translate_seo_title', true);
        $seo_description = get_post_meta($manual_result, '_ez_translate_seo_description', true);
        
        echo "<p><strong>Language Meta:</strong> " . esc_html($language_meta) . "</p>";
        echo "<p><strong>SEO Title:</strong> " . esc_html($seo_title) . "</p>";
        echo "<p><strong>SEO Description:</strong> " . esc_html($seo_description) . "</p>";
    }
    
    // Clean up
    wp_delete_post($manual_result, true);
    echo "<p>✅ Manual test landing page cleaned up</p>";
}

// Test 2: Try to add language without auto-creation (old way)
echo "<h2>Test 2: Add Language Without Landing Page</h2>";

$language_data = array(
    'code' => 'basic-test',
    'name' => 'Basic Test Language',
    'slug' => 'basic-test-lang',
    'enabled' => true
);

// Try to add language with explicit null for landing page data
$result_no_landing = LanguageManager::add_language($language_data, null);

if (is_wp_error($result_no_landing)) {
    echo "<p style='color: red;'>❌ Language creation failed: " . $result_no_landing->get_error_message() . "</p>";
} else {
    echo "<p style='color: green;'>✅ Language creation result:</p>";
    echo "<pre>" . print_r($result_no_landing, true) . "</pre>";
    
    // Check if language was saved
    $saved_language = LanguageManager::get_language('basic-test');
    if ($saved_language) {
        echo "<p style='color: green;'>✅ Language saved successfully</p>";
        echo "<pre>" . print_r($saved_language, true) . "</pre>";
        
        if (isset($saved_language['landing_page_id']) && !empty($saved_language['landing_page_id'])) {
            echo "<p style='color: green;'>✅ Landing page ID found: " . $saved_language['landing_page_id'] . "</p>";
            
            // Verify the landing page exists
            $landing_post = get_post($saved_language['landing_page_id']);
            if ($landing_post) {
                echo "<p style='color: green;'>✅ Auto-created landing page verified</p>";
                echo "<p><strong>Auto Title:</strong> " . esc_html($landing_post->post_title) . "</p>";
                echo "<p><strong>Auto Slug:</strong> " . esc_html($landing_post->post_name) . "</p>";
                echo "<p><strong>Auto Status:</strong> " . esc_html($landing_post->post_status) . "</p>";
            } else {
                echo "<p style='color: red;'>❌ Auto-created landing page not found</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ No landing page ID in saved language</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Language not found after creation</p>";
    }
}

// Test 3: Test language deletion (should clean up landing page)
echo "<h2>Test 3: Test Language Deletion</h2>";

$language_before_delete = LanguageManager::get_language('basic-test');
$landing_page_id_to_delete = null;

if ($language_before_delete && isset($language_before_delete['landing_page_id'])) {
    $landing_page_id_to_delete = $language_before_delete['landing_page_id'];
    echo "<p>Landing page ID before deletion: " . $landing_page_id_to_delete . "</p>";
}

$delete_result = LanguageManager::delete_language('basic-test');

if (is_wp_error($delete_result)) {
    echo "<p style='color: red;'>❌ Language deletion failed: " . $delete_result->get_error_message() . "</p>";
} else {
    echo "<p style='color: green;'>✅ Language deleted successfully</p>";
    
    // Check if landing page was also deleted
    if ($landing_page_id_to_delete) {
        $post_after_delete = get_post($landing_page_id_to_delete);
        if (!$post_after_delete) {
            echo "<p style='color: green;'>✅ Landing page was automatically deleted</p>";
        } else {
            echo "<p style='color: red;'>❌ Landing page still exists after language deletion</p>";
        }
    }
}

echo "<h2>Summary</h2>";
echo "<p>Basic functionality test completed. Check the results above to see if auto landing page creation is working.</p>";

echo "<p><a href='wp-admin/admin.php?page=ez-translate'>← Back to EZ Translate Settings</a></p>";
?>
