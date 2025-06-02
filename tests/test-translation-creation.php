<?php
/**
 * Translation Creation Tests for EZ Translate
 *
 * @package EZTranslate
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include shared test utilities
require_once __DIR__ . '/test-utilities.php';

use EZTranslate\Logger;
use EZTranslate\LanguageManager;
use EZTranslate\PostMetaManager;
use EZTranslate\RestAPI;





/**
 * Test Translation Creation functionality
 */
function ez_translate_test_translation_creation() {
    echo '<div class="card">';
    echo '<h2>' . __('Translation Creation Tests', 'ez-translate') . '</h2>';

    // Check error logs first
    ez_translate_check_error_logs();

    // Ensure we have test languages
    ez_translate_ensure_test_languages();

    $tests_passed = 0;
    $total_tests = 0;
    
    // Test 1: Check if REST API class exists and has create_translation method
    $total_tests++;
    echo '<h3>Test 1: REST API Translation Method</h3>';
    if (class_exists('EZTranslate\RestAPI') && method_exists('EZTranslate\RestAPI', 'create_translation')) {
        echo '<p style="color: green;">✅ REST API create_translation method exists</p>';
        $tests_passed++;
    } else {
        echo '<p style="color: red;">❌ REST API create_translation method not found</p>';
    }
    
    // Test 2: Create test post for translation
    $total_tests++;
    echo '<h3>Test 2: Create Test Post</h3>';
    $test_post_id = wp_insert_post([
        'post_title' => 'EZ Translate Test Post for Translation',
        'post_content' => 'This is test content for translation testing.',
        'post_status' => 'publish',
        'post_type' => 'post'
    ]);
    
    if ($test_post_id && !is_wp_error($test_post_id)) {
        echo '<p style="color: green;">✅ Test post created (ID: ' . $test_post_id . ')</p>';
        $tests_passed++;
        
        // Set original language for the test post
        PostMetaManager::set_post_language($test_post_id, 'en');
        echo '<p>Set original language to "en"</p>';
    } else {
        echo '<p style="color: red;">❌ Failed to create test post</p>';
        $test_post_id = null;
    }
    
    // Test 3: Test translation creation via REST API
    if ($test_post_id) {
        $total_tests++;
        echo '<h3>Test 3: Create Translation via REST API</h3>';
        
        try {
            $rest_api = new \EZTranslate\RestAPI();
            $fake_request = new \WP_REST_Request('POST', '/ez-translate/v1/create-translation/' . $test_post_id);
            $fake_request->set_param('id', $test_post_id);
            $fake_request->set_param('target_language', 'es');
            
            $result = $rest_api->create_translation($fake_request);
            
            if (is_wp_error($result)) {
                echo '<p style="color: red;">❌ Translation creation failed: ' . $result->get_error_message() . '</p>';
            } else {
                $data = $result->get_data();
                if ($data['success']) {
                    echo '<p style="color: green;">✅ Translation created successfully</p>';
                    echo '<p>Translation ID: ' . $data['data']['translation_id'] . '</p>';
                    echo '<p>Group ID: ' . $data['data']['group_id'] . '</p>';
                    $tests_passed++;
                    
                    // Store translation ID for cleanup
                    $translation_id = $data['data']['translation_id'];
                } else {
                    echo '<p style="color: red;">❌ Translation creation returned failure</p>';
                }
            }
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ Exception during translation creation: ' . $e->getMessage() . '</p>';
        }
    }
    
    // Test 4: Verify translation metadata
    if (isset($translation_id) && $translation_id) {
        $total_tests++;
        echo '<h3>Test 4: Verify Translation Metadata</h3>';
        
        $translation_meta = PostMetaManager::get_post_metadata($translation_id);
        
        if ($translation_meta['language'] === 'es') {
            echo '<p style="color: green;">✅ Translation language set correctly</p>';
            $tests_passed++;
        } else {
            echo '<p style="color: red;">❌ Translation language not set correctly</p>';
        }
        
        // Check if group ID is set and matches source
        $source_meta = PostMetaManager::get_post_metadata($test_post_id);
        if ($translation_meta['group'] && $translation_meta['group'] === $source_meta['group']) {
            echo '<p style="color: green;">✅ Translation group ID matches source</p>';
        } else {
            echo '<p style="color: orange;">⚠️ Translation group ID issue</p>';
        }
    }
    
    // Test 5: Test duplicate translation prevention
    if ($test_post_id && isset($translation_id) && $translation_id) {
        $total_tests++;
        echo '<h3>Test 5: Duplicate Translation Prevention</h3>';

        // Ensure test languages still exist
        ez_translate_ensure_test_languages();

        try {
            $rest_api = new \EZTranslate\RestAPI();
            $fake_request = new \WP_REST_Request('POST', '/ez-translate/v1/create-translation/' . $test_post_id);
            $fake_request->set_param('id', $test_post_id);
            $fake_request->set_param('target_language', 'es'); // Same language as before

            $result = $rest_api->create_translation($fake_request);

            if (is_wp_error($result) && $result->get_error_code() === 'translation_exists') {
                echo '<p style="color: green;">✅ Duplicate translation correctly prevented</p>';
                echo '<p>Error message: ' . $result->get_error_message() . '</p>';
                $tests_passed++;
            } else {
                echo '<p style="color: red;">❌ Duplicate translation not prevented</p>';
                if (is_wp_error($result)) {
                    echo '<p>Error code: ' . $result->get_error_code() . '</p>';
                    echo '<p>Error message: ' . $result->get_error_message() . '</p>';
                } else {
                    echo '<p>Result: ' . print_r($result->get_data(), true) . '</p>';
                }
            }
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ Exception during duplicate test: ' . $e->getMessage() . '</p>';
        }
    } else {
        echo '<h3>Test 5: Duplicate Translation Prevention</h3>';
        echo '<p style="color: orange;">⚠️ Skipped - no translation created in previous test</p>';
    }
    
    // Test 6: Test invalid language
    if ($test_post_id) {
        $total_tests++;
        echo '<h3>Test 6: Invalid Target Language</h3>';

        // Ensure test languages still exist
        ez_translate_ensure_test_languages();

        try {
            $rest_api = new \EZTranslate\RestAPI();
            $fake_request = new \WP_REST_Request('POST', '/ez-translate/v1/create-translation/' . $test_post_id);
            $fake_request->set_param('id', $test_post_id);
            $fake_request->set_param('target_language', 'invalid_lang');

            $result = $rest_api->create_translation($fake_request);

            if (is_wp_error($result) && $result->get_error_code() === 'invalid_target_language') {
                echo '<p style="color: green;">✅ Invalid language correctly rejected</p>';
                echo '<p>Error message: ' . $result->get_error_message() . '</p>';
                $tests_passed++;
            } else {
                echo '<p style="color: red;">❌ Invalid language not rejected</p>';
                if (is_wp_error($result)) {
                    echo '<p>Error code: ' . $result->get_error_code() . '</p>';
                    echo '<p>Error message: ' . $result->get_error_message() . '</p>';
                } else {
                    echo '<p>Result: ' . print_r($result->get_data(), true) . '</p>';
                }
            }
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ Exception during invalid language test: ' . $e->getMessage() . '</p>';
        }
    }
    
    // Test 7: Test translation content copying
    if (isset($translation_id) && $translation_id) {
        $total_tests++;
        echo '<h3>Test 7: Content Copying</h3>';
        
        $source_post = get_post($test_post_id);
        $translation_post = get_post($translation_id);
        
        if ($translation_post->post_content === $source_post->post_content) {
            echo '<p style="color: green;">✅ Content copied correctly</p>';
            $tests_passed++;
        } else {
            echo '<p style="color: red;">❌ Content not copied correctly</p>';
        }
    }
    
    // Cleanup test posts
    if ($test_post_id) {
        wp_delete_post($test_post_id, true);
        echo '<p>Cleaned up test post (ID: ' . $test_post_id . ')</p>';
    }
    
    if (isset($translation_id) && $translation_id) {
        wp_delete_post($translation_id, true);
        echo '<p>Cleaned up translation post (ID: ' . $translation_id . ')</p>';
    }
    
    // Summary
    echo '<h3>Test Summary</h3>';
    echo '<p><strong>Tests passed: ' . $tests_passed . '/' . $total_tests . '</strong></p>';
    
    if ($tests_passed === $total_tests) {
        echo '<p style="color: green; font-weight: bold;">🎉 All translation creation tests passed!</p>';
    } else {
        echo '<p style="color: red; font-weight: bold;">❌ Some tests failed. Please check the implementation.</p>';
    }
    
    echo '</div>';
}

/**
 * Display translation creation tests in admin
 */
function ez_translate_display_translation_creation_tests() {
    if (isset($_GET['run_ez_translate_translation_tests']) && $_GET['run_ez_translate_translation_tests'] === '1') {
        ez_translate_test_translation_creation();
    }
}

// Hook into admin
add_action('admin_init', 'ez_translate_display_translation_creation_tests');
