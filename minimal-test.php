<?php
/**
 * Minimal Test - Just test the filter directly
 */

// Include WordPress
require_once '../../../wp-config.php';

// Include EZ Translate files
require_once 'includes/class-ez-translate-language-manager.php';
require_once 'includes/class-ez-translate-frontend.php';

use EZTranslate\LanguageManager;
use EZTranslate\Frontend;

echo "<h1>Minimal Filter Test</h1>\n";

// Clean up
LanguageManager::delete_language('test');

// Step 1: Create language
$language_data = array(
    'code' => 'test',
    'name' => 'Test Language',
    'slug' => 'test-language',
    'enabled' => true,
    'site_name' => 'Test Site Name'
);

$lang_result = LanguageManager::add_language($language_data);
if (is_wp_error($lang_result)) {
    echo "<p style='color: red;'>Failed to create language: " . $lang_result->get_error_message() . "</p>";
    exit;
}

echo "<p>✅ Language created</p>";

// Step 2: Create page
$post_id = wp_insert_post(array(
    'post_title' => 'Test Page',
    'post_content' => 'Test content',
    'post_status' => 'publish',
    'post_type' => 'page'
));

if (is_wp_error($post_id)) {
    echo "<p style='color: red;'>Failed to create page: " . $post_id->get_error_message() . "</p>";
    exit;
}

echo "<p>✅ Page created with ID: $post_id</p>";

// Step 3: Set metadata
update_post_meta($post_id, '_ez_translate_language', 'test');
update_post_meta($post_id, '_ez_translate_seo_title', 'Test SEO Title');

echo "<p>✅ Metadata set</p>";

// Step 4: Verify metadata
$saved_language = get_post_meta($post_id, '_ez_translate_language', true);
$saved_seo_title = get_post_meta($post_id, '_ez_translate_seo_title', true);

echo "<p><strong>Verification:</strong></p>";
echo "<ul>";
echo "<li>Language: '$saved_language'</li>";
echo "<li>SEO Title: '$saved_seo_title'</li>";
echo "</ul>";

if (empty($saved_language) || empty($saved_seo_title)) {
    echo "<p style='color: red;'>❌ Metadata not saved correctly!</p>";
    exit;
}

// Step 5: Test filter
$frontend = new Frontend(true); // test mode

global $post;
$original_post = $post;
$post = get_post($post_id);

echo "<p><strong>Global \$post set to ID:</strong> " . $post->ID . "</p>";

// Test the filter
$title_parts = array(
    'title' => 'Original Title',
    'site' => 'Original Site'
);

echo "<p><strong>Input:</strong></p>";
echo "<pre>" . print_r($title_parts, true) . "</pre>";

echo "<p><strong>Calling filter...</strong></p>";
$result = $frontend->filter_document_title($title_parts);

echo "<p><strong>Output:</strong></p>";
echo "<pre>" . print_r($result, true) . "</pre>";

// Check results
$title_changed = ($result['title'] !== $title_parts['title']);
$site_changed = ($result['site'] !== $title_parts['site']);

echo "<p><strong>Results:</strong></p>";
echo "<ul>";
echo "<li>Title changed: " . ($title_changed ? 'YES' : 'NO') . "</li>";
echo "<li>Site changed: " . ($site_changed ? 'YES' : 'NO') . "</li>";
echo "</ul>";

if ($title_changed) {
    echo "<p style='color: green;'>✅ Title filter working!</p>";
} else {
    echo "<p style='color: red;'>❌ Title filter NOT working!</p>";
}

if ($site_changed) {
    echo "<p style='color: green;'>✅ Site filter working!</p>";
} else {
    echo "<p style='color: red;'>❌ Site filter NOT working!</p>";
}

// Restore and clean up
$post = $original_post;
wp_delete_post($post_id, true);
LanguageManager::delete_language('test');

echo "<p>✅ Cleaned up</p>";

// Check error log for debug messages
echo "<h2>Debug Log Messages</h2>";
echo "<p>Check your WordPress error log for messages starting with '[EZ-Translate DEBUG]'</p>";
echo "<p>If WP_DEBUG is enabled, you should see detailed debug information about the filter execution.</p>";
?>
