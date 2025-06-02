<?php
/**
 * Shared test utilities for EZ Translate
 *
 * @package EZTranslate
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ensure test languages exist for testing
 * This function is shared across all test files to avoid redeclaration errors
 *
 * @since 1.0.0
 */
function ez_translate_ensure_test_languages() {
    // Check if function was already called to avoid duplicate operations
    static $languages_ensured = false;
    if ($languages_ensured) {
        return;
    }

    $existing_languages = \EZTranslate\LanguageManager::get_languages();

    // Check if we have English and Spanish
    $has_english = false;
    $has_spanish = false;

    foreach ($existing_languages as $lang) {
        if ($lang['code'] === 'en') {
            $has_english = true;
        }
        if ($lang['code'] === 'es') {
            $has_spanish = true;
        }
    }

    // Add English if missing
    if (!$has_english) {
        $english_lang = array(
            'code' => 'en',
            'name' => 'English',
            'slug' => 'english',
            'native_name' => 'English',
            'flag' => '🇺🇸',
            'rtl' => false,
            'enabled' => true
        );

        try {
            \EZTranslate\LanguageManager::add_language($english_lang);
        } catch (Exception $e) {
            // Language might already exist, ignore duplicate errors
            if (strpos($e->getMessage(), 'duplicate') === false) {
                error_log('[EZ-Translate] Test setup: Could not add English language: ' . $e->getMessage());
            }
        }
    }

    // Add Spanish if missing
    if (!$has_spanish) {
        $spanish_lang = array(
            'code' => 'es',
            'name' => 'Spanish',
            'slug' => 'spanish',
            'native_name' => 'Español',
            'flag' => '🇪🇸',
            'rtl' => false,
            'enabled' => true
        );

        try {
            \EZTranslate\LanguageManager::add_language($spanish_lang);
        } catch (Exception $e) {
            // Language might already exist, ignore duplicate errors
            if (strpos($e->getMessage(), 'duplicate') === false) {
                error_log('[EZ-Translate] Test setup: Could not add Spanish language: ' . $e->getMessage());
            }
        }
    }

    $languages_ensured = true;
}

/**
 * Clean up test languages after testing
 * Removes test languages that were added during testing
 *
 * @since 1.0.0
 */
function ez_translate_cleanup_test_languages() {
    $test_codes = array('test', 'dup', 'temp');

    // Get existing languages first to avoid deletion errors
    $existing_languages = \EZTranslate\LanguageManager::get_languages();
    $existing_codes = array_keys($existing_languages);

    foreach ($test_codes as $code) {
        // Only try to delete if the language actually exists
        if (in_array($code, $existing_codes)) {
            try {
                \EZTranslate\LanguageManager::delete_language($code);
            } catch (Exception $e) {
                // Log but don't fail the test
                error_log('[EZ-Translate] Test cleanup: Could not delete test language ' . $code . ': ' . $e->getMessage());
            }
        }
    }
}

/**
 * Check error logs for critical issues
 * Displays any recent errors in the WordPress error log
 *
 * @since 1.0.0
 */
function ez_translate_check_error_logs() {
    // Only show this in debug mode
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    $error_log_path = ini_get('error_log');
    if (!$error_log_path || !file_exists($error_log_path)) {
        return;
    }

    // Read last 50 lines of error log
    $lines = array();
    $file = new SplFileObject($error_log_path);
    $file->seek(PHP_INT_MAX);
    $total_lines = $file->key();
    
    $start_line = max(0, $total_lines - 50);
    $file->seek($start_line);
    
    while (!$file->eof()) {
        $line = $file->current();
        if (strpos($line, '[EZ-Translate]') !== false) {
            $lines[] = trim($line);
        }
        $file->next();
    }

    if (!empty($lines)) {
        echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0;">';
        echo '<h4>Recent EZ Translate Log Entries:</h4>';
        echo '<pre style="font-size: 11px; max-height: 200px; overflow-y: auto;">';
        foreach (array_slice($lines, -10) as $line) {
            echo esc_html($line) . "\n";
        }
        echo '</pre>';
        echo '</div>';
    }
}

