<?php
/**
 * Language Detector class for EZ Translate
 *
 * Handles browser language detection and intelligent redirection
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
 * Language Detector class
 *
 * @since 1.0.0
 */
class LanguageDetector {

    /**
     * Option name for storing detector settings
     *
     * @var string
     * @since 1.0.0
     */
    const OPTION_NAME = 'ez_translate_detector_settings';

    /**
     * Get page language from post ID
     *
     * @param int $post_id Post ID
     * @return string|null Language code or null if not found
     * @since 1.0.0
     */
    public static function get_page_language($post_id) {
        if (empty($post_id)) {
            return null;
        }

        // First try to get from post meta
        $language = get_post_meta($post_id, '_ez_translate_language', true);
        
        if (!empty($language)) {
            Logger::debug('Page language found in meta', array(
                'post_id' => $post_id,
                'language' => $language
            ));
            return $language;
        }

        // Fallback: try to detect from WordPress locale
        $wp_locale = get_locale();
        $default_language = substr($wp_locale, 0, 2); // es_ES -> es
        
        Logger::debug('Page language detected from WordPress locale', array(
            'post_id' => $post_id,
            'wp_locale' => $wp_locale,
            'detected_language' => $default_language
        ));
        
        return $default_language;
    }

    /**
     * Find translation of a post in target language
     *
     * @param int $post_id Original post ID
     * @param string $target_language Target language code
     * @return int|null Translation post ID or null if not found
     * @since 1.0.0
     */
    public static function find_translation($post_id, $target_language) {
        if (empty($post_id) || empty($target_language)) {
            return null;
        }

        // Get the translation group ID
        $group_id = get_post_meta($post_id, '_ez_translate_group', true);
        
        if (empty($group_id)) {
            Logger::debug('No translation group found for post', array(
                'post_id' => $post_id
            ));
            return null;
        }

        // Find posts in the same group with target language
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-post-meta-manager.php';
        $posts_in_group = \EZTranslate\PostMetaManager::get_posts_in_group($group_id);

        foreach ($posts_in_group as $post) {
            $post_language = get_post_meta($post->ID, '_ez_translate_language', true);
            if ($post_language === $target_language) {
                Logger::debug('Translation found', array(
                    'original_post_id' => $post_id,
                    'translation_post_id' => $post->ID,
                    'target_language' => $target_language,
                    'group_id' => $group_id
                ));
                return $post->ID;
            }
        }

        Logger::debug('No translation found in target language', array(
            'post_id' => $post_id,
            'target_language' => $target_language,
            'group_id' => $group_id,
            'posts_in_group' => count($posts_in_group)
        ));

        return null;
    }

    /**
     * Get landing page for a specific language
     *
     * @param string $language_code Language code
     * @return int|null Landing page ID or null if not found
     * @since 1.0.0
     */
    public static function get_landing_page($language_code) {
        if (empty($language_code)) {
            return null;
        }

        // Special case for Spanish - redirect to main landing page
        if ($language_code === 'es') {
            $main_landing_page = get_option('ez_translate_main_landing_page');
            if (!empty($main_landing_page)) {
                Logger::debug('Spanish language redirecting to main landing page', array(
                    'language_code' => $language_code,
                    'landing_page_id' => $main_landing_page
                ));
                return $main_landing_page;
            }
        }

        // Get language configuration
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        $language = \EZTranslate\LanguageManager::get_language($language_code);
        
        if (!empty($language) && isset($language['landing_page_id'])) {
            Logger::debug('Landing page found for language', array(
                'language_code' => $language_code,
                'landing_page_id' => $language['landing_page_id']
            ));
            return $language['landing_page_id'];
        }

        Logger::debug('No landing page found for language', array(
            'language_code' => $language_code
        ));

        return null;
    }

    /**
     * Get detector configuration
     *
     * @return array Detector configuration
     * @since 1.0.0
     */
    public static function get_detector_config() {
        $default_config = array(
            'enabled' => true,
            'auto_redirect' => false,
            'show_helper' => true,
            'position' => 'bottom-right',
            'delay' => 2000,
            'restrict_navigation' => true,
            'messages' => array(
                'es' => array(
                    'title' => 'Escoge tu edición favorita',
                    'description' => 'Siempre que entres se cargará esta edición',
                    'confirm_button' => 'Confirmar',
                    'stay_button' => 'Mantener actual',
                    'free_navigation' => 'Navegar libremente',
                    'dropdown_title' => 'Idiomas disponibles',
                    'translation_available' => 'Tenemos esta versión en',
                    'landing_available' => 'Tenemos página de inicio en',
                    'current_language' => 'Idioma actual',
                    'translation_label' => 'Traducción',
                    'landing_label' => 'Página de inicio'
                ),
                'en' => array(
                    'title' => 'Choose your favorite edition',
                    'description' => 'This edition will always load when you visit',
                    'confirm_button' => 'Confirm',
                    'stay_button' => 'Keep current',
                    'free_navigation' => 'Browse freely',
                    'dropdown_title' => 'Available languages',
                    'translation_available' => 'We have this version in',
                    'landing_available' => 'We have homepage in',
                    'current_language' => 'Current language',
                    'translation_label' => 'Translation',
                    'landing_label' => 'Homepage'
                ),
                'pt' => array(
                    'title' => 'Escolha sua edição favorita',
                    'description' => 'Esta edição sempre carregará quando você visitar',
                    'confirm_button' => 'Confirmar',
                    'stay_button' => 'Manter atual',
                    'free_navigation' => 'Navegar livremente',
                    'dropdown_title' => 'Idiomas disponíveis',
                    'translation_available' => 'Temos esta versão em',
                    'landing_available' => 'Temos página inicial em',
                    'current_language' => 'Idioma atual',
                    'translation_label' => 'Tradução',
                    'landing_label' => 'Página inicial'
                ),
                'fr' => array(
                    'title' => 'Choisissez votre édition préférée',
                    'description' => 'Cette édition se chargera toujours lors de votre visite',
                    'confirm_button' => 'Confirmer',
                    'stay_button' => 'Garder actuel',
                    'free_navigation' => 'Naviguer librement',
                    'dropdown_title' => 'Langues disponibles',
                    'translation_available' => 'Nous avons cette version en',
                    'landing_available' => 'Nous avons page d\'accueil en',
                    'current_language' => 'Langue actuelle',
                    'translation_label' => 'Traduction',
                    'landing_label' => 'Page d\'accueil'
                ),
                'default' => array(
                    'title' => 'Choose your favorite edition',
                    'description' => 'This edition will always load when you visit',
                    'confirm_button' => 'Confirm',
                    'stay_button' => 'Keep current',
                    'free_navigation' => 'Browse freely',
                    'dropdown_title' => 'Available languages',
                    'translation_available' => 'We have this version in',
                    'landing_available' => 'We have homepage in',
                    'current_language' => 'Current language',
                    'translation_label' => 'Translation',
                    'landing_label' => 'Homepage'
                )
            )
        );

        $saved_config = get_option(self::OPTION_NAME, array());
        $config = wp_parse_args($saved_config, $default_config);

        Logger::debug('Detector configuration retrieved', array(
            'enabled' => $config['enabled'],
            'auto_redirect' => $config['auto_redirect'],
            'position' => $config['position']
        ));

        return $config;
    }

