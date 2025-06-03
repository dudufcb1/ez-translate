<?php
/**
 * Landing Page Creation Tests for EZ Translate
 *
 * Tests the automatic creation of landing pages when adding new languages.
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

/**
 * Test Landing Page Creation functionality
 *
 * @since 1.0.0
 */
class EZTranslateLandingPageCreationTests {

    /**
     * Run all landing page creation tests
     *
     * @return array Test results
     * @since 1.0.0
     */
    public static function run_all_tests() {
        $results = array();

        // Test 1: Basic landing page creation
        $results[] = self::test_basic_landing_page_creation();

        // Test 2: Landing page with custom slug
        $results[] = self::test_landing_page_custom_slug();

        // Test 3: Landing page metadata validation
        $results[] = self::test_landing_page_metadata();

        // Test 4: Duplicate slug handling
        $results[] = self::test_duplicate_slug_handling();

        // Test 5: Invalid language code
        $results[] = self::test_invalid_language_code();

        // Test 6: Missing required fields
        $results[] = self::test_missing_required_fields();

        // Test 7: Published vs draft status
        $results[] = self::test_page_status_options();

        // Test 8: Language without landing page
        $results[] = self::test_language_without_landing_page();

        // Test 9: Landing page creation during language edit
        $results[] = self::test_landing_page_creation_during_edit();

        // Test 10: SEO metadata editing on created landing pages
        $results[] = self::test_seo_metadata_editing();

        return $results;
    }

