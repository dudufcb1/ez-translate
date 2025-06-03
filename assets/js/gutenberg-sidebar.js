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

    // Use modern APIs (WordPress 6.6+) with fallback to deprecated ones
    const PluginSidebar = wp.editor?.PluginSidebar || wp.editPost?.PluginSidebar;
    const PluginSidebarMoreMenuItem = wp.editor?.PluginSidebarMoreMenuItem || wp.editPost?.PluginSidebarMoreMenuItem;
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
        const [translationData, setTranslationData] = useState(null);
        const [loadingTranslations, setLoadingTranslations] = useState(false);

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
        const currentIsLanding = postMeta._ez_translate_is_landing || false;
        const currentSeoTitle = postMeta._ez_translate_seo_title || '';
        const currentSeoDescription = postMeta._ez_translate_seo_description || '';

        // Detect original language (from WordPress config or current page)
        const wpLanguage = window.ezTranslateGutenberg?.wpLanguage || 'en';

        // If page already has a language set, that's the original language
        // Otherwise, use WordPress default language
        const originalLanguage = currentLanguage || wpLanguage;

        // Load languages and translations on component mount
        useEffect(() => {
            loadLanguages();
            loadExistingTranslations();
        }, []);

        // Reload translations when post ID changes
        useEffect(() => {
            if (postId) {
                loadExistingTranslations();
            }
        }, [postId]);

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
         * Load existing translations for this post
         */
        const loadExistingTranslations = async () => {
            console.log('EZ Translate: loadExistingTranslations called, postId:', postId);
            if (!postId) {
                console.log('EZ Translate: No postId, skipping translation verification');
                return;
            }

            try {
                console.log('EZ Translate: Starting translation verification for post:', postId);
                setLoadingTranslations(true);

                const response = await apiFetch({
                    path: `ez-translate/v1/verify-translations/${postId}`,
                    method: 'GET'
                });

                console.log('Translation verification response:', response);
                setTranslationData(response);

                // Filter existing languages to exclude those with existing translations
                if (response.unavailable_languages && response.unavailable_languages.length > 0) {
                    // Get current languages and filter out unavailable ones
                    setLanguages(currentLanguages => {
                        return currentLanguages.filter(lang => {
                            // Keep the empty option and languages not in unavailable list
                            return lang.value === '' || !response.unavailable_languages.includes(lang.value);
                        });
                    });
                }

            } catch (err) {
                console.error('Failed to load existing translations:', err);
                // Don't show error for this, just log it
            } finally {
                setLoadingTranslations(false);
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
                console.log('Creating translation from', originalLanguage, 'to', selectedTargetLanguage);

                // Call the REST API to create translation
                const response = await wp.apiFetch({
                    path: `/ez-translate/v1/create-translation/${postId}`,
                    method: 'POST',
                    data: {
                        target_language: selectedTargetLanguage
                    }
                });

                if (response.success) {
                    // Reload translations to update the UI
                    await loadExistingTranslations();

                    // Show success message
                    const message = __('Translation created successfully!', 'ez-translate') +
                                  '\n\n' + __('You will be redirected to edit the new translation.', 'ez-translate');

                    if (confirm(message)) {
                        // Redirect to edit the new translation
                        window.location.href = response.data.edit_url;
                    }
                } else {
                    setError(__('Failed to create translation. Please try again.', 'ez-translate'));
                }

                // Reset selection
                setSelectedTargetLanguage('');

            } catch (err) {
                console.error('Failed to create translation:', err);

                // Handle specific error cases
                if (err.code === 'translation_exists') {
                    setError(__('A translation for this language already exists.', 'ez-translate'));
                } else if (err.code === 'invalid_target_language') {
                    setError(__('Invalid target language selected.', 'ez-translate'));
                } else {
                    setError(__('Failed to create translation. Please try again.', 'ez-translate'));
                }
            } finally {
                setCreating(false);
            }
        };

        /**
         * Handle landing page toggle with validation
         */
        const handleLandingToggle = async (isLanding) => {
            if (isLanding) {
                // Check if another landing page exists for this language
                try {
                    const response = await apiFetch({
                        path: `ez-translate/v1/post-meta/${postId}`,
                        method: 'POST',
                        data: {
                            is_landing: true
                        }
                    });

                    if (response.success) {
                        updateMeta('_ez_translate_is_landing', true);
                        setError(null);
                    } else {
                        setError(__('Another page is already set as landing page for this language.', 'ez-translate'));
                    }
                } catch (err) {
                    console.error('Failed to set landing page:', err);
                    if (err.code === 'landing_page_exists') {
                        setError(__('Another page is already set as landing page for this language.', 'ez-translate'));
                    } else {
                        setError(__('Failed to set landing page. Please try again.', 'ez-translate'));
                    }
                }
            } else {
                // Removing landing page status
                updateMeta('_ez_translate_is_landing', false);
                updateMeta('_ez_translate_seo_title', '');
                updateMeta('_ez_translate_seo_description', '');
                setError(null);
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

            // Existing Translations Panel
            translationData && translationData.existing_translations && translationData.existing_translations.length > 0 && el(PanelBody, {
                title: __('Existing Translations', 'ez-translate'),
                initialOpen: true
            },
                el('div', { style: { marginBottom: '12px' } },
                    el('p', { style: { margin: '0 0 8px 0', fontSize: '13px', color: '#666' } },
                        __('This page has translations in the following languages:', 'ez-translate')
                    )
                ),

                translationData.existing_translations.map((translation, index) =>
                    el('div', {
                        key: translation.post_id,
                        style: {
                            padding: '12px',
                            border: '1px solid #ddd',
                            borderRadius: '4px',
                            marginBottom: '8px',
                            backgroundColor: '#f9f9f9'
                        }
                    },
                        el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' } },
                            el('div', null,
                                el('strong', { style: { display: 'block', marginBottom: '4px' } },
                                    translation.language_info.name + (translation.language_info.native_name ? ` (${translation.language_info.native_name})` : '') +
                                    (translation.is_current ? ' (Current)' : '') +
                                    (translation.is_original ? ' (Original)' : '')
                                ),
                                el('div', { style: { fontSize: '12px', color: '#666' } },
                                    translation.title
                                )
                            ),
                            el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
                                translation.is_current && el('span', {
                                    style: {
                                        fontSize: '10px',
                                        backgroundColor: '#0073aa',
                                        color: 'white',
                                        padding: '2px 6px',
                                        borderRadius: '3px'
                                    }
                                }, __('Current', 'ez-translate')),
                                translation.is_original && el('span', {
                                    style: {
                                        fontSize: '10px',
                                        backgroundColor: '#d63638',
                                        color: 'white',
                                        padding: '2px 6px',
                                        borderRadius: '3px'
                                    }
                                }, __('Original', 'ez-translate')),
                                el('div', { style: { fontSize: '11px', color: '#999' } },
                                    translation.language.toUpperCase()
                                )
                            )
                        ),

                        el('div', { style: { display: 'flex', gap: '8px', alignItems: 'center' } },
                            // Only show Edit/View buttons for other translations, not current post
                            !translation.is_current && el('a', {
                                href: translation.edit_url,
                                className: 'components-button is-secondary is-small',
                                style: { textDecoration: 'none', fontSize: '12px' }
                            }, __('Edit', 'ez-translate')),

                            !translation.is_current && translation.status === 'publish' && el('a', {
                                href: translation.view_url,
                                className: 'components-button is-tertiary is-small',
                                target: '_blank',
                                style: { textDecoration: 'none', fontSize: '12px' }
                            }, __('View', 'ez-translate')),

                            translation.is_landing && el('span', {
                                style: {
                                    fontSize: '11px',
                                    backgroundColor: '#00a32a',
                                    color: 'white',
                                    padding: '2px 6px',
                                    borderRadius: '3px',
                                    marginLeft: translation.is_current ? '0' : 'auto'
                                }
                            }, __('Landing', 'ez-translate'))
                        )
                    )
                )
            ),

            // Source Language Info Panel (when auto-detected)
            translationData && translationData.source_language_detected && el(PanelBody, {
                title: __('Language Detection', 'ez-translate'),
                initialOpen: false
            },
                el('div', { style: { padding: '8px', backgroundColor: '#e7f3ff', borderRadius: '4px', border: '1px solid #72aee6' } },
                    el('p', { style: { margin: '0', fontSize: '13px' } },
                        __('Language automatically detected as:', 'ez-translate') + ' ' +
                        (translationData.source_language ? translationData.source_language.toUpperCase() : __('Unknown', 'ez-translate'))
                    ),
                    el('p', { style: { margin: '8px 0 0 0', fontSize: '12px', color: '#666' } },
                        __('This page was detected as part of a translation group. You can create additional translations using the form above.', 'ez-translate')
                    )
                )
            ),

            // Landing Page Panel (show for all pages that have a language set)
            currentLanguage && el(PanelBody, {
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
