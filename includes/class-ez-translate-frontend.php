<?php
/**
 * Frontend functionality for EZ Translate
 *
 * Handles frontend operations including SEO metadata injection,
 * hreflang tags, and other frontend-specific features.
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend class
 *
 * @since 1.0.0
 */
class Frontend {



    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init();
    }



    /**
     * Initialize frontend functionality
     *
     * @since 1.0.0
     */
    private function init() {
        // Hook early to take control of metadata
        add_action('wp_head', array($this, 'override_head_metadata'), 1);

        // Hook into language attribute for HTML tag
        add_filter('language_attributes', array($this, 'filter_language_attributes'), 10, 1);

        // Hook into document title filter
        add_filter('document_title_parts', array($this, 'filter_document_title'), 10, 1);

        // Hook into wp_head for hreflang tags
        add_action('wp_head', array($this, 'inject_hreflang_tags'), 20);

        // Hook for language detector scripts and data
        add_action('wp_head', array($this, 'inject_language_detector_config'), 25);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_language_detector_assets'));

        // Hook to add dataset attributes to body
        add_filter('body_class', array($this, 'add_language_body_attributes'), 10, 1);
    }

    /**
     * Filter language attributes for HTML tag
     *
     * @param string $output Language attributes
     * @return string Modified language attributes
     * @since 1.0.0
     */
    public function filter_language_attributes($output) {
        global $post;

        // Handle homepage case (blog posts homepage)
        if ((is_home() || is_front_page()) && !is_singular()) {
            // Homepage uses WordPress default language
            $wp_locale = get_locale();
            $wp_language_code = strstr($wp_locale, '_', true) ?: $wp_locale; // es_MX -> es
            $current_language = $wp_language_code;
        } else {
            // Only process on singular pages (skip check in debug mode)
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                if (!is_singular() || !$post) {
                    return $output;
                }
            }

            // In debug mode, ensure we have a post
            if ((defined('WP_DEBUG') && WP_DEBUG) && !$post) {
                return $output;
            }

            // Get the current post's language
            $current_language = get_post_meta($post->ID, '_ez_translate_language', true);
        }

        if (!empty($current_language)) {
            // Convert language code to proper locale for lang attribute
            $locale = $this->convert_language_to_locale($current_language);
            $lang_code = strstr($locale, '_', true) ?: $locale; // Get language part before underscore

            // Replace or add lang attribute
            if (strpos($output, 'lang=') !== false) {
                $output = preg_replace('/lang="[^"]*"/', 'lang="' . esc_attr($lang_code) . '"', $output);
            } else {
                $output .= ' lang="' . esc_attr($lang_code) . '"';
            }
        }

        return $output;
    }

    /**
     * Override and control all head metadata
     *
     * @since 1.0.0
     */
    public function override_head_metadata() {
        global $post;

        // Handle homepage case (blog posts homepage)
        if ((is_home() || is_front_page()) && !is_singular()) {
            $this->handle_homepage_metadata();
            return;
        }

        // Only process on singular pages (skip check in debug mode)
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            if (!is_singular() || !$post) {
                return;
            }
        }

        // In debug mode, ensure we have a post
        if ((defined('WP_DEBUG') && WP_DEBUG) && !$post) {
            return;
        }

        // Get post metadata
        $current_language = get_post_meta($post->ID, '_ez_translate_language', true);
        $is_landing = get_post_meta($post->ID, '_ez_translate_is_landing', true);
        $seo_title = get_post_meta($post->ID, '_ez_translate_seo_title', true);
        $seo_description = get_post_meta($post->ID, '_ez_translate_seo_description', true);

        // Check if this is the homepage or should use default language
        $wp_locale = get_locale();
        $wp_language_code = strstr($wp_locale, '_', true) ?: $wp_locale; // es_MX -> es
        $is_homepage = $this->is_homepage($post->ID);

        // If no language assigned, determine if we should use default language or try detection
        if (empty($current_language)) {
            if ($is_homepage) {
                // Homepage always uses WordPress default language
                $current_language = $wp_language_code;
                $is_landing = false; // Homepage is not a landing page

                Logger::info('Frontend: Homepage detected, using WordPress default language', array(
                    'post_id' => $post->ID,
                    'wp_locale' => $wp_locale,
                    'assigned_language' => $current_language
                ));
            } else {
                // For other pages, try to detect if they're part of a translation group
                $group_info = $this->detect_translation_group_membership($post->ID);
                if ($group_info) {
                    $current_language = $group_info['language'];
                    $is_landing = $group_info['is_landing'];

                    Logger::info('Frontend: Auto-detected translation group membership', array(
                        'post_id' => $post->ID,
                        'detected_language' => $current_language,
                        'group_id' => $group_info['group_id'],
                        'role' => $group_info['role'],
                        'total_in_group' => $group_info['total_in_group']
                    ));
                } else {
                    // No language metadata and not part of translation group
                    // For content without explicit language, use WordPress default
                    $current_language = $wp_language_code;
                    $is_landing = false;

                    Logger::info('Frontend: No language metadata found, using WordPress default', array(
                        'post_id' => $post->ID,
                        'wp_locale' => $wp_locale,
                        'assigned_language' => $current_language,
                        'reason' => 'no_metadata_fallback'
                    ));
                }
            }
        }

        // Only process pages with language metadata (assigned or detected)
        if (empty($current_language)) {
            return;
        }



        // Generate our complete metadata
        $this->generate_complete_metadata($post, $current_language, $is_landing, $seo_title, $seo_description);
    }

    /**
     * Generate complete and consistent metadata
     *
     * @param WP_Post $post Current post
     * @param string $language Language code
     * @param bool $is_landing Whether this is a landing page
     * @param string $seo_title Custom SEO title
     * @param string $seo_description Custom SEO description
     * @since 1.0.0
     */
    private function generate_complete_metadata($post, $language, $is_landing, $seo_title, $seo_description) {
        // Detect if this is the WordPress default language
        $wp_locale = get_locale();
        $wp_language_code = strstr($wp_locale, '_', true) ?: $wp_locale; // es_MX -> es
        $is_default_language = empty($language) || $language === $wp_language_code;

        // Get language-specific site metadata (now handles default language automatically)
        $language_site_metadata = \EZTranslate\LanguageManager::get_language_site_metadata($language);

        // Determine title and description with fallback logic
        if ($is_landing) {
            // For landing pages: SEO title > Language site title > Post title
            $page_title = !empty($seo_title) ? $seo_title :
                         (!empty($language_site_metadata['site_title']) ? $language_site_metadata['site_title'] : $post->post_title);

            // For landing pages: SEO description > Language site description > Post excerpt
            $page_description = !empty($seo_description) ? $seo_description :
                               (!empty($language_site_metadata['site_description']) ? $language_site_metadata['site_description'] : $this->get_post_excerpt($post));
        } else {
            // For regular pages with language: SEO title > Post title
            $page_title = !empty($seo_title) ? $seo_title : $post->post_title;
            $page_description = !empty($seo_description) ? $seo_description : $this->get_post_excerpt($post);
        }

        // Get current URL (handle homepage case)
        if ($post->ID === 0) {
            // Homepage case
            $current_url = home_url('/');
        } else {
            $current_url = get_permalink($post->ID);
        }

        // Convert language to locale
        $locale = $this->convert_language_to_locale($language);

        // Determine content type
        $og_type = $is_landing ? 'website' : 'article';

        // Start EZ Translate metadata section
        echo "\n<!-- EZ Translate: SEO Metadata -->\n";

        // Generate meta description
        echo '<meta name="description" content="' . esc_attr($page_description) . '">' . "\n";

        // Generate Open Graph metadata
        echo '<!-- EZ Translate: Open Graph -->' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($page_title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($page_description) . '">' . "\n";
        echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($current_url) . '">' . "\n";
        echo '<meta property="og:locale" content="' . esc_attr($locale) . '">' . "\n";

        // Add site name (use language-specific name if available)
        $site_name = !empty($language_site_metadata['site_name']) ? $language_site_metadata['site_name'] : get_bloginfo('name');
        if (!empty($site_name)) {
            echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
        }

        // Include featured image if available (skip for homepage)
        if ($post->ID !== 0 && has_post_thumbnail($post->ID)) {
            $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large');
            if ($thumbnail_url) {
                echo '<meta property="og:image" content="' . esc_url($thumbnail_url) . '">' . "\n";
            }
        }

        // Generate Twitter Card metadata
        echo '<!-- EZ Translate: Twitter Cards -->' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($page_title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($page_description) . '">' . "\n";

        // Include Twitter image if available (skip for homepage)
        if ($post->ID !== 0 && has_post_thumbnail($post->ID)) {
            $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large');
            if ($thumbnail_url) {
                echo '<meta name="twitter:image" content="' . esc_url($thumbnail_url) . '">' . "\n";
            }
        }

        // Generate JSON-LD structured data for articles (skip for homepage and landing pages)
        if (!$is_landing && $post->ID !== 0) {
            echo '<!-- EZ Translate: JSON-LD Structured Data -->' . "\n";
            $this->generate_article_jsonld($post, $page_title, $page_description, $language, $current_url, $language_site_metadata);
        }

        // End EZ Translate metadata section
        echo '<!-- /EZ Translate: SEO Metadata -->' . "\n\n";

        Logger::info('Frontend: Complete metadata generated', array(
            'post_id' => $post->ID,
            'language' => $language,
            'is_default_language' => $is_default_language,
            'wp_language_code' => $wp_language_code,
            'is_landing' => $is_landing,
            'og_type' => $og_type,
            'title' => $page_title,
            'url' => $current_url,
            'used_language_site_title' => $is_landing && !empty($seo_title) ? false : !empty($language_site_metadata['site_title']),
            'used_language_site_description' => $is_landing && !empty($seo_description) ? false : !empty($language_site_metadata['site_description']),
            'metadata_source' => $is_default_language ? 'default_language_config' : 'language_specific_config'
        ));
    }

    /**
     * Handle homepage metadata when it's not a singular page
     *
     * @since 1.0.0
     */
    private function handle_homepage_metadata() {

        // Get WordPress default language
        $wp_locale = get_locale();
        $wp_language_code = strstr($wp_locale, '_', true) ?: $wp_locale; // es_MX -> es

        // Get configured default language metadata
        $default_metadata = \EZTranslate\LanguageManager::get_default_language_metadata();

        // Create a fake post object for homepage using configured metadata
        $homepage_post = new \stdClass();
        $homepage_post->ID = 0;

        // Use configured metadata if available, otherwise fallback to WordPress defaults
        $homepage_post->post_title = !empty($default_metadata['site_title']) ? $default_metadata['site_title'] : get_bloginfo('name');
        $homepage_post->post_content = !empty($default_metadata['site_description']) ? $default_metadata['site_description'] : get_bloginfo('description');
        $homepage_post->post_excerpt = !empty($default_metadata['site_description']) ? $default_metadata['site_description'] : get_bloginfo('description');

        // Generate metadata for homepage using default language
        $this->generate_complete_metadata($homepage_post, $wp_language_code, false, '', '');

        Logger::info('Frontend: Homepage metadata generated', array(
            'is_home' => is_home(),
            'is_front_page' => is_front_page(),
            'wp_locale' => $wp_locale,
            'language' => $wp_language_code,
            'configured_metadata' => $default_metadata,
            'used_title' => $homepage_post->post_title,
            'used_description' => $homepage_post->post_content,
            'wp_site_name' => get_bloginfo('name'),
            'wp_site_description' => get_bloginfo('description')
        ));
    }



    /**
     * Filter document title for pages with custom SEO titles and site names
     *
     * @param array $title_parts The document title parts
     * @return array Modified title parts
     * @since 1.0.0
     */
    public function filter_document_title($title_parts) {
        global $post;

        // Handle homepage case (blog posts homepage)
        if ((is_home() || is_front_page()) && !is_singular()) {
            // Homepage uses WordPress default language
            $wp_locale = get_locale();
            $wp_language_code = strstr($wp_locale, '_', true) ?: $wp_locale; // es_MX -> es
            $current_language = $wp_language_code;
            $seo_title = ''; // No custom SEO title for homepage
        } else {
            // Only process on singular pages (skip check in debug mode)
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                if (!is_singular() || !$post) {
                    return $title_parts;
                }
            }

            // In debug mode, ensure we have a post
            if ((defined('WP_DEBUG') && WP_DEBUG) && !$post) {
                return $title_parts;
            }

            // Check if this page has custom SEO title or language
            $seo_title = get_post_meta($post->ID, '_ez_translate_seo_title', true);
            $current_language = get_post_meta($post->ID, '_ez_translate_language', true);
        }

        // Only process if page has a language assigned (indicating it's managed by EZ Translate)
        if (!empty($current_language)) {
            // Get language-specific site metadata
            $language_site_metadata = \EZTranslate\LanguageManager::get_language_site_metadata($current_language);

            // Apply custom SEO title if available
            if (!empty($seo_title)) {
                $title_parts['title'] = sanitize_text_field($seo_title);
            }

            // Apply custom site name if available
            if (!empty($language_site_metadata['site_name'])) {
                $title_parts['site'] = sanitize_text_field($language_site_metadata['site_name']);
            }
        }

        return $title_parts;
    }

    /**
     * Inject meta description for pages with custom SEO descriptions
     *
     * @since 1.0.0
     */
    public function inject_meta_description() {
        global $post;

        // Only process on singular pages (skip check in debug mode)
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            if (!is_singular() || !$post) {
                return;
            }
        }

        // In debug mode, ensure we have a post
        if ((defined('WP_DEBUG') && WP_DEBUG) && !$post) {
            return;
        }

        // Check if this page has custom SEO description
        $seo_description = get_post_meta($post->ID, '_ez_translate_seo_description', true);
        $current_language = get_post_meta($post->ID, '_ez_translate_language', true);

        // Apply custom SEO description if:
        // 1. Page has a custom SEO description set, AND
        // 2. Page has a language assigned (indicating it's managed by EZ Translate)
        if (!empty($seo_description) && !empty($current_language)) {
            $clean_description = sanitize_text_field($seo_description);
            echo '<meta name="description" content="' . esc_attr($clean_description) . '">' . "\n";
        }
    }







    /**
     * Convert language code to locale format
     *
     * @param string $language_code Language code (e.g., 'es', 'en')
     * @return string Locale format (e.g., 'es_ES', 'en_US')
     * @since 1.0.0
     */
    private function convert_language_to_locale($language_code) {
        // Basic mapping of common language codes to locales
        $locale_map = array(
            'en' => 'en_US',
            'es' => 'es_ES',
            'fr' => 'fr_FR',
            'de' => 'de_DE',
            'it' => 'it_IT',
            'pt' => 'pt_BR',
            'ru' => 'ru_RU',
            'ja' => 'ja_JP',
            'ko' => 'ko_KR',
            'zh' => 'zh_CN',
            'ar' => 'ar_SA',
            'hi' => 'hi_IN',
            'nl' => 'nl_NL',
            'sv' => 'sv_SE',
            'da' => 'da_DK',
            'no' => 'nb_NO',
            'fi' => 'fi_FI',
            'pl' => 'pl_PL',
            'tr' => 'tr_TR',
            'he' => 'he_IL',
            'th' => 'th_TH',
            'vi' => 'vi_VN',
            'uk' => 'uk_UA',
            'cs' => 'cs_CZ',
            'hu' => 'hu_HU',
            'ro' => 'ro_RO',
            'bg' => 'bg_BG',
            'hr' => 'hr_HR',
            'sk' => 'sk_SK',
            'sl' => 'sl_SI',
            'et' => 'et_EE',
            'lv' => 'lv_LV',
            'lt' => 'lt_LT',
        );

        return isset($locale_map[$language_code]) ? $locale_map[$language_code] : $language_code . '_' . strtoupper($language_code);
    }

    /**
     * Inject hreflang tags for multilingual pages
     *
     * @since 1.0.0
     */
    public function inject_hreflang_tags() {
        global $post;

        // Only process on singular pages (skip check in debug mode)
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            if (!is_singular() || !$post) {
                return;
            }
        }

        // In debug mode, ensure we have a post
        if ((defined('WP_DEBUG') && WP_DEBUG) && !$post) {
            return;
        }

        // Check if this is a landing page first
        if ($this->is_landing_page($post->ID)) {
            $this->inject_landing_page_hreflang_tags($post->ID);
            return;
        }

        // Check if this is the homepage and we have landing pages configured
        if ($this->is_homepage($post->ID) && $this->has_landing_pages()) {
            $this->inject_landing_page_hreflang_tags($post->ID);
            return;
        }

        // Regular translation group logic for non-landing pages
        $current_language = get_post_meta($post->ID, '_ez_translate_language', true);
        $translation_group = get_post_meta($post->ID, '_ez_translate_group', true);

        // If no language or group assigned, try to detect
        if (empty($current_language) || empty($translation_group)) {
            $group_info = $this->detect_translation_group_membership($post->ID);
            if ($group_info) {
                $current_language = $group_info['language'];
                $translation_group = $group_info['group_id'];

                Logger::info('Frontend: Auto-detected for hreflang', array(
                    'post_id' => $post->ID,
                    'detected_language' => $current_language,
                    'detected_group' => $translation_group,
                    'detection_method' => $group_info['detection_method']
                ));
            }
        }

        // Skip if still no language or translation group
        if (empty($current_language) || empty($translation_group)) {
            return;
        }

        // Get all posts in the same translation group
        $related_posts = \EZTranslate\PostMetaManager::get_posts_in_group($translation_group);

        // Skip if no related posts found or only current post
        if (empty($related_posts) || count($related_posts) <= 1) {
            return;
        }

        // Generate hreflang tags for all related posts
        $hreflang_tags = array();
        $default_language_post = null;

        // Get configured default language for x-default
        $configured_default_language = $this->get_default_language_for_hreflang();

        foreach ($related_posts as $related_post_id) {
            $related_language = get_post_meta($related_post_id, '_ez_translate_language', true);

            // If post doesn't have language, try to detect it
            if (empty($related_language)) {
                $group_info = $this->detect_translation_group_membership($related_post_id);
                if ($group_info) {
                    $related_language = $group_info['language'];
                }
            }

            if (empty($related_language)) {
                continue;
            }

            $related_url = get_permalink($related_post_id);
            if (!$related_url) {
                continue;
            }

            // Convert language code to proper hreflang format
            $hreflang_code = $this->convert_language_to_hreflang($related_language);

            $hreflang_tags[] = array(
                'language' => $hreflang_code,
                'url' => $related_url,
                'post_id' => $related_post_id,
                'language_code' => $related_language
            );

            // Find the post for the configured default language
            if ($related_language === $configured_default_language) {
                $default_language_post = array(
                    'url' => $related_url,
                    'language' => $related_language,
                    'post_id' => $related_post_id
                );
            }
        }

        // If no configured default language found, use fallback logic
        if (!$default_language_post && !empty($hreflang_tags)) {
            // Fallback: prefer English, then Spanish, then first in list
            foreach ($hreflang_tags as $tag) {
                if (!$default_language_post) {
                    $default_language_post = array(
                        'url' => $tag['url'],
                        'language' => $tag['language_code'],
                        'post_id' => $tag['post_id']
                    );
                } elseif ($tag['language_code'] === 'en' && $default_language_post['language'] !== 'en') {
                    $default_language_post = array(
                        'url' => $tag['url'],
                        'language' => $tag['language_code'],
                        'post_id' => $tag['post_id']
                    );
                    break; // English found, stop looking
                }
            }
        }

        // Output hreflang tags
        if (!empty($hreflang_tags)) {
            echo "\n<!-- EZ Translate: Hreflang Tags -->\n";

            // Sort tags to ensure consistent order (current language first, then alphabetical)
            $current_hreflang = $this->convert_language_to_hreflang($current_language);
            $sorted_tags = array();
            $other_tags = array();

            foreach ($hreflang_tags as $tag) {
                if ($tag['language'] === $current_hreflang) {
                    $sorted_tags[] = $tag; // Current language first
                } else {
                    $other_tags[] = $tag;
                }
            }

            // Sort other tags alphabetically
            usort($other_tags, function($a, $b) {
                return strcmp($a['language'], $b['language']);
            });

            $all_tags = array_merge($sorted_tags, $other_tags);

            // Output all language-specific hreflang tags (including self-reference)
            foreach ($all_tags as $tag) {
                echo '<link rel="alternate" hreflang="' . esc_attr($tag['language']) . '" href="' . esc_url($tag['url']) . '">' . "\n";
            }

            // Output x-default tag (points to configured default language)
            if ($default_language_post) {
                echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($default_language_post['url']) . '">' . "\n";
            }

            echo '<!-- /EZ Translate: Hreflang Tags -->' . "\n\n";

            Logger::info('Frontend: Hreflang tags injected', array(
                'post_id' => $post->ID,
                'group' => $translation_group,
                'tags_count' => count($hreflang_tags),
                'languages' => array_column($hreflang_tags, 'language'),
                'current_language' => $current_hreflang,
                'configured_default' => $configured_default_language,
                'default_language' => $default_language_post ? $default_language_post['language'] : 'none',
                'includes_x_default' => !empty($default_language_post)
            ));
        }
    }

    /**
     * Convert language code to hreflang format
     *
     * @param string $language_code Language code (e.g., 'es', 'en')
     * @return string Hreflang code (e.g., 'es', 'en', 'zh-CN')
     * @since 1.0.0
     */
    private function convert_language_to_hreflang($language_code) {
        // Special cases for hreflang that differ from locale
        $hreflang_map = array(
            'zh' => 'zh-CN',  // Chinese Simplified
            'pt' => 'pt-BR',  // Portuguese (Brazil is more common)
            'en' => 'en',     // English (generic)
            'es' => 'es',     // Spanish (generic)
            'fr' => 'fr',     // French (generic)
            'de' => 'de',     // German (generic)
            'it' => 'it',     // Italian (generic)
            'ja' => 'ja',     // Japanese
            'ko' => 'ko',     // Korean
            'ru' => 'ru',     // Russian
            'ar' => 'ar',     // Arabic
            'hi' => 'hi',     // Hindi
            'th' => 'th',     // Thai
            'vi' => 'vi',     // Vietnamese
            'tr' => 'tr',     // Turkish
            'pl' => 'pl',     // Polish
            'nl' => 'nl',     // Dutch
            'sv' => 'sv',     // Swedish
            'da' => 'da',     // Danish
            'no' => 'no',     // Norwegian
            'fi' => 'fi',     // Finnish
            'he' => 'he',     // Hebrew
            'fa' => 'fa',     // Persian
            'ur' => 'ur',     // Urdu
            'bn' => 'bn',     // Bengali
            'ta' => 'ta',     // Tamil
            'te' => 'te',     // Telugu
            'ml' => 'ml',     // Malayalam
            'kn' => 'kn',     // Kannada
            'gu' => 'gu',     // Gujarati
            'pa' => 'pa',     // Punjabi
            'mr' => 'mr',     // Marathi
            'ne' => 'ne',     // Nepali
            'si' => 'si',     // Sinhala
            'my' => 'my',     // Myanmar
            'km' => 'km',     // Khmer
            'lo' => 'lo',     // Lao
            'ka' => 'ka',     // Georgian
            'am' => 'am',     // Amharic
            'sw' => 'sw',     // Swahili
            'zu' => 'zu',     // Zulu
            'af' => 'af',     // Afrikaans
            'is' => 'is',     // Icelandic
            'mt' => 'mt',     // Maltese
            'ga' => 'ga',     // Irish
            'cy' => 'cy',     // Welsh
            'eu' => 'eu',     // Basque
            'ca' => 'ca',     // Catalan
            'gl' => 'gl',     // Galician
            'el' => 'el',     // Greek
            'mk' => 'mk',     // Macedonian
            'sq' => 'sq',     // Albanian
            'sr' => 'sr',     // Serbian
            'bs' => 'bs',     // Bosnian
            'me' => 'me',     // Montenegrin
            'uk' => 'uk',     // Ukrainian
            'cs' => 'cs',     // Czech
            'hu' => 'hu',     // Hungarian
            'ro' => 'ro',     // Romanian
            'bg' => 'bg',     // Bulgarian
            'hr' => 'hr',     // Croatian
            'sk' => 'sk',     // Slovak
            'sl' => 'sl',     // Slovenian
            'et' => 'et',     // Estonian
            'lv' => 'lv',     // Latvian
            'lt' => 'lt',     // Lithuanian
        );

        return isset($hreflang_map[$language_code]) ? $hreflang_map[$language_code] : $language_code;
    }

    /**
     * Get post excerpt for meta description
     *
     * @param WP_Post $post Post object
     * @return string Post excerpt
     * @since 1.0.0
     */
    private function get_post_excerpt($post) {
        // Use manual excerpt if available
        if (!empty($post->post_excerpt)) {
            return wp_strip_all_tags($post->post_excerpt);
        }

        // Generate excerpt from content
        $content = wp_strip_all_tags($post->post_content);
        $content = preg_replace('/\s+/', ' ', $content); // Normalize whitespace
        $content = trim($content);

        // Limit to 160 characters for meta description
        if (strlen($content) > 160) {
            $content = substr($content, 0, 157) . '...';
        }

        return $content;
    }

    /**
     * Generate JSON-LD structured data for articles
     *
     * @param WP_Post $post Post object
     * @param string $title Article title
     * @param string $description Article description
     * @param string $language Language code
     * @param string $url Article URL
     * @param array $language_site_metadata Language-specific site metadata
     * @since 1.0.0
     */
    private function generate_article_jsonld($post, $title, $description, $language, $url, $language_site_metadata = array()) {
        $author = get_userdata($post->post_author);
        $author_name = $author ? $author->display_name : 'Unknown';
        $author_url = $author ? get_author_posts_url($post->post_author) : '';

        $published_date = get_the_date('c', $post->ID);
        $modified_date = get_the_modified_date('c', $post->ID);

        $jsonld = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
            'description' => $description,
            'url' => $url,
            'inLanguage' => $language,
            'datePublished' => $published_date,
            'dateModified' => $modified_date,
            'author' => array(
                '@type' => 'Person',
                'name' => $author_name
            )
        );

        if (!empty($author_url)) {
            $jsonld['author']['url'] = $author_url;
        }

        // Add featured image if available
        if (has_post_thumbnail($post->ID)) {
            $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large');
            if ($thumbnail_url) {
                $jsonld['image'] = $thumbnail_url;
            }
        }

        // Add publisher information (use language-specific name if available)
        $site_name = !empty($language_site_metadata['site_name']) ? $language_site_metadata['site_name'] : get_bloginfo('name');
        if (!empty($site_name)) {
            $jsonld['publisher'] = array(
                '@type' => 'Organization',
                'name' => $site_name,
                'url' => home_url()
            );
        }

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo "\n" . '</script>' . "\n";
    }

    /**
     * Debug method to check post metadata status
     *
     * @param int $post_id Post ID to check
     * @return array Debug information
     * @since 1.0.0
     */
    public function debug_post_metadata($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array('error' => 'Post not found');
        }

        $debug_info = array(
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'is_singular' => is_singular(),
            'metadata' => array(
                '_ez_translate_language' => get_post_meta($post_id, '_ez_translate_language', true),
                '_ez_translate_group' => get_post_meta($post_id, '_ez_translate_group', true),
                '_ez_translate_is_landing' => get_post_meta($post_id, '_ez_translate_is_landing', true),
                '_ez_translate_seo_title' => get_post_meta($post_id, '_ez_translate_seo_title', true),
                '_ez_translate_seo_description' => get_post_meta($post_id, '_ez_translate_seo_description', true)
            ),
            'will_generate_metadata' => false,
            'will_generate_hreflang' => false
        );

        // Check if metadata will be generated
        $language = $debug_info['metadata']['_ez_translate_language'];
        $group = $debug_info['metadata']['_ez_translate_group'];

        $debug_info['will_generate_metadata'] = !empty($language);
        $debug_info['will_generate_hreflang'] = !empty($language) && !empty($group);

        if (!empty($group)) {
            $related_posts = \EZTranslate\PostMetaManager::get_posts_in_group($group);
            $debug_info['translation_group'] = array(
                'group_id' => $group,
                'total_posts' => count($related_posts),
                'post_ids' => $related_posts,
                'will_generate_hreflang' => count($related_posts) > 1
            );
        }

        return $debug_info;
    }

    /**
     * Detect if a post is part of a translation group even without explicit metadata
     *
     * This method searches for posts that reference this post as their original
     * or have similar content/title patterns that suggest they are translations.
     *
     * @param int $post_id Post ID to check
     * @return array|false Group information or false if not detected
     * @since 1.0.0
     */
    private function detect_translation_group_membership($post_id) {
        global $wpdb;

        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // Method 1: Check if any posts reference this post as their original
        $related_posts = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, pm1.meta_value as language, pm2.meta_value as group_id, pm3.meta_value as original_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_ez_translate_language'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_ez_translate_group'
            LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_ez_translate_original_id'
            WHERE (pm3.meta_value = %s OR p.post_parent = %s)
            AND p.post_status = 'publish'
            AND p.ID != %s
        ", $post_id, $post_id, $post_id));

        if (!empty($related_posts)) {
            // Found posts that reference this post as original
            $group_id = $related_posts[0]->group_id;
            $all_posts_in_group = \EZTranslate\PostMetaManager::get_posts_in_group($group_id);

            // Determine the language of the original post
            $original_language = $this->detect_original_language($post);

            return array(
                'language' => $original_language,
                'group_id' => $group_id,
                'role' => 'original', // This is the original post
                'is_landing' => false, // Original posts are typically not landing pages
                'total_in_group' => count($all_posts_in_group),
                'detection_method' => 'referenced_as_original'
            );
        }

        // Method 2: Check if this post has similar titles to posts with translation metadata
        $similar_posts = $this->find_posts_with_similar_titles($post);

        if (!empty($similar_posts)) {
            // Found posts with similar titles that have translation metadata
            $group_id = $similar_posts[0]->group_id;
            $all_posts_in_group = \EZTranslate\PostMetaManager::get_posts_in_group($group_id);

            // Determine language based on content or WordPress locale
            $detected_language = $this->detect_language_from_content($post);

            return array(
                'language' => $detected_language,
                'group_id' => $group_id,
                'role' => 'original', // Assume original if not explicitly marked as translation
                'is_landing' => false,
                'total_in_group' => count($all_posts_in_group),
                'detection_method' => 'similar_title'
            );
        }

        return false;
    }

    /**
     * Detect the original language of a post
     *
     * @param WP_Post $post Post object
     * @return string Language code
     * @since 1.0.0
     */
    private function detect_original_language($post) {
        // First try to detect from content
        $content_language = $this->detect_language_from_content($post);
        if ($content_language) {
            return $content_language;
        }

        // Get WordPress default language as fallback
        $wp_locale = get_locale();

        // If WordPress is in Spanish, assume original is Spanish
        if (strpos($wp_locale, 'es') === 0) {
            return 'es';
        }

        // If WordPress is in English, assume original is English
        if (strpos($wp_locale, 'en') === 0) {
            return 'en';
        }

        // Default to Spanish if we can't determine
        return 'es';
    }

    /**
     * Find posts with similar titles that have translation metadata
     *
     * @param WP_Post $post Post to find similar titles for
     * @return array Posts with similar titles
     * @since 1.0.0
     */
    private function find_posts_with_similar_titles($post) {
        global $wpdb;

        // Extract key words from title (remove common words)
        $title_words = $this->extract_key_words($post->post_title);

        if (empty($title_words)) {
            return array();
        }

        // Build prepared statements for each key word
        $results = array();

        foreach ($title_words as $word) {
            if (strlen($word) > 3) { // Only use words longer than 3 characters
                $like_value = '%' . $wpdb->esc_like($word) . '%';

                $word_results = $wpdb->get_results($wpdb->prepare("
                    SELECT p.ID, p.post_title, pm1.meta_value as language, pm2.meta_value as group_id
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_ez_translate_language'
                    INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_ez_translate_group'
                    WHERE p.post_title LIKE %s
                    AND p.post_status = 'publish'
                    AND p.ID != %s
                    AND p.post_type = %s
                    LIMIT 5
                ", $like_value, $post->ID, $post->post_type));

                if (!empty($word_results)) {
                    // Merge results, avoiding duplicates
                    foreach ($word_results as $result) {
                        $found = false;
                        foreach ($results as $existing) {
                            if ($existing->ID === $result->ID) {
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $results[] = $result;
                        }
                    }

                    // Stop if we have enough results
                    if (count($results) >= 5) {
                        break;
                    }
                }
            }
        }

        return array_slice($results, 0, 5);
    }

    /**
     * Extract key words from a title
     *
     * @param string $title Title to extract words from
     * @return array Key words
     * @since 1.0.0
     */
    private function extract_key_words($title) {
        // Common stop words in Spanish and English
        $stop_words = array(
            'el', 'la', 'de', 'que', 'y', 'a', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le', 'da', 'su', 'por', 'son', 'con', 'para', 'al', 'del', 'los', 'las',
            'the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i', 'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at', 'this', 'but', 'his', 'by', 'from'
        );

        // Clean and split title
        $title = strtolower($title);
        $title = preg_replace('/[^\w\s]/', ' ', $title); // Remove punctuation
        $words = preg_split('/\s+/', $title);

        // Filter out stop words and short words
        $key_words = array();
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) > 3 && !in_array($word, $stop_words)) {
                $key_words[] = $word;
            }
        }

        return $key_words;
    }

    /**
     * Detect language from post content
     *
     * @param WP_Post $post Post object
     * @return string Language code
     * @since 1.0.0
     */
    private function detect_language_from_content($post) {
        // Simple heuristic: check for common Spanish vs English words
        $content = strtolower($post->post_title . ' ' . $post->post_content);

        $spanish_indicators = array('el ', 'la ', 'de ', 'que ', 'y ', 'para ', 'con ', 'por ', 'en ', 'del ', 'los ', 'las ', 'una ', 'uno ');
        $english_indicators = array('the ', 'and ', 'for ', 'with ', 'this ', 'that ', 'from ', 'they ', 'have ', 'been ');

        $spanish_count = 0;
        $english_count = 0;

        foreach ($spanish_indicators as $indicator) {
            $spanish_count += substr_count($content, $indicator);
        }

        foreach ($english_indicators as $indicator) {
            $english_count += substr_count($content, $indicator);
        }

        // If more Spanish indicators, assume Spanish
        if ($spanish_count > $english_count) {
            return 'es';
        }

        // If more English indicators, assume English
        if ($english_count > $spanish_count) {
            return 'en';
        }

        // Default to WordPress locale if can't determine
        $wp_locale = get_locale();
        return strstr($wp_locale, '_', true) ?: 'es';
    }

    /**
     * Get the configured default language for x-default hreflang
     *
     * @return string Default language code
     * @since 1.0.0
     */
    private function get_default_language_for_hreflang() {
        // TODO: This should come from plugin settings when we implement MEJORA 1
        // For now, we'll use a temporary option or fallback to English

        $default_language = get_option('ez_translate_default_language', '');

        if (!empty($default_language)) {
            return $default_language;
        }

        // Fallback logic: prefer English if available, otherwise use WordPress locale
        $wp_locale = get_locale();

        if (strpos($wp_locale, 'en') === 0) {
            return 'en';
        }

        // Default to English as it's most universally understood
        return 'en';
    }

    /**
     * Check if a post is a landing page
     *
     * @param int $post_id Post ID
     * @return bool True if it's a landing page
     * @since 1.0.0
     */
    private function is_landing_page($post_id) {
        // Check if this is the main landing page
        $main_landing_page_id = get_option('ez_translate_main_landing_page_id', 0);
        if ($main_landing_page_id > 0 && $main_landing_page_id == $post_id) {
            return true;
        }

        // Load language manager to get languages configuration
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        $languages = \EZTranslate\LanguageManager::get_languages();

        // Check if this post ID is configured as a landing page for any language
        foreach ($languages as $language) {
            if (!empty($language['landing_page_id']) && $language['landing_page_id'] == $post_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a post is the homepage
     *
     * @param int $post_id Post ID
     * @return bool True if it's the homepage
     * @since 1.0.0
     */
    private function is_homepage($post_id) {
        // Check if this is the front page
        $front_page_id = get_option('page_on_front', 0);

        if ($front_page_id && $front_page_id == $post_id) {
            return true;
        }

        // Check if this is the blog page when front page shows posts
        if (get_option('show_on_front') === 'posts' && is_home()) {
            return true;
        }

        return false;
    }

    /**
     * Check if there are landing pages configured
     *
     * @return bool True if landing pages exist
     * @since 1.0.0
     */
    private function has_landing_pages() {
        // Check if main landing page is configured
        $main_landing_page_id = get_option('ez_translate_main_landing_page_id', 0);
        if ($main_landing_page_id > 0) {
            return true;
        }

        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        $languages = \EZTranslate\LanguageManager::get_languages();

        foreach ($languages as $language) {
            if (!empty($language['landing_page_id'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inject hreflang tags specifically for landing pages
     *
     * @param int $current_post_id Current landing page post ID
     * @since 1.0.0
     */
    private function inject_landing_page_hreflang_tags($current_post_id) {
        // Load language manager to get all languages
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        $languages = \EZTranslate\LanguageManager::get_languages();

        $hreflang_tags = array();
        $current_language = null;
        $default_language_post = null;
        $configured_default_language = $this->get_default_language_for_hreflang();

        // Get current page language
        // First check if this is the main landing page
        $main_landing_page_id = get_option('ez_translate_main_landing_page_id', 0);
        if ($main_landing_page_id > 0 && $main_landing_page_id == $current_post_id) {
            // This is the main landing page, use WordPress default language
            $wp_locale = get_locale();
            $current_language = strstr($wp_locale, '_', true) ?: $wp_locale;
        } else {
            // Check configured landing pages
            foreach ($languages as $language) {
                if (!empty($language['landing_page_id']) && $language['landing_page_id'] == $current_post_id) {
                    $current_language = $language['code'];
                    break;
                }
            }
        }

        // Get WordPress original language (from locale)
        $wp_locale = get_locale();
        $original_language = strstr($wp_locale, '_', true) ?: $wp_locale; // es_MX -> es
        $homepage_url = home_url('/');

        // Set x-default (for usability - usually English)
        $default_language_post = null;

        // Add main landing page if configured
        $main_landing_page_id = get_option('ez_translate_main_landing_page_id', 0);
        if ($main_landing_page_id > 0) {
            $main_post = get_post($main_landing_page_id);
            if ($main_post && $main_post->post_status === 'publish') {
                $wp_locale = get_locale();
                $main_language_code = strstr($wp_locale, '_', true) ?: $wp_locale;
                $url = get_permalink($main_post->ID);
                $hreflang_code = $this->convert_language_to_hreflang($main_language_code);

                $hreflang_tags[] = array(
                    'language' => $hreflang_code,
                    'url' => $url,
                    'post_id' => $main_post->ID,
                    'language_code' => $main_language_code
                );

                // Set as x-default if this is the configured default language
                if ($main_language_code === $configured_default_language) {
                    $default_language_post = array(
                        'url' => $url,
                        'language' => $main_language_code,
                        'post_id' => $main_post->ID
                    );
                }
            }
        }

        // Generate hreflang tags for all configured landing pages
        foreach ($languages as $language) {
            if (!empty($language['landing_page_id'])) {
                $post = get_post($language['landing_page_id']);

                if ($post && $post->post_status === 'publish') {
                    $url = get_permalink($post->ID);
                    $hreflang_code = $this->convert_language_to_hreflang($language['code']);

                    $hreflang_tags[] = array(
                        'language' => $hreflang_code,
                        'url' => $url,
                        'post_id' => $post->ID,
                        'language_code' => $language['code']
                    );

                    // Set as x-default if this is the configured default language
                    if ($language['code'] === $configured_default_language) {
                        $default_language_post = array(
                            'url' => $url,
                            'language' => $language['code'],
                            'post_id' => $post->ID
                        );
                    }
                }
            }
        }

        // Add homepage as the original site language (WordPress locale) only if not already included
        $original_language_hreflang = $this->convert_language_to_hreflang($original_language);
        $homepage_already_included = false;

        // Check if homepage is already included with the same language
        foreach ($hreflang_tags as $tag) {
            if ($tag['url'] === $homepage_url && $tag['language'] === $original_language_hreflang) {
                $homepage_already_included = true;
                break;
            }
        }

        // Also check if main landing page is the same as homepage
        $front_page_id = get_option('page_on_front', 0);
        if ($main_landing_page_id > 0 && $main_landing_page_id == $front_page_id) {
            $homepage_already_included = true; // Main landing page is the homepage
        }

        if (!$homepage_already_included) {
            $hreflang_tags[] = array(
                'language' => $original_language_hreflang,
                'url' => $homepage_url,
                'post_id' => $front_page_id,
                'language_code' => $original_language
            );
        }

        // Set x-default: prefer configured default language, fallback to original language
        if (!$default_language_post) {
            // If no landing page exists for configured default, use homepage
            $default_language_post = array(
                'url' => $homepage_url,
                'language' => $configured_default_language,
                'post_id' => get_option('page_on_front', 0)
            );
        }

        // Output hreflang tags
        if (!empty($hreflang_tags)) {
            echo "\n<!-- EZ Translate: Landing Page Hreflang Tags -->\n";

            // Sort tags to ensure consistent order (current language first, then alphabetical)
            $current_hreflang = $current_language ? $this->convert_language_to_hreflang($current_language) : '';
            $sorted_tags = array();
            $other_tags = array();

            foreach ($hreflang_tags as $tag) {
                if ($tag['language'] === $current_hreflang) {
                    $sorted_tags[] = $tag; // Current language first
                } else {
                    $other_tags[] = $tag;
                }
            }

            // Sort other tags alphabetically
            usort($other_tags, function($a, $b) {
                return strcmp($a['language'], $b['language']);
            });

            $all_tags = array_merge($sorted_tags, $other_tags);

            // Output all language-specific hreflang tags (including self-reference)
            foreach ($all_tags as $tag) {
                echo '<link rel="alternate" hreflang="' . esc_attr($tag['language']) . '" href="' . esc_url($tag['url']) . '">' . "\n";
            }

            // Output x-default tag (points to configured default language)
            if ($default_language_post) {
                echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($default_language_post['url']) . '">' . "\n";
            }

            echo '<!-- /EZ Translate: Landing Page Hreflang Tags -->' . "\n\n";

            Logger::info('Frontend: Landing page hreflang tags injected', array(
                'post_id' => $current_post_id,
                'current_language' => $current_language,
                'original_language' => $original_language,
                'tags_count' => count($hreflang_tags),
                'languages' => array_column($hreflang_tags, 'language'),
                'configured_default' => $configured_default_language,
                'x_default_language' => $default_language_post ? $default_language_post['language'] : 'none',
                'includes_homepage' => true,
                'includes_x_default' => !empty($default_language_post)
            ));
        }
    }

    /**
     * Inject language detector configuration into head
     *
     * @since 1.0.0
     */
    public function inject_language_detector_config() {
        // Only inject on frontend pages
        if (is_admin()) {
            return;
        }

        // Load language detector class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-detector.php';

        // Get detector configuration
        $config = \EZTranslate\LanguageDetector::get_detector_config();

        // Skip if detector is disabled
        if (!$config['enabled']) {
            return;
        }

        // Get current post ID
        $post_id = get_queried_object_id();
        $current_language = null;

        if (!empty($post_id)) {
            $current_language = \EZTranslate\LanguageDetector::get_page_language($post_id);
        } else {
            // Fallback to WordPress locale
            $wp_locale = get_locale();
            $current_language = substr($wp_locale, 0, 2);
        }

        // Get available languages
        $languages = \EZTranslate\LanguageDetector::get_available_languages();

        // Prepare configuration for JavaScript
        $js_config = array(
            'enabled' => $config['enabled'],
            'currentLanguage' => $current_language,
            'availableLanguages' => $languages,
            'config' => $config,
            'postId' => $post_id,
            'restUrl' => rest_url('ez-translate/v1/'),
            'homeUrl' => home_url('/'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ez_translate_detector')
        );

        echo "\n<!-- EZ Translate: Language Detector Configuration -->\n";
        echo '<script type="text/javascript">';
        echo 'window.ezTranslateDetector = ' . wp_json_encode($js_config) . ';';
        echo '</script>';
        echo "\n<!-- /EZ Translate: Language Detector Configuration -->\n";

        Logger::debug('Language detector configuration injected', array(
            'post_id' => $post_id,
            'current_language' => $current_language,
            'enabled' => $config['enabled']
        ));
    }

    /**
     * Enqueue language detector assets
     *
     * @since 1.0.0
     */
    public function enqueue_language_detector_assets() {
        // Only enqueue on frontend pages
        if (is_admin()) {
            return;
        }

        // Load language detector class
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-detector.php';

        // Get detector configuration
        $config = \EZTranslate\LanguageDetector::get_detector_config();

        // Skip if detector is disabled
        if (!$config['enabled']) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'ez-translate-language-detector',
            EZ_TRANSLATE_PLUGIN_URL . 'assets/css/language-detector.css',
            array(),
            EZ_TRANSLATE_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'ez-translate-language-detector',
            EZ_TRANSLATE_PLUGIN_URL . 'assets/js/language-detector.js',
            array(),
            EZ_TRANSLATE_VERSION,
            true
        );

        Logger::debug('Language detector assets enqueued');
    }

    /**
     * Add language dataset attributes to body
     *
     * @param array $classes Body classes
     * @return array Modified body classes
     * @since 1.0.0
     */
    public function add_language_body_attributes($classes) {
        // Only on frontend pages
        if (is_admin()) {
            return $classes;
        }

        // Get current post ID
        $post_id = get_queried_object_id();

        if (!empty($post_id)) {
            // Load language detector class
            require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-detector.php';

            $current_language = \EZTranslate\LanguageDetector::get_page_language($post_id);

            if (!empty($current_language)) {
                // Add language class to body
                $classes[] = 'ez-translate-lang-' . $current_language;

                // Add dataset attribute via JavaScript (since we can't modify body attributes directly)
                add_action('wp_footer', function() use ($current_language, $post_id) {
                    echo '<script type="text/javascript">';
                    echo 'document.body.setAttribute("data-ez-current-language", "' . esc_js($current_language) . '");';
                    echo 'document.body.setAttribute("data-ez-post-id", "' . esc_js($post_id) . '");';
                    echo '</script>';
                }, 1);
            }
        }

        return $classes;
    }
}
