<?php
/**
 * Test Site Metadata per Language (MEJORA 2)
 *
 * @package EZTranslate
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Site Metadata Tests Class
 */
class EZ_Translate_Site_Metadata_Tests {

    /**
     * Run all site metadata tests
     *
     * @return array Test results
     * @since 1.0.0
     */
    public static function run_tests() {
        $results = array();

        echo '<div style="margin: 20px 0;">';
        echo '<h3>' . __('Site Metadata per Language Tests (MEJORA 2)', 'ez-translate') . '</h3>';

        // Test 1: Language data structure extension
        $results[] = self::test_language_data_structure();

        // Test 2: Site metadata sanitization
        $results[] = self::test_site_metadata_sanitization();

        // Test 3: Language site metadata retrieval
        $results[] = self::test_language_site_metadata_retrieval();

        // Test 4: Frontend metadata integration
        $results[] = self::test_frontend_metadata_integration();

        // Test 5: Landing page metadata fallback
        $results[] = self::test_landing_page_metadata_fallback();

        // Test 6: Admin interface integration
        $results[] = self::test_admin_interface_integration();

        // Test 7: Backward compatibility
        $results[] = self::test_backward_compatibility();

        echo '</div>';

        // Summary
        $passed = array_filter($results, function($r) { return $r['status'] === 'PASS'; });
        $total = count($results);
        $passed_count = count($passed);

        echo '<div class="notice notice-info inline" style="margin-top: 20px; padding: 15px;">';
        echo '<h4 style="margin: 0 0 10px 0;">' . __('Site Metadata Test Summary', 'ez-translate') . '</h4>';
        echo '<p style="margin: 0;"><strong>' . sprintf(__('%d of %d tests passed', 'ez-translate'), $passed_count, $total) . '</strong></p>';
        if ($passed_count === $total) {
            echo '<p style="margin: 5px 0 0 0; color: #00a32a;">' . __('All site metadata tests are working correctly!', 'ez-translate') . '</p>';
        } else {
            echo '<p style="margin: 5px 0 0 0; color: #d63638;">' . __('Some tests failed. Please check the implementation.', 'ez-translate') . '</p>';
        }
        echo '</div>';

        return $results;
    }

