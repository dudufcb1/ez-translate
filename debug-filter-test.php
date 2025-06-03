<?php
/**
 * Debug Filter Test
 * 
 * Detailed debug test to understand why the title filter is not working
 */

// Include WordPress
require_once '../../../wp-config.php';

// Include EZ Translate files
require_once 'includes/class-ez-translate-language-manager.php';
require_once 'includes/class-ez-translate-frontend.php';

use EZTranslate\LanguageManager;
use EZTranslate\Frontend;

echo "<h1>Debug Filter Test</h1>\n";

// Clean up first
$existing_posts = get_posts(array(
    'post_type' => 'page',
    'title' => 'Debug Test Page',
    'numberposts' => -1
));

foreach ($existing_posts as $existing_post) {
    wp_delete_post($existing_post->ID, true);
}

LanguageManager::delete_language('test');

echo "<p>✅ Cleaned up existing test data</p>";

// Step 1: Create language
echo "<h2>Step 1: Creating Test Language</h2>";

$language_data = array(
    'code' => 'test',
    'name' => 'Test Language',
    'slug' => 'test-language',
    'enabled' => true,
    'site_name' => 'Debug Site Name'
);

$lang_result = LanguageManager::add_language($language_data);
if (is_wp_error($lang_result)) {
    echo "<p style='color: red;'>❌ Failed to create language: " . $lang_result->get_error_message() . "</p>";
    exit;
}

echo "<p style='color: green;'>✅ Language created successfully</p>";

// Verify language was saved
$saved_language = LanguageManager::get_language('test');
echo "<p><strong>Saved language data:</strong></p>";
echo "<pre>" . print_r($saved_language, true) . "</pre>";

// Step 2: Create test page
echo "<h2>Step 2: Creating Test Page</h2>";

$post_id = wp_insert_post(array(
    'post_title' => 'Debug Test Page',
    'post_content' => 'Debug test content',
    'post_status' => 'publish',
    'post_type' => 'page'
));

if (is_wp_error($post_id)) {
    echo "<p style='color: red;'>❌ Failed to create page: " . $post_id->get_error_message() . "</p>";
    exit;
}

echo "<p style='color: green;'>✅ Page created with ID: $post_id</p>";

// Step 3: Set metadata
echo "<h2>Step 3: Setting Metadata</h2>";

$meta_result1 = update_post_meta($post_id, '_ez_translate_language', 'test');
$meta_result2 = update_post_meta($post_id, '_ez_translate_seo_title', 'Debug SEO Title');
$meta_result3 = update_post_meta($post_id, '_ez_translate_seo_description', 'Debug SEO description');

echo "<p>Language meta result: " . ($meta_result1 ? 'SUCCESS' : 'FAILED') . "</p>";
echo "<p>SEO title meta result: " . ($meta_result2 ? 'SUCCESS' : 'FAILED') . "</p>";
echo "<p>SEO description meta result: " . ($meta_result3 ? 'SUCCESS' : 'FAILED') . "</p>";

// Verify metadata was saved
$saved_language_meta = get_post_meta($post_id, '_ez_translate_language', true);
$saved_seo_title = get_post_meta($post_id, '_ez_translate_seo_title', true);
$saved_seo_description = get_post_meta($post_id, '_ez_translate_seo_description', true);

echo "<p><strong>Saved metadata:</strong></p>";
echo "<ul>";
echo "<li>Language: '" . $saved_language_meta . "'</li>";
echo "<li>SEO Title: '" . $saved_seo_title . "'</li>";
echo "<li>SEO Description: '" . $saved_seo_description . "'</li>";
echo "</ul>";

// Step 4: Get language site metadata
echo "<h2>Step 4: Getting Language Site Metadata</h2>";

$site_metadata = LanguageManager::get_language_site_metadata('test');
echo "<p><strong>Site metadata for 'test' language:</strong></p>";
echo "<pre>" . print_r($site_metadata, true) . "</pre>";

// Step 5: Test the filter
echo "<h2>Step 5: Testing Document Title Filter</h2>";

$frontend = new Frontend(true); // test mode

// Set up global $post
global $post;
$original_post = $post;
$post = get_post($post_id);

