<?php
/**
 * Test suite for Complete Metadata Control functionality
 *
 * Tests the new complete metadata control system that takes over
 * all SEO metadata generation for multilingual pages.
 *
 * @package EZTranslate
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include test utilities
require_once EZ_TRANSLATE_PLUGIN_DIR . 'tests/test-utilities.php';

/**
 * Test Complete Metadata Control functionality
 *
 * @since 1.0.0
 */
class EZTranslateMetadataControlTest {

    /**
     * Run all metadata control tests
     *
     * @return array Test results
     * @since 1.0.0
     */
    public static function run_tests() {
        $results = array();

        // Test 1: Language Attributes Filter
        $results[] = self::test_language_attributes_filter();

        // Test 2: Complete Metadata Generation
        $results[] = self::test_complete_metadata_generation();

        // Test 3: Article vs Website OG Type
        $results[] = self::test_og_type_detection();

        // Test 4: Correct URL Generation
        $results[] = self::test_correct_url_generation();

        // Test 5: Language-specific Locale
        $results[] = self::test_language_locale_conversion();

        // Test 6: Post Excerpt Generation
        $results[] = self::test_post_excerpt_generation();

        // Test 7: JSON-LD Structured Data
        $results[] = self::test_jsonld_generation();

        return $results;
    }

