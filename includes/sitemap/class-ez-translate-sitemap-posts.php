<?php
/**
 * Posts Sitemap Generator for EZ Translate
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate\Sitemap;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use EZTranslate\Logger;
use EZTranslate\PostMetaManager;

/**
 * Posts Sitemap Generator class
 *
 * Generates sitemaps for posts with multilingual support
 *
 * @since 1.0.0
 */
class SitemapPosts extends SitemapGenerator {

    /**
     * Generate posts sitemap XML
     *
     * @param string $language Language code (optional)
     * @return string
     * @since 1.0.0
     */
    public function generate($language = '') {
        Logger::debug('Generating posts sitemap', array('language' => $language));
        
        $xml = $this->get_xml_header();
        $xml .= $this->get_urlset_opening();
        
        $posts = $this->get_posts($language);
        
        foreach ($posts as $post) {
            $url = get_permalink($post->ID);
            
            // Skip if URL should be excluded
            if ($this->is_url_excluded($url)) {
                continue;
            }
            
            $lastmod = $this->format_sitemap_date($post->post_modified_gmt);
            $changefreq = $this->get_change_frequency('post', $post->post_modified_gmt);
            $priority = $this->get_priority_for_type('post');
            
            $xml .= $this->generate_url_entry($url, $lastmod, $changefreq, $priority);
        }
        
        $xml .= $this->get_urlset_closing();
        
        Logger::info('Posts sitemap generated', array(
            'language' => $language,
            'posts_count' => count($posts),
            'size' => strlen($xml)
        ));
        
        return $xml;
    }

    /**
     * Get posts for sitemap
     *
     * @param string $language Language code (optional)
     * @return array
     * @since 1.0.0
     */
    private function get_posts($language = '') {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        );
        
        // Filter by language if specified
        if (!empty($language)) {
            $args['meta_query'] = array(
                array(
                    'key' => '_ez_translate_language',
                    'value' => $language,
                    'compare' => '='
                )
            );
        } else {
            // If no language specified and multilingual is enabled,
            // get posts for default language (Spanish/es or posts without metadata)
            $enabled_languages = $this->get_enabled_languages();
            if (!empty($enabled_languages)) {
                $args['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_ez_translate_language',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_ez_translate_language',
                        'value' => 'es', // Spanish as default language
                        'compare' => '='
                    )
                );
            }
        }
        
        $query = new \WP_Query($args);
        return $query->posts;
    }

    /**
     * Get last modification time for posts sitemap
     *
     * @param string $language Language code (optional)
     * @return string
     * @since 1.0.0
     */
    public function get_last_modified($language = '') {
        global $wpdb;

        if (!empty($language)) {
            // Specific language posts
            $last_modified = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}
                WHERE post_type = 'post' AND post_status = 'publish'
                AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_ez_translate_language' AND meta_value = %s
                )",
                $language
            ));
        } else {
            // Default language posts (Spanish or posts without metadata)
            $enabled_languages = $this->get_enabled_languages();
            if (!empty($enabled_languages)) {
                $last_modified = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}
                    WHERE post_type = 'post' AND post_status = 'publish'
                    AND (
                        ID NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ez_translate_language')
                        OR ID IN (
                            SELECT post_id FROM {$wpdb->postmeta}
                            WHERE meta_key = '_ez_translate_language' AND meta_value = %s
                        )
                    )",
                    'es'
                ));
            } else {
                // No multilingual setup, get all posts
                $last_modified = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}
                    WHERE post_type = %s AND post_status = %s",
                    'post',
                    'publish'
                ));
            }
        }
        return $this->format_sitemap_date($last_modified);
    }

    /**
     * Get posts count for language
     *
     * @param string $language Language code (optional)
     * @return int
     * @since 1.0.0
     */
    public function get_posts_count($language = '') {
        global $wpdb;

        if (!empty($language)) {
            // Specific language posts
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type = 'post' AND post_status = 'publish'
                AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_ez_translate_language' AND meta_value = %s
                )",
                $language
            ));
        } else {
            // Default language posts (Spanish or posts without metadata)
            $enabled_languages = $this->get_enabled_languages();
            if (!empty($enabled_languages)) {
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                    WHERE post_type = 'post' AND post_status = 'publish'
                    AND (
                        ID NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ez_translate_language')
                        OR ID IN (
                            SELECT post_id FROM {$wpdb->postmeta}
                            WHERE meta_key = '_ez_translate_language' AND meta_value = %s
                        )
                    )",
                    'es'
                ));
            } else {
                // No multilingual setup, get all posts
                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                    WHERE post_type = %s AND post_status = %s",
                    'post',
                    'publish'
                ));
            }
        }
    }

    /**
     * Check if posts sitemap should be generated for language
     *
     * @param string $language Language code (optional)
     * @return bool
     * @since 1.0.0
     */
    public function should_generate($language = '') {
        // Check if post type is enabled
        if (!in_array('post', $this->settings['post_types'])) {
            return false;
        }
        
        // Check if there are posts for this language
        return $this->get_posts_count($language) > 0;
    }

    /**
     * Get sample URLs for testing
     *
     * @param string $language Language code (optional)
     * @param int $limit Number of URLs to return
     * @return array
     * @since 1.0.0
     */
    public function get_sample_urls($language = '', $limit = 5) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC'
        );
        
        if (!empty($language)) {
            $args['meta_query'] = array(
                array(
                    'key' => '_ez_translate_language',
                    'value' => $language,
                    'compare' => '='
                )
            );
        }
        
        $query = new \WP_Query($args);
        $urls = array();
        
        foreach ($query->posts as $post) {
            $urls[] = array(
                'url' => get_permalink($post->ID),
                'title' => $post->post_title,
                'modified' => $post->post_modified_gmt
            );
        }
        
        return $urls;
    }
}
