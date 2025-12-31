/**
 * i18n.js - Internacionalización para JavaScript
 * 
 * Sistema de traducciones para el frontend de TravelMap
 */

(function(window) {
    'use strict';
    
    const i18n = {
        currentLang: 'en',
        translations: {},
        availableLanguages: ['en', 'es'],
        
        /**
         * Inicializa el sistema de traducciones
         */
        init: function(callback) {
            this.detectLanguage();
            this.loadTranslations(callback);
        },
        
        /**
         * Detecta el idioma a utilizar basado en:
         * 1. localStorage 'travelmap_lang'
         * 2. Cookie 'travelmap_lang'
         * 3. Idioma del navegador
         * 4. Idioma por defecto (inglés)
         */
        detectLanguage: function() {
            // 1. localStorage
            const storedLang = localStorage.getItem('travelmap_lang');
            if (storedLang && this.availableLanguages.includes(storedLang)) {
                this.currentLang = storedLang;
                return;
            }
            
            // 2. Cookie
            const cookieLang = this.getCookie('travelmap_lang');
            if (cookieLang && this.availableLanguages.includes(cookieLang)) {
                this.currentLang = cookieLang;
                localStorage.setItem('travelmap_lang', cookieLang);
                return;
            }
            
            // 3. Idioma del navegador
            const browserLang = navigator.language || navigator.userLanguage;
            const langCode = browserLang.substring(0, 2);
            if (this.availableLanguages.includes(langCode)) {
                this.currentLang = langCode;
                localStorage.setItem('travelmap_lang', langCode);
                return;
            }
            
            // 4. Idioma por defecto
            this.currentLang = 'en';
            localStorage.setItem('travelmap_lang', 'en');
        },
        
        /**
         * Carga las traducciones del idioma actual
         */
        loadTranslations: function(callback) {
            const langFile = window.BASE_URL ? 
                window.BASE_URL + '/lang/' + this.currentLang + '.json' :
                '/TravelMap/lang/' + this.currentLang + '.json';
            
            fetch(langFile)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to load language file');
                    }
                    return response.json();
                })
                .then(data => {
                    this.translations = data;
                    if (callback) callback();
                })
                .catch(error => {
                    console.error('Error loading translations:', error);
                    this.translations = {};
                    if (callback) callback();
                });
        },
        
        /**
         * Obtiene una traducción por su clave
         * 
         * @param {string} key - Clave de la traducción (ej: "app.name" o "navigation.home")
         * @param {string} defaultValue - Valor por defecto si no se encuentra
         * @return {string} Texto traducido
         */
        get: function(key, defaultValue) {
            const keys = key.split('.');
            let value = this.translations;
            
            for (let i = 0; i < keys.length; i++) {
                if (value && typeof value === 'object' && keys[i] in value) {
                    value = value[keys[i]];
                } else {
                    return defaultValue || key;
                }
            }
            
            return typeof value === 'string' ? value : (defaultValue || key);
        },
        
        /**
         * Alias corto para get()
         */
        t: function(key, defaultValue) {
            return this.get(key, defaultValue);
        },
        
        /**
         * Establece el idioma actual
         */
        setLanguage: function(lang, callback) {
            if (this.availableLanguages.includes(lang)) {
                this.currentLang = lang;
                localStorage.setItem('travelmap_lang', lang);
                
                // Actualizar cookie también
                this.setCookie('travelmap_lang', lang, 365);
                
                this.loadTranslations(callback);
                return true;
            }
            return false;
        },
        
        /**
         * Obtiene el idioma actual
         */
        getCurrentLanguage: function() {
            return this.currentLang;
        },
        
        /**
         * Obtiene todos los idiomas disponibles
         */
        getAvailableLanguages: function() {
            return this.availableLanguages;
        },
        
        /**
         * Obtiene el nombre del idioma en su propio idioma
         */
        getLanguageName: function(langCode) {
            const names = {
                'en': 'English',
                'es': 'Español'
            };
            return names[langCode] || langCode;
        },
        
        /**
         * Helper para obtener una cookie
         */
        getCookie: function(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) {
                return parts.pop().split(';').shift();
            }
            return null;
        },
        
        /**
         * Helper para establecer una cookie
         */
        setCookie: function(name, value, days) {
            let expires = '';
            if (days) {
                const date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            document.cookie = name + '=' + (value || '') + expires + '; path=/';
        },
        
        /**
         * Traduce todos los elementos con atributo data-i18n
         */
        translatePage: function() {
            const elements = document.querySelectorAll('[data-i18n]');
            elements.forEach(element => {
                const key = element.getAttribute('data-i18n');
                const text = this.get(key);
                
                // Determinar si actualizar texto o placeholder
                if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                    if (element.placeholder !== undefined) {
                        element.placeholder = text;
                    } else {
                        element.value = text;
                    }
                } else {
                    element.textContent = text;
                }
            });
        }
    };
    
    // Exponer globalmente
    window.i18n = i18n;
    
    /**
     * Función global de atajo para traducciones
     */
    window.__ = function(key, defaultValue) {
        return i18n.get(key, defaultValue);
    };
    
})(window);
