<?php
/**
 * Test file for Post Meta Manager
 *
 * @package EZTranslate
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Post Meta Manager functionality
 *
 * @since 1.0.0
 */
function ez_translate_test_post_meta_manager() {
    $results = array();
    $test_post_id = null;

    try {
        // Load required classes
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-logger.php';
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-post-meta-manager.php';

        // Initialize logger
        \EZTranslate\Logger::init();

        // Add a test language first
        $test_language = array(
            'code' => 'en',
            'name' => 'English',
            'slug' => 'english',
            'native_name' => 'English',
            'flag' => '🇺🇸',
            'rtl' => false,
            'enabled' => true
        );
        \EZTranslate\LanguageManager::add_language($test_language);

        // Create a test post
        $test_post_id = wp_insert_post(array(
            'post_title' => 'Test Post for EZ Translate Meta',
            'post_content' => 'This is a test post for testing multilingual metadata.',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));

        if (is_wp_error($test_post_id)) {
            throw new Exception('Failed to create test post: ' . $test_post_id->get_error_message());
        }

        // Test 1: Generate Group ID
        $group_id = \EZTranslate\PostMetaManager::generate_group_id();
        $results['generate_group_id'] = array(
            'status' => !empty($group_id) && strlen($group_id) === 19 && strpos($group_id, 'tg_') === 0,
            'message' => $group_id ? "Generated group ID: $group_id" : 'Failed to generate group ID',
            'data' => array('group_id' => $group_id)
        );

        // Test 2: Validate Group ID
        $is_valid = \EZTranslate\PostMetaManager::validate_group_id($group_id);
        $results['validate_group_id'] = array(
            'status' => $is_valid,
            'message' => $is_valid ? 'Group ID validation passed' : 'Group ID validation failed',
            'data' => array('group_id' => $group_id, 'is_valid' => $is_valid)
        );

        // Test 3: Set Post Language (must be done first for landing page validation)
        $language_result = \EZTranslate\PostMetaManager::set_post_language($test_post_id, 'en');
        $results['set_post_language'] = array(
            'status' => $language_result,
            'message' => $language_result ? 'Post language set successfully' : 'Failed to set post language',
            'data' => array('post_id' => $test_post_id, 'language' => 'en')
        );

        // Test 4: Get Post Language
        $retrieved_language = \EZTranslate\PostMetaManager::get_post_language($test_post_id);
        $results['get_post_language'] = array(
            'status' => $retrieved_language === 'en',
            'message' => $retrieved_language === 'en' ? 'Post language retrieved correctly' : 'Failed to retrieve post language',
            'data' => array('post_id' => $test_post_id, 'retrieved_language' => $retrieved_language)
        );

        // Test 5: Set Post Group
        $group_result = \EZTranslate\PostMetaManager::set_post_group($test_post_id, $group_id);
        $results['set_post_group'] = array(
            'status' => $group_result === $group_id,
            'message' => $group_result === $group_id ? 'Post group set successfully' : 'Failed to set post group',
            'data' => array('post_id' => $test_post_id, 'group_id' => $group_id, 'result' => $group_result)
        );

        // Test 6: Get Post Group
        $retrieved_group = \EZTranslate\PostMetaManager::get_post_group($test_post_id);
        $results['get_post_group'] = array(
            'status' => $retrieved_group === $group_id,
            'message' => $retrieved_group === $group_id ? 'Post group retrieved correctly' : 'Failed to retrieve post group',
            'data' => array('post_id' => $test_post_id, 'retrieved_group' => $retrieved_group)
        );

        // Test 7: Set Landing Page Status (now that language is set)
        $landing_result = \EZTranslate\PostMetaManager::set_post_landing_status($test_post_id, true);
        $results['set_landing_status'] = array(
            'status' => $landing_result,
            'message' => $landing_result ? 'Landing page status set successfully' : 'Failed to set landing page status',
            'data' => array('post_id' => $test_post_id, 'is_landing' => true)
        );

        // Test 8: Check Landing Page Status
        $is_landing = \EZTranslate\PostMetaManager::is_post_landing_page($test_post_id);
        $results['is_landing_page'] = array(
            'status' => $is_landing === true,
            'message' => $is_landing === true ? 'Landing page status retrieved correctly' : 'Failed to retrieve landing page status',
            'data' => array('post_id' => $test_post_id, 'is_landing' => $is_landing)
        );

        // Test 9: Set SEO Title
        $seo_title = 'Test SEO Title for Landing Page';
        $seo_title_result = \EZTranslate\PostMetaManager::set_post_seo_title($test_post_id, $seo_title);
        $results['set_seo_title'] = array(
            'status' => $seo_title_result,
            'message' => $seo_title_result ? 'SEO title set successfully' : 'Failed to set SEO title',
            'data' => array('post_id' => $test_post_id, 'seo_title' => $seo_title)
        );

        // Test 10: Get SEO Title
        $retrieved_seo_title = \EZTranslate\PostMetaManager::get_post_seo_title($test_post_id);
        $results['get_seo_title'] = array(
            'status' => $retrieved_seo_title === $seo_title,
            'message' => $retrieved_seo_title === $seo_title ? 'SEO title retrieved correctly' : 'Failed to retrieve SEO title',
            'data' => array('post_id' => $test_post_id, 'retrieved_seo_title' => $retrieved_seo_title)
        );

        // Test 11: Set SEO Description
        $seo_description = 'Test SEO description for the landing page with multilingual content.';
        $seo_desc_result = \EZTranslate\PostMetaManager::set_post_seo_description($test_post_id, $seo_description);
        $results['set_seo_description'] = array(
            'status' => $seo_desc_result,
            'message' => $seo_desc_result ? 'SEO description set successfully' : 'Failed to set SEO description',
            'data' => array('post_id' => $test_post_id, 'seo_description' => $seo_description)
        );

        // Test 12: Get SEO Description
        $retrieved_seo_desc = \EZTranslate\PostMetaManager::get_post_seo_description($test_post_id);
        $results['get_seo_description'] = array(
            'status' => $retrieved_seo_desc === $seo_description,
            'message' => $retrieved_seo_desc === $seo_description ? 'SEO description retrieved correctly' : 'Failed to retrieve SEO description',
            'data' => array('post_id' => $test_post_id, 'retrieved_seo_desc' => $retrieved_seo_desc)
        );

        // Test 13: Get All Post Metadata
        $all_metadata = \EZTranslate\PostMetaManager::get_post_metadata($test_post_id);
        $expected_keys = array('language', 'group', 'is_landing', 'seo_title', 'seo_description');
        $has_all_keys = count(array_intersect($expected_keys, array_keys($all_metadata))) === count($expected_keys);
        $results['get_all_metadata'] = array(
            'status' => $has_all_keys,
            'message' => $has_all_keys ? 'All metadata retrieved correctly' : 'Some metadata missing',
            'data' => array('post_id' => $test_post_id, 'metadata' => $all_metadata)
        );

        // Test 14: Get Landing Page for Language (using LanguageManager)
        $landing_page_data = \EZTranslate\LanguageManager::get_landing_page_for_language('en');
        $landing_page_id = $landing_page_data ? $landing_page_data['id'] : null;
        $results['get_landing_for_language'] = array(
            'status' => $landing_page_id === $test_post_id,
            'message' => $landing_page_id === $test_post_id ? 'Landing page found for language' : 'Failed to find landing page for language',
            'data' => array('language' => 'en', 'landing_page_id' => $landing_page_id, 'expected' => $test_post_id)
        );

        // Test 15: Get Posts in Group
        $posts_in_group = \EZTranslate\PostMetaManager::get_posts_in_group($group_id);
        $results['get_posts_in_group'] = array(
            'status' => in_array($test_post_id, $posts_in_group),
            'message' => in_array($test_post_id, $posts_in_group) ? 'Post found in translation group' : 'Post not found in translation group',
            'data' => array('group_id' => $group_id, 'posts_in_group' => $posts_in_group, 'test_post_id' => $test_post_id)
        );

        // Test 16: Get Posts by Language
        $posts_by_language = \EZTranslate\PostMetaManager::get_posts_by_language('en');
        $results['get_posts_by_language'] = array(
            'status' => in_array($test_post_id, $posts_by_language),
            'message' => in_array($test_post_id, $posts_by_language) ? 'Post found by language' : 'Post not found by language',
            'data' => array('language' => 'en', 'posts_by_language' => $posts_by_language, 'test_post_id' => $test_post_id)
        );

    } catch (Exception $e) {
        $results['error'] = array(
            'status' => false,
            'message' => 'Test failed with exception: ' . $e->getMessage(),
            'data' => array('exception' => $e->getMessage())
        );
    }

    // Clean up test post
    if ($test_post_id && !is_wp_error($test_post_id)) {
        wp_delete_post($test_post_id, true);
    }

    // Clean up test language
    \EZTranslate\LanguageManager::delete_language('en');

    return $results;
}