echo "<p><strong>Global \$post set to:</strong></p>";
echo "<ul>";
echo "<li>ID: " . $post->ID . "</li>";
echo "<li>Title: " . $post->post_title . "</li>";
echo "<li>Type: " . $post->post_type . "</li>";
echo "</ul>";

// Test title parts
$title_parts = array(
    'title' => 'Original Debug Title',
    'site' => 'Original Site Name'
);

echo "<p><strong>Input title parts:</strong></p>";
echo "<pre>" . print_r($title_parts, true) . "</pre>";

// Call the filter
echo "<p><strong>Calling filter_document_title()...</strong></p>";
$filtered_title_parts = $frontend->filter_document_title($title_parts);

echo "<p><strong>Output title parts:</strong></p>";
echo "<pre>" . print_r($filtered_title_parts, true) . "</pre>";

// Step 6: Manual verification of filter logic
echo "<h2>Step 6: Manual Filter Logic Verification</h2>";

// Manually check what the filter should do
$manual_seo_title = get_post_meta($post->ID, '_ez_translate_seo_title', true);
$manual_current_language = get_post_meta($post->ID, '_ez_translate_language', true);

echo "<p><strong>Manual checks:</strong></p>";
echo "<ul>";
echo "<li>Post ID: " . $post->ID . "</li>";
echo "<li>SEO Title from meta: '" . $manual_seo_title . "'</li>";
echo "<li>Language from meta: '" . $manual_current_language . "'</li>";
echo "<li>SEO Title empty? " . (empty($manual_seo_title) ? 'YES' : 'NO') . "</li>";
echo "<li>Language empty? " . (empty($manual_current_language) ? 'YES' : 'NO') . "</li>";
echo "</ul>";

if (!empty($manual_current_language)) {
    $manual_site_metadata = LanguageManager::get_language_site_metadata($manual_current_language);
    echo "<p><strong>Manual site metadata lookup:</strong></p>";
    echo "<pre>" . print_r($manual_site_metadata, true) . "</pre>";
    
    echo "<p><strong>Site name from metadata:</strong> '" . (isset($manual_site_metadata['site_name']) ? $manual_site_metadata['site_name'] : 'NOT SET') . "'</p>";
}

// Step 7: Results
echo "<h2>Step 7: Test Results</h2>";

$title_changed = ($filtered_title_parts['title'] !== $title_parts['title']);
$site_changed = ($filtered_title_parts['site'] !== $title_parts['site']);

echo "<p><strong>Title changed:</strong> " . ($title_changed ? 'YES' : 'NO') . "</p>";
echo "<p><strong>Site changed:</strong> " . ($site_changed ? 'YES' : 'NO') . "</p>";

if ($title_changed) {
    echo "<p style='color: green;'>✅ Title filter working: '" . $title_parts['title'] . "' → '" . $filtered_title_parts['title'] . "'</p>";
} else {
    echo "<p style='color: red;'>❌ Title filter NOT working: '" . $title_parts['title'] . "' (unchanged)</p>";
}

if ($site_changed) {
    echo "<p style='color: green;'>✅ Site name filter working: '" . $title_parts['site'] . "' → '" . $filtered_title_parts['site'] . "'</p>";
} else {
    echo "<p style='color: red;'>❌ Site name filter NOT working: '" . $title_parts['site'] . "' (unchanged)</p>";
}

// Restore original post
$post = $original_post;

// Clean up
wp_delete_post($post_id, true);
LanguageManager::delete_language('test');

echo "<p>✅ Cleaned up test data</p>";

echo "<h2>Summary</h2>";
if ($title_changed && $site_changed) {
    echo "<p style='color: green; font-weight: bold; font-size: 18px;'>🎉 BOTH FILTERS WORKING!</p>";
} elseif ($title_changed) {
    echo "<p style='color: orange; font-weight: bold; font-size: 18px;'>⚠️ ONLY TITLE FILTER WORKING</p>";
} elseif ($site_changed) {
    echo "<p style='color: orange; font-weight: bold; font-size: 18px;'>⚠️ ONLY SITE NAME FILTER WORKING</p>";
} else {
    echo "<p style='color: red; font-weight: bold; font-size: 18px;'>❌ NEITHER FILTER WORKING</p>";
}
?>
