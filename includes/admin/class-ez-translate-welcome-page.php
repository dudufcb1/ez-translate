<?php
/**
 * Welcome Page Admin Class
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
 * Welcome Page Admin Class
 *
 * @since 1.0.0
 */
class WelcomePage {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_welcome_page'));
        add_action('admin_init', array($this, 'handle_welcome_redirect'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_welcome_styles'));
    }

    /**
     * Handle welcome redirect after activation
     *
     * @since 1.0.0
     */
    public function handle_welcome_redirect() {
        if (get_option('ez_translate_activation_redirect', false)) {
            delete_option('ez_translate_activation_redirect');
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only checking activation parameter, no data processing
            if (!isset($_GET['activate-multi'])) {
                wp_redirect(admin_url('admin.php?page=ez-translate-welcome'));
                exit;
            }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
        }
    }

    /**
     * Add welcome page to admin menu
     *
     * @since 1.0.0
     */
    public function add_welcome_page() {
        add_submenu_page(
            'ez-translate',
            __('Welcome to EZ Translate', 'ez-translate'),
            __('🎉 Welcome', 'ez-translate'),
            'manage_options',
            'ez-translate-welcome',
            array($this, 'render_welcome_page')
        );
    }

    /**
     * Enqueue welcome page styles
     *
     * @since 1.0.0
     */
    public function enqueue_welcome_styles($hook) {
        if ($hook !== 'ez-translate_page_ez-translate-welcome') {
            return;
        }

        wp_add_inline_style('wp-admin', $this->get_welcome_styles());
    }

    /**
     * Get welcome page styles
     *
     * @return string
     * @since 1.0.0
     */
    private function get_welcome_styles() {
        return '
        .ez-welcome-container {
            max-width: 1200px;
            margin: 20px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .ez-welcome-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .ez-welcome-header h1 {
            font-size: 2.5em;
            margin: 0 0 10px 0;
            color: white;
        }
        .ez-welcome-header p {
            font-size: 1.2em;
            margin: 0;
            opacity: 0.9;
        }
        .ez-welcome-content {
            padding: 40px;
        }
        .ez-feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 30px 0;
        }
        .ez-feature-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .ez-feature-card h3 {
            color: #333;
            margin-top: 0;
        }
        .ez-cta-section {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 40px;
            text-align: center;
            margin: 40px 0;
            border-radius: 8px;
        }
        .ez-cta-button {
            display: inline-block;
            background: white;
            color: #f5576c;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            margin: 10px;
            transition: transform 0.3s ease;
        }
        .ez-cta-button:hover {
            transform: translateY(-2px);
            color: #f5576c;
            text-decoration: none;
        }
        .ez-services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .ez-service-item {
            text-align: center;
            padding: 20px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            transition: border-color 0.3s ease;
        }
        .ez-service-item:hover {
            border-color: #667eea;
        }
        .ez-service-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
        }
        ';
    }

