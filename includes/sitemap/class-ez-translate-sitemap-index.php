<?php
/**
 * Sitemap Index Generator for EZ Translate
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
 * Sitemap Index Generator class
 *
 * Generates the main sitemap index that lists all available sitemaps
 *
 * @since 1.0.0
 */
class SitemapIndex extends SitemapGenerator {

    /**
     * Generate sitemap index XML
     *
     * @param string $language Not used for index
     * @return string
     * @since 1.0.0
     */
    public function generate($language = '') {
        Logger::debug('Generating sitemap index');
        
        $xml = $this->get_xml_header();
        $xml .= $this->get_sitemapindex_opening();
        
        $site_url = $this->get_site_url();
        $enabled_languages = $this->get_enabled_languages();
        
        // Add posts sitemaps
        if (in_array('post', $this->settings['post_types'])) {
            if (empty($enabled_languages)) {
                // Single language site
                $xml .= $this->generate_sitemap_entry(
                    $site_url . '/sitemap-posts.xml',
                    $this->get_posts_last_modified()
                );
            } else {
                // Multi-language site - include default language sitemap
                $xml .= $this->generate_sitemap_entry(
                    $site_url . '/sitemap-posts.xml',
                    $this->get_posts_last_modified('')
                );

                // Add language-specific sitemaps
                foreach ($enabled_languages as $lang_code) {
                    $xml .= $this->generate_sitemap_entry(
                        $site_url . '/sitemap-posts-' . $lang_code . '.xml',
                        $this->get_posts_last_modified($lang_code)
                    );
                }
            }
        }
        
        // Add pages sitemaps
        if (in_array('page', $this->settings['post_types'])) {
            if (empty($enabled_languages)) {
                // Single language site
                $xml .= $this->generate_sitemap_entry(
                    $site_url . '/sitemap-pages.xml',
                    $this->get_pages_last_modified()
                );
            } else {
                // Multi-language site - include default language sitemap
                $xml .= $this->generate_sitemap_entry(
                    $site_url . '/sitemap-pages.xml',
                    $this->get_pages_last_modified('')
                );

                // Add language-specific sitemaps
                foreach ($enabled_languages as $lang_code) {
                    $xml .= $this->generate_sitemap_entry(
                        $site_url . '/sitemap-pages-' . $lang_code . '.xml',
                        $this->get_pages_last_modified($lang_code)
                    );
                }
            }
        }
        
        // Add taxonomy sitemaps
        if (!empty($this->settings['taxonomies'])) {
            if (empty($enabled_languages)) {
                // Single language site - add general taxonomy sitemap
                $xml .= $this->generate_sitemap_entry(
                    $site_url . '/sitemap-taxonomies.xml',
                    $this->get_taxonomies_last_modified()
                );
            } else {
                // Multi-language site - include default language sitemap
                $xml .= $this->generate_sitemap_entry(
                    $site_url . '/sitemap-taxonomies.xml',
                    $this->get_taxonomies_last_modified('')
                );

                // Add language-specific sitemaps
                foreach ($enabled_languages as $lang_code) {
                    $xml .= $this->generate_sitemap_entry(
                        $site_url . '/sitemap-taxonomies-' . $lang_code . '.xml',
                        $this->get_taxonomies_last_modified($lang_code)
                    );
                }
            }
        }
        
        $xml .= $this->get_sitemapindex_closing();
        
        Logger::info('Sitemap index generated', array(
            'languages' => count($enabled_languages),
            'size' => strlen($xml)
        ));
        
        return $xml;
    }

    /**
     * Get last modification time for sitemap index
     *
     * @param string $language Not used for index
     * @return string
     * @since 1.0.0
     */
    public function get_last_modified($language = '') {
        // Get the most recent modification from all content types
        $last_modified_times = array();
        
        // Posts
        $posts_modified = $this->get_posts_last_modified();
        if (!empty($posts_modified)) {
            $last_modified_times[] = strtotime($posts_modified);
        }
        
        // Pages
        $pages_modified = $this->get_pages_last_modified();
        if (!empty($pages_modified)) {
            $last_modified_times[] = strtotime($pages_modified);
        }
        
        // Taxonomies
        $taxonomies_modified = $this->get_taxonomies_last_modified();
        if (!empty($taxonomies_modified)) {
            $last_modified_times[] = strtotime($taxonomies_modified);
        }
        
        if (empty($last_modified_times)) {
            return $this->format_sitemap_date(current_time('timestamp'));
        }
        
        $latest = max($last_modified_times);
        return $this->format_sitemap_date($latest);
    }

