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
        // Check cache first
        $cache_key = 'ez_translate_sitemap_posts_' . md5($language);
        $cached_result = wp_cache_get($cache_key, 'ez_translate');

        if ($cached_result !== false) {
            return $cached_result;
        }

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
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            // Meta query is necessary for multilingual sitemap functionality
            $args['meta_query'] = array(
                array(
                    'key' => '_ez_translate_language',
                    'value' => $language,
                    'compare' => '='
                )
            );
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        } else {
            // If no language specified and multilingual is enabled,
            // get posts for default language (Spanish/es or posts without metadata)
            $enabled_languages = $this->get_enabled_languages();
            if (!empty($enabled_languages)) {
                // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                // Meta query is necessary for multilingual sitemap functionality
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
                // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            }
        }

        $query = new \WP_Query($args);
        $result = $query->posts;

        // Cache for 15 minutes (sitemap posts don't change frequently)
        wp_cache_set($cache_key, $result, 'ez_translate', 900);

        return $result;
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

        // Check cache first - critical for SEO bot performance
        $cache_key = 'ez_translate_sitemap_posts_lastmod_' . md5($language);
        $cached_result = wp_cache_get($cache_key, 'ez_translate');

        if ($cached_result !== false) {
            return $cached_result;
        }

        if (!empty($language)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
            // Critical SEO sitemap query - cache implemented above
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
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
        } else {
            // Default language posts (Spanish or posts without metadata)
            $enabled_languages = $this->get_enabled_languages();
            if (!empty($enabled_languages)) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
                // Critical SEO sitemap query - cache implemented above
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
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
            } else {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
                // Critical SEO sitemap query - cache implemented above
                // No multilingual setup, get all posts
                $last_modified = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}
                    WHERE post_type = %s AND post_status = %s",
                    'post',
                    'publish'
                ));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
            }
        }

        $formatted_date = $this->format_sitemap_date($last_modified);

        // Cache for 30 minutes (sitemap data is semi-static)
        wp_cache_set($cache_key, $formatted_date, 'ez_translate', 1800);

        return $formatted_date;
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

        // Check cache first - critical for SEO bot performance
        $cache_key = 'ez_translate_sitemap_posts_count_' . md5($language);
        $cached_result = wp_cache_get($cache_key, 'ez_translate');

        if ($cached_result !== false) {
            return (int) $cached_result;
        }

        if (!empty($language)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
            // Critical SEO sitemap query - cache implemented above
            // Specific language posts
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type = 'post' AND post_status = 'publish'
                AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_ez_translate_language' AND meta_value = %s
                )",
                $language
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
        } else {
            // Default language posts (Spanish or posts without metadata)
            $enabled_languages = $this->get_enabled_languages();
            if (!empty($enabled_languages)) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
                // Critical SEO sitemap query - cache implemented above
                $count = $wpdb->get_var($wpdb->prepare(
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
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
            } else {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
                // Critical SEO sitemap query - cache implemented above
                // No multilingual setup, get all posts
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                    WHERE post_type = %s AND post_status = %s",
                    'post',
                    'publish'
                ));
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
            }
        }

        $result = (int) $count;

        // Cache for 30 minutes (sitemap data is semi-static)
        wp_cache_set($cache_key, $result, 'ez_translate', 1800);

        return $result;
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
        // Check cache first
        $cache_key = 'ez_translate_sitemap_posts_sample_urls_' . md5($language . '_' . $limit);
        $cached_result = wp_cache_get($cache_key, 'ez_translate');

        if ($cached_result !== false) {
            return $cached_result;
        }

        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC'
        );

        if (!empty($language)) {
            // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            // Meta query is necessary for language-specific sample URLs
            $args['meta_query'] = array(
                array(
                    'key' => '_ez_translate_language',
                    'value' => $language,
                    'compare' => '='
                )
            );
            // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
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

        // Cache for 5 minutes (sample URLs are for testing/preview)
        wp_cache_set($cache_key, $urls, 'ez_translate', 300);

        return $urls;
    }
}
