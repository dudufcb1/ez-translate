<?php
/**
 * Dashboard Widget Admin Class
 *
 * @package EZTranslate
 * @subpackage Admin
 * @since 1.0.0
 */

namespace EZTranslate\Admin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard Widget Admin Class
 *
 * @since 1.0.0
 */
class DashboardWidget {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }

    /**
     * Add dashboard widget
     *
     * @since 1.0.0
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'ez_translate_dashboard_widget',
            '🌍 EZ Translate - Multilingual Status',
            array($this, 'render_dashboard_widget')
        );
    }

    /**
     * Render dashboard widget
     *
     * @since 1.0.0
     */
    public function render_dashboard_widget() {
        // Get translation statistics
        $stats = $this->get_translation_stats();
        
        ?>
        <div style="padding: 10px;">
            <!-- Stats Overview -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div style="text-align: center; padding: 15px; background: #f0f6ff; border-radius: 8px; border-left: 4px solid #0073aa;">
                    <div style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo esc_html($stats['languages']); ?></div>
                    <div style="font-size: 12px; color: #666;"><?php esc_html_e('Languages', 'ez-translate'); ?></div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: #f0fff4; border-radius: 8px; border-left: 4px solid #00a32a;">
                    <div style="font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo esc_html($stats['translated_posts']); ?></div>
                    <div style="font-size: 12px; color: #666;"><?php esc_html_e('Translated Posts', 'ez-translate'); ?></div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: #fff8e1; border-radius: 8px; border-left: 4px solid #ffb900;">
                    <div style="font-size: 24px; font-weight: bold; color: #ffb900;"><?php echo esc_html($stats['pending']); ?></div>
                    <div style="font-size: 12px; color: #666;"><?php esc_html_e('Pending', 'ez-translate'); ?></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0;"><?php esc_html_e('Quick Actions', 'ez-translate'); ?></h4>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ez-translate')); ?>" class="button button-primary button-small">
                        <?php esc_html_e('Manage Languages', 'ez-translate'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ez-translate-seo-metadata')); ?>" class="button button-secondary button-small">
                        <?php esc_html_e('SEO Settings', 'ez-translate'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('edit.php')); ?>" class="button button-secondary button-small">
                        <?php esc_html_e('Translate Content', 'ez-translate'); ?>
                    </a>
                </div>
            </div>

            <!-- Professional Services CTA -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 15px;">
                <h4 style="margin: 0 0 10px 0; color: white;"><?php esc_html_e('🚀 Need Professional Development?', 'ez-translate'); ?></h4>
                <p style="margin: 0 0 15px 0; font-size: 14px; opacity: 0.9;">
                    <?php esc_html_e('WP themes & plugins, Laravel, React, Vue.js, Python, AI integration!', 'ez-translate'); ?>
                </p>
                <a href="https://especialistaenwp.com" target="_blank" style="background: white; color: #667eea; padding: 8px 16px; text-decoration: none; border-radius: 20px; font-weight: bold; font-size: 13px;">
                    <?php esc_html_e('Visit EspecialistaEnWP.com', 'ez-translate'); ?>
                </a>
            </div>

            <!-- Services Grid -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; font-size: 11px;">
                <div style="text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="font-size: 14px; margin-bottom: 3px;">🎨</div>
                    <strong><?php esc_html_e('FSE Themes', 'ez-translate'); ?></strong>
                </div>
                <div style="text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="font-size: 14px; margin-bottom: 3px;">🏛️</div>
                    <strong><?php esc_html_e('Classic Themes', 'ez-translate'); ?></strong>
                </div>
                <div style="text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="font-size: 14px; margin-bottom: 3px;">🔧</div>
                    <strong><?php esc_html_e('WP Plugins', 'ez-translate'); ?></strong>
                </div>
                <div style="text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="font-size: 14px; margin-bottom: 3px;">📱</div>
                    <strong><?php esc_html_e('Gutenberg Blocks', 'ez-translate'); ?></strong>
                </div>
                <div style="text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="font-size: 14px; margin-bottom: 3px;">⚡</div>
                    <strong><?php esc_html_e('Laravel', 'ez-translate'); ?></strong>
                </div>
                <div style="text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="font-size: 14px; margin-bottom: 3px;">⚛️</div>
                    <strong><?php esc_html_e('React & Vue', 'ez-translate'); ?></strong>
                </div>
                <div style="text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="font-size: 14px; margin-bottom: 3px;">🐍</div>
                    <strong><?php esc_html_e('Python', 'ez-translate'); ?></strong>
                </div>
                <div style="text-align: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="font-size: 14px; margin-bottom: 3px;">🤖</div>
                    <strong><?php esc_html_e('AI Integration', 'ez-translate'); ?></strong>
                </div>
            </div>

            <!-- Contact CTA -->
            <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <a href="https://especialistaenwp.com/contact" target="_blank" style="color: #0073aa; text-decoration: none; font-weight: bold; font-size: 13px;">
                    💬 <?php esc_html_e('Get Free Consultation', 'ez-translate'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Get translation statistics
     *
     * @return array
     * @since 1.0.0
     */
    private function get_translation_stats() {
        // Get languages count
        $languages = get_option('ez_translate_languages', array());
        $languages_count = count($languages);

        // Get translated posts count using cache
        $cache_key = 'ez_translate_dashboard_stats';
        $translated_posts = wp_cache_get($cache_key);

        if (false === $translated_posts) {
            global $wpdb;

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom meta query for translation statistics
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Using wp_cache_set below
            // Count posts with translation metadata
            $translated_posts = $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id)
                 FROM {$wpdb->postmeta}
                 WHERE meta_key LIKE 'ez_translate_%'
                 AND meta_key NOT LIKE '%_original_post_id'"
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

            // Cache for 5 minutes
            wp_cache_set($cache_key, $translated_posts, '', 300);
        }

        // Count pending translations (posts without translations in all languages)
        $total_posts = wp_count_posts('post')->publish + wp_count_posts('page')->publish;
        $expected_translations = $total_posts * max(1, $languages_count - 1); // Exclude original language
        $pending = max(0, $expected_translations - (int) $translated_posts);

        return array(
            'languages' => $languages_count,
            'translated_posts' => (int) $translated_posts,
            'pending' => $pending,
            'total_posts' => $total_posts
        );
    }
}