    /**
     * Render welcome page
     *
     * @since 1.0.0
     */
    public function render_welcome_page() {
        ?>
        <div class="wrap">
            <div class="ez-welcome-container">
                <!-- Header Section -->
                <div class="ez-welcome-header">
                    <h1>🌍 <?php esc_html_e('Welcome to EZ Translate!', 'ez-translate'); ?></h1>
                    <p><?php esc_html_e('Your multilingual WP journey starts here', 'ez-translate'); ?></p>
                </div>

                <!-- Main Content -->
                <div class="ez-welcome-content">
                    <h2><?php esc_html_e('🚀 What makes EZ Translate special?', 'ez-translate'); ?></h2>
                    
                    <div class="ez-feature-grid">
                        <div class="ez-feature-card">
                            <h3>🎯 <?php esc_html_e('SEO Optimized', 'ez-translate'); ?></h3>
                            <p><?php esc_html_e('Automatic hreflang tags, multilingual sitemaps, and search engine friendly URLs ensure your content ranks well in all languages.', 'ez-translate'); ?></p>
                        </div>
                        
                        <div class="ez-feature-card">
                            <h3>⚡ <?php esc_html_e('Gutenberg Ready', 'ez-translate'); ?></h3>
                            <p><?php esc_html_e('Native integration with the block editor makes translating content as easy as editing it. No complex workflows needed!', 'ez-translate'); ?></p>
                        </div>
                        
                        <div class="ez-feature-card">
                            <h3>🔄 <?php esc_html_e('Smart Redirects', 'ez-translate'); ?></h3>
                            <p><?php esc_html_e('Automatic language detection and intelligent redirect management ensure visitors always see content in their preferred language.', 'ez-translate'); ?></p>
                        </div>
                        
                        <div class="ez-feature-card">
                            <h3>📊 <?php esc_html_e('Advanced Analytics', 'ez-translate'); ?></h3>
                            <p><?php esc_html_e('Track translation performance, user language preferences, and optimize your multilingual strategy with detailed insights.', 'ez-translate'); ?></p>
                        </div>
                    </div>

                    <!-- Quick Start -->
                    <h2><?php esc_html_e('🎯 Quick Start Guide', 'ez-translate'); ?></h2>
                    <ol style="font-size: 1.1em; line-height: 1.6;">
                        <li><strong><?php esc_html_e('Add Languages:', 'ez-translate'); ?></strong> <?php esc_html_e('Go to EZ Translate → Languages and add your target languages', 'ez-translate'); ?></li>
                        <li><strong><?php esc_html_e('Configure SEO:', 'ez-translate'); ?></strong> <?php esc_html_e('Visit EZ Translate → SEO Metadata to optimize your multilingual SEO', 'ez-translate'); ?></li>
                        <li><strong><?php esc_html_e('Start Translating:', 'ez-translate'); ?></strong> <?php esc_html_e('Edit any post or page and use the translation panel to create multilingual content', 'ez-translate'); ?></li>
                        <li><strong><?php esc_html_e('Customize Display:', 'ez-translate'); ?></strong> <?php esc_html_e('Configure the language selector and user experience settings', 'ez-translate'); ?></li>
                    </ol>

                    <!-- CTA Section -->
                    <div class="ez-cta-section">
                        <h2><?php esc_html_e('🚀 Need Professional Development Solutions?', 'ez-translate'); ?></h2>
                        <p style="font-size: 1.2em; margin-bottom: 25px;">
                            <?php esc_html_e('The creator of EZ Translate offers comprehensive web development services: WP themes & plugins, Laravel, React, Vue.js, Python, and AI integration', 'ez-translate'); ?>
                        </p>
                        
                        <a href="https://especialistaenwp.com" target="_blank" class="ez-cta-button">
                            🌐 <?php esc_html_e('Visit EspecialistaEnWP.com', 'ez-translate'); ?>
                        </a>
                        
                        <a href="https://especialistaenwp.com/contact" target="_blank" class="ez-cta-button">
                            💬 <?php esc_html_e('Free Consultation', 'ez-translate'); ?>
                        </a>
                    </div>

                    <!-- Services Grid -->
                    <h2><?php esc_html_e('🛠️ Professional Services Available', 'ez-translate'); ?></h2>
                    <div class="ez-services-grid">
                        <div class="ez-service-item">
                            <div class="ez-service-icon">🎨</div>
                            <h4><?php esc_html_e('FSE Themes', 'ez-translate'); ?></h4>
                            <p><?php esc_html_e('Modern Full Site Editing themes built for performance and flexibility', 'ez-translate'); ?></p>
                        </div>

                        <div class="ez-service-item">
                            <div class="ez-service-icon">🏛️</div>
                            <h4><?php esc_html_e('Classic Themes', 'ez-translate'); ?></h4>
                            <p><?php esc_html_e('Traditional WP themes with custom post types and advanced functionality', 'ez-translate'); ?></p>
                        </div>

                        <div class="ez-service-item">
                            <div class="ez-service-icon">🔧</div>
                            <h4><?php esc_html_e('Custom Plugins', 'ez-translate'); ?></h4>
                            <p><?php esc_html_e('Modern and classic plugins tailored for your specific business needs', 'ez-translate'); ?></p>
                        </div>

                        <div class="ez-service-item">
                            <div class="ez-service-icon">📱</div>
                            <h4><?php esc_html_e('Gutenberg Blocks', 'ez-translate'); ?></h4>
                            <p><?php esc_html_e('Custom blocks that extend the editor with powerful new functionality', 'ez-translate'); ?></p>
                        </div>

                        <div class="ez-service-item">
                            <div class="ez-service-icon">🤖</div>
                            <h4><?php esc_html_e('AI Integration', 'ez-translate'); ?></h4>
                            <p><?php esc_html_e('Cutting-edge artificial intelligence features to automate and enhance your site', 'ez-translate'); ?></p>
                        </div>

                        <div class="ez-service-item">
                            <div class="ez-service-icon">⚡</div>
                            <h4><?php esc_html_e('Laravel Development', 'ez-translate'); ?></h4>
                            <p><?php esc_html_e('Custom web applications and APIs built with Laravel framework', 'ez-translate'); ?></p>
                        </div>

                        <div class="ez-service-item">
                            <div class="ez-service-icon">⚛️</div>
                            <h4><?php esc_html_e('React & Vue.js', 'ez-translate'); ?></h4>
                            <p><?php esc_html_e('Modern frontend applications and interactive user interfaces', 'ez-translate'); ?></p>
                        </div>

                        <div class="ez-service-item">
                            <div class="ez-service-icon">🐍</div>
                            <h4><?php esc_html_e('Python Solutions', 'ez-translate'); ?></h4>
                            <p><?php esc_html_e('Data analysis, automation scripts, and backend development', 'ez-translate'); ?></p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="text-align: center; margin-top: 40px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ez-translate')); ?>" class="button button-primary button-hero">
                            <?php esc_html_e('🎯 Start Using EZ Translate', 'ez-translate'); ?>
                        </a>
                        
                        <a href="https://especialistaenwp.com/plugins/ez-translate/documentation" target="_blank" class="button button-secondary button-hero" style="margin-left: 15px;">
                            <?php esc_html_e('📚 View Documentation', 'ez-translate'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