    /**
     * Test 1: Basic landing page creation
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_basic_landing_page_creation() {
        try {
            // Clean up any existing test data
            self::cleanup_test_data();

            // Create language with landing page
            $language_data = array(
                'code' => 'test',
                'name' => 'Test Language',
                'slug' => 'test-language',
                'enabled' => true
            );

            $landing_page_data = array(
                'title' => 'Welcome to Test Language',
                'description' => 'This is a test landing page for the test language.',
                'slug' => 'test-welcome',
                'status' => 'draft'
            );

            $result = LanguageManager::add_language($language_data, $landing_page_data);

            if (is_wp_error($result)) {
                return array(
                    'test' => 'Basic Landing Page Creation',
                    'status' => 'FAIL',
                    'message' => 'Failed to create language with landing page: ' . $result->get_error_message()
                );
            }

            // Verify landing page was created
            if (!isset($result['landing_page_id'])) {
                return array(
                    'test' => 'Basic Landing Page Creation',
                    'status' => 'FAIL',
                    'message' => 'Landing page ID not returned in result'
                );
            }

            $post_id = $result['landing_page_id'];
            $post = get_post($post_id);

            if (!$post) {
                return array(
                    'test' => 'Basic Landing Page Creation',
                    'status' => 'FAIL',
                    'message' => 'Landing page post not found in database'
                );
            }

            // Verify post properties
            if ($post->post_title !== 'Welcome to Test Language') {
                return array(
                    'test' => 'Basic Landing Page Creation',
                    'status' => 'FAIL',
                    'message' => 'Landing page title incorrect'
                );
            }

            if ($post->post_name !== 'test-welcome') {
                return array(
                    'test' => 'Basic Landing Page Creation',
                    'status' => 'FAIL',
                    'message' => 'Landing page slug incorrect'
                );
            }

            if ($post->post_status !== 'draft') {
                return array(
                    'test' => 'Basic Landing Page Creation',
                    'status' => 'FAIL',
                    'message' => 'Landing page status incorrect'
                );
            }

            // Clean up
            self::cleanup_test_data();

            return array(
                'test' => 'Basic Landing Page Creation',
                'status' => 'PASS',
                'message' => 'Landing page created successfully with correct properties'
            );

        } catch (Exception $e) {
            self::cleanup_test_data();
            return array(
                'test' => 'Basic Landing Page Creation',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 2: Landing page with custom slug
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_landing_page_custom_slug() {
        try {
            // Clean up any existing test data
            self::cleanup_test_data();

            $language_data = array(
                'code' => 'test2',
                'name' => 'Test Language 2',
                'slug' => 'test-language-2',
                'enabled' => true
            );

            $landing_page_data = array(
                'title' => 'Custom Slug Test',
                'description' => 'Testing custom slug functionality.',
                'slug' => 'custom-landing-slug',
                'status' => 'publish'
            );

            $result = LanguageManager::add_language($language_data, $landing_page_data);

            if (is_wp_error($result)) {
                return array(
                    'test' => 'Landing Page Custom Slug',
                    'status' => 'FAIL',
                    'message' => 'Failed to create language: ' . $result->get_error_message()
                );
            }

            $post_id = $result['landing_page_id'];
            $post = get_post($post_id);

            if ($post->post_name !== 'custom-landing-slug') {
                return array(
                    'test' => 'Landing Page Custom Slug',
                    'status' => 'FAIL',
                    'message' => 'Custom slug not applied correctly'
                );
            }

            if ($post->post_status !== 'publish') {
                return array(
                    'test' => 'Landing Page Custom Slug',
                    'status' => 'FAIL',
                    'message' => 'Published status not applied correctly'
                );
            }

            // Clean up
            self::cleanup_test_data();

            return array(
                'test' => 'Landing Page Custom Slug',
                'status' => 'PASS',
                'message' => 'Custom slug and publish status applied correctly'
            );

        } catch (Exception $e) {
            self::cleanup_test_data();
            return array(
                'test' => 'Landing Page Custom Slug',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 3: Landing page metadata validation
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_landing_page_metadata() {
        try {
            // Clean up any existing test data
            self::cleanup_test_data();

            $language_data = array(
                'code' => 'test3',
                'name' => 'Test Language 3',
                'slug' => 'test-language-3',
                'enabled' => true
            );

            $landing_page_data = array(
                'title' => 'Metadata Test Page',
                'description' => 'Testing metadata assignment for landing pages.',
                'slug' => 'metadata-test',
                'status' => 'draft'
            );

            $result = LanguageManager::add_language($language_data, $landing_page_data);

            if (is_wp_error($result)) {
                return array(
                    'test' => 'Landing Page Metadata',
                    'status' => 'FAIL',
                    'message' => 'Failed to create language: ' . $result->get_error_message()
                );
            }

            $post_id = $result['landing_page_id'];

            // Check metadata
            $language_meta = get_post_meta($post_id, '_ez_translate_language', true);
            $seo_title = get_post_meta($post_id, '_ez_translate_seo_title', true);
            $seo_description = get_post_meta($post_id, '_ez_translate_seo_description', true);
            $group_id = get_post_meta($post_id, '_ez_translate_group', true);

            if ($language_meta !== 'test3') {
                return array(
                    'test' => 'Landing Page Metadata',
                    'status' => 'FAIL',
                    'message' => 'Language metadata not set correctly'
                );
            }

            if ($seo_title !== 'Metadata Test Page') {
                return array(
                    'test' => 'Landing Page Metadata',
                    'status' => 'FAIL',
                    'message' => 'SEO title metadata not set correctly'
                );
            }

            if ($seo_description !== 'Testing metadata assignment for landing pages.') {
                return array(
                    'test' => 'Landing Page Metadata',
                    'status' => 'FAIL',
                    'message' => 'SEO description metadata not set correctly'
                );
            }

            if (empty($group_id) || !preg_match('/^tg_[a-zA-Z0-9]{16}$/', $group_id)) {
                return array(
                    'test' => 'Landing Page Metadata',
                    'status' => 'FAIL',
                    'message' => 'Translation group ID not generated correctly'
                );
            }

            // Clean up
            self::cleanup_test_data();

            return array(
                'test' => 'Landing Page Metadata',
                'status' => 'PASS',
                'message' => 'All metadata assigned correctly including translation group ID'
            );

        } catch (Exception $e) {
            self::cleanup_test_data();
            return array(
                'test' => 'Landing Page Metadata',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 4: Duplicate slug handling
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_duplicate_slug_handling() {
        try {
            // Clean up any existing test data
            self::cleanup_test_data();

            // Create a page with a specific slug first
            $existing_post_id = wp_insert_post(array(
                'post_title' => 'Existing Page',
                'post_content' => 'This page already exists',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'duplicate-test'
            ));

            if (is_wp_error($existing_post_id)) {
                return array(
                    'test' => 'Duplicate Slug Handling',
                    'status' => 'FAIL',
                    'message' => 'Failed to create existing post for test'
                );
            }

            // Now try to create a landing page with the same slug
            $language_data = array(
                'code' => 'test4',
                'name' => 'Test Language 4',
                'slug' => 'test-language-4',
                'enabled' => true
            );

            $landing_page_data = array(
                'title' => 'Duplicate Slug Test',
                'description' => 'Testing duplicate slug handling.',
                'slug' => 'duplicate-test', // Same as existing post
                'status' => 'draft'
            );

            $result = LanguageManager::add_language($language_data, $landing_page_data);

            if (is_wp_error($result)) {
                wp_delete_post($existing_post_id, true);
                return array(
                    'test' => 'Duplicate Slug Handling',
                    'status' => 'FAIL',
                    'message' => 'Failed to create language: ' . $result->get_error_message()
                );
            }

            $post_id = $result['landing_page_id'];
            $post = get_post($post_id);

            // Should have appended language code to make it unique
            if ($post->post_name !== 'duplicate-test-test4') {
                wp_delete_post($existing_post_id, true);
                return array(
                    'test' => 'Duplicate Slug Handling',
                    'status' => 'FAIL',
                    'message' => 'Duplicate slug not handled correctly. Expected: duplicate-test-test4, Got: ' . $post->post_name
                );
            }

            // Clean up
            wp_delete_post($existing_post_id, true);
            self::cleanup_test_data();

            return array(
                'test' => 'Duplicate Slug Handling',
                'status' => 'PASS',
                'message' => 'Duplicate slug handled correctly by appending language code'
            );

        } catch (Exception $e) {
            self::cleanup_test_data();
            return array(
                'test' => 'Duplicate Slug Handling',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 5: Invalid language code
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_invalid_language_code() {
        try {
            $landing_page_data = array(
                'title' => 'Invalid Language Test',
                'description' => 'Testing with invalid language code.',
                'slug' => 'invalid-test',
                'status' => 'draft'
            );

            $result = LanguageManager::create_landing_page_for_language('nonexistent', $landing_page_data);

            if (!is_wp_error($result)) {
                return array(
                    'test' => 'Invalid Language Code',
                    'status' => 'FAIL',
                    'message' => 'Should have failed with invalid language code'
                );
            }

            if ($result->get_error_code() !== 'language_not_found') {
                return array(
                    'test' => 'Invalid Language Code',
                    'status' => 'FAIL',
                    'message' => 'Wrong error code returned: ' . $result->get_error_code()
                );
            }

            return array(
                'test' => 'Invalid Language Code',
                'status' => 'PASS',
                'message' => 'Correctly rejected invalid language code'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Invalid Language Code',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 6: Missing required fields
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_missing_required_fields() {
        try {
            // Clean up any existing test data
            self::cleanup_test_data();

            // First create a language
            $language_data = array(
                'code' => 'test6',
                'name' => 'Test Language 6',
                'slug' => 'test-language-6',
                'enabled' => true
            );

            $result = LanguageManager::add_language($language_data);
            if (is_wp_error($result)) {
                return array(
                    'test' => 'Missing Required Fields',
                    'status' => 'FAIL',
                    'message' => 'Failed to create test language'
                );
            }

            // Test missing title
            $landing_page_data = array(
                'title' => '', // Missing
                'description' => 'Valid description',
                'slug' => 'missing-title-test',
                'status' => 'draft'
            );

            $result = LanguageManager::create_landing_page_for_language('test6', $landing_page_data);

            if (!is_wp_error($result) || $result->get_error_code() !== 'missing_data') {
                self::cleanup_test_data();
                return array(
                    'test' => 'Missing Required Fields',
                    'status' => 'FAIL',
                    'message' => 'Should have failed with missing title'
                );
            }

            // Test missing description
            $landing_page_data = array(
                'title' => 'Valid title',
                'description' => '', // Missing
                'slug' => 'missing-description-test',
                'status' => 'draft'
            );

            $result = LanguageManager::create_landing_page_for_language('test6', $landing_page_data);

            if (!is_wp_error($result) || $result->get_error_code() !== 'missing_data') {
                self::cleanup_test_data();
                return array(
                    'test' => 'Missing Required Fields',
                    'status' => 'FAIL',
                    'message' => 'Should have failed with missing description'
                );
            }

            // Clean up
            self::cleanup_test_data();

            return array(
                'test' => 'Missing Required Fields',
                'status' => 'PASS',
                'message' => 'Correctly validated required fields'
            );

        } catch (Exception $e) {
            self::cleanup_test_data();
            return array(
                'test' => 'Missing Required Fields',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 7: Published vs draft status
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_page_status_options() {
        try {
            // Clean up any existing test data
            self::cleanup_test_data();

            // Test draft status (default)
            $language_data = array(
                'code' => 'test7',
                'name' => 'Test Language 7',
                'slug' => 'test-language-7',
                'enabled' => true
            );

            $landing_page_data = array(
                'title' => 'Status Test Draft',
                'description' => 'Testing draft status.',
                'slug' => 'status-test-draft',
                'status' => 'draft'
            );

            $result = LanguageManager::add_language($language_data, $landing_page_data);

            if (is_wp_error($result)) {
                return array(
                    'test' => 'Page Status Options',
                    'status' => 'FAIL',
                    'message' => 'Failed to create draft page: ' . $result->get_error_message()
                );
            }

            $post = get_post($result['landing_page_id']);
            if ($post->post_status !== 'draft') {
                self::cleanup_test_data();
                return array(
                    'test' => 'Page Status Options',
                    'status' => 'FAIL',
                    'message' => 'Draft status not applied correctly'
                );
            }

            // Clean up
            self::cleanup_test_data();

            return array(
                'test' => 'Page Status Options',
                'status' => 'PASS',
                'message' => 'Page status options work correctly'
            );

        } catch (Exception $e) {
            self::cleanup_test_data();
            return array(
                'test' => 'Page Status Options',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 8: Language without landing page
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_language_without_landing_page() {
        try {
            // Clean up any existing test data
            self::cleanup_test_data();

            // Create language without landing page data
            $language_data = array(
                'code' => 'test8',
                'name' => 'Test Language 8',
                'slug' => 'test-language-8',
                'enabled' => true
            );

            $result = LanguageManager::add_language($language_data, null);

            if (is_wp_error($result)) {
                return array(
                    'test' => 'Language Without Landing Page',
                    'status' => 'FAIL',
                    'message' => 'Failed to create language without landing page: ' . $result->get_error_message()
                );
            }

            // Should return true (not array with landing_page_id)
            if ($result !== true) {
                self::cleanup_test_data();
                return array(
                    'test' => 'Language Without Landing Page',
                    'status' => 'FAIL',
                    'message' => 'Unexpected return value when no landing page requested'
                );
            }

            // Verify language was created
            $language = LanguageManager::get_language('test8');
            if (!$language) {
                self::cleanup_test_data();
                return array(
                    'test' => 'Language Without Landing Page',
                    'status' => 'FAIL',
                    'message' => 'Language was not created'
                );
            }

            // Clean up
            self::cleanup_test_data();

            return array(
                'test' => 'Language Without Landing Page',
                'status' => 'PASS',
                'message' => 'Language created successfully without landing page'
            );

        } catch (Exception $e) {
            self::cleanup_test_data();
            return array(
                'test' => 'Language Without Landing Page',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 9: Landing page creation during language edit
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_landing_page_creation_during_edit() {
        try {
            // Clean up any existing test data
            self::cleanup_test_data();

            // First create a language without landing page
            $language_data = array(
                'code' => 'test9',
                'name' => 'Test Language 9',
                'slug' => 'test-language-9',
                'enabled' => true
            );

            $result = LanguageManager::add_language($language_data);
            if (is_wp_error($result)) {
                return array(
                    'test' => 'Landing Page Creation During Edit',
                    'status' => 'FAIL',
                    'message' => 'Failed to create initial language: ' . $result->get_error_message()
                );
            }

            // Now simulate editing the language and adding a landing page
            $updated_language_data = array(
                'code' => 'test9',
                'name' => 'Test Language 9 Updated',
                'slug' => 'test-language-9-updated',
                'enabled' => true
            );

            // Update the language first
            $update_result = LanguageManager::update_language('test9', $updated_language_data);
            if (is_wp_error($update_result)) {
                self::cleanup_test_data();
                return array(
                    'test' => 'Landing Page Creation During Edit',
                    'status' => 'FAIL',
                    'message' => 'Failed to update language: ' . $update_result->get_error_message()
                );
            }

            // Now create landing page for the updated language
            $landing_page_data = array(
                'title' => 'Edit Test Landing Page',
                'description' => 'Landing page created during language edit.',
                'slug' => 'edit-test-landing',
                'status' => 'draft'
            );

            $landing_page_result = LanguageManager::create_landing_page_for_language('test9', $landing_page_data);

            if (is_wp_error($landing_page_result)) {
                self::cleanup_test_data();
                return array(
                    'test' => 'Landing Page Creation During Edit',
                    'status' => 'FAIL',
                    'message' => 'Failed to create landing page during edit: ' . $landing_page_result->get_error_message()
                );
            }

            // Verify the landing page was created correctly
            $post = get_post($landing_page_result);
            if (!$post) {
                self::cleanup_test_data();
                return array(
                    'test' => 'Landing Page Creation During Edit',
                    'status' => 'FAIL',
                    'message' => 'Landing page post not found after creation'
                );
            }

            // Verify metadata
            $language_meta = get_post_meta($landing_page_result, '_ez_translate_language', true);
            if ($language_meta !== 'test9') {
                self::cleanup_test_data();
                return array(
                    'test' => 'Landing Page Creation During Edit',
                    'status' => 'FAIL',
                    'message' => 'Language metadata not set correctly on landing page'
                );
            }

            // Verify the language was updated
            $updated_language = LanguageManager::get_language('test9');
            if (!$updated_language || $updated_language['name'] !== 'Test Language 9 Updated') {
                self::cleanup_test_data();
                return array(
                    'test' => 'Landing Page Creation During Edit',
                    'status' => 'FAIL',
                    'message' => 'Language was not updated correctly'
                );
            }

            // Clean up
            self::cleanup_test_data();

            return array(
                'test' => 'Landing Page Creation During Edit',
                'status' => 'PASS',
                'message' => 'Language updated and landing page created successfully during edit'
            );

        } catch (Exception $e) {
            self::cleanup_test_data();
            return array(
                'test' => 'Landing Page Creation During Edit',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 10: SEO metadata editing on created landing pages
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_seo_metadata_editing() {
        try {
            // Clean up any existing test data
            self::cleanup_test_data();

            // Create a language with landing page
            $language_data = array(
                'code' => 'test10',
                'name' => 'Test Language 10',
                'slug' => 'test-language-10',
                'enabled' => true
            );

            $landing_page_data = array(
                'title' => 'SEO Test Landing Page',
                'description' => 'Testing SEO metadata editing functionality.',
                'slug' => 'seo-test-landing',
                'status' => 'draft'
            );

            $result = LanguageManager::add_language($language_data, $landing_page_data);

            if (is_wp_error($result)) {
                return array(
                    'test' => 'SEO Metadata Editing',
                    'status' => 'FAIL',
                    'message' => 'Failed to create language with landing page: ' . $result->get_error_message()
                );
            }

            $post_id = $result['landing_page_id'];

            // Verify initial SEO metadata was set correctly
            $initial_seo_title = get_post_meta($post_id, '_ez_translate_seo_title', true);
            $initial_seo_description = get_post_meta($post_id, '_ez_translate_seo_description', true);

            if ($initial_seo_title !== 'SEO Test Landing Page') {
                self::cleanup_test_data();
                return array(
                    'test' => 'SEO Metadata Editing',
                    'status' => 'FAIL',
                    'message' => 'Initial SEO title not set correctly'
                );
            }

            if ($initial_seo_description !== 'Testing SEO metadata editing functionality.') {
                self::cleanup_test_data();
                return array(
                    'test' => 'SEO Metadata Editing',
                    'status' => 'FAIL',
                    'message' => 'Initial SEO description not set correctly'
                );
            }

            // Simulate updating SEO metadata (as would happen in Gutenberg)
            $new_seo_title = 'Updated SEO Title for Landing Page';
            $new_seo_description = 'Updated SEO description with more detailed information about the landing page content.';

            update_post_meta($post_id, '_ez_translate_seo_title', $new_seo_title);
            update_post_meta($post_id, '_ez_translate_seo_description', $new_seo_description);

            // Verify the updates were saved
            $updated_seo_title = get_post_meta($post_id, '_ez_translate_seo_title', true);
            $updated_seo_description = get_post_meta($post_id, '_ez_translate_seo_description', true);

            if ($updated_seo_title !== $new_seo_title) {
                self::cleanup_test_data();
                return array(
                    'test' => 'SEO Metadata Editing',
                    'status' => 'FAIL',
                    'message' => 'SEO title update failed'
                );
            }

            if ($updated_seo_description !== $new_seo_description) {
                self::cleanup_test_data();
                return array(
                    'test' => 'SEO Metadata Editing',
                    'status' => 'FAIL',
                    'message' => 'SEO description update failed'
                );
            }

            // Verify language metadata is still intact
            $language_meta = get_post_meta($post_id, '_ez_translate_language', true);
            if ($language_meta !== 'test10') {
                self::cleanup_test_data();
                return array(
                    'test' => 'SEO Metadata Editing',
                    'status' => 'FAIL',
                    'message' => 'Language metadata was corrupted during SEO update'
                );
            }

            // Clean up
            self::cleanup_test_data();

            return array(
                'test' => 'SEO Metadata Editing',
                'status' => 'PASS',
                'message' => 'SEO metadata can be edited successfully on created landing pages'
            );

        } catch (Exception $e) {
            self::cleanup_test_data();
            return array(
                'test' => 'SEO Metadata Editing',
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
    private static function cleanup_test_data() {
        // Remove test languages
        $test_codes = array('test', 'test2', 'test3', 'test4', 'test5', 'test6', 'test7', 'test8', 'test9', 'test10');
        foreach ($test_codes as $code) {
            LanguageManager::delete_language($code);
        }

        // Remove test posts
        $test_posts = get_posts(array(
            'post_type' => 'page',
            'post_status' => array('draft', 'publish'),
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
}

/**
 * Display landing page creation tests in admin interface
 *
 * @since 1.0.0
 */
function ez_translate_display_landing_page_creation_tests() {
    echo '<div class="card">';
    echo '<h2>' . __('Landing Page Creation Tests', 'ez-translate') . '</h2>';

    $results = EZTranslateLandingPageCreationTests::run_all_tests();

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
        echo '<p style="margin: 5px 0 0 0; color: #00a32a;">' . __('All landing page creation tests are working correctly!', 'ez-translate') . '</p>';
    } else {
        echo '<p style="margin: 5px 0 0 0; color: #d63638;">' . __('Some tests failed. Please check the implementation.', 'ez-translate') . '</p>';
    }
    echo '</div>';

    echo '</div>';
}
