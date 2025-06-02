/**
 * EZ Translate Gutenberg Sidebar - Compiled Version
 * 
 * @package EZTranslate
 * @since 1.0.0
 */

(function() {
    'use strict';

    // WordPress dependencies
    const { __ } = wp.i18n;
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { 
        PanelBody, 
        SelectControl, 
        ToggleControl, 
        TextControl, 
        TextareaControl,
        Notice,
        Spinner
    } = wp.components;
    const { useSelect, useDispatch } = wp.data;
    const { useState, useEffect, createElement: el } = wp.element;
    const { apiFetch } = wp;

    /**
     * EZ Translate Sidebar Component
     */
    function EZTranslateSidebar() {
        // State management
        const [languages, setLanguages] = useState([]);
        const [allLanguages, setAllLanguages] = useState([]); // Store all languages for reference
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState(null);
        const [selectedTargetLanguage, setSelectedTargetLanguage] = useState('');
        const [creating, setCreating] = useState(false);

        // Get post data from WordPress data store
        const { postId, postMeta } = useSelect((select) => {
            const { getCurrentPostId } = select('core/editor');
            const { getEditedPostAttribute } = select('core/editor');
            
            return {
                postId: getCurrentPostId(),
                postMeta: getEditedPostAttribute('meta') || {}
            };
        });

        // Get meta update function
        const { editPost } = useDispatch('core/editor');

        // Current metadata values
        const currentLanguage = postMeta._ez_translate_language || '';
        const currentGroup = postMeta._ez_translate_group || '';
        const currentIsLanding = postMeta._ez_translate_is_landing || false;
        const currentSeoTitle = postMeta._ez_translate_seo_title || '';
        const currentSeoDescription = postMeta._ez_translate_seo_description || '';

        // Detect original language (from WordPress config or current page)
        const wpLanguage = window.ezTranslateGutenberg?.wpLanguage || 'en';

        // If page already has a language set, that's the original language
        // Otherwise, use WordPress default language
        const originalLanguage = currentLanguage || wpLanguage;

        // Check if this page is already a translation (has a different language than WP default)
        const isTranslationPage = currentLanguage && currentLanguage !== wpLanguage;



        // Load languages on component mount
        useEffect(() => {
            loadLanguages();
        }, []);

        /**
         * Load available languages from API
         */
        const loadLanguages = async () => {
            try {
                setLoading(true);
                setError(null);

                const response = await apiFetch({
                    path: 'ez-translate/v1/languages',
                    method: 'GET'
                });

                // Format languages for SelectControl (exclude original language)
                const formattedLanguages = [
                    { value: '', label: __('Select target language...', 'ez-translate') }
                ];

                // Store all languages for reference (convert array to object if needed)
                const allLangsObj = Array.isArray(response)
                    ? response.reduce((acc, lang) => ({ ...acc, [lang.code]: lang }), {})
                    : response;
                setAllLanguages(allLangsObj);

                // Handle both array and object responses
                const languageEntries = Array.isArray(response)
                    ? response.map(lang => [lang.code, lang])
                    : Object.entries(response);

                languageEntries.forEach(([code, language]) => {
                    // Only show enabled languages that are not the original language
                    if (language.enabled && code !== originalLanguage) {
                        formattedLanguages.push({
                            value: code,
                            label: language.name + (language.native_name ? ` (${language.native_name})` : ''),
                        });
                    }
                });
                setLanguages(formattedLanguages);
            } catch (err) {
                console.error('Failed to load languages:', err);
                setError(__('Failed to load languages. Please refresh the page.', 'ez-translate'));
            } finally {
                setLoading(false);
            }
        };

        /**
         * Update post meta field
         */
        const updateMeta = (key, value) => {
            editPost({
                meta: {
                    ...postMeta,
                    [key]: value
                }
            });

            // Clear any previous messages
            setError(null);
        };

        /**
         * Generate new translation group ID
         */
        const generateGroupId = () => {
            const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
            let result = 'tg_';
            for (let i = 0; i < 16; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        };

        /**
         * Handle target language selection (for creating translation)
         */
        const handleLanguageChange = (targetLanguage) => {
            setSelectedTargetLanguage(targetLanguage);
            setError(null);
        };

        /**
         * Create translation page
         */
        const createTranslation = async () => {
            if (!selectedTargetLanguage) {
                setError(__('Please select a target language first.', 'ez-translate'));
                return;
            }

            setCreating(true);
            setError(null);

            try {
                // TODO: Implement actual page duplication via REST API
                console.log('Creating translation from', originalLanguage, 'to', selectedTargetLanguage);

                // For now, just show a message
                alert(`Translation creation will be implemented soon!\n\nFrom: ${originalLanguage}\nTo: ${selectedTargetLanguage}`);

                // Reset selection
                setSelectedTargetLanguage('');

            } catch (err) {
                console.error('Failed to create translation:', err);
                setError(__('Failed to create translation. Please try again.', 'ez-translate'));
            } finally {
                setCreating(false);
            }
        };

        /**
         * Handle landing page toggle
         */
        const handleLandingToggle = (isLanding) => {
            updateMeta('_ez_translate_is_landing', isLanding);
            
            // Clear SEO fields if not landing page
            if (!isLanding) {
                updateMeta('_ez_translate_seo_title', '');
                updateMeta('_ez_translate_seo_description', '');
            }
        };

        /**
         * Render loading state
         */
        if (loading) {
            return el('div', { style: { padding: '16px', textAlign: 'center' } },
                el(Spinner),
                el('p', null, __('Loading languages...', 'ez-translate'))
            );
        }

        return el('div', null,
            // Error Notice
            error && el(Notice, {
                status: 'error',
                isDismissible: true,
                onRemove: () => setError(null)
            }, error),

            // Language Selection Panel
            el(PanelBody, {
                title: __('Translation Settings', 'ez-translate'),
                initialOpen: true
            },
                // Show original language (read-only)
                el('div', { style: { marginBottom: '16px' } },
                    el('label', { style: { fontWeight: 'bold', display: 'block', marginBottom: '4px' } },
                        __('Original Language', 'ez-translate')
                    ),
                    el('div', { style: { padding: '8px', backgroundColor: '#f0f0f0', borderRadius: '4px' } },
                        originalLanguage.toUpperCase() + ' (' + (allLanguages[originalLanguage]?.name || 'Default') + ')'
                    )
                ),

                // Target language selector
                el(SelectControl, {
                    label: __('Create Translation To', 'ez-translate'),
                    value: selectedTargetLanguage,
                    options: languages,
                    onChange: handleLanguageChange,
                    help: __('Select the target language to create a translation.', 'ez-translate')
                }),

                // Show create button when target language is selected
                selectedTargetLanguage && el('div', {
                    style: { marginTop: '16px' }
                },
                    el('button', {
                        className: 'components-button is-primary',
                        onClick: createTranslation,
                        disabled: creating,
                        style: { width: '100%' }
                    },
                        creating
                            ? __('Creating Translation...', 'ez-translate')
                            : __('Create Translation Page', 'ez-translate') + ' (' + (allLanguages[selectedTargetLanguage]?.name || selectedTargetLanguage) + ')'
                    ),

                    el('p', {
                        style: {
                            margin: '8px 0 0 0',
                            fontSize: '12px',
                            color: '#757575',
                            fontStyle: 'italic'
                        }
                    },
                        __('This will create a new page with the same content in the selected language.', 'ez-translate')
                    )
                )
            ),

            // Landing Page Panel (only show for translation pages)
            isTranslationPage && el(PanelBody, {
                title: __('Landing Page Settings', 'ez-translate'),
                initialOpen: false
            },
                el(ToggleControl, {
                    label: __('Landing Page', 'ez-translate'),
                    checked: currentIsLanding,
                    onChange: handleLandingToggle,
                    help: __('Mark this page as the landing page for this language.', 'ez-translate')
                }),

                currentIsLanding && el('div', null,
                    el(TextControl, {
                        label: __('SEO Title', 'ez-translate'),
                        value: currentSeoTitle,
                        onChange: (value) => updateMeta('_ez_translate_seo_title', value),
                        help: __('Custom SEO title for this landing page.', 'ez-translate')
                    }),

                    el(TextareaControl, {
                        label: __('SEO Description', 'ez-translate'),
                        value: currentSeoDescription,
                        onChange: (value) => updateMeta('_ez_translate_seo_description', value),
                        help: __('Custom SEO description for this landing page.', 'ez-translate'),
                        rows: 3
                    })
                )
            )
        );
    }

    /**
     * Register the plugin
     */
    registerPlugin('ez-translate-sidebar', {
        render: function() {
            return el('div', null,
                el(PluginSidebarMoreMenuItem, {
                    target: 'ez-translate-sidebar'
                }, __('EZ Translate', 'ez-translate')),
                
                el(PluginSidebar, {
                    name: 'ez-translate-sidebar',
                    title: __('EZ Translate', 'ez-translate'),
                    icon: 'translation'
                }, el(EZTranslateSidebar))
            );
        }
    });

})();
