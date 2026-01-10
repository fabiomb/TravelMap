/**
 * Utility to manage distance unit preferences (KM/MI)
 * Handles auto-detection via locale, localStorage persistence, and conversion logic
 */
const UnitManager = (function () {
    const STORAGE_KEY = 'travelmap_unit_preference';

    // Locales that predominantly use miles for road distance
    const MILE_LOCALES = [
        'en-US', // USA
        'en-GB', // UK (Mixed, but road signs are miles)
        'en-LR', // Liberia
        'en-MM', // Myanmar
        'es-PR', // Puerto Rico (Mixed, but miles common)
        'en-AS', // American Samoa
        'en-GU', // Guam
        'en-MP', // Northern Mariana Islands
        'en-VI'  // US Virgin Islands
    ];

    /**
     * Detects if the browser's language preference suggests using miles
     */
    function detectLocaleUnit() {
        const languages = navigator.languages || [navigator.language || navigator.userLanguage];

        for (const lang of languages) {
            if (MILE_LOCALES.some(locale => lang === locale || lang.startsWith(locale + '-'))) {
                return 'mi';
            }
            // Broad check for US/UK if specific locale not found
            if (lang.includes('-US') || lang.includes('-GB')) {
                return 'mi';
            }
        }
        return 'km';
    }

    return {
        /**
         * Gets the preferred unit (localStorage > Locale Detection > Server Fallback)
         */
        getUnit: function () {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved === 'km' || saved === 'mi') {
                return saved;
            }

            // Auto-detect
            const detected = detectLocaleUnit();

            // If detection matches server fallback, we don't need to save anything yet
            // but we use it as the default.
            return detected;
        },

        /**
         * Saves the preference and triggers an event
         */
        setUnit: function (unit) {
            if (unit !== 'km' && unit !== 'mi') return;

            localStorage.setItem(STORAGE_KEY, unit);

            // Dispatch custom event for parts of the UI that need to re-render
            window.dispatchEvent(new CustomEvent('travelmap:unit_changed', { detail: { unit: unit } }));
        },

        /**
         * Formats a distance in meters to the preferred unit string
         */
        formatDistance: function (meters, options = {}) {
            if (meters === undefined || meters === null || meters < 0) return '';

            const unit = this.getUnit();
            let value, label;

            if (unit === 'mi') {
                value = meters / 1609.344;
                label = 'mi';
            } else {
                value = meters / 1000;
                label = 'km';
            }

            const precision = options.precision !== undefined ? options.precision : 0;
            const formattedValue = value.toLocaleString(undefined, {
                minimumFractionDigits: precision,
                maximumFractionDigits: precision
            });

            return `${formattedValue} ${label}`;
        }
    };
})();
