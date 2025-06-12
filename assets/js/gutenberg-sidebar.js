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

        // SEO AI states
        const [aiLoading, setAiLoading] = useState(false);
        const [aiError, setAiError] = useState(null);
        const [seoValidation, setSeoValidation] = useState({});
        const [similarityCheck, setSimilarityCheck] = useState({});
        const [manualCheckResult, setManualCheckResult] = useState(null);

        // API status states
        const [apiStatus, setApiStatus] = useState(null);
        const [loadingApiStatus, setLoadingApiStatus] = useState(false);

        // Multi-translation states
        const [selectedLanguages, setSelectedLanguages] = useState([]);
        const [creatingMultiple, setCreatingMultiple] = useState(false);
        const [multiTranslationProgress, setMultiTranslationProgress] = useState(null);

        // Landing page functionality removed - legacy state removed

        // Get post data from WordPress data store
        const { postId, postMeta, postTitle, postContent, isSaving, isAutosaving, hasEdits } = useSelect((select) => {
            const { getCurrentPostId, getEditedPostAttribute, isSavingPost, isAutosavingPost, isEditedPostDirty } = select('core/editor');

            return {
                postId: getCurrentPostId(),
                postMeta: getEditedPostAttribute('meta') || {},
                postTitle: getEditedPostAttribute('title') || '',
                postContent: getEditedPostAttribute('content') || '',
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
        const currentOgTitle = postMeta._ez_translate_og_title || '';

        // Detect original language (from WordPress config or current page)
        const wpLanguage = window.ezTranslateGutenberg?.wpLanguage || 'en';

        // If page already has a language set, that's the original language
        // Otherwise, use WordPress default language
        const originalLanguage = currentLanguage || wpLanguage;

        // Load languages and translations on component mount
        useEffect(() => {
            loadLanguages();
            loadExistingTranslations();
            loadApiStatus();
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
        }, [isSaving, isAutosaving, hasEdits]);

        // Validate SEO content when fields change
        useEffect(() => {
            if (currentSeoTitle || currentSeoDescription || currentOgTitle) {
                validateSeoContent({
                    seo_title: currentSeoTitle,
                    seo_description: currentSeoDescription,
                    og_title: currentOgTitle
                });
            }
        }, [currentSeoTitle, currentSeoDescription, currentOgTitle]);

        // Clear manual check result when post title changes
        useEffect(() => {
            if (manualCheckResult && manualCheckResult.title !== postTitle) {
                setManualCheckResult(null);
                setSimilarityCheck({}); // Also clear similarity check
            }
        }, [postTitle, manualCheckResult]);

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
            if (!postId) {
                return;
            }

            try {
                setLoadingTranslations(true);

                const response = await apiFetch({
                    path: `ez-translate/v1/verify-translations/${postId}`,
                    method: 'GET'
                });
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
         * Load API status
         */
        const loadApiStatus = async () => {
            try {
                setLoadingApiStatus(true);

                const response = await apiFetch({
                    path: 'ez-translate/v1/api-status',
                    method: 'GET'
                });

                if (response.success) {
                    setApiStatus(response.data);
                }
            } catch (err) {
                console.error('Failed to load API status:', err);
                // Set default status if API call fails
                setApiStatus({
                    api_enabled: false,
                    has_api_key: false,
                    provider: 'none'
                });
            } finally {
                setLoadingApiStatus(false);
            }
        };

        /**
         * Update post meta field
         */
        const updateMeta = (key, value) => {
            const newMeta = {
                ...postMeta,
                [key]: value
            };

            editPost({
                meta: newMeta
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

            // Check API status and show appropriate message
            let translationMethod = 'copy';
            let warningMessage = '';

            if (apiStatus) {
                if (!apiStatus.api_enabled || !apiStatus.has_api_key) {
                    translationMethod = 'copy';
                    warningMessage = __('AI translation is not available. The content will be copied and you can translate it manually.', 'ez-translate');
                } else {
                    translationMethod = 'ai';
                }
            }

            // Show warning if using fallback method
            if (warningMessage) {
                const shouldContinue = confirm(
                    warningMessage + '\n\n' +
                    __('Do you want to continue?', 'ez-translate')
                );

                if (!shouldContinue) {
                    setCreating(false);
                    return;
                }
            }

            try {
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

                    // Determine the actual method used
                    const actualMethod = response.data.translation_method || 'copy';
                    let successMessage = __('Translation created successfully!', 'ez-translate');

                    // Add method-specific message
                    if (actualMethod === 'copy') {
                        successMessage += '\n\n' + __('Content was copied. You can now edit and translate it manually.', 'ez-translate');
                    } else if (actualMethod === 'ai') {
                        successMessage += '\n\n' + __('Content was translated using AI. Please review and edit as needed.', 'ez-translate');
                    }

                    // Show fallback message if AI was expected but copy was used
                    if (translationMethod === 'ai' && actualMethod === 'copy') {
                        successMessage += '\n\n' + __('Note: AI translation failed, so content was copied instead.', 'ez-translate');
                    }

                    successMessage += '\n\n' + __('Click OK to open the translation in a new window.', 'ez-translate');

                    if (confirm(successMessage)) {
                        // Open in new window instead of redirecting
                        window.open(response.data.edit_url, '_blank');
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
         * Re-translate existing translation
         */
        const retranslateExisting = async (targetLanguage) => {
            // Confirm with user before re-translating
            const confirmMessage = __('Are you sure you want to re-translate this content?', 'ez-translate') + '\n\n' +
                __('This will update the existing translation with new content. The current translation will be overwritten.', 'ez-translate') + '\n\n' +
                __('Target language:', 'ez-translate') + ' ' + targetLanguage.toUpperCase();

            if (!confirm(confirmMessage)) {
                return;
            }

            setCreating(true);
            setError(null);

            // Check API status and show appropriate message
            let translationMethod = 'copy';
            let warningMessage = '';

            if (apiStatus) {
                if (!apiStatus.api_enabled || !apiStatus.has_api_key) {
                    translationMethod = 'copy';
                    warningMessage = __('AI translation is not available. The content will be copied and you can translate it manually.', 'ez-translate');
                } else {
                    translationMethod = 'ai';
                }
            }

            // Show warning if using fallback method
            if (warningMessage) {
                const shouldContinue = confirm(
                    warningMessage + '\n\n' +
                    __('Do you want to continue with re-translation?', 'ez-translate')
                );

                if (!shouldContinue) {
                    setCreating(false);
                    return;
                }
            }

            try {
                // Call the REST API to re-translate (force_retranslate = true)
                const response = await wp.apiFetch({
                    path: `/ez-translate/v1/create-translation/${postId}`,
                    method: 'POST',
                    data: {
                        target_language: targetLanguage,
                        force_retranslate: true
                    }
                });

                if (response.success) {
                    // Reload translations to update the UI
                    await loadExistingTranslations();

                    // Determine the actual method used
                    const actualMethod = response.data.translation_method || 'copy';
                    let successMessage = __('Translation updated successfully!', 'ez-translate');

                    // Add method-specific message
                    if (actualMethod === 'copy') {
                        successMessage += '\n\n' + __('Content was copied. You can now edit and translate it manually.', 'ez-translate');
                    } else if (actualMethod === 'ai') {
                        successMessage += '\n\n' + __('Content was translated using AI. Please review and edit as needed.', 'ez-translate');
                    }

                    // Show fallback message if AI was expected but copy was used
                    if (translationMethod === 'ai' && actualMethod === 'copy') {
                        successMessage += '\n\n' + __('Note: AI translation failed, so content was copied instead.', 'ez-translate');
                    }

                    successMessage += '\n\n' + __('Click OK to open the updated translation in a new window.', 'ez-translate');

                    if (confirm(successMessage)) {
                        // Open in new window instead of redirecting
                        window.open(response.data.edit_url, '_blank');
                    }
                } else {
                    setError(__('Failed to re-translate. Please try again.', 'ez-translate'));
                }

            } catch (err) {
                console.error('Failed to re-translate:', err);

                // Handle specific error cases
                if (err.code === 'invalid_target_language') {
                    setError(__('Invalid target language selected.', 'ez-translate'));
                } else {
                    setError(__('Failed to re-translate. Please try again.', 'ez-translate'));
                }
            } finally {
                setCreating(false);
            }
        };

        /**
         * Handle multi-language selection
         */
        const handleMultiLanguageChange = (languageCode, isChecked) => {
            if (isChecked) {
                setSelectedLanguages(prev => [...prev, languageCode]);
            } else {
                setSelectedLanguages(prev => prev.filter(code => code !== languageCode));
            }
        };

        /**
         * Create multiple translations
         */
        const createMultipleTranslations = async () => {
            if (selectedLanguages.length === 0) {
                setError(__('Please select at least one language for translation.', 'ez-translate'));
                return;
            }

            setCreatingMultiple(true);
            setError(null);
            setMultiTranslationProgress({
                total: selectedLanguages.length,
                completed: 0,
                current: null,
                results: []
            });

            // Check API status and show appropriate message
            let warningMessage = '';
            if (apiStatus && (!apiStatus.api_enabled || !apiStatus.has_api_key)) {
                warningMessage = __('AI translation is not available. All content will be copied and you can translate manually.', 'ez-translate');
            }

            // Show warning if using fallback method
            if (warningMessage) {
                const shouldContinue = confirm(
                    warningMessage + '\n\n' +
                    __('Do you want to continue creating translations for all selected languages?', 'ez-translate')
                );

                if (!shouldContinue) {
                    setCreatingMultiple(false);
                    setMultiTranslationProgress(null);
                    return;
                }
            }

            try {
                // Call the REST API to create multiple translations
                const response = await wp.apiFetch({
                    path: `/ez-translate/v1/create-multiple-translations/${postId}`,
                    method: 'POST',
                    data: {
                        target_languages: selectedLanguages
                    }
                });

                if (response.success) {
                    // Reload translations to update the UI
                    await loadExistingTranslations();

                    const data = response.data;
                    const successCount = data.successful_count;
                    const failedCount = data.failed_count;

                    // Open successful translations in new windows
                    if (data.successful_translations && data.successful_translations.length > 0) {
                        let openMessage = __('Translations created successfully!', 'ez-translate') + '\n\n';
                        openMessage += __('Successful:', 'ez-translate') + ' ' + successCount + '\n';
                        if (failedCount > 0) {
                            openMessage += __('Failed:', 'ez-translate') + ' ' + failedCount + '\n';
                        }
                        openMessage += '\n' + __('Click OK to open all successful translations in new windows.', 'ez-translate');

                        if (confirm(openMessage)) {
                            // Open each successful translation in a new window with a small delay
                            data.successful_translations.forEach((translation, index) => {
                                setTimeout(() => {
                                    window.open(translation.edit_url, '_blank');
                                }, index * 500); // 500ms delay between windows
                            });
                        }
                    }

                    // Show summary
                    let summaryMessage = __('Multi-translation completed!', 'ez-translate') + '\n\n';
                    summaryMessage += __('Total requested:', 'ez-translate') + ' ' + data.total_requested + '\n';
                    summaryMessage += __('Successful:', 'ez-translate') + ' ' + successCount + '\n';
                    summaryMessage += __('Failed:', 'ez-translate') + ' ' + failedCount;

                    if (failedCount > 0) {
                        summaryMessage += '\n\n' + __('Failed languages:', 'ez-translate') + '\n';
                        data.failed_translations.forEach(failure => {
                            summaryMessage += '- ' + failure.language + ': ' + failure.error + '\n';
                        });
                    }

                    alert(summaryMessage);
                } else {
                    setError(__('Failed to create multiple translations. Please try again.', 'ez-translate'));
                }

                // Reset selections
                setSelectedLanguages([]);

            } catch (err) {
                console.error('Failed to create multiple translations:', err);
                setError(__('Failed to create multiple translations. Please try again.', 'ez-translate'));
            } finally {
                setCreatingMultiple(false);
                setMultiTranslationProgress(null);
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
                alert('Database value: ' + JSON.stringify(response.metadata, null, 2));
            } catch (error) {
                alert('Error checking database: ' + error.message);
            }
        };

        /**
         * Generate SEO fields using AI
         */
        const generateSeoFields = async () => {
            setAiLoading(true);
            setAiError(null);

            // Store current values for comparison
            const currentValues = {
                seo_title: currentSeoTitle,
                seo_description: currentSeoDescription,
                og_title: currentOgTitle
            };

            try {
                const response = await apiFetch({
                    path: 'ez-translate/v1/generate-seo',
                    method: 'POST',
                    data: {
                        post_id: postId
                    }
                });

                if (response.success) {
                    // Show comparison if there were previous values
                    const hasCurrentValues = currentValues.seo_title || currentValues.seo_description || currentValues.og_title;

                    if (hasCurrentValues) {
                        const shouldReplace = confirm(
                            __('AI has generated new SEO content. Do you want to replace your current values?', 'ez-translate') + '\n\n' +
                            __('BEFORE:', 'ez-translate') + '\n' +
                            (currentValues.seo_title ? __('Title:', 'ez-translate') + ' ' + currentValues.seo_title + '\n' : '') +
                            (currentValues.seo_description ? __('Description:', 'ez-translate') + ' ' + currentValues.seo_description.substring(0, 60) + '...\n' : '') +
                            (currentValues.og_title ? __('OG Title:', 'ez-translate') + ' ' + currentValues.og_title + '\n' : '') +
                            '\n' + __('AFTER (AI Generated):', 'ez-translate') + '\n' +
                            __('Title:', 'ez-translate') + ' ' + response.data.seo_title + '\n' +
                            __('Description:', 'ez-translate') + ' ' + response.data.seo_description.substring(0, 60) + '...\n' +
                            __('OG Title:', 'ez-translate') + ' ' + response.data.og_title
                        );

                        if (!shouldReplace) {
                            setAiLoading(false);
                            return;
                        }
                    }

                    // Update meta fields with generated content
                    const newMeta = {
                        ...postMeta,
                        '_ez_translate_seo_title': response.data.seo_title,
                        '_ez_translate_seo_description': response.data.seo_description,
                        '_ez_translate_og_title': response.data.og_title
                    };

                    editPost({ meta: newMeta });

                    // Validate the generated content
                    validateSeoContent({
                        seo_title: response.data.seo_title,
                        seo_description: response.data.seo_description,
                        og_title: response.data.og_title
                    });

                    // Clear similarity check since we have new content
                    setSimilarityCheck({});
                } else {
                    setAiError(__('Failed to generate SEO fields. Please try again.', 'ez-translate'));
                }
            } catch (err) {
                console.error('Failed to generate SEO fields:', err);
                setAiError(__('AI service is not available. Please try again later.', 'ez-translate'));
            } finally {
                setAiLoading(false);
            }
        };

        /**
         * Generate shorter version of content
         */
        const generateShorterVersion = async (content, type, maxLength) => {
            setAiLoading(true);
            setAiError(null);

            try {
                const response = await apiFetch({
                    path: 'ez-translate/v1/generate-shorter-seo',
                    method: 'POST',
                    data: {
                        post_id: postId,
                        content: content,
                        type: type,
                        max_length: maxLength
                    }
                });

                if (response.success) {
                    const metaKey = type === 'title' ? '_ez_translate_seo_title' : '_ez_translate_seo_description';
                    updateMeta(metaKey, response.data.shortened_content);
                } else {
                    setAiError(__('Failed to generate shorter version. Please try again.', 'ez-translate'));
                }
            } catch (err) {
                console.error('Failed to generate shorter version:', err);
                setAiError(__('AI service is not available. Please try again later.', 'ez-translate'));
            } finally {
                setAiLoading(false);
            }
        };

        /**
         * Get current title from editor (including unsaved changes)
         */
        const getCurrentEditorTitle = () => {
            // Try to get from Gutenberg data store first (most reliable)
            if (postTitle) {
                return postTitle.trim();
            }

            // Fallback: try to get from DOM if data store is empty
            const titleElement = document.querySelector('.editor-post-title__input, .wp-block-post-title');
            if (titleElement) {
                return titleElement.textContent.trim();
            }

            return '';
        };

        /**
         * Check title similarity
         */
        const checkTitleSimilarity = async (title) => {
            try {
                const response = await apiFetch({
                    path: 'ez-translate/v1/check-title-similarity',
                    method: 'POST',
                    data: {
                        post_id: postId,
                        title: title,
                        threshold: 0.85
                    }
                });

                if (response.success) {
                    setSimilarityCheck(response.data);
                }
            } catch (err) {
                console.error('Failed to check title similarity:', err);
            }
        };

        /**
         * Manual similarity check for current title
         */
        const checkCurrentTitleSimilarity = async () => {
            const currentTitle = getCurrentEditorTitle();

            if (!currentTitle || currentTitle.length < 3) {
                setAiError(__('Please enter a title of at least 3 characters to check for similarities.', 'ez-translate'));
                return;
            }

            setAiLoading(true);
            setAiError(null);
            setManualCheckResult(null);

            try {
                const response = await apiFetch({
                    path: 'ez-translate/v1/check-title-similarity',
                    method: 'POST',
                    data: {
                        post_id: postId,
                        title: currentTitle,
                        threshold: 0.85
                    }
                });

                if (response.success) {
                    setSimilarityCheck(response.data);
                    setManualCheckResult({
                        title: currentTitle,
                        checked: true,
                        timestamp: new Date().toLocaleTimeString()
                    });
                } else {
                    setAiError(__('Failed to check title similarity. Please try again.', 'ez-translate'));
                }
            } catch (err) {
                console.error('Failed to check title similarity:', err);
                setAiError(__('Failed to check title similarity. Please try again.', 'ez-translate'));
            } finally {
                setAiLoading(false);
            }
        };

        /**
         * Generate alternative title suggestions
         */
        const generateAlternativeTitle = async (originalTitle) => {
            setAiLoading(true);
            setAiError(null);

            try {
                const response = await apiFetch({
                    path: 'ez-translate/v1/generate-alternative-title',
                    method: 'POST',
                    data: {
                        post_id: postId,
                        original_title: originalTitle,
                        similar_titles: similarityCheck.similar_titles || []
                    }
                });

                if (response.success && response.data.alternatives.length > 0) {
                    // Show alternatives to user (simple implementation)
                    const alternatives = response.data.alternatives;
                    const choice = prompt(
                        __('AI suggests these alternative titles:', 'ez-translate') + '\n\n' +
                        alternatives.map((alt, index) => `${index + 1}. ${alt}`).join('\n') + '\n\n' +
                        __('Enter the number of your choice (1-3), or cancel to keep current title:', 'ez-translate')
                    );

                    const choiceIndex = parseInt(choice) - 1;
                    if (choiceIndex >= 0 && choiceIndex < alternatives.length) {
                        updateMeta('_ez_translate_seo_title', alternatives[choiceIndex]);
                        setSimilarityCheck({}); // Clear similarity warning
                    }
                } else {
                    setAiError(__('Failed to generate alternative titles. Please try again.', 'ez-translate'));
                }
            } catch (err) {
                console.error('Failed to generate alternative titles:', err);
                setAiError(__('AI service is not available. Please try again later.', 'ez-translate'));
            } finally {
                setAiLoading(false);
            }
        };

        /**
         * Validate SEO content
         */
        const validateSeoContent = (seoData) => {
            const limits = {
                seo_title: 60,
                seo_description: 155,
                og_title: 60
            };

            const validation = {};
            Object.keys(limits).forEach(field => {
                if (seoData[field]) {
                    const length = seoData[field].length;
                    const limit = limits[field];
                    const percentage = (length / limit) * 100;

                    validation[field] = {
                        length: length,
                        limit: limit,
                        percentage: percentage,
                        status: percentage > 100 ? 'error' : percentage > 90 ? 'warning' : 'success'
                    };
                }
            });

            setSeoValidation(validation);
        };

        /**
         * Get validation color for field
         */
        const getValidationColor = (field) => {
            const validation = seoValidation[field];
            if (!validation) return '#666';

            switch (validation.status) {
                case 'error': return '#d63638';
                case 'warning': return '#dba617';
                case 'success': return '#00a32a';
                default: return '#666';
            }
        };

        /**
         * Get character count display
         */
        const getCharacterCount = (content, field) => {
            const validation = seoValidation[field];
            const length = content ? content.length : 0;
            const limit = validation ? validation.limit : 60;

            return `${length}/${limit}`;
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

            // Multi-Translation Panel
            languages.length > 1 && el(PanelBody, {
                title: __('Create Multiple Translations', 'ez-translate'),
                initialOpen: false
            },
                el('div', { style: { marginBottom: '16px' } },
                    el('p', { style: { margin: '0 0 12px 0', fontSize: '13px', color: '#666' } },
                        __('Select multiple languages to create all translations at once:', 'ez-translate')
                    )
                ),

                // Language checkboxes
                el('div', { style: { marginBottom: '16px' } },
                    languages.filter(lang => lang.value !== '').map(language =>
                        el('div', {
                            key: language.value,
                            style: {
                                display: 'flex',
                                alignItems: 'center',
                                marginBottom: '8px',
                                padding: '8px',
                                backgroundColor: selectedLanguages.includes(language.value) ? '#e7f3ff' : '#f9f9f9',
                                border: '1px solid ' + (selectedLanguages.includes(language.value) ? '#72aee6' : '#ddd'),
                                borderRadius: '4px'
                            }
                        },
                            el('input', {
                                type: 'checkbox',
                                id: 'multi-lang-' + language.value,
                                checked: selectedLanguages.includes(language.value),
                                onChange: (e) => handleMultiLanguageChange(language.value, e.target.checked),
                                style: { marginRight: '8px' }
                            }),
                            el('label', {
                                htmlFor: 'multi-lang-' + language.value,
                                style: {
                                    fontSize: '13px',
                                    cursor: 'pointer',
                                    flex: 1,
                                    color: selectedLanguages.includes(language.value) ? '#0073aa' : '#333'
                                }
                            }, language.label)
                        )
                    )
                ),

                // Progress indicator
                multiTranslationProgress && el('div', {
                    style: {
                        marginBottom: '16px',
                        padding: '12px',
                        backgroundColor: '#e7f3ff',
                        border: '1px solid #72aee6',
                        borderRadius: '4px'
                    }
                },
                    el('div', { style: { display: 'flex', alignItems: 'center', marginBottom: '8px' } },
                        el('span', { className: 'dashicons dashicons-update', style: { animation: 'rotation 1s infinite linear', marginRight: '8px', color: '#0073aa' } }),
                        el('strong', { style: { color: '#0073aa' } }, __('Creating Translations...', 'ez-translate'))
                    ),
                    el('div', { style: { fontSize: '12px', color: '#666' } },
                        __('Progress:', 'ez-translate') + ' ' + multiTranslationProgress.completed + '/' + multiTranslationProgress.total
                    )
                ),

                // Create button
                selectedLanguages.length > 0 && el('div', {
                    style: { marginTop: '16px' }
                },
                    el('button', {
                        className: 'components-button is-primary',
                        onClick: createMultipleTranslations,
                        disabled: creatingMultiple,
                        style: { width: '100%', marginBottom: '8px' }
                    },
                        creatingMultiple
                            ? __('Creating Translations...', 'ez-translate')
                            : __('Create All Selected Translations', 'ez-translate') + ' (' + selectedLanguages.length + ')'
                    ),

                    el('p', {
                        style: {
                            margin: '8px 0 0 0',
                            fontSize: '12px',
                            color: '#757575',
                            fontStyle: 'italic'
                        }
                    },
                        __('Each translation will be created one by one and opened in a new window.', 'ez-translate')
                    )
                ),

                // API status warning
                apiStatus && (!apiStatus.api_enabled || !apiStatus.has_api_key) && el('div', {
                    style: {
                        marginTop: '12px',
                        padding: '8px',
                        backgroundColor: '#fff3cd',
                        border: '1px solid #ffeaa7',
                        borderRadius: '4px'
                    }
                },
                    el('div', { style: { display: 'flex', alignItems: 'center' } },
                        el('span', { className: 'dashicons dashicons-info', style: { marginRight: '6px', color: '#856404' } }),
                        el('span', { style: { fontSize: '12px', color: '#856404' } },
                            __('AI translation not available. Content will be copied for manual translation.', 'ez-translate')
                        )
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

                            // Re-translate button for existing translations
                            !translation.is_current && el('button', {
                                className: 'components-button is-primary is-small',
                                onClick: () => retranslateExisting(translation.language),
                                disabled: creating,
                                style: { fontSize: '12px' }
                            }, creating ? __('...', 'ez-translate') : __('Re-translate', 'ez-translate')),

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

            // SEO Metadata Panel with AI (available for all content)
            el(PanelBody, {
                title: __('SEO Metadata with AI', 'ez-translate'),
                initialOpen: true
            },
                // AI Error Notice
                aiError && el(Notice, {
                    status: 'error',
                    isDismissible: true,
                    onRemove: () => setAiError(null),
                    style: { marginBottom: '16px' }
                }, aiError),

                // Post Information
                el('div', {
                    style: {
                        marginBottom: '16px',
                        padding: '10px',
                        backgroundColor: '#f0f6fc',
                        border: '1px solid #c8e1ff',
                        borderRadius: '4px'
                    }
                },
                    el('div', { style: { display: 'flex', alignItems: 'center', marginBottom: '6px' } },
                        el('span', { className: 'dashicons dashicons-admin-post', style: { marginRight: '8px', color: '#0073aa' } }),
                        el('strong', { style: { color: '#0073aa', fontSize: '13px' } }, __('Content Analysis', 'ez-translate'))
                    ),
                    el('div', { style: { fontSize: '12px', color: '#333' } },
                        el('div', { style: { marginBottom: '4px' } },
                            el('span', { style: { fontWeight: 'bold' } }, __('Title:', 'ez-translate') + ' '),
                            el('span', null, postTitle || __('(No title)', 'ez-translate'))
                        ),
                        el('div', { style: { marginBottom: '4px' } },
                            el('span', { style: { fontWeight: 'bold' } }, __('Content Length:', 'ez-translate') + ' '),
                            el('span', null, (postContent ? postContent.length : 0) + ' ' + __('characters', 'ez-translate'))
                        ),
                        currentLanguage && el('div', null,
                            el('span', { style: { fontWeight: 'bold' } }, __('Language:', 'ez-translate') + ' '),
                            el('span', null, currentLanguage)
                        )
                    )
                ),

                // Manual Title Similarity Check
                el('div', {
                    style: {
                        marginBottom: '20px',
                        padding: '12px',
                        backgroundColor: '#f8f9fa',
                        border: '1px solid #dee2e6',
                        borderRadius: '4px'
                    }
                },
                    el('div', { style: { display: 'flex', alignItems: 'center', marginBottom: '10px' } },
                        el('span', { className: 'dashicons dashicons-search', style: { marginRight: '8px', color: '#6c757d' } }),
                        el('strong', { style: { color: '#495057', fontSize: '13px' } }, __('Title Similarity Check', 'ez-translate'))
                    ),

                    el('div', { style: { display: 'flex', gap: '10px', alignItems: 'center', marginBottom: '8px' } },
                        el('button', {
                            className: 'components-button is-secondary',
                            onClick: checkCurrentTitleSimilarity,
                            disabled: aiLoading,
                            style: { flex: '1' }
                        },
                            aiLoading
                                ? el('span', null,
                                    el('span', { className: 'dashicons dashicons-update', style: { animation: 'rotation 1s infinite linear', marginRight: '6px' } }),
                                    __('Checking...', 'ez-translate')
                                  )
                                : el('span', null,
                                    el('span', { className: 'dashicons dashicons-search', style: { marginRight: '6px' } }),
                                    __('Check Current Title', 'ez-translate')
                                  )
                        ),

                        manualCheckResult && el('span', {
                            style: {
                                fontSize: '11px',
                                color: '#6c757d',
                                fontStyle: 'italic'
                            }
                        }, __('Last check:', 'ez-translate') + ' ' + manualCheckResult.timestamp)
                    ),

                    el('p', { style: { margin: '0', fontSize: '11px', color: '#6c757d' } },
                        __('Check if your current title is similar to existing content before saving. Helps prevent SEO cannibalization.', 'ez-translate')
                    ),

                    // Manual check result
                    manualCheckResult && !similarityCheck.is_similar && el('div', {
                        style: {
                            marginTop: '8px',
                            padding: '8px',
                            backgroundColor: '#d1edff',
                            border: '1px solid #0073aa',
                            borderRadius: '3px'
                        }
                    },
                        el('div', { style: { display: 'flex', alignItems: 'center' } },
                            el('span', { className: 'dashicons dashicons-yes-alt', style: { color: '#00a32a', marginRight: '6px' } }),
                            el('span', { style: { fontSize: '12px', color: '#0073aa', fontWeight: 'bold' } },
                                __('✓ Title is unique!', 'ez-translate')
                            )
                        ),
                        el('p', { style: { margin: '4px 0 0 0', fontSize: '11px', color: '#0073aa' } },
                            __('No similar titles found. This title should work well for SEO.', 'ez-translate')
                        )
                    ),

                    // Similarity Warning (right after manual check)
                    similarityCheck.is_similar && el('div', {
                        style: {
                            marginTop: '12px',
                            padding: '12px',
                            backgroundColor: '#fff3cd',
                            border: '1px solid #ffeaa7',
                            borderRadius: '4px'
                        }
                    },
                        el('div', { style: { display: 'flex', alignItems: 'center', marginBottom: '8px' } },
                            el('span', { className: 'dashicons dashicons-warning', style: { color: '#856404', marginRight: '8px' } }),
                            el('strong', { style: { color: '#856404' } }, __('⚠️ Similar Title Detected', 'ez-translate'))
                        ),
                        el('p', { style: { margin: '0 0 8px 0', fontSize: '13px', color: '#856404' } },
                            __('Your title is similar to existing content. This may cause SEO cannibalization.', 'ez-translate')
                        ),
                        el('div', { style: { marginBottom: '8px' } },
                            el('p', { style: { margin: '0 0 4px 0', fontSize: '12px', color: '#6c757d' } },
                                __('Similarity score:', 'ez-translate') + ' ' + Math.round(similarityCheck.similarity_score * 100) + '%'
                            ),
                            similarityCheck.similar_posts && similarityCheck.similar_posts.length > 0 && el('div', { style: { margin: '4px 0' } },
                                el('p', { style: { margin: '0 0 6px 0', fontSize: '12px', color: '#6c757d', fontWeight: 'bold' } },
                                    __('Similar content found:', 'ez-translate')
                                ),
                                ...similarityCheck.similar_posts.slice(0, 3).map((post, index) =>
                                    el('div', {
                                        key: index,
                                        style: {
                                            margin: '3px 0',
                                            padding: '6px 8px',
                                            backgroundColor: '#f8f9fa',
                                            border: '1px solid #e9ecef',
                                            borderRadius: '3px',
                                            fontSize: '11px'
                                        }
                                    },
                                        el('div', { style: { marginBottom: '3px' } },
                                            el('span', { style: { color: '#495057', fontWeight: 'bold' } }, post.title),
                                            el('span', {
                                                style: {
                                                    marginLeft: '6px',
                                                    fontSize: '10px',
                                                    color: '#6c757d',
                                                    textTransform: 'uppercase'
                                                }
                                            }, post.type)
                                        ),
                                        el('div', { style: { display: 'flex', gap: '8px' } },
                                            el('a', {
                                                href: post.url,
                                                target: '_blank',
                                                style: {
                                                    color: '#0073aa',
                                                    textDecoration: 'none',
                                                    fontSize: '10px'
                                                },
                                                title: __('View in frontend (new tab)', 'ez-translate')
                                            }, '🔗 ' + __('View', 'ez-translate')),
                                            el('a', {
                                                href: post.edit_url,
                                                target: '_blank',
                                                style: {
                                                    color: '#d63638',
                                                    textDecoration: 'none',
                                                    fontSize: '10px'
                                                },
                                                title: __('Edit post (new tab)', 'ez-translate')
                                            }, '✏️ ' + __('Edit', 'ez-translate'))
                                        )
                                    )
                                ),
                                similarityCheck.similar_posts && similarityCheck.similar_posts.length > 3 && el('p', {
                                    style: { margin: '4px 0 0 0', fontSize: '11px', color: '#6c757d', fontStyle: 'italic' }
                                }, __('...and', 'ez-translate') + ' ' + (similarityCheck.similar_posts.length - 3) + ' ' + __('more similar posts', 'ez-translate'))
                            )
                        ),
                        el('div', { style: { display: 'flex', gap: '8px', marginTop: '8px' } },
                            el('button', {
                                className: 'components-button is-small is-tertiary',
                                onClick: () => generateAlternativeTitle(getCurrentEditorTitle()),
                                disabled: aiLoading
                            }, __('💡 Suggest Alternative', 'ez-translate')),

                            el('button', {
                                className: 'components-button is-small is-link',
                                onClick: checkCurrentTitleSimilarity,
                                disabled: aiLoading
                            }, __('🔄 Recheck', 'ez-translate'))
                        )
                    )
                ),

                // Generate All SEO Fields Button
                el('div', { style: { marginBottom: '20px', textAlign: 'center' } },
                    el('button', {
                        className: 'components-button is-primary',
                        onClick: generateSeoFields,
                        disabled: aiLoading,
                        style: { width: '100%', marginBottom: '8px' }
                    },
                        aiLoading
                            ? el('span', null,
                                el('span', { className: 'dashicons dashicons-update', style: { animation: 'rotation 1s infinite linear', marginRight: '8px' } }),
                                __('Generating with AI...', 'ez-translate')
                              )
                            : el('span', null,
                                el('span', { className: 'dashicons dashicons-robot', style: { marginRight: '8px' } }),
                                __('Generate All SEO Fields with AI', 'ez-translate')
                              )
                    ),
                    el('p', { style: { margin: '0', fontSize: '11px', color: '#666', fontStyle: 'italic' } },
                        __('AI will analyze your content and generate optimized SEO title, description, and social media title.', 'ez-translate')
                    )
                ),

                // Current Values Preview (if any exist)
                (currentSeoTitle || currentSeoDescription || currentOgTitle) && el('div', {
                    style: {
                        marginBottom: '20px',
                        padding: '12px',
                        backgroundColor: '#f8f9fa',
                        border: '1px solid #e9ecef',
                        borderRadius: '4px'
                    }
                },
                    el('div', { style: { display: 'flex', alignItems: 'center', marginBottom: '8px' } },
                        el('span', { className: 'dashicons dashicons-visibility', style: { marginRight: '8px', color: '#0073aa' } }),
                        el('strong', { style: { color: '#0073aa' } }, __('Current SEO Values', 'ez-translate'))
                    ),

                    currentSeoTitle && el('div', { style: { marginBottom: '6px' } },
                        el('span', { style: { fontSize: '12px', fontWeight: 'bold', color: '#666' } }, __('SEO Title:', 'ez-translate') + ' '),
                        el('span', { style: { fontSize: '12px', color: '#333' } }, currentSeoTitle),
                        el('span', { style: { fontSize: '11px', color: '#999', marginLeft: '8px' } }, '(' + currentSeoTitle.length + ' chars)')
                    ),

                    currentSeoDescription && el('div', { style: { marginBottom: '6px' } },
                        el('span', { style: { fontSize: '12px', fontWeight: 'bold', color: '#666' } }, __('SEO Description:', 'ez-translate') + ' '),
                        el('span', { style: { fontSize: '12px', color: '#333' } }, currentSeoDescription.length > 80 ? currentSeoDescription.substring(0, 80) + '...' : currentSeoDescription),
                        el('span', { style: { fontSize: '11px', color: '#999', marginLeft: '8px' } }, '(' + currentSeoDescription.length + ' chars)')
                    ),

                    currentOgTitle && el('div', { style: { marginBottom: '6px' } },
                        el('span', { style: { fontSize: '12px', fontWeight: 'bold', color: '#666' } }, __('OG Title:', 'ez-translate') + ' '),
                        el('span', { style: { fontSize: '12px', color: '#333' } }, currentOgTitle),
                        el('span', { style: { fontSize: '11px', color: '#999', marginLeft: '8px' } }, '(' + currentOgTitle.length + ' chars)')
                    ),

                    el('p', { style: { margin: '8px 0 0 0', fontSize: '11px', color: '#666', fontStyle: 'italic' } },
                        __('These are your current values. AI will generate new optimized versions.', 'ez-translate')
                    )
                ),

                // Empty State Message (when no SEO values exist)
                (!currentSeoTitle && !currentSeoDescription && !currentOgTitle) && el('div', {
                    style: {
                        marginBottom: '20px',
                        padding: '12px',
                        backgroundColor: '#fff3cd',
                        border: '1px solid #ffeaa7',
                        borderRadius: '4px'
                    }
                },
                    el('div', { style: { display: 'flex', alignItems: 'center', marginBottom: '8px' } },
                        el('span', { className: 'dashicons dashicons-lightbulb', style: { marginRight: '8px', color: '#856404' } }),
                        el('strong', { style: { color: '#856404' } }, __('No SEO Content Yet', 'ez-translate'))
                    ),
                    el('p', { style: { margin: '0', fontSize: '12px', color: '#856404' } },
                        __('This content doesn\'t have SEO metadata yet. Use the AI button above to generate optimized SEO title, description, and social media title automatically.', 'ez-translate')
                    )
                ),

                // SEO Title Field
                el('div', { style: { marginBottom: '20px' } },
                    el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '4px' } },
                        el('label', { style: { fontWeight: 'bold', color: getValidationColor('seo_title') } },
                            __('SEO Title', 'ez-translate')
                        ),
                        el('span', { style: { fontSize: '12px', color: getValidationColor('seo_title') } },
                            getCharacterCount(currentSeoTitle, 'seo_title')
                        )
                    ),
                    el(TextControl, {
                        value: currentSeoTitle,
                        onChange: (value) => {
                            updateMeta('_ez_translate_seo_title', value);
                            validateSeoContent({ seo_title: value, seo_description: currentSeoDescription, og_title: currentOgTitle });
                            if (value.length > 10) checkTitleSimilarity(value);
                        },
                        placeholder: __('Enter SEO title...', 'ez-translate'),
                        style: { borderColor: getValidationColor('seo_title') }
                    }),
                    el('div', { style: { display: 'flex', gap: '8px', marginTop: '8px' } },
                        seoValidation.seo_title && seoValidation.seo_title.status === 'error' && el('button', {
                            className: 'components-button is-small is-secondary',
                            onClick: () => generateShorterVersion(currentSeoTitle, 'title', 60),
                            disabled: aiLoading
                        }, __('✂️ Make Shorter', 'ez-translate')),

                        similarityCheck.is_similar && el('button', {
                            className: 'components-button is-small is-tertiary',
                            onClick: () => generateAlternativeTitle(currentSeoTitle),
                            disabled: aiLoading
                        }, __('💡 Suggest Alternative', 'ez-translate'))
                    ),
                    el('p', { style: { margin: '4px 0 0 0', fontSize: '11px', color: '#666' } },
                        __('Recommended: 50-60 characters for optimal display in search results.', 'ez-translate')
                    )
                ),

                // SEO Description Field
                el('div', { style: { marginBottom: '20px' } },
                    el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '4px' } },
                        el('label', { style: { fontWeight: 'bold', color: getValidationColor('seo_description') } },
                            __('SEO Description', 'ez-translate')
                        ),
                        el('span', { style: { fontSize: '12px', color: getValidationColor('seo_description') } },
                            getCharacterCount(currentSeoDescription, 'seo_description')
                        )
                    ),
                    el(TextareaControl, {
                        value: currentSeoDescription,
                        onChange: (value) => {
                            updateMeta('_ez_translate_seo_description', value);
                            validateSeoContent({ seo_title: currentSeoTitle, seo_description: value, og_title: currentOgTitle });
                        },
                        placeholder: __('Enter SEO description...', 'ez-translate'),
                        rows: 3,
                        style: { borderColor: getValidationColor('seo_description') }
                    }),
                    seoValidation.seo_description && seoValidation.seo_description.status === 'error' && el('div', { style: { marginTop: '8px' } },
                        el('button', {
                            className: 'components-button is-small is-secondary',
                            onClick: () => generateShorterVersion(currentSeoDescription, 'description', 155),
                            disabled: aiLoading
                        }, __('✂️ Make Shorter', 'ez-translate'))
                    ),
                    el('p', { style: { margin: '4px 0 0 0', fontSize: '11px', color: '#666' } },
                        __('Recommended: 150-155 characters for best search engine results.', 'ez-translate')
                    )
                ),

                // OG Title Field
                el('div', { style: { marginBottom: '20px' } },
                    el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '4px' } },
                        el('label', { style: { fontWeight: 'bold', color: getValidationColor('og_title') } },
                            __('Social Media Title (OG Title)', 'ez-translate')
                        ),
                        el('span', { style: { fontSize: '12px', color: getValidationColor('og_title') } },
                            getCharacterCount(currentOgTitle, 'og_title')
                        )
                    ),
                    el(TextControl, {
                        value: currentOgTitle,
                        onChange: (value) => {
                            updateMeta('_ez_translate_og_title', value);
                            validateSeoContent({ seo_title: currentSeoTitle, seo_description: currentSeoDescription, og_title: value });
                        },
                        placeholder: __('Enter social media title...', 'ez-translate'),
                        style: { borderColor: getValidationColor('og_title') }
                    }),
                    seoValidation.og_title && seoValidation.og_title.status === 'error' && el('div', { style: { marginTop: '8px' } },
                        el('button', {
                            className: 'components-button is-small is-secondary',
                            onClick: () => generateShorterVersion(currentOgTitle, 'title', 60),
                            disabled: aiLoading
                        }, __('✂️ Make Shorter', 'ez-translate'))
                    ),
                    el('p', { style: { margin: '4px 0 0 0', fontSize: '11px', color: '#666' } },
                        __('Used when sharing on social media. Defaults to SEO title if empty.', 'ez-translate')
                    )
                ),

                // Info Box
                el('div', { style: { marginTop: '16px', padding: '12px', backgroundColor: '#f0f0f0', borderRadius: '4px' } },
                    el('div', { style: { display: 'flex', alignItems: 'center', marginBottom: '8px' } },
                        el('span', { className: 'dashicons dashicons-info', style: { marginRight: '8px', color: '#0073aa' } }),
                        el('strong', { style: { color: '#0073aa' } }, __('AI-Powered SEO', 'ez-translate'))
                    ),
                    el('p', { style: { margin: '0', fontSize: '12px', color: '#666' } },
                        __('These SEO fields are enhanced with AI to help you create optimized content. The system checks character limits, similarity with existing content, and provides intelligent suggestions.', 'ez-translate')
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

    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes rotation {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .ez-translate-ai-loading {
            animation: rotation 1s infinite linear;
        }
    `;
    document.head.appendChild(style);

})();
