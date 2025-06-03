<?php
/**
 * SEO Title Functionality Tests
 *
 * Tests for verifying that custom SEO titles are properly applied to the <title> tag
 *
 * @package EZTranslate
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include required files
require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-frontend.php';

// Include shared test utilities
require_once __DIR__ . '/test-utilities.php';

use EZTranslate\LanguageManager;
use EZTranslate\Frontend;

/**
 * Display SEO title functionality tests
 *
 * @since 1.0.0
 */
function ez_translate_display_seo_title_tests() {
    echo '<h3>' . __('SEO Title Functionality Tests', 'ez-translate') . '</h3>';
    
    $results = ez_translate_run_seo_title_tests();
    
    echo '<div style="margin: 20px 0;">';
    foreach ($results as $result) {
        $status_class = $result['status'] === 'PASS' ? 'notice-success' : 'notice-error';
        $status_icon = $result['status'] === 'PASS' ? '✅' : '❌';
        echo '<div class="notice ' . $status_class . ' inline" style="margin: 5px 0; padding: 10px;">';
        echo '<p style="margin: 0;"><strong>' . $status_icon . ' ' . esc_html($result['test']) . ':</strong> ' . esc_html($result['message']) . '</p>';
        echo '</div>';
    }
    echo '</div>';
    
    // Summary
    $passed = array_filter($results, function($r) { return $r['status'] === 'PASS'; });
    $total = count($results);
    $passed_count = count($passed);
    
    echo '<div class="notice notice-info inline" style="margin-top: 20px; padding: 15px;">';
    echo '<h4 style="margin: 0 0 10px 0;">' . __('Test Summary', 'ez-translate') . '</h4>';
    echo '<p style="margin: 0;"><strong>' . sprintf(__('%d of %d tests passed', 'ez-translate'), $passed_count, $total) . '</strong></p>';
    if ($passed_count === $total) {
        echo '<p style="margin: 5px 0 0 0; color: #00a32a;">' . __('All SEO title functionality tests are working correctly!', 'ez-translate') . '</p>';
    } else {
        echo '<p style="margin: 5px 0 0 0; color: #d63638;">' . __('Some tests failed. Please check the implementation.', 'ez-translate') . '</p>';
    }
    echo '</div>';
}

/**
 * Run all SEO title functionality tests
 *
 * @return array Test results
 * @since 1.0.0
 */
function ez_translate_run_seo_title_tests() {
    $results = array();
    
    // Test 1: Document title filter with custom SEO title
    $results[] = test_document_title_filter_with_seo_title();
    
    // Test 2: Document title filter without SEO title
    $results[] = test_document_title_filter_without_seo_title();
    
    // Test 3: Document title filter for non-EZ-Translate pages
    $results[] = test_document_title_filter_non_ez_translate_pages();
    
    // Test 4: Meta description injection
    $results[] = test_meta_description_injection();
    
    // Test 5: Complete metadata generation
    $results[] = test_complete_metadata_generation();

    // Test 6: Site name translation
    $results[] = test_site_name_translation();

    return $results;
}

/**
 * Test 1: Document title filter with custom SEO title
 *
 * @return array Test result
 * @since 1.0.0
 */
