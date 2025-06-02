<?php
/**
 * REST API Controller for EZ Translate
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use EZTranslate\Logger;
use EZTranslate\LanguageManager;
use EZTranslate\PostMetaManager;
use Exception;

/**
 * REST API Controller class
 *
 * @since 1.0.0
 */
class RestAPI {

    /**
     * API namespace
     *
     * @var string
     * @since 1.0.0
     */
    const NAMESPACE = 'ez-translate/v1';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        Logger::info('REST API controller initialized');
    }

    /**
     * Register REST API routes
     *
     * @since 1.0.0
     */
    public function register_routes() {
        // Public languages endpoint for reading (Gutenberg)
        register_rest_route(self::NAMESPACE, '/languages', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_languages'),
            'permission_callback' => '__return_true', // Public read access
        ));

        // Admin languages endpoints for management
        register_rest_route(self::NAMESPACE, '/admin/languages', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_languages'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route(self::NAMESPACE, '/languages', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_language'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => $this->get_language_schema(),
        ));

        register_rest_route(self::NAMESPACE, '/languages/(?P<code>[a-zA-Z0-9_-]+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_language'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => $this->get_language_schema(),
        ));

        register_rest_route(self::NAMESPACE, '/languages/(?P<code>[a-zA-Z0-9_-]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_language'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // Post metadata endpoints
        register_rest_route(self::NAMESPACE, '/post-meta/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post_meta'),
            'permission_callback' => array($this, 'check_post_permissions'),
        ));

        register_rest_route(self::NAMESPACE, '/post-meta/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_post_meta'),
            'permission_callback' => array($this, 'check_post_permissions'),
            'args' => $this->get_post_meta_schema(),
        ));

        // Translation creation endpoint
        register_rest_route(self::NAMESPACE, '/create-translation/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_translation'),
            'permission_callback' => array($this, 'check_post_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
                'target_language' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param, $request, $key) {
                        return is_string($param) && !empty($param);
                    },
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        Logger::info('REST API routes registered');
    }

    /**
     * Check permissions for language operations
     *
     * @param WP_REST_Request $request Request object
     * @return bool
     * @since 1.0.0
     */
    public function check_permissions($request) {
        $has_permission = current_user_can('manage_options');
        
        if (!$has_permission) {
            Logger::warning('REST API access denied', array(
                'user_id' => get_current_user_id(),
                'endpoint' => $request->get_route()
            ));
        }

        return $has_permission;
    }

    /**
     * Check permissions for post operations
     *
     * @param WP_REST_Request $request Request object
     * @return bool
     * @since 1.0.0
     */
    public function check_post_permissions($request) {
        $post_id = $request->get_param('id');
        $post = get_post($post_id);

        if (!$post) {
            return false;
        }

        $has_permission = current_user_can('edit_post', $post_id);
        
        if (!$has_permission) {
            Logger::warning('REST API post access denied', array(
                'user_id' => get_current_user_id(),
                'post_id' => $post_id,
                'endpoint' => $request->get_route()
            ));
        }

        return $has_permission;
    }

    /**
     * Get all languages
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function get_languages($request) {
        try {
            // Check if this is an admin request (has manage_options permission)
            $is_admin_request = current_user_can('manage_options');

            if ($is_admin_request) {
                // Admin can see all languages
                $languages = LanguageManager::get_languages();
            } else {
                // Public access only sees enabled languages
                $languages = LanguageManager::get_enabled_languages();
            }

            Logger::debug('REST API: Languages retrieved', array(
                'count' => count($languages),
                'admin_request' => $is_admin_request
            ));

            return rest_ensure_response($languages);
        } catch (Exception $e) {
            Logger::error('REST API: Failed to get languages', array(
                'error' => $e->getMessage()
            ));

            return new \WP_Error('get_languages_failed', 'Failed to retrieve languages', array('status' => 500));
        }
    }

    /**
     * Create a new language
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function create_language($request) {
        try {
            $language_data = array(
                'code' => sanitize_text_field($request->get_param('code')),
                'name' => sanitize_text_field($request->get_param('name')),
                'slug' => sanitize_title($request->get_param('slug')),
                'native_name' => sanitize_text_field($request->get_param('native_name')),
                'flag' => sanitize_text_field($request->get_param('flag')),
                'rtl' => (bool) $request->get_param('rtl'),
                'enabled' => (bool) $request->get_param('enabled'),
            );

            $result = LanguageManager::add_language($language_data);

            if (is_wp_error($result)) {
                Logger::warning('REST API: Failed to create language', array(
                    'error' => $result->get_error_message(),
                    'data' => $language_data
                ));

                return $result;
            }

            Logger::info('REST API: Language created', array(
                'code' => $language_data['code']
            ));

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Language created successfully',
                'data' => $language_data
            ));
        } catch (Exception $e) {
            Logger::error('REST API: Exception creating language', array(
                'error' => $e->getMessage()
            ));

            return new \WP_Error('create_language_failed', 'Failed to create language', array('status' => 500));
        }
    }

    /**
     * Update an existing language
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function update_language($request) {
        try {
            $code = sanitize_text_field($request->get_param('code'));
            
            $language_data = array(
                'name' => sanitize_text_field($request->get_param('name')),
                'slug' => sanitize_title($request->get_param('slug')),
                'native_name' => sanitize_text_field($request->get_param('native_name')),
                'flag' => sanitize_text_field($request->get_param('flag')),
                'rtl' => (bool) $request->get_param('rtl'),
                'enabled' => (bool) $request->get_param('enabled'),
            );

            $result = LanguageManager::update_language($code, $language_data);

            if (is_wp_error($result)) {
                Logger::warning('REST API: Failed to update language', array(
                    'error' => $result->get_error_message(),
                    'code' => $code
                ));

                return $result;
            }

            Logger::info('REST API: Language updated', array(
                'code' => $code
            ));

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Language updated successfully',
                'data' => $language_data
            ));
        } catch (Exception $e) {
            Logger::error('REST API: Exception updating language', array(
                'error' => $e->getMessage()
            ));

            return new \WP_Error('update_language_failed', 'Failed to update language', array('status' => 500));
        }
    }

    /**
     * Delete a language
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function delete_language($request) {
        try {
            $code = sanitize_text_field($request->get_param('code'));
            
            $result = LanguageManager::delete_language($code);

            if (is_wp_error($result)) {
                Logger::warning('REST API: Failed to delete language', array(
                    'error' => $result->get_error_message(),
                    'code' => $code
                ));

                return $result;
            }

            Logger::info('REST API: Language deleted', array(
                'code' => $code
            ));

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Language deleted successfully'
            ));
        } catch (Exception $e) {
            Logger::error('REST API: Exception deleting language', array(
                'error' => $e->getMessage()
            ));

            return new \WP_Error('delete_language_failed', 'Failed to delete language', array('status' => 500));
        }
    }

    /**
     * Get post metadata
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function get_post_meta($request) {
        try {
            $post_id = (int) $request->get_param('id');

            $metadata = PostMetaManager::get_post_metadata($post_id);

            Logger::debug('REST API: Post metadata retrieved', array(
                'post_id' => $post_id,
                'metadata' => $metadata
            ));

            return rest_ensure_response($metadata);
        } catch (Exception $e) {
            Logger::error('REST API: Failed to get post metadata', array(
                'error' => $e->getMessage(),
                'post_id' => $request->get_param('id')
            ));

            return new \WP_Error('get_post_meta_failed', 'Failed to retrieve post metadata', array('status' => 500));
        }
    }

    /**
     * Update post metadata
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function update_post_meta($request) {
        try {
            $post_id = (int) $request->get_param('id');

            $metadata = array();

            // Get and sanitize metadata fields
            if ($request->has_param('language')) {
                $metadata['language'] = sanitize_text_field($request->get_param('language'));
            }

            if ($request->has_param('group')) {
                $metadata['group'] = sanitize_text_field($request->get_param('group'));
            }

            if ($request->has_param('is_landing')) {
                $is_landing = (bool) $request->get_param('is_landing');

                // Special handling for landing page validation
                if ($is_landing) {
                    $language_code = get_post_meta($post_id, '_ez_translate_language', true);
                    if (!empty($language_code)) {
                        $existing_landing = PostMetaManager::get_landing_page_for_language($language_code);
                        if ($existing_landing && $existing_landing != $post_id) {
                            Logger::warning('REST API: Landing page already exists for language', array(
                                'post_id' => $post_id,
                                'language' => $language_code,
                                'existing_landing' => $existing_landing
                            ));

                            return new \WP_Error('landing_page_exists', 'Another page is already set as landing page for this language', array('status' => 409));
                        }
                    }
                }

                $metadata['is_landing'] = $is_landing;
            }

            if ($request->has_param('seo_title')) {
                $metadata['seo_title'] = sanitize_text_field($request->get_param('seo_title'));
            }

            if ($request->has_param('seo_description')) {
                $metadata['seo_description'] = sanitize_textarea_field($request->get_param('seo_description'));
            }

            $result = PostMetaManager::set_post_metadata($post_id, $metadata);

            if (!$result) {
                Logger::warning('REST API: Failed to update post metadata', array(
                    'post_id' => $post_id,
                    'metadata' => $metadata
                ));

                return new \WP_Error('update_post_meta_failed', 'Failed to update post metadata', array('status' => 500));
            }

            Logger::info('REST API: Post metadata updated', array(
                'post_id' => $post_id,
                'metadata' => $metadata
            ));

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Post metadata updated successfully',
                'data' => $metadata
            ));
        } catch (Exception $e) {
            Logger::error('REST API: Exception updating post metadata', array(
                'error' => $e->getMessage(),
                'post_id' => $request->get_param('id')
            ));

            return new \WP_Error('update_post_meta_failed', 'Failed to update post metadata', array('status' => 500));
        }
    }

    /**
     * Create translation page
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function create_translation($request) {
        try {
            $source_post_id = (int) $request->get_param('id');
            $target_language = sanitize_text_field($request->get_param('target_language'));

            // Get the source post
            $source_post = get_post($source_post_id);
            if (!$source_post) {
                return new \WP_Error('source_post_not_found', 'Source post not found', array('status' => 404));
            }

            // Validate target language exists
            $language = LanguageManager::get_language($target_language);
            if (!$language) {
                return new \WP_Error('invalid_target_language', 'Target language not found', array('status' => 400));
            }

            // Get source post metadata
            $source_metadata = PostMetaManager::get_post_metadata($source_post_id);
            $source_language = $source_metadata['language'] ?? '';

            // Check if translation already exists
            if ($source_metadata['group']) {
                $existing_translations = PostMetaManager::get_posts_in_group($source_metadata['group']);
                foreach ($existing_translations as $translation) {
                    $translation_meta = PostMetaManager::get_post_metadata($translation->ID);
                    if ($translation_meta['language'] === $target_language) {
                        return new \WP_Error('translation_exists', 'Translation already exists for this language', array(
                            'status' => 409,
                            'existing_post_id' => $translation->ID
                        ));
                    }
                }
            }

            // Create the translation post
            $translation_data = array(
                'post_title' => $source_post->post_title . ' (' . $language['name'] . ')',
                'post_content' => $source_post->post_content,
                'post_excerpt' => $source_post->post_excerpt,
                'post_status' => 'draft', // Always create as draft
                'post_type' => $source_post->post_type,
                'post_author' => get_current_user_id(),
                'post_parent' => $source_post->post_parent,
                'menu_order' => $source_post->menu_order,
            );

            $translation_id = wp_insert_post($translation_data);

            if (is_wp_error($translation_id)) {
                Logger::error('REST API: Failed to create translation post', array(
                    'error' => $translation_id->get_error_message(),
                    'source_post_id' => $source_post_id,
                    'target_language' => $target_language
                ));
                return $translation_id;
            }

            // Set up translation group
            $group_id = $source_metadata['group'];
            if (!$group_id) {
                // Create new group and assign to source post
                $group_id = PostMetaManager::generate_group_id();
                PostMetaManager::set_post_group($source_post_id, $group_id);
            }

            // Set metadata for translation
            PostMetaManager::set_post_language($translation_id, $target_language);
            PostMetaManager::set_post_group($translation_id, $group_id);

            // Copy featured image if exists
            $featured_image_id = get_post_thumbnail_id($source_post_id);
            if ($featured_image_id) {
                set_post_thumbnail($translation_id, $featured_image_id);
            }

            // Copy custom fields (excluding our own meta fields)
            $custom_fields = get_post_meta($source_post_id);
            foreach ($custom_fields as $key => $values) {
                if (strpos($key, '_ez_translate_') !== 0) {
                    foreach ($values as $value) {
                        add_post_meta($translation_id, $key, maybe_unserialize($value));
                    }
                }
            }

            Logger::info('REST API: Translation created successfully', array(
                'source_post_id' => $source_post_id,
                'translation_id' => $translation_id,
                'target_language' => $target_language,
                'group_id' => $group_id
            ));

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Translation created successfully',
                'data' => array(
                    'translation_id' => $translation_id,
                    'edit_url' => admin_url('post.php?post=' . $translation_id . '&action=edit'),
                    'source_post_id' => $source_post_id,
                    'target_language' => $target_language,
                    'group_id' => $group_id
                )
            ));

        } catch (Exception $e) {
            Logger::error('REST API: Exception creating translation', array(
                'error' => $e->getMessage(),
                'source_post_id' => $request->get_param('id'),
                'target_language' => $request->get_param('target_language')
            ));

            return new \WP_Error('create_translation_failed', 'Failed to create translation', array('status' => 500));
        }
    }

    /**
     * Get language schema for validation
     *
     * @return array
     * @since 1.0.0
     */
    private function get_language_schema() {
        return array(
            'code' => array(
                'required' => true,
                'type' => 'string',
                'pattern' => '^[a-zA-Z0-9_-]{2,5}$',
                'description' => 'Language code (2-5 characters)',
            ),
            'name' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'Language name',
            ),
            'slug' => array(
                'required' => true,
                'type' => 'string',
                'description' => 'URL-friendly slug',
            ),
            'native_name' => array(
                'required' => false,
                'type' => 'string',
                'description' => 'Native language name',
            ),
            'flag' => array(
                'required' => false,
                'type' => 'string',
                'description' => 'Flag emoji',
            ),
            'rtl' => array(
                'required' => false,
                'type' => 'boolean',
                'default' => false,
                'description' => 'Right-to-left text direction',
            ),
            'enabled' => array(
                'required' => false,
                'type' => 'boolean',
                'default' => true,
                'description' => 'Language enabled status',
            ),
        );
    }

    /**
     * Get post metadata schema for validation
     *
     * @return array
     * @since 1.0.0
     */
    private function get_post_meta_schema() {
        return array(
            'language' => array(
                'required' => false,
                'type' => 'string',
                'description' => 'Language code for the post',
            ),
            'group' => array(
                'required' => false,
                'type' => 'string',
                'pattern' => '^tg_[a-zA-Z0-9]{16}$',
                'description' => 'Translation group ID',
            ),
            'is_landing' => array(
                'required' => false,
                'type' => 'boolean',
                'description' => 'Landing page status',
            ),
            'seo_title' => array(
                'required' => false,
                'type' => 'string',
                'description' => 'SEO title for landing pages',
            ),
            'seo_description' => array(
                'required' => false,
                'type' => 'string',
                'description' => 'SEO description for landing pages',
            ),
        );
    }
}
