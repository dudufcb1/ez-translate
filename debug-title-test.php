<?php
/**
 * Debug Title Test
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

echo "<h1>EZ Translate Title Filter Debug Test</h1>\n";

// Create a test language with custom site name
$language_data = array(
    'code' => 'test_lang',
    'name' => 'Test Language',
    'slug' => 'test-language',
    'enabled' => true,
    'site_name' => 'WordPress Specialist'
);

$lang_result = LanguageManager::add_language($language_data);
if (is_wp_error($lang_result)) {
    echo "<p style='color: red;'>Failed to create test language: " . $lang_result->get_error_message() . "</p>";
    exit;
}

echo "<p><strong>Created test language with custom site name:</strong> WordPress Specialist</p>";

// Create a test page
$post_id = wp_insert_post(array(
    'post_title' => 'Original Test Title',
    'post_content' => 'Test content',
    'post_status' => 'publish',
    'post_type' => 'page'
));

if (is_wp_error($post_id)) {
    echo "<p style='color: red;'>Failed to create test page: " . $post_id->get_error_message() . "</p>";
    exit;
}

echo "<p><strong>Created test page with ID:</strong> $post_id</p>";

// Set language and SEO metadata
update_post_meta($post_id, '_ez_translate_language', 'test_lang');
update_post_meta($post_id, '_ez_translate_seo_title', 'Custom SEO Title from EZ Translate');
update_post_meta($post_id, '_ez_translate_seo_description', 'Custom SEO description from EZ Translate');

echo "<p><strong>Set metadata:</strong></p>";
echo "<ul>";
echo "<li>Language: test_lang</li>";
echo "<li>SEO Title: Custom SEO Title from EZ Translate</li>";
echo "<li>SEO Description: Custom SEO description from EZ Translate</li>";
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
    'title' => 'Original Test Title',
    'site' => 'Original Site Name'
);

echo "<p><strong>Before filter:</strong></p>";
echo "<pre>" . print_r($title_parts, true) . "</pre>";

$filtered_title_parts = $frontend->filter_document_title($title_parts);

echo "<p><strong>After filter:</strong></p>";
echo "<pre>" . print_r($filtered_title_parts, true) . "</pre>";

// Check if title was changed
if ($filtered_title_parts['title'] === 'Custom SEO Title from EZ Translate') {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: Title filter is working correctly!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ FAILED: Title was not changed by the filter</p>";
    echo "<p>Expected: 'Custom SEO Title from EZ Translate'</p>";
    echo "<p>Got: '" . $filtered_title_parts['title'] . "'</p>";
}

// Check if site name was changed
if ($filtered_title_parts['site'] === 'WordPress Specialist') {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: Site name translation is working correctly!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ FAILED: Site name was not translated by the filter</p>";
    echo "<p>Expected: 'WordPress Specialist'</p>";
    echo "<p>Got: '" . $filtered_title_parts['site'] . "'</p>";
}

// Test meta description injection
echo "<p><strong>Testing meta description injection...</strong></p>";

ob_start();
$frontend->inject_meta_description();
$meta_output = ob_get_clean();

echo "<p><strong>Meta description output:</strong></p>";
echo "<pre>" . htmlspecialchars($meta_output) . "</pre>";

if (strpos($meta_output, 'Custom SEO description from EZ Translate') !== false) {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: Meta description injection is working correctly!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ FAILED: Meta description was not injected correctly</p>";
}

// Test complete metadata generation
echo "<p><strong>Testing complete metadata generation...</strong></p>";

ob_start();
$frontend->override_head_metadata();
$complete_output = ob_get_clean();

echo "<p><strong>Complete metadata output:</strong></p>";
echo "<pre>" . htmlspecialchars($complete_output) . "</pre>";

// Check for Open Graph
if (strpos($complete_output, 'og:title') !== false && strpos($complete_output, 'Custom SEO Title from EZ Translate') !== false) {
    echo "<p style='color: green;'>✅ Open Graph title found</p>";
} else {
    echo "<p style='color: red;'>❌ Open Graph title not found</p>";
}

if (strpos($complete_output, 'og:description') !== false && strpos($complete_output, 'Custom SEO description from EZ Translate') !== false) {
    echo "<p style='color: green;'>✅ Open Graph description found</p>";
} else {
    echo "<p style='color: red;'>❌ Open Graph description not found</p>";
}

// Check for Twitter Cards
if (strpos($complete_output, 'twitter:title') !== false) {
    echo "<p style='color: green;'>✅ Twitter Card title found</p>";
} else {
    echo "<p style='color: red;'>❌ Twitter Card title not found</p>";
}

// Check for JSON-LD
if (strpos($complete_output, 'application/ld+json') !== false) {
    echo "<p style='color: green;'>✅ JSON-LD structured data found</p>";
} else {
    echo "<p style='color: red;'>❌ JSON-LD structured data not found</p>";
}

// Restore original post
$post = $original_post;

// Clean up
wp_delete_post($post_id, true);
LanguageManager::delete_language('test_lang');
echo "<p><strong>Test page and language deleted.</strong></p>";

echo "<h2>Test Summary</h2>";
echo "<p>The debug test has completed. If you see green checkmarks above, the title filter, site name translation, and metadata generation are working correctly.</p>";
echo "<p><strong>Key points:</strong></p>";
echo "<ul>";
echo "<li>The document title filter should change 'Original Test Title' to 'Custom SEO Title from EZ Translate'</li>";
echo "<li>The site name should change from 'Original Site Name' to 'WordPress Specialist'</li>";
echo "<li>The meta description should be injected as a proper HTML meta tag</li>";
echo "<li>Complete metadata should include Open Graph, Twitter Cards, and JSON-LD</li>";
echo "</ul>";
?>
