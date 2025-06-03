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

        // Landing page functionality removed - legacy state removed

        // Get post data from WordPress data store
        const { postId, postMeta, isSaving, isAutosaving, hasEdits } = useSelect((select) => {
            const { getCurrentPostId, getEditedPostAttribute, isSavingPost, isAutosavingPost, isEditedPostDirty } = select('core/editor');

            return {
                postId: getCurrentPostId(),
                postMeta: getEditedPostAttribute('meta') || {},
                isSaving: isSavingPost(),
                isAutosaving: isAutosavingPost(),
                hasEdits: isEditedPostDirty()
            };
        });

        // Get meta update function
        const { editPost } = useDispatch('core/editor');

        // Current metadata values
        const currentLanguage = postMeta._ez_translate_language || '';
        // Landing page functionality removed - legacy variables removed
        const currentSeoTitle = postMeta._ez_translate_seo_title || '';
        const currentSeoDescription = postMeta._ez_translate_seo_description || '';

        // Debug logging
        console.log('EZ Translate: Current meta values:', {
            language: currentLanguage,
            seoTitle: currentSeoTitle,
            seoDescription: currentSeoDescription,
            allPostMeta: postMeta
        });

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

        // Landing page sync effect removed - legacy functionality

        // Monitor post save status
        useEffect(() => {
            console.log('EZ Translate: Post save status changed', {
                isSaving: isSaving,
                isAutosaving: isAutosaving,
                hasEdits: hasEdits,
                postMeta: postMeta
            });
        }, [isSaving, isAutosaving, hasEdits]);

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
            console.log('EZ Translate: updateMeta called with:', { key, value, currentMeta: postMeta });

            const newMeta = {
                ...postMeta,
                [key]: value
            };

            console.log('EZ Translate: About to call editPost with meta:', newMeta);

            editPost({
                meta: newMeta
            });

            // Clear any previous messages
            setError(null);

            console.log('EZ Translate: editPost called successfully with new meta');
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

        // Landing page toggle handler removed - legacy functionality

        /**
         * Debug function to check database value
         */
        const checkDatabaseValue = async () => {
            try {
                const response = await apiFetch({
                    path: `ez-translate/v1/post-meta/${postId}`,
                    method: 'GET'
                });
                console.log('EZ Translate: Database value check:', response);
                alert('Database value: ' + JSON.stringify(response.metadata, null, 2));
            } catch (error) {
                console.error('EZ Translate: Failed to check database value:', error);
                alert('Error checking database: ' + error.message);
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

                            // Landing page badge removed - legacy functionality
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

            // Current Language Info Panel (when page has language assigned)
            currentLanguage && el(PanelBody, {
                title: __('Current Language', 'ez-translate'),
                initialOpen: true
            },
                el('div', { style: { marginBottom: '16px' } },
                    el('label', { style: { fontWeight: 'bold', display: 'block', marginBottom: '4px' } },
                        __('Page Language', 'ez-translate')
                    ),
                    el('div', { style: { padding: '8px', backgroundColor: '#e7f3ff', borderRadius: '4px', border: '1px solid #72aee6' } },
                        currentLanguage.toUpperCase() + ' (' + (allLanguages[currentLanguage]?.name || 'Unknown') + ')'
                    )
                ),

                postMeta._ez_translate_group && el('div', { style: { marginBottom: '16px' } },
                    el('label', { style: { fontWeight: 'bold', display: 'block', marginBottom: '4px' } },
                        __('Translation Group', 'ez-translate')
                    ),
                    el('div', { style: { padding: '8px', backgroundColor: '#f0f0f0', borderRadius: '4px', fontFamily: 'monospace', fontSize: '12px' } },
                        postMeta._ez_translate_group
                    ),
                    el('p', { style: { margin: '4px 0 0 0', fontSize: '11px', color: '#666' } },
                        __('Pages with the same group ID are translations of each other.', 'ez-translate')
                    )
                )
            ),

            // SEO Metadata Panel (when page has language assigned)
            currentLanguage && el(PanelBody, {
                title: __('SEO Metadata', 'ez-translate'),
                initialOpen: true
            },
                el(TextControl, {
                    label: __('SEO Title', 'ez-translate'),
                    value: currentSeoTitle,
                    onChange: (value) => updateMeta('_ez_translate_seo_title', value),
                    help: __('Custom SEO title for this page. Leave empty to use the page title.', 'ez-translate'),
                    placeholder: __('Enter SEO title...', 'ez-translate')
                }),

                el(TextareaControl, {
                    label: __('SEO Description', 'ez-translate'),
                    value: currentSeoDescription,
                    onChange: (value) => updateMeta('_ez_translate_seo_description', value),
                    help: __('Meta description for search engines and social media.', 'ez-translate'),
                    placeholder: __('Enter SEO description...', 'ez-translate'),
                    rows: 3
                }),

                el('div', { style: { marginTop: '12px', padding: '8px', backgroundColor: '#f0f0f0', borderRadius: '4px' } },
                    el('p', { style: { margin: '0', fontSize: '12px', color: '#666' } },
                        __('These SEO settings will be used in the page head, Open Graph tags, and structured data.', 'ez-translate')
                    )
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
