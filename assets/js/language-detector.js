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
                detectorDismissed: 'ez_translate_detector_dismissed'
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

            // Get browser language
            const browserLanguage = this.getBrowserLanguage();
            const currentLanguage = this.config.currentLanguage;

            console.log('[EZ Translate] Browser language:', browserLanguage, 'Current language:', currentLanguage);

            // Load detector data with translations info
            const detectorData = await this.loadDetectorData();

            if (!detectorData) {
                console.log('[EZ Translate] Failed to load detector data');
                return;
            }

            // Check if user has preferences
            const userLanguage = this.getUserLanguage();
            const freeNavigation = this.getFreeNavigation();
            const detectorDismissed = this.getDetectorDismissed();

            // Store available translations for later use
            this.availableTranslations = detectorData.available_translations || [];
            this.hasTranslations = detectorData.has_translations || false;

            console.log('[EZ Translate] Available translations:', this.availableTranslations);

            // Determine what to show based on translations and user preferences
            if (freeNavigation) {
                // User chose free navigation, show fold mode only
                this.showDetector('fold');
            } else if (detectorDismissed) {
                // User dismissed detector, show helper if translation exists in their language
                this.checkAndShowHelper(browserLanguage);
            } else if (browserLanguage && browserLanguage !== currentLanguage && this.hasTranslations) {
                // Language mismatch AND translations exist, show unfold mode
                this.showDetector('unfold', browserLanguage);
            } else if (this.hasTranslations) {
                // Has translations but user is in correct language, show fold mode
                this.showDetector('fold');
            }
            // If no translations exist, don't show detector at all
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
            
            return `
                <div class="ez-detector-tab">
                    <span class="ez-detector-flag">${currentLang.flag || '🌐'}</span>
                    <span class="ez-detector-text">${currentLang.code.toUpperCase()}</span>
                </div>
                <div class="ez-detector-dropdown">
                    <div class="ez-detector-title">Cambiar idioma</div>
                    ${this.createLanguageList()}
                </div>
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
         * Create language list for dropdown (only languages with translations)
         */
        createLanguageList() {
            let html = '';

            // Add current language first
            const currentLang = this.getLanguageData(this.config.currentLanguage);
            html += `
                <div class="ez-detector-lang-item active" data-language="${this.config.currentLanguage}">
                    <span class="ez-detector-flag">${currentLang.flag || '🌐'}</span>
                    <span class="ez-detector-name">${currentLang.native_name || currentLang.name}</span>
                    <span class="ez-detector-current">✓</span>
                </div>
            `;

            // Add languages that have translations
            if (this.availableTranslations) {
                this.availableTranslations.forEach(translation => {
                    if (translation.language_code !== this.config.currentLanguage) {
                        const lang = this.getLanguageData(translation.language_code);
                        html += `
                            <div class="ez-detector-lang-item" data-language="${translation.language_code}">
                                <span class="ez-detector-flag">${lang.flag || '🌐'}</span>
                                <span class="ez-detector-name">${lang.native_name || lang.name}</span>
                                ${translation.is_landing_page ? '<small>(Landing)</small>' : ''}
                            </div>
                        `;
                    }
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
            const messages = this.config.config.messages[languageCode];
            
            // Fallback to Spanish if language not found
            return messages || this.config.config.messages['es'] || {
                title: 'Choose your favorite edition',
                description: 'This edition will always load when you visit',
                confirm_button: 'Confirm',
                stay_button: 'Keep current',
                free_navigation: 'Browse freely'
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
        }

        /**
         * Handle confirm action (redirect to selected language)
         */
        handleConfirm(language) {
            console.log('[EZ Translate] Confirming language:', language);

            // Save user preference
            this.setUserLanguage(language);

            // Redirect to appropriate page
            this.redirectToLanguage(language);
        }

        /**
         * Handle stay action (keep current language)
         */
        handleStay() {
            console.log('[EZ Translate] Staying in current language');

            // Save current language as preference
            this.setUserLanguage(this.config.currentLanguage);

            // Hide detector
            this.removeDetector();
        }

        /**
         * Handle free navigation action
         */
        handleFreeNavigation() {
            console.log('[EZ Translate] Enabling free navigation');

            // Save free navigation preference
            this.setFreeNavigation(true);

            // Hide detector
            this.removeDetector();

            // Show fold mode
            setTimeout(() => {
                this.showDetector('fold');
            }, 300);
        }

        /**
         * Handle close action
         */
        handleClose() {
            console.log('[EZ Translate] Closing detector');

            // Mark as dismissed
            this.setDetectorDismissed(true);

            // Hide detector
            this.removeDetector();

            // Check if we should show helper
            const browserLanguage = this.getBrowserLanguage();
            if (browserLanguage && browserLanguage !== this.config.currentLanguage) {
                this.checkAndShowHelper(browserLanguage);
            }
        }

        /**
         * Handle switch action (from helper mode)
         */
        handleSwitch(language) {
            console.log('[EZ Translate] Switching to language:', language);

            // Save user preference
            this.setUserLanguage(language);

            // Redirect to appropriate page
            this.redirectToLanguage(language);
        }

        /**
         * Handle language selection from dropdown
         */
        handleLanguageSelect(language) {
            console.log('[EZ Translate] Language selected:', language);

            if (language === this.config.currentLanguage) {
                // Same language, just close dropdown
                this.removeDetector();
                return;
            }

            // Save user preference
            this.setUserLanguage(language);

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

                // Fallback to home page
                console.log('[EZ Translate] No translation found, redirecting to home page');
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
         * Check and show helper if translation exists
         */
        checkAndShowHelper(targetLanguage) {
            const translation = this.findTranslationInData(targetLanguage);

            if (translation) {
                setTimeout(() => {
                    this.showDetector('helper', targetLanguage);
                }, 1000);
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
    }

    // Initialize when page loads
    if (window.ezTranslateDetector) {
        new EZLanguageDetector();
    }

})();
