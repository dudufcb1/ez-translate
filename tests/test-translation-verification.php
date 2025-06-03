<?php
/**
 * Translation Verification Tests
 * 
 * Tests for verifying existing translations functionality
 * 
 * @package EZTranslate
 * @subpackage Tests
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translation Verification Test Suite
 * 
 * @since 1.0.0
 */
class EZTranslateTranslationVerificationTests {

    /**
     * Run all translation verification tests
     *
     * @return array Test results
     * @since 1.0.0
     */
    public static function run_all_tests() {
        $results = array();

        $results[] = self::test_verify_translations_endpoint();
        $results[] = self::test_existing_translations_detection();
        $results[] = self::test_available_languages_filtering();
        $results[] = self::test_auto_detection_integration();
        $results[] = self::test_translation_group_membership();

        return $results;
    }

    /**
     * Test verify translations REST endpoint
     *
     * @return array Test result
     * @since 1.0.0
     */
    public static function test_verify_translations_endpoint() {
        try {
            // Create test posts
            $original_post = wp_insert_post(array(
                'post_title' => 'Test Original Post',
                'post_content' => 'This is a test post in English.',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            if (is_wp_error($original_post)) {
                return array(
                    'test' => 'Verify Translations Endpoint',
                    'status' => 'FAIL',
                    'message' => 'Failed to create test post'
                );
            }

            // Set up translation metadata
            $group_id = 'test_group_' . time();
            update_post_meta($original_post, '_ez_translate_language', 'en');
            update_post_meta($original_post, '_ez_translate_group', $group_id);

            // Create a translation
            $translation_post = wp_insert_post(array(
                'post_title' => 'Publicación de Prueba',
                'post_content' => 'Esta es una publicación de prueba en español.',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            update_post_meta($translation_post, '_ez_translate_language', 'es');
            update_post_meta($translation_post, '_ez_translate_group', $group_id);
            update_post_meta($translation_post, '_ez_translate_original_id', $original_post);

            // Test the REST API endpoint
            $rest_api = new \EZTranslate\RestAPI();
            $request = new WP_REST_Request('GET', '/ez-translate/v1/verify-translations/' . $original_post);
            $request->set_param('id', $original_post);

            $response = $rest_api->verify_existing_translations($request);

            if (is_wp_error($response)) {
                return array(
                    'test' => 'Verify Translations Endpoint',
                    'status' => 'FAIL',
                    'message' => 'REST API returned error: ' . $response->get_error_message()
                );
            }

            $data = $response->get_data();

            // Verify response structure
            $required_fields = array('source_post_id', 'source_language', 'existing_translations', 'available_languages');
            foreach ($required_fields as $field) {
                if (!isset($data[$field])) {
                    return array(
                        'test' => 'Verify Translations Endpoint',
                        'status' => 'FAIL',
                        'message' => "Missing required field: {$field}"
                    );
                }
            }

            // Verify that Spanish translation is detected
            $spanish_found = false;
            foreach ($data['existing_translations'] as $translation) {
                if ($translation['language'] === 'es') {
                    $spanish_found = true;
                    break;
                }
            }

            if (!$spanish_found) {
                return array(
                    'test' => 'Verify Translations Endpoint',
                    'status' => 'FAIL',
                    'message' => 'Spanish translation not detected in existing translations'
                );
            }

            // Cleanup
            wp_delete_post($original_post, true);
            wp_delete_post($translation_post, true);

            return array(
                'test' => 'Verify Translations Endpoint',
                'status' => 'PASS',
                'message' => 'REST endpoint correctly identifies existing translations'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Verify Translations Endpoint',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test existing translations detection
     *
     * @return array Test result
     * @since 1.0.0
     */
    public static function test_existing_translations_detection() {
        try {
            // Create test posts with multiple translations
            $original_post = wp_insert_post(array(
                'post_title' => 'Multi-language Test Post',
                'post_content' => 'This post will have multiple translations.',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            $group_id = 'multi_test_' . time();
            update_post_meta($original_post, '_ez_translate_language', 'en');
            update_post_meta($original_post, '_ez_translate_group', $group_id);

            // Create Spanish translation
            $spanish_post = wp_insert_post(array(
                'post_title' => 'Publicación de Prueba Multiidioma',
                'post_content' => 'Esta publicación tendrá múltiples traducciones.',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            update_post_meta($spanish_post, '_ez_translate_language', 'es');
            update_post_meta($spanish_post, '_ez_translate_group', $group_id);

            // Create Portuguese translation
            $portuguese_post = wp_insert_post(array(
                'post_title' => 'Post de Teste Multilíngue',
                'post_content' => 'Este post terá múltiplas traduções.',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            update_post_meta($portuguese_post, '_ez_translate_language', 'pt');
            update_post_meta($portuguese_post, '_ez_translate_group', $group_id);

            // Test detection
            $rest_api = new \EZTranslate\RestAPI();
            $request = new WP_REST_Request('GET', '/ez-translate/v1/verify-translations/' . $original_post);
            $request->set_param('id', $original_post);

            $response = $rest_api->verify_existing_translations($request);
            $data = $response->get_data();

            // Should detect 2 existing translations (Spanish and Portuguese)
            if (count($data['existing_translations']) !== 2) {
                return array(
                    'test' => 'Existing Translations Detection',
                    'status' => 'FAIL',
                    'message' => 'Expected 2 translations, found ' . count($data['existing_translations'])
                );
            }

            // Verify languages are correctly identified
            $detected_languages = array();
            foreach ($data['existing_translations'] as $translation) {
                $detected_languages[] = $translation['language'];
            }

            if (!in_array('es', $detected_languages) || !in_array('pt', $detected_languages)) {
                return array(
                    'test' => 'Existing Translations Detection',
                    'status' => 'FAIL',
                    'message' => 'Spanish or Portuguese translation not detected'
                );
            }

            // Cleanup
            wp_delete_post($original_post, true);
            wp_delete_post($spanish_post, true);
            wp_delete_post($portuguese_post, true);

            return array(
                'test' => 'Existing Translations Detection',
                'status' => 'PASS',
                'message' => 'Multiple translations correctly detected'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Existing Translations Detection',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test available languages filtering
     *
     * @return array Test result
     * @since 1.0.0
     */
    public static function test_available_languages_filtering() {
        try {
            // Create test post with one translation
            $original_post = wp_insert_post(array(
                'post_title' => 'Filtering Test Post',
                'post_content' => 'Test post for language filtering.',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            $group_id = 'filter_test_' . time();
            update_post_meta($original_post, '_ez_translate_language', 'en');
            update_post_meta($original_post, '_ez_translate_group', $group_id);

            // Create only Spanish translation
            $spanish_post = wp_insert_post(array(
                'post_title' => 'Publicación de Filtrado',
                'post_content' => 'Publicación de prueba para filtrado de idiomas.',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            update_post_meta($spanish_post, '_ez_translate_language', 'es');
            update_post_meta($spanish_post, '_ez_translate_group', $group_id);

            // Test filtering
            $rest_api = new \EZTranslate\RestAPI();
            $request = new WP_REST_Request('GET', '/ez-translate/v1/verify-translations/' . $original_post);
            $request->set_param('id', $original_post);

            $response = $rest_api->verify_existing_translations($request);
            $data = $response->get_data();

            // Spanish should be in unavailable_languages
            if (!in_array('es', $data['unavailable_languages'])) {
                return array(
                    'test' => 'Available Languages Filtering',
                    'status' => 'FAIL',
                    'message' => 'Spanish not marked as unavailable'
                );
            }

            // Available languages should not include Spanish or English (source)
            $available_codes = array();
            foreach ($data['available_languages'] as $language) {
                $available_codes[] = $language['code'];
            }

            if (in_array('es', $available_codes) || in_array('en', $available_codes)) {
                return array(
                    'test' => 'Available Languages Filtering',
                    'status' => 'FAIL',
                    'message' => 'Source or existing translation language included in available languages'
                );
            }

            // Cleanup
            wp_delete_post($original_post, true);
            wp_delete_post($spanish_post, true);

            return array(
                'test' => 'Available Languages Filtering',
                'status' => 'PASS',
                'message' => 'Languages correctly filtered based on existing translations'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Available Languages Filtering',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test auto-detection integration
     *
     * @return array Test result
     * @since 1.0.0
     */
    public static function test_auto_detection_integration() {
        try {
            // Create post without explicit language metadata
            $original_post = wp_insert_post(array(
                'post_title' => 'Auto Detection Test Post',
                'post_content' => 'This post has no explicit language metadata.',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            // Create a translation that references this post
            $translation_post = wp_insert_post(array(
                'post_title' => 'Publicación de Detección Automática',
                'post_content' => 'Esta publicación referencia al post original.',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            $group_id = 'auto_detect_' . time();
            update_post_meta($translation_post, '_ez_translate_language', 'es');
            update_post_meta($translation_post, '_ez_translate_group', $group_id);
            update_post_meta($translation_post, '_ez_translate_original_id', $original_post);

            // Test auto-detection
            $rest_api = new \EZTranslate\RestAPI();
            $request = new WP_REST_Request('GET', '/ez-translate/v1/verify-translations/' . $original_post);
            $request->set_param('id', $original_post);

            $response = $rest_api->verify_existing_translations($request);
            $data = $response->get_data();

            // Should detect source language automatically
            if (empty($data['source_language'])) {
                return array(
                    'test' => 'Auto-Detection Integration',
                    'status' => 'FAIL',
                    'message' => 'Source language not auto-detected'
                );
            }

            // Should mark as detected
            if (!$data['source_language_detected']) {
                return array(
                    'test' => 'Auto-Detection Integration',
                    'status' => 'FAIL',
                    'message' => 'Auto-detection flag not set correctly'
                );
            }

            // Should find the Spanish translation
            if (count($data['existing_translations']) !== 1) {
                return array(
                    'test' => 'Auto-Detection Integration',
                    'status' => 'FAIL',
                    'message' => 'Spanish translation not found through auto-detection'
                );
            }

            // Cleanup
            wp_delete_post($original_post, true);
            wp_delete_post($translation_post, true);

            return array(
                'test' => 'Auto-Detection Integration',
                'status' => 'PASS',
                'message' => 'Auto-detection correctly integrated with verification'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Auto-Detection Integration',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test translation group membership detection
     *
     * @return array Test result
     * @since 1.0.0
     */
    public static function test_translation_group_membership() {
        try {
            // Create posts with similar titles but different groups
            $post1 = wp_insert_post(array(
                'post_title' => 'Learning Programming Basics',
                'post_content' => 'This is about programming basics.',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            $post2 = wp_insert_post(array(
                'post_title' => 'Aprender Programación Básica',
                'post_content' => 'Esto es sobre programación básica.',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            // Set up only the Spanish post with metadata
            $group_id = 'membership_test_' . time();
            update_post_meta($post2, '_ez_translate_language', 'es');
            update_post_meta($post2, '_ez_translate_group', $group_id);

            // Test if post1 is detected as part of the group
            $rest_api = new \EZTranslate\RestAPI();
            $request = new WP_REST_Request('GET', '/ez-translate/v1/verify-translations/' . $post1);
            $request->set_param('id', $post1);

            $response = $rest_api->verify_existing_translations($request);
            $data = $response->get_data();

            // Should detect the Spanish translation through similarity
            if (empty($data['translation_group'])) {
                return array(
                    'test' => 'Translation Group Membership',
                    'status' => 'FAIL',
                    'message' => 'Translation group not detected through title similarity'
                );
            }

            // Should find the Spanish post as existing translation
            $spanish_found = false;
            foreach ($data['existing_translations'] as $translation) {
                if ($translation['post_id'] == $post2) {
                    $spanish_found = true;
                    break;
                }
            }

            if (!$spanish_found) {
                return array(
                    'test' => 'Translation Group Membership',
                    'status' => 'FAIL',
                    'message' => 'Related Spanish post not found through similarity detection'
                );
            }

            // Cleanup
            wp_delete_post($post1, true);
            wp_delete_post($post2, true);

            return array(
                'test' => 'Translation Group Membership',
                'status' => 'PASS',
                'message' => 'Translation group membership correctly detected through title similarity'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Translation Group Membership',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }
}
