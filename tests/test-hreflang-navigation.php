<?php
/**
 * Test suite for Hreflang and Multilingual Navigation functionality
 *
 * Tests the automatic generation of hreflang tags and navigation
 * between related translations in the same translation group.
 *
 * @package EZTranslate
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Hreflang and Navigation functionality
 *
 * @since 1.0.0
 */
class EZTranslateHreflangNavigationTest {

    /**
     * Run all hreflang navigation tests
     *
     * @return array Test results
     * @since 1.0.0
     */
    public static function run_tests() {
        $results = array();

        // Test 1: Frontend Class Hreflang Hook Registration
        $results[] = self::test_frontend_hreflang_hooks();

        // Test 2: Hreflang Tags Generation for Translation Group
        $results[] = self::test_hreflang_tags_generation();

        // Test 3: Hreflang Language Code Conversion
        $results[] = self::test_hreflang_language_conversion();

        // Test 4: No Hreflang Tags for Single Language Posts
        $results[] = self::test_no_hreflang_for_single_posts();

        // Test 5: Hreflang Tags for Multiple Languages
        $results[] = self::test_hreflang_multiple_languages();

        // Test 6: Hreflang Tags Skip Posts Without Language
        $results[] = self::test_hreflang_skip_no_language();

        // Test 7: Hreflang Tags Skip Posts Without Group
        $results[] = self::test_hreflang_skip_no_group();

        // Test 8: Hreflang URL Generation
        $results[] = self::test_hreflang_url_generation();

        return $results;
    }

