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
            // Only restrict if navigation restriction is enabled and user chose a specific language
            if (!this.config.config.restrict_navigation || userChoice !== 'language') {
                return false;
            }

            const userLanguage = this.getUserLanguage();

            // If user chose a language but is on a different language page, redirect them
            if (userLanguage && userLanguage !== currentLanguage) {
                console.log('[EZ Translate] User restricted to language:', userLanguage, 'but on:', currentLanguage, '- redirecting');
                this.redirectToUserLanguage(userLanguage);
                return true; // Indicate that user was redirected
            }

            return false;
        }

        /**
         * Redirect user to their chosen language
         */
        redirectToUserLanguage(userLanguage) {
            try {
                // Try to find a translation of current page in user's language
                const translation = this.findTranslationInData(userLanguage);

                if (translation) {
                    console.log('[EZ Translate] Redirecting to user language translation:', translation.url);
                    window.location.href = translation.url;
                    return;
                }

                // If no translation, redirect to landing page for that language
                const targetLang = this.config.availableLanguages.find(lang => lang.code === userLanguage);
                if (targetLang && targetLang.landing_page_id) {
                    const landingUrl = `${this.config.homeUrl}?p=${targetLang.landing_page_id}`;
                    console.log('[EZ Translate] Redirecting to user language landing page:', landingUrl);
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
                hasTranslations: this.hasTranslations
            });

            // Remove any existing translator first
            this.removeTranslator();

            // Determine what to show based on user choice
            if (!userChoice) {
                // Ejemplo 8: Usuario no ha elegido nada - mostrar selector desplegado + traductor activo
                console.log('[EZ Translate] First-time user - showing expanded selector');
                this.showDetector('unfold', browserLanguage);
                this.showTranslator(browserLanguage);
            } else if (userChoice === 'language') {
                // Ejemplos 1, 2, 4: Usuario eligió idioma específico - solo selector minimizado, NO traductor
                console.log('[EZ Translate] User chose specific language - only minimized selector');
                this.showDetector('minimized');
                // NO mostrar traductor porque está encerrado en su idioma
            } else if (userChoice === 'free') {
                // Ejemplo 6: Usuario eligió navegar libremente - selector minimizado + traductor activo
                console.log('[EZ Translate] User chose free navigation - minimized selector + active translator');
                this.showDetector('minimized');
                this.showTranslator(browserLanguage);
            } else if (userChoice === 'dismissed') {
                // Ejemplo 7: Usuario cerró sin elegir - selector minimizado + traductor activo
                console.log('[EZ Translate] User dismissed - minimized selector + active translator');
                this.showDetector('minimized');
                this.showTranslator(browserLanguage);
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

            // Use the first available translation as primary target for the tab display
            const primaryTarget = targetLanguage || otherTranslations[0].language_code;

            // Create translator element
            const translator = document.createElement('div');
            translator.className = `ez-language-detector ez-detector-helper ez-detector-${this.config.config.position}`;
            translator.id = 'ez-language-translator';
            translator.innerHTML = this.createTranslatorHTML(primaryTarget);

            // Add to page
            document.body.appendChild(translator);

            // Add event listeners
            this.attachTranslatorEventListeners(translator);

            // Show translator
            translator.classList.add('ez-detector-visible');

            console.log('[EZ Translate] Translator shown with', otherTranslations.length, 'available translations');
        }

        /**
         * Remove translator from page
         */
        removeTranslator() {
            const existingTranslator = document.getElementById('ez-language-translator');
            if (existingTranslator) {
                existingTranslator.remove();
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
                <div class="ez-detector-tab ez-translator-tab">
                    <span class="ez-detector-flag">${targetLang.flag || '🌐'}</span>
                    <span class="ez-detector-text">${messages.translation_available || 'Read this article in'}</span>
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
            translator.addEventListener('click', (e) => {
                if (e.target.closest('.ez-translator-lang-item')) {
                    const langItem = e.target.closest('.ez-translator-lang-item');
                    const language = langItem.dataset.language;
                    this.handleSwitch(language);
                }
            });
        }

        /**
         * Attach event listeners to helper element
         */
        attachHelperEventListeners(helper) {
            helper.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                const language = e.target.dataset.language;

                if (action === 'switch') {
                    this.handleSwitch(language);
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

            // Create detector element
            this.detector = this.createDetectorElement(mode, targetLanguage);
            
            // Add to page
            document.body.appendChild(this.detector);

            // Add event listeners
            this.attachEventListeners();

            // Show with delay if configured
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
            detector.className = `ez-language-detector ez-detector-${mode} ez-detector-${this.config.config.position}`;
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

            return `
                <button class="ez-detector-minimized-btn" data-action="expand">
                    <span class="ez-detector-flag">${currentLang.flag || '🌐'}</span>
                    <span class="ez-detector-text">${currentLang.code.toUpperCase()}</span>
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
         * Create language list for dropdown (all available languages)
         */
        createLanguageList() {
            let html = '';
            const messages = this.getMessages(this.config.currentLanguage);

            // Show ALL available languages, not just those with translations of current page
            this.config.availableLanguages.forEach(lang => {
                const isActive = lang.code === this.config.currentLanguage;

                // Check if this language has a translation or landing page
                const translation = this.findTranslationInData(lang.code);
                let statusText = '';

                if (isActive) {
                    statusText = `<span class="ez-detector-current">✓</span>`;
                } else if (translation) {
                    statusText = translation.is_landing_page ? `<small>${messages.landing_label}</small>` : `<small>${messages.translation_label}</small>`;
                } else {
                    // Check if language has a landing page configured
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

            // Show all available translations, not just the target language
            if (this.availableTranslations && this.availableTranslations.length > 0) {
                this.availableTranslations.forEach(translation => {
                    // Skip current language
                    if (translation.language_code === this.config.currentLanguage) {
                        return;
                    }

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
         * Create editions list for unfold mode
         */
        createEditionsList(targetLanguage) {
            let html = '<div class="ez-detector-editions-list">';

            // Show target language first (highlighted) if translation exists
            const targetTranslation = this.findTranslationInData(targetLanguage);
            if (targetTranslation) {
                const targetLang = this.getLanguageData(targetLanguage);
                html += `
                    <div class="ez-detector-edition ez-detector-edition-highlighted" data-language="${targetLanguage}">
                        <span class="ez-detector-flag">${targetLang.flag || '🌐'}</span>
                        <span class="ez-detector-name">${targetLang.native_name || targetLang.name}</span>
                        ${targetTranslation.is_landing_page ? '<small>(Landing Page)</small>' : ''}
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

            // Show other available translations
            if (this.availableTranslations) {
                this.availableTranslations.forEach(translation => {
                    if (translation.language_code !== targetLanguage && translation.language_code !== this.config.currentLanguage) {
                        const lang = this.getLanguageData(translation.language_code);
                        html += `
                            <div class="ez-detector-edition" data-language="${translation.language_code}">
                                <span class="ez-detector-flag">${lang.flag || '🌐'}</span>
                                <span class="ez-detector-name">${lang.native_name || lang.name}</span>
                                ${translation.is_landing_page ? '<small>(Landing Page)</small>' : ''}
                            </div>
                        `;
                    }
                });
            }

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
                const action = e.target.dataset.action;
                const language = e.target.dataset.language;

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
            });

            // Handle language selection in dropdown
            this.detector.addEventListener('click', (e) => {
                if (e.target.closest('.ez-detector-lang-item')) {
                    const langItem = e.target.closest('.ez-detector-lang-item');
                    const language = langItem.dataset.language;
                    this.handleLanguageSelect(language);
                }
            });

            // Handle edition selection
            this.detector.addEventListener('click', (e) => {
                if (e.target.closest('.ez-detector-edition')) {
                    const edition = e.target.closest('.ez-detector-edition');
                    const language = edition.dataset.language;
                    this.handleConfirm(language);
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
                        this.showDetector('unfold', this.getBrowserLanguage());
                    });
                }
            }
        }

        /**
         * Handle confirm action (redirect to selected language)
         */
        handleConfirm(language) {
            console.log('[EZ Translate] Confirming language:', language);

            // Save user preference and choice type
            this.setUserLanguage(language);
            this.setUserChoice('language');

            // Remove translator since user chose specific language
            this.removeTranslator();

            // Redirect to appropriate page
            this.redirectToLanguage(language);
        }

        /**
         * Handle stay action (keep current language)
         */
        handleStay() {
            console.log('[EZ Translate] Staying in current language');

            // Save current language as preference and choice type
            this.setUserLanguage(this.config.currentLanguage);
            this.setUserChoice('language');

            // Remove translator since user chose to stay in specific language
            this.removeTranslator();
            this.removeHelper();

            // Switch to minimized mode (keep selector minimized, no translator)
            this.showDetector('minimized');
        }

        /**
         * Handle expand action (from minimized selector)
         */
        handleExpand() {
            console.log('[EZ Translate] Expanding selector from minimized state');

            // Show expanded selector
            this.showDetector('unfold', this.getBrowserLanguage());
        }

        /**
         * Handle free navigation action
         */
        handleFreeNavigation() {
            console.log('[EZ Translate] Enabling free navigation');

            // Save choice type
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

            // Mark as dismissed
            this.setUserChoice('dismissed');

            // Remove helper if exists
            this.removeHelper();

            // Switch to minimized mode and show translator (user dismissed but translator stays active)
            this.showDetector('minimized');
            this.showTranslator(this.getBrowserLanguage());
        }

        /**
         * Handle switch action (from helper mode)
         */
        handleSwitch(language) {
            console.log('[EZ Translate] Switching to language:', language);

            // Save user preference and choice type
            this.setUserLanguage(language);
            this.setUserChoice('language');

            // Redirect to appropriate page
            this.redirectToLanguage(language);
        }

        /**
         * Handle language selection from dropdown
         */
        handleLanguageSelect(language) {
            console.log('[EZ Translate] Language selected:', language);

            if (language === this.config.currentLanguage) {
                // Same language, keep minimized mode visible but remove translator
                this.setUserChoice('language');
                this.removeTranslator();
                this.showDetector('minimized');
                return;
            }

            // Save user preference and choice type
            this.setUserLanguage(language);
            this.setUserChoice('language');

            // Remove translator since user chose specific language
            this.removeTranslator();

            // Redirect to appropriate page
            this.redirectToLanguage(language);
        }

        /**
         * Redirect to appropriate page in target language
         */
        redirectToLanguage(targetLanguage) {
            try {
                // Find translation in already loaded data
                const translation = this.findTranslationInData(targetLanguage);

                if (translation) {
                    console.log('[EZ Translate] Redirecting to:', translation.url);
                    window.location.href = translation.url;
                    return;
                }

                // If no specific translation, try to find landing page for the language
                const targetLang = this.config.availableLanguages.find(lang => lang.code === targetLanguage);
                if (targetLang && targetLang.landing_page_id) {
                    const landingUrl = `${this.config.homeUrl}?p=${targetLang.landing_page_id}`;
                    console.log('[EZ Translate] Redirecting to landing page:', landingUrl);
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
