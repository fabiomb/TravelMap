/**
 * Public Map - MapLibre GL + deck.gl Implementation
 * 
 * Consumes the API and renders all trips with routes and points on the map
 * Uses WebGL for high-performance rendering of flight arcs
 */

(function () {
    'use strict';

    // Global variables
    let map;
    let tripsData = [];
    let appConfig = null;
    let deckOverlay = null;
    let deckLoaded = false;
    let supercluster = null;
    let clusterMarkers = [];
    let pointMarkers = [];
    let popup = null;

    // Map styles — delegated to MapConfig
    const MAP_STYLES = MapConfig.MAP_STYLES;
    const getMapStyleUrl = MapConfig.getMapStyleUrl;

    // Track visibility state
    let visibleTripIds = new Set();
    let showRoutes = true;
    let showPoints = true;
    let showFlightRoutes = false;

    // Route sources and layers tracking
    let routeSourcesAdded = new Set();

    // Throttle timer for cluster updates (performance)
    let clusterUpdateTimer = null;
    const CLUSTER_UPDATE_DELAY = 100; // ms - throttle cluster recalculations

    // Idle detection for reducing GPU usage when not interacting
    let idleTimer = null;
    let isIdle = false;
    const IDLE_TIMEOUT = 3000; // ms - time before map goes idle

    // LocalStorage key for user preferences
    const STORAGE_KEY = 'travelmap_preferences';

    /**
     * Parse URL parameters for map configuration
     * Supported parameters:
     * - center: lat,lng (e.g., ?center=40.4168,-3.7038)
     * - zoom: number (e.g., ?zoom=10)
     * - trips: comma-separated IDs (e.g., ?trips=1,2,3)
     * - routes: 1/0 or true/false (e.g., ?routes=1)
     * - points: 1/0 or true/false (e.g., ?points=0)
     * - flights: 1/0 or true/false (e.g., ?flights=1)
     */
    function getURLParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const params = {};

        // Parse center parameter (lat,lng)
        if (urlParams.has('center')) {
            const centerStr = urlParams.get('center');
            const parts = centerStr.split(',').map(s => parseFloat(s.trim()));
            if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
                params.center = parts; // [lat, lng]
            }
        }

        // Parse zoom parameter
        if (urlParams.has('zoom')) {
            const zoomVal = parseFloat(urlParams.get('zoom'));
            if (!isNaN(zoomVal) && zoomVal >= 1 && zoomVal <= 18) {
                params.zoom = zoomVal;
            }
        }

        // Parse trips parameter (comma-separated IDs)
        if (urlParams.has('trips')) {
            const tripsStr = urlParams.get('trips');
            const tripIds = tripsStr.split(',')
                .map(s => parseInt(s.trim()))
                .filter(id => !isNaN(id) && id > 0);
            if (tripIds.length > 0) {
                params.trips = tripIds;
            }
        }

        // Parse boolean parameters (routes, points, flights)
        ['routes', 'points', 'flights'].forEach(function (key) {
            if (urlParams.has(key)) {
                const val = urlParams.get(key).toLowerCase();
                params[key] = val === '1' || val === 'true';
            }
        });

        return params;
    }

    // Icons and config — delegated to MapConfig
    const transportIcons = MapConfig.transportIcons;
    const transportConfig = MapConfig.transportConfig; // mutable — server colors applied below
    const pointTypeIcons = MapConfig.pointTypeIcons;

    // SVG icons for stats (page-specific, not shared)
    const statsIcons = {
        routes: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="19" r="3"/><path d="M12 5H8.5C6.567 5 5 6.567 5 8.5C5 10.433 6.567 12 8.5 12H15.5C17.433 12 19 13.567 19 15.5C19 17.433 17.433 19 15.5 19H12"/></svg>',
        points: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"><path d="M7 18C5.17107 18.4117 4 19.0443 4 19.7537C4 20.9943 7.58172 22 12 22C16.4183 22 20 20.9943 20 19.7537C20 19.0443 18.8289 18.4117 17 18"/><path d="M14.5 9C14.5 10.3807 13.3807 11.5 12 11.5C10.6193 11.5 9.5 10.3807 9.5 9C9.5 7.61929 10.6193 6.5 12 6.5C13.3807 6.5 14.5 7.61929 14.5 9Z"/><path d="M13.2574 17.4936C12.9201 17.8184 12.4693 18 12.0002 18C11.531 18 11.0802 17.8184 10.7429 17.4936C7.6543 14.5008 3.51519 11.1575 5.53371 6.30373C6.6251 3.67932 9.24494 2 12.0002 2C14.7554 2 17.3752 3.67933 18.4666 6.30373C20.4826 11.1514 16.3536 14.5111 13.2574 17.4936Z"/></svg>'
    };

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
        return {
            showRoutes: true,
            showPoints: true,
            showFlightRoutes: false,
            selectedTrips: null
        };
    }

    /**
     * Save user preferences to localStorage
     */
    function savePreferences() {
        try {
            const existingPrefs = loadPreferences();
            const prefs = {
                showRoutes: $('#toggleRoutes').is(':checked'),
                showPoints: $('#togglePoints').is(':checked'),
                showFlightRoutes: $('#toggleFlightRoutes').is(':checked'),
                selectedTrips: getSelectedTripIds(),
                knownTripIds: getAllTripIds(),
                performanceMode: existingPrefs.performanceMode, // Preserve performance mode setting
                yearCollapsedStates: existingPrefs.yearCollapsedStates, // Preserve collapsed states
                groupBy: existingPrefs.groupBy || 'year' // Preserve groupBy preference
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        } catch (e) {
            console.warn('Error saving preferences to localStorage:', e);
        }
    }

    /**
     * Save groupBy preference to localStorage
     */
    function saveGroupByPreference(groupBy) {
        try {
            const prefs = loadPreferences();
            prefs.groupBy = groupBy;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        } catch (e) {
            console.warn('Error saving groupBy preference:', e);
        }
    }

    function getAllTripIds() {
        return tripsData.map(trip => trip.id);
    }

    function getSelectedTripIds() {
        const selected = [];
        $('.trip-checkbox:checked').each(function () {
            selected.push(parseInt($(this).val()));
        });
        return selected;
    }

    function applyPreferencesToControls() {
        const prefs = loadPreferences();
        $('#toggleRoutes').prop('checked', prefs.showRoutes);
        $('#togglePoints').prop('checked', prefs.showPoints);
        $('#toggleFlightRoutes').prop('checked', prefs.showFlightRoutes);
        showRoutes = prefs.showRoutes;
        showPoints = prefs.showPoints;
        showFlightRoutes = prefs.showFlightRoutes;
    }

    /**
     * Apply language to map labels by modifying the text-field properties
     * to use the localized name field from OpenStreetMap data
     * 
     * @param {string} lang - Language code (e.g., 'en', 'es', 'fr')
     */
    function applyLanguageToMap(lang) {
        if (!map || !map.getStyle()) return;

        // Get the current map style
        const style = map.getStyle();
        if (!style || !style.layers) return;

        // Language field mapping for OpenStreetMap data
        // name:XX where XX is the language code
        const langField = `name:${lang}`;

        // Process each layer that has text labels
        style.layers.forEach(layer => {
            if (layer.layout && layer.layout['text-field']) {
                const currentTextField = layer.layout['text-field'];

                // Skip if it's already a complex expression
                if (Array.isArray(currentTextField) && currentTextField[0] === 'coalesce') {
                    return;
                }

                // Create a coalesce expression that tries:
                // 1. Localized name (name:es, name:en, etc.)
                // 2. Fallback to default name
                // 3. Fallback to name:en if available
                // 4. Fallback to original name
                const newTextField = [
                    'coalesce',
                    ['get', langField],
                    ['get', 'name:en'],
                    ['get', 'name']
                ];

                // Update the layer's text-field
                map.setLayoutProperty(layer.id, 'text-field', newTextField);
            }
        });

        console.log(`Map labels updated to language: ${lang}`);
    }

    /**
     * Load config from server
     */
    function loadConfig() {
        return $.ajax({
            url: BASE_URL + '/api/get_config.php',
            method: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (response.success && response.data) {
                appConfig = response.data;
                if (appConfig.transportColors) {
                    transportConfig.plane.color = appConfig.transportColors.plane || transportConfig.plane.color;
                    transportConfig.ship.color = appConfig.transportColors.ship || transportConfig.ship.color;
                    transportConfig.car.color = appConfig.transportColors.car || transportConfig.car.color;
                    transportConfig.bus.color = appConfig.transportColors.bus || transportConfig.bus.color;
                    transportConfig.train.color = appConfig.transportColors.train || transportConfig.train.color;
                    transportConfig.walk.color = appConfig.transportColors.walk || transportConfig.walk.color;
                    transportConfig.aerial.color = appConfig.transportColors.aerial || transportConfig.aerial.color;
                }
            }
        }).fail(function () {
            console.warn('Config load failed, using defaults');
        });
    }

    /**
     * Formats distance based on transport type and user preferences
     */
    function formatDistance(meters, transportType, isRoundTrip) {
        if (!meters || meters <= 0) return '';

        const formatted = UnitManager.formatDistance(meters);
        const roundTripIcon = isRoundTrip ? ` <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ms-1 text-warning" style="vertical-align: text-bottom;" title="${__('routes.is_round_trip') || 'Ida y Vuelta'}"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></svg>` : '';

        return ` · ${formatted}${roundTripIcon}`;
    }

    /**
     * Formatea fecha/hora de inicio y fin de ruta
     */
    function formatRouteDatetime(start, end) {
        const isValid = (v) => v && v !== 'null' && v !== 'undefined' && v !== '';
        if (!isValid(start) && !isValid(end)) return '';
        
        const opts = { day: '2-digit', month: 'short' };
        const timeOpts = { hour: '2-digit', minute: '2-digit' };
        const locale = document.documentElement.lang || 'es-ES';
        
        const parseDate = (dt) => {
            if (!isValid(dt)) return null;
            let d = new Date(dt);
            if (!isNaN(d.getTime())) return d;
            d = new Date(dt.replace(' ', 'T'));
            if (!isNaN(d.getTime())) return d;
            const parts = dt.match(/(\d{4})-(\d{2})-(\d{2})\s*(\d{2}):?(\d{2})?/);
            if (parts) {
                return new Date(parts[1], parts[2] - 1, parts[3], parts[4] || 0, parts[5] || 0);
            }
            return null;
        };
        
        const formatDate = (dt) => {
            const d = parseDate(dt);
            if (!d) return '';
            return d.toLocaleDateString(locale, opts);
        };
        
        const formatTime = (dt) => {
            const d = parseDate(dt);
            if (!d) return '';
            return d.toLocaleTimeString(locale, timeOpts);
        };
        
        const startDate = formatDate(start);
        const startTime = formatTime(start);
        const endDate = formatDate(end);
        const endTime = formatTime(end);
        
        if (startDate && startTime && endDate && endTime) {
            return `${startDate} ${startTime} - ${endTime}`;
        } else if (startDate && startTime) {
            return `${startDate} ${startTime}`;
        } else if (startDate) {
            return startDate;
        } else if (endDate && endTime) {
            return `${endDate} ${endTime}`;
        } else if (endDate) {
            return endDate;
        }
        return '';
    }

    /**
     * Initialize the MapLibre GL map
     */
    function initMap() {
        // Get configured map style or default to voyager
        const mapStyleKey = appConfig?.map?.style || 'voyager';
        const mapStyleUrl = MAP_STYLES[mapStyleKey] || MAP_STYLES['voyager'];

        // Detect current language for map labels
        const currentLang = window.i18n?.currentLang || document.documentElement.lang || 'en';

        // Create MapLibre GL map with performance optimizations
        map = new maplibregl.Map({
            container: 'map',
            style: mapStyleUrl,
            center: [0, 20],
            zoom: 2,
            minZoom: 1,
            maxZoom: 18,
            attributionControl: true,
            language: currentLang,  // Set map language
            // Performance optimizations for AMD/low-end GPUs
            antialias: false,           // Disable antialiasing (major GPU saver)
            preserveDrawingBuffer: false,
            fadeDuration: 0,            // Disable fade animations
            trackResize: true,
            refreshExpiredTiles: false, // Don't refresh unchanged tiles
            maxTileCacheSize: 50,       // Limit tile cache
            crossSourceCollisions: false // Reduce collision detection
        });

        // Add navigation controls
        map.addControl(new maplibregl.NavigationControl(), 'top-left');

        // Create popup instance
        popup = new maplibregl.Popup({
            closeButton: true,
            closeOnClick: false,
            maxWidth: '320px'
        });

        // Track if we just opened a popup (to prevent immediate close)
        let popupOpenTime = 0;

        // Listen for popup open events
        popup.on('open', function () {
            popupOpenTime = Date.now();
        });

        // Close popup when clicking on the map canvas (not markers or popups)
        document.getElementById('map').addEventListener('click', function (e) {
            // Check if click is on the canvas itself, not on markers or popups
            if (e.target.classList.contains('maplibregl-canvas')) {
                // Only close if popup wasn't just opened (within 100ms)
                if (Date.now() - popupOpenTime > 100) {
                    popup.remove();
                }
            }
        });

        // Wait for map to load before adding layers
        map.on('load', function () {
            console.log('MapLibre GL map loaded');
            // Set default cursor - use the map container element
            document.getElementById('map').style.cursor = 'default';

            // Apply language to map labels
            applyLanguageToMap(currentLang);

            loadData();
        });

        // Update clusters on zoom (throttled for performance)
        map.on('zoom', throttledClusterUpdate);
        map.on('moveend', throttledClusterUpdate);

        // Idle detection to reduce GPU usage when not interacting
        function resetIdleTimer() {
            if (isIdle) {
                isIdle = false;
                // Wake up the map - trigger repaint
                map.triggerRepaint();
            }
            if (idleTimer) clearTimeout(idleTimer);
            idleTimer = setTimeout(() => {
                isIdle = true;
                // When idle, stop continuous rendering
                // The map will still respond to interactions
            }, IDLE_TIMEOUT);
        }

        map.on('move', resetIdleTimer);
        map.on('zoom', resetIdleTimer);
        map.on('click', resetIdleTimer);
        map.on('mousemove', resetIdleTimer);

        // Start idle timer
        resetIdleTimer();
    }

    /**
     * Throttled cluster update to reduce CPU usage
     */
    function throttledClusterUpdate() {
        if (clusterUpdateTimer) {
            clearTimeout(clusterUpdateTimer);
        }
        clusterUpdateTimer = setTimeout(updateClusters, CLUSTER_UPDATE_DELAY);
    }

    /**
     * Load data from API
     */
    function loadData() {
        $.ajax({
            url: API_URL,
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data && response.data.trips) {
                    tripsData = response.data.trips;
                    initSupercluster();
                    renderAllTrips();
                    renderTripsPanel();
                    applyInitialToggleStates();
                    fitMapToContent();
                    renderLegend();
                } else {
                    showError(__('map.no_trips_found'));
                }
            },
            error: function (xhr, status, error) {
                console.error('Error loading data:', error);
                showError(__('map.error_loading_data'));
            }
        });
    }

    /**
     * Initialize Supercluster for point clustering
     */
    function initSupercluster() {
        const mapConfig = appConfig?.map || {};
        const maxClusterRadius = mapConfig.maxClusterRadius || 50;

        supercluster = new Supercluster({
            radius: maxClusterRadius,
            maxZoom: 16,
            minZoom: 0
        });
    }

    /**
     * Render all trips on the map
     */
    function renderAllTrips() {
        const allPoints = [];
        // Track coordinates to detect co-located points
        const coordsMap = new Map();

        tripsData.forEach(function (trip) {
            visibleTripIds.add(trip.id);

            // Render routes
            if (trip.routes && trip.routes.length > 0) {
                trip.routes.forEach(function (route) {
                    renderRoute(route, trip);
                });
            }

            // Collect points for clustering
            if (trip.points && trip.points.length > 0) {
                trip.points.forEach(function (point) {
                    const coordKey = `${point.latitude.toFixed(6)},${point.longitude.toFixed(6)}`;

                    // Count how many points are at this location
                    const existingCount = coordsMap.get(coordKey) || 0;
                    coordsMap.set(coordKey, existingCount + 1);

                    // Apply small offset if there are multiple points at same location
                    // Offset creates a small spiral pattern around the original point
                    let offsetLat = point.latitude;
                    let offsetLon = point.longitude;

                    if (existingCount > 0) {
                        const angle = (existingCount * 137.5) * (Math.PI / 180); // Golden angle for good distribution
                        const radius = 0.00015 * Math.sqrt(existingCount); // Grows slightly with each point
                        offsetLat = point.latitude + radius * Math.cos(angle);
                        offsetLon = point.longitude + radius * Math.sin(angle);
                    }

                    allPoints.push({
                        type: 'Feature',
                        properties: {
                            ...point,
                            tripId: trip.id,
                            tripTitle: trip.title,
                            tripColor: trip.color,
                            tripTags: trip.tags || [],
                            originalLat: point.latitude,
                            originalLon: point.longitude
                        },
                        geometry: {
                            type: 'Point',
                            coordinates: [offsetLon, offsetLat]
                        }
                    });
                });
            }
        });

        // Load points into supercluster
        supercluster.load(allPoints);
        updateClusters();

        // Render flight arcs with deck.gl
        updateFlightArcs();
    }

    /**
     * Render a route on the map.
     * Layer creation is delegated to MapRenderer.addRouteLayer; popup handlers are page-specific.
     */
    function renderRoute(route, trip) {
        const transportType = route.transport_type || 'car';
        const isPlaneRoute = transportType === 'plane';
        const isFuture = isFutureTrip(trip);
        const sourceId = `route-${trip.id}-${route.id}`;
        const layerId = `route-layer-${trip.id}-${route.id}`;
        const visibility = isPlaneRoute ? showFlightRoutes : showRoutes;

        const arcEntry = MapRenderer.addRouteLayer(map, route, sourceId, layerId, {
            isFuture: isFuture,
            visibility: visibility ? 'visible' : 'none'
        });

        if (arcEntry !== null) return; // Simple plane arc — handled by deck.gl

        routeSourcesAdded.add(sourceId);

        // Popup and cursor handlers (page-specific)
        if (map.getLayer(layerId)) {
            const config = transportConfig[transportType] || transportConfig['car'];

            map.on('click', layerId, function (e) {
                e.preventDefault();
                const futureLabel = isFuture ? ` <span class="badge bg-secondary">Próximo</span>` : '';

                let tagsHtml = '';
                if (appConfig?.tripTagsEnabled && trip.tags && trip.tags.length > 0) {
                    tagsHtml = '<div class="mt-1 d-flex gap-1 flex-wrap">';
                    trip.tags.forEach(tag => {
                        tagsHtml += `<span class="badge bg-light text-dark border" style="font-size: 0.7em;">${escapeHtml(tag)}</span>`;
                    });
                    tagsHtml += '</div>';
                }

                let linksHtml = '';
                if (route.links && route.links.length > 0) {
                    linksHtml = '<div class="mt-1 d-flex gap-1 flex-wrap">';
                    route.links.forEach(link => {
                        const color = link.color || '#6c757d';
                        const svgPaths = link.svg_paths || '<path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z"/><path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243z"/>';
                        const label = link.label || link.type || 'Link';
                        linksHtml += `<a href="${escapeHtml(link.url)}" target="_blank" class="text-decoration-none" title="${escapeHtml(label)}"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="${color}" viewBox="0 0 16 16">${svgPaths}</svg></a>`;
                    });
                    linksHtml += '</div>';
                }

                popup.setLngLat(e.lngLat)
                    .setHTML(`
                        <div class="route-popup">
                            ${(route.image_url || route.imageUrl) ? `<img src="${route.image_url || route.imageUrl}" alt="" style="width: 100%; max-height: 150px; object-fit: cover; margin-bottom: 8px; border-radius: 4px; cursor: pointer;" onclick="openLightbox('${route.image_url || route.imageUrl}', '${escapeHtml(route.name || trip.title)}')" title="${__('map.click_to_view_full', '')}">` : ''}
                            <strong>${config.icon} ${escapeHtml(trip.title)}</strong>${appConfig?.tripPageEnabled ? ` <a href="trip.php?id=${trip.id}" target="_blank" class="ms-1 text-muted text-decoration-none" title="${__('map.view_trip_details')}"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/></svg></a>` : ''}${futureLabel}
                            ${route.name ? `<br><small class="text-primary fw-bold">${escapeHtml(route.name)}</small>` : ''}
                            ${(route.start_datetime && route.start_datetime !== 'null') || (route.end_datetime && route.end_datetime !== 'null') ? `<br><small class="text-secondary">${formatRouteDatetime(route.start_datetime, route.end_datetime)}</small>` : ''}
                            ${route.description ? `<br><small class="text-muted">${escapeHtml(route.description)}</small>` : ''}
                            ${linksHtml}
                            ${tagsHtml}
                            <br>
                            <small class="text-muted">${__('map.transport')}: ${__('map.transport_' + transportType)}${formatDistance(route.distance_meters, transportType, route.is_round_trip)}</small>
                        </div>
                    `)
                    .addTo(map);
            });

            map.on('mouseenter', layerId, function () { document.getElementById('map').style.cursor = 'pointer'; });
            map.on('mouseleave', layerId, function () { document.getElementById('map').style.cursor = 'default'; });
        }
    }

    /**
     * Update flight arcs using deck.gl (lazy loaded for performance)
     */
    function updateFlightArcs() {
        if (!showFlightRoutes) {
            if (deckOverlay) {
                deckOverlay.setProps({ layers: [] });
            }
            return;
        }

        // Lazy load deck.gl if not already loaded
        if (typeof deck === 'undefined') {
            if (!deckLoaded) {
                deckLoaded = true; // Prevent multiple loads
                console.log('Loading deck.gl on demand...');
                const script = document.createElement('script');
                script.src = BASE_URL + '/assets/vendor/deckgl/deck.gl.min.js';
                script.onload = function () {
                    console.log('deck.gl loaded');
                    renderFlightArcs();
                };
                script.onerror = function () {
                    console.error('Failed to load deck.gl');
                    deckLoaded = false;
                };
                document.head.appendChild(script);
            }
            return;
        }

        renderFlightArcs();
    }

    /**
     * Actually render the flight arcs (called after deck.gl is loaded)
     */
    function renderFlightArcs() {
        const flightData = [];

        tripsData.forEach(function (trip) {
            if (!visibleTripIds.has(trip.id)) return;

            if (trip.routes && trip.routes.length > 0) {
                trip.routes.forEach(function (route) {
                    if (route.transport_type === 'plane' && route.geojson && route.geojson.geometry) {
                        const coords = route.geojson.geometry.coordinates;
                        // Only render as arc if it's a simple A-to-B flight (exactly 2 points)
                        // Complex aerial routes with 3+ waypoints are rendered as MapLibre lines
                        if (coords && coords.length === 2) {
                            const isFuture = isFutureTrip(trip);
                            flightData.push({
                                source: coords[0],
                                target: coords[1],
                                tripId: trip.id,
                                tripTitle: trip.title,
                                routeName: route.name || '',
                                routeDescription: route.description || '',
                                image_url: route.image_url || null,
                                thumbnail_url: route.thumbnail_url || null,
                                startDatetime: route.start_datetime || '',
                                endDatetime: route.end_datetime || '',
                                routeLinks: route.links || [],
                                isFuture: isFuture,
                                distanceMeters: route.distance_meters,
                                isRoundTrip: route.is_round_trip,
                                color: isFuture ? [107, 107, 107, 150] : MapConfig.hexToRgba(transportConfig.plane.color, 180)
                            });
                        }
                    }
                });
            }
        });

        // Create or update deck.gl overlay with performance optimizations
        const arcLayer = new deck.ArcLayer({
            id: 'flight-arcs',
            data: flightData,
            getSourcePosition: d => d.source,
            getTargetPosition: d => d.target,
            getSourceColor: d => d.color,
            getTargetColor: d => d.color,
            getWidth: 2,
            getHeight: 0.3,
            greatCircle: true,
            pickable: true,
            // Performance: reduce arc segments for smoother rendering
            numSegments: 25,            // Default is 50, reduce for performance
            updateTriggers: {
                getSourcePosition: flightData.length,
                getTargetPosition: flightData.length
            },
            onHover: (info) => {
                // Change cursor to pointer when hovering over flight arcs
                document.getElementById('map').style.cursor = info.object ? 'pointer' : 'default';
            },
            onClick: (info) => {
                if (info.object) {
                    const d = info.object;
                    const trip = tripsData.find(t => t.id === d.tripId); // Buscar trip completo para tags
                    const futureLabel = d.isFuture ? ` <span class="badge bg-secondary">Próximo</span>` : '';

                    // Generar HTML de tags
                    let tagsHtml = '';
                    if (appConfig?.tripTagsEnabled && trip && trip.tags && trip.tags.length > 0) {
                        tagsHtml = '<div class="mt-1 d-flex gap-1 flex-wrap">';
                        trip.tags.forEach(tag => {
                            tagsHtml += `<span class="badge bg-light text-dark border" style="font-size: 0.7em;">${escapeHtml(tag)}</span>`;
                        });
                        tagsHtml += '</div>';
                    }

                    let linksHtml = '';
                    if (d.routeLinks && d.routeLinks.length > 0) {
                        linksHtml = '<div class="mt-1 d-flex gap-1 flex-wrap">';
                        d.routeLinks.forEach(link => {
                            const color = link.color || '#6c757d';
                            const svgPaths = link.svg_paths || '<path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z"/><path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243z"/>';
                            const label = link.label || link.type || 'Link';
                            linksHtml += `<a href="${escapeHtml(link.url)}" target="_blank" class="text-decoration-none" title="${escapeHtml(label)}"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="${color}" viewBox="0 0 16 16">${svgPaths}</svg></a>`;
                        });
                        linksHtml += '</div>';
                    }

                    popup.setLngLat(info.coordinate)
                        .setHTML(`
                            <div class="route-popup">
                                ${(d.image_url || d.thumbnail_url) ? `<img src="${d.image_url || d.thumbnail_url}" alt="" style="width: 100%; max-height: 150px; object-fit: cover; margin-bottom: 8px; border-radius: 4px; cursor: pointer;" onclick="openLightbox('${d.image_url || d.thumbnail_url}', '${escapeHtml(d.routeName || d.tripTitle)}')" title="${__('map.click_to_view_full', '')}">` : ''}
                                <strong>${transportIcons.plane} ${escapeHtml(d.tripTitle)}</strong>${appConfig?.tripPageEnabled ? ` <a href="trip.php?id=${d.tripId}" target="_blank" class="ms-1 text-muted text-decoration-none" title="${__('map.view_trip_details')}"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/></svg></a>` : ''}${futureLabel}
                                ${d.routeName ? `<br><small class="text-primary fw-bold">${escapeHtml(d.routeName)}</small>` : ''}
                                ${(d.startDatetime && d.startDatetime !== 'null') || (d.endDatetime && d.endDatetime !== 'null') ? `<br><small class="text-secondary">${formatRouteDatetime(d.startDatetime, d.endDatetime)}</small>` : ''}
                                ${d.routeDescription ? `<br><small class="text-muted">${escapeHtml(d.routeDescription)}</small>` : ''}
                                ${linksHtml}
                                ${tagsHtml}
                                <br>
                                <small class="text-muted">${__('map.transport')}: ${__('map.transport_plane')}${formatDistance(d.distanceMeters, 'plane', d.isRoundTrip)}</small>
                            </div>
                        `)
                        .addTo(map);
                }
            }
        });

        if (!deckOverlay) {
            deckOverlay = new deck.MapboxOverlay({
                interleaved: true,
                layers: [arcLayer]
            });
            map.addControl(deckOverlay);
        } else {
            deckOverlay.setProps({ layers: [arcLayer] });
        }
    }

    /**
     * Update cluster markers
     */
    function updateClusters() {
        if (!supercluster || !showPoints) {
            clearClusterMarkers();
            return;
        }

        const bounds = map.getBounds();
        const zoom = Math.floor(map.getZoom());

        // Get visible points (filter by visible trips)
        const visiblePoints = supercluster.getClusters(
            [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()],
            zoom
        ).filter(feature => {
            if (feature.properties.cluster) {
                // For clusters, check if any point belongs to visible trip
                const leaves = supercluster.getLeaves(feature.properties.cluster_id, Infinity);
                return leaves.some(leaf => visibleTripIds.has(leaf.properties.tripId));
            }
            return visibleTripIds.has(feature.properties.tripId);
        });

        // Clear existing markers
        clearClusterMarkers();

        // Create new markers
        visiblePoints.forEach(function (feature) {
            const coords = feature.geometry.coordinates;

            if (feature.properties.cluster) {
                // Cluster marker
                const count = feature.properties.point_count;
                const el = MapRenderer.createClusterMarkerEl(count);

                const marker = new maplibregl.Marker({ element: el })
                    .setLngLat(coords)
                    .addTo(map);

                // Click to zoom into cluster
                el.addEventListener('click', function (e) {
                    e.stopPropagation(); // Prevent map click handler
                    const expansionZoom = supercluster.getClusterExpansionZoom(feature.properties.cluster_id);
                    map.easeTo({
                        center: coords,
                        zoom: expansionZoom
                    });
                });

                clusterMarkers.push(marker);
            } else {
                // Individual point marker with icon
                const point = feature.properties;
                const el = MapRenderer.createPointMarkerEl(point);

                const marker = new maplibregl.Marker({ element: el, anchor: 'bottom' })
                    .setLngLat(coords)
                    .addTo(map);

                // Click to show popup
                el.addEventListener('click', function (e) {
                    e.stopPropagation(); // Prevent map click from closing popup
                    popup.setLngLat(coords)
                        .setHTML(MapRenderer.createPoiPopup(point, {
                            tripColor: point.tripColor,
                            tripTitle: point.tripTitle,
                            tripTags: point.tripTags,
                            tripTagsEnabled: appConfig && appConfig.tripTagsEnabled
                        }))
                        .addTo(map);
                });

                pointMarkers.push(marker);
            }
        });
    }

    /**
     * Clear all cluster markers
     */
    function clearClusterMarkers() {
        clusterMarkers.forEach(m => m.remove());
        clusterMarkers = [];
        pointMarkers.forEach(m => m.remove());
        pointMarkers = [];
    }


    /**
     * Check if trip is in the future
     */
    function isFutureTrip(trip) {
        if (trip.status === 'planned') return true;
        if (!trip.start_date) return false;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const tripStart = new Date(trip.start_date + 'T00:00:00');
        return tripStart > today;
    }

    /**
     * Fit map to content bounds
     */
    function fitMapToContent() {
        const urlParams = getURLParams();

        // If URL has center and/or zoom parameters, use those
        if (urlParams.center || urlParams.zoom) {
            if (urlParams.center && urlParams.zoom) {
                // Both center and zoom specified
                map.setCenter([urlParams.center[1], urlParams.center[0]]); // [lng, lat]
                map.setZoom(urlParams.zoom);
            } else if (urlParams.center) {
                // Only center specified
                map.setCenter([urlParams.center[1], urlParams.center[0]]);
            } else if (urlParams.zoom) {
                // Only zoom specified
                map.setZoom(urlParams.zoom);
            }
            return;
        }

        // Otherwise, fit to content bounds
        const bounds = new maplibregl.LngLatBounds();
        let hasContent = false;

        tripsData.forEach(function (trip) {
            if (trip.routes) {
                trip.routes.forEach(function (route) {
                    if (route.geojson && route.geojson.geometry && route.geojson.geometry.coordinates) {
                        const coords = route.geojson.geometry.coordinates;
                        coords.forEach(function (coord) {
                            bounds.extend(coord);
                            hasContent = true;
                        });
                    }
                });
            }
            if (trip.points) {
                trip.points.forEach(function (point) {
                    bounds.extend([point.longitude, point.latitude]);
                    hasContent = true;
                });
            }
        });

        if (hasContent && !bounds.isEmpty()) {
            map.fitBounds(bounds, { padding: 50 });
        }
    }

    /**
     * Show/hide trip on map
     */
    function showTrip(tripId) {
        visibleTripIds.add(tripId);
        updateTripVisibility(tripId, true);
    }

    function hideTrip(tripId) {
        visibleTripIds.delete(tripId);
        updateTripVisibility(tripId, false);
    }

    function updateTripVisibility(tripId, visible) {
        // Update route layer visibility
        const layers = map.getStyle().layers || [];
        layers.forEach(function (layer) {
            if (layer.metadata && layer.metadata.tripId === tripId) {
                const transportType = layer.metadata?.transportType;
                // Plane routes use showFlightRoutes, others use showRoutes
                const toggleState = transportType === 'plane' ? showFlightRoutes : showRoutes;
                map.setLayoutProperty(layer.id, 'visibility', visible && toggleState ? 'visible' : 'none');
            }
        });

        // Update clusters and flight arcs
        updateClusters();
        updateFlightArcs();
    }

    /**
     * Toggle all routes visibility (excludes plane routes - those use flights toggle)
     */
    function toggleRoutes(show) {
        showRoutes = show;
        const layers = map.getStyle().layers || [];
        layers.forEach(function (layer) {
            if (layer.id.startsWith('route-layer-')) {
                const tripId = layer.metadata?.tripId;
                const transportType = layer.metadata?.transportType;
                // Skip plane routes - they're controlled by flights toggle
                if (transportType === 'plane') return;
                const visible = show && visibleTripIds.has(tripId);
                map.setLayoutProperty(layer.id, 'visibility', visible ? 'visible' : 'none');
            }
        });
    }

    /**
     * Toggle points visibility
     */
    function togglePoints(show) {
        showPoints = show;
        if (show) {
            updateClusters();
        } else {
            clearClusterMarkers();
        }
    }

    /**
     * Toggle flight routes visibility (both deck.gl arcs and complex plane line layers)
     */
    function toggleFlightRoutes(show) {
        showFlightRoutes = show;

        // Toggle deck.gl arcs (simple A-to-B flights)
        updateFlightArcs();

        // Toggle complex plane line layers (3+ waypoints)
        const layers = map.getStyle().layers || [];
        layers.forEach(function (layer) {
            if (layer.id.startsWith('route-layer-')) {
                const tripId = layer.metadata?.tripId;
                const transportType = layer.metadata?.transportType;
                if (transportType === 'plane') {
                    const visible = show && visibleTripIds.has(tripId);
                    map.setLayoutProperty(layer.id, 'visibility', visible ? 'visible' : 'none');
                }
            }
        });
    }

    /**
     * Render legend
     */
    function renderLegend() {
        const legendItems = $('#legendItems');
        if (legendItems.length === 0) return;

        legendItems.empty();
        //permite que se pueda ocultar y mostrar el listado de leyendas.
        legendItems.prev().on('click', function () {
            legendItems.toggle(200);
        });

        const transportOrder = [
            { type: 'plane', icon: transportIcons.plane, label: __('map.transport_plane') },
            { type: 'car', icon: transportIcons.car, label: __('map.transport_car') },
            { type: 'bike', icon: transportIcons.bike, label: __('map.transport_bike') },
            { type: 'train', icon: transportIcons.train, label: __('map.transport_train') },
            { type: 'ship', icon: transportIcons.ship, label: __('map.transport_ship') },
            { type: 'walk', icon: transportIcons.walk, label: __('map.transport_walk') },
            { type: 'bus', icon: transportIcons.bus, label: __('map.transport_bus') },
            { type: 'aerial', icon: transportIcons.aerial, label: __('map.transport_aerial') }
        ];

        transportOrder.forEach(function (item) {
            const config = transportConfig[item.type];
            if (config) {
                legendItems.append(`
                    <div class="legend-item">
                        <div class="legend-line" style="background-color: ${config.color};"></div>
                        <small>${item.icon} ${item.label}</small>
                    </div>
                `);
            }
        });

        // Future trips indicator
        legendItems.append(`
            <div class="legend-item legend-future-separator">
                <div class="legend-line legend-future" style="background: repeating-linear-gradient(90deg, #6B6B6B, #6B6B6B 2px, transparent 2px, transparent 8px);"></div>
                <small>${__('map.upcoming_trip')}</small>
            </div>
        `);
    }

    // ==================== TRIPS PANEL ====================

    function groupTripsByTag(trips) {
        const grouped = {};
        trips.forEach(function (trip) {
            const tags = (appConfig?.tripTagsEnabled && trip.tags && trip.tags.length > 0)
                ? trip.tags
                : [null];
            tags.forEach(function (tag) {
                const key = tag !== null ? tag : '__no_tag__';
                if (!grouped[key]) grouped[key] = [];
                grouped[key].push(trip);
            });
        });
        return grouped;
    }

    function getSortedTagKeys(groupedTrips) {
        return Object.keys(groupedTrips).sort(function (a, b) {
            if (a === '__no_tag__') return 1;
            if (b === '__no_tag__') return -1;
            return a.localeCompare(b);
        });
    }

    function groupTripsByYear(trips) {
        const grouped = {};
        trips.forEach(function (trip) {
            let year;
            if (isFutureTrip(trip)) {
                year = 'future';
            } else if (trip.start_date) {
                year = new Date(trip.start_date + 'T00:00:00').getFullYear().toString();
            } else {
                year = 'Sin fecha';
            }
            if (!grouped[year]) grouped[year] = [];
            grouped[year].push(trip);
        });
        return grouped;
    }

    function getSortedYearKeys(groupedTrips) {
        return Object.keys(groupedTrips).sort(function (a, b) {
            if (a === 'future') return -1;
            if (b === 'future') return 1;
            if (a === 'Sin fecha') return 1;
            if (b === 'Sin fecha') return -1;
            return parseInt(b) - parseInt(a);
        });
    }

    function renderTripsPanel() {
        const $tripsList = $('#tripsList');
        $tripsList.empty();

        if (tripsData.length === 0) {
            $tripsList.html('<p class="text-muted small text-center">' + __('map.no_trips_available') + '</p>');
            return;
        }

        const prefs = loadPreferences();
        const groupBy = prefs.groupBy || 'year';

        const chevronDown = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>';
        const chevronRight = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>';

        let groupedTrips, sortedKeys;

        if (groupBy === 'tag') {
            groupedTrips = groupTripsByTag(tripsData);
            sortedKeys = getSortedTagKeys(groupedTrips);
        } else {
            groupedTrips = groupTripsByYear(tripsData);
            sortedKeys = getSortedYearKeys(groupedTrips);
        }

        const savedCollapsedStates = prefs.yearCollapsedStates || {};
        const currentYear = new Date().getFullYear().toString();

        sortedKeys.forEach(function (groupKey) {
            const trips = groupedTrips[groupKey];
            const groupId = 'year-' + groupKey.replace(/\s/g, '-').replace(/[^a-zA-Z0-9\-_]/g, '_');

            let groupLabel, groupClass, defaultExpanded;

            if (groupBy === 'tag') {
                const isNoTag = groupKey === '__no_tag__';
                groupLabel = isNoTag ? __('map.no_tag') : escapeHtml(groupKey);
                groupClass = isNoTag ? 'year-group year-no-tag' : 'year-group';
                defaultExpanded = true;
            } else {
                const isFutureGroup = groupKey === 'future';
                groupLabel = isFutureGroup ? __('map.upcoming_trips') : groupKey;
                groupClass = isFutureGroup ? 'year-group year-future' : 'year-group';
                defaultExpanded = isFutureGroup || groupKey === currentYear;
            }

            const isCollapsed = savedCollapsedStates[groupKey] !== undefined
                ? savedCollapsedStates[groupKey]
                : !defaultExpanded;

            let totalRoutes = 0, totalPoints = 0;
            trips.forEach(t => {
                totalRoutes += t.routes ? t.routes.length : 0;
                totalPoints += t.points ? t.points.length : 0;
            });

            const $yearGroup = $(`
                <div class="${groupClass}" data-year="${groupKey}">
                    <div class="year-header">
                        <div class="year-header-left">
                            <input class="form-check-input year-checkbox" type="checkbox" id="${groupId}-checkbox" data-year="${groupKey}" checked>
                            <button class="year-toggle-btn" type="button" data-target="${groupId}">
                                <span class="year-chevron">${isCollapsed ? chevronRight : chevronDown}</span>
                                <span class="year-label">${groupLabel}</span>
                                <span class="year-count badge">${trips.length}</span>
                            </button>
                        </div>
                        <div class="year-stats">
                            <span title="${__('map.routes')}">${statsIcons.routes} ${totalRoutes}</span>
                            <span title="${__('map.points')}">${statsIcons.points} ${totalPoints}</span>
                        </div>
                    </div>
                    <div class="year-trips ${isCollapsed ? 'collapsed' : ''}" id="${groupId}"></div>
                </div>
            `);

            const $yearTrips = $yearGroup.find('.year-trips');

            trips.forEach(function (trip) {
                const isFuture = isFutureTrip(trip);
                const itemClass = isFuture ? 'trip-filter-item trip-future' : 'trip-filter-item';
                const colorIndicator = isFuture ? '#6B6B6B' : trip.color;

                // Generar HTML de tags (solo en modo año, en modo tag se omiten para no repetir)
                let tagsHtml = '';
                if (groupBy === 'year' && appConfig?.tripTagsEnabled && trip.tags && trip.tags.length > 0) {
                    tagsHtml = '<div class="trip-tags mt-1 d-flex gap-1 flex-wrap">';
                    const MAX_TAGS = 4;
                    const visibleTags = trip.tags.slice(0, MAX_TAGS);
                    const hiddenTags = trip.tags.slice(MAX_TAGS);
                    visibleTags.forEach(tag => {
                        tagsHtml += `<span class="badge bg-light text-dark border" style="font-size: 0.65em;">${escapeHtml(tag)}</span>`;
                    });
                    if (hiddenTags.length > 0) {
                        const hiddenTagsText = escapeHtml(hiddenTags.join(', '));
                        tagsHtml += `<span class="badge bg-secondary border" style="font-size: 0.65em; cursor: help;" title="${hiddenTagsText}">+${hiddenTags.length}</span>`;
                    }
                    tagsHtml += '</div>';
                }

                const routesCount = trip.routes ? trip.routes.length : 0;
                const pointsCount = trip.points ? trip.points.length : 0;

                $yearTrips.append(`
                    <div class="${itemClass}">
                        <div class="form-check d-flex align-items-start gap-2">
                            <input class="form-check-input trip-checkbox flex-shrink-0 mt-1" type="checkbox" id="trip-${trip.id}" value="${trip.id}" data-year="${groupKey}" checked>
                            <div class="trip-color-dot mt-1" style="background-color: ${colorIndicator};"></div>
                            <label class="form-check-label flex-grow-1" for="trip-${trip.id}">
                                <span class="trip-title">${escapeHtml(trip.title)}${appConfig?.tripPageEnabled ? ` <a href="trip.php?id=${trip.id}" class="trip-page-link" title="${__('map.view_trip_details')}"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/></svg></a>` : ''}</span>
                                <span class="trip-details">
                                    ${formatDateRange(trip.start_date, trip.end_date)}
                                    <span class="trip-counts">
                                        <span title="${__('map.routes')}">${statsIcons.routes} ${routesCount}</span>
                                        <span title="${__('map.points')}">${statsIcons.points} ${pointsCount}</span>
                                    </span>
                                </span>
                                ${tagsHtml}
                            </label>
                        </div>
                    </div>
                `);
            });

            $tripsList.append($yearGroup);
        });

        // Event handlers
        $('.year-toggle-btn').on('click', function () {
            const targetId = $(this).data('target');
            const $target = $('#' + targetId);
            const $chevron = $(this).find('.year-chevron');
            const isCollapsing = !$target.hasClass('collapsed');

            $target.toggleClass('collapsed');
            $chevron.html(isCollapsing ? '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>');

            // Save state
            const groupKey = $(this).closest('.year-group').data('year');
            const prefs = loadPreferences();
            if (!prefs.yearCollapsedStates) prefs.yearCollapsedStates = {};
            prefs.yearCollapsedStates[groupKey] = isCollapsing;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        });

        $('.year-checkbox').on('change', function () {
            const groupKey = $(this).data('year');
            const isChecked = $(this).is(':checked');
            $(`.trip-checkbox[data-year="${groupKey}"]`).each(function () {
                $(this).prop('checked', isChecked);
                const tripId = parseInt($(this).val());
                if (isChecked) showTrip(tripId);
                else hideTrip(tripId);
            });
            savePreferences();
        });

        $('.trip-checkbox').on('change', function () {
            const tripId = parseInt($(this).val());
            const isChecked = $(this).is(':checked');
            if (isChecked) showTrip(tripId);
            else hideTrip(tripId);
            updateYearCheckboxState($(this).data('year'));
            savePreferences();
        });

        applyTripSelectionPreferences();
        sortedKeys.forEach(key => updateYearCheckboxState(key));
    }

    function updateYearCheckboxState(year) {
        const $yearCheckbox = $(`.year-checkbox[data-year="${year}"]`);
        const $tripCheckboxes = $(`.trip-checkbox[data-year="${year}"]`);
        const total = $tripCheckboxes.length;
        const checked = $tripCheckboxes.filter(':checked').length;

        $yearCheckbox.prop('checked', checked === total);
        $yearCheckbox.prop('indeterminate', checked > 0 && checked < total);
    }

    function applyTripSelectionPreferences() {
        const urlParams = getURLParams();
        const prefs = loadPreferences();

        // If URL has trip parameters, use those instead of saved preferences
        if (urlParams.trips && urlParams.trips.length > 0) {
            $('.trip-checkbox').each(function () {
                const tripId = parseInt($(this).val());
                const shouldBeChecked = urlParams.trips.includes(tripId);

                $(this).prop('checked', shouldBeChecked);
                if (shouldBeChecked) showTrip(tripId);
                else hideTrip(tripId);
            });
            return;
        }

        // Otherwise, use saved preferences
        if (prefs.selectedTrips === null) return;

        const knownTripIds = prefs.knownTripIds || [];

        $('.trip-checkbox').each(function () {
            const tripId = parseInt($(this).val());
            const isNewTrip = knownTripIds.length > 0 && !knownTripIds.includes(tripId);
            const shouldBeChecked = isNewTrip || prefs.selectedTrips.includes(tripId);

            $(this).prop('checked', shouldBeChecked);
            if (shouldBeChecked) showTrip(tripId);
            else hideTrip(tripId);
        });
    }

    function applyInitialToggleStates() {
        const urlParams = getURLParams();
        const prefs = loadPreferences();

        // Determine which values to use: URL params override preferences
        const showFlightRoutes = urlParams.hasOwnProperty('flights') ? urlParams.flights : prefs.showFlightRoutes;
        const showPoints = urlParams.hasOwnProperty('points') ? urlParams.points : prefs.showPoints;
        const showRoutes = urlParams.hasOwnProperty('routes') ? urlParams.routes : prefs.showRoutes;

        // Update checkbox states to reflect what's being shown
        $('#toggleFlightRoutes').prop('checked', showFlightRoutes);
        $('#togglePoints').prop('checked', showPoints);
        $('#toggleRoutes').prop('checked', showRoutes);

        // Apply the states
        if (showFlightRoutes) {
            updateFlightArcs();
        }

        if (!showPoints) {
            clearClusterMarkers();
        }

        if (!showRoutes) {
            toggleRoutes(false);
        }
    }

    // ==================== UTILITIES ====================

    function formatDateRange(startDate, endDate) {
        if (!startDate && !endDate) return 'Sin fechas';
        const start = startDate ? formatDate(startDate) : '';
        const end = endDate ? formatDate(endDate) : '';
        if (start && end) return `${start} - ${end}`;
        return start || end;
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        // Handle both date-only (YYYY-MM-DD) and datetime (YYYY-MM-DD HH:mm:ss) formats
        let isoString = dateString;
        if (dateString.includes(' ')) {
            // DATETIME format: replace space with T
            isoString = dateString.replace(' ', 'T');
        } else if (!dateString.includes('T')) {
            // Date-only format: append T00:00:00
            isoString = dateString + 'T00:00:00';
        }
        const date = new Date(isoString);
        return date.toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
    }

    function showError(message) {
        $('#tripsList').html(`<div class="alert alert-warning">${message}</div>`);
    }

    /**
     * Generate shareable URL with current map state
     */
    function generateShareableLink() {
        const params = new URLSearchParams();

        // Get current map center and zoom
        const center = map.getCenter();
        const zoom = map.getZoom();

        // Add center (lat,lng)
        params.set('center', `${center.lat.toFixed(6)},${center.lng.toFixed(6)}`);

        // Add zoom
        params.set('zoom', Math.round(zoom).toString());

        // Add selected trips
        const selectedTrips = [];
        $('.trip-checkbox:checked').each(function () {
            selectedTrips.push($(this).val());
        });
        if (selectedTrips.length > 0) {
            params.set('trips', selectedTrips.join(','));
        }

        // Add toggle states
        if ($('#toggleRoutes').is(':checked')) {
            params.set('routes', '1');
        } else {
            params.set('routes', '0');
        }

        if ($('#togglePoints').is(':checked')) {
            params.set('points', '1');
        } else {
            params.set('points', '0');
        }

        if ($('#toggleFlightRoutes').is(':checked')) {
            params.set('flights', '1');
        } else {
            params.set('flights', '0');
        }

        // Generate full URL
        const baseUrl = window.location.origin + window.location.pathname;
        return `${baseUrl}?${params.toString()}`;
    }

    /**
     * Copy shareable link to clipboard
     */
    function shareMapLink() {
        const url = generateShareableLink();

        // Try to use the modern Clipboard API
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
                showShareSuccess();
            }).catch(function (err) {
                // Fallback to old method
                fallbackCopyToClipboard(url);
            });
        } else {
            // Fallback for older browsers
            fallbackCopyToClipboard(url);
        }
    }

    /**
     * Fallback method to copy text to clipboard
     */
    function fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showShareSuccess();
            } else {
                showShareError();
            }
        } catch (err) {
            showShareError();
        }

        document.body.removeChild(textArea);
    }

    /**
     * Show success message when link is copied
     */
    function showShareSuccess() {
        const btn = $('#shareMapBtn');
        const originalHtml = btn.html();

        btn.html(`
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-1">
                <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425z"/>
            </svg>
            ${__('map.link_copied')}
        `);
        btn.addClass('btn-success').removeClass('btn-outline-primary');

        setTimeout(function () {
            btn.html(originalHtml);
            btn.removeClass('btn-success').addClass('btn-outline-primary');
        }, 2000);
    }

    /**
     * Show error message when copy fails
     */
    function showShareError() {
        alert(__('map.copy_failed'));
    }
    // ==================== SEARCH ====================

    function searchPublicPlace(query) {
        if (!query || query.trim().length < 3) {
            alert(__('map.search_min_chars'));
            return;
        }

        const searchResults = $('#publicSearchResults');
        searchResults.html('<div class="list-group-item small"><div class="spinner-border spinner-border-sm me-2"></div>' + __('map.searching') + '</div>');
        searchResults.show();

        $.ajax({
            url: `${BASE_URL}/api/geocode.php?q=${encodeURIComponent(query)}&limit=5`,
            method: 'GET',
            dataType: 'json',
            success: function (results) {
                searchResults.empty();
                if (!results || results.length === 0) {
                    searchResults.html('<div class="list-group-item small text-muted">' + __('map.no_results') + '</div>');
                    return;
                }
                if (results.error) {
                    searchResults.html(`<div class="list-group-item small text-danger">${results.error}</div>`);
                    return;
                }
                results.forEach(function (place) {
                    const item = $(`<button type="button" class="list-group-item list-group-item-action small" data-lat="${place.lat}" data-lon="${place.lon}">
                        <strong>${place.name || place.type}</strong><br>
                        <span class="text-muted" style="font-size: 0.85em;">${place.display_name}</span>
                    </button>`);
                    item.on('click', function () {
                        map.flyTo({ center: [parseFloat(place.lon), parseFloat(place.lat)], zoom: 12 });
                        searchResults.hide();
                        $('#publicPlaceSearch').val('');
                    });
                    searchResults.append(item);
                });
            },
            error: function () {
                searchResults.html('<div class="list-group-item small text-danger">Error al buscar</div>');
            }
        });
    }

    // ==================== EVENT HANDLERS ====================

    function setupEventHandlers() {
        $('#publicSearchBtn').on('click', () => searchPublicPlace($('#publicPlaceSearch').val()));
        $('#publicPlaceSearch').on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                searchPublicPlace($(this).val());
            }
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#publicPlaceSearch, #publicSearchResults, #publicSearchBtn').length) {
                $('#publicSearchResults').hide();
            }
        });

        $('#toggleRoutes').on('change', function () {
            toggleRoutes($(this).is(':checked'));
            savePreferences();
        });

        $('#toggleFlightRoutes').on('change', function () {
            toggleFlightRoutes($(this).is(':checked'));
            savePreferences();
        });

        $('#togglePoints').on('change', function () {
            togglePoints($(this).is(':checked'));
            savePreferences();
        });

        $('#filterAll').on('click', function () {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            $('.trip-checkbox').prop('checked', true).trigger('change');
        });

        $('#filterPast').on('click', function () {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            tripsData.forEach(trip => {
                $('#trip-' + trip.id).prop('checked', !isFutureTrip(trip)).trigger('change');
            });
        });

        $('#filterNone').on('click', function () {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            $('.trip-checkbox').prop('checked', false).trigger('change');
        });

        // Share map link button
        $('#shareMapBtn').on('click', function () {
            shareMapLink();
        });

        // Group by selector — restore saved state before binding listener
        const savedGroupBy = (loadPreferences().groupBy) || 'year';
        $('input[name="groupBy"][value="' + savedGroupBy + '"]').prop('checked', true);

        $('input[name="groupBy"]').on('change', function () {
            saveGroupByPreference($(this).val());
            renderTripsPanel();
        });

    }

    // ==================== LIGHTBOX ====================

    window.openLightbox = function (imageUrl, altText) {
        const lightbox = document.getElementById('imageLightbox');
        const lightboxImage = document.getElementById('lightboxImage');
        if (lightbox && lightboxImage) {
            lightboxImage.src = imageUrl;
            lightboxImage.alt = altText || '';
            lightbox.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeLightbox = function () {
        const lightbox = document.getElementById('imageLightbox');
        if (lightbox) {
            lightbox.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeLightbox();
    });

    // ==================== PERFORMANCE MODE ====================

    /**
     * Detect if we should enable performance mode
     * Targets Windows + AMD which has poor WebGL performance
     */
    function detectPerformanceMode() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (!gl) return false;

            const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
            if (!debugInfo) return false;

            const renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL).toLowerCase();
            const isAMD = renderer.includes('amd') || renderer.includes('radeon') || renderer.includes('ati');
            const isWindows = navigator.platform.toLowerCase().includes('win');

            // Also enable for Intel integrated graphics on Windows
            const isIntel = renderer.includes('intel');

            if ((isAMD || isIntel) && isWindows) {
                console.log('Performance mode enabled for:', renderer);
                return true;
            }

            return false;
        } catch (e) {
            return false;
        }
    }

    /**
     * Apply performance mode if needed
     */
    function applyPerformanceMode() {
        const prefs = loadPreferences();
        // Check if user explicitly set performance mode, or auto-detect
        const shouldEnable = prefs.performanceMode === true ||
            (prefs.performanceMode === undefined && detectPerformanceMode());

        if (shouldEnable) {
            document.body.classList.add('performance-mode');
            console.log('Performance mode: enabled (CSS blur effects disabled)');
        }
    }

    // ==================== INITIALIZATION ====================

    $(document).ready(function () {
        applyPerformanceMode();
        applyPreferencesToControls();
        loadConfig().always(function () {
            initMap();
            setupEventHandlers();
        });
    });

})();
