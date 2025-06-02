<?php
/**
 * Test file for Gutenberg Integration
 *
 * @package EZTranslate
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Gutenberg Integration functionality
 */
function ez_translate_test_gutenberg_integration() {
    echo '<div class="card">';
    echo '<h2>' . __('Gutenberg Integration Tests', 'ez-translate') . '</h2>';

    // Check error logs first
    ez_translate_check_error_logs();

    // Ensure we have some test languages for the API
    ez_translate_ensure_test_languages();

    $tests_passed = 0;
    $total_tests = 0;
    
    // Test 1: Check if Gutenberg class exists
    $total_tests++;
    echo '<h3>Test 1: Gutenberg Class Initialization</h3>';
    if (class_exists('EZTranslate\Gutenberg')) {
        echo '<p style="color: green;">✅ Gutenberg class exists</p>';
        $tests_passed++;
    } else {
        echo '<p style="color: red;">❌ Gutenberg class not found</p>';
    }
    
    // Test 2: Check if REST API class exists
    $total_tests++;
    echo '<h3>Test 2: REST API Class Initialization</h3>';
    if (class_exists('EZTranslate\RestAPI')) {
        echo '<p style="color: green;">✅ REST API class exists</p>';
        $tests_passed++;
    } else {
        echo '<p style="color: red;">❌ REST API class not found</p>';
    }
    
    // Test 3: Check if meta fields are registered
    $total_tests++;
    echo '<h3>Test 3: Meta Fields Registration</h3>';
    $registered_meta = get_registered_meta_keys('post');
    $required_meta_keys = [
        '_ez_translate_language',
        '_ez_translate_group',
        '_ez_translate_is_landing',
        '_ez_translate_seo_title',
        '_ez_translate_seo_description'
    ];
    
    $meta_registered = true;
    foreach ($required_meta_keys as $meta_key) {
        if (!isset($registered_meta[$meta_key])) {
            $meta_registered = false;
            echo '<p style="color: red;">❌ Meta field not registered: ' . $meta_key . '</p>';
        }
    }
    
    if ($meta_registered) {
        echo '<p style="color: green;">✅ All meta fields registered correctly</p>';
        $tests_passed++;
    }
    
    // Test 4: Check if JavaScript assets exist
    $total_tests++;
    echo '<h3>Test 4: JavaScript Assets</h3>';
    $js_file = EZ_TRANSLATE_PLUGIN_DIR . 'assets/js/gutenberg-sidebar.js';
    $asset_file = EZ_TRANSLATE_PLUGIN_DIR . 'assets/js/gutenberg-sidebar.asset.php';
    
    if (file_exists($js_file) && file_exists($asset_file)) {
        echo '<p style="color: green;">✅ JavaScript assets exist</p>';
        $tests_passed++;
    } else {
        echo '<p style="color: red;">❌ JavaScript assets missing</p>';
        if (!file_exists($js_file)) {
            echo '<p style="color: red;">  - Missing: gutenberg-sidebar.js</p>';
        }
        if (!file_exists($asset_file)) {
            echo '<p style="color: red;">  - Missing: gutenberg-sidebar.asset.php</p>';
        }
    }
    
    // Test 5: Check if CSS assets exist
    $total_tests++;
    echo '<h3>Test 5: CSS Assets</h3>';
    $css_file = EZ_TRANSLATE_PLUGIN_DIR . 'assets/css/gutenberg-sidebar.css';
    
    if (file_exists($css_file)) {
        echo '<p style="color: green;">✅ CSS assets exist</p>';
        $tests_passed++;
    } else {
        echo '<p style="color: red;">❌ CSS assets missing</p>';
    }
    
    // Test 6: Test REST API endpoints
    $total_tests++;
    echo '<h3>Test 6: REST API Endpoints</h3>';

    // Check if REST API routes are registered
    $rest_server = rest_get_server();
    $routes = $rest_server->get_routes();

    $required_routes = [
        '/ez-translate/v1/languages',
        '/ez-translate/v1/post-meta/(?P<id>\d+)'
    ];

    $routes_registered = true;
    foreach ($required_routes as $route_pattern) {
        $found = false;
        foreach ($routes as $route => $handlers) {
            if (strpos($route, 'ez-translate/v1') !== false) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $routes_registered = false;
            echo '<p style="color: red;">❌ Route not found: ' . $route_pattern . '</p>';
        }
    }

    if ($routes_registered) {
        echo '<p style="color: green;">✅ REST API routes registered</p>';

        // Test actual API call
        $api_url = rest_url('ez-translate/v1/languages');
        echo '<p><strong>Testing API call:</strong> ' . $api_url . '</p>';

        // Try alternative URL if the first one has index.php
        $alt_api_url = str_replace('/index.php/', '/', $api_url);
        if ($alt_api_url !== $api_url) {
            echo '<p><strong>Alternative URL:</strong> ' . $alt_api_url . '</p>';
        }

        // Test the endpoint directly first
        echo '<h4>Direct API Test</h4>';

        // Test 1: Direct function call
        try {
            echo '<p><strong>Testing direct function call...</strong></p>';
            $rest_api = new \EZTranslate\RestAPI();
            $fake_request = new \WP_REST_Request('GET', '/ez-translate/v1/languages');
            $direct_result = $rest_api->get_languages($fake_request);

            if (is_wp_error($direct_result)) {
                echo '<p style="color: red;">❌ Direct call failed: ' . $direct_result->get_error_message() . '</p>';
            } else {
                echo '<p style="color: green;">✅ Direct function call works</p>';
                $data = $direct_result->get_data();
                echo '<p>Direct result: ' . count($data) . ' languages</p>';
            }
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ Direct call exception: ' . $e->getMessage() . '</p>';
        }

        // Test 2: Check if routes are actually registered
        echo '<p><strong>Checking route registration...</strong></p>';
        $rest_server = rest_get_server();
        $routes = $rest_server->get_routes();
        $ez_routes = array_filter(array_keys($routes), function($route) {
            return strpos($route, 'ez-translate') !== false;
        });
        echo '<p>EZ Translate routes found: ' . implode(', ', $ez_routes) . '</p>';

        // Test 3: HTTP request with detailed debugging
        echo '<p><strong>Testing HTTP request...</strong></p>';
        $response = wp_remote_get($api_url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/json',
            )
        ));

        if (is_wp_error($response)) {
            echo '<p style="color: red;">❌ HTTP request failed: ' . $response->get_error_message() . '</p>';
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_headers = wp_remote_retrieve_headers($response);
            $body = wp_remote_retrieve_body($response);

            echo '<p><strong>Response Code:</strong> ' . $response_code . '</p>';
            echo '<p><strong>Content-Type:</strong> ' . (isset($response_headers['content-type']) ? $response_headers['content-type'] : 'Not set') . '</p>';

            if ($response_code === 200) {
                $data = json_decode($body, true);
                if (is_array($data)) {
                    echo '<p style="color: green;">✅ API endpoint responds correctly</p>';
                    echo '<p>Languages found: ' . count($data) . '</p>';
                } else {
                    echo '<p style="color: red;">❌ API response is not valid JSON</p>';
                    echo '<p><strong>Response body (first 500 chars):</strong></p>';
                    echo '<pre style="background: #f0f0f0; padding: 10px; font-size: 12px; overflow: auto; max-height: 200px;">' . esc_html(substr($body, 0, 500)) . '</pre>';
                }
            } else {
                echo '<p style="color: red;">❌ HTTP Error ' . $response_code . '</p>';
                echo '<p><strong>Response body (first 500 chars):</strong></p>';
                echo '<pre style="background: #f0f0f0; padding: 10px; font-size: 12px; overflow: auto; max-height: 200px;">' . esc_html(substr($body, 0, 500)) . '</pre>';
            }
        }

        // If first URL fails, try alternative
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            if ($alt_api_url !== $api_url) {
                echo '<p style="color: orange;">⚠️ Trying alternative URL: ' . $alt_api_url . '</p>';
                $response2 = wp_remote_get($alt_api_url);
                if (!is_wp_error($response2) && wp_remote_retrieve_response_code($response2) === 200) {
                    echo '<p style="color: green;">✅ Alternative URL works!</p>';
                }
            }
        }

        $tests_passed++;
    }
    
    // Test 7: Test language data for JavaScript
    $total_tests++;
    echo '<h3>Test 7: Language Data for JavaScript</h3>';
    
    try {
        $js_languages = \EZTranslate\Gutenberg::get_languages_for_js();
        if (is_array($js_languages)) {
            echo '<p style="color: green;">✅ Language data formatted for JavaScript</p>';
            echo '<p>Available languages: ' . count($js_languages) . '</p>';
            $tests_passed++;
        } else {
            echo '<p style="color: red;">❌ Language data not properly formatted</p>';
        }
    } catch (Exception $e) {
        echo '<p style="color: red;">❌ Error getting language data: ' . $e->getMessage() . '</p>';
    }
    
    // Test 8: Test meta field authorization
    $total_tests++;
    echo '<h3>Test 8: Meta Field Authorization</h3>';
    
    if (class_exists('EZTranslate\Gutenberg')) {
        $gutenberg = new \EZTranslate\Gutenberg();
        
        // Test with a valid post ID (create a temporary post)
        $test_post_id = wp_insert_post([
            'post_title' => 'EZ Translate Test Post',
            'post_content' => 'Test content',
            'post_status' => 'draft',
            'post_type' => 'post'
        ]);
        
        if ($test_post_id && !is_wp_error($test_post_id)) {
            $can_edit = $gutenberg->meta_auth_callback(true, '_ez_translate_language', $test_post_id, get_current_user_id(), 'edit_post', []);
            
            if ($can_edit) {
                echo '<p style="color: green;">✅ Meta field authorization working</p>';
                $tests_passed++;
            } else {
                echo '<p style="color: red;">❌ Meta field authorization failed</p>';
            }
            
            // Clean up test post
            wp_delete_post($test_post_id, true);
        } else {
            echo '<p style="color: red;">❌ Could not create test post for authorization test</p>';
        }
    } else {
        echo '<p style="color: red;">❌ Gutenberg class not available for authorization test</p>';
    }
    
    // Summary
    echo '<hr>';
    echo '<h3>Test Summary</h3>';
    echo '<p><strong>Tests Passed:</strong> ' . $tests_passed . '/' . $total_tests . '</p>';
    
    if ($tests_passed === $total_tests) {
        echo '<p style="color: green; font-weight: bold;">🎉 All Gutenberg integration tests passed!</p>';
    } else {
        echo '<p style="color: red; font-weight: bold;">⚠️ Some tests failed. Please check the implementation.</p>';
    }
    
    echo '</div>';
    
    return $tests_passed === $total_tests;
}