    /**
     * Update detector configuration
     *
     * @param array $config Configuration array
     * @return bool True on success, false on failure
     * @since 1.0.0
     */
    public static function update_detector_config($config) {
        $current_config = self::get_detector_config();
        $updated_config = wp_parse_args($config, $current_config);

        $result = update_option(self::OPTION_NAME, $updated_config);

        if ($result) {
            Logger::info('Detector configuration updated', array(
                'config' => $updated_config
            ));
        } else {
            Logger::error('Failed to update detector configuration', array(
                'config' => $config
            ));
        }

        return $result;
    }

    /**
     * Get available languages for detector
     *
     * @return array Array of available languages including site default with landing page info
     * @since 1.0.0
     */
    public static function get_available_languages() {
        require_once EZ_TRANSLATE_PLUGIN_DIR . 'includes/class-ez-translate-language-manager.php';
        $languages = \EZTranslate\LanguageManager::get_enabled_languages();

        // Add site default language (extracted from WordPress locale)
        $wp_locale = get_locale();
        $default_language_code = substr($wp_locale, 0, 2);

        // Check if default language is already in the list
        $default_exists = false;
        foreach ($languages as &$language) {
            if ($language['code'] === $default_language_code) {
                $default_exists = true;
                $language['is_default'] = true;
            }

            // Add landing page info to each language
            if ($language['code'] === 'es') {
                // Spanish uses main landing page
                $language['landing_page_id'] = get_option('ez_translate_main_landing_page');
            } else {
                // Other languages use their configured landing page
                $language['landing_page_id'] = isset($language['landing_page_id']) ? $language['landing_page_id'] : null;
            }
        }

        // Add default language if not exists
        if (!$default_exists) {
            $landing_page_id = null;
            if ($default_language_code === 'es') {
                $landing_page_id = get_option('ez_translate_main_landing_page');
            }

            $languages[] = array(
                'code' => $default_language_code,
                'name' => self::get_language_name_from_code($default_language_code),
                'native_name' => self::get_language_name_from_code($default_language_code),
                'flag' => self::get_flag_from_code($default_language_code),
                'enabled' => true,
                'is_default' => true,
                'landing_page_id' => $landing_page_id
            );
        }

        Logger::debug('Available languages for detector', array(
            'count' => count($languages),
            'default_language' => $default_language_code
        ));

        return $languages;
    }

    /**
     * Get language name from code
     *
     * @param string $code Language code
     * @return string Language name
     * @since 1.0.0
     */
    private static function get_language_name_from_code($code) {
        $language_names = array(
            'es' => 'Español',
            'en' => 'English',
            'pt' => 'Português',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'ja' => '日本語',
            'ko' => '한국어',
            'zh' => '中文',
            'ru' => 'Русский'
        );

        return isset($language_names[$code]) ? $language_names[$code] : ucfirst($code);
    }

    /**
     * Get flag emoji from language code
     *
     * @param string $code Language code
     * @return string Flag emoji
     * @since 1.0.0
     */
    private static function get_flag_from_code($code) {
        $flags = array(
            'es' => '🇪🇸',
            'en' => '🇺🇸',
            'pt' => '🇵🇹',
            'fr' => '🇫🇷',
            'de' => '🇩🇪',
            'it' => '🇮🇹',
            'ja' => '🇯🇵',
            'ko' => '🇰🇷',
            'zh' => '🇨🇳',
            'ru' => '🇷🇺'
        );

        return isset($flags[$code]) ? $flags[$code] : '🌐';
    }

    /**
     * Clears any caches used by the Language Detector.
     *
     * Note: Currently, this method serves as a placeholder as the detector
     * primarily relies on WordPress's object cache for options, which is
     * handled by update_option(). If specific caching mechanisms are added
     * to this class in the future, this method should be updated.
     *
     * @since 1.0.0
     * @return void
     */
    public static function clear_cache() {
//         Logger::info('LanguageDetector::clear_cache() called. No specific custom cache to clear at this time.');
        // Future cache clearing logic would go here, e.g.:
        // delete_transient('ez_translate_detector_some_cache_key');
    }

}
