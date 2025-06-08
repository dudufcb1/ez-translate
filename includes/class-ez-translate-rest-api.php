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
use EZTranslate\Providers\GeminiProvider;
use EZTranslate\Helpers\ConstructPrompt;
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

        // Translation verification endpoint
        register_rest_route(self::NAMESPACE, '/verify-translations/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'verify_existing_translations'),
            'permission_callback' => array($this, 'check_post_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));

        // SEO AI endpoints
        register_rest_route(self::NAMESPACE, '/generate-seo', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_seo_fields'),
            'permission_callback' => array($this, 'check_post_permissions'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
                'content' => array(
                    'required' => false,
                    'sanitize_callback' => 'wp_kses_post'
                ),
                'title' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        register_rest_route(self::NAMESPACE, '/generate-shorter-seo', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_shorter_seo'),
            'permission_callback' => array($this, 'check_post_permissions'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
                'content' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'type' => array(
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return in_array($param, array('title', 'description'));
                    }
                ),
                'max_length' => array(
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param) && $param > 0;
                    }
                )
            )
        ));

        register_rest_route(self::NAMESPACE, '/generate-alternative-title', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_alternative_title'),
            'permission_callback' => array($this, 'check_post_permissions'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
                'original_title' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'similar_titles' => array(
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_array($param);
                    }
                ),
                'content' => array(
                    'required' => false,
                    'sanitize_callback' => 'wp_kses_post'
                )
            )
        ));

        register_rest_route(self::NAMESPACE, '/check-title-similarity', array(
            'methods' => 'POST',
            'callback' => array($this, 'check_title_similarity'),
            'permission_callback' => array($this, 'check_post_permissions'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
                'title' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'threshold' => array(
                    'required' => false,
                    'default' => 0.85,
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param) && $param >= 0 && $param <= 1;
                    }
                )
            )
        ));

        // Language detector endpoint - returns all data needed for decision making
        register_rest_route(self::NAMESPACE, '/language-detector', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_language_detector_data'),
            'permission_callback' => '__return_true', // Public access
            'args' => array(
                'post_id' => array(
                    'required' => false,
                    'validate_callback' => function($param, $request, $key) {
                        return empty($param) || is_numeric($param);
                    }
                )
            )
        ));

        // API status endpoint - check if AI translation is available
        register_rest_route(self::NAMESPACE, '/api-status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_api_status'),
            'permission_callback' => '__return_true', // Public access
        ));

        // Multiple translations endpoint
        register_rest_route(self::NAMESPACE, '/create-multiple-translations/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_multiple_translations'),
            'permission_callback' => array($this, 'check_post_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
                'target_languages' => array(
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_array($param) && !empty($param);
                    }
                )
            )
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
        // Try to get post_id first (for SEO endpoints), then fallback to id (for other endpoints)
        $post_id = $request->get_param('post_id') ?: $request->get_param('id');

        if (!$post_id) {
            Logger::warning('REST API: No post ID provided', array(
                'user_id' => get_current_user_id(),
                'endpoint' => $request->get_route(),
                'params' => $request->get_params()
            ));
            return false;
        }

        $post = get_post($post_id);

        if (!$post) {
            Logger::warning('REST API: Post not found', array(
                'user_id' => get_current_user_id(),
                'post_id' => $post_id,
                'endpoint' => $request->get_route()
            ));
            return false;
        }

        $has_permission = current_user_can('edit_post', $post_id);

        if (!$has_permission) {
            Logger::warning('REST API post access denied', array(
                'user_id' => get_current_user_id(),
                'post_id' => $post_id,
                'endpoint' => $request->get_route(),
                'user_capabilities' => wp_get_current_user()->allcaps
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

            // Landing page functionality removed - legacy parameter ignored
            if ($request->has_param('is_landing')) {
                // Legacy parameter - no longer processed
            }

            if ($request->has_param('seo_title')) {
                $metadata['seo_title'] = sanitize_text_field($request->get_param('seo_title'));
            }

            if ($request->has_param('seo_description')) {
                $metadata['seo_description'] = sanitize_textarea_field($request->get_param('seo_description'));
            }

            if ($request->has_param('og_title')) {
                $metadata['og_title'] = sanitize_text_field($request->get_param('og_title'));
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

            // Check if translation already exists (include drafts to prevent duplicates)
            if (!empty($source_metadata['group'])) {
                $existing_translations = PostMetaManager::get_posts_in_group($source_metadata['group'], array('publish', 'draft', 'pending', 'private'));
                foreach ($existing_translations as $translation_id) {
                    $translation_meta = PostMetaManager::get_post_metadata($translation_id);
                    if (isset($translation_meta['language']) && $translation_meta['language'] === $target_language) {
                        return new \WP_Error('translation_exists', 'Translation already exists for this language', array(
                            'status' => 409,
                            'existing_post_id' => $translation_id
                        ));
                    }
                }
            }

            // Get landing page for target language to set as parent
            $landing_page_data = LanguageManager::get_landing_page_for_language($target_language);
            $parent_id = 0; // Default to no parent

            if ($landing_page_data && !empty($landing_page_data['post_id'])) {
                $parent_id = $landing_page_data['post_id'];
                Logger::info('REST API: Setting landing page as parent for translation', array(
                    'target_language' => $target_language,
                    'landing_page_id' => $parent_id,
                    'landing_page_title' => $landing_page_data['title']
                ));
            } else {
                Logger::warning('REST API: No landing page found for target language, creating translation without parent', array(
                    'target_language' => $target_language,
                    'source_post_id' => $source_post_id
                ));
            }

            // Prepare translation content
            $translated_title = $source_post->post_title . ' (' . $language['name'] . ')';
            $translated_content = $source_post->post_content;
            $translation_method = 'copy'; // Default method

            // Check if AI translation is enabled and available
            if (LanguageManager::is_api_enabled()) {
                try {
                    Logger::info('REST API: Attempting AI translation', array(
                        'source_post_id' => $source_post_id,
                        'target_language' => $target_language,
                        'source_title' => $source_post->post_title
                    ));

                    // Create prompt for translation
                    $prompt = new ConstructPrompt(
                        $source_post->post_title,
                        $source_post->post_content,
                        $language['name'] // Use full language name for better context
                    );

                    // Use Gemini provider for translation
                    $gemini_provider = new GeminiProvider();
                    $translation_result = $gemini_provider->generarTexto($prompt);

                    if (isset($translation_result['title']) && isset($translation_result['content'])) {
                        $translated_title = $translation_result['title'];
                        $translated_content = $translation_result['content'];
                        $translation_method = 'ai';

                        Logger::info('REST API: AI translation successful', array(
                            'source_post_id' => $source_post_id,
                            'target_language' => $target_language,
                            'translated_title' => $translated_title
                        ));
                    } else {
                        Logger::warning('REST API: AI translation returned incomplete data, falling back to copy', array(
                            'source_post_id' => $source_post_id,
                            'target_language' => $target_language,
                            'translation_result' => $translation_result
                        ));
                    }

                } catch (Exception $translation_error) {
                    Logger::warning('REST API: AI translation failed, falling back to copy', array(
                        'source_post_id' => $source_post_id,
                        'target_language' => $target_language,
                        'error' => $translation_error->getMessage()
                    ));
                }
            } else {
                Logger::info('REST API: AI translation not enabled, using copy method', array(
                    'source_post_id' => $source_post_id,
                    'target_language' => $target_language
                ));
            }

            // Create the translation post
            $translation_data = array(
                'post_title' => $translated_title,
                'post_content' => $translated_content,
                'post_excerpt' => $source_post->post_excerpt,
                'post_status' => 'draft', // Always create as draft
                'post_type' => $source_post->post_type,
                'post_author' => get_current_user_id(),
                'post_parent' => $parent_id, // Set landing page as parent for hierarchical URLs
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
            $group_id = $source_metadata['group'] ?? '';
            if (!$group_id) {
                // Create new group and assign to source post
                $group_id = PostMetaManager::generate_group_id();
                PostMetaManager::set_post_group($source_post_id, $group_id);

                // IMPORTANT: Set the source language for the original post
                // If no source language is set, detect it from WordPress default or assume 'es'
                // MODIFICACIÓN: Asignar siempre el idioma si está vacío o no existe
                if (empty($source_language)) {
                    $wp_language = substr(get_locale(), 0, 2); // Convert en_US to en
                    $source_language = ($wp_language === 'en') ? 'es' : $wp_language; // Default to Spanish if WordPress is English
                }
                // Forzar asignación aunque el meta exista pero esté vacío
                PostMetaManager::set_post_language($source_post_id, $source_language);

                Logger::info('REST API: Original post added to translation group', array(
                    'source_post_id' => $source_post_id,
                    'source_language' => $source_language,
                    'group_id' => $group_id
                ));
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

            // Prepare response data
            $response_data = array(
                'translation_id' => $translation_id,
                'edit_url' => admin_url('post.php?post=' . $translation_id . '&action=edit'),
                'source_post_id' => $source_post_id,
                'target_language' => $target_language,
                'group_id' => $group_id,
                'parent_page_id' => $parent_id,
                'translation_method' => $translation_method,
                'translated_title' => $translated_title
            );

            // Add landing page info if available
            if ($landing_page_data) {
                $response_data['landing_page'] = array(
                    'id' => $landing_page_data['post_id'],
                    'title' => $landing_page_data['title'],
                    'slug' => $landing_page_data['slug'],
                    'url' => $landing_page_data['view_url']
                );
            }

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Translation created successfully',
                'data' => $response_data
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
     * Verify existing translations for a post
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function verify_existing_translations($request) {
        try {
            $post_id = (int) $request->get_param('id');

            // Get the source post
            $source_post = get_post($post_id);
            if (!$source_post) {
                return new \WP_Error('source_post_not_found', 'Source post not found', array('status' => 404));
            }

            // Get all available languages
            $all_languages = LanguageManager::get_enabled_languages();

            // Get current post metadata (or detect automatically)
            $source_metadata = PostMetaManager::get_post_metadata($post_id);
            $source_language = $source_metadata['language'] ?? '';

            // If no language assigned, try to detect using Frontend detection system
            if (empty($source_language)) {
                // Use Frontend class detection method
                $frontend = new \EZTranslate\Frontend();
                $group_info = $frontend->debug_post_metadata($post_id);

                if (isset($group_info['metadata']['_ez_translate_language']) && !empty($group_info['metadata']['_ez_translate_language'])) {
                    $source_language = $group_info['metadata']['_ez_translate_language'];
                    $source_metadata['group'] = $group_info['metadata']['_ez_translate_group'] ?? '';
                } else {
                    // If still no language detected, check if this post is part of a translation group
                    // and try to infer the language from the group context
                    if (!empty($source_metadata['group'])) {
                        $related_post_ids = PostMetaManager::get_posts_in_group($source_metadata['group']);

                        // If this is the only post without language in the group, it's likely the original
                        $posts_with_language = 0;
                        foreach ($related_post_ids as $related_id) {
                            if ($related_id != $post_id) {
                                $related_meta = PostMetaManager::get_post_metadata($related_id);
                                if (!empty($related_meta['language'])) {
                                    $posts_with_language++;
                                }
                            }
                        }

                        // If other posts in group have languages but this one doesn't, assume it's Spanish (original)
                        if ($posts_with_language > 0) {
                            $source_language = 'es'; // Default to Spanish as original language

                            // Auto-fix: Set the language for this post
                            PostMetaManager::set_post_language($post_id, $source_language);

                            Logger::info('REST API: Auto-fixed missing language for original post', array(
                                'post_id' => $post_id,
                                'assigned_language' => $source_language,
                                'group_id' => $source_metadata['group']
                            ));
                        }
                    }
                }
            }

            $result = array(
                'source_post_id' => $post_id,
                'source_language' => $source_language,
                'source_language_detected' => empty($source_metadata['language']), // True if auto-detected
                'translation_group' => $source_metadata['group'] ?? '',
                'available_languages' => array(),
                'existing_translations' => array(),
                'unavailable_languages' => array()
            );

            // Get existing translations if we have a group
            $existing_translations = array();
            $group_id = $source_metadata['group'] ?? '';

            if (!empty($group_id)) {
                // Include drafts and other statuses to show all existing translations
                $related_post_ids = PostMetaManager::get_posts_in_group($group_id, array('publish', 'draft', 'pending', 'private'));

                foreach ($related_post_ids as $related_post_id) {
                    $related_post = get_post($related_post_id);
                    if (!$related_post) {
                        continue; // Skip if post doesn't exist
                    }

                    $related_metadata = PostMetaManager::get_post_metadata($related_post_id);
                    $related_language = $related_metadata['language'] ?? '';

                    if (!empty($related_language)) {
                        $translation_info = array(
                            'post_id' => $related_post_id,
                            'title' => $related_post->post_title,
                            'status' => $related_post->post_status,
                            'edit_url' => admin_url('post.php?post=' . $related_post_id . '&action=edit'),
                            'view_url' => get_permalink($related_post_id),
                            'language' => $related_language,
                            'is_current' => ($related_post_id == $post_id)
                        );

                        $existing_translations[$related_language] = $translation_info;
                    }
                }
            } else {
                // If no group, try to find related posts through auto-detection
                // This handles cases where the source post doesn't have explicit metadata
                $frontend = new \EZTranslate\Frontend();
                $group_info = $frontend->debug_post_metadata($post_id);

                if (!empty($group_info['translation_group']['group_id'])) {
                    $auto_group_id = $group_info['translation_group']['group_id'];
                    $related_post_ids = PostMetaManager::get_posts_in_group($auto_group_id);

                    foreach ($related_post_ids as $related_post_id) {
                        $related_post = get_post($related_post_id);
                        if (!$related_post) {
                            continue;
                        }

                        $related_metadata = PostMetaManager::get_post_metadata($related_post_id);
                        $related_language = $related_metadata['language'] ?? '';

                        if (!empty($related_language)) {
                            $translation_info = array(
                                'post_id' => $related_post_id,
                                'title' => $related_post->post_title,
                                'status' => $related_post->post_status,
                                'edit_url' => admin_url('post.php?post=' . $related_post_id . '&action=edit'),
                                'view_url' => get_permalink($related_post_id),
                                'language' => $related_language,
                                'is_current' => ($related_post_id == $post_id)
                            );

                            $existing_translations[$related_language] = $translation_info;
                        }
                    }
                }
            }

            // Determine which post is the original (based on site language, not date)
            if (!empty($existing_translations)) {
                // Get the site's default language (WordPress locale converted to language code)
                $wp_locale = get_locale(); // e.g., 'es_ES', 'en_US'
                $site_language = substr($wp_locale, 0, 2); // Convert to 'es', 'en'

                // Try to get the configured default language from settings
                $default_language = null;
                $languages = LanguageManager::get_enabled_languages();
                foreach ($languages as $lang) {
                    if (isset($lang['is_default']) && $lang['is_default']) {
                        $default_language = $lang['code'];
                        break;
                    }
                }

                // Priority: 1) Configured default language, 2) Site language, 3) Spanish as fallback
                $original_language = $default_language ?: $site_language;
                if (!isset($existing_translations[$original_language])) {
                    $original_language = 'es'; // Fallback to Spanish
                }

                // Mark the post in the original language as original
                if (isset($existing_translations[$original_language])) {
                    foreach ($existing_translations as $lang => &$translation) {
                        $translation['is_original'] = ($lang === $original_language);
                    }
                    unset($translation); // Break reference
                } else {
                    Logger::warning('REST API: Could not identify original post', array(
                        'available_languages' => array_keys($existing_translations),
                        'expected_original_language' => $original_language
                    ));
                }
            }

            // Process all languages to determine availability
            foreach ($all_languages as $language) {
                $language_code = $language['code'];

                if (isset($existing_translations[$language_code])) {
                    // Translation exists - add to existing translations list
                    $result['existing_translations'][] = array_merge(
                        $existing_translations[$language_code],
                        array('language_info' => $language)
                    );

                    // Mark as unavailable for creation (translation already exists)
                    $result['unavailable_languages'][] = $language_code;
                } else {
                    // Translation available for creation
                    $result['available_languages'][] = $language;
                }
            }

            return rest_ensure_response($result);

        } catch (Exception $e) {
            Logger::error('REST API: Exception verifying translations', array(
                'error' => $e->getMessage(),
                'post_id' => $request->get_param('id')
            ));

            return new \WP_Error('verify_translations_failed', 'Failed to verify existing translations', array('status' => 500));
        }
    }

    /**
     * Generate SEO fields using AI
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function generate_seo_fields($request) {
        try {
            $post_id = (int) $request->get_param('post_id');
            $custom_content = $request->get_param('content');
            $custom_title = $request->get_param('title');

            // Get post data
            $post = get_post($post_id);
            if (!$post) {
                return new \WP_Error('post_not_found', 'Post not found', array('status' => 404));
            }

            // Check if AI is enabled
            if (!LanguageManager::is_api_enabled()) {
                return new \WP_Error('ai_not_enabled', 'AI features are not enabled', array('status' => 400));
            }

            // Use custom content/title if provided, otherwise use post data
            $title = $custom_title ?: $post->post_title;
            $content = $custom_content ?: $post->post_content;

            // Get post language for context
            $post_meta = PostMetaManager::get_post_metadata($post_id);
            $language = $post_meta['language'] ?? 'es'; // Default to Spanish

            // Get language info for better context
            $language_info = LanguageManager::get_language($language);
            $language_name = $language_info ? $language_info['name'] : $language;

            Logger::info('REST API: Generating SEO fields with AI', array(
                'post_id' => $post_id,
                'language' => $language,
                'title_length' => strlen($title),
                'content_length' => strlen($content)
            ));

            // Create prompt for SEO generation
            $prompt = new ConstructPrompt($title, $content, $language_name);

            // Use SEO Gemini provider
            $seo_provider = new \EZTranslate\Providers\SeoGeminiProvider();
            $seo_fields = $seo_provider->generateSeoFields($prompt);

            Logger::info('REST API: SEO fields generated successfully', array(
                'post_id' => $post_id,
                'generated_fields' => array_keys($seo_fields)
            ));

            return rest_ensure_response(array(
                'success' => true,
                'data' => $seo_fields,
                'message' => 'SEO fields generated successfully'
            ));

        } catch (Exception $e) {
            Logger::error('REST API: Failed to generate SEO fields', array(
                'error' => $e->getMessage(),
                'post_id' => $request->get_param('post_id')
            ));

            return new \WP_Error('seo_generation_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Generate shorter version of SEO content
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function generate_shorter_seo($request) {
        try {
            $post_id = (int) $request->get_param('post_id');
            $content = sanitize_text_field($request->get_param('content'));
            $type = sanitize_text_field($request->get_param('type'));
            $max_length = (int) $request->get_param('max_length');

            // Check if AI is enabled
            if (!LanguageManager::is_api_enabled()) {
                return new \WP_Error('ai_not_enabled', 'AI features are not enabled', array('status' => 400));
            }

            Logger::info('REST API: Generating shorter SEO content', array(
                'post_id' => $post_id,
                'type' => $type,
                'original_length' => strlen($content),
                'max_length' => $max_length
            ));

            // Use SEO Gemini provider
            $seo_provider = new \EZTranslate\Providers\SeoGeminiProvider();
            $shorter_content = $seo_provider->generateShorterVersion($content, $type, $max_length);

            Logger::info('REST API: Shorter SEO content generated', array(
                'post_id' => $post_id,
                'new_length' => strlen($shorter_content)
            ));

            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'shortened_content' => $shorter_content,
                    'original_length' => strlen($content),
                    'new_length' => strlen($shorter_content)
                ),
                'message' => 'Shorter version generated successfully'
            ));

        } catch (Exception $e) {
            Logger::error('REST API: Failed to generate shorter SEO content', array(
                'error' => $e->getMessage(),
                'post_id' => $request->get_param('post_id')
            ));

            return new \WP_Error('shorter_seo_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Generate alternative title suggestions
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function generate_alternative_title($request) {
        try {
            $post_id = (int) $request->get_param('post_id');
            $original_title = sanitize_text_field($request->get_param('original_title'));
            $similar_titles = $request->get_param('similar_titles');
            $custom_content = $request->get_param('content');

            // Get post data
            $post = get_post($post_id);
            if (!$post) {
                return new \WP_Error('post_not_found', 'Post not found', array('status' => 404));
            }

            // Check if AI is enabled
            if (!LanguageManager::is_api_enabled()) {
                return new \WP_Error('ai_not_enabled', 'AI features are not enabled', array('status' => 400));
            }

            // Sanitize similar titles array
            $sanitized_similar_titles = array();
            if (is_array($similar_titles)) {
                foreach ($similar_titles as $title) {
                    $sanitized_similar_titles[] = sanitize_text_field($title);
                }
            }

            $content = $custom_content ?: $post->post_content;

            Logger::info('REST API: Generating alternative titles', array(
                'post_id' => $post_id,
                'original_title' => $original_title,
                'similar_count' => count($sanitized_similar_titles)
            ));

            // Use SEO Gemini provider
            $seo_provider = new \EZTranslate\Providers\SeoGeminiProvider();
            $alternatives = $seo_provider->generateAlternativeTitle($original_title, $sanitized_similar_titles, $content);

            Logger::info('REST API: Alternative titles generated', array(
                'post_id' => $post_id,
                'alternatives_count' => count($alternatives)
            ));

            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'alternatives' => $alternatives,
                    'original_title' => $original_title,
                    'similar_titles' => $sanitized_similar_titles
                ),
                'message' => 'Alternative titles generated successfully'
            ));

        } catch (Exception $e) {
            Logger::error('REST API: Failed to generate alternative titles', array(
                'error' => $e->getMessage(),
                'post_id' => $request->get_param('post_id')
            ));

            return new \WP_Error('alternative_titles_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Get language detector data with available translations
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function get_language_detector_data($request) {
        try {
            // Load language detector class
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-detector.php';

            $post_id = $request->get_param('post_id');
            $current_language = null;
            $available_translations = array();

            // Get current page language if post_id is provided
            if (!empty($post_id)) {
                $current_language = \EZTranslate\LanguageDetector::get_page_language($post_id);

                // Get available translations for this post
                $available_translations = $this->get_available_translations_for_post($post_id, $current_language);
            } else {
                // Fallback to WordPress locale
                $wp_locale = get_locale();
                $current_language = substr($wp_locale, 0, 2);
            }

            // Get detector configuration
            $config = \EZTranslate\LanguageDetector::get_detector_config();

            // Get available languages
            $languages = \EZTranslate\LanguageDetector::get_available_languages();

            $response_data = array(
                'enabled' => $config['enabled'],
                'current_language' => $current_language,
                'available_languages' => $languages,
                'available_translations' => $available_translations,
                'config' => $config,
                'post_id' => $post_id,
                'has_translations' => !empty($available_translations)
            );

            Logger::info('Language detector data retrieved', array(
                'post_id' => $post_id,
                'current_language' => $current_language,
                'languages_count' => count($languages),
                'translations_count' => count($available_translations),
                'enabled' => $config['enabled']
            ));

            return new \WP_REST_Response($response_data, 200);

        } catch (Exception $e) {
            Logger::error('Error retrieving language detector data', array(
                'error' => $e->getMessage(),
                'post_id' => $request->get_param('post_id')
            ));

            return new \WP_REST_Response(array(
                'error' => 'Failed to retrieve language detector data',
                'message' => $e->getMessage()
            ), 500);
        }
    }

    /**
     * Get available translations for a specific post
     *
     * @param int $post_id Post ID
     * @param string $current_language Current page language
     * @return array Available translations with URLs
     * @since 1.0.0
     */
    private function get_available_translations_for_post($post_id, $current_language) {
        $translations = array();

        // Get the translation group ID
        $group_id = get_post_meta($post_id, '_ez_translate_group', true);

        if (empty($group_id)) {
            return $translations;
        }

        // Find posts in the same group
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-post-meta-manager.php';
        $posts_in_group = \EZTranslate\PostMetaManager::get_posts_in_group($group_id);

        foreach ($posts_in_group as $related_post_id) {
            // Ensure we have a valid post ID
            if (!is_numeric($related_post_id)) {
                continue;
            }

            $post = get_post($related_post_id);
            if (!$post || !is_object($post) || $post->post_status !== 'publish') {
                continue;
            }

            $post_language = get_post_meta($post->ID, '_ez_translate_language', true);

            // Skip current post and posts without language
            if ($post->ID == $post_id || empty($post_language)) {
                continue;
            }

            $translations[] = array(
                'language_code' => $post_language,
                'post_id' => $post->ID,
                'url' => get_permalink($post->ID),
                'title' => get_the_title($post->ID)
            );
        }

        // Also get landing pages for languages that don't have translations
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-detector.php';
        $available_languages = \EZTranslate\LanguageDetector::get_available_languages();

        foreach ($available_languages as $language) {
            $lang_code = $language['code'];

            // Skip current language
            if ($lang_code === $current_language) {
                continue;
            }

            // Check if we already have a translation for this language
            $has_translation = false;
            foreach ($translations as $translation) {
                if ($translation['language_code'] === $lang_code) {
                    $has_translation = true;
                    break;
                }
            }

            // If no translation exists, add landing page option
            if (!$has_translation) {
                $landing_page_id = \EZTranslate\LanguageDetector::get_landing_page($lang_code);

                if ($landing_page_id) {
                    $landing_post = get_post($landing_page_id);
                    if ($landing_post && $landing_post->post_status === 'publish') {
                        $translations[] = array(
                            'language_code' => $lang_code,
                            'post_id' => $landing_page_id,
                            'url' => get_permalink($landing_page_id),
                            'title' => get_the_title($landing_page_id),
                            'is_landing_page' => true
                        );
                    }
                }
            }
        }

        return $translations;
    }



    /**
     * Check title similarity against existing posts
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function check_title_similarity($request) {
        try {
            $post_id = (int) $request->get_param('post_id');
            $title = sanitize_text_field($request->get_param('title'));
            $threshold = (float) $request->get_param('threshold');

            Logger::info('REST API: Checking title similarity', array(
                'post_id' => $post_id,
                'title' => $title,
                'threshold' => $threshold
            ));

            // Get existing post titles with IDs (excluding current post)
            global $wpdb;
            $existing_posts = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_title, post_type FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                 AND post_type IN ('post', 'page')
                 AND ID != %d
                 AND post_title != ''",
                $post_id
            ), ARRAY_A);

            // Extract just titles for similarity check
            $existing_titles = array_column($existing_posts, 'post_title');

            // Use SEO Gemini provider for similarity check
            $seo_provider = new \EZTranslate\Providers\SeoGeminiProvider();
            $similarity_result = $seo_provider->checkTitleSimilarity($title, $existing_titles, $threshold);

            // If similar titles found, add post details (ID, URL)
            if ($similarity_result['is_similar'] && !empty($similarity_result['similar_titles'])) {
                $similar_posts_details = array();

                foreach ($similarity_result['similar_titles'] as $similar_title) {
                    // Find the post with this title
                    foreach ($existing_posts as $post_data) {
                        if ($post_data['post_title'] === $similar_title) {
                            $similar_posts_details[] = array(
                                'id' => $post_data['ID'],
                                'title' => $post_data['post_title'],
                                'type' => $post_data['post_type'],
                                'url' => get_permalink($post_data['ID']),
                                'edit_url' => get_edit_post_link($post_data['ID'])
                            );
                            break;
                        }
                    }
                }

                $similarity_result['similar_posts'] = $similar_posts_details;
            }

            Logger::info('REST API: Title similarity check completed', array(
                'post_id' => $post_id,
                'is_similar' => $similarity_result['is_similar'],
                'similarity_score' => $similarity_result['similarity_score'],
                'similar_count' => count($similarity_result['similar_titles'])
            ));

            return rest_ensure_response(array(
                'success' => true,
                'data' => $similarity_result,
                'message' => 'Title similarity check completed'
            ));

        } catch (Exception $e) {
            Logger::error('REST API: Failed to check title similarity', array(
                'error' => $e->getMessage(),
                'post_id' => $request->get_param('post_id')
            ));

            return new \WP_Error('similarity_check_failed', $e->getMessage(), array('status' => 500));
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
            // Landing page parameter removed - legacy functionality
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

    /**
     * Get API status
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function get_api_status($request) {
        try {
            $api_enabled = LanguageManager::is_api_enabled();
            $api_settings = LanguageManager::get_api_settings();

            Logger::info('REST API: API status requested', array(
                'api_enabled' => $api_enabled,
                'has_api_key' => !empty($api_settings['api_key'])
            ));

            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'api_enabled' => $api_enabled,
                    'has_api_key' => !empty($api_settings['api_key']),
                    'provider' => 'gemini'
                )
            ));
        } catch (Exception $e) {
            Logger::error('REST API: Failed to get API status', array(
                'error' => $e->getMessage()
            ));

            return new \WP_Error('api_status_failed', 'Failed to get API status', array('status' => 500));
        }
    }

    /**
     * Create multiple translations
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function create_multiple_translations($request) {
        try {
            $source_post_id = (int) $request->get_param('id');
            $target_languages = $request->get_param('target_languages');

            // Get the source post
            $source_post = get_post($source_post_id);
            if (!$source_post) {
                return new \WP_Error('source_post_not_found', 'Source post not found', array('status' => 404));
            }

            // Validate all target languages exist
            foreach ($target_languages as $target_language) {
                $language = LanguageManager::get_language($target_language);
                if (!$language) {
                    return new \WP_Error('invalid_target_language', 'Target language not found: ' . $target_language, array('status' => 400));
                }
            }

            $results = array();
            $errors = array();

            Logger::info('REST API: Creating multiple translations', array(
                'source_post_id' => $source_post_id,
                'target_languages' => $target_languages,
                'count' => count($target_languages)
            ));

            // Create translations one by one
            foreach ($target_languages as $target_language) {
                try {
                    // Create a mock request for the single translation endpoint
                    $single_request = new \WP_REST_Request('POST', '/ez-translate/v1/create-translation/' . $source_post_id);
                    $single_request->set_param('id', $source_post_id);
                    $single_request->set_param('target_language', $target_language);

                    // Call the existing create_translation method
                    $result = $this->create_translation($single_request);

                    if (is_wp_error($result)) {
                        $errors[] = array(
                            'language' => $target_language,
                            'error' => $result->get_error_message()
                        );
                    } else {
                        $response_data = $result->get_data();
                        if ($response_data['success']) {
                            $results[] = array(
                                'language' => $target_language,
                                'translation_id' => $response_data['data']['translation_id'],
                                'edit_url' => $response_data['data']['edit_url'],
                                'translation_method' => $response_data['data']['translation_method'] ?? 'copy'
                            );
                        } else {
                            $errors[] = array(
                                'language' => $target_language,
                                'error' => 'Failed to create translation'
                            );
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = array(
                        'language' => $target_language,
                        'error' => $e->getMessage()
                    );
                }
            }

            Logger::info('REST API: Multiple translations completed', array(
                'source_post_id' => $source_post_id,
                'successful' => count($results),
                'failed' => count($errors)
            ));

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Multiple translations process completed',
                'data' => array(
                    'successful_translations' => $results,
                    'failed_translations' => $errors,
                    'total_requested' => count($target_languages),
                    'successful_count' => count($results),
                    'failed_count' => count($errors)
                )
            ));

        } catch (Exception $e) {
            Logger::error('REST API: Failed to create multiple translations', array(
                'error' => $e->getMessage(),
                'source_post_id' => $request->get_param('id'),
                'target_languages' => $request->get_param('target_languages')
            ));

            return new \WP_Error('create_multiple_translations_failed', 'Failed to create multiple translations', array('status' => 500));
        }
    }
}
