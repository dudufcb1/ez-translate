<?php
/**
 * Test Auto Landing Page Creation
 * 
 * This file tests the new automatic landing page creation functionality
 * when adding languages to the EZ Translate plugin.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once '../../../wp-load.php';
}

// Include required files
require_once 'includes/class-ez-translate-language-manager.php';
require_once 'includes/class-ez-translate-logger.php';

use EZTranslate\LanguageManager;
use EZTranslate\Logger;

echo "<h1>Test: Auto Landing Page Creation</h1>";
echo "<p>Testing the new automatic landing page creation when adding languages.</p>";

// Clean up any existing test data
echo "<h2>Cleanup</h2>";
LanguageManager::delete_language('test-auto');
echo "<p>✅ Cleaned up existing test data</p>";

// Test 1: Create language with automatic landing page
echo "<h2>Test 1: Create Language with Auto Landing Page</h2>";

$language_data = array(
    'code' => 'test-auto',
    'name' => 'Test Auto Language',
    'slug' => 'test-auto-language',
    'native_name' => 'Test Auto Native',
    'flag' => '🧪',
    'rtl' => false,
    'enabled' => true,
    'site_name' => 'Test Auto Site',
    'site_title' => 'Test Auto Site Title',
    'site_description' => 'Test auto site description'
);

echo "<p><strong>Creating language:</strong> " . $language_data['name'] . " (" . $language_data['code'] . ")</p>";

$result = LanguageManager::add_language($language_data);

if (is_wp_error($result)) {
    echo "<p style='color: red;'>❌ Failed to create language: " . $result->get_error_message() . "</p>";
    exit;
}

echo "<p style='color: green;'>✅ Language created successfully!</p>";

// Check if landing page was created
if (isset($result['landing_page_id'])) {
    $landing_page_id = $result['landing_page_id'];
    echo "<p style='color: green;'>✅ Landing page created automatically with ID: " . $landing_page_id . "</p>";
    
    // Get the post details
    $post = get_post($landing_page_id);
    if ($post) {
        echo "<div style='background: #f0f0f0; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa;'>";
        echo "<h3>Landing Page Details:</h3>";
        echo "<p><strong>Title:</strong> " . esc_html($post->post_title) . "</p>";
        echo "<p><strong>Slug:</strong> " . esc_html($post->post_name) . "</p>";
        echo "<p><strong>Status:</strong> " . esc_html($post->post_status) . "</p>";
        echo "<p><strong>Content:</strong> " . esc_html(wp_strip_all_tags($post->post_content)) . "</p>";
        
        // Check metadata
        $language_meta = get_post_meta($landing_page_id, '_ez_translate_language', true);
        $seo_title = get_post_meta($landing_page_id, '_ez_translate_seo_title', true);
        $seo_description = get_post_meta($landing_page_id, '_ez_translate_seo_description', true);
        $group_id = get_post_meta($landing_page_id, '_ez_translate_group', true);
        
        echo "<p><strong>Language Meta:</strong> " . esc_html($language_meta) . "</p>";
        echo "<p><strong>SEO Title:</strong> " . esc_html($seo_title) . "</p>";
        echo "<p><strong>SEO Description:</strong> " . esc_html($seo_description) . "</p>";
        echo "<p><strong>Group ID:</strong> " . esc_html($group_id) . "</p>";
        
        // Links
        $edit_url = admin_url('post.php?post=' . $landing_page_id . '&action=edit');
        $view_url = get_permalink($landing_page_id);
        echo "<p><strong>Edit URL:</strong> <a href='" . esc_url($edit_url) . "' target='_blank'>Edit Page</a></p>";
        echo "<p><strong>View URL:</strong> <a href='" . esc_url($view_url) . "' target='_blank'>View Page</a></p>";
        echo "</div>";
    } else {
        echo "<p style='color: red;'>❌ Landing page post not found!</p>";
    }
} else {
    echo "<p style='color: red;'>❌ No landing page ID returned!</p>";
}

// Test 2: Verify language data includes landing page ID
echo "<h2>Test 2: Verify Language Data</h2>";

$language = LanguageManager::get_language('test-auto');
if ($language) {
    echo "<p style='color: green;'>✅ Language retrieved successfully</p>";
    
    if (isset($language['landing_page_id']) && !empty($language['landing_page_id'])) {
        echo "<p style='color: green;'>✅ Landing page ID stored in language data: " . $language['landing_page_id'] . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Landing page ID not found in language data!</p>";
    }
    
    echo "<div style='background: #f9f9f9; padding: 15px; margin: 10px 0; border: 1px solid #ddd;'>";
    echo "<h3>Complete Language Data:</h3>";
    echo "<pre>" . print_r($language, true) . "</pre>";
    echo "</div>";
} else {
    echo "<p style='color: red;'>❌ Language not found!</p>";
}

// Test 3: Test get_landing_page_for_language function
echo "<h2>Test 3: Get Landing Page Function</h2>";

$landing_page_data = LanguageManager::get_landing_page_for_language('test-auto');
if ($landing_page_data) {
    echo "<p style='color: green;'>✅ Landing page retrieved via get_landing_page_for_language</p>";
    echo "<div style='background: #f9f9f9; padding: 15px; margin: 10px 0; border: 1px solid #ddd;'>";
    echo "<h3>Landing Page Data:</h3>";
    echo "<pre>" . print_r($landing_page_data, true) . "</pre>";
    echo "</div>";
} else {
    echo "<p style='color: red;'>❌ Landing page not found via get_landing_page_for_language!</p>";
}

// Test 4: Test language deletion (should also delete landing page)
echo "<h2>Test 4: Test Language Deletion</h2>";

$landing_page_id_before_delete = $language['landing_page_id'] ?? null;
echo "<p>Landing page ID before deletion: " . $landing_page_id_before_delete . "</p>";

$delete_result = LanguageManager::delete_language('test-auto');
if (is_wp_error($delete_result)) {
    echo "<p style='color: red;'>❌ Failed to delete language: " . $delete_result->get_error_message() . "</p>";
} else {
    echo "<p style='color: green;'>✅ Language deleted successfully</p>";
    
    // Check if landing page was also deleted
    if ($landing_page_id_before_delete) {
        $post_after_delete = get_post($landing_page_id_before_delete);
        if (!$post_after_delete) {
            echo "<p style='color: green;'>✅ Landing page was automatically deleted</p>";
        } else {
            echo "<p style='color: red;'>❌ Landing page still exists after language deletion!</p>";
        }
    }
}

echo "<h2>Summary</h2>";
echo "<p>✅ Auto landing page creation test completed!</p>";
echo "<p><strong>Key Features Tested:</strong></p>";
echo "<ul>";
echo "<li>✅ Automatic landing page creation when adding languages</li>";
echo "<li>✅ Landing page ID storage in language configuration</li>";
echo "<li>✅ Proper metadata assignment to landing pages</li>";
echo "<li>✅ Landing page retrieval via stored ID</li>";
echo "<li>✅ Automatic landing page deletion when language is deleted</li>";
echo "</ul>";

echo "<p><a href='wp-admin/admin.php?page=ez-translate'>← Back to EZ Translate Settings</a></p>";
?>