    /**
     * Test language data structure extension
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_language_data_structure() {
        try {
            // Create test language with site metadata
            $test_language = array(
                'code' => 'ts',
                'name' => 'Test Site Language',
                'slug' => 'test-site-language',
                'native_name' => 'Test Native Site',
                'flag' => '🧪',
                'rtl' => false,
                'enabled' => true,
                'site_title' => 'Test Site Title',
                'site_description' => 'Test site description for this language'
            );

            // Test sanitization
            $sanitized = \EZTranslate\LanguageManager::sanitize_language_data($test_language);

            // Verify site metadata fields are preserved
            if (!isset($sanitized['site_title']) || !isset($sanitized['site_description'])) {
                throw new Exception('Site metadata fields not preserved in sanitization');
            }

            if ($sanitized['site_title'] !== 'Test Site Title' || 
                $sanitized['site_description'] !== 'Test site description for this language') {
                throw new Exception('Site metadata values not correctly sanitized');
            }

            // Test adding language with site metadata
            $result = \EZTranslate\LanguageManager::add_language($sanitized);
            if (is_wp_error($result)) {
                throw new Exception('Failed to add language with site metadata: ' . $result->get_error_message());
            }

            // Verify language was saved with site metadata
            $saved_language = \EZTranslate\LanguageManager::get_language('ts');
            if (!$saved_language ||
                $saved_language['site_title'] !== 'Test Site Title' ||
                $saved_language['site_description'] !== 'Test site description for this language') {
                throw new Exception('Language site metadata not saved correctly');
            }

            // Clean up
            \EZTranslate\LanguageManager::delete_language('ts');

            return array(
                'test' => 'Language Data Structure Extension',
                'status' => 'PASS',
                'message' => 'Site metadata fields correctly integrated into language structure'
            );

        } catch (Exception $e) {
            // Clean up on error
            \EZTranslate\LanguageManager::delete_language('ts');

            return array(
                'test' => 'Language Data Structure Extension',
                'status' => 'FAIL',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Test site metadata sanitization
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_site_metadata_sanitization() {
        try {
            // Test with potentially unsafe data
            $unsafe_data = array(
                'code' => 'tz',
                'name' => 'Test Sanitize',
                'slug' => 'test-sanitize',
                'site_title' => '<script>alert("xss")</script>Safe Title',
                'site_description' => "Line 1\nLine 2\r\nLine 3<script>alert('xss')</script>"
            );

            $sanitized = \EZTranslate\LanguageManager::sanitize_language_data($unsafe_data);

            // Verify sanitization
            if (strpos($sanitized['site_title'], '<script>') !== false) {
                throw new Exception('Site title not properly sanitized');
            }

            if (strpos($sanitized['site_description'], '<script>') !== false) {
                throw new Exception('Site description not properly sanitized');
            }

            // Verify expected content is preserved
            if (strpos($sanitized['site_title'], 'Safe Title') === false) {
                throw new Exception('Safe content removed from site title');
            }

            if (strpos($sanitized['site_description'], 'Line 1') === false) {
                throw new Exception('Safe content removed from site description');
            }

            return array(
                'test' => 'Site Metadata Sanitization',
                'status' => 'PASS',
                'message' => 'Site metadata properly sanitized while preserving safe content'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Site Metadata Sanitization',
                'status' => 'FAIL',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Test language site metadata retrieval
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_language_site_metadata_retrieval() {
        try {
            // Create test language with site metadata
            $test_language = array(
                'code' => 'tr',
                'name' => 'Test Retrieval Language',
                'slug' => 'test-retrieval',
                'site_title' => 'Retrieval Test Site Title',
                'site_description' => 'Retrieval test site description'
            );

            $sanitized = \EZTranslate\LanguageManager::sanitize_language_data($test_language);
            \EZTranslate\LanguageManager::add_language($sanitized);

            // Test metadata retrieval
            $metadata = \EZTranslate\LanguageManager::get_language_site_metadata('tr');

            if (empty($metadata)) {
                throw new Exception('No metadata returned for existing language');
            }

            if (!isset($metadata['site_title']) || !isset($metadata['site_description'])) {
                throw new Exception('Metadata structure incomplete');
            }

            if ($metadata['site_title'] !== 'Retrieval Test Site Title' ||
                $metadata['site_description'] !== 'Retrieval test site description') {
                throw new Exception('Metadata values incorrect');
            }

            // Test retrieval for non-existent language
            $empty_metadata = \EZTranslate\LanguageManager::get_language_site_metadata('nonexistent');
            if (!empty($empty_metadata)) {
                throw new Exception('Metadata returned for non-existent language');
            }

            // Clean up
            \EZTranslate\LanguageManager::delete_language('tr');

            return array(
                'test' => 'Language Site Metadata Retrieval',
                'status' => 'PASS',
                'message' => 'Site metadata correctly retrieved and handles missing languages'
            );

        } catch (Exception $e) {
            // Clean up on error
            \EZTranslate\LanguageManager::delete_language('tr');
            
            return array(
                'test' => 'Language Site Metadata Retrieval',
                'status' => 'FAIL',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Test frontend metadata integration
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_frontend_metadata_integration() {
        try {
            // Create test language with site metadata
            $test_language = array(
                'code' => 'tf',
                'name' => 'Test Frontend Language',
                'slug' => 'test-frontend',
                'site_title' => 'Frontend Test Site Title',
                'site_description' => 'Frontend test site description'
            );

            $sanitized = \EZTranslate\LanguageManager::sanitize_language_data($test_language);
            \EZTranslate\LanguageManager::add_language($sanitized);

            // Create test post
            $post_id = wp_insert_post(array(
                'post_title' => 'Test Frontend Post',
                'post_content' => 'Test content for frontend integration',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            if (!$post_id) {
                throw new Exception('Failed to create test post');
            }

            // Set up as landing page with language but no custom SEO
            update_post_meta($post_id, '_ez_translate_language', 'tf');
            update_post_meta($post_id, '_ez_translate_is_landing', true);

            // Test frontend integration
            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            // Capture metadata output
            ob_start();
            $frontend->override_head_metadata();
            $output = ob_get_clean();

            // Verify site metadata is used
            if (strpos($output, 'Frontend Test Site Title') === false) {
                throw new Exception('Language site title not used in frontend metadata');
            }

            if (strpos($output, 'Frontend test site description') === false) {
                throw new Exception('Language site description not used in frontend metadata');
            }

            // Clean up
            wp_delete_post($post_id, true);
            \EZTranslate\LanguageManager::delete_language('tf');

            return array(
                'test' => 'Frontend Metadata Integration',
                'status' => 'PASS',
                'message' => 'Language site metadata correctly integrated into frontend SEO'
            );

        } catch (Exception $e) {
            // Clean up on error
            if (isset($post_id)) {
                wp_delete_post($post_id, true);
            }
            \EZTranslate\LanguageManager::delete_language('tf');
            
            return array(
                'test' => 'Frontend Metadata Integration',
                'status' => 'FAIL',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Test landing page metadata fallback
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_landing_page_metadata_fallback() {
        try {
            // Create test language with site metadata
            $test_language = array(
                'code' => 'tb',
                'name' => 'Test Fallback Language',
                'slug' => 'test-fallback',
                'site_title' => 'Fallback Test Site Title',
                'site_description' => 'Fallback test site description'
            );

            $sanitized = \EZTranslate\LanguageManager::sanitize_language_data($test_language);
            \EZTranslate\LanguageManager::add_language($sanitized);

            // Create test post
            $post_id = wp_insert_post(array(
                'post_title' => 'Test Fallback Post',
                'post_content' => 'Test content for fallback testing',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            if (!$post_id) {
                throw new Exception('Failed to create test post');
            }

            // Test 1: Landing page with custom SEO (should use custom SEO)
            update_post_meta($post_id, '_ez_translate_language', 'tb');
            update_post_meta($post_id, '_ez_translate_is_landing', true);
            update_post_meta($post_id, '_ez_translate_seo_title', 'Custom SEO Title');
            update_post_meta($post_id, '_ez_translate_seo_description', 'Custom SEO Description');

            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            ob_start();
            $frontend->override_head_metadata();
            $output1 = ob_get_clean();

            if (strpos($output1, 'Custom SEO Title') === false) {
                throw new Exception('Custom SEO title not used when available');
            }

            // Test 2: Landing page without custom SEO (should use language site metadata)
            delete_post_meta($post_id, '_ez_translate_seo_title');
            delete_post_meta($post_id, '_ez_translate_seo_description');

            ob_start();
            $frontend->override_head_metadata();
            $output2 = ob_get_clean();

            if (strpos($output2, 'Fallback Test Site Title') === false) {
                throw new Exception('Language site title not used as fallback');
            }

            if (strpos($output2, 'Fallback test site description') === false) {
                throw new Exception('Language site description not used as fallback');
            }

            // Test 3: Regular page (should not use language site metadata)
            update_post_meta($post_id, '_ez_translate_is_landing', false);

            ob_start();
            $frontend->override_head_metadata();
            $output3 = ob_get_clean();

            if (strpos($output3, 'Fallback Test Site Title') !== false) {
                throw new Exception('Language site title incorrectly used for regular page');
            }

            // Clean up
            wp_delete_post($post_id, true);
            \EZTranslate\LanguageManager::delete_language('tb');

            return array(
                'test' => 'Landing Page Metadata Fallback',
                'status' => 'PASS',
                'message' => 'Metadata fallback logic works correctly for landing pages'
            );

        } catch (Exception $e) {
            // Clean up on error
            if (isset($post_id)) {
                wp_delete_post($post_id, true);
            }
            \EZTranslate\LanguageManager::delete_language('tb');

            return array(
                'test' => 'Landing Page Metadata Fallback',
                'status' => 'FAIL',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Test admin interface integration
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_admin_interface_integration() {
        try {
            // Test that admin form can handle site metadata
            $form_data = array(
                'code' => 'ta',
                'name' => 'Test Admin Language',
                'slug' => 'test-admin',
                'site_title' => 'Admin Test Site Title',
                'site_description' => 'Admin test site description'
            );

            // Simulate form submission
            $sanitized = \EZTranslate\LanguageManager::sanitize_language_data($form_data);
            $result = \EZTranslate\LanguageManager::add_language($sanitized);

            if (is_wp_error($result)) {
                throw new Exception('Admin form data not processed correctly: ' . $result->get_error_message());
            }

            // Verify language was saved with site metadata
            $saved_language = \EZTranslate\LanguageManager::get_language('ta');
            if (!$saved_language) {
                throw new Exception('Language not saved from admin form');
            }

            if ($saved_language['site_title'] !== 'Admin Test Site Title' ||
                $saved_language['site_description'] !== 'Admin test site description') {
                throw new Exception('Site metadata not saved from admin form');
            }

            // Test update functionality
            $update_data = array(
                'code' => 'ta',
                'name' => 'Updated Admin Language',
                'slug' => 'updated-admin',
                'site_title' => 'Updated Admin Site Title',
                'site_description' => 'Updated admin site description'
            );

            $sanitized_update = \EZTranslate\LanguageManager::sanitize_language_data($update_data);
            $update_result = \EZTranslate\LanguageManager::update_language('ta', $sanitized_update);

            if (is_wp_error($update_result)) {
                throw new Exception('Admin update not processed correctly: ' . $update_result->get_error_message());
            }

            // Verify update
            $updated_language = \EZTranslate\LanguageManager::get_language('ta');
            if ($updated_language['site_title'] !== 'Updated Admin Site Title' ||
                $updated_language['site_description'] !== 'Updated admin site description') {
                throw new Exception('Site metadata not updated from admin form');
            }

            // Clean up
            \EZTranslate\LanguageManager::delete_language('ta');

            return array(
                'test' => 'Admin Interface Integration',
                'status' => 'PASS',
                'message' => 'Admin interface correctly handles site metadata fields'
            );

        } catch (Exception $e) {
            // Clean up on error
            \EZTranslate\LanguageManager::delete_language('ta');

            return array(
                'test' => 'Admin Interface Integration',
                'status' => 'FAIL',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Test backward compatibility
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_backward_compatibility() {
        try {
            // Test with old language structure (without site metadata)
            $old_language = array(
                'code' => 'tc',
                'name' => 'Test Compatibility Language',
                'slug' => 'test-compatibility',
                'native_name' => 'Test Native',
                'flag' => '🔄',
                'rtl' => false,
                'enabled' => true
                // No site_title or site_description
            );

            // Should work without errors
            $sanitized = \EZTranslate\LanguageManager::sanitize_language_data($old_language);
            $result = \EZTranslate\LanguageManager::add_language($sanitized);

            if (is_wp_error($result)) {
                throw new Exception('Old language structure not compatible: ' . $result->get_error_message());
            }

            // Verify empty site metadata fields are handled
            $metadata = \EZTranslate\LanguageManager::get_language_site_metadata('tc');
            if (!is_array($metadata) ||
                !isset($metadata['site_title']) ||
                !isset($metadata['site_description'])) {
                throw new Exception('Metadata structure not properly initialized for old languages');
            }

            if (!empty($metadata['site_title']) || !empty($metadata['site_description'])) {
                throw new Exception('Empty metadata not handled correctly');
            }

            // Test frontend with old language (should not break)
            $post_id = wp_insert_post(array(
                'post_title' => 'Test Compatibility Post',
                'post_content' => 'Test content',
                'post_status' => 'publish',
                'post_type' => 'post'
            ));

            update_post_meta($post_id, '_ez_translate_language', 'tc');
            update_post_meta($post_id, '_ez_translate_is_landing', true);

            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            ob_start();
            $frontend->override_head_metadata();
            $output = ob_get_clean();

            // Should use post title as fallback
            if (strpos($output, 'Test Compatibility Post') === false) {
                throw new Exception('Fallback to post title not working for old languages');
            }

            // Clean up
            wp_delete_post($post_id, true);
            \EZTranslate\LanguageManager::delete_language('tc');

            return array(
                'test' => 'Backward Compatibility',
                'status' => 'PASS',
                'message' => 'Backward compatibility maintained for existing languages'
            );

        } catch (Exception $e) {
            // Clean up on error
            if (isset($post_id)) {
                wp_delete_post($post_id, true);
            }
            \EZTranslate\LanguageManager::delete_language('tc');

            return array(
                'test' => 'Backward Compatibility',
                'status' => 'FAIL',
                'message' => $e->getMessage()
            );
        }
    }
}

/**
 * Display site metadata tests
 *
 * @since 1.0.0
 */
function ez_translate_display_site_metadata_tests() {
    EZ_Translate_Site_Metadata_Tests::run_tests();
}