/**
 * Create a test post for testing purposes
 *
 * @param array $args Post arguments
 * @return int|WP_Error Post ID on success, WP_Error on failure
 * @since 1.0.0
 */
function ez_translate_create_test_post($args = array()) {
    $defaults = array(
        'post_title' => 'Test Post for EZ Translate',
        'post_content' => 'This is a test post created for EZ Translate testing purposes.',
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_author' => 1
    );

    $args = wp_parse_args($args, $defaults);
    
    return wp_insert_post($args);
}

/**
 * Clean up test posts
 *
 * @param array $post_ids Array of post IDs to delete
 * @since 1.0.0
 */
function ez_translate_cleanup_test_posts($post_ids) {
    if (!is_array($post_ids)) {
        $post_ids = array($post_ids);
    }

    foreach ($post_ids as $post_id) {
        if ($post_id && is_numeric($post_id)) {
            wp_delete_post($post_id, true);
        }
    }
}

/**
 * Setup test environment
 * Ensures all necessary components are loaded and configured
 *
 * @since 1.0.0
 */
function ez_translate_setup_test_environment() {
    // Ensure required classes are loaded
    if (!class_exists('EZTranslate\LanguageManager')) {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
    }

    if (!class_exists('EZTranslate\PostMetaManager')) {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-post-meta-manager.php';
    }

    if (!class_exists('EZTranslate\RestAPI')) {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-rest-api.php';
    }

    if (!class_exists('EZTranslate\Frontend')) {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-frontend.php';
    }

    // Ensure test languages exist
    ez_translate_ensure_test_languages();
}

/**
 * Display test result in standardized format
 *
 * @param string $test_name Name of the test
 * @param bool $passed Whether the test passed
 * @param string $message Test result message
 * @param array $details Additional details (optional)
 * @since 1.0.0
 */
function ez_translate_display_test_result($test_name, $passed, $message, $details = array()) {
    $status = $passed ? 'PASS' : 'FAIL';
    $color = $passed ? 'green' : 'red';
    
    echo '<div style="margin: 10px 0; padding: 10px; border-left: 4px solid ' . $color . '; background: #f9f9f9;">';
    echo '<strong style="color: ' . $color . ';">[' . $status . ']</strong> ';
    echo '<strong>' . esc_html($test_name) . '</strong><br>';
    echo esc_html($message);
    
    if (!empty($details)) {
        echo '<br><small style="color: #666;">';
        foreach ($details as $key => $value) {
            echo esc_html($key) . ': ' . esc_html($value) . ' | ';
        }
        echo '</small>';
    }
    echo '</div>';
}

/**
 * Get test statistics summary
 *
 * @param int $passed Number of tests passed
 * @param int $total Total number of tests
 * @return string HTML summary
 * @since 1.0.0
 */
function ez_translate_get_test_summary($passed, $total) {
    $percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
    $color = $passed === $total ? 'green' : ($passed > 0 ? 'orange' : 'red');
    
    $summary = '<div style="margin: 20px 0; padding: 15px; background: #f0f0f1; border-radius: 4px;">';
    $summary .= '<h3 style="margin: 0 0 10px 0; color: ' . $color . ';">Test Results Summary</h3>';
    $summary .= '<p style="margin: 0; font-size: 16px;">';
    $summary .= '<strong style="color: ' . $color . ';">' . $passed . '/' . $total . ' tests passed</strong>';
    $summary .= ' (' . $percentage . '%)';
    $summary .= '</p>';
    
    if ($passed !== $total) {
        $summary .= '<p style="margin: 10px 0 0 0; color: #d63638;">';
        $summary .= '<strong>⚠️ Some tests failed. Please review the results above.</strong>';
        $summary .= '</p>';
    } else {
        $summary .= '<p style="margin: 10px 0 0 0; color: #00a32a;">';
        $summary .= '<strong>✅ All tests passed successfully!</strong>';
        $summary .= '</p>';
    }
    
    $summary .= '</div>';
    
    return $summary;
}