    /**
     * Test 1: Language Attributes Filter
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_language_attributes_filter() {
        try {
            // Set up test languages
            ez_translate_ensure_test_languages();

            // Create test post
            $post_id = wp_insert_post(array(
                'post_title' => 'Language Attributes Test',
                'post_content' => 'Test content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            // Set language metadata
            update_post_meta($post_id, '_ez_translate_language', 'es');

            // Test language attributes filter
            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            // Test the filter
            $original_attributes = 'dir="ltr"';
            $filtered_attributes = $frontend->filter_language_attributes($original_attributes);

            // Clean up
            wp_delete_post($post_id, true);

            // Verify language attribute was added
            if (strpos($filtered_attributes, 'lang="es"') === false) {
                return array(
                    'test' => 'Language Attributes Filter',
                    'status' => 'FAIL',
                    'message' => 'Language attribute not added correctly'
                );
            }

            return array(
                'test' => 'Language Attributes Filter',
                'status' => 'PASS',
                'message' => 'Language attribute added successfully'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Language Attributes Filter',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 2: Complete Metadata Generation
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_complete_metadata_generation() {
        try {
            // Set up test languages
            ez_translate_ensure_test_languages();

            // Create test post
            $post_id = wp_insert_post(array(
                'post_title' => 'Metadata Test Post',
                'post_content' => 'This is test content for metadata generation.',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            // Set metadata
            update_post_meta($post_id, '_ez_translate_language', 'pt');
            update_post_meta($post_id, '_ez_translate_seo_title', 'Custom SEO Title');
            update_post_meta($post_id, '_ez_translate_seo_description', 'Custom SEO Description');

            // Test metadata generation
            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            // Capture output
            ob_start();
            $frontend->override_head_metadata();
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            // Verify essential metadata is present
            $required_tags = array(
                'meta name="description"',
                'meta property="og:title"',
                'meta property="og:description"',
                'meta property="og:url"',
                'meta property="og:locale"',
                'meta name="twitter:title"'
            );

            foreach ($required_tags as $tag) {
                if (strpos($output, $tag) === false) {
                    return array(
                        'test' => 'Complete Metadata Generation',
                        'status' => 'FAIL',
                        'message' => "Missing required tag: {$tag}"
                    );
                }
            }

            // Verify custom SEO title is used
            if (strpos($output, 'Custom SEO Title') === false) {
                return array(
                    'test' => 'Complete Metadata Generation',
                    'status' => 'FAIL',
                    'message' => 'Custom SEO title not used in metadata'
                );
            }

            return array(
                'test' => 'Complete Metadata Generation',
                'status' => 'PASS',
                'message' => 'Complete metadata generated successfully'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Complete Metadata Generation',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 3: Article vs Website OG Type
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_og_type_detection() {
        try {
            // Set up test languages
            ez_translate_ensure_test_languages();

            // Test regular page (should be article)
            $post_id = wp_insert_post(array(
                'post_title' => 'Regular Article',
                'post_content' => 'Article content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            update_post_meta($post_id, '_ez_translate_language', 'en');
            update_post_meta($post_id, '_ez_translate_is_landing', false);

            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            ob_start();
            $frontend->override_head_metadata();
            $article_output = ob_get_clean();

            // Test landing page (should be website)
            update_post_meta($post_id, '_ez_translate_is_landing', true);

            ob_start();
            $frontend->override_head_metadata();
            $landing_output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            // Verify og:type is correct
            if (strpos($article_output, 'og:type" content="article"') === false) {
                return array(
                    'test' => 'OG Type Detection',
                    'status' => 'FAIL',
                    'message' => 'Regular page should have og:type="article"'
                );
            }

            if (strpos($landing_output, 'og:type" content="website"') === false) {
                return array(
                    'test' => 'OG Type Detection',
                    'status' => 'FAIL',
                    'message' => 'Landing page should have og:type="website"'
                );
            }

            return array(
                'test' => 'OG Type Detection',
                'status' => 'PASS',
                'message' => 'OG type detection working correctly'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'OG Type Detection',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 4: Correct URL Generation
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_correct_url_generation() {
        try {
            // Set up test languages
            ez_translate_ensure_test_languages();

            // Create test post
            $post_id = wp_insert_post(array(
                'post_title' => 'URL Test Post',
                'post_content' => 'Content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            update_post_meta($post_id, '_ez_translate_language', 'fr');

            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            ob_start();
            $frontend->override_head_metadata();
            $output = ob_get_clean();

            $expected_url = get_permalink($post_id);

            // Clean up
            wp_delete_post($post_id, true);

            // Verify correct URL is used
            if (strpos($output, 'og:url" content="' . $expected_url . '"') === false) {
                return array(
                    'test' => 'Correct URL Generation',
                    'status' => 'FAIL',
                    'message' => 'Correct URL not found in og:url'
                );
            }

            return array(
                'test' => 'Correct URL Generation',
                'status' => 'PASS',
                'message' => 'Correct URL generated in metadata'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Correct URL Generation',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 5: Language-specific Locale
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_language_locale_conversion() {
        try {
            // Set up test languages
            ez_translate_ensure_test_languages();

            // Create test post
            $post_id = wp_insert_post(array(
                'post_title' => 'Locale Test',
                'post_content' => 'Content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            update_post_meta($post_id, '_ez_translate_language', 'pt');

            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            ob_start();
            $frontend->override_head_metadata();
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            // Verify correct locale is used (pt should become pt_BR)
            if (strpos($output, 'og:locale" content="pt_BR"') === false) {
                return array(
                    'test' => 'Language Locale Conversion',
                    'status' => 'FAIL',
                    'message' => 'Portuguese locale not converted to pt_BR'
                );
            }

            return array(
                'test' => 'Language Locale Conversion',
                'status' => 'PASS',
                'message' => 'Language locale conversion working correctly'
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
     * Test 6: Post Excerpt Generation
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_post_excerpt_generation() {
        try {
            // Set up test languages
            ez_translate_ensure_test_languages();

            // Create test post with long content
            $long_content = str_repeat('This is a very long content that should be truncated for meta description. ', 10);

            $post_id = wp_insert_post(array(
                'post_title' => 'Excerpt Test',
                'post_content' => $long_content,
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            update_post_meta($post_id, '_ez_translate_language', 'en');

            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            ob_start();
            $frontend->override_head_metadata();
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            // Verify meta description is present and truncated
            if (strpos($output, 'meta name="description"') === false) {
                return array(
                    'test' => 'Post Excerpt Generation',
                    'status' => 'FAIL',
                    'message' => 'Meta description not generated'
                );
            }

            // Check if content is truncated (should contain "...")
            if (strpos($output, '...') === false) {
                return array(
                    'test' => 'Post Excerpt Generation',
                    'status' => 'FAIL',
                    'message' => 'Long content not truncated properly'
                );
            }

            return array(
                'test' => 'Post Excerpt Generation',
                'status' => 'PASS',
                'message' => 'Post excerpt generation working correctly'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Post Excerpt Generation',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 7: JSON-LD Structured Data
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_jsonld_generation() {
        try {
            // Set up test languages
            ez_translate_ensure_test_languages();

            // Create test post (not landing page)
            $post_id = wp_insert_post(array(
                'post_title' => 'JSON-LD Test Article',
                'post_content' => 'Article content for JSON-LD',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            update_post_meta($post_id, '_ez_translate_language', 'de');
            update_post_meta($post_id, '_ez_translate_is_landing', false);

            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            ob_start();
            $frontend->override_head_metadata();
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            // Verify JSON-LD is present for articles
            if (strpos($output, 'application/ld+json') === false) {
                return array(
                    'test' => 'JSON-LD Structured Data',
                    'status' => 'FAIL',
                    'message' => 'JSON-LD script not found'
                );
            }

            // Verify it contains Article type
            if (strpos($output, '"@type":"Article"') === false) {
                return array(
                    'test' => 'JSON-LD Structured Data',
                    'status' => 'FAIL',
                    'message' => 'JSON-LD does not contain Article type'
                );
            }

            return array(
                'test' => 'JSON-LD Structured Data',
                'status' => 'PASS',
                'message' => 'JSON-LD structured data generated correctly'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'JSON-LD Structured Data',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }
}

/**
 * Display metadata control test results in admin interface
 *
 * @since 1.0.0
 */
function ez_translate_display_metadata_control_tests() {
    echo '<h3>Complete Metadata Control Tests</h3>';

    $test_results = EZTranslateMetadataControlTest::run_tests();

    $total_tests = count($test_results);
    $passed_tests = 0;
    $failed_tests = 0;

    foreach ($test_results as $result) {
        if ($result['status'] === 'PASS') {
            $passed_tests++;
        } else {
            $failed_tests++;
        }
    }

    // Display summary
    echo '<div style="margin: 20px 0; padding: 15px; border-left: 4px solid ' .
         ($failed_tests === 0 ? '#00a32a' : '#d63638') . '; background: #f9f9f9;">';
    echo '<h4 style="margin: 0 0 10px 0;">Test Summary</h4>';
    echo '<p style="margin: 0;"><strong>Total Tests:</strong> ' . $total_tests . '</p>';
    echo '<p style="margin: 0;"><strong>Passed:</strong> <span style="color: #00a32a;">' . $passed_tests . '</span></p>';
    echo '<p style="margin: 0;"><strong>Failed:</strong> <span style="color: #d63638;">' . $failed_tests . '</span></p>';
    echo '</div>';

    // Display individual test results
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Test</th><th>Status</th><th>Message</th></tr></thead>';
    echo '<tbody>';

    foreach ($test_results as $result) {
        $status_color = $result['status'] === 'PASS' ? '#00a32a' : '#d63638';
        $status_icon = $result['status'] === 'PASS' ? '✅' : '❌';

        echo '<tr>';
        echo '<td><strong>' . esc_html($result['test']) . '</strong></td>';
        echo '<td style="color: ' . $status_color . '; font-weight: bold;">' .
             $status_icon . ' ' . esc_html($result['status']) . '</td>';
        echo '<td>' . esc_html($result['message']) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    if ($failed_tests === 0) {
        echo '<div style="margin: 20px 0; padding: 15px; background: #d1edff; border-left: 4px solid #0073aa;">';
        echo '<h4 style="margin: 0 0 10px 0;">🎉 All Metadata Control Tests Passed!</h4>';
        echo '<p style="margin: 0;">The complete metadata control system is working correctly. ';
        echo 'Your multilingual pages now have consistent, language-specific SEO metadata including ';
        echo 'proper Open Graph tags, Twitter Cards, and JSON-LD structured data.</p>';
        echo '</div>';
    } else {
        echo '<div style="margin: 20px 0; padding: 15px; background: #fef7f1; border-left: 4px solid #dba617;">';
        echo '<h4 style="margin: 0 0 10px 0;">⚠️ Some Tests Failed</h4>';
        echo '<p style="margin: 0;">Please review the failed tests above. ';
        echo 'Metadata control may not work correctly until these issues are resolved.</p>';
        echo '</div>';
    }
}