    /**
     * Test 1: Frontend Class Hreflang Hook Registration
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_frontend_hreflang_hooks() {
        try {
            // Initialize Frontend class
            $frontend = new \EZTranslate\Frontend();
            
            // Check if hreflang hook is properly registered
            $hreflang_priority = has_action('wp_head', array($frontend, 'inject_hreflang_tags'));

            if ($hreflang_priority === false) {
                return array(
                    'test' => 'Frontend Hreflang Hook Registration',
                    'status' => 'FAIL',
                    'message' => 'Hreflang hook not properly registered'
                );
            }

            // Verify priority is 3 (after SEO metadata)
            if ($hreflang_priority !== 3) {
                return array(
                    'test' => 'Frontend Hreflang Hook Registration',
                    'status' => 'FAIL',
                    'message' => 'Hreflang hook priority incorrect: expected 3, got ' . $hreflang_priority
                );
            }

            return array(
                'test' => 'Frontend Hreflang Hook Registration',
                'status' => 'PASS',
                'message' => 'Hreflang hook registered successfully with priority 3'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Frontend Hreflang Hook Registration',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 2: Hreflang Tags Generation for Translation Group
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_hreflang_tags_generation() {
        try {
            // Set up test languages
            ez_translate_ensure_test_languages();

            // Create test posts in different languages with same group
            $group_id = 'tg_test123456789abc';
            
            $post_en = wp_insert_post(array(
                'post_title' => 'Test Post English',
                'post_content' => 'English content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            $post_es = wp_insert_post(array(
                'post_title' => 'Test Post Spanish',
                'post_content' => 'Spanish content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            // Set metadata for both posts
            update_post_meta($post_en, '_ez_translate_language', 'en');
            update_post_meta($post_en, '_ez_translate_group', $group_id);
            
            update_post_meta($post_es, '_ez_translate_language', 'es');
            update_post_meta($post_es, '_ez_translate_group', $group_id);

            // Test hreflang generation for English post
            global $post;
            $post = get_post($post_en);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            // Capture output
            ob_start();
            $frontend->inject_hreflang_tags();
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_en, true);
            wp_delete_post($post_es, true);

            // Verify hreflang tags were generated
            if (empty($output)) {
                return array(
                    'test' => 'Hreflang Tags Generation',
                    'status' => 'FAIL',
                    'message' => 'No hreflang tags generated for translation group'
                );
            }

            // Check for both language tags and x-default
            if (strpos($output, 'hreflang="en"') === false || strpos($output, 'hreflang="es"') === false) {
                return array(
                    'test' => 'Hreflang Tags Generation',
                    'status' => 'FAIL',
                    'message' => 'Missing expected hreflang tags for en/es'
                );
            }

            // Check for x-default tag
            if (strpos($output, 'hreflang="x-default"') === false) {
                return array(
                    'test' => 'Hreflang Tags Generation',
                    'status' => 'FAIL',
                    'message' => 'Missing x-default hreflang tag'
                );
            }

            return array(
                'test' => 'Hreflang Tags Generation',
                'status' => 'PASS',
                'message' => 'Hreflang tags generated successfully for translation group'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Hreflang Tags Generation',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 3: Hreflang Language Code Conversion
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_hreflang_language_conversion() {
        try {
            $frontend = new \EZTranslate\Frontend();
            
            // Use reflection to access private method
            $reflection = new ReflectionClass($frontend);
            $method = $reflection->getMethod('convert_language_to_hreflang');
            $method->setAccessible(true);

            // Test common language conversions
            $test_cases = array(
                'en' => 'en',
                'es' => 'es',
                'zh' => 'zh-CN',
                'pt' => 'pt-BR',
                'fr' => 'fr',
                'de' => 'de',
                'unknown' => 'unknown'  // Should return as-is
            );

            foreach ($test_cases as $input => $expected) {
                $result = $method->invoke($frontend, $input);
                if ($result !== $expected) {
                    return array(
                        'test' => 'Hreflang Language Conversion',
                        'status' => 'FAIL',
                        'message' => "Language conversion failed: {$input} -> expected {$expected}, got {$result}"
                    );
                }
            }

            return array(
                'test' => 'Hreflang Language Conversion',
                'status' => 'PASS',
                'message' => 'Language code conversion working correctly'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Hreflang Language Conversion',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 4: No Hreflang Tags for Single Language Posts
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_no_hreflang_for_single_posts() {
        try {
            // Set up test languages
            ez_translate_ensure_test_languages();

            // Create single post with language but no related translations
            $post_id = wp_insert_post(array(
                'post_title' => 'Single Language Post',
                'post_content' => 'Content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            // Set metadata for single post
            update_post_meta($post_id, '_ez_translate_language', 'en');
            update_post_meta($post_id, '_ez_translate_group', 'tg_single123456789');

            // Test hreflang generation
            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            // Capture output
            ob_start();
            $frontend->inject_hreflang_tags();
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            // Should not generate hreflang tags for single post
            if (!empty($output) && strpos($output, 'hreflang') !== false) {
                return array(
                    'test' => 'No Hreflang for Single Posts',
                    'status' => 'FAIL',
                    'message' => 'Hreflang tags generated for single post (should not happen)'
                );
            }

            return array(
                'test' => 'No Hreflang for Single Posts',
                'status' => 'PASS',
                'message' => 'No hreflang tags generated for single post (correct behavior)'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'No Hreflang for Single Posts',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 5: Hreflang Tags for Multiple Languages
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_hreflang_multiple_languages() {
        try {
            // Set up test languages
            ez_translate_ensure_test_languages();

            // Create test posts in multiple languages with same group
            $group_id = 'tg_multi123456789';

            $post_en = wp_insert_post(array(
                'post_title' => 'Multi Test English',
                'post_content' => 'English content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            $post_es = wp_insert_post(array(
                'post_title' => 'Multi Test Spanish',
                'post_content' => 'Spanish content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            $post_fr = wp_insert_post(array(
                'post_title' => 'Multi Test French',
                'post_content' => 'French content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            // Set metadata for all posts
            update_post_meta($post_en, '_ez_translate_language', 'en');
            update_post_meta($post_en, '_ez_translate_group', $group_id);

            update_post_meta($post_es, '_ez_translate_language', 'es');
            update_post_meta($post_es, '_ez_translate_group', $group_id);

            update_post_meta($post_fr, '_ez_translate_language', 'fr');
            update_post_meta($post_fr, '_ez_translate_group', $group_id);

            // Test hreflang generation for English post
            global $post;
            $post = get_post($post_en);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            // Capture output
            ob_start();
            $frontend->inject_hreflang_tags();
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_en, true);
            wp_delete_post($post_es, true);
            wp_delete_post($post_fr, true);

            // Verify all three language tags are present
            $expected_languages = array('en', 'es', 'fr');
            foreach ($expected_languages as $lang) {
                if (strpos($output, 'hreflang="' . $lang . '"') === false) {
                    return array(
                        'test' => 'Hreflang Multiple Languages',
                        'status' => 'FAIL',
                        'message' => "Missing hreflang tag for language: {$lang}"
                    );
                }
            }

            // Count the number of hreflang tags (should be 3)
            $tag_count = substr_count($output, 'hreflang=');
            if ($tag_count !== 3) {
                return array(
                    'test' => 'Hreflang Multiple Languages',
                    'status' => 'FAIL',
                    'message' => "Expected 3 hreflang tags, found {$tag_count}"
                );
            }

            return array(
                'test' => 'Hreflang Multiple Languages',
                'status' => 'PASS',
                'message' => 'Hreflang tags generated correctly for 3 languages'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Hreflang Multiple Languages',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 6: Hreflang Tags Skip Posts Without Language
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_hreflang_skip_no_language() {
        try {
            // Create post without language metadata
            $post_id = wp_insert_post(array(
                'post_title' => 'Post Without Language',
                'post_content' => 'Content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            // Set only group metadata (no language)
            update_post_meta($post_id, '_ez_translate_group', 'tg_nolang123456789');

            // Test hreflang generation
            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            // Capture output
            ob_start();
            $frontend->inject_hreflang_tags();
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            // Should not generate hreflang tags without language
            if (!empty($output) && strpos($output, 'hreflang') !== false) {
                return array(
                    'test' => 'Hreflang Skip No Language',
                    'status' => 'FAIL',
                    'message' => 'Hreflang tags generated for post without language'
                );
            }

            return array(
                'test' => 'Hreflang Skip No Language',
                'status' => 'PASS',
                'message' => 'Correctly skipped hreflang for post without language'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Hreflang Skip No Language',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 7: Hreflang Tags Skip Posts Without Group
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_hreflang_skip_no_group() {
        try {
            // Set up test languages
            ez_translate_ensure_test_languages();

            // Create post without group metadata
            $post_id = wp_insert_post(array(
                'post_title' => 'Post Without Group',
                'post_content' => 'Content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            // Set only language metadata (no group)
            update_post_meta($post_id, '_ez_translate_language', 'en');

            // Test hreflang generation
            global $post;
            $post = get_post($post_id);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            // Capture output
            ob_start();
            $frontend->inject_hreflang_tags();
            $output = ob_get_clean();

            // Clean up
            wp_delete_post($post_id, true);

            // Should not generate hreflang tags without group
            if (!empty($output) && strpos($output, 'hreflang') !== false) {
                return array(
                    'test' => 'Hreflang Skip No Group',
                    'status' => 'FAIL',
                    'message' => 'Hreflang tags generated for post without group'
                );
            }

            return array(
                'test' => 'Hreflang Skip No Group',
                'status' => 'PASS',
                'message' => 'Correctly skipped hreflang for post without group'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Hreflang Skip No Group',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test 8: Hreflang URL Generation
     *
     * @return array Test result
     * @since 1.0.0
     */
    private static function test_hreflang_url_generation() {
        try {
            // Set up test languages
            ez_translate_ensure_test_languages();

            // Create test posts in different languages with same group
            $group_id = 'tg_urltest123456789';

            $post_en = wp_insert_post(array(
                'post_title' => 'URL Test English',
                'post_content' => 'English content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            $post_es = wp_insert_post(array(
                'post_title' => 'URL Test Spanish',
                'post_content' => 'Spanish content',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));

            // Set metadata for both posts
            update_post_meta($post_en, '_ez_translate_language', 'en');
            update_post_meta($post_en, '_ez_translate_group', $group_id);

            update_post_meta($post_es, '_ez_translate_language', 'es');
            update_post_meta($post_es, '_ez_translate_group', $group_id);

            // Test hreflang generation for English post
            global $post;
            $post = get_post($post_en);

            $frontend = new \EZTranslate\Frontend();
            $frontend->enable_test_mode();

            // Capture output
            ob_start();
            $frontend->inject_hreflang_tags();
            $output = ob_get_clean();

            // Get expected URLs
            $en_url = get_permalink($post_en);
            $es_url = get_permalink($post_es);

            // Clean up
            wp_delete_post($post_en, true);
            wp_delete_post($post_es, true);

            // Verify URLs are present in output
            if (strpos($output, $en_url) === false) {
                return array(
                    'test' => 'Hreflang URL Generation',
                    'status' => 'FAIL',
                    'message' => 'English URL not found in hreflang tags'
                );
            }

            if (strpos($output, $es_url) === false) {
                return array(
                    'test' => 'Hreflang URL Generation',
                    'status' => 'FAIL',
                    'message' => 'Spanish URL not found in hreflang tags'
                );
            }

            // Verify proper link structure
            if (strpos($output, '<link rel="alternate"') === false) {
                return array(
                    'test' => 'Hreflang URL Generation',
                    'status' => 'FAIL',
                    'message' => 'Proper link rel="alternate" structure not found'
                );
            }

            return array(
                'test' => 'Hreflang URL Generation',
                'status' => 'PASS',
                'message' => 'Hreflang URLs generated correctly with proper link structure'
            );

        } catch (Exception $e) {
            return array(
                'test' => 'Hreflang URL Generation',
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            );
        }
    }
}

/**
 * Display hreflang navigation test results in admin interface
 *
 * @since 1.0.0
 */
function ez_translate_display_hreflang_navigation_tests() {
    echo '<h3>Hreflang and Multilingual Navigation Tests</h3>';

    $test_results = EZTranslateHreflangNavigationTest::run_tests();

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
        echo '<h4 style="margin: 0 0 10px 0;">🎉 All Hreflang Tests Passed!</h4>';
        echo '<p style="margin: 0;">The hreflang and multilingual navigation functionality is working correctly. ';
        echo 'Your site will now automatically generate proper hreflang tags for pages with translations, ';
        echo 'helping search engines understand the relationship between different language versions of your content.</p>';
        echo '</div>';
    } else {
        echo '<div style="margin: 20px 0; padding: 15px; background: #fef7f1; border-left: 4px solid #dba617;">';
        echo '<h4 style="margin: 0 0 10px 0;">⚠️ Some Tests Failed</h4>';
        echo '<p style="margin: 0;">Please review the failed tests above. ';
        echo 'Hreflang functionality may not work correctly until these issues are resolved.</p>';
        echo '</div>';
    }
}
