<?php
/**
 * Simple Title Test
 * 
 * Quick test to verify that the document title filter is working correctly
 */

// Include WordPress
require_once '../../../wp-config.php';

// Include EZ Translate files
require_once 'includes/class-ez-translate-language-manager.php';
require_once 'includes/class-ez-translate-frontend.php';

use EZTranslate\LanguageManager;
use EZTranslate\Frontend;

echo "<h1>Simple Title Filter Test</h1>\n";

// Clean up any existing test data first
$existing_posts = get_posts(array(
    'post_type' => 'page',
    'title' => 'Simple Test Title',
    'numberposts' => -1
));

foreach ($existing_posts as $existing_post) {
    wp_delete_post($existing_post->ID, true);
    echo "<p>Deleted existing test post: {$existing_post->ID}</p>";
}

LanguageManager::delete_language('test');
echo "<p>Cleaned up existing test language</p>";

// Create a test language
$language_data = array(
    'code' => 'test',
    'name' => 'Test Language',
    'slug' => 'test-language',
    'enabled' => true,
    'site_name' => 'Test Site Name'
);

$lang_result = LanguageManager::add_language($language_data);
if (is_wp_error($lang_result)) {
    echo "<p style='color: red;'>Failed to create test language: " . $lang_result->get_error_message() . "</p>";
    exit;
}

echo "<p style='color: green;'>✅ Created test language successfully</p>";

// Create a test page
$post_id = wp_insert_post(array(
    'post_title' => 'Simple Test Title',
    'post_content' => 'Test content',
    'post_status' => 'publish',
    'post_type' => 'page'
));

if (is_wp_error($post_id)) {
    echo "<p style='color: red;'>Failed to create test page: " . $post_id->get_error_message() . "</p>";
    exit;
}

echo "<p style='color: green;'>✅ Created test page with ID: $post_id</p>";

// Set language and SEO metadata
update_post_meta($post_id, '_ez_translate_language', 'test');
update_post_meta($post_id, '_ez_translate_seo_title', 'Custom SEO Title');
update_post_meta($post_id, '_ez_translate_seo_description', 'Custom SEO description');

echo "<p style='color: green;'>✅ Set metadata on test page</p>";

// Verify metadata was saved
$saved_language = get_post_meta($post_id, '_ez_translate_language', true);
$saved_seo_title = get_post_meta($post_id, '_ez_translate_seo_title', true);
$saved_seo_description = get_post_meta($post_id, '_ez_translate_seo_description', true);

echo "<p><strong>Saved metadata:</strong></p>";
echo "<ul>";
echo "<li>Language: " . ($saved_language ?: 'NOT SET') . "</li>";
echo "<li>SEO Title: " . ($saved_seo_title ?: 'NOT SET') . "</li>";
echo "<li>SEO Description: " . ($saved_seo_description ?: 'NOT SET') . "</li>";
echo "</ul>";

// Test the filter
$frontend = new Frontend(true); // true = test mode

// Simulate global $post
global $post;
$original_post = $post;
$post = get_post($post_id);

echo "<p><strong>Testing document title filter...</strong></p>";

// Test title parts
$title_parts = array(
    'title' => 'Simple Test Title',
    'site' => 'Original Site Name'
);

echo "<p><strong>Before filter:</strong></p>";
echo "<pre>" . print_r($title_parts, true) . "</pre>";

// Call the filter directly
$filtered_title_parts = $frontend->filter_document_title($title_parts);

echo "<p><strong>After filter:</strong></p>";
echo "<pre>" . print_r($filtered_title_parts, true) . "</pre>";

// Check results
$title_success = false;
$site_success = false;

if ($filtered_title_parts['title'] === 'Custom SEO Title') {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: Title filter working correctly!</p>";
    $title_success = true;
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ FAILED: Title was not changed</p>";
    echo "<p>Expected: 'Custom SEO Title'</p>";
    echo "<p>Got: '" . $filtered_title_parts['title'] . "'</p>";
}

if ($filtered_title_parts['site'] === 'Test Site Name') {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: Site name filter working correctly!</p>";
    $site_success = true;
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ FAILED: Site name was not changed</p>";
    echo "<p>Expected: 'Test Site Name'</p>";
    echo "<p>Got: '" . $filtered_title_parts['site'] . "'</p>";
}

// Test meta description injection
echo "<p><strong>Testing meta description injection...</strong></p>";

ob_start();
$frontend->inject_meta_description();
$meta_output = ob_get_clean();

echo "<p><strong>Meta description output:</strong></p>";
echo "<pre>" . htmlspecialchars($meta_output) . "</pre>";

$meta_success = false;
if (strpos($meta_output, 'Custom SEO description') !== false) {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: Meta description injection working correctly!</p>";
    $meta_success = true;
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ FAILED: Meta description was not injected</p>";
}

// Restore original post
$post = $original_post;

// Clean up
wp_delete_post($post_id, true);
LanguageManager::delete_language('test');
echo "<p>✅ Cleaned up test data</p>";

// Summary
echo "<h2>Test Summary</h2>";
if ($title_success && $site_success && $meta_success) {
    echo "<p style='color: green; font-weight: bold; font-size: 18px;'>🎉 ALL TESTS PASSED!</p>";
    echo "<p>The title filter and meta description injection are working correctly.</p>";
} else {
    echo "<p style='color: red; font-weight: bold; font-size: 18px;'>❌ SOME TESTS FAILED</p>";
    echo "<ul>";
    if (!$title_success) echo "<li>Title filter not working</li>";
    if (!$site_success) echo "<li>Site name filter not working</li>";
    if (!$meta_success) echo "<li>Meta description injection not working</li>";
    echo "</ul>";
}
?>
