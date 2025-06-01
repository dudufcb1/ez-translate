<?php
/**
 * Test file for EZ Translate Language Manager
 *
 * @package EZTranslate
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple test class for Language Manager functionality
 *
 * @since 1.0.0
 */
class EZTranslateLanguageManagerTest {

    /**
     * Run all tests
     *
     * @since 1.0.0
     */
    public static function run_tests() {
        echo "<h2>EZ Translate Language Manager Tests</h2>\n";
        
        // Load the language manager
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        
        $tests = array(
            'test_add_language',
            'test_get_languages',
            'test_get_language_by_code',
            'test_update_language',
            'test_delete_language',
            'test_validation',
            'test_sanitization',
            'test_duplicate_prevention',
            'test_common_languages_selector'
        );
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            echo "<h3>Running: {$test}</h3>\n";
            try {
                $result = self::$test();
                if ($result) {
                    echo "<p style='color: green;'>✓ PASSED</p>\n";
                    $passed++;
                } else {
                    echo "<p style='color: red;'>✗ FAILED</p>\n";
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>✗ ERROR: " . esc_html($e->getMessage()) . "</p>\n";
            }
            echo "<hr>\n";
        }
        
        echo "<h3>Test Results: {$passed}/{$total} passed</h3>\n";
        
        // Clean up test data
        self::cleanup_test_data();
    }

    /**
     * Test adding a language
     *
     * @return bool
     * @since 1.0.0
     */
    private static function test_add_language() {
        $test_language = array(
            'code' => 'test',
            'name' => 'Test Language',
            'slug' => 'test-language',
            'native_name' => 'Test Native',
            'flag' => '🧪',
            'rtl' => false,
            'enabled' => true
        );
        
        $result = \EZTranslate\LanguageManager::add_language($test_language);
        
        if (is_wp_error($result)) {
            echo "Error adding language: " . $result->get_error_message();
            return false;
        }
        
        // Verify the language was added
        $languages = \EZTranslate\LanguageManager::get_languages(false);
        foreach ($languages as $language) {
            if ($language['code'] === 'test') {
                echo "Language added successfully: " . $language['name'];
                return true;
            }
        }
        
        echo "Language was not found after adding";
        return false;
    }

    /**
     * Test getting all languages
     *
     * @return bool
     * @since 1.0.0
     */
    private static function test_get_languages() {
        $languages = \EZTranslate\LanguageManager::get_languages(false);
        
        if (!is_array($languages)) {
            echo "get_languages() did not return an array";
            return false;
        }
        
        echo "Retrieved " . count($languages) . " languages";
        return true;
    }

    /**
     * Test getting a specific language by code
     *
     * @return bool
     * @since 1.0.0
     */
    private static function test_get_language_by_code() {
        $language = \EZTranslate\LanguageManager::get_language('test');
        
        if ($language === null) {
            echo "Language 'test' not found";
            return false;
        }
        
        if ($language['code'] !== 'test' || $language['name'] !== 'Test Language') {
            echo "Language data does not match expected values";
            return false;
        }
        
        echo "Language retrieved successfully: " . $language['name'];
        return true;
    }

    /**
     * Test updating a language
     *
     * @return bool
     * @since 1.0.0
     */
    private static function test_update_language() {
        $updated_data = array(
            'code' => 'test',
            'name' => 'Updated Test Language',
            'slug' => 'updated-test-language',
            'native_name' => 'Updated Native',
            'flag' => '🔄',
            'rtl' => true,
            'enabled' => false
        );
        
        $result = \EZTranslate\LanguageManager::update_language('test', $updated_data);
        
        if (is_wp_error($result)) {
            echo "Error updating language: " . $result->get_error_message();
            return false;
        }
        
        // Verify the update
        $language = \EZTranslate\LanguageManager::get_language('test');
        if ($language['name'] !== 'Updated Test Language' || $language['rtl'] !== true) {
            echo "Language was not updated correctly";
            return false;
        }
        
        echo "Language updated successfully";
        return true;
    }

    /**
     * Test deleting a language
     *
     * @return bool
     * @since 1.0.0
     */
    private static function test_delete_language() {
        $result = \EZTranslate\LanguageManager::delete_language('test');
        
        if (is_wp_error($result)) {
            echo "Error deleting language: " . $result->get_error_message();
            return false;
        }
        
        // Verify the deletion
        $language = \EZTranslate\LanguageManager::get_language('test');
        if ($language !== null) {
            echo "Language was not deleted";
            return false;
        }
        
        echo "Language deleted successfully";
        return true;
    }

    /**
     * Test validation
     *
     * @return bool
     * @since 1.0.0
     */
    private static function test_validation() {
        // Test invalid data
        $invalid_language = array(
            'code' => '', // Empty code
            'name' => '', // Empty name
            'slug' => 'Invalid Slug!', // Invalid slug format
        );
        
        $result = \EZTranslate\LanguageManager::validate_language_data($invalid_language);
        
        if (!is_wp_error($result)) {
            echo "Validation should have failed for invalid data";
            return false;
        }
        
        $errors = $result->get_error_messages();
        if (count($errors) < 3) {
            echo "Expected at least 3 validation errors, got " . count($errors);
            return false;
        }
        
        echo "Validation correctly rejected invalid data with " . count($errors) . " errors";
        return true;
    }