function test_document_title_filter_with_seo_title() {
    try {
        // Clean up any existing test data
        cleanup_seo_test_data();

        // Create a test language first
        $language_data = array(
            'code' => 'test',
            'name' => 'Test Language',
            'slug' => 'test-language',
            'enabled' => true
        );

        $lang_result = LanguageManager::add_language($language_data);
        if (is_wp_error($lang_result)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Document Title Filter with SEO Title',
                'status' => 'FAIL',
                'message' => 'Failed to create test language: ' . $lang_result->get_error_message()
            );
        }

        // Create a test page with language and SEO title
        $post_id = wp_insert_post(array(
            'post_title' => 'Original Page Title',
            'post_content' => 'Test page content',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));

        if (!$post_id || is_wp_error($post_id)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Document Title Filter with SEO Title',
                'status' => 'FAIL',
                'message' => 'Failed to create test page'
            );
        }

        // Set language and SEO metadata
        update_post_meta($post_id, '_ez_translate_language', 'test');
        update_post_meta($post_id, '_ez_translate_seo_title', 'Custom SEO Title for Testing');
        update_post_meta($post_id, '_ez_translate_seo_description', 'Custom SEO description for testing');
        
        // Create frontend instance in test mode
        $frontend = new Frontend(true); // true = test mode
        
        // Simulate the global $post
        global $post;
        $original_post = $post;
        $post = get_post($post_id);
        
        // Debug: Verify metadata was saved correctly
        $saved_language = get_post_meta($post_id, '_ez_translate_language', true);
        $saved_seo_title = get_post_meta($post_id, '_ez_translate_seo_title', true);

        if (empty($saved_language)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Document Title Filter with SEO Title',
                'status' => 'FAIL',
                'message' => 'Language metadata was not saved correctly'
            );
        }

        if (empty($saved_seo_title)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Document Title Filter with SEO Title',
                'status' => 'FAIL',
                'message' => 'SEO title metadata was not saved correctly'
            );
        }

        // Debug: Verify language exists in database
        $language_exists = LanguageManager::get_language('test');
        if (!$language_exists) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Document Title Filter with SEO Title',
                'status' => 'FAIL',
                'message' => 'Test language was not created in database'
            );
        }

        // Test the document title filter
        $title_parts = array(
            'title' => 'Original Page Title',
            'site' => 'Test Site'
        );

        $filtered_title_parts = $frontend->filter_document_title($title_parts);

        // Restore original post
        $post = $original_post;

        // Verify the title was changed
        if ($filtered_title_parts['title'] !== 'Custom SEO Title for Testing') {
            cleanup_seo_test_data();
            return array(
                'test' => 'Document Title Filter with SEO Title',
                'status' => 'FAIL',
                'message' => 'SEO title was not applied. Expected: "Custom SEO Title for Testing", Got: "' . $filtered_title_parts['title'] . '". Language: "' . $saved_language . '", SEO Title: "' . $saved_seo_title . '"'
            );
        }
        
        // Clean up
        cleanup_seo_test_data();
        
        return array(
            'test' => 'Document Title Filter with SEO Title',
            'status' => 'PASS',
            'message' => 'Custom SEO title correctly applied to document title'
        );
        
    } catch (Exception $e) {
        cleanup_seo_test_data();
        return array(
            'test' => 'Document Title Filter with SEO Title',
            'status' => 'FAIL',
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

/**
 * Test 2: Document title filter without SEO title
 *
 * @return array Test result
 * @since 1.0.0
 */
function test_document_title_filter_without_seo_title() {
    try {
        // Clean up any existing test data
        cleanup_seo_test_data();

        // Create a test language first
        $language_data = array(
            'code' => 'test',
            'name' => 'Test Language',
            'slug' => 'test-language',
            'enabled' => true
        );

        $lang_result = LanguageManager::add_language($language_data);
        if (is_wp_error($lang_result)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Document Title Filter without SEO Title',
                'status' => 'FAIL',
                'message' => 'Failed to create test language: ' . $lang_result->get_error_message()
            );
        }

        // Create a test page with language but no SEO title
        $post_id = wp_insert_post(array(
            'post_title' => 'Original Page Title Without SEO',
            'post_content' => 'Test page content',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));

        if (!$post_id || is_wp_error($post_id)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Document Title Filter without SEO Title',
                'status' => 'FAIL',
                'message' => 'Failed to create test page'
            );
        }

        // Set language but no SEO title
        update_post_meta($post_id, '_ez_translate_language', 'test');
        // Intentionally not setting _ez_translate_seo_title
        
        // Create frontend instance in test mode
        $frontend = new Frontend(true); // true = test mode
        
        // Simulate the global $post
        global $post;
        $original_post = $post;
        $post = get_post($post_id);
        
        // Test the document title filter
        $title_parts = array(
            'title' => 'Original Page Title Without SEO',
            'site' => 'Test Site'
        );
        
        $filtered_title_parts = $frontend->filter_document_title($title_parts);
        
        // Restore original post
        $post = $original_post;
        
        // Verify the title was NOT changed (should remain original)
        if ($filtered_title_parts['title'] !== 'Original Page Title Without SEO') {
            cleanup_seo_test_data();
            return array(
                'test' => 'Document Title Filter without SEO Title',
                'status' => 'FAIL',
                'message' => 'Title was unexpectedly changed when no SEO title was set'
            );
        }
        
        // Clean up
        cleanup_seo_test_data();
        
        return array(
            'test' => 'Document Title Filter without SEO Title',
            'status' => 'PASS',
            'message' => 'Original title preserved when no custom SEO title is set'
        );
        
    } catch (Exception $e) {
        cleanup_seo_test_data();
        return array(
            'test' => 'Document Title Filter without SEO Title',
            'status' => 'FAIL',
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

/**
 * Test 3: Document title filter for non-EZ-Translate pages
 *
 * @return array Test result
 * @since 1.0.0
 */
function test_document_title_filter_non_ez_translate_pages() {
    try {
        // Clean up any existing test data
        cleanup_seo_test_data();
        
        // Create a regular page without EZ Translate metadata
        $post_id = wp_insert_post(array(
            'post_title' => 'Regular Page Title',
            'post_content' => 'Regular page content',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
        
        if (!$post_id || is_wp_error($post_id)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Document Title Filter for Non-EZ-Translate Pages',
                'status' => 'FAIL',
                'message' => 'Failed to create test page'
            );
        }
        
        // Intentionally not setting any EZ Translate metadata
        
        // Create frontend instance in test mode
        $frontend = new Frontend(true); // true = test mode
        
        // Simulate the global $post
        global $post;
        $original_post = $post;
        $post = get_post($post_id);
        
        // Test the document title filter
        $title_parts = array(
            'title' => 'Regular Page Title',
            'site' => 'Test Site'
        );
        
        $filtered_title_parts = $frontend->filter_document_title($title_parts);
        
        // Restore original post
        $post = $original_post;
        
        // Verify the title was NOT changed (should remain original)
        if ($filtered_title_parts['title'] !== 'Regular Page Title') {
            cleanup_seo_test_data();
            return array(
                'test' => 'Document Title Filter for Non-EZ-Translate Pages',
                'status' => 'FAIL',
                'message' => 'Title was unexpectedly changed for non-EZ-Translate page'
            );
        }
        
        // Clean up
        cleanup_seo_test_data();
        
        return array(
            'test' => 'Document Title Filter for Non-EZ-Translate Pages',
            'status' => 'PASS',
            'message' => 'Non-EZ-Translate pages are not affected by the title filter'
        );
        
    } catch (Exception $e) {
        cleanup_seo_test_data();
        return array(
            'test' => 'Document Title Filter for Non-EZ-Translate Pages',
            'status' => 'FAIL',
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

/**
 * Test 4: Meta description injection
 *
 * @return array Test result
 * @since 1.0.0
 */
function test_meta_description_injection() {
    try {
        // Clean up any existing test data
        cleanup_seo_test_data();

        // Create a test language first
        $language_data = array(
            'code' => 'test',
            'name' => 'Test Language',
            'slug' => 'test-language',
            'enabled' => true
        );

        $lang_result = LanguageManager::add_language($language_data);
        if (is_wp_error($lang_result)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Meta Description Injection',
                'status' => 'FAIL',
                'message' => 'Failed to create test language: ' . $lang_result->get_error_message()
            );
        }

        // Create a test page with language and SEO description
        $post_id = wp_insert_post(array(
            'post_title' => 'Meta Description Test Page',
            'post_content' => 'Test page content',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));

        if (!$post_id || is_wp_error($post_id)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Meta Description Injection',
                'status' => 'FAIL',
                'message' => 'Failed to create test page'
            );
        }

        // Set language and SEO metadata
        update_post_meta($post_id, '_ez_translate_language', 'test');
        update_post_meta($post_id, '_ez_translate_seo_description', 'Custom meta description for testing the injection functionality.');

        // Create frontend instance in test mode
        $frontend = new Frontend(true); // true = test mode

        // Simulate the global $post
        global $post;
        $original_post = $post;
        $post = get_post($post_id);

        // Capture the output of inject_meta_description
        ob_start();
        $frontend->inject_meta_description();
        $output = ob_get_clean();

        // Restore original post
        $post = $original_post;

        // Verify the meta description was injected
        if (strpos($output, 'Custom meta description for testing the injection functionality.') === false) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Meta Description Injection',
                'status' => 'FAIL',
                'message' => 'Meta description was not injected correctly. Output: ' . $output
            );
        }

        // Verify it's a proper meta tag
        if (strpos($output, '<meta name="description"') === false) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Meta Description Injection',
                'status' => 'FAIL',
                'message' => 'Meta description tag format is incorrect'
            );
        }

        // Clean up
        cleanup_seo_test_data();

        return array(
            'test' => 'Meta Description Injection',
            'status' => 'PASS',
            'message' => 'Meta description correctly injected with proper HTML format'
        );

    } catch (Exception $e) {
        cleanup_seo_test_data();
        return array(
            'test' => 'Meta Description Injection',
            'status' => 'FAIL',
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

/**
 * Test 5: Complete metadata generation
 *
 * @return array Test result
 * @since 1.0.0
 */
function test_complete_metadata_generation() {
    try {
        // Clean up any existing test data
        cleanup_seo_test_data();

        // Create a language first
        $language_data = array(
            'code' => 'test',
            'name' => 'SEO Test Language',
            'slug' => 'seo-test-language',
            'enabled' => true
        );

        $lang_result = LanguageManager::add_language($language_data);
        if (is_wp_error($lang_result)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Complete Metadata Generation',
                'status' => 'FAIL',
                'message' => 'Failed to create test language: ' . $lang_result->get_error_message()
            );
        }

        // Create a test page with complete SEO metadata
        $post_id = wp_insert_post(array(
            'post_title' => 'Complete Metadata Test Page',
            'post_content' => 'Test page content for complete metadata generation',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));

        if (!$post_id || is_wp_error($post_id)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Complete Metadata Generation',
                'status' => 'FAIL',
                'message' => 'Failed to create test page'
            );
        }

        // Set complete SEO metadata
        update_post_meta($post_id, '_ez_translate_language', 'test');
        update_post_meta($post_id, '_ez_translate_seo_title', 'Complete SEO Title Test');
        update_post_meta($post_id, '_ez_translate_seo_description', 'Complete SEO description for testing all metadata generation functionality.');

        // Create frontend instance in test mode
        $frontend = new Frontend(true); // true = test mode

        // Simulate the global $post
        global $post;
        $original_post = $post;
        $post = get_post($post_id);

        // Capture the output of override_head_metadata
        ob_start();
        $frontend->override_head_metadata();
        $output = ob_get_clean();

        // Restore original post
        $post = $original_post;

        // Verify Open Graph title is present
        if (strpos($output, 'og:title') === false || strpos($output, 'Complete SEO Title Test') === false) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Complete Metadata Generation',
                'status' => 'FAIL',
                'message' => 'Open Graph title not found in metadata output'
            );
        }

        // Verify Open Graph description is present
        if (strpos($output, 'og:description') === false || strpos($output, 'Complete SEO description for testing') === false) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Complete Metadata Generation',
                'status' => 'FAIL',
                'message' => 'Open Graph description not found in metadata output'
            );
        }

        // Verify Twitter Card metadata is present
        if (strpos($output, 'twitter:title') === false || strpos($output, 'twitter:description') === false) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Complete Metadata Generation',
                'status' => 'FAIL',
                'message' => 'Twitter Card metadata not found in output'
            );
        }

        // Verify JSON-LD structured data is present
        if (strpos($output, 'application/ld+json') === false || strpos($output, 'schema.org') === false) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Complete Metadata Generation',
                'status' => 'FAIL',
                'message' => 'JSON-LD structured data not found in output'
            );
        }

        // Clean up
        cleanup_seo_test_data();

        return array(
            'test' => 'Complete Metadata Generation',
            'status' => 'PASS',
            'message' => 'Complete metadata generated successfully with Open Graph, Twitter Cards, and JSON-LD'
        );

    } catch (Exception $e) {
        cleanup_seo_test_data();
        return array(
            'test' => 'Complete Metadata Generation',
            'status' => 'FAIL',
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

/**
 * Test 6: Site name translation
 *
 * @return array Test result
 * @since 1.0.0
 */
function test_site_name_translation() {
    try {
        // Clean up any existing test data
        cleanup_seo_test_data();

        // Create a language with custom site name
        $language_data = array(
            'code' => 'test',
            'name' => 'SEO Test Language',
            'slug' => 'seo-test-language',
            'enabled' => true,
            'site_name' => 'WordPress Specialist'
        );

        $lang_result = LanguageManager::add_language($language_data);
        if (is_wp_error($lang_result)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Site Name Translation',
                'status' => 'FAIL',
                'message' => 'Failed to create test language: ' . $lang_result->get_error_message()
            );
        }

        // Create a test page with language assigned
        $post_id = wp_insert_post(array(
            'post_title' => 'Site Name Test Page',
            'post_content' => 'Test page content',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));

        if (!$post_id || is_wp_error($post_id)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Site Name Translation',
                'status' => 'FAIL',
                'message' => 'Failed to create test page'
            );
        }

        // Set language metadata
        update_post_meta($post_id, '_ez_translate_language', 'test');

        // Debug: Verify metadata and language
        $saved_language = get_post_meta($post_id, '_ez_translate_language', true);
        $language_data = LanguageManager::get_language('test');
        $site_metadata = LanguageManager::get_language_site_metadata('test');

        if (empty($saved_language)) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Site Name Translation',
                'status' => 'FAIL',
                'message' => 'Language metadata was not saved correctly'
            );
        }

        if (!$language_data) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Site Name Translation',
                'status' => 'FAIL',
                'message' => 'Test language was not found in database'
            );
        }

        if (empty($site_metadata['site_name'])) {
            cleanup_seo_test_data();
            return array(
                'test' => 'Site Name Translation',
                'status' => 'FAIL',
                'message' => 'Site name was not saved in language metadata. Site metadata: ' . print_r($site_metadata, true)
            );
        }

        // Create frontend instance in test mode
        $frontend = new Frontend(true); // true = test mode

        // Simulate the global $post
        global $post;
        $original_post = $post;
        $post = get_post($post_id);

        // Test the document title filter with site name
        $title_parts = array(
            'title' => 'Site Name Test Page',
            'site' => 'Original Site Name'
        );

        $filtered_title_parts = $frontend->filter_document_title($title_parts);

        // Restore original post
        $post = $original_post;

        // Verify the site name was changed
        if ($filtered_title_parts['site'] !== 'WordPress Specialist') {
            cleanup_seo_test_data();
            return array(
                'test' => 'Site Name Translation',
                'status' => 'FAIL',
                'message' => 'Site name was not translated. Expected: "WordPress Specialist", Got: "' . $filtered_title_parts['site'] . '". Language: "' . $saved_language . '", Site metadata: ' . print_r($site_metadata, true)
            );
        }

        // Verify the page title remained unchanged (no custom SEO title)
        if ($filtered_title_parts['title'] !== 'Site Name Test Page') {
            cleanup_seo_test_data();
            return array(
                'test' => 'Site Name Translation',
                'status' => 'FAIL',
                'message' => 'Page title was unexpectedly changed when only site name should be translated'
            );
        }

        // Clean up
        cleanup_seo_test_data();

        return array(
            'test' => 'Site Name Translation',
            'status' => 'PASS',
            'message' => 'Site name correctly translated based on language configuration'
        );

    } catch (Exception $e) {
        cleanup_seo_test_data();
        return array(
            'test' => 'Site Name Translation',
            'status' => 'FAIL',
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

/**
 * Clean up SEO test data
 *
 * @since 1.0.0
 */
function cleanup_seo_test_data() {
    // Remove test language
    LanguageManager::delete_language('test');

    // Remove test posts
    $test_posts = get_posts(array(
        'post_type' => 'page',
        'meta_query' => array(
            array(
                'key' => '_ez_translate_language',
                'value' => 'test',
                'compare' => '='
            )
        ),
        'numberposts' => -1
    ));
    
    foreach ($test_posts as $post) {
        wp_delete_post($post->ID, true);
    }
    
    // Also remove any posts with test titles
    $test_titles = array(
        'Original Page Title',
        'Original Page Title Without SEO',
        'Regular Page Title'
    );
    
    foreach ($test_titles as $title) {
        $posts = get_posts(array(
            'post_type' => 'page',
            'title' => $title,
            'numberposts' => -1
        ));
        
        foreach ($posts as $post) {
            wp_delete_post($post->ID, true);
        }
    }
}
