<?php
/**
 * Landing Page Management Tests
 *
 * Tests for managing landing pages from the admin settings
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

// Include shared test utilities
require_once __DIR__ . '/test-utilities.php';

use EZTranslate\LanguageManager;

/**
 * Display landing page management tests
 *
 * @since 1.0.0
 */
function ez_translate_display_landing_page_management_tests() {
    echo '<h3>' . __('Landing Page Management Tests', 'ez-translate') . '</h3>';
    
    $results = ez_translate_run_landing_page_management_tests();
    
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
        echo '<p style="margin: 5px 0 0 0; color: #00a32a;">' . __('All landing page management tests are working correctly!', 'ez-translate') . '</p>';
    } else {
        echo '<p style="margin: 5px 0 0 0; color: #d63638;">' . __('Some tests failed. Please check the implementation.', 'ez-translate') . '</p>';
    }
    echo '</div>';
}

/**
 * Run all landing page management tests
 *
 * @return array Test results
 * @since 1.0.0
 */
function ez_translate_run_landing_page_management_tests() {
    $results = array();
    
    // Test 1: Get landing page for language
    $results[] = test_get_landing_page_for_language();
    
    // Test 2: Update landing page SEO
    $results[] = test_update_landing_page_seo();
    
    // Test 3: Landing page detection with multiple pages
    $results[] = test_landing_page_detection_multiple_pages();
    
    // Test 4: SEO update validation
    $results[] = test_seo_update_validation();
    
    // Test 5: Landing page info completeness
    $results[] = test_landing_page_info_completeness();
    
    return $results;
}

/**
 * Test 1: Get landing page for language
 *
 * @return array Test result
 * @since 1.0.0
 */