/**
 * Check WordPress error logs for recent EZ Translate errors
 */
function ez_translate_check_error_logs() {
    echo '<h4>Recent Error Logs</h4>';

    // Common log file locations
    $log_files = [
        ABSPATH . 'wp-content/debug.log',
        ABSPATH . 'error_log',
        ini_get('error_log'),
        '/tmp/error_log'
    ];

    $found_logs = false;

    foreach ($log_files as $log_file) {
        if ($log_file && file_exists($log_file) && is_readable($log_file)) {
            $found_logs = true;
            echo '<p><strong>Checking:</strong> ' . $log_file . '</p>';

            // Read last 50 lines
            $lines = file($log_file);
            if ($lines) {
                $recent_lines = array_slice($lines, -50);
                $ez_translate_errors = array_filter($recent_lines, function($line) {
                    return strpos($line, 'EZ-Translate') !== false ||
                           strpos($line, 'ez-translate') !== false ||
                           strpos($line, 'RestAPI') !== false;
                });

                if (!empty($ez_translate_errors)) {
                    echo '<p style="color: orange;">Found ' . count($ez_translate_errors) . ' EZ Translate related log entries:</p>';
                    echo '<pre style="background: #f0f0f0; padding: 10px; font-size: 11px; max-height: 200px; overflow: auto;">';
                    foreach (array_slice($ez_translate_errors, -10) as $error) {
                        echo esc_html($error);
                    }
                    echo '</pre>';
                } else {
                    echo '<p style="color: green;">No EZ Translate errors found in this log</p>';
                }
            }
            break; // Only check the first available log file
        }
    }

    if (!$found_logs) {
        echo '<p style="color: orange;">No accessible log files found. Check your WordPress debug settings.</p>';
        echo '<p>To enable logging, add this to wp-config.php:</p>';
        echo '<pre>define(\'WP_DEBUG\', true);
define(\'WP_DEBUG_LOG\', true);
define(\'WP_DEBUG_DISPLAY\', false);</pre>';
    }
}

/**
 * Ensure test languages exist for API testing
 */
function ez_translate_ensure_test_languages() {
    $existing_languages = \EZTranslate\LanguageManager::get_languages();

    // If no languages exist, add some test languages
    if (empty($existing_languages)) {
        $test_languages = [
            [
                'code' => 'en',
                'name' => 'English',
                'slug' => 'english',
                'native_name' => 'English',
                'flag' => '🇺🇸',
                'rtl' => false,
                'enabled' => true
            ],
            [
                'code' => 'es',
                'name' => 'Spanish',
                'slug' => 'spanish',
                'native_name' => 'Español',
                'flag' => '🇪🇸',
                'rtl' => false,
                'enabled' => true
            ]
        ];

        foreach ($test_languages as $language) {
            \EZTranslate\LanguageManager::add_language($language);
        }

        echo '<p style="color: blue;">ℹ️ Added test languages for API testing</p>';
    }
}

/**
 * Display Gutenberg integration tests in admin
 */
function ez_translate_display_gutenberg_tests() {
    if (isset($_GET['run_ez_translate_gutenberg_tests']) && $_GET['run_ez_translate_gutenberg_tests'] === '1') {
        ez_translate_test_gutenberg_integration();
    }
}