    /**
     * Test sanitization
     *
     * @return bool
     * @since 1.0.0
     */
    private static function test_sanitization() {
        $dirty_data = array(
            'code' => '  EN  ',
            'name' => '<script>alert("test")</script>English',
            'slug' => 'English Language!@#',
            'native_name' => 'English<br>',
            'flag' => '🇺🇸🇺🇸',
            'rtl' => 'false',
            'enabled' => '1'
        );
        
        $clean_data = \EZTranslate\LanguageManager::sanitize_language_data($dirty_data);
        
        // Check that dangerous content was removed
        if (strpos($clean_data['name'], '<script>') !== false) {
            echo "Script tags were not removed from name";
            return false;
        }
        
        // Check that slug was properly formatted
        if ($clean_data['slug'] !== 'english-language') {
            echo "Slug was not properly sanitized: " . $clean_data['slug'];
            return false;
        }
        
        // Check boolean conversion
        if ($clean_data['rtl'] !== false || $clean_data['enabled'] !== true) {
            echo "Boolean fields were not properly converted";
            return false;
        }
        
        echo "Data sanitization working correctly";
        return true;
    }

    /**
     * Test duplicate prevention
     *
     * @return bool
     * @since 1.0.0
     */
    private static function test_duplicate_prevention() {
        // Add a test language
        $language1 = array(
            'code' => 'dup',
            'name' => 'Duplicate Test',
            'slug' => 'duplicate-test',
            'enabled' => true
        );
        
        $result1 = \EZTranslate\LanguageManager::add_language($language1);
        if (is_wp_error($result1)) {
            echo "Failed to add first language: " . $result1->get_error_message();
            return false;
        }
        
        // Try to add a duplicate
        $language2 = array(
            'code' => 'dup', // Same code
            'name' => 'Another Duplicate',
            'slug' => 'another-duplicate',
            'enabled' => true
        );
        
        $result2 = \EZTranslate\LanguageManager::add_language($language2);
        if (!is_wp_error($result2)) {
            echo "Duplicate code was allowed when it should have been rejected";
            return false;
        }
        
        // Clean up
        \EZTranslate\LanguageManager::delete_language('dup');
        
        echo "Duplicate prevention working correctly";
        return true;
    }

    /**
     * Test common languages selector functionality
     *
     * @return bool
     * @since 1.0.0
     */
    private static function test_common_languages_selector() {
        // Load the admin class to test the language options method
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-admin.php';

        // Create a reflection class to access the private method
        $adminClass = new \EZTranslate\Admin();
        $reflection = new ReflectionClass($adminClass);
        $method = $reflection->getMethod('get_language_options');
        $method->setAccessible(true);

        // Get the language options HTML
        $options_html = $method->invoke($adminClass);

        if (empty($options_html)) {
            echo "Language options HTML is empty";
            return false;
        }

        // Check that it contains some expected languages
        $expected_languages = array('en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'zh', 'ja', 'ar');
        $found_languages = 0;

        foreach ($expected_languages as $lang_code) {
            if (strpos($options_html, 'value="' . $lang_code . '"') !== false) {
                $found_languages++;
            }
        }

        if ($found_languages < 5) {
            echo "Expected to find at least 5 common languages, found {$found_languages}";
            return false;
        }

        // Check that options have proper data attributes
        if (strpos($options_html, 'data-name=') === false ||
            strpos($options_html, 'data-native=') === false ||
            strpos($options_html, 'data-flag=') === false) {
            echo "Language options missing required data attributes";
            return false;
        }

        // Test that existing languages are excluded
        // First add a test language
        $test_language = array(
            'code' => 'en',
            'name' => 'English Test',
            'slug' => 'english-test',
            'enabled' => true
        );

        \EZTranslate\LanguageManager::add_language($test_language);

        // Get options again - English should now be excluded
        $options_html_after = $method->invoke($adminClass);

        if (strpos($options_html_after, 'value="en"') !== false) {
            echo "Existing language 'en' was not properly excluded from options";
            return false;
        }

        // Clean up
        \EZTranslate\LanguageManager::delete_language('en');

        echo "Common languages selector working correctly with {$found_languages} languages found";
        return true;
    }

    /**
     * Clean up test data
     *
     * @since 1.0.0
     */
    private static function cleanup_test_data() {
        // Remove any test languages that might still exist
        $test_codes = array('test', 'dup', 'en');
        
        foreach ($test_codes as $code) {
            \EZTranslate\LanguageManager::delete_language($code);
        }
        
        // Clear cache
        \EZTranslate\LanguageManager::clear_cache();
        
        echo "<p><em>Test data cleaned up</em></p>\n";
    }
}

// Auto-run tests if accessed directly with proper parameter
if (isset($_GET['run_ez_translate_tests']) && current_user_can('manage_options')) {
    EZTranslateLanguageManagerTest::run_tests();
}