function test_get_landing_page_for_language() {
    try {
        // Clean up any existing test data
        cleanup_test_data();
        
        // Create a language with landing page
        $language_data = array(
            'code' => 'mgmt1',
            'name' => 'Management Test 1',
            'slug' => 'management-test-1',
            'enabled' => true
        );
        
        $landing_page_data = array(
            'title' => 'Management Test Landing Page',
            'description' => 'Testing landing page management functionality.',
            'slug' => 'mgmt-test-landing',
            'status' => 'draft'
        );
        
        $result = LanguageManager::add_language($language_data, $landing_page_data);
        
        if (is_wp_error($result)) {
            cleanup_test_data();
            return array(
                'test' => 'Get Landing Page for Language',
                'status' => 'FAIL',
                'message' => 'Failed to create test language: ' . $result->get_error_message()
            );
        }
        
        // Get landing page info
        $landing_page = LanguageManager::get_landing_page_for_language('mgmt1');
        
        if (!$landing_page) {
            cleanup_test_data();
            return array(
                'test' => 'Get Landing Page for Language',
                'status' => 'FAIL',
                'message' => 'Landing page not found for language'
            );
        }
        
        // Verify landing page data structure
        $required_fields = array('post_id', 'title', 'slug', 'status', 'edit_url', 'view_url', 'seo_title', 'seo_description', 'group_id');
        foreach ($required_fields as $field) {
            if (!array_key_exists($field, $landing_page)) {
                cleanup_test_data();
                return array(
                    'test' => 'Get Landing Page for Language',
                    'status' => 'FAIL',
                    'message' => 'Missing field in landing page data: ' . $field
                );
            }
        }
        
        // Verify data values
        if ($landing_page['title'] !== 'Management Test Landing Page') {
            cleanup_test_data();
            return array(
                'test' => 'Get Landing Page for Language',
                'status' => 'FAIL',
                'message' => 'Landing page title mismatch'
            );
        }
        
        // Clean up
        cleanup_test_data();
        
        return array(
            'test' => 'Get Landing Page for Language',
            'status' => 'PASS',
            'message' => 'Landing page retrieved successfully with complete data structure'
        );
        
    } catch (Exception $e) {
        cleanup_test_data();
        return array(
            'test' => 'Get Landing Page for Language',
            'status' => 'FAIL',
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

/**
 * Test 2: Update landing page SEO
 *
 * @return array Test result
 * @since 1.0.0
 */
function test_update_landing_page_seo() {
    try {
        // Clean up any existing test data
        cleanup_test_data();
        
        // Create a language with landing page
        $language_data = array(
            'code' => 'mgmt2',
            'name' => 'Management Test 2',
            'slug' => 'management-test-2',
            'enabled' => true
        );
        
        $landing_page_data = array(
            'title' => 'SEO Update Test Page',
            'description' => 'Original SEO description.',
            'slug' => 'seo-update-test',
            'status' => 'draft'
        );
        
        $result = LanguageManager::add_language($language_data, $landing_page_data);
        
        if (is_wp_error($result)) {
            cleanup_test_data();
            return array(
                'test' => 'Update Landing Page SEO',
                'status' => 'FAIL',
                'message' => 'Failed to create test language: ' . $result->get_error_message()
            );
        }
        
        $post_id = $result['landing_page_id'];
        
        // Update SEO data
        $new_seo_data = array(
            'title' => 'Updated SEO Title for Management Test',
            'description' => 'Updated SEO description with new content for testing the management functionality.'
        );
        
        $update_result = LanguageManager::update_landing_page_seo($post_id, $new_seo_data);
        
        if (is_wp_error($update_result)) {
            cleanup_test_data();
            return array(
                'test' => 'Update Landing Page SEO',
                'status' => 'FAIL',
                'message' => 'Failed to update SEO: ' . $update_result->get_error_message()
            );
        }
        
        // Verify the updates
        $updated_title = get_post_meta($post_id, '_ez_translate_seo_title', true);
        $updated_description = get_post_meta($post_id, '_ez_translate_seo_description', true);
        
        if ($updated_title !== $new_seo_data['title']) {
            cleanup_test_data();
            return array(
                'test' => 'Update Landing Page SEO',
                'status' => 'FAIL',
                'message' => 'SEO title was not updated correctly'
            );
        }
        
        if ($updated_description !== $new_seo_data['description']) {
            cleanup_test_data();
            return array(
                'test' => 'Update Landing Page SEO',
                'status' => 'FAIL',
                'message' => 'SEO description was not updated correctly'
            );
        }
        
        // Clean up
        cleanup_test_data();
        
        return array(
            'test' => 'Update Landing Page SEO',
            'status' => 'PASS',
            'message' => 'SEO metadata updated successfully'
        );
        
    } catch (Exception $e) {
        cleanup_test_data();
        return array(
            'test' => 'Update Landing Page SEO',
            'status' => 'FAIL',
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

/**
 * Test 3: Landing page detection with multiple pages
 *
 * @return array Test result
 * @since 1.0.0
 */
function test_landing_page_detection_multiple_pages() {
    try {
        // Clean up any existing test data
        cleanup_test_data();

        // Create a language
        $language_data = array(
            'code' => 'mgmt3',
            'name' => 'Management Test 3',
            'slug' => 'management-test-3',
            'enabled' => true
        );

        $result = LanguageManager::add_language($language_data);

        if (is_wp_error($result)) {
            cleanup_test_data();
            return array(
                'test' => 'Landing Page Detection Multiple Pages',
                'status' => 'FAIL',
                'message' => 'Failed to create test language: ' . $result->get_error_message()
            );
        }

        // Create multiple pages with the same language
        $post_id_1 = wp_insert_post(array(
            'post_title' => 'First Page',
            'post_content' => 'First page content',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));

        $post_id_2 = wp_insert_post(array(
            'post_title' => 'Second Page',
            'post_content' => 'Second page content',
            'post_status' => 'draft',
            'post_type' => 'page'
        ));

        // Assign language to both pages
        update_post_meta($post_id_1, '_ez_translate_language', 'mgmt3');
        update_post_meta($post_id_1, '_ez_translate_seo_title', 'First Page SEO Title');
        update_post_meta($post_id_1, '_ez_translate_seo_description', 'First page SEO description');

        update_post_meta($post_id_2, '_ez_translate_language', 'mgmt3');
        update_post_meta($post_id_2, '_ez_translate_seo_title', 'Second Page SEO Title');
        update_post_meta($post_id_2, '_ez_translate_seo_description', 'Second page SEO description');

        // Get landing page (should return the first one found)
        $landing_page = LanguageManager::get_landing_page_for_language('mgmt3');

        if (!$landing_page) {
            cleanup_test_data();
            return array(
                'test' => 'Landing Page Detection Multiple Pages',
                'status' => 'FAIL',
                'message' => 'No landing page found when multiple pages exist'
            );
        }

        // Should return one of the pages (implementation returns first found)
        if (!in_array($landing_page['post_id'], array($post_id_1, $post_id_2))) {
            cleanup_test_data();
            return array(
                'test' => 'Landing Page Detection Multiple Pages',
                'status' => 'FAIL',
                'message' => 'Returned landing page is not one of the expected pages'
            );
        }

        // Clean up
        cleanup_test_data();

        return array(
            'test' => 'Landing Page Detection Multiple Pages',
            'status' => 'PASS',
            'message' => 'Landing page detection works correctly with multiple pages'
        );

    } catch (Exception $e) {
        cleanup_test_data();
        return array(
            'test' => 'Landing Page Detection Multiple Pages',
            'status' => 'FAIL',
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

/**
 * Test 4: SEO update validation
 *
 * @return array Test result
 * @since 1.0.0
 */
function test_seo_update_validation() {
    try {
        // Test with invalid post ID
        $result = LanguageManager::update_landing_page_seo(0, array('title' => 'Test', 'description' => 'Test'));

        if (!is_wp_error($result)) {
            return array(
                'test' => 'SEO Update Validation',
                'status' => 'FAIL',
                'message' => 'Should return error for invalid post ID'
            );
        }

        // Test with non-existent post
        $result = LanguageManager::update_landing_page_seo(999999, array('title' => 'Test', 'description' => 'Test'));

        if (!is_wp_error($result)) {
            return array(
                'test' => 'SEO Update Validation',
                'status' => 'FAIL',
                'message' => 'Should return error for non-existent post'
            );
        }

        // Create a regular post (not a translation page)
        $post_id = wp_insert_post(array(
            'post_title' => 'Regular Post',
            'post_content' => 'Regular post content',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));

        // Try to update SEO without language metadata
        $result = LanguageManager::update_landing_page_seo($post_id, array('title' => 'Test', 'description' => 'Test'));

        if (!is_wp_error($result)) {
            wp_delete_post($post_id, true);
            return array(
                'test' => 'SEO Update Validation',
                'status' => 'FAIL',
                'message' => 'Should return error for non-translation page'
            );
        }

        // Clean up
        wp_delete_post($post_id, true);

        return array(
            'test' => 'SEO Update Validation',
            'status' => 'PASS',
            'message' => 'SEO update validation works correctly'
        );

    } catch (Exception $e) {
        return array(
            'test' => 'SEO Update Validation',
            'status' => 'FAIL',
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

/**
 * Test 5: Landing page info completeness
 *
 * @return array Test result
 * @since 1.0.0
 */
function test_landing_page_info_completeness() {
    try {
        // Clean up any existing test data
        cleanup_test_data();

        // Create a language with landing page
        $language_data = array(
            'code' => 'mgmt5',
            'name' => 'Management Test 5',
            'slug' => 'management-test-5',
            'enabled' => true
        );

        $landing_page_data = array(
            'title' => 'Completeness Test Page',
            'description' => 'Testing completeness of landing page info.',
            'slug' => 'completeness-test',
            'status' => 'publish'
        );

        $result = LanguageManager::add_language($language_data, $landing_page_data);

        if (is_wp_error($result)) {
            cleanup_test_data();
            return array(
                'test' => 'Landing Page Info Completeness',
                'status' => 'FAIL',
                'message' => 'Failed to create test language: ' . $result->get_error_message()
            );
        }

        // Get landing page info
        $landing_page = LanguageManager::get_landing_page_for_language('mgmt5');

        if (!$landing_page) {
            cleanup_test_data();
            return array(
                'test' => 'Landing Page Info Completeness',
                'status' => 'FAIL',
                'message' => 'Landing page not found'
            );
        }

        // Verify URLs are properly formatted
        if (!filter_var($landing_page['view_url'], FILTER_VALIDATE_URL)) {
            cleanup_test_data();
            return array(
                'test' => 'Landing Page Info Completeness',
                'status' => 'FAIL',
                'message' => 'View URL is not a valid URL'
            );
        }

        if (!filter_var($landing_page['edit_url'], FILTER_VALIDATE_URL)) {
            cleanup_test_data();
            return array(
                'test' => 'Landing Page Info Completeness',
                'status' => 'FAIL',
                'message' => 'Edit URL is not a valid URL'
            );
        }

        // Verify edit URL contains correct post ID
        if (strpos($landing_page['edit_url'], 'post=' . $landing_page['post_id']) === false) {
            cleanup_test_data();
            return array(
                'test' => 'Landing Page Info Completeness',
                'status' => 'FAIL',
                'message' => 'Edit URL does not contain correct post ID'
            );
        }

        // Verify status matches
        $post = get_post($landing_page['post_id']);
        if ($post->post_status !== $landing_page['status']) {
            cleanup_test_data();
            return array(
                'test' => 'Landing Page Info Completeness',
                'status' => 'FAIL',
                'message' => 'Status mismatch between post and landing page info'
            );
        }

        // Clean up
        cleanup_test_data();

        return array(
            'test' => 'Landing Page Info Completeness',
            'status' => 'PASS',
            'message' => 'Landing page info is complete and accurate'
        );

    } catch (Exception $e) {
        cleanup_test_data();
        return array(
            'test' => 'Landing Page Info Completeness',
            'status' => 'FAIL',
            'message' => 'Exception: ' . $e->getMessage()
        );
    }
}

/**
 * Clean up test data
 *
 * @since 1.0.0
 */
function cleanup_test_data() {
    // Remove test languages
    $test_codes = array('mgmt1', 'mgmt2', 'mgmt3', 'mgmt4', 'mgmt5');
    foreach ($test_codes as $code) {
        LanguageManager::delete_language($code);
    }
    
    // Remove any test posts
    $test_posts = get_posts(array(
        'post_type' => 'page',
        'meta_query' => array(
            array(
                'key' => '_ez_translate_language',
                'value' => $test_codes,
                'compare' => 'IN'
            )
        ),
        'numberposts' => -1
    ));
    
    foreach ($test_posts as $post) {
        wp_delete_post($post->ID, true);
    }
}
