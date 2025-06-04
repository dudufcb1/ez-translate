<?php
/**
 * Tests for EZ Translate Frontend SEO functionality
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

/**
 * Test Frontend SEO functionality
 *
 * @since 1.0.0
 */
class EZTranslateFrontendSEOTest {

    /**
     * Run all tests
     *
     * @return array Test results
     * @since 1.0.0
     */
    public static function run_tests() {
        $results = array();

        // Test 1: Frontend class initialization
        $results[] = self::test_frontend_initialization();

        // Test 2: SEO metadata injection for landing pages
        $results[] = self::test_seo_metadata_injection();

        // Test 3: Document title filtering
        $results[] = self::test_document_title_filtering();

        // Test 4: Meta description injection
        $results[] = self::test_meta_description_injection();

        // Test 5: Open Graph metadata injection
        $results[] = self::test_open_graph_injection();

        // Test 6: Twitter Card metadata injection
        $results[] = self::test_twitter_card_injection();

        // Test 7: JSON-LD structured data injection
        $results[] = self::test_json_ld_injection();

        // Test 8: Language to locale conversion
        $results[] = self::test_language_locale_conversion();

        // Test 9: Non-landing page behavior
        $results[] = self::test_non_landing_page_behavior();

        return $results;
    }

    /**
     * Test frontend class initialization
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_frontend_initialization() {
        try {
            // Check if Frontend class exists
            if (!class_exists('EZTranslate\Frontend')) {
                return array(
                    'test' => 'Frontend Class Initialization',
                    'status' => 'FAIL',
                    'message' => 'Frontend class does not exist'
                );
            }

            // Check if hooks are properly registered
            $frontend = new \EZTranslate\Frontend();
            
            // Check if wp_head action is registered
            $wp_head_priority = has_action('wp_head', array($frontend, 'override_head_metadata'));
            $title_filter_priority = has_filter('document_title_parts', array($frontend, 'filter_document_title'));

            if ($wp_head_priority === false || $title_filter_priority === false) {
                return array(
                    'test' => 'Frontend Class Initialization',
                    'status' => 'FAIL',
                    'message' => 'Frontend hooks not properly registered'
                );
            }

            return array(
                'test' => 'Frontend Class Initialization',
                'status' => 'PASS',
                'message' => 'Frontend class initialized and hooks registered successfully'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Frontend Class Initialization',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test SEO metadata injection for landing pages
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_seo_metadata_injection() {
        try {
            // Create a test page
            $post_id = wp_insert_post(array(
                'post_title' => 'Test Landing Page',
                'post_content' => 'Test content for landing page',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            if (is_wp_error($post_id)) {
                return array(
                    'test' => 'SEO Metadata Injection',
                    'status' => 'FAIL',
                    'message' => 'Failed to create test post'
                );
            }

            // Set up test language
            ez_translate_ensure_test_languages();

            // Set post metadata
            update_post_meta($post_id, '_ez_translate_language', 'es');
            update_post_meta($post_id, '_ez_translate_is_landing', true);
            update_post_meta($post_id, '_ez_translate_seo_title', 'Custom SEO Title');
            update_post_meta($post_id, '_ez_translate_seo_description', 'Custom SEO Description');

            // Simulate being on the frontend
            global $post;
            $post = get_post($post_id);

            // Test metadata injection
            $frontend = new \EZTranslate\Frontend();

            // Capture output
            ob_start();
            $frontend->override_head_metadata();
            $output = ob_get_clean();

            // Check if metadata was injected
            $has_og_title = strpos($output, 'og:title') !== false;
            $has_og_description = strpos($output, 'og:description') !== false;
            $has_twitter_card = strpos($output, 'twitter:card') !== false;
            $has_json_ld = strpos($output, 'application/ld+json') !== false;

            // Clean up
            wp_delete_post($post_id, true);

            if (!$has_og_title || !$has_og_description || !$has_twitter_card || !$has_json_ld) {
                return array(
                    'test' => 'SEO Metadata Injection',
                    'status' => 'FAIL',
                    'message' => 'Not all expected metadata was injected'
                );
            }

            return array(
                'test' => 'SEO Metadata Injection',
                'status' => 'PASS',
                'message' => 'SEO metadata injected successfully for landing page'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'SEO Metadata Injection',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test document title filtering
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_document_title_filtering() {
        try {
            // Create a test page
            $post_id = wp_insert_post(array(
                'post_title' => 'Original Title',
                'post_content' => 'Test content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            if (is_wp_error($post_id)) {
                return array(
                    'test' => 'Document Title Filtering',
                    'status' => 'FAIL',
                    'message' => 'Failed to create test post'
                );
            }

            // Set up as landing page with custom SEO title
            update_post_meta($post_id, '_ez_translate_language', 'es');
            update_post_meta($post_id, '_ez_translate_is_landing', true);
            update_post_meta($post_id, '_ez_translate_seo_title', 'Custom SEO Title');

            // Simulate being on the frontend
            global $post;
            $post = get_post($post_id);

            // Test title filtering
            $frontend = new \EZTranslate\Frontend();

            $title_parts = array('title' => 'Original Title');
            $filtered_parts = $frontend->filter_document_title($title_parts);

            // Clean up
            wp_delete_post($post_id, true);

            if ($filtered_parts['title'] !== 'Custom SEO Title') {
                return array(
                    'test' => 'Document Title Filtering',
                    'status' => 'FAIL',
                    'message' => 'Document title was not properly overridden'
                );
            }

            return array(
                'test' => 'Document Title Filtering',
                'status' => 'PASS',
                'message' => 'Document title filtered successfully for landing page'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Document Title Filtering',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test meta description injection
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_meta_description_injection() {
        try {
            // Create a test page
            $post_id = wp_insert_post(array(
                'post_title' => 'Test Page',
                'post_content' => 'Test content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            if (is_wp_error($post_id)) {
                return array(
                    'test' => 'Meta Description Injection',
                    'status' => 'FAIL',
                    'message' => 'Failed to create test post'
                );
            }

            // Set up as landing page with custom SEO description
            update_post_meta($post_id, '_ez_translate_language', 'es');
            update_post_meta($post_id, '_ez_translate_is_landing', true);
            update_post_meta($post_id, '_ez_translate_seo_description', 'Custom SEO Description');

            // Simulate being on the frontend
            global $post;
            $post = get_post($post_id);

            // Test meta description injection
            $frontend = new \EZTranslate\Frontend();

            // Capture output
            ob_start();
            $frontend->inject_meta_description();
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            if (strpos($output, 'name="description"') === false || strpos($output, 'Custom SEO Description') === false) {
                return array(
                    'test' => 'Meta Description Injection',
                    'status' => 'FAIL',
                    'message' => 'Meta description was not properly injected'
                );
            }

            return array(
                'test' => 'Meta Description Injection',
                'status' => 'PASS',
                'message' => 'Meta description injected successfully for landing page'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Meta Description Injection',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test Open Graph metadata injection
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_open_graph_injection() {
        try {
            $frontend = new \EZTranslate\Frontend();
            
            // Use reflection to test private method
            $reflection = new ReflectionClass($frontend);
            $method = $reflection->getMethod('inject_open_graph_metadata');
            $method->setAccessible(true);

            // Create a test post context
            $post_id = wp_insert_post(array(
                'post_title' => 'Test Page',
                'post_content' => 'Test content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            global $post;
            $post = get_post($post_id);

            // Capture output
            ob_start();
            $method->invoke($frontend, 'Test Title', 'Test Description', 'es');
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            $has_og_title = strpos($output, 'property="og:title"') !== false;
            $has_og_description = strpos($output, 'property="og:description"') !== false;
            $has_og_locale = strpos($output, 'property="og:locale"') !== false;

            if (!$has_og_title || !$has_og_description || !$has_og_locale) {
                return array(
                    'test' => 'Open Graph Injection',
                    'status' => 'FAIL',
                    'message' => 'Open Graph metadata not properly injected'
                );
            }

            return array(
                'test' => 'Open Graph Injection',
                'status' => 'PASS',
                'message' => 'Open Graph metadata injected successfully'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Open Graph Injection',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test Twitter Card metadata injection
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_twitter_card_injection() {
        try {
            $frontend = new \EZTranslate\Frontend();
            
            // Use reflection to test private method
            $reflection = new ReflectionClass($frontend);
            $method = $reflection->getMethod('inject_twitter_card_metadata');
            $method->setAccessible(true);

            // Create a test post context
            $post_id = wp_insert_post(array(
                'post_title' => 'Test Page',
                'post_content' => 'Test content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            global $post;
            $post = get_post($post_id);

            // Capture output
            ob_start();
            $method->invoke($frontend, 'Test Title', 'Test Description');
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            $has_twitter_card = strpos($output, 'name="twitter:card"') !== false;
            $has_twitter_title = strpos($output, 'name="twitter:title"') !== false;
            $has_twitter_description = strpos($output, 'name="twitter:description"') !== false;

            if (!$has_twitter_card || !$has_twitter_title || !$has_twitter_description) {
                return array(
                    'test' => 'Twitter Card Injection',
                    'status' => 'FAIL',
                    'message' => 'Twitter Card metadata not properly injected'
                );
            }

            return array(
                'test' => 'Twitter Card Injection',
                'status' => 'PASS',
                'message' => 'Twitter Card metadata injected successfully'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Twitter Card Injection',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test JSON-LD structured data injection
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_json_ld_injection() {
        try {
            $frontend = new \EZTranslate\Frontend();
            
            // Use reflection to test private method
            $reflection = new ReflectionClass($frontend);
            $method = $reflection->getMethod('inject_json_ld_metadata');
            $method->setAccessible(true);

            // Create a test post context
            $post_id = wp_insert_post(array(
                'post_title' => 'Test Page',
                'post_content' => 'Test content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            global $post;
            $post = get_post($post_id);

            // Capture output
            ob_start();
            $method->invoke($frontend, 'Test Title', 'Test Description', 'es');
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            $has_json_ld = strpos($output, 'application/ld+json') !== false;
            $has_schema_context = strpos($output, 'schema.org') !== false;
            $has_webpage_type = strpos($output, 'WebPage') !== false;

            if (!$has_json_ld || !$has_schema_context || !$has_webpage_type) {
                return array(
                    'test' => 'JSON-LD Injection',
                    'status' => 'FAIL',
                    'message' => 'JSON-LD structured data not properly injected'
                );
            }

            return array(
                'test' => 'JSON-LD Injection',
                'status' => 'PASS',
                'message' => 'JSON-LD structured data injected successfully'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'JSON-LD Injection',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test language to locale conversion
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_language_locale_conversion() {
        try {
            $frontend = new \EZTranslate\Frontend();
            
            // Use reflection to test private method
            $reflection = new ReflectionClass($frontend);
            $method = $reflection->getMethod('convert_language_to_locale');
            $method->setAccessible(true);

            // Test known language codes
            $test_cases = array(
                'es' => 'es_ES',
                'en' => 'en_US',
                'fr' => 'fr_FR',
                'de' => 'de_DE',
                'unknown' => 'unknown_UNKNOWN'
            );

            foreach ($test_cases as $input => $expected) {
                $result = $method->invoke($frontend, $input);
                if ($result !== $expected) {
                    return array(
                        'test' => 'Language Locale Conversion',
                        'status' => 'FAIL',
                        'message' => "Conversion failed for {$input}: expected {$expected}, got {$result}"
                    );
                }
            }

            return array(
                'test' => 'Language Locale Conversion',
                'status' => 'PASS',
                'message' => 'Language to locale conversion working correctly'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Language Locale Conversion',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test non-landing page behavior
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_non_landing_page_behavior() {
        try {
            // Create a test page (not a landing page)
            $post_id = wp_insert_post(array(
                'post_title' => 'Regular Page',
                'post_content' => 'Test content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            if (is_wp_error($post_id)) {
                return array(
                    'test' => 'Non-Landing Page Behavior',
                    'status' => 'FAIL',
                    'message' => 'Failed to create test post'
                );
            }

            // Set language but NOT as landing page
            update_post_meta($post_id, '_ez_translate_language', 'es');
            update_post_meta($post_id, '_ez_translate_is_landing', false);

            // Simulate being on the frontend
            global $post;
            $post = get_post($post_id);

            // Test that no SEO metadata is injected
            $frontend = new \EZTranslate\Frontend();

            // Capture output
            ob_start();
            $frontend->override_head_metadata();
            $output = ob_get_clean();

            // Test title filtering (should not change)
            $title_parts = array('title' => 'Regular Page');
            $filtered_parts = $frontend->filter_document_title($title_parts);

            // Test meta description injection (should not inject)
            ob_start();
            $frontend->inject_meta_description();
            $description_output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            // Check that no metadata was injected
            $has_metadata = !empty($output) || !empty($description_output);
            $title_changed = $filtered_parts['title'] !== 'Regular Page';

            if ($has_metadata || $title_changed) {
                return array(
                    'test' => 'Non-Landing Page Behavior',
                    'status' => 'FAIL',
                    'message' => 'SEO metadata was injected for non-landing page'
                );
            }

            return array(
                'test' => 'Non-Landing Page Behavior',
                'status' => 'PASS',
                'message' => 'Non-landing pages correctly skip SEO metadata injection'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Non-Landing Page Behavior',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }


}

/**
 * Display Frontend SEO test results in admin interface
 *
 * @since 1.0.0
 */
function ez_translate_display_frontend_seo_tests() {
    echo '<h3>Frontend SEO Tests</h3>';

    $results = EZTranslateFrontendSEOTest::run_tests();

    $total_tests = count($results);
    $passed_tests = 0;

    echo '<table class="widefat" style="margin-top: 10px;">';
    echo '<thead><tr><th>Test</th><th>Status</th><th>Message</th></tr></thead>';
    echo '<tbody>';

    foreach ($results as $result) {
        $status_class = $result['status'] === 'PASS' ? 'style="color: green; font-weight: bold;"' : 'style="color: red; font-weight: bold;"';
        if ($result['status'] === 'PASS') {
            $passed_tests++;
        }

        echo '<tr>';
        echo '<td>' . esc_html($result['test']) . '</td>';
        echo '<td ' . $status_class . '>' . esc_html($result['status']) . '</td>';
        echo '<td>' . esc_html($result['message']) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    $status_color = $passed_tests === $total_tests ? 'green' : 'red';
    echo '<p style="margin-top: 15px; font-weight: bold; color: ' . $status_color . ';">';
    echo sprintf('Frontend SEO Tests: %d/%d passed', $passed_tests, $total_tests);
    echo '</p>';
}
