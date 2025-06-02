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
     * Test mode flag - bypasses WordPress conditional checks for testing
     *
     * @var bool
     * @since 1.0.0
     */
    private $test_mode = false;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Enable test mode (for unit testing)
     *
     * @since 1.0.0
     */
    public function enable_test_mode() {
        $this->test_mode = true;
    }

    /**
     * Initialize frontend functionality
     *
     * @since 1.0.0
     */
    private function init() {
        // Hook into wp_head for metadata injection
        add_action('wp_head', array($this, 'inject_seo_metadata'), 1);
        
        // Hook into document title filter for landing pages
        add_filter('document_title_parts', array($this, 'filter_document_title'), 10, 1);
        
        // Hook into meta description for landing pages
        add_action('wp_head', array($this, 'inject_meta_description'), 2);

        Logger::debug('Frontend hooks initialized');
    }

    /**
     * Inject SEO metadata for landing pages
     *
     * @since 1.0.0
     */
    public function inject_seo_metadata() {
        global $post;

        // Only process on singular pages (skip check in test mode)
        if (!$this->test_mode && (!is_singular() || !$post)) {
            return;
        }

        // In test mode, ensure we have a post
        if ($this->test_mode && !$post) {
            return;
        }

        // Check if this is a landing page
        $is_landing = get_post_meta($post->ID, '_ez_translate_is_landing', true);
        
        if (!$is_landing) {
            Logger::debug('Frontend: Not a landing page, skipping SEO injection', array(
                'post_id' => $post->ID,
                'post_title' => $post->post_title
            ));
            return;
        }

        // Get SEO metadata
        $seo_title = get_post_meta($post->ID, '_ez_translate_seo_title', true);
        $seo_description = get_post_meta($post->ID, '_ez_translate_seo_description', true);
        $language = get_post_meta($post->ID, '_ez_translate_language', true);

        Logger::info('Frontend: Injecting SEO metadata for landing page', array(
            'post_id' => $post->ID,
            'language' => $language,
            'has_seo_title' => !empty($seo_title),
            'has_seo_description' => !empty($seo_description)
        ));

        // Inject Open Graph metadata
        $this->inject_open_graph_metadata($seo_title, $seo_description, $language);

        // Inject Twitter Card metadata
        $this->inject_twitter_card_metadata($seo_title, $seo_description);

        // Inject JSON-LD structured data
        $this->inject_json_ld_metadata($seo_title, $seo_description, $language);
    }

    /**
     * Filter document title for landing pages
     *
     * @param array $title_parts The document title parts
     * @return array Modified title parts
     * @since 1.0.0
     */
    public function filter_document_title($title_parts) {
        global $post;

        // Only process on singular pages (skip check in test mode)
        if (!$this->test_mode && (!is_singular() || !$post)) {
            return $title_parts;
        }

        // In test mode, ensure we have a post
        if ($this->test_mode && !$post) {
            return $title_parts;
        }

        // Check if this is a landing page with custom SEO title
        $is_landing = get_post_meta($post->ID, '_ez_translate_is_landing', true);
        $seo_title = get_post_meta($post->ID, '_ez_translate_seo_title', true);

        if ($is_landing && !empty($seo_title)) {
            $title_parts['title'] = sanitize_text_field($seo_title);
            
            Logger::debug('Frontend: Document title overridden for landing page', array(
                'post_id' => $post->ID,
                'original_title' => $post->post_title,
                'seo_title' => $seo_title
            ));
        }

        return $title_parts;
    }

    /**
     * Inject meta description for landing pages
     *
     * @since 1.0.0
     */
    public function inject_meta_description() {
        global $post;

        // Only process on singular pages (skip check in test mode)
        if (!$this->test_mode && (!is_singular() || !$post)) {
            return;
        }

        // In test mode, ensure we have a post
        if ($this->test_mode && !$post) {
            return;
        }

        // Check if this is a landing page with custom SEO description
        $is_landing = get_post_meta($post->ID, '_ez_translate_is_landing', true);
        $seo_description = get_post_meta($post->ID, '_ez_translate_seo_description', true);

        if ($is_landing && !empty($seo_description)) {
            $clean_description = sanitize_text_field($seo_description);
            echo '<meta name="description" content="' . esc_attr($clean_description) . '">' . "\n";
            
            Logger::debug('Frontend: Meta description injected for landing page', array(
                'post_id' => $post->ID,
                'description_length' => strlen($clean_description)
            ));
        }
    }

    /**
     * Inject Open Graph metadata
     *
     * @param string $title SEO title
     * @param string $description SEO description
     * @param string $language Language code
     * @since 1.0.0
     */
    private function inject_open_graph_metadata($title, $description, $language) {
        global $post;

        if (!empty($title)) {
            echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        }

        if (!empty($description)) {
            echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        }

        // Always include basic Open Graph data
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink($post->ID)) . '">' . "\n";

        if (!empty($language)) {
            echo '<meta property="og:locale" content="' . esc_attr($this->convert_language_to_locale($language)) . '">' . "\n";
        }

        // Include featured image if available
        if (has_post_thumbnail($post->ID)) {
            $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large');
            if ($thumbnail_url) {
                echo '<meta property="og:image" content="' . esc_url($thumbnail_url) . '">' . "\n";
            }
        }

        Logger::debug('Frontend: Open Graph metadata injected', array(
            'post_id' => $post->ID,
            'language' => $language
        ));
    }

    /**
     * Inject Twitter Card metadata
     *
     * @param string $title SEO title
     * @param string $description SEO description
     * @since 1.0.0
     */
    private function inject_twitter_card_metadata($title, $description) {
        global $post;

        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

        if (!empty($title)) {
            echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        }

        if (!empty($description)) {
            echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        }

        // Include featured image if available
        if (has_post_thumbnail($post->ID)) {
            $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large');
            if ($thumbnail_url) {
                echo '<meta name="twitter:image" content="' . esc_url($thumbnail_url) . '">' . "\n";
            }
        }

        Logger::debug('Frontend: Twitter Card metadata injected', array(
            'post_id' => $post->ID
        ));
    }

    /**
     * Inject JSON-LD structured data
     *
     * @param string $title SEO title
     * @param string $description SEO description
     * @param string $language Language code
     * @since 1.0.0
     */
    private function inject_json_ld_metadata($title, $description, $language) {
        global $post;

        $json_ld = array(
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'url' => get_permalink($post->ID),
            'name' => !empty($title) ? $title : get_the_title($post->ID),
            'description' => !empty($description) ? $description : get_the_excerpt($post->ID),
            'inLanguage' => !empty($language) ? $language : get_locale(),
            'datePublished' => get_the_date('c', $post->ID),
            'dateModified' => get_the_modified_date('c', $post->ID),
        );

        // Add author information
        $author_id = $post->post_author;
        if ($author_id) {
            $json_ld['author'] = array(
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $author_id),
                'url' => get_author_posts_url($author_id)
            );
        }

        // Add featured image if available
        if (has_post_thumbnail($post->ID)) {
            $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large');
            if ($thumbnail_url) {
                $json_ld['image'] = $thumbnail_url;
            }
        }

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($json_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        echo '</script>' . "\n";

        Logger::debug('Frontend: JSON-LD structured data injected', array(
            'post_id' => $post->ID,
            'language' => $language
        ));
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
            'pt' => 'pt_PT',
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
}
