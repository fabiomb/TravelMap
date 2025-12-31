/**
 * Public Map - Visualizador de Viajes
 * 
 * Consume el API y renderiza todos los viajes con rutas y puntos en el mapa
 */

(function () {
    'use strict';

    // Variables globales
    let map;
    let tripsData = [];
    let routesLayers = {}; // { tripId: [layers] }
    let flightRoutesLayers = {}; // { tripId: [flightLayers] } - Rutas en avión separadas
    let flightRoutesData = {}; // { tripId: [routeData] } - Raw data for deferred creation
    let flightRoutesCreated = false; // Flag to track if flight layers have been created
    let pointsClusters = {}; // { tripId: clusterGroup }
    let allPointsCluster; // Cluster global para todos los puntos
    let appConfig = null; // Configuración cargada desde el servidor
    
    // LocalStorage key for user preferences
    const STORAGE_KEY = 'travelmap_preferences';

    /**
     * Load user preferences from localStorage
     */
    function loadPreferences() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                return JSON.parse(saved);
            }
        } catch (e) {
            console.warn('Error loading preferences from localStorage:', e);
        }
        // Default preferences
        return {
            showRoutes: true,
            showPoints: true,
            showFlightRoutes: false,
            selectedTrips: null // null means all selected (first load)
        };
    }

    /**
     * Save user preferences to localStorage
     */
    function savePreferences() {
        try {
            const prefs = {
                showRoutes: $('#toggleRoutes').is(':checked'),
                showPoints: $('#togglePoints').is(':checked'),
                showFlightRoutes: $('#toggleFlightRoutes').is(':checked'),
                selectedTrips: getSelectedTripIds(),
                knownTripIds: getAllTripIds() // Track all known trips to detect new ones
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        } catch (e) {
            console.warn('Error saving preferences to localStorage:', e);
        }
    }

    /**
     * Get array of all trip IDs currently loaded
     */
    function getAllTripIds() {
        return tripsData.map(function(trip) {
            return trip.id;
        });
    }

    /**
     * Get array of selected trip IDs
     */
    function getSelectedTripIds() {
        const selected = [];
        $('.trip-checkbox:checked').each(function() {
            selected.push(parseInt($(this).val()));
        });
        return selected;
    }

    /**
     * Apply loaded preferences to controls
     */
    function applyPreferencesToControls() {
        const prefs = loadPreferences();
        
        // Apply toggle states
        $('#toggleRoutes').prop('checked', prefs.showRoutes);
        $('#togglePoints').prop('checked', prefs.showPoints);
        $('#toggleFlightRoutes').prop('checked', prefs.showFlightRoutes);
        
    }

    /**
     * Apply trip selection preferences after trips are loaded
     */
    function applyTripSelectionPreferences() {
        const prefs = loadPreferences();
        
        // If selectedTrips is null (first visit), leave all checked (default)
        if (prefs.selectedTrips === null) {
            return;
        }
        
        const knownTripIds = prefs.knownTripIds || [];
        
        // Apply saved trip selections
        $('.trip-checkbox').each(function() {
            const tripId = parseInt($(this).val());
            
            // If this trip wasn't known before (new trip), default to visible
            const isNewTrip = knownTripIds.length > 0 && !knownTripIds.includes(tripId);
            const shouldBeChecked = isNewTrip || prefs.selectedTrips.includes(tripId);
            
            $(this).prop('checked', shouldBeChecked);
        });
        
        // Update map visibility based on selections
        $('.trip-checkbox').each(function() {
            const tripId = parseInt($(this).val());
            const isChecked = $(this).is(':checked');
            if (isChecked) {
                showTrip(tripId);
            } else {
                hideTrip(tripId);
            }
        });
        
    }

    /**
     * Apply initial toggle states after trips are rendered
     * This ensures flight routes are shown if the user has that preference saved
     */
    function applyInitialToggleStates() {
        const prefs = loadPreferences();
        
        // Handle flight routes visibility based on saved preference
        if (prefs.showFlightRoutes) {
            // Create flight layers on demand
            if (!flightRoutesCreated) {
                createFlightRouteLayers();
            }
            
            $('.trip-checkbox:checked').each(function() {
                const tripId = parseInt($(this).val());
                if (flightRoutesLayers[tripId]) {
                    flightRoutesLayers[tripId].forEach(function(layer) {
                        if (!map.hasLayer(layer)) {
                            layer.addTo(map);
                        }
                    });
                }
            });
        }
        
        // Handle points visibility based on saved preference
        if (!prefs.showPoints) {
            if (map.hasLayer(allPointsCluster)) {
                map.removeLayer(allPointsCluster);
            }
        }
        
        // Handle routes visibility based on saved preference
        if (!prefs.showRoutes) {
            Object.values(routesLayers).forEach(function(layers) {
                layers.forEach(function(layer) {
                    if (map.hasLayer(layer)) {
                        map.removeLayer(layer);
                    }
                });
            });
        }
        
    }

    // SVG icons for transport types
    const transportIcons = {
        plane: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/></svg>',
        car: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 15.4222V18.5C22 18.9659 22 19.1989 21.9239 19.3827C21.8224 19.6277 21.6277 19.8224 21.3827 19.9239C21.1989 20 20.9659 20 20.5 20C20.0341 20 19.8011 20 19.6173 19.9239C19.3723 19.8224 19.1776 19.6277 19.0761 19.3827C19 19.1989 19 18.9659 19 18.5C19 18.0341 19 17.8011 18.9239 17.6173C18.8224 17.3723 18.6277 17.1776 18.3827 17.0761C18.1989 17 17.9659 17 17.5 17H6.5C6.03406 17 5.80109 17 5.61732 17.0761C5.37229 17.1776 5.17761 17.3723 5.07612 17.6173C5 17.8011 5 18.0341 5 18.5C5 18.9659 5 19.1989 4.92388 19.3827C4.82239 19.6277 4.62771 19.8224 4.38268 19.9239C4.19891 20 3.96594 20 3.5 20C3.03406 20 2.80109 20 2.61732 19.9239C2.37229 19.8224 2.17761 19.6277 2.07612 19.3827C2 19.1989 2 18.9659 2 18.5V15.4222C2 14.22 2 13.6188 2.17163 13.052C2.34326 12.4851 2.67671 11.9849 3.3436 10.9846L4 10L4.96154 7.69231C5.70726 5.90257 6.08013 5.0077 6.8359 4.50385C7.59167 4 8.56112 4 10.5 4H13.5C15.4389 4 16.4083 4 17.1641 4.50385C17.9199 5.0077 18.2927 5.90257 19.0385 7.69231L20 10L20.6564 10.9846C21.3233 11.9849 21.6567 12.4851 21.8284 13.052C22 13.6188 22 14.22 22 15.4222Z"/><path d="M2 8.5L4 10L5.76114 10.4403C5.91978 10.4799 6.08269 10.5 6.24621 10.5H17.7538C17.9173 10.5 18.0802 10.4799 18.2389 10.4403L20 10L22 8.5"/><path d="M18 14V14.01"/><path d="M6 14V14.01"/></svg>',
        train: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 3H6.73259C9.34372 3 10.6493 3 11.8679 3.40119C13.0866 3.80239 14.1368 4.57795 16.2373 6.12907L19.9289 8.85517C19.9692 8.88495 19.9894 8.89984 20.0084 8.91416C21.2491 9.84877 21.985 11.307 21.9998 12.8603C22 12.8841 22 12.9091 22 12.9593C22 12.9971 22 13.016 21.9997 13.032C21.9825 14.1115 21.1115 14.9825 20.032 14.9997C20.016 15 19.9971 15 19.9593 15H2"/><path d="M2 11H6.095C8.68885 11 9.98577 11 11.1857 11.451C12.3856 11.9019 13.3983 12.77 15.4238 14.5061L16 15"/><path d="M10 7H17"/><path d="M2 19H22"/><path d="M18 19V21"/><path d="M12 19V21"/><path d="M6 19V21"/></svg>',
        ship: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 21.1932C2.68524 22.2443 3.57104 22.2443 4.27299 21.1932C6.52985 17.7408 8.67954 23.6764 10.273 21.2321C12.703 17.5694 14.4508 23.9218 16.273 21.1932C18.6492 17.5582 20.1295 23.5776 22 21.5842"/><path d="M3.57228 17L2.07481 12.6457C1.80373 11.8574 2.30283 11 3.03273 11H20.8582C23.9522 11 19.9943 17 17.9966 17"/><path d="M18 11L15.201 7.50122C14.4419 6.55236 13.2926 6 12.0775 6H8C6.89543 6 6 6.89543 6 8V11"/><path d="M10 6V3C10 2.44772 9.55228 2 9 2H8"/></svg>',
        walk: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12.5L7.73811 9.89287C7.91034 9.63452 8.14035 9.41983 8.40993 9.26578L10.599 8.01487C11.1619 7.69323 11.8483 7.67417 12.4282 7.9641C13.0851 8.29255 13.4658 8.98636 13.7461 9.66522C14.2069 10.7814 15.3984 12 18 12"/><path d="M13 9L11.7772 14.5951M10.5 8.5L9.77457 11.7645C9.6069 12.519 9.88897 13.3025 10.4991 13.777L14 16.5L15.5 21"/><path d="M9.5 16L9 17.5L6.5 20.5"/><path d="M15 4.5C15 5.32843 14.3284 6 13.5 6C12.6716 6 12 5.32843 12 4.5C12 3.67157 12.6716 3 13.5 3C14.3284 3 15 3.67157 15 4.5Z"/></svg>'
    };

    // Colores y configuraciones por tipo de transporte (valores por defecto)
    let transportConfig = {
        'plane': { color: '#FF4444', icon: transportIcons.plane, dashArray: '10, 5' },
        'ship': { color: '#00AAAA', icon: transportIcons.ship, dashArray: '10, 5' },
        'car': { color: '#4444FF', icon: transportIcons.car, dashArray: null },
        'train': { color: '#FF8800', icon: transportIcons.train, dashArray: null },
        'walk': { color: '#44FF44', icon: transportIcons.walk, dashArray: null }
    };

    // Iconos por tipo de punto
    const pointTypeConfig = {
        'stay': { 
            emoji: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4V20C3 20.9428 3 21.4142 3.29289 21.7071C3.58579 22 4.05719 22 5 22H19C19.9428 22 20.4142 22 20.7071 21.7071C21 21.4142 21 20.9428 21 20V4"/><path d="M10.5 8V9.5M10.5 11V9.5M13.5 8V9.5M13.5 11V9.5M10.5 9.5H13.5"/><path d="M14 22L14 17.9999C14 16.8954 13.1046 15.9999 12 15.9999C10.8954 15.9999 10 16.8954 10 17.9999V22"/><path d="M2 4H8C8.6399 2.82727 10.1897 2 12 2C13.8103 2 15.3601 2.82727 16 4H22"/><path d="M6 8H7M6 12H7M6 16H7"/><path d="M17 8H18M17 12H18M17 16H18"/></svg>', 
            labelKey: 'map.point_type_stay',
            label: __('map.point_type_stay'),
            color: '#FF6B6B' 
        },
        'visit': { 
            emoji: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8.31253 4.7812L7.6885 4.36517V4.36517L8.31253 4.7812ZM7.5 6V6.75C7.75076 6.75 7.98494 6.62467 8.12404 6.41603L7.5 6ZM2.17224 8.83886L1.45453 8.62115L2.17224 8.83886ZM4.83886 6.17224L4.62115 5.45453H4.62115L4.83886 6.17224ZM3.46243 20.092L3.93822 19.5123L3.93822 19.5123L3.46243 20.092ZM2.90796 19.5376L3.48772 19.0618L3.48772 19.0618L2.90796 19.5376ZM21.092 19.5376L20.5123 19.0618L20.5123 19.0618L21.092 19.5376ZM20.5376 20.092L20.0618 19.5123L20.0618 19.5123L20.5376 20.092ZM14.0195 3.89791C14.3847 4.09336 14.8392 3.95575 15.0346 3.59054C15.2301 3.22534 15.0924 2.77084 14.7272 2.57539L14.0195 3.89791ZM22.5455 8.62115C22.4252 8.22477 22.0064 8.00092 21.61 8.12116C21.2137 8.2414 20.9898 8.6602 21.1101 9.05658L22.5455 8.62115ZM21.25 11.5V13.5H22.75V11.5H21.25ZM14.5 20.25H9.5V21.75H14.5V20.25ZM2.75 13.5V11.5H1.25V13.5H2.75ZM12.3593 2.25H11.6407V3.75H12.3593V2.25ZM7.6885 4.36517L6.87596 5.58397L8.12404 6.41603L8.93657 5.19722L7.6885 4.36517ZM11.6407 2.25C11.1305 2.25 10.6969 2.24925 10.3369 2.28282C9.96142 2.31783 9.61234 2.39366 9.27276 2.57539L9.98055 3.89791C10.0831 3.84299 10.2171 3.80049 10.4762 3.77634C10.7506 3.75075 11.1031 3.75 11.6407 3.75V2.25ZM8.93657 5.19722C9.23482 4.74985 9.43093 4.45704 9.60448 4.24286C9.76825 4.04074 9.87794 3.95282 9.98055 3.89791L9.27276 2.57539C8.93318 2.75713 8.67645 3.00553 8.43904 3.29853C8.2114 3.57947 7.97154 3.94062 7.6885 4.36517L8.93657 5.19722ZM2.75 11.5C2.75 10.0499 2.75814 9.49107 2.88994 9.05657L1.45453 8.62115C1.24186 9.32224 1.25 10.159 1.25 11.5H2.75ZM7.5 5.25C6.159 5.25 5.32224 5.24186 4.62115 5.45453L5.05657 6.88994C5.49107 6.75814 6.04987 6.75 7.5 6.75V5.25ZM2.88994 9.05657C3.20503 8.01787 4.01787 7.20503 5.05657 6.88994L4.62115 5.45453C3.10304 5.91505 1.91505 7.10304 1.45453 8.62115L2.88994 9.05657ZM9.5 20.25C7.83789 20.25 6.65724 20.2488 5.75133 20.1417C4.86197 20.0366 4.33563 19.8384 3.93822 19.5123L2.98663 20.6718C3.69558 21.2536 4.54428 21.5095 5.57525 21.6313C6.58966 21.7512 7.87463 21.75 9.5 21.75V20.25ZM1.25 13.5C1.25 15.1254 1.24877 16.4103 1.36868 17.4248C1.49054 18.4557 1.74638 19.3044 2.3282 20.0134L3.48772 19.0618C3.16158 18.6644 2.96343 18.138 2.85831 17.2487C2.75123 16.3428 2.75 15.1621 2.75 13.5H1.25ZM3.93822 19.5123C3.77366 19.3772 3.62277 19.2263 3.48772 19.0618L2.3282 20.0134C2.52558 20.2539 2.74612 20.4744 2.98663 20.6718L3.93822 19.5123ZM21.25 13.5C21.25 15.1621 21.2488 16.3428 21.1417 17.2487C21.0366 18.138 20.8384 18.6644 20.5123 19.0618L21.6718 20.0134C22.2536 19.3044 22.5095 18.4557 22.6313 17.4248C22.7512 16.4103 22.75 15.1254 22.75 13.5H21.25ZM14.5 21.75C16.1254 21.75 17.4103 21.7512 18.4248 21.6313C19.4557 21.5095 20.3044 21.2536 21.0134 20.6718L20.0618 19.5123C19.6644 19.8384 19.138 20.0366 18.2487 20.1417C17.3428 20.2488 16.1621 20.25 14.5 20.25V21.75ZM20.5123 19.0618C20.3772 19.2263 20.2263 19.3772 20.0618 19.5123L21.0134 20.6718C21.2539 20.4744 21.4744 20.2539 21.6718 20.0134L20.5123 19.0618ZM12.3593 3.75C12.8969 3.75 13.2494 3.75075 13.5238 3.77634C13.7829 3.80049 13.9169 3.84299 14.0195 3.89791L14.7272 2.57539C14.3877 2.39366 14.0386 2.31783 13.6631 2.28282C13.3031 2.24925 12.8695 2.25 12.3593 2.25V3.75ZM22.75 11.5C22.75 10.159 22.7581 9.32224 22.5455 8.62115L21.1101 9.05658C21.2419 9.49107 21.25 10.0499 21.25 11.5H22.75Z" fill="currentColor" stroke="none"/><circle cx="12" cy="13" r="4" stroke="currentColor" fill="none"/><path d="M17.9737 3.02148C17.9795 2.99284 18.0205 2.99284 18.0263 3.02148C18.3302 4.50808 19.4919 5.66984 20.9785 5.97368C21.0072 5.97954 21.0072 6.02046 20.9785 6.02632C19.4919 6.33016 18.3302 7.49192 18.0263 8.97852C18.0205 9.00716 17.9795 9.00716 17.9737 8.97852C17.6698 7.49192 16.5081 6.33016 15.0215 6.02632C14.9928 6.02046 14.9928 5.97954 15.0215 5.97368C16.5081 5.66984 17.6698 4.50808 17.9737 3.02148Z" fill="currentColor" stroke="none"/></svg>', 
            labelKey: 'map.point_type_visit',
            label: __('map.point_type_visit'),
            color: '#4ECDC4' 
        },
        'food': { 
            emoji: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M21 17C18.2386 17 16 14.7614 16 12C16 9.23858 18.2386 7 21 7"/><path d="M21 21C16.0294 21 12 16.9706 12 12C12 7.02944 16.0294 3 21 3"/><path d="M6 3L6 8M6 21L6 11"/><path d="M3.5 8H8.5"/><path d="M9 3L9 7.35224C9 12.216 3 12.2159 3 7.35207L3 3"/></svg>', 
            labelKey: 'map.point_type_food',
            label: __('map.point_type_food'),
            color: '#FFE66D' 
        }
    };

    /**
     * Carga la configuración desde el servidor
     */
    function loadConfig() {
        return $.ajax({
            url: BASE_URL + '/api/get_config.php',
            method: 'GET',
            dataType: 'json'
        }).done(function(response) {
            if (response.success && response.data) {
                appConfig = response.data;
                
                // Actualizar colores de transporte con la configuración del servidor
                if (appConfig.transportColors) {
                    transportConfig.plane.color = appConfig.transportColors.plane || transportConfig.plane.color;
                    transportConfig.ship.color = appConfig.transportColors.ship || transportConfig.ship.color;
                    transportConfig.car.color = appConfig.transportColors.car || transportConfig.car.color;
                    transportConfig.train.color = appConfig.transportColors.train || transportConfig.train.color;
                    transportConfig.walk.color = appConfig.transportColors.walk || transportConfig.walk.color;
                }
            }
        }).fail(function(xhr, status, error) {
            console.warn('Config load failed, using defaults');
        });
    }

    /**
     * Inicializa el mapa
     */
    function initMap() {
        // Obtener configuración del mapa
        const mapConfig = appConfig?.map || {};
        const clusterEnabled = mapConfig.clusterEnabled !== false; // Por defecto true
        const maxClusterRadius = mapConfig.maxClusterRadius || 30;
        const disableClusteringAtZoom = mapConfig.disableClusteringAtZoom || 15;
        
        // Crear mapa centrado en vista global
        map = L.map('map', {
            center: [20, 0],
            zoom: 2,
            minZoom: 2,
            maxZoom: 18,
            zoomControl: true
        });

        // Capa base de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Cluster global para todos los puntos (solo si está habilitado)
        if (clusterEnabled) {
            allPointsCluster = L.markerClusterGroup({
                showCoverageOnHover: false,
                zoomToBoundsOnClick: true,
                spiderfyOnMaxZoom: true,
                maxClusterRadius: maxClusterRadius,
                disableClusteringAtZoom: disableClusteringAtZoom,
                removeOutsideVisibleBounds: true,
                chunkedLoading: true,
                chunkInterval: 200,
                iconCreateFunction: function(cluster) {
                    const count = cluster.getChildCount();
                    return L.divIcon({
                        html: `<div class="marker-cluster-custom"><span>${count}</span></div>`,
                        className: 'custom-cluster-icon',
                        iconSize: L.point(40, 40)
                    });
                }
            });

            map.addLayer(allPointsCluster);
        } else {
            // Si el clustering está deshabilitado, usar un LayerGroup normal
            allPointsCluster = L.layerGroup();
            map.addLayer(allPointsCluster);
        }

    }

    /**
     * Renderiza la leyenda de transporte con los colores configurados
     */
    function renderLegend() {
        const legendItems = $('#legendItems');
        if (legendItems.length === 0) return;
        
        legendItems.empty();
        
        // Definir el orden y labels de los tipos de transporte
        const transportOrder = [
            { type: 'plane', icon: transportIcons.plane, label: __('map.transport_plane') },
            { type: 'car', icon: transportIcons.car, label: __('map.transport_car') },
            { type: 'train', icon: transportIcons.train, label: __('map.transport_train') },
            { type: 'ship', icon: transportIcons.ship, label: __('map.transport_ship') },
            { type: 'walk', icon: transportIcons.walk, label: __('map.transport_walk') }
        ];
        
        transportOrder.forEach(function(item) {
            const config = transportConfig[item.type];
            if (config) {
                const legendItem = $(`
                    <div class="legend-item">
                        <div class="legend-line" style="background-color: ${config.color};"></div>
                        <small>${item.icon} ${item.label}</small>
                    </div>
                `);
                legendItems.append(legendItem);
            }
        });
        
        // Add future trips indicator to legend
        const futureLegendIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 2V6M8 2V6"/><path d="M21 15V12C21 8.22876 21 6.34315 19.8284 5.17157C18.6569 4 16.7712 4 13 4H11C7.22876 4 5.34315 4 4.17157 5.17157C3 6.34315 3 8.22876 3 12V14C3 17.7712 3 19.6569 4.17157 20.8284C5.34315 22 7.22876 22 11 22H12"/><path d="M3 10H21"/><path d="M18.5 22C19.0057 21.5085 21 20.2002 21 19.5C21 18.7998 19.0057 17.4915 18.5 17M20.5 19.5H14"/></svg>';
        const futureLegendItem = $(`
            <div class="legend-item legend-future-separator">
                <div class="legend-line legend-future" style="background: repeating-linear-gradient(90deg, #6B6B6B, #6B6B6B 2px, transparent 2px, transparent 8px);"></div>
                <small>${futureLegendIcon} ${__('map.upcoming_trip')}</small>
            </div>
        `);
        legendItems.append(futureLegendItem);
    }

    /**
     * Carga los datos desde la API
     */
    function loadData() {
        $.ajax({
            url: API_URL,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.trips) {
                    tripsData = response.data.trips;
                    
                    // Order is important: render trips first (populates layers), 
                    // then panel (applies saved selections)
                    renderAllTrips();
                    renderTripsPanel();
                    applyInitialToggleStates();
                    fitMapToContent();
                } else {
                    showError(__('map.no_trips_found'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar datos:', error);
                showError(__('map.error_loading_data'));
            }
        });
    }

    /**
     * Groups trips by year based on start_date
     */
    function groupTripsByYear(trips) {
        const grouped = {};
        const futureYear = 'future';
        
        trips.forEach(function(trip) {
            let year;
            if (isFutureTrip(trip)) {
                year = futureYear;
            } else if (trip.start_date) {
                year = new Date(trip.start_date + 'T00:00:00').getFullYear().toString();
            } else {
                year = 'Sin fecha';
            }
            
            if (!grouped[year]) {
                grouped[year] = [];
            }
            grouped[year].push(trip);
        });
        
        return grouped;
    }

    /**
     * Gets sorted year keys (future first, then descending years)
     */
    function getSortedYearKeys(groupedTrips) {
        const keys = Object.keys(groupedTrips);
        return keys.sort(function(a, b) {
            if (a === 'future') return -1;
            if (b === 'future') return 1;
            if (a === 'Sin fecha') return 1;
            if (b === 'Sin fecha') return -1;
            return parseInt(b) - parseInt(a); // Descending order
        });
    }

    /**
     * Gets saved year collapsed states from preferences
     * Returns null if no states have been saved yet (first visit)
     */
    function getYearCollapsedStates() {
        const prefs = loadPreferences();
        return prefs.yearCollapsedStates || null;
    }

    /**
     * Determines which years should be expanded by default
     * - "future" (Próximos viajes) always expanded
     * - Current year expanded, or last year if current has no trips
     */
    function getDefaultExpandedYears(sortedYears) {
        const expanded = ['future']; // Always expand future trips
        
        const currentYear = new Date().getFullYear().toString();
        
        // Check if current year has trips
        if (sortedYears.includes(currentYear)) {
            expanded.push(currentYear);
        } else {
            // Find the most recent year with trips (excluding 'future' and 'Sin fecha')
            for (let i = 0; i < sortedYears.length; i++) {
                const year = sortedYears[i];
                if (year !== 'future' && year !== 'Sin fecha') {
                    expanded.push(year);
                    break;
                }
            }
        }
        
        return expanded;
    }

    /**
     * Saves year collapsed state to preferences
     */
    function saveYearCollapsedState(year, isCollapsed) {
        const prefs = loadPreferences();
        if (!prefs.yearCollapsedStates) {
            prefs.yearCollapsedStates = {};
        }
        prefs.yearCollapsedStates[year] = isCollapsed;
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        } catch (e) {
            console.warn('Error saving year collapsed state:', e);
        }
    }

    /**
     * Renderiza el panel de viajes con checkboxes agrupados por año
     */
    function renderTripsPanel() {
        const $tripsList = $('#tripsList');
        $tripsList.empty();

        if (tripsData.length === 0) {
            $tripsList.html('<p class="text-muted small text-center">' + __('map.no_trips_available') + '</p>');
            return;
        }

        // Group trips by year
        const groupedTrips = groupTripsByYear(tripsData);
        const sortedYears = getSortedYearKeys(groupedTrips);
        const savedCollapsedStates = getYearCollapsedStates();
        
        // Determine collapsed states: use saved or calculate defaults
        let collapsedStates = {};
        if (savedCollapsedStates === null) {
            // First visit: collapse all except future and current/last year
            const expandedYears = getDefaultExpandedYears(sortedYears);
            sortedYears.forEach(function(year) {
                collapsedStates[year] = !expandedYears.includes(year);
            });
        } else {
            collapsedStates = savedCollapsedStates;
        }
        
        // Icons
        const chevronDown = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>';
        const chevronRight = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>';
        const futureIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 2V6M8 2V6"/><path d="M21 15V12C21 8.22876 21 6.34315 19.8284 5.17157C18.6569 4 16.7712 4 13 4H11C7.22876 4 5.34315 4 4.17157 5.17157C3 6.34315 3 8.22876 3 12V14C3 17.7712 3 19.6569 4.17157 20.8284C5.34315 22 7.22876 22 11 22H12"/><path d="M3 10H21"/><path d="M18.5 22C19.0057 21.5085 21 20.2002 21 19.5C21 18.7998 19.0057 17.4915 18.5 17M20.5 19.5H14"/></svg>';
        const routeIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="19" r="3"/><path d="M12 5H8.5C6.567 5 5 6.567 5 8.5C5 10.433 6.567 12 8.5 12H15.5C17.433 12 19 13.567 19 15.5C19 17.433 17.433 19 15.5 19H12"/></svg>';
        const pointIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M7 18C5.17107 18.4117 4 19.0443 4 19.7537C4 20.9943 7.58172 22 12 22C16.4183 22 20 20.9943 20 19.7537C20 19.0443 18.8289 18.4117 17 18"/><path d="M14.5 9C14.5 10.3807 13.3807 11.5 12 11.5C10.6193 11.5 9.5 10.3807 9.5 9C9.5 7.61929 10.6193 6.5 12 6.5C13.3807 6.5 14.5 7.61929 14.5 9Z"/><path d="M13.2574 17.4936C12.9201 17.8184 12.4693 18 12.0002 18C11.531 18 11.0802 17.8184 10.7429 17.4936C7.6543 14.5008 3.51519 11.1575 5.53371 6.30373C6.6251 3.67932 9.24494 2 12.0002 2C14.7554 2 17.3752 3.67933 18.4666 6.30373C20.4826 11.1514 16.3536 14.5111 13.2574 17.4936Z"/></svg>';
        
        sortedYears.forEach(function(year) {
            const trips = groupedTrips[year];
            const yearId = 'year-' + year.replace(/\s/g, '-');
            const isCollapsed = collapsedStates[year] === true;
            const isFutureGroup = year === 'future';
            const yearLabel = isFutureGroup ? __('map.upcoming_trips') : year;
            const yearClass = isFutureGroup ? 'year-group year-future' : 'year-group';
            
            // Calculate totals for the year
            let totalRoutes = 0;
            let totalPoints = 0;
            trips.forEach(function(trip) {
                totalRoutes += trip.routes ? trip.routes.length : 0;
                totalPoints += trip.points ? trip.points.length : 0;
            });
            
            // Year group header
            const $yearGroup = $(`
                <div class="${yearClass}" data-year="${year}">
                    <div class="year-header">
                        <div class="year-header-left">
                            <input class="form-check-input year-checkbox" 
                                   type="checkbox" 
                                   id="${yearId}-checkbox" 
                                   data-year="${year}"
                                   checked>
                            <button class="year-toggle-btn" type="button" data-target="${yearId}">
                                <span class="year-chevron">${isCollapsed ? chevronRight : chevronDown}</span>
                                <span class="year-label">${isFutureGroup ? futureIcon + ' ' : ''}${yearLabel}</span>
                                <span class="year-count badge">${trips.length}</span>
                            </button>
                        </div>
                        <div class="year-stats">
                            <span title="Rutas">${routeIcon} ${totalRoutes}</span>
                            <span title="Puntos">${pointIcon} ${totalPoints}</span>
                        </div>
                    </div>
                    <div class="year-trips ${isCollapsed ? 'collapsed' : ''}" id="${yearId}">
                    </div>
                </div>
            `);
            
            const $yearTrips = $yearGroup.find('.year-trips');
            
            // Render trips in this year group
            trips.forEach(function(trip) {
                const routesCount = trip.routes ? trip.routes.length : 0;
                const pointsCount = trip.points ? trip.points.length : 0;
                const isFuture = isFutureTrip(trip);
                const futureBadge = isFuture ? `<span class="badge bg-light text-secondary border ms-2" style="font-size: 0.65rem;">${futureIcon} Próximo</span>` : '';
                const itemClass = isFuture ? 'trip-filter-item trip-future' : 'trip-filter-item';
                const colorIndicator = isFuture ? '#6B6B6B' : trip.color;
                
                const $tripItem = $(`
                    <div class="${itemClass}">
                        <div class="form-check d-flex align-items-start gap-2">
                            <input class="form-check-input trip-checkbox flex-shrink-0 mt-1" 
                                   type="checkbox" 
                                   id="trip-${trip.id}" 
                                   value="${trip.id}"
                                   data-year="${year}"
                                   checked>
                            <div class="trip-color-dot mt-1" style="background-color: ${colorIndicator};"></div>
                            <label class="form-check-label flex-grow-1" for="trip-${trip.id}">
                                <span class="trip-title">${escapeHtml(trip.title)}${futureBadge}</span>
                                <span class="trip-details">
                                    ${formatDateRange(trip.start_date, trip.end_date)}
                                    <span class="trip-counts">
                                        <span title="Rutas">${routeIcon} ${routesCount}</span>
                                        <span title="Puntos">${pointIcon} ${pointsCount}</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                `);
                
                $yearTrips.append($tripItem);
            });
            
            $tripsList.append($yearGroup);
        });

        // Year toggle (collapse/expand) events
        $('.year-toggle-btn').on('click', function() {
            const targetId = $(this).data('target');
            const $target = $('#' + targetId);
            const $chevron = $(this).find('.year-chevron');
            const year = $(this).closest('.year-group').data('year');
            const isCollapsing = !$target.hasClass('collapsed');
            
            $target.toggleClass('collapsed');
            $chevron.html(isCollapsing ? chevronRight : chevronDown);
            
            // Save collapsed state
            saveYearCollapsedState(year, isCollapsing);
        });

        // Year checkbox events
        $('.year-checkbox').on('change', function() {
            const year = $(this).data('year');
            const isChecked = $(this).is(':checked');
            
            // Toggle all trips in this year
            $(`.trip-checkbox[data-year="${year}"]`).each(function() {
                $(this).prop('checked', isChecked);
                const tripId = parseInt($(this).val());
                if (isChecked) {
                    showTrip(tripId);
                } else {
                    hideTrip(tripId);
                }
            });
            
            savePreferences();
        });

        // Trip checkbox events
        $('.trip-checkbox').on('change', function() {
            onTripToggle.call(this);
            updateYearCheckboxState($(this).data('year'));
            savePreferences();
        });
        
        // Apply saved trip selections after rendering
        applyTripSelectionPreferences();
        
        // Update year checkbox states based on trip selections
        sortedYears.forEach(function(year) {
            updateYearCheckboxState(year);
        });
    }

    /**
     * Updates year checkbox state based on its trips
     */
    function updateYearCheckboxState(year) {
        const $yearCheckbox = $(`.year-checkbox[data-year="${year}"]`);
        const $tripCheckboxes = $(`.trip-checkbox[data-year="${year}"]`);
        const totalTrips = $tripCheckboxes.length;
        const checkedTrips = $tripCheckboxes.filter(':checked').length;
        
        if (checkedTrips === 0) {
            $yearCheckbox.prop('checked', false);
            $yearCheckbox.prop('indeterminate', false);
        } else if (checkedTrips === totalTrips) {
            $yearCheckbox.prop('checked', true);
            $yearCheckbox.prop('indeterminate', false);
        } else {
            $yearCheckbox.prop('checked', false);
            $yearCheckbox.prop('indeterminate', true);
        }
    }

    /**
     * Renderiza todos los viajes en el mapa
     */
    function renderAllTrips() {
        tripsData.forEach(function(trip) {
            renderTrip(trip);
        });
    }

    /**
     * Renderiza un viaje individual
     */
    function renderTrip(trip) {
        const tripId = trip.id;

        // Inicializar arrays de layers (simple arrays, no cluster groups)
        routesLayers[tripId] = [];
        flightRoutesLayers[tripId] = [];
        pointsClusters[tripId] = []; // Simple array to track markers

        // Renderizar rutas
        if (trip.routes && trip.routes.length > 0) {
            trip.routes.forEach(function(route) {
                renderRoute(route, trip);
            });
        }

        // Renderizar puntos
        if (trip.points && trip.points.length > 0) {
            trip.points.forEach(function(point) {
                renderPoint(point, trip);
            });
        }
    }

    /**
     * Determina si un viaje es futuro basándose en su fecha de inicio
     */
    function isFutureTrip(trip) {
        if (!trip.start_date) return false;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const tripStart = new Date(trip.start_date + 'T00:00:00');
        return tripStart > today;
    }

    /**
     * Renderiza una ruta en el mapa
     */
    function renderRoute(route, trip) {
        if (!route.geojson || !route.geojson.geometry) {
            return;
        }

        const transportType = route.transport_type || 'car';
        
        // For plane routes, defer creation until user enables them
        if (transportType === 'plane') {
            if (!flightRoutesData[trip.id]) {
                flightRoutesData[trip.id] = [];
            }
            flightRoutesData[trip.id].push({ route: route, trip: trip });
            return;
        }
        
        // For other transport types, create layers immediately
        const config = transportConfig[transportType] || transportConfig['car'];
        const isFuture = isFutureTrip(trip);
        const color = isFuture ? '#6B6B6B' : (config.color || route.color);
        const dashArray = isFuture ? '2, 6' : config.dashArray;
        const opacity = 0.7;

        const layer = L.geoJSON(route.geojson, {
            style: {
                color: color,
                weight: isFuture ? 3 : 4,
                opacity: opacity,
                dashArray: dashArray
            },
            onEachFeature: function(feature, lyr) {
                const futureIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 2V6M8 2V6"/><path d="M21 15V12C21 8.22876 21 6.34315 19.8284 5.17157C18.6569 4 16.7712 4 13 4H11C7.22876 4 5.34315 4 4.17157 5.17157C3 6.34315 3 8.22876 3 12V14C3 17.7712 3 19.6569 4.17157 20.8284C5.34315 22 7.22876 22 11 22H12"/><path d="M3 10H21"/><path d="M18.5 22C19.0057 21.5085 21 20.2002 21 19.5C21 18.7998 19.0057 17.4915 18.5 17M20.5 19.5H14"/></svg>';
                const futureLabel = isFuture ? ` <span class="badge bg-secondary">${futureIconSvg} Próximo</span>` : '';
                lyr.bindPopup(`
                    <div class="route-popup">
                        <strong>${config.icon} ${escapeHtml(trip.title)}</strong>${futureLabel}<br>
                        <small class="text-muted">Transporte: ${transportType}</small>
                    </div>
                `);
            }
        });

        layer.addTo(map);
        routesLayers[trip.id].push(layer);
    }

    /**
     * Creates flight route layers on demand (deferred creation with batching)
     */
    function createFlightRouteLayers(callback) {
        if (flightRoutesCreated) {
            if (callback) callback();
            return;
        }
        
        // Flatten all flight data into a single array for batch processing
        const allFlightData = [];
        Object.keys(flightRoutesData).forEach(function(tripId) {
            if (!flightRoutesLayers[tripId]) {
                flightRoutesLayers[tripId] = [];
            }
            flightRoutesData[tripId].forEach(function(data) {
                allFlightData.push({ tripId: tripId, route: data.route, trip: data.trip });
            });
        });
        
        // Process in batches (polylines are fast, can do more per batch)
        const BATCH_SIZE = 50;
        let index = 0;
        
        function processBatch() {
            const end = Math.min(index + BATCH_SIZE, allFlightData.length);
            
            for (let i = index; i < end; i++) {
                const item = allFlightData[i];
                const layer = createFlightLayer(item.route, item.trip);
                if (layer) {
                    flightRoutesLayers[item.tripId].push(layer);
                    // Add to map if flight toggle is enabled
                    if ($('#toggleFlightRoutes').is(':checked') && 
                        $('#trip-' + item.tripId).is(':checked')) {
                        layer.addTo(map);
                    }
                }
            }
            
            index = end;
            
            if (index < allFlightData.length) {
                // More to process - schedule next batch
                requestAnimationFrame(processBatch);
            } else {
                // Done
                flightRoutesCreated = true;
                if (callback) callback();
            }
        }
        
        // Start processing
        processBatch();
    }

    /**
     * Creates a single flight layer (simple polyline - fast)
     */
    function createFlightLayer(route, trip) {
        if (!route.geojson || !route.geojson.geometry || route.geojson.geometry.type !== 'LineString') {
            return null;
        }
        
        const coords = route.geojson.geometry.coordinates;
        const isFuture = isFutureTrip(trip);
        const config = transportConfig['plane'];
        const color = isFuture ? '#6B6B6B' : (config.color || route.color);
        const dashArray = isFuture ? '2, 4' : '4, 6';
        const opacity = isFuture ? 0.5 : 0.6;
        
        // Simple straight line (much faster than Bézier curves)
        const latLngs = coords.map(function(c) { return [c[1], c[0]]; });
        
        const layer = L.polyline(latLngs, {
            color: color,
            weight: 2,
            opacity: opacity,
            dashArray: dashArray
        });
        
        // Popup
        const futureIconSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 2V6M8 2V6"/><path d="M21 15V12C21 8.22876 21 6.34315 19.8284 5.17157C18.6569 4 16.7712 4 13 4H11C7.22876 4 5.34315 4 4.17157 5.17157C3 6.34315 3 8.22876 3 12V14C3 17.7712 3 19.6569 4.17157 20.8284C5.34315 22 7.22876 22 11 22H12"/><path d="M3 10H21"/><path d="M18.5 22C19.0057 21.5085 21 20.2002 21 19.5C21 18.7998 19.0057 17.4915 18.5 17M20.5 19.5H14"/></svg>';
        const futureLabel = isFuture ? ` <span class="badge bg-secondary">${futureIconSvg} Próximo</span>` : '';
        layer.bindPopup(`
            <div class="route-popup">
                <strong>${config.icon} ${escapeHtml(trip.title)}</strong>${futureLabel}<br>
                <small class="text-muted">Transporte: plane</small>
            </div>
        `);
        
        return layer;
    }

    /**
     * Renderiza un punto de interés
     */
    function renderPoint(point, trip) {
        const typeConfig = pointTypeConfig[point.type] || pointTypeConfig['visit'];
        
        // Icono personalizado
        const icon = L.divIcon({
            className: 'custom-point-marker',
            html: `<div class="point-marker-inner" style="background-color: ${typeConfig.color}; border-color: ${trip.color};">
                        <span>${typeConfig.emoji}</span>
                   </div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 36],
            popupAnchor: [0, -36]
        });

        const marker = L.marker([point.latitude, point.longitude], {
            icon: icon,
            title: point.title
        });

        // Popup con información completa
        const popupContent = createPointPopup(point, trip, typeConfig);
        marker.bindPopup(popupContent, {
            maxWidth: 300,
            className: 'custom-popup'
        });

        // Add to global cluster and track in simple array
        allPointsCluster.addLayer(marker);
        pointsClusters[trip.id].push(marker);
    }

    /**
     * Crea el contenido HTML del popup de un punto
     */
    function createPointPopup(point, trip, typeConfig) {
        let html = '<div class="point-popup">';
        
        // Imagen si existe (clicable para abrir lightbox)
        // Usar thumbnail para mostrar en popup, full image para lightbox
        if (point.image_url) {
            const displayImage = point.thumbnail_url || point.image_url;
            html += `<img src="${displayImage}" alt="${escapeHtml(point.title)}" class="popup-image" onclick="openLightbox('${point.image_url}', '${escapeHtml(point.title)}')" title="${__('map.click_to_view_full')}">`;
        }
        
        // Contenido
        html += '<div class="popup-content">';
        
        // Título
        html += `<h6 class="popup-title">${escapeHtml(point.title)}</h6>`;
        
        // Badge del tipo
        const typeLabel = typeConfig.labelKey ? __(typeConfig.labelKey) : typeConfig.label;
        html += `<span class="badge mb-2" style="background-color: ${typeConfig.color}; display: inline-flex; align-items: center; gap: 4px;">${typeConfig.emoji} <span>${typeLabel}</span></span>`;
        
        // Viaje
        html += `<p class="popup-trip mb-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-map me-1" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M15.817.113A.5.5 0 0 1 16 .5v14a.5.5 0 0 1-.402.49l-5 1a.5.5 0 0 1-.196 0L5.5 15.01l-4.902.98A.5.5 0 0 1 0 15.5v-14a.5.5 0 0 1 .402-.49l5-1a.5.5 0 0 1 .196 0L10.5.99l4.902-.98a.5.5 0 0 1 .415.103M10 1.91l-4-.8v12.98l4 .8zm1 12.98 4-.8V1.11l-4 .8zm-6-.8V1.11l-4 .8v12.98z"/>
                    </svg>
                    <span style="color: ${trip.color}; font-weight: bold;">${escapeHtml(trip.title)}</span>
                 </p>`;
        
        // Fecha
        if (point.visit_date) {
            html += `<p class="popup-date mb-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-calendar-event me-1" viewBox="0 0 16 16">
                            <path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5z"/>
                            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/>
                        </svg>
                        ${formatDate(point.visit_date)}
                     </p>`;
        }
        
        // Descripción
        if (point.description) {
            html += `<p class="popup-description">${escapeHtml(point.description)}</p>`;
        }
        
        // Coordenadas
        html += `<p class="popup-coords">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-geo-alt me-1" viewBox="0 0 16 16">
                        <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/>
                        <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                    </svg>
                    ${point.latitude.toFixed(6)}, ${point.longitude.toFixed(6)}
                 </p>`;
        
        html += '</div></div>';
        
        return html;
    }

    /**
     * Ajusta la vista del mapa para mostrar todo el contenido
     */
    function fitMapToContent() {
        if (tripsData.length === 0) {
            return;
        }

        const bounds = L.latLngBounds();
        let hasContent = false;

        // Agregar rutas a los bounds (solo rutas con getBounds, skip curves)
        Object.values(routesLayers).forEach(function(layers) {
            layers.forEach(function(layer) {
                try {
                    if (layer.getBounds && typeof layer.getBounds === 'function') {
                        const layerBounds = layer.getBounds();
                        if (layerBounds && layerBounds.isValid()) {
                            bounds.extend(layerBounds);
                            hasContent = true;
                        }
                    }
                } catch (e) {
                    // Skip layers that don't support getBounds
                }
            });
        });

        // Agregar puntos a los bounds
        try {
            const clusterLayers = allPointsCluster.getLayers();
            if (clusterLayers && clusterLayers.length > 0) {
                bounds.extend(allPointsCluster.getBounds());
                hasContent = true;
            }
        } catch (e) {
            // Skip if cluster bounds fail
        }

        if (hasContent && bounds.isValid()) {
            map.fitBounds(bounds, { padding: [50, 50] });
        }
    }

    /**
     * Maneja el toggle de un viaje
     */
    function onTripToggle(e) {
        const tripId = parseInt($(this).val());
        const isChecked = $(this).is(':checked');

        if (isChecked) {
            showTrip(tripId);
        } else {
            hideTrip(tripId);
        }
    }

    /**
     * Muestra un viaje en el mapa
     */
    function showTrip(tripId) {
        // Mostrar rutas (excepto avión si el toggle está desactivado)
        if (routesLayers[tripId] && $('#toggleRoutes').is(':checked')) {
            routesLayers[tripId].forEach(function(layer) {
                if (!map.hasLayer(layer)) {
                    layer.addTo(map);
                }
            });
        }

        // Mostrar rutas en avión solo si el toggle está activado
        if ($('#toggleFlightRoutes').is(':checked')) {
            // Create flight layers on demand if not yet created
            if (!flightRoutesCreated) {
                createFlightRouteLayers();
            }
            
            if (flightRoutesLayers[tripId]) {
                flightRoutesLayers[tripId].forEach(function(layer) {
                    if (!map.hasLayer(layer)) {
                        layer.addTo(map);
                    }
                });
            }
        }

        // Mostrar puntos
        if (pointsClusters[tripId]) {
            pointsClusters[tripId].forEach(function(marker) {
                if (!allPointsCluster.hasLayer(marker)) {
                    allPointsCluster.addLayer(marker);
                }
            });
        }
    }

    /**
     * Oculta un viaje del mapa
     */
    function hideTrip(tripId) {
        // Ocultar rutas
        if (routesLayers[tripId]) {
            routesLayers[tripId].forEach(function(layer) {
                if (map.hasLayer(layer)) {
                    map.removeLayer(layer);
                }
            });
        }

        // Ocultar rutas en avión
        if (flightRoutesLayers[tripId]) {
            flightRoutesLayers[tripId].forEach(function(layer) {
                if (map.hasLayer(layer)) {
                    map.removeLayer(layer);
                }
            });
        }

        // Ocultar puntos
        if (pointsClusters[tripId]) {
            pointsClusters[tripId].forEach(function(marker) {
                if (allPointsCluster.hasLayer(marker)) {
                    allPointsCluster.removeLayer(marker);
                }
            });
        }
    }

    /**
     * Muestra un mensaje de error
     */
    function showError(message) {
        $('#tripsList').html(`
            <div class="alert alert-warning" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-triangle me-2" viewBox="0 0 16 16">
                    <path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057m1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/>
                    <path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/>
                </svg>
                ${message}
            </div>
        `);
    }

    /**
     * Formatea un rango de fechas
     */
    function formatDateRange(startDate, endDate) {
        if (!startDate && !endDate) {
            return 'Sin fechas';
        }
        
        const start = startDate ? formatDate(startDate) : '';
        const end = endDate ? formatDate(endDate) : '';
        
        if (start && end) {
            return `${start} - ${end}`;
        }
        return start || end;
    }

    /**
     * Formatea una fecha
     */
    function formatDate(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString + 'T00:00:00');
        return date.toLocaleDateString('es-ES', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }

    /**
     * Escapa HTML para prevenir XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
    }

    /**
     * Busca lugares usando Nominatim (OpenStreetMap)
     */
    function searchPublicPlace(query) {
        if (!query || query.trim().length < 3) {
            alert(__('map.search_min_chars'));
            return;
        }

        const searchResults = $('#publicSearchResults');
        searchResults.html('<div class="list-group-item small"><div class="spinner-border spinner-border-sm me-2"></div>' + __('map.searching') + '</div>');
        searchResults.show();

        // Usar proxy local para evitar problemas de CORS
        const url = `${BASE_URL}/api/geocode.php?q=${encodeURIComponent(query)}&limit=5`;

        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            success: function(results) {
                searchResults.empty();

                if (!results || results.length === 0) {
                    searchResults.html('<div class="list-group-item small text-muted">' + __('map.no_results') + '</div>');
                    return;
                }

                if (results.error) {
                    searchResults.html(`<div class="list-group-item small text-danger">${results.error}</div>`);
                    return;
                }

                results.forEach(function(place) {
                    const displayName = place.display_name;
                    const lat = parseFloat(place.lat);
                    const lon = parseFloat(place.lon);
                    
                    const item = $(`
                        <button type="button" class="list-group-item list-group-item-action small" data-lat="${lat}" data-lon="${lon}">
                            <div>
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-geo-alt-fill me-1" viewBox="0 0 16 16">
                                    <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/>
                                </svg>
                                <strong>${place.name || place.type}</strong>
                            </div>
                            <div class="text-muted" style="font-size: 0.85em;">${displayName}</div>
                        </button>
                    `);

                    item.on('click', function() {
                        const lat = parseFloat($(this).data('lat'));
                        const lon = parseFloat($(this).data('lon'));
                        goToPublicPlace(lat, lon);
                    });

                    searchResults.append(item);
                });
            },
            error: function(xhr, status, error) {
                console.error('Error en búsqueda:', error);
                let errorMsg = 'Error al buscar. Intenta nuevamente.';
                
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                
                searchResults.html(`<div class="list-group-item small text-danger">${errorMsg}</div>`);
            }
        });
    }

    /**
     * Centra el mapa en un lugar
     */
    function goToPublicPlace(lat, lng) {
        map.setView([lat, lng], 12);
        
        // Ocultar resultados
        $('#publicSearchResults').hide();
        $('#publicPlaceSearch').val('');

    }

    /**
     * Configuración de event handlers
     */
    function setupEventHandlers() {
        // Búsqueda de lugares
        $('#publicSearchBtn').on('click', function() {
            const query = $('#publicPlaceSearch').val();
            searchPublicPlace(query);
        });

        $('#publicPlaceSearch').on('keypress', function(e) {
            if (e.which === 13) { // Enter
                e.preventDefault();
                const query = $(this).val();
                searchPublicPlace(query);
            }
        });

        // Ocultar resultados al hacer clic fuera
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#publicPlaceSearch, #publicSearchResults, #publicSearchBtn').length) {
                $('#publicSearchResults').hide();
            }
        });

        // Toggle de rutas
        $('#toggleRoutes').on('change', function() {
            const show = $(this).is(':checked');
            $('.trip-checkbox:checked').each(function() {
                const tripId = parseInt($(this).val());
                if (routesLayers[tripId]) {
                    routesLayers[tripId].forEach(function(layer) {
                        if (show) {
                            if (!map.hasLayer(layer)) layer.addTo(map);
                        } else {
                            if (map.hasLayer(layer)) map.removeLayer(layer);
                        }
                    });
                }
            });
            savePreferences();
        });

        // Toggle de rutas en avión
        $('#toggleFlightRoutes').on('change', function() {
            const show = $(this).is(':checked');
            
            if (show) {
                // Create flight layers on first enable (batched creation handles adding to map)
                if (!flightRoutesCreated) {
                    createFlightRouteLayers();
                } else {
                    // Already created, just show them
                    $('.trip-checkbox:checked').each(function() {
                        const tripId = parseInt($(this).val());
                        if (flightRoutesLayers[tripId]) {
                            flightRoutesLayers[tripId].forEach(function(layer) {
                                if (!map.hasLayer(layer)) layer.addTo(map);
                            });
                        }
                    });
                }
            } else {
                // Hide all flight routes
                Object.values(flightRoutesLayers).forEach(function(layers) {
                    layers.forEach(function(layer) {
                        if (map.hasLayer(layer)) map.removeLayer(layer);
                    });
                });
            }
            savePreferences();
        });

        // Toggle de puntos
        $('#togglePoints').on('change', function() {
            const show = $(this).is(':checked');
            if (show) {
                if (!map.hasLayer(allPointsCluster)) {
                    map.addLayer(allPointsCluster);
                }
            } else {
                if (map.hasLayer(allPointsCluster)) {
                    map.removeLayer(allPointsCluster);
                }
            }
            savePreferences();
        });

        // Filter buttons
        $('#filterAll').on('click', function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            $('.trip-checkbox').prop('checked', true).trigger('change');
            savePreferences();
        });

        $('#filterPast').on('click', function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            // Uncheck future trips, check past trips
            tripsData.forEach(function(trip) {
                const isFuture = isFutureTrip(trip);
                $('#trip-' + trip.id).prop('checked', !isFuture).trigger('change');
            });
            savePreferences();
        });

        $('#filterNone').on('click', function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            $('.trip-checkbox').prop('checked', false).trigger('change');
            savePreferences();
        });

        // Inicializar lightbox
        initLightbox();
    }

    // Inicialización principal: cargar configuración primero, luego inicializar el mapa
    $(document).ready(function() {
        // Apply saved preferences to controls before anything else
        applyPreferencesToControls();
        
        loadConfig().always(function() {
            // Inicializar el mapa después de cargar la configuración
            initMap();
            renderLegend(); // Renderizar leyenda con colores configurados
            loadData();
            setupEventHandlers();
        });
    });

    /**
     * Inicializa el lightbox para imágenes
     */
    function initLightbox() {
        const lightbox = document.getElementById('imageLightbox');
        if (lightbox) {
            // Cerrar lightbox al hacer click en cualquier parte
            lightbox.addEventListener('click', function() {
                closeLightbox();
            });
        }
    }

    /**
     * Abre el lightbox con una imagen
     */
    window.openLightbox = function(imageUrl, altText) {
        const lightbox = document.getElementById('imageLightbox');
        const lightboxImage = document.getElementById('lightboxImage');
        
        if (lightbox && lightboxImage) {
            lightboxImage.src = imageUrl;
            lightboxImage.alt = altText || '';
            lightbox.style.display = 'flex';
            
            // Prevenir scroll del body
            document.body.style.overflow = 'hidden';
        }
    };

    /**
     * Cierra el lightbox
     */
    window.closeLightbox = function() {
        const lightbox = document.getElementById('imageLightbox');
        
        if (lightbox) {
            lightbox.style.display = 'none';
            
            // Restaurar scroll del body
            document.body.style.overflow = '';
        }
    };

    // También cerrar con tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLightbox();
        }
    });

})();
