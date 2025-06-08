<?php
/**
 * Sitemap Cache Manager for EZ Translate
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
 * Sitemap Cache Manager class
 *
 * Handles caching of generated sitemaps to improve performance
 *
 * @since 1.0.0
 */
class SitemapCache {

    /**
     * Cache directory path
     *
     * @var string
     * @since 1.0.0
     */
    private static $cache_dir = '';

    /**
     * Cache duration in seconds
     *
     * @var int
     * @since 1.0.0
     */
    private static $cache_duration = 86400; // 24 hours

    /**
     * Initialize cache system
     *
     * @since 1.0.0
     */
    public static function init() {
        self::$cache_dir = self::get_cache_directory();
        self::$cache_duration = self::get_cache_duration();
        
        // Create cache directory if it doesn't exist
        self::ensure_cache_directory();
        
        Logger::debug('SitemapCache initialized', array(
            'cache_dir' => self::$cache_dir,
            'cache_duration' => self::$cache_duration
        ));
    }

    /**
     * Get cache directory path
     *
     * @return string
     * @since 1.0.0
     */
    private static function get_cache_directory() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/ez-translate/sitemaps/';
    }

    /**
     * Get cache duration from settings
     *
     * @return int
     * @since 1.0.0
     */
    private static function get_cache_duration() {
        $settings = get_option('ez_translate_sitemap_settings', array());
        return isset($settings['cache_duration']) ? (int) $settings['cache_duration'] : 86400;
    }

    /**
     * Ensure cache directory exists
     *
     * @since 1.0.0
     */
    private static function ensure_cache_directory() {
        if (!file_exists(self::$cache_dir)) {
            wp_mkdir_p(self::$cache_dir);
            
            // Create .htaccess to protect cache directory
            $htaccess_content = "# EZ Translate Sitemap Cache\n";
            $htaccess_content .= "<Files \"*.xml\">\n";
            $htaccess_content .= "    Header set Content-Type \"application/xml; charset=UTF-8\"\n";
            $htaccess_content .= "    Header set Cache-Control \"max-age=3600\"\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents(self::$cache_dir . '.htaccess', $htaccess_content);
            
            Logger::info('Sitemap cache directory created', array('path' => self::$cache_dir));
        }
    }

    /**
     * Get cache file path for sitemap
     *
     * @param string $type Sitemap type
     * @param string $language Language code (optional)
     * @return string
     * @since 1.0.0
     */
    private static function get_cache_file_path($type, $language = '') {
        $filename = 'sitemap-' . $type;
        
        if (!empty($language)) {
            $filename .= '-' . $language;
        }
        
        $filename .= '.xml';
        
        return self::$cache_dir . $filename;
    }

    /**
     * Check if cached sitemap exists and is valid
     *
     * @param string $type Sitemap type
     * @param string $language Language code (optional)
     * @return bool
     * @since 1.0.0
     */
    public static function is_cached($type, $language = '') {
        if (empty(self::$cache_dir)) {
            self::init();
        }
        
        $cache_file = self::get_cache_file_path($type, $language);
        
        if (!file_exists($cache_file)) {
            return false;
        }
        
        $file_time = filemtime($cache_file);
        $current_time = time();
        
        $is_valid = ($current_time - $file_time) < self::$cache_duration;
        
        Logger::debug('Cache check', array(
            'type' => $type,
            'language' => $language,
            'file_exists' => true,
            'file_age' => $current_time - $file_time,
            'is_valid' => $is_valid
        ));
        
        return $is_valid;
    }

    /**
     * Get cached sitemap content
     *
     * @param string $type Sitemap type
     * @param string $language Language code (optional)
     * @return string|false
     * @since 1.0.0
     */
    public static function get_cached($type, $language = '') {
        if (!self::is_cached($type, $language)) {
            return false;
        }
        
        $cache_file = self::get_cache_file_path($type, $language);
        $content = file_get_contents($cache_file);
        
        if ($content !== false) {
            Logger::info('Sitemap served from cache', array(
                'type' => $type,
                'language' => $language,
                'size' => strlen($content)
            ));
        }
        
        return $content;
    }

    /**
     * Cache sitemap content
     *
     * @param string $type Sitemap type
     * @param string $content XML content
     * @param string $language Language code (optional)
     * @return bool
     * @since 1.0.0
     */
    public static function cache_sitemap($type, $content, $language = '') {
        if (empty(self::$cache_dir)) {
            self::init();
        }
        
        $cache_file = self::get_cache_file_path($type, $language);
        
        $result = file_put_contents($cache_file, $content);
        
        if ($result !== false) {
            Logger::info('Sitemap cached successfully', array(
                'type' => $type,
                'language' => $language,
                'file' => $cache_file,
                'size' => strlen($content)
            ));
            return true;
        } else {
            Logger::error('Failed to cache sitemap', array(
                'type' => $type,
                'language' => $language,
                'file' => $cache_file
            ));
            return false;
        }
    }

    /**
     * Invalidate cache for specific sitemap type
     *
     * @param string $type Sitemap type ('all', 'posts', 'pages', 'taxonomies', 'index')
     * @param string $language Language code (optional, 'all' for all languages)
     * @since 1.0.0
     */
    public static function invalidate($type = 'all', $language = '') {
        if (empty(self::$cache_dir)) {
            self::init();
        }
        
        $files_deleted = 0;
        
        if ($type === 'all') {
            // Delete all cache files
            $pattern = self::$cache_dir . 'sitemap-*.xml';
            $files = glob($pattern);
            
            foreach ($files as $file) {
                if (wp_delete_file($file)) {
                    $files_deleted++;
                }
            }
        } else {
            if ($language === 'all') {
                // Delete all files for this type across all languages
                $pattern = self::$cache_dir . 'sitemap-' . $type . '*.xml';
                $files = glob($pattern);
                
                foreach ($files as $file) {
                    if (wp_delete_file($file)) {
                        $files_deleted++;
                    }
                }
            } else {
                // Delete specific file
                $cache_file = self::get_cache_file_path($type, $language);
                if (file_exists($cache_file) && wp_delete_file($cache_file)) {
                    $files_deleted++;
                }
            }
        }
        
        Logger::info('Sitemap cache invalidated', array(
            'type' => $type,
            'language' => $language,
            'files_deleted' => $files_deleted
        ));
        
        return $files_deleted;
    }

    /**
     * Clean up old cache files
     *
     * @since 1.0.0
     */
    public static function cleanup_old_files() {
        if (empty(self::$cache_dir)) {
            self::init();
        }
        
        $pattern = self::$cache_dir . 'sitemap-*.xml';
        $files = glob($pattern);
        $current_time = time();
        $files_deleted = 0;
        
        foreach ($files as $file) {
            $file_time = filemtime($file);
            
            // Delete files older than cache duration
            if (($current_time - $file_time) > self::$cache_duration) {
                if (wp_delete_file($file)) {
                    $files_deleted++;
                }
            }
        }
        
        Logger::info('Old cache files cleaned up', array(
            'files_deleted' => $files_deleted
        ));
        
        return $files_deleted;
    }

    /**
     * Get cache statistics
     *
     * @return array
     * @since 1.0.0
     */
    public static function get_cache_stats() {
        if (empty(self::$cache_dir)) {
            self::init();
        }
        
        $pattern = self::$cache_dir . 'sitemap-*.xml';
        $files = glob($pattern);
        $current_time = time();
        
        $stats = array(
            'total_files' => 0,
            'valid_files' => 0,
            'expired_files' => 0,
            'total_size' => 0,
            'cache_dir' => self::$cache_dir,
            'cache_duration' => self::$cache_duration
        );
        
        foreach ($files as $file) {
            $stats['total_files']++;
            $file_time = filemtime($file);
            $file_size = filesize($file);
            $stats['total_size'] += $file_size;
            
            if (($current_time - $file_time) < self::$cache_duration) {
                $stats['valid_files']++;
            } else {
                $stats['expired_files']++;
            }
        }
        
        return $stats;
    }

    /**
     * Get cache directory URL
     *
     * @return string
     * @since 1.0.0
     */
    public static function get_cache_url() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/ez-translate/sitemaps/';
    }
}