/**
 * Display test results for Post Meta Manager
 *
 * @since 1.0.0
 */
function ez_translate_display_post_meta_tests() {
    $results = ez_translate_test_post_meta_manager();
    
    echo '<div class="ez-translate-tests">';
    echo '<h3>🧪 Post Meta Manager Tests</h3>';
    
    $passed = 0;
    $total = count($results);
    
    foreach ($results as $test_name => $result) {
        $status_icon = $result['status'] ? '✅' : '❌';
        $status_class = $result['status'] ? 'success' : 'error';
        
        if ($result['status']) {
            $passed++;
        }
        
        echo '<div class="test-result ' . $status_class . '">';
        echo '<strong>' . $status_icon . ' ' . ucwords(str_replace('_', ' ', $test_name)) . '</strong><br>';
        echo $result['message'];
        
        if (!empty($result['data'])) {
            echo '<details style="margin-top: 5px;">';
            echo '<summary>Test Data</summary>';
            echo '<pre>' . print_r($result['data'], true) . '</pre>';
            echo '</details>';
        }
        
        echo '</div><br>';
    }
    
    echo '<div class="test-summary">';
    echo '<strong>Summary: ' . $passed . '/' . $total . ' tests passed</strong>';
    echo '</div>';
    echo '</div>';
    
    echo '<style>
        .ez-translate-tests { margin: 20px 0; }
        .test-result.success { color: #155724; background-color: #d4edda; padding: 10px; border-radius: 4px; }
        .test-result.error { color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 4px; }
        .test-summary { margin-top: 20px; padding: 15px; background-color: #e9ecef; border-radius: 4px; }
        details { margin-top: 5px; }
        pre { background-color: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>';
}
