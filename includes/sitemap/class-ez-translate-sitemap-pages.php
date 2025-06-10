<?php
/**
 * Pages Sitemap Generator for EZ Translate
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
use EZTranslate\LanguageManager;

/**
 * Pages Sitemap Generator class
 *
 * Generates sitemaps for pages with multilingual support and landing page priority
 *
 * @since 1.0.0
 */
class SitemapPages extends SitemapGenerator {

    /**
     * Generate pages sitemap XML
     *
     * @param string $language Language code (optional)
     * @return string
     * @since 1.0.0
     */
    public function generate($language = '') {
        Logger::debug('Generating pages sitemap', array('language' => $language));
        
        $xml = $this->get_xml_header();
        $xml .= $this->get_urlset_opening();
        
        $pages = $this->get_pages($language);
        $landing_pages = $this->get_landing_pages($language);
        
        foreach ($pages as $page) {
            $url = get_permalink($page->ID);
            
            // Skip if URL should be excluded
            if ($this->is_url_excluded($url)) {
                continue;
            }
            
            $lastmod = $this->format_sitemap_date($page->post_modified_gmt);
            $changefreq = $this->get_change_frequency('page', $page->post_modified_gmt);
            
            // Check if this is a landing page for higher priority
            $is_landing = in_array($page->ID, $landing_pages);
            $priority = $is_landing ? 
                       $this->get_priority_for_type('landing_page') : 
                       $this->get_priority_for_type('page');
            
            $xml .= $this->generate_url_entry($url, $lastmod, $changefreq, $priority);
        }
        
        $xml .= $this->get_urlset_closing();
        
        Logger::info('Pages sitemap generated', array(
            'language' => $language,
            'pages_count' => count($pages),
            'landing_pages_count' => count($landing_pages),
            'size' => strlen($xml)
        ));
        
        return $xml;
    }

    /**
     * Get pages for sitemap
     *
     * @param string $language Language code (optional)
     * @return array
     * @since 1.0.0
     */
    private function get_pages($language = '') {
        // Check cache first
        $cache_key = 'ez_translate_sitemap_pages_' . md5($language);
        $cached_result = wp_cache_get($cache_key, 'ez_translate');

        if ($cached_result !== false) {
            return $cached_result;
        }

        $args = array(
            'post_type' => 'page',
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
            // get pages for default language (Spanish/es or pages without metadata)
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

        // Cache for 15 minutes (sitemap pages don't change frequently)
        wp_cache_set($cache_key, $result, 'ez_translate', 900);

        return $result;
    }

    /**
     * Get landing pages for the specified language
     *
     * @param string $language Language code (optional)
     * @return array Array of page IDs
     * @since 1.0.0
     */
    private function get_landing_pages($language = '') {
        $landing_pages = array();
        
        // Get landing pages from language settings
        $languages = LanguageManager::get_languages();
        
        if (!empty($language)) {
            // Get landing page for specific language
            foreach ($languages as $lang) {
                if ($lang['code'] === $language && isset($lang['landing_page_id'])) {
                    $landing_pages[] = (int) $lang['landing_page_id'];
                    break;
                }
            }
        } else {
            // Get all landing pages for default language context
            $default_locale = get_locale();
            foreach ($languages as $lang) {
                if (isset($lang['landing_page_id'])) {
                    // Include landing page if it's for default language or no specific language
                    $page_language = get_post_meta($lang['landing_page_id'], '_ez_translate_language', true);
                    if (empty($page_language) || $page_language === $default_locale) {
                        $landing_pages[] = (int) $lang['landing_page_id'];
                    }
                }
            }
        }
        
        return array_unique($landing_pages);
    }

    /**
     * Get last modification time for pages sitemap
     *
     * @param string $language Language code (optional)
     * @return string
     * @since 1.0.0
     */
    public function get_last_modified($language = '') {
        global $wpdb;

        // Check cache first - critical for SEO bot performance
        $cache_key = 'ez_translate_sitemap_pages_lastmod_' . md5($language);
        $cached_result = wp_cache_get($cache_key, 'ez_translate');

        if ($cached_result !== false) {
            return $cached_result;
        }

        if (!empty($language)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
            // Critical SEO sitemap query - cache implemented above
            // Specific language pages
            $last_modified = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}
                WHERE post_type = 'page' AND post_status = 'publish'
                AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_ez_translate_language' AND meta_value = %s
                )",
                $language
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
        } else {
            // Default language pages (Spanish or pages without metadata)
            $enabled_languages = $this->get_enabled_languages();
            if (!empty($enabled_languages)) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
                // Critical SEO sitemap query - cache implemented above
                $last_modified = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}
                    WHERE post_type = 'page' AND post_status = 'publish'
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
                // No multilingual setup, get all pages
                $last_modified = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}
                    WHERE post_type = %s AND post_status = %s",
                    'page',
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
     * Get pages count for language
     *
     * @param string $language Language code (optional)
     * @return int
     * @since 1.0.0
     */
    public function get_pages_count($language = '') {
        global $wpdb;

        // Check cache first - critical for SEO bot performance
        $cache_key = 'ez_translate_sitemap_pages_count_' . md5($language);
        $cached_result = wp_cache_get($cache_key, 'ez_translate');

        if ($cached_result !== false) {
            return (int) $cached_result;
        }

        if (!empty($language)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
            // Critical SEO sitemap query - cache implemented above
            // Specific language pages
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_type = 'page' AND post_status = 'publish'
                AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_ez_translate_language' AND meta_value = %s
                )",
                $language
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
        } else {
            // Default language pages (Spanish or pages without metadata)
            $enabled_languages = $this->get_enabled_languages();
            if (!empty($enabled_languages)) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
                // Critical SEO sitemap query - cache implemented above
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                    WHERE post_type = 'page' AND post_status = 'publish'
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
                // No multilingual setup, get all pages
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                    WHERE post_type = %s AND post_status = %s",
                    'page',
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
     * Check if pages sitemap should be generated for language
     *
     * @param string $language Language code (optional)
     * @return bool
     * @since 1.0.0
     */
    public function should_generate($language = '') {
        // Check if page type is enabled
        if (!in_array('page', $this->settings['post_types'])) {
            return false;
        }
        
        // Check if there are pages for this language
        return $this->get_pages_count($language) > 0;
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
        $cache_key = 'ez_translate_sitemap_sample_urls_' . md5($language . '_' . $limit);
        $cached_result = wp_cache_get($cache_key, 'ez_translate');

        if ($cached_result !== false) {
            return $cached_result;
        }

        $args = array(
            'post_type' => 'page',
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
        $landing_pages = $this->get_landing_pages($language);

        foreach ($query->posts as $page) {
            $is_landing = in_array($page->ID, $landing_pages);
            $urls[] = array(
                'url' => get_permalink($page->ID),
                'title' => $page->post_title,
                'modified' => $page->post_modified_gmt,
                'is_landing' => $is_landing,
                'priority' => $is_landing ?
                            $this->get_priority_for_type('landing_page') :
                            $this->get_priority_for_type('page')
            );
        }

        // Cache for 5 minutes (sample URLs are for testing/preview)
        wp_cache_set($cache_key, $urls, 'ez_translate', 300);

        return $urls;
    }
}
