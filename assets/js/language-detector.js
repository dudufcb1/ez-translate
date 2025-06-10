/**
 * EZ Translate Language Detector
 * 
 * Handles browser language detection and intelligent redirection
 * 
 * @package EZTranslate
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * EZ Language Detector Class
     */
    class EZLanguageDetector {
        constructor() {
            this.config = window.ezTranslateDetector || {};
            this.isInitialized = false;
            this.detector = null;
            this.translator = null;
            this.container = null;
            this.currentMode = 'fold'; // fold, unfold, helper

            // localStorage keys
            this.storageKeys = {
                userLanguage: 'ez_translate_user_language',
                freeNavigation: 'ez_translate_free_navigation',
                detectorDismissed: 'ez_translate_detector_dismissed',
                userChoice: 'ez_translate_user_choice' // 'language', 'free', 'dismissed'
            };

            this.init();
        }

        /**
         * Initialize the detector
         */
        init() {
            if (this.isInitialized || !this.config.enabled) {
                return;
            }

            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.start());
            } else {
                this.start();
            }
        }

        /**
         * Start the detection process
         */
        async start() {
            this.isInitialized = true;

            console.log('[EZ Translate] Language detector started', this.config);

            // Clean up redundant localStorage values
            this.cleanupRedundantStorage();

            // Get browser language
            const browserLanguage = this.getBrowserLanguage();
            const currentLanguage = this.config.currentLanguage;

            console.log('[EZ Translate] Browser language:', browserLanguage, 'Current language:', currentLanguage);

            // Check if user should be restricted to their chosen language
            const userChoice = this.getUserChoice(); // 'language', 'free', 'dismissed', null
            if (this.shouldRestrictNavigation(userChoice, currentLanguage)) {
                return; // Exit early if user was redirected
            }

            // Load detector data with translations info
            const detectorData = await this.loadDetectorData();

            if (!detectorData) {
                console.log('[EZ Translate] Failed to load detector data');
                return;
            }

            // Store available translations for later use
            this.availableTranslations = detectorData.available_translations || [];
            this.hasTranslations = detectorData.has_translations || false;

            console.log('[EZ Translate] Available translations:', this.availableTranslations);
            console.log('[EZ Translate] User choice:', userChoice);

            // Determine what to show based on translations and user preferences
            this.determineDetectorMode(browserLanguage, currentLanguage, userChoice)
        }

        /**
         * Check if user should be restricted to their chosen language
         */
        shouldRestrictNavigation(userChoice, currentLanguage) {
            // Si userChoice es un código de idioma (ej: 'en', 'es', etc) y restrict_navigation está activo
            if (!this.config.config.restrict_navigation) {
                return false;
            }
            // Solo restringir si userChoice es un código de idioma válido
            const availableCodes = this.config.availableLanguages.map(l => l.code);
            if (!userChoice || !availableCodes.includes(userChoice)) {
                return false;
            }
            // Si el usuario está en una página de otro idioma, redirigir
            if (userChoice !== currentLanguage) {
                console.log('[EZ Translate] User restricted to language:', userChoice, 'but on:', currentLanguage, '- redirecting');
                this.redirectToUserLanguage(userChoice);
                return true;
            }
            return false;
        }

        /**
         * Setup navigation interception for restricted users
         */
        setupNavigationInterception() {
            const userChoice = this.getUserChoice();
            const availableCodes = this.config.availableLanguages.map(l => l.code);

            // Only setup interception if user is restricted to a specific language
            if (!this.config.config.restrict_navigation || !userChoice || !availableCodes.includes(userChoice)) {
                return;
            }

            console.log('[EZ Translate] Setting up navigation interception for restricted user:', userChoice);

            // Intercept all link clicks
            document.addEventListener('click', async (e) => {
                const link = e.target.closest('a[href]');
                if (!link) return;

                const href = link.getAttribute('href');

                // Skip external links, anchors, and admin links
                if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') ||
                    href.includes('wp-admin') || href.includes('wp-login') ||
                    href.startsWith('http') && !href.includes(window.location.hostname)) {
                    return;
                }

                // Get the target URL
                let targetUrl;
                if (href.startsWith('/')) {
                    targetUrl = window.location.origin + href;
                } else if (href.startsWith('http')) {
                    targetUrl = href;
                } else {
                    targetUrl = new URL(href, window.location.href).href;
                }

                // Extract post ID from target URL
                const targetPostId = this.extractPostIdFromUrl(targetUrl);

                if (targetPostId) {
                    console.log('[EZ Translate] Intercepting navigation to:', targetUrl, 'Post ID:', targetPostId);

                    // Prevent default navigation
                    e.preventDefault();

                    // Check if target post has translation in user's language
                    const translation = await this.findTranslationForPost(targetPostId, userChoice);

                    if (translation) {
                        console.log('[EZ Translate] Redirecting to translation:', translation.url);
                        window.location.href = translation.url;
                    } else {
                        console.log('[EZ Translate] No translation found, redirecting to landing page');
                        this.redirectToUserLanguageLanding(userChoice);
                    }
                }
            });
        }

        /**
         * Redirect to user's language landing page using slug-based URL
         */
        async redirectToUserLanguageLanding(userLanguage) {
            try {
                // First, try to find landing page from available translations (which includes URLs)
                const landingTranslation = this.findLandingPageInTranslations(userLanguage);
                if (landingTranslation) {
                    console.log('[EZ Translate] Redirecting to landing page via translation data:', landingTranslation.url);
                    window.location.href = landingTranslation.url;
                    return;
                }

                // If not found in translations, try to get landing page URL via API
                const landingUrl = await this.getLandingPageUrl(userLanguage);
                if (landingUrl) {
                    console.log('[EZ Translate] Redirecting to landing page via API:', landingUrl);
                    window.location.href = landingUrl;
                    return;
                }

                // Special case for Spanish - redirect to home page
                if (userLanguage === 'es') {
                    console.log('[EZ Translate] Redirecting to home page for Spanish');
                    window.location.href = this.config.homeUrl;
                    return;
                }

                // Fallback to home page
                console.log('[EZ Translate] No landing page found, redirecting to home page');
                window.location.href = this.config.homeUrl;

            } catch (error) {
                console.error('[EZ Translate] Error during landing page redirection:', error);
                window.location.href = this.config.homeUrl;
            }
        }

        /**
         * Redirect user to their chosen language
         */
        async redirectToUserLanguage(userLanguage) {
            try {
                // Get the current URL to determine target post
                const currentUrl = window.location.href;
                const targetPostId = this.extractPostIdFromUrl(currentUrl);

                console.log('[EZ Translate] Redirecting user to language:', userLanguage, 'from URL:', currentUrl, 'Post ID:', targetPostId);

                // If we can identify the target post, check for its translations
                if (targetPostId) {
                    const targetTranslation = await this.findTranslationForPost(targetPostId, userLanguage);
                    if (targetTranslation) {
                        console.log('[EZ Translate] Found translation for target post:', targetTranslation.url);
                        window.location.href = targetTranslation.url;
                        return;
                    }
                }

                // Fallback: Try to find a translation of current page in user's language
                const translation = this.findTranslationInData(userLanguage);

                if (translation) {
                    console.log('[EZ Translate] Redirecting to user language translation:', translation.url);
                    window.location.href = translation.url;
                    return;
                }

                // If no translation, redirect to landing page for that language
                const landingTranslation = this.findLandingPageInTranslations(userLanguage);
                if (landingTranslation) {
                    console.log('[EZ Translate] Redirecting to landing page via translation data:', landingTranslation.url);
                    window.location.href = landingTranslation.url;
                    return;
                }

                // If not found in translations, try to get landing page URL via API
                const landingUrl = await this.getLandingPageUrl(userLanguage);
                if (landingUrl) {
                    console.log('[EZ Translate] Redirecting to landing page via API:', landingUrl);
                    window.location.href = landingUrl;
                    return;
                }

                // Special case for Spanish - redirect to home page
                if (userLanguage === 'es') {
                    console.log('[EZ Translate] Redirecting to home page for Spanish');
                    window.location.href = this.config.homeUrl;
                    return;
                }

                // Fallback to home page
                console.log('[EZ Translate] No translation found, redirecting to home page');
                window.location.href = this.config.homeUrl;

            } catch (error) {
                console.error('[EZ Translate] Error during user language redirection:', error);
                window.location.href = this.config.homeUrl;
            }
        }

        /**
         * Clean up redundant localStorage values and migrate to userChoice system
         */
        cleanupRedundantStorage() {
            const userChoice = this.getUserChoice();
            const userLanguage = this.getUserLanguage();
            const freeNavigation = this.getFreeNavigation();
            const detectorDismissed = this.getDetectorDismissed();

            // If userChoice doesn't exist but other values do, migrate them
            if (!userChoice) {
                if (userLanguage) {
                    // User had selected a language
                    this.setUserChoice('language');
                    console.log('[EZ Translate] Migrated user language choice to new system');
                } else if (freeNavigation) {
                    // User had chosen free navigation
                    this.setUserChoice('free');
                    console.log('[EZ Translate] Migrated free navigation choice to new system');
                } else if (detectorDismissed) {
                    // User had dismissed the detector
                    this.setUserChoice('dismissed');
                    console.log('[EZ Translate] Migrated dismissed state to new system');
                }
            }

            // Clean up old redundant values (keep userLanguage as it's still needed for redirection)
            if (userChoice) {
                localStorage.removeItem(this.storageKeys.freeNavigation);
                localStorage.removeItem(this.storageKeys.detectorDismissed);
                console.log('[EZ Translate] Cleaned up redundant localStorage values');
            }
        }

        /**
         * Determine which detector mode to show based on user preferences
         */
        determineDetectorMode(browserLanguage, currentLanguage, userChoice) {
            console.log('[EZ Translate] Determining detector mode:', {
                browserLanguage,
                currentLanguage,
                userChoice,
                hasTranslations: this.hasTranslations,
                hasLandingPages: this.hasLandingPages()
            });

            // Remove any existing translator first
            this.removeTranslator();

            // Si no hay landing pages configuradas, no mostrar nada
            if (!this.hasLandingPages()) {
                console.log('[EZ Translate] No landing pages configured, not showing detector');
                return;
            }

            const availableCodes = this.config.availableLanguages.map(l => l.code);

            if (!userChoice) {
                // Primera visita - verificar si debe mostrarse automáticamente
                if (this.shouldShowDetectorAutomatically(browserLanguage, currentLanguage)) {
                    this.showDetector('unfold', browserLanguage);
                    this.showTranslator(browserLanguage);
                } else {
                    // No mostrar automáticamente, solo el botón minimizado
                    this.showDetector('minimized');
                    this.showTranslator(browserLanguage);
                }
            } else if (availableCodes.includes(userChoice)) {
                // Si userChoice es un código de idioma válido (ej: 'en', 'es', etc)
                // Mostrar siempre el minimizado para poder cambiar idioma
                this.showDetector('minimized');
                // No mostrar traductor si navegación está restringida

                // Setup navigation interception for restricted users
                this.setupNavigationInterception();
            } else if (userChoice === 'free' || userChoice === 'dismissed') {
                // Mostrar traductor siempre en estos estados
                this.showDetector('minimized');
                this.showTranslator(browserLanguage);
            }
        }

        /**
         * Check if there are landing pages configured
         */
        hasLandingPages() {
            // Check if any language has a landing page configured
            return this.config.availableLanguages.some(lang => lang.landing_page_id);
        }

        /**
         * Check if detector should show automatically on first visit
         */
        shouldShowDetectorAutomatically(browserLanguage, currentLanguage) {
            // No mostrar si el usuario está en su idioma nativo
            if (browserLanguage === currentLanguage) {
                console.log('[EZ Translate] User is in their native language, not showing detector automatically');
                return false;
            }

            // No mostrar si no hay idiomas disponibles diferentes al actual
            const otherLanguages = this.config.availableLanguages.filter(lang =>
                lang.code !== currentLanguage && lang.landing_page_id
            );

            if (otherLanguages.length === 0) {
                console.log('[EZ Translate] No other languages with landing pages available');
                return false;
            }

            // Mostrar si el idioma del navegador tiene landing page disponible
            const browserLangHasLanding = otherLanguages.some(lang => lang.code === browserLanguage);
            if (browserLangHasLanding) {
                console.log('[EZ Translate] Browser language has landing page, showing detector');
                return true;
            }

            // No mostrar automáticamente en otros casos
            console.log('[EZ Translate] Browser language does not have landing page, not showing automatically');
            return false;
        }

        /**
         * Create or get the main container for detector elements
         */
        ensureContainer() {
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.className = `ez-language-container ez-detector-${this.config.config.position}`;
                this.container.id = 'ez-language-container';
                document.body.appendChild(this.container);
            }
            return this.container;
        }

        /**
         * Remove the main container
         */
        removeContainer() {
            if (this.container) {
                this.container.remove();
                this.container = null;
                this.detector = null;
                this.translator = null;
            }
        }

        /**
         * Show translator (small button to help with translations)
         */
        showTranslator(targetLanguage) {
            // Only show if there are translations available
            if (!this.hasTranslations || !this.availableTranslations || this.availableTranslations.length === 0) {
                console.log('[EZ Translate] Not showing translator - no translations available');
                return;
            }

            // Check if there are translations other than current language
            const otherTranslations = this.availableTranslations.filter(t => t.language_code !== this.config.currentLanguage);
            if (otherTranslations.length === 0) {
                console.log('[EZ Translate] Not showing translator - no other language translations');
                return;
            }

            // Remove any existing translator
            this.removeTranslator();

            // Ensure container exists
            const container = this.ensureContainer();

            // Use the first available translation as primary target for the tab display
            const primaryTarget = targetLanguage || otherTranslations[0].language_code;

            // Create translator element
            this.translator = document.createElement('div');
            this.translator.className = `ez-language-detector ez-detector-helper`;
            this.translator.id = 'ez-language-translator';
            this.translator.innerHTML = this.createTranslatorHTML(primaryTarget);

            // Add to container
            container.appendChild(this.translator);

            // Add event listeners
            this.attachTranslatorEventListeners(this.translator);

            // Show translator
            this.translator.classList.add('ez-detector-visible');

            console.log('[EZ Translate] Translator shown with', otherTranslations.length, 'available translations');
        }

        /**
         * Remove translator from page
         */
        removeTranslator() {
            if (this.translator) {
                this.translator.remove();
                this.translator = null;
                console.log('[EZ Translate] Translator removed');
            }
        }

        /**
         * Create translator HTML (dropdown with language options)
         */
        createTranslatorHTML(targetLanguage) {
            const targetLang = this.getLanguageData(targetLanguage);
            const messages = this.getMessages(this.config.currentLanguage);

            return `
                <div class="ez-detector-tab ez-translator-tab" title="${messages.translation_available || 'Read this article in'} ${targetLang.native_name || targetLang.name}">
                    <span class="ez-translator-minimized-icon">
                        <span class="dashicons dashicons-translation"></span>
                    </span>
                    <span class="ez-translator-expanded-content">
                        <span class="ez-detector-flag">${targetLang.flag || '🌐'}</span>
                        <span class="ez-detector-text">${messages.translation_available || 'Read this article in'}</span>
                    </span>
                </div>
                <div class="ez-detector-dropdown">
                    <div class="ez-detector-title">${messages.dropdown_title}</div>
                    ${this.createTranslatorLanguageList(targetLanguage)}
                </div>
            `;
        }

        /**
         * Attach event listeners to translator element
         */
        attachTranslatorEventListeners(translator) {
            // Handle language selection from translator dropdown
            translator.addEventListener('click', async (e) => {
                if (e.target.closest('.ez-translator-lang-item')) {
                    const langItem = e.target.closest('.ez-translator-lang-item');
                    const language = langItem.dataset.language;
                    await this.handleSwitch(language);
                }
            });
        }

        /**
         * Attach event listeners to helper element
         */
        attachHelperEventListeners(helper) {
            helper.addEventListener('click', async (e) => {
                const action = e.target.dataset.action;
                const language = e.target.dataset.language;

                if (action === 'switch') {
                    await this.handleSwitch(language);
                }
            });
        }

        /**
         * Load detector data from API
         */
        async loadDetectorData() {
            try {
                const response = await fetch(`${this.config.restUrl}language-detector?post_id=${this.config.postId}`);

                if (response.ok) {
                    const data = await response.json();
                    console.log('[EZ Translate] Detector data loaded:', data);
                    return data;
                }
            } catch (error) {
                console.error('[EZ Translate] Error loading detector data:', error);
            }

            return null;
        }

        /**
         * Get browser language (first preference, language code only)
         */
        getBrowserLanguage() {
            if (!navigator.languages || navigator.languages.length === 0) {
                return null;
            }

            const browserLang = navigator.languages[0];
            return browserLang.split('-')[0].toLowerCase(); // es-US -> es
        }

        /**
         * Show detector in specified mode
         */
        showDetector(mode, targetLanguage = null) {
            this.currentMode = mode;

            // Remove existing detector
            this.removeDetector();

            // For unfold mode, use full screen positioning (no container)
            if (mode === 'unfold') {
                // Create detector element
                this.detector = this.createDetectorElement(mode, targetLanguage);

                // Add directly to page for full screen modal
                document.body.appendChild(this.detector);
            } else {
                // For minimized/fold modes, use container
                const container = this.ensureContainer();

                // Create detector element
                this.detector = this.createDetectorElement(mode, targetLanguage);

                // Add to container
                container.appendChild(this.detector);
            }

            // Add event listeners
            this.attachEventListeners();

            // Mostrar siempre el selector minimizado, incluso si hay idioma elegido
            if (mode === 'unfold' && this.config.config.delay > 0) {
                setTimeout(() => {
                    if (this.detector) {
                        this.detector.classList.add('ez-detector-visible');
                    }
                }, this.config.config.delay);
            } else {
                this.detector.classList.add('ez-detector-visible');
            }

            console.log('[EZ Translate] Detector shown in mode:', mode);
        }

        /**
         * Create detector HTML element
         */
        createDetectorElement(mode, targetLanguage) {
            const detector = document.createElement('div');

            // For unfold mode, include position classes (full screen modal)
            // For other modes, don't include position classes (handled by container)
            if (mode === 'unfold') {
                detector.className = `ez-language-detector ez-detector-${mode} ez-detector-${this.config.config.position}`;
            } else {
                detector.className = `ez-language-detector ez-detector-${mode}`;
            }

            detector.id = 'ez-language-detector';

            if (mode === 'fold') {
                detector.innerHTML = this.createFoldModeHTML();
            } else if (mode === 'unfold') {
                detector.innerHTML = this.createUnfoldModeHTML(targetLanguage);
            } else if (mode === 'minimized') {
                detector.innerHTML = this.createMinimizedModeHTML();
            } else if (mode === 'helper') {
                detector.innerHTML = this.createHelperModeHTML(targetLanguage);
            }

            return detector;
        }

        /**
         * Create fold mode HTML (passive tab)
         */
        createFoldModeHTML() {
            const currentLang = this.getLanguageData(this.config.currentLanguage);
            const messages = this.getMessages(this.config.currentLanguage);

            return `
                <div class="ez-detector-tab">
                    <span class="ez-detector-flag">${currentLang.flag || '🌐'}</span>
                    <span class="ez-detector-text">${currentLang.code.toUpperCase()}</span>
                </div>
                <div class="ez-detector-dropdown">
                    <div class="ez-detector-title">${messages.dropdown_title}</div>
                    ${this.createLanguageList()}
                </div>
            `;
        }

        /**
         * Create minimized mode HTML (small button to reopen selector)
         */
        createMinimizedModeHTML() {
            const currentLang = this.getLanguageData(this.config.currentLanguage);
            // Usar icono de bandera para el estado minimizado
            // Agregar data-action a todos los elementos para asegurar que el clic funcione en toda el área
            return `
                <button class="ez-detector-minimized-btn" data-action="expand" title="${this.getMessages(this.config.currentLanguage).dropdown_title || 'Change language'}">
                    <span class="ez-detector-minimized-icon" data-action="expand">
                        <span class="ez-detector-flag" data-action="expand">${currentLang.flag || '🌐'}</span>
                    </span>
                </button>
            `;
        }

        /**
         * Create unfold mode HTML (prominent window)
         */
        createUnfoldModeHTML(targetLanguage) {
            const targetLang = this.getLanguageData(targetLanguage);
            const messages = this.getMessages(targetLanguage);
            
            return `
                <div class="ez-detector-window">
                    <div class="ez-detector-header">
                        <h3>${messages.title}</h3>
                        <button class="ez-detector-close" data-action="close">&times;</button>
                    </div>
                    <div class="ez-detector-content">
                        <div class="ez-detector-editions">
                            ${this.createEditionsList(targetLanguage)}
                        </div>
                        <p class="ez-detector-description">${messages.description}</p>
                        <div class="ez-detector-actions">
                            <button class="ez-detector-btn ez-detector-btn-primary" data-action="confirm" data-language="${targetLanguage}">
                                ${messages.confirm_button}
                            </button>
                            <button class="ez-detector-btn ez-detector-btn-secondary" data-action="stay">
                                ${messages.stay_button}
                            </button>
                            <button class="ez-detector-btn ez-detector-btn-link" data-action="free">
                                ${messages.free_navigation}
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        /**
         * Create helper mode HTML (small button)
         */
        createHelperModeHTML(targetLanguage) {
            const targetLang = this.getLanguageData(targetLanguage);
            
            return `
                <div class="ez-detector-helper">
                    <button class="ez-detector-helper-btn" data-action="switch" data-language="${targetLanguage}">
                        <span class="ez-detector-flag">${targetLang.flag || '🌐'}</span>
                        <span class="ez-detector-text">${targetLang.code.toUpperCase()}</span>
                    </button>
                </div>
            `;
        }

        /**
         * Create language list for dropdown (all available languages with landing pages)
         */
        createLanguageList() {
            let html = '';
            const messages = this.getMessages(this.config.currentLanguage);

            // Show ALL available languages that have landing pages configured
            this.config.availableLanguages.forEach(lang => {
                // Skip languages without landing pages
                if (!lang.landing_page_id) {
                    return;
                }

                const isActive = lang.code === this.config.currentLanguage;

                // Check if this language has a translation or landing page
                const translation = this.findTranslationInData(lang.code);
                let statusText = '';

                if (isActive) {
                    statusText = `<span class="ez-detector-current">✓</span>`;
                } else if (translation) {
                    statusText = translation.is_landing_page ? `<small>${messages.landing_label}</small>` : `<small>${messages.translation_label}</small>`;
                } else {
                    // Language has landing page configured
                    statusText = `<small>${messages.landing_label}</small>`;
                }

                html += `
                    <div class="ez-detector-lang-item ${isActive ? 'active' : ''}" data-language="${lang.code}">
                        <span class="ez-detector-flag">${lang.flag || '🌐'}</span>
                        ${statusText}
                        <span class="ez-detector-name">${lang.native_name || lang.name}</span>
                    </div>
                `;
            });

            return html;
        }

        /**
         * Create language list for translator (all available translations)
         */
        createTranslatorLanguageList(targetLanguage) {
            const messages = this.getMessages(this.config.currentLanguage);
            let html = '';

            // Group translations by language to prioritize real translations over landing pages
            const translationsByLang = {};

            if (this.availableTranslations && this.availableTranslations.length > 0) {
                this.availableTranslations.forEach(translation => {
                    // Skip current language
                    if (translation.language_code === this.config.currentLanguage) {
                        return;
                    }

                    const langCode = translation.language_code;

                    // If we don't have this language yet, or if this is a real translation (not landing page)
                    // and the existing one is a landing page, prioritize the real translation
                    if (!translationsByLang[langCode] ||
                        (!translation.is_landing_page && translationsByLang[langCode].is_landing_page)) {
                        translationsByLang[langCode] = translation;
                    }
                });

                // Now create HTML for each language (prioritizing real translations)
                Object.values(translationsByLang).forEach(translation => {
                    const lang = this.getLanguageData(translation.language_code);
                    const statusText = translation.is_landing_page ?
                        `<small>${messages.landing_label || 'Homepage'}</small>` :
                        `<small>${messages.translation_label || 'Translation'}</small>`;

                    html += `
                        <div class="ez-detector-lang-item ez-translator-lang-item" data-language="${translation.language_code}">
                            <span class="ez-detector-flag">${lang.flag || '🌐'}</span>
                            <span class="ez-detector-name">${lang.native_name || lang.name}</span>
                            ${statusText}
                        </div>
                    `;
                });
            }

            return html;
        }

        /**
         * Create editions list for unfold mode - shows ALL available languages with landing pages
         */
        createEditionsList(targetLanguage) {
            let html = '<div class="ez-detector-editions-list">';

            // Show target language first (highlighted) if it has landing page or translation
            const targetLang = this.getLanguageData(targetLanguage);
            const targetTranslation = this.findTranslationInData(targetLanguage);
            const targetHasLanding = this.config.availableLanguages.find(lang =>
                lang.code === targetLanguage && lang.landing_page_id
            );

            if (targetTranslation || targetHasLanding) {
                html += `
                    <div class="ez-detector-edition ez-detector-edition-highlighted" data-language="${targetLanguage}">
                        <span class="ez-detector-flag">${targetLang.flag || '🌐'}</span>
                        <span class="ez-detector-name">${targetLang.native_name || targetLang.name}</span>
                        ${targetTranslation ?
                            (targetTranslation.is_landing_page ? '<small>(Landing Page)</small>' : '<small>(Translation)</small>') :
                            '<small>(Landing Page)</small>'
                        }
                    </div>
                `;
            }

            // Show current language
            const currentLang = this.getLanguageData(this.config.currentLanguage);
            if (currentLang && currentLang.code !== targetLanguage) {
                html += `
                    <div class="ez-detector-edition" data-language="${this.config.currentLanguage}">
                        <span class="ez-detector-flag">${currentLang.flag || '🌐'}</span>
                        <span class="ez-detector-name">${currentLang.native_name || currentLang.name}</span>
                        <small>(Current)</small>
                    </div>
                `;
            }

            // Show ALL other available languages that have landing pages
            this.config.availableLanguages.forEach(lang => {
                // Skip if it's the target language or current language, or if no landing page
                if (lang.code === targetLanguage || lang.code === this.config.currentLanguage || !lang.landing_page_id) {
                    return;
                }

                // Check if this language has a real translation for current page
                const translation = this.findTranslationInData(lang.code);
                const statusText = translation ?
                    (translation.is_landing_page ? '<small>(Landing Page)</small>' : '<small>(Translation)</small>') :
                    '<small>(Landing Page)</small>';

                html += `
                    <div class="ez-detector-edition" data-language="${lang.code}">
                        <span class="ez-detector-flag">${lang.flag || '🌐'}</span>
                        <span class="ez-detector-name">${lang.native_name || lang.name}</span>
                        ${statusText}
                    </div>
                `;
            });

            html += '</div>';
            return html;
        }

        /**
         * Get language data by code
         */
        getLanguageData(code) {
            return this.config.availableLanguages.find(lang => lang.code === code) || {
                code: code,
                name: code.toUpperCase(),
                native_name: code.toUpperCase(),
                flag: '🌐'
            };
        }

        /**
         * Get messages for a language
         */
        getMessages(languageCode) {
            // Try to get messages from backend configuration
            const configMessages = this.config.config && this.config.config.messages;

            if (configMessages) {
                // Try specific language first, then fallback to English, then default
                const messages = configMessages[languageCode] ||
                               configMessages['en'] ||
                               configMessages['default'];

                if (messages) {
                    return messages;
                }
            }

            // Only use hardcoded fallback if no backend messages are available
            console.warn('[EZ Translate] No backend messages found, using hardcoded fallback');
            return {
                title: 'Choose your favorite edition',
                description: 'This edition will always load when you visit',
                confirm_button: 'Confirm',
                stay_button: 'Keep current',
                free_navigation: 'Browse freely',
                dropdown_title: 'Available languages',
                translation_available: 'We have this version in',
                landing_available: 'We have homepage in',
                current_language: 'Current language',
                translation_label: 'Translation',
                landing_label: 'Homepage'
            };
        }

        /**
         * Attach event listeners to detector
         */
        attachEventListeners() {
            if (!this.detector) return;

            // Handle all clicks on detector
            this.detector.addEventListener('click', (e) => {
                // Find the closest element with data-action (bubble up the DOM tree)
                const actionElement = e.target.closest('[data-action]');

                if (actionElement) {
                    const action = actionElement.dataset.action;
                    const language = actionElement.dataset.language;

                    switch (action) {
                        case 'confirm':
                            this.handleConfirm(language);
                            break;
                        case 'stay':
                            this.handleStay();
                            break;
                        case 'free':
                            this.handleFreeNavigation();
                            break;
                        case 'close':
                            this.handleClose();
                            break;
                        case 'switch':
                            this.handleSwitch(language);
                            break;
                        case 'expand':
                            this.handleExpand();
                            break;
                    }
                }
            });

            // Handle language selection in dropdown
            this.detector.addEventListener('click', async (e) => {
                if (e.target.closest('.ez-detector-lang-item')) {
                    const langItem = e.target.closest('.ez-detector-lang-item');
                    const language = langItem.dataset.language;
                    await this.handleLanguageSelect(language);
                }
            });

            // Handle edition selection
            this.detector.addEventListener('click', async (e) => {
                if (e.target.closest('.ez-detector-edition')) {
                    const edition = e.target.closest('.ez-detector-edition');
                    const language = edition.dataset.language;
                    await this.handleConfirm(language);
                }
            });

            // Handle click on fold mode tab to expand selector (Ejemplo 3, 5)
            if (this.currentMode === 'fold') {
                const tab = this.detector.querySelector('.ez-detector-tab');
                if (tab) {
                    tab.addEventListener('click', (e) => {
                        // Prevent event from bubbling to dropdown
                        e.stopPropagation();
                        console.log('[EZ Translate] Fold tab clicked - showing expanded selector');
                        // Fix: bind 'this' to the class instance
                        this.showDetector('unfold', this.getBrowserLanguage());
                    });
                }
            }
        }

        /**
         * Handle confirm action (redirect to selected language)
         */
        async handleConfirm(language) {
            console.log('[EZ Translate] Confirming language:', language);

            // Guardar preferencia de navegación
            this.setUserChoice(language); // language code (ej: 'en', 'es', etc)

            // Remove translator since user chose specific language
            this.removeTranslator();

            // Redirect to appropriate page
            await this.redirectToLanguage(language);
        }

        /**
         * Handle stay action (keep current language)
         */
        handleStay() {
            console.log('[EZ Translate] Staying in current language');

            // Guardar preferencia de navegación
            this.setUserChoice(this.config.currentLanguage);

            // Quitar traductor y helper
            this.removeTranslator();
            this.removeHelper();

            // Solo mostrar minimizado
            this.showDetector('minimized');
        }

        /**
         * Handle free navigation action
         */
        handleFreeNavigation() {
            console.log('[EZ Translate] Enabling free navigation');

            // Guardar preferencia de navegación
            this.setUserChoice('free');

            // Hide detector
            this.removeDetector();

            // Show minimized mode and translator
            setTimeout(() => {
                const browserLanguage = this.getBrowserLanguage();
                const currentLanguage = this.config.currentLanguage;
                this.determineDetectorMode(browserLanguage, currentLanguage, 'free');
            }, 300);
        }

        /**
         * Handle close action (Ejemplo 7: dismissed)
         */
        handleClose() {
            console.log('[EZ Translate] Closing detector - user dismissed');

            // Guardar preferencia de navegación
            this.setUserChoice('dismissed');

            // Quitar helper si existe
            this.removeHelper();

            // Mostrar minimizado y traductor activo
            this.showDetector('minimized');
            this.showTranslator(this.getBrowserLanguage());
        }

        /**
         * Handle switch action (from helper mode)
         */
        async handleSwitch(language) {
            console.log('[EZ Translate] Switching to language:', language);

            // Solo redirigir, NO guardar preferencia
            await this.redirectToLanguage(language);
        }

        /**
         * Handle expand action (from minimized selector)
         */
        handleExpand = (language) => {
            console.log('[EZ Translate] Expanding selector from minimized state');
            // Show expanded selector
            this.showDetector('unfold', this.getBrowserLanguage());
        }

        /**
         * Handle language selection from dropdown
         */
        async handleLanguageSelect(language) {
            console.log('[EZ Translate] Language selected:', language);

            if (language === this.config.currentLanguage) {
                // Mismo idioma, mantener minimizado y quitar traductor
                this.setUserChoice(this.config.currentLanguage);
                this.removeTranslator();
                this.showDetector('minimized');
                return;
            }

            // Si el usuario elige un idioma diferente al actual, guardar la preferencia
            // pero NO redirigir inmediatamente para mantener al usuario en el contenido actual
            console.log('[EZ Translate] Saving language preference without redirecting:', language);
            this.setUserChoice(language);

            // Quitar traductor ya que el usuario eligió un idioma específico
            this.removeTranslator();

            // Mostrar detector minimizado
            this.showDetector('minimized');

            // Configurar interceptación de navegación para futuras navegaciones
            this.setupNavigationInterception();
        }

        /**
         * Redirect to appropriate page in target language
         */
        async redirectToLanguage(targetLanguage) {
            try {
                // Find translation in already loaded data
                const translation = this.findTranslationInData(targetLanguage);

                if (translation) {
                    console.log('[EZ Translate] Redirecting to:', translation.url);
                    window.location.href = translation.url;
                    return;
                }

                // If no specific translation, try to find landing page for the language
                const landingTranslation = this.findLandingPageInTranslations(targetLanguage);
                if (landingTranslation) {
                    console.log('[EZ Translate] Redirecting to landing page via translation data:', landingTranslation.url);
                    window.location.href = landingTranslation.url;
                    return;
                }

                // If not found in translations, try to get landing page URL via API
                const landingUrl = await this.getLandingPageUrl(targetLanguage);
                if (landingUrl) {
                    console.log('[EZ Translate] Redirecting to landing page via API:', landingUrl);
                    window.location.href = landingUrl;
                    return;
                }

                // Special case for Spanish - redirect to main landing page
                if (targetLanguage === 'es') {
                    console.log('[EZ Translate] Redirecting to home page for Spanish');
                    window.location.href = this.config.homeUrl;
                    return;
                }

                // Fallback to home page
                console.log('[EZ Translate] No translation or landing page found, redirecting to home page');
                window.location.href = this.config.homeUrl;

            } catch (error) {
                console.error('[EZ Translate] Error during redirection:', error);
                // Fallback to home page
                window.location.href = this.config.homeUrl;
            }
        }

        /**
         * Find translation in already loaded data
         */
        findTranslationInData(targetLanguage) {
            if (!this.availableTranslations) {
                return null;
            }

            return this.availableTranslations.find(translation =>
                translation.language_code === targetLanguage
            );
        }

        /**
         * Find landing page in available translations
         */
        findLandingPageInTranslations(targetLanguage) {
            if (!this.availableTranslations) {
                return null;
            }

            return this.availableTranslations.find(translation =>
                translation.language_code === targetLanguage && translation.is_landing_page
            );
        }

        /**
         * Get landing page URL via API call
         */
        async getLandingPageUrl(targetLanguage) {
            try {
                console.log('[EZ Translate] Getting landing page URL for language:', targetLanguage);

                // Get the landing page ID from config
                const targetLang = this.config.availableLanguages.find(lang => lang.code === targetLanguage);
                if (!targetLang || !targetLang.landing_page_id) {
                    console.log('[EZ Translate] No landing page ID configured for language:', targetLanguage);
                    return null;
                }

                // Use WordPress REST API to get the permalink (which uses slug)
                const postResponse = await fetch(`${this.config.restUrl.replace('ez-translate/v1/', '')}wp/v2/pages/${targetLang.landing_page_id}?_fields=link`);

                if (postResponse.ok) {
                    const post = await postResponse.json();
                    console.log('[EZ Translate] Found landing page URL via API:', post.link);
                    return post.link;
                }

                // Try posts if pages failed
                const pageResponse = await fetch(`${this.config.restUrl.replace('ez-translate/v1/', '')}wp/v2/posts/${targetLang.landing_page_id}?_fields=link`);

                if (pageResponse.ok) {
                    const page = await pageResponse.json();
                    console.log('[EZ Translate] Found landing post URL via API:', page.link);
                    return page.link;
                }

                console.log('[EZ Translate] Could not get landing page URL via API for ID:', targetLang.landing_page_id);
                return null;

            } catch (error) {
                console.error('[EZ Translate] Error getting landing page URL:', error);
                return null;
            }
        }

        /**
         * Extract post ID from URL
         */
        extractPostIdFromUrl(url) {
            try {
                // Try different URL patterns

                // Pattern 1: ?p=123 or ?page_id=123
                const urlParams = new URLSearchParams(new URL(url).search);
                if (urlParams.has('p')) {
                    return parseInt(urlParams.get('p'));
                }
                if (urlParams.has('page_id')) {
                    return parseInt(urlParams.get('page_id'));
                }

                // Pattern 2: /post-slug/ - we'll need to make an API call for this
                const urlObj = new URL(url);
                const pathname = urlObj.pathname;

                // Skip home page and admin URLs
                if (pathname === '/' || pathname.includes('/wp-admin/') || pathname.includes('/wp-content/')) {
                    return null;
                }

                // For pretty permalinks, we'll need to resolve via API
                // Return a special marker that indicates we need to resolve this
                return { needsResolution: true, url: url };

            } catch (error) {
                console.error('[EZ Translate] Error extracting post ID from URL:', error);
                return null;
            }
        }

        /**
         * Find translation for a specific post
         */
        async findTranslationForPost(postId, targetLanguage) {
            try {
                // If postId is an object with needsResolution, resolve the URL first
                if (typeof postId === 'object' && postId.needsResolution) {
                    postId = await this.resolvePostIdFromUrl(postId.url);
                    if (!postId) {
                        return null;
                    }
                }

                console.log('[EZ Translate] Looking for translation of post', postId, 'in language', targetLanguage);

                // Make API call to get translations for this specific post
                const response = await fetch(`${this.config.restUrl}language-detector?post_id=${postId}`);

                if (!response.ok) {
                    console.error('[EZ Translate] Failed to fetch translations for post:', postId);
                    return null;
                }

                const data = await response.json();

                if (data.available_translations) {
                    const translation = data.available_translations.find(t => t.language_code === targetLanguage);
                    if (translation) {
                        console.log('[EZ Translate] Found translation for post', postId, ':', translation);
                        return translation;
                    }
                }

                console.log('[EZ Translate] No translation found for post', postId, 'in language', targetLanguage);
                return null;

            } catch (error) {
                console.error('[EZ Translate] Error finding translation for post:', error);
                return null;
            }
        }

        /**
         * Resolve post ID from URL using WordPress API
         */
        async resolvePostIdFromUrl(url) {
            try {
                // Use WordPress REST API to resolve URL to post ID
                const urlObj = new URL(url);
                const pathname = urlObj.pathname;

                // Extract slug from pathname
                const pathParts = pathname.split('/').filter(p => p);
                if (pathParts.length === 0) {
                    return null;
                }

                const slug = pathParts[pathParts.length - 1];

                // Try to get post by slug
                const postResponse = await fetch(`${this.config.restUrl.replace('ez-translate/v1/', '')}wp/v2/posts?slug=${slug}&_fields=id`);

                if (postResponse.ok) {
                    const posts = await postResponse.json();
                    if (posts.length > 0) {
                        console.log('[EZ Translate] Resolved post ID from slug:', slug, '-> ID:', posts[0].id);
                        return posts[0].id;
                    }
                }

                // Try pages if post didn't work
                const pageResponse = await fetch(`${this.config.restUrl.replace('ez-translate/v1/', '')}wp/v2/pages?slug=${slug}&_fields=id`);

                if (pageResponse.ok) {
                    const pages = await pageResponse.json();
                    if (pages.length > 0) {
                        console.log('[EZ Translate] Resolved page ID from slug:', slug, '-> ID:', pages[0].id);
                        return pages[0].id;
                    }
                }

                console.log('[EZ Translate] Could not resolve post/page ID for slug:', slug);
                return null;

            } catch (error) {
                console.error('[EZ Translate] Error resolving post ID from URL:', error);
                return null;
            }
        }



        /**
         * Remove detector from page
         */
        removeDetector() {
            if (this.detector) {
                this.detector.remove();
                this.detector = null;
            }
        }

        /**
         * Remove helper from page
         */
        removeHelper() {
            const helper = document.getElementById('ez-language-helper');
            if (helper) {
                helper.remove();
            }
        }

        // localStorage methods
        getUserLanguage() {
            return localStorage.getItem(this.storageKeys.userLanguage);
        }

        setUserLanguage(language) {
            localStorage.setItem(this.storageKeys.userLanguage, language);
        }

        getFreeNavigation() {
            return localStorage.getItem(this.storageKeys.freeNavigation) === 'true';
        }

        setFreeNavigation(value) {
            localStorage.setItem(this.storageKeys.freeNavigation, value.toString());
        }

        getDetectorDismissed() {
            return localStorage.getItem(this.storageKeys.detectorDismissed) === 'true';
        }

        setDetectorDismissed(value) {
            localStorage.setItem(this.storageKeys.detectorDismissed, value.toString());
        }

        getUserChoice() {
            return localStorage.getItem(this.storageKeys.userChoice);
        }

        setUserChoice(choice) {
            localStorage.setItem(this.storageKeys.userChoice, choice);
        }
    }

    // Initialize when page loads
    if (window.ezTranslateDetector) {
        new EZLanguageDetector();
    }

})();
