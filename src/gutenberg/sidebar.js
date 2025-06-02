/**
 * EZ Translate Gutenberg Sidebar
 * 
 * @package EZTranslate
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { 
    PanelBody, 
    SelectControl, 
    ToggleControl, 
    TextControl, 
    TextareaControl,
    Notice,
    Spinner
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * EZ Translate Sidebar Component
 */
const EZTranslateSidebar = () => {
    // State management
    const [languages, setLanguages] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);

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
                path: '/ez-translate/v1/languages',
                method: 'GET'
            });

            // Format languages for SelectControl
            const formattedLanguages = [
                { value: '', label: __('Select Language', 'ez-translate') }
            ];

            Object.entries(response).forEach(([code, language]) => {
                if (language.enabled) {
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
        setSuccess(null);
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
     * Handle language change
     */
    const handleLanguageChange = (newLanguage) => {
        updateMeta('_ez_translate_language', newLanguage);
        
        // Auto-generate group ID if not exists and language is selected
        if (newLanguage && !currentGroup) {
            const newGroupId = generateGroupId();
            updateMeta('_ez_translate_group', newGroupId);
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
        return (
            <div style={{ padding: '16px', textAlign: 'center' }}>
                <Spinner />
                <p>{__('Loading languages...', 'ez-translate')}</p>
            </div>
        );
    }

    return (
        <div>
            {/* Error Notice */}
            {error && (
                <Notice status="error" isDismissible onRemove={() => setError(null)}>
                    {error}
                </Notice>
            )}

            {/* Success Notice */}
            {success && (
                <Notice status="success" isDismissible onRemove={() => setSuccess(null)}>
                    {success}
                </Notice>
            )}

            {/* Language Selection Panel */}
            <PanelBody 
                title={__('Language Settings', 'ez-translate')} 
                initialOpen={true}
            >
                <SelectControl
                    label={__('Page Language', 'ez-translate')}
                    value={currentLanguage}
                    options={languages}
                    onChange={handleLanguageChange}
                    help={__('Select the language for this page.', 'ez-translate')}
                />

                {currentLanguage && (
                    <TextControl
                        label={__('Translation Group ID', 'ez-translate')}
                        value={currentGroup}
                        onChange={(value) => updateMeta('_ez_translate_group', value)}
                        help={__('Pages with the same group ID are translations of each other.', 'ez-translate')}
                        placeholder="tg_xxxxxxxxxxxxxxxx"
                    />
                )}
            </PanelBody>

            {/* Landing Page Panel */}
            {currentLanguage && (
                <PanelBody 
                    title={__('Landing Page Settings', 'ez-translate')} 
                    initialOpen={false}
                >
                    <ToggleControl
                        label={__('Landing Page', 'ez-translate')}
                        checked={currentIsLanding}
                        onChange={handleLandingToggle}
                        help={__('Mark this page as the landing page for this language.', 'ez-translate')}
                    />

                    {currentIsLanding && (
                        <>
                            <TextControl
                                label={__('SEO Title', 'ez-translate')}
                                value={currentSeoTitle}
                                onChange={(value) => updateMeta('_ez_translate_seo_title', value)}
                                help={__('Custom SEO title for this landing page.', 'ez-translate')}
                            />

                            <TextareaControl
                                label={__('SEO Description', 'ez-translate')}
                                value={currentSeoDescription}
                                onChange={(value) => updateMeta('_ez_translate_seo_description', value)}
                                help={__('Custom SEO description for this landing page.', 'ez-translate')}
                                rows={3}
                            />
                        </>
                    )}
                </PanelBody>
            )}

            {/* Debug Info Panel (only in development) */}
            {window.ezTranslateGutenberg && window.ezTranslateGutenberg.debug && (
                <PanelBody 
                    title={__('Debug Info', 'ez-translate')} 
                    initialOpen={false}
                >
                    <p><strong>Post ID:</strong> {postId}</p>
                    <p><strong>Current Language:</strong> {currentLanguage || 'None'}</p>
                    <p><strong>Group ID:</strong> {currentGroup || 'None'}</p>
                    <p><strong>Is Landing:</strong> {currentIsLanding ? 'Yes' : 'No'}</p>
                </PanelBody>
            )}
        </div>
    );
};

/**
 * Register the plugin
 */
registerPlugin('ez-translate-sidebar', {
    render: () => (
        <>
            <PluginSidebarMoreMenuItem target="ez-translate-sidebar">
                {__('EZ Translate', 'ez-translate')}
            </PluginSidebarMoreMenuItem>
            <PluginSidebar
                name="ez-translate-sidebar"
                title={__('EZ Translate', 'ez-translate')}
                icon="translation"
            >
                <EZTranslateSidebar />
            </PluginSidebar>
        </>
    ),
});