    /**
     * Get last modified time for posts
     *
     * @param string $language Language code (optional)
     * @return string
     * @since 1.0.0
     */
    private function get_posts_last_modified($language = '') {
        global $wpdb;

        if (!empty($language)) {
            // Specific language content
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
            // Default language content (Spanish or no language metadata)
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
     * Get last modified time for pages
     *
     * @param string $language Language code (optional)
     * @return string
     * @since 1.0.0
     */
    private function get_pages_last_modified($language = '') {
        global $wpdb;

        if (!empty($language)) {
            // Specific language content
            $last_modified = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}
                WHERE post_type = 'page' AND post_status = 'publish'
                AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_ez_translate_language' AND meta_value = %s
                )",
                $language
            ));
        } else {
            // Default language content (Spanish or no language metadata)
            $enabled_languages = $this->get_enabled_languages();
            if (!empty($enabled_languages)) {
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
            } else {
                // No multilingual setup, get all pages
                $last_modified = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}
                    WHERE post_type = %s AND post_status = %s",
                    'page',
                    'publish'
                ));
            }
        }
        return $this->format_sitemap_date($last_modified);
    }

    /**
     * Get last modified time for taxonomies
     *
     * @param string $language Language code (optional)
     * @return string
     * @since 1.0.0
     */
    private function get_taxonomies_last_modified($language = '') {
        global $wpdb;

        // Build taxonomy conditions using individual OR clauses to avoid interpolation
        $taxonomy_conditions = array();
        foreach ($this->settings['taxonomies'] as $taxonomy) {
            $taxonomy_conditions[] = $wpdb->prepare('tt.taxonomy = %s', esc_sql($taxonomy));
        }
        $taxonomy_where = '(' . implode(' OR ', $taxonomy_conditions) . ')';

        if (!empty($language)) {
            // Specific language content
            $last_modified = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(p.post_modified_gmt)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE p.post_status = 'publish'
                AND {$taxonomy_where}
                AND p.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_ez_translate_language' AND meta_value = %s
                )",
                $language
            ));
        } else {
            // Default language content (Spanish or no language metadata)
            $enabled_languages = $this->get_enabled_languages();
            if (!empty($enabled_languages)) {
                $last_modified = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(p.post_modified_gmt)
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_status = 'publish'
                    AND {$taxonomy_where}
                    AND (
                        p.ID NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ez_translate_language')
                        OR p.ID IN (
                            SELECT post_id FROM {$wpdb->postmeta}
                            WHERE meta_key = '_ez_translate_language' AND meta_value = %s
                        )
                    )",
                    'es'
                ));
            } else {
                // No multilingual setup, get all taxonomy posts
                $last_modified = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(p.post_modified_gmt)
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE p.post_status = %s
                    AND {$taxonomy_where}",
                    'publish'
                ));
            }
        }

        return $this->format_sitemap_date($last_modified);
    }

    /**
     * Get count of sitemaps that will be generated
     *
     * @return int
     * @since 1.0.0
     */
    public function get_sitemap_count() {
        $count = 0;
        $enabled_languages = $this->get_enabled_languages();

        $language_multiplier = empty($enabled_languages) ? 1 : count($enabled_languages);

        // Posts sitemaps
        if (in_array('post', $this->settings['post_types'])) {
            $count += $language_multiplier;
        }

        // Pages sitemaps
        if (in_array('page', $this->settings['post_types'])) {
            $count += $language_multiplier;
        }

        // Taxonomy sitemaps
        if (!empty($this->settings['taxonomies'])) {
            $count += $language_multiplier;
        }

        return $count;
    }

}
