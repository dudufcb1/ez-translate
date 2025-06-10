/**
 * EZ Translate SEO Metadata Admin JavaScript
 *
 * @package EZTranslate
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * SEO Metadata Admin functionality
     */
    const SeoMetadataAdmin = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initializeToggles();
            this.checkGlobalState();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Global enable/disable toggle
            $('#enabled').on('change', this.handleGlobalToggle.bind(this));
            
            // Enable all button
            $('#enable-all').on('click', this.enableAll.bind(this));
            
            // Disable all button
            $('#disable-all').on('click', this.disableAll.bind(this));
            
            // Reset defaults button
            $('#reset-defaults').on('click', this.resetDefaults.bind(this));
            
            // Form submission
            $('#seo-metadata-form').on('submit', this.handleFormSubmit.bind(this));
            
            // Individual toggle changes
            $('.toggle-switch input[type="checkbox"]').on('change', this.handleToggleChange.bind(this));
        },

        /**
         * Initialize toggle states
         */
        initializeToggles: function() {
            // Set initial states based on current values
            $('.toggle-switch input[type="checkbox"]').each(function() {
                const $toggle = $(this);
                const $section = $toggle.closest('.seo-metadata-section');
                
                if ($toggle.is(':checked')) {
                    $section.addClass('section-enabled');
                } else {
                    $section.addClass('section-disabled');
                }
            });
        },

        /**
         * Handle global enable/disable toggle
         */
        handleGlobalToggle: function(e) {
            const isEnabled = $(e.target).is(':checked');
            const $form = $('#seo-metadata-form');
            
            if (isEnabled) {
                $form.removeClass('globally-disabled');
                this.showNotice('info', ezTranslateSeoMetadata.strings.enabled || 'SEO metadata enabled');
            } else {
                $form.addClass('globally-disabled');
                this.showNotice('warning', 'SEO metadata disabled - no metadata will be generated');
            }
            
            // Update visual state of all sections
            $('.seo-metadata-section').each(function() {
                const $section = $(this);
                if (isEnabled) {
                    $section.removeClass('section-globally-disabled');
                } else {
                    $section.addClass('section-globally-disabled');
                }
            });
        },

        /**
         * Check and update global state
         */
        checkGlobalState: function() {
            const isGloballyEnabled = $('#enabled').is(':checked');
            if (!isGloballyEnabled) {
                $('#seo-metadata-form').addClass('globally-disabled');
                $('.seo-metadata-section').addClass('section-globally-disabled');
            }
        },

        /**
         * Enable all metadata options
         */
        enableAll: function(e) {
            e.preventDefault();
            
            // Enable global toggle first
            $('#enabled').prop('checked', true).trigger('change');
            
            // Enable all individual toggles except global ones
            $('.seo-metadata-section .toggle-switch input[type="checkbox"]').each(function() {
                const $toggle = $(this);
                if ($toggle.attr('id') !== 'enabled' && $toggle.attr('id') !== 'override_other_plugins') {
                    $toggle.prop('checked', true).trigger('change');
                }
            });
            
            this.showNotice('success', 'All metadata options enabled');
        },

        /**
         * Disable all metadata options
         */
        disableAll: function(e) {
            e.preventDefault();
            
            // Disable all toggles except the global enable
            $('.seo-metadata-section .toggle-switch input[type="checkbox"]').each(function() {
                const $toggle = $(this);
                if ($toggle.attr('id') !== 'enabled') {
                    $toggle.prop('checked', false).trigger('change');
                }
            });
            
            this.showNotice('info', 'All metadata options disabled');
        },

        /**
         * Reset to default settings
         */
        resetDefaults: function(e) {
            e.preventDefault();
            
            if (!confirm(ezTranslateSeoMetadata.strings.confirmReset || 'Are you sure you want to reset all settings to defaults?')) {
                return;
            }
            
            // Default settings (all enabled except override_other_plugins)
            const defaults = {
                'enabled': true,
                'document_title': true,
                'meta_description': true,
                'canonical_urls': true,
                'open_graph': true,
                'twitter_cards': true,
                'featured_images': true,
                'hreflang_tags': true,
                'language_alternates': true,
                'json_ld_schema': true,
                'article_metadata': true,
                'override_other_plugins': true
            };
            
            // Apply defaults
            Object.keys(defaults).forEach(function(key) {
                const $toggle = $('#' + key);
                if ($toggle.length) {
                    $toggle.prop('checked', defaults[key]).trigger('change');
                }
            });
            
            this.showNotice('success', 'Settings reset to defaults');
        },

        /**
         * Handle individual toggle changes
         */
        handleToggleChange: function(e) {
            const $toggle = $(e.target);
            const $section = $toggle.closest('.seo-metadata-section');
            const isChecked = $toggle.is(':checked');
            
            // Update section visual state
            if (isChecked) {
                $section.removeClass('section-disabled').addClass('section-enabled');
            } else {
                $section.removeClass('section-enabled').addClass('section-disabled');
            }
            
            // Add visual feedback
            $toggle.closest('.toggle-switch').addClass('toggle-changed');
            setTimeout(function() {
                $toggle.closest('.toggle-switch').removeClass('toggle-changed');
            }, 300);
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            const $form = $(e.target);
            const $submitButton = $form.find('input[type="submit"]');
            
            // Add loading state
            $submitButton.prop('disabled', true).val('Saving...');
            $form.addClass('seo-metadata-loading');
            
            // Show saving notice
            this.showNotice('info', 'Saving settings...');
            
            // Form will submit normally, but we provide visual feedback
            return true;
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            // Remove existing notices
            $('.ez-translate-notice').remove();
            
            // Create new notice
            const $notice = $('<div class="notice ez-translate-notice is-dismissible notice-' + type + '"><p>' + message + '</p></div>');
            
            // Insert after page title
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 3 seconds for non-error notices
            if (type !== 'error') {
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $(this).remove();
                    });
                }, 3000);
            }
            
            // Make dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Get current form data as object
         */
        getFormData: function() {
            const formData = {};
            $('#seo-metadata-form').find('input[type="checkbox"]').each(function() {
                const $input = $(this);
                formData[$input.attr('name')] = $input.is(':checked');
            });
            return formData;
        },

        /**
         * Validate form before submission
         */
        validateForm: function() {
            const formData = this.getFormData();
            
            // Check if at least one option is enabled when global is enabled
            if (formData.enabled) {
                const hasEnabledOptions = Object.keys(formData).some(function(key) {
                    return key !== 'enabled' && key !== 'override_other_plugins' && formData[key];
                });
                
                if (!hasEnabledOptions) {
                    this.showNotice('warning', 'At least one metadata option should be enabled when SEO metadata is enabled.');
                    return false;
                }
            }
            
            return true;
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only initialize on the SEO metadata admin page
        if ($('#seo-metadata-form').length) {
            SeoMetadataAdmin.init();
        }
    });

    // Make SeoMetadataAdmin globally available for debugging
    window.EZTranslateSeoMetadataAdmin = SeoMetadataAdmin;

})(jQuery);
