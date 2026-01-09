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

    // Map style URLs (all free, no API key needed)
    const MAP_STYLES = {
        'positron': 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json',
        'voyager': 'https://basemaps.cartocdn.com/gl/voyager-gl-style/style.json',
        'dark-matter': 'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json',
        'osm-liberty': 'https://tiles.openfreemap.org/styles/liberty'
    };

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

    // SVG icons for transport types
    const transportIcons = {
        plane: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/></svg>',
        car: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 15.4222V18.5C22 18.9659 22 19.1989 21.9239 19.3827C21.8224 19.6277 21.6277 19.8224 21.3827 19.9239C21.1989 20 20.9659 20 20.5 20C20.0341 20 19.8011 20 19.6173 19.9239C19.3723 19.8224 19.1776 19.6277 19.0761 19.3827C19 19.1989 19 18.9659 19 18.5C19 18.0341 19 17.8011 18.9239 17.6173C18.8224 17.3723 18.6277 17.1776 18.3827 17.0761C18.1989 17 17.9659 17 17.5 17H6.5C6.03406 17 5.80109 17 5.61732 17.0761C5.37229 17.1776 5.17761 17.3723 5.07612 17.6173C5 17.8011 5 18.0341 5 18.5C5 18.9659 5 19.1989 4.92388 19.3827C4.82239 19.6277 4.62771 19.8224 4.38268 19.9239C4.19891 20 3.96594 20 3.5 20C3.03406 20 2.80109 20 2.61732 19.9239C2.37229 19.8224 2.17761 19.6277 2.07612 19.3827C2 19.1989 2 18.9659 2 18.5V15.4222C2 14.22 2 13.6188 2.17163 13.052C2.34326 12.4851 2.67671 11.9849 3.3436 10.9846L4 10L4.96154 7.69231C5.70726 5.90257 6.08013 5.0077 6.8359 4.50385C7.59167 4 8.56112 4 10.5 4H13.5C15.4389 4 16.4083 4 17.1641 4.50385C17.9199 5.0077 18.2927 5.90257 19.0385 7.69231L20 10L20.6564 10.9846C21.3233 11.9849 21.6567 12.4851 21.8284 13.052C22 13.6188 22 14.22 22 15.4222Z"/><path d="M2 8.5L4 10L5.76114 10.4403C5.91978 10.4799 6.08269 10.5 6.24621 10.5H17.7538C17.9173 10.5 18.0802 10.4799 18.2389 10.4403L20 10L22 8.5"/><path d="M18 14V14.01"/><path d="M6 14V14.01"/></svg>',
        train: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 3H6.73259C9.34372 3 10.6493 3 11.8679 3.40119C13.0866 3.80239 14.1368 4.57795 16.2373 6.12907L19.9289 8.85517C19.9692 8.88495 19.9894 8.89984 20.0084 8.91416C21.2491 9.84877 21.985 11.307 21.9998 12.8603C22 12.8841 22 12.9091 22 12.9593C22 12.9971 22 13.016 21.9997 13.032C21.9825 14.1115 21.1115 14.9825 20.032 14.9997C20.016 15 19.9971 15 19.9593 15H2"/><path d="M2 11H6.095C8.68885 11 9.98577 11 11.1857 11.451C12.3856 11.9019 13.3983 12.77 15.4238 14.5061L16 15"/><path d="M10 7H17"/><path d="M2 19H22"/><path d="M18 19V21"/><path d="M12 19V21"/><path d="M6 19V21"/></svg>',
        ship: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 21.1932C2.68524 22.2443 3.57104 22.2443 4.27299 21.1932C6.52985 17.7408 8.67954 23.6764 10.273 21.2321C12.703 17.5694 14.4508 23.9218 16.273 21.1932C18.6492 17.5582 20.1295 23.5776 22 21.5842"/><path d="M3.57228 17L2.07481 12.6457C1.80373 11.8574 2.30283 11 3.03273 11H20.8582C23.9522 11 19.9943 17 17.9966 17"/><path d="M18 11L15.201 7.50122C14.4419 6.55236 13.2926 6 12.0775 6H8C6.89543 6 6 6.89543 6 8V11"/><path d="M10 6V3C10 2.44772 9.55228 2 9 2H8"/></svg>',
        walk: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12.5L7.73811 9.89287C7.91034 9.63452 8.14035 9.41983 8.40993 9.26578L10.599 8.01487C11.1619 7.69323 11.8483 7.67417 12.4282 7.9641C13.0851 8.29255 13.4658 8.98636 13.7461 9.66522C14.2069 10.7814 15.3984 12 18 12"/><path d="M13 9L11.7772 14.5951M10.5 8.5L9.77457 11.7645C9.6069 12.519 9.88897 13.3025 10.4991 13.777L14 16.5L15.5 21"/><path d="M9.5 16L9 17.5L6.5 20.5"/><path d="M15 4.5C15 5.32843 14.3284 6 13.5 6C12.6716 6 12 5.32843 12 4.5C12 3.67157 12.6716 3 13.5 3C14.3284 3 15 3.67157 15 4.5Z"/></svg>'
    };

    // Transport config (colors will be updated from server)
    let transportConfig = {
        'plane': { color: '#FF4444', icon: transportIcons.plane, dashArray: [10, 5] },
        'ship': { color: '#00AAAA', icon: transportIcons.ship, dashArray: [10, 5] },
        'car': { color: '#4444FF', icon: transportIcons.car, dashArray: null },
        'train': { color: '#FF8800', icon: transportIcons.train, dashArray: null },
        'walk': { color: '#44FF44', icon: transportIcons.walk, dashArray: null }
    };

    // SVG icons for point types (using currentColor for dynamic coloring via CSS)
    const pointTypeIcons = {
        stay: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4V20C3 20.9428 3 21.4142 3.29289 21.7071C3.58579 22 4.05719 22 5 22H19C19.9428 22 20.4142 22 20.7071 21.7071C21 21.4142 21 20.9428 21 20V4"/><path d="M10.5 8V9.5M10.5 11V9.5M13.5 8V9.5M13.5 11V9.5M10.5 9.5H13.5"/><path d="M14 22L14 17.9999C14 16.8954 13.1046 15.9999 12 15.9999C10.8954 15.9999 10 16.8954 10 17.9999V22"/><path d="M2 4H8C8.6399 2.82727 10.1897 2 12 2C13.8103 2 15.3601 2.82727 16 4H22"/><path d="M6 8H7M6 12H7M6 16H7"/><path d="M17 8H18M17 12H18M17 16H18"/></svg>',
        visit: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="none"><path d="M8.31253 4.7812L7.6885 4.36517V4.36517L8.31253 4.7812ZM7.5 6V6.75C7.75076 6.75 7.98494 6.62467 8.12404 6.41603L7.5 6ZM2.17224 8.83886L1.45453 8.62115L2.17224 8.83886ZM4.83886 6.17224L4.62115 5.45453H4.62115L4.83886 6.17224ZM3.46243 20.092L3.93822 19.5123L3.93822 19.5123L3.46243 20.092ZM2.90796 19.5376L3.48772 19.0618L3.48772 19.0618L2.90796 19.5376ZM21.092 19.5376L20.5123 19.0618L20.5123 19.0618L21.092 19.5376ZM20.5376 20.092L20.0618 19.5123L20.0618 19.5123L20.5376 20.092ZM14.0195 3.89791C14.3847 4.09336 14.8392 3.95575 15.0346 3.59054C15.2301 3.22534 15.0924 2.77084 14.7272 2.57539L14.0195 3.89791ZM22.5455 8.62115C22.4252 8.22477 22.0064 8.00092 21.61 8.12116C21.2137 8.2414 20.9898 8.6602 21.1101 9.05658L22.5455 8.62115ZM21.25 11.5V13.5H22.75V11.5H21.25ZM14.5 20.25H9.5V21.75H14.5V20.25ZM2.75 13.5V11.5H1.25V13.5H2.75ZM12.3593 2.25H11.6407V3.75H12.3593V2.25ZM7.6885 4.36517L6.87596 5.58397L8.12404 6.41603L8.93657 5.19722L7.6885 4.36517ZM11.6407 2.25C11.1305 2.25 10.6969 2.24925 10.3369 2.28282C9.96142 2.31783 9.61234 2.39366 9.27276 2.57539L9.98055 3.89791C10.0831 3.84299 10.2171 3.80049 10.4762 3.77634C10.7506 3.75075 11.1031 3.75 11.6407 3.75V2.25ZM8.93657 5.19722C9.23482 4.74985 9.43093 4.45704 9.60448 4.24286C9.76825 4.04074 9.87794 3.95282 9.98055 3.89791L9.27276 2.57539C8.93318 2.75713 8.67645 3.00553 8.43904 3.29853C8.2114 3.57947 7.97154 3.94062 7.6885 4.36517L8.93657 5.19722ZM2.75 11.5C2.75 10.0499 2.75814 9.49107 2.88994 9.05657L1.45453 8.62115C1.24186 9.32224 1.25 10.159 1.25 11.5H2.75ZM7.5 5.25C6.159 5.25 5.32224 5.24186 4.62115 5.45453L5.05657 6.88994C5.49107 6.75814 6.04987 6.75 7.5 6.75V5.25ZM2.88994 9.05657C3.20503 8.01787 4.01787 7.20503 5.05657 6.88994L4.62115 5.45453C3.10304 5.91505 1.91505 7.10304 1.45453 8.62115L2.88994 9.05657ZM9.5 20.25C7.83789 20.25 6.65724 20.2488 5.75133 20.1417C4.86197 20.0366 4.33563 19.8384 3.93822 19.5123L2.98663 20.6718C3.69558 21.2536 4.54428 21.5095 5.57525 21.6313C6.58966 21.7512 7.87463 21.75 9.5 21.75V20.25ZM1.25 13.5C1.25 15.1254 1.24877 16.4103 1.36868 17.4248C1.49054 18.4557 1.74638 19.3044 2.3282 20.0134L3.48772 19.0618C3.16158 18.6644 2.96343 18.138 2.85831 17.2487C2.75123 16.3428 2.75 15.1621 2.75 13.5H1.25ZM3.93822 19.5123C3.77366 19.3772 3.62277 19.2263 3.48772 19.0618L2.3282 20.0134C2.52558 20.2539 2.74612 20.4744 2.98663 20.6718L3.93822 19.5123ZM21.25 13.5C21.25 15.1621 21.2488 16.3428 21.1417 17.2487C21.0366 18.138 20.8384 18.6644 20.5123 19.0618L21.6718 20.0134C22.2536 19.3044 22.5095 18.4557 22.6313 17.4248C22.7512 16.4103 22.75 15.1254 22.75 13.5H21.25ZM14.5 21.75C16.1254 21.75 17.4103 21.7512 18.4248 21.6313C19.4557 21.5095 20.3044 21.2536 21.0134 20.6718L20.0618 19.5123C19.6644 19.8384 19.138 20.0366 18.2487 20.1417C17.3428 20.2488 16.1621 20.25 14.5 20.25V21.75ZM20.5123 19.0618C20.3772 19.2263 20.2263 19.3772 20.0618 19.5123L21.0134 20.6718C21.2539 20.4744 21.4744 20.2539 21.6718 20.0134L20.5123 19.0618ZM12.3593 3.75C12.8969 3.75 13.2494 3.75075 13.5238 3.77634C13.7829 3.80049 13.9169 3.84299 14.0195 3.89791L14.7272 2.57539C14.3877 2.39366 14.0386 2.31783 13.6631 2.28282C13.3031 2.24925 12.8695 2.25 12.3593 2.25V3.75ZM22.75 11.5C22.75 10.159 22.7581 9.32224 22.5455 8.62115L21.1101 9.05658C21.2419 9.49107 21.25 10.0499 21.25 11.5H22.75Z"/><path d="M16 13C16 15.2091 14.2091 17 12 17C9.79086 17 8 15.2091 8 13C8 10.7909 9.79086 9 12 9C14.2091 9 16 10.7909 16 13Z" stroke="currentColor" stroke-width="1.25" fill="none"/><path d="M17.9737 3.02148C17.9795 2.99284 18.0205 2.99284 18.0263 3.02148C18.3302 4.50808 19.4919 5.66984 20.9785 5.97368C21.0072 5.97954 21.0072 6.02046 20.9785 6.02632C19.4919 6.33016 18.3302 7.49192 18.0263 8.97852C18.0205 9.00716 17.9795 9.00716 17.9737 8.97852C17.6698 7.49192 16.5081 6.33016 15.0215 6.02632C14.9928 6.02046 14.9928 5.97954 15.0215 5.97368C16.5081 5.66984 17.6698 4.50808 17.9737 3.02148Z"/></svg>',
        food: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"><path d="M21 17C18.2386 17 16 14.7614 16 12C16 9.23858 18.2386 7 21 7"/><path d="M21 21C16.0294 21 12 16.9706 12 12C12 7.02944 16.0294 3 21 3"/><path d="M6 3L6 8M6 21L6 11"/><path d="M3.5 8H8.5"/><path d="M9 3L9 7.35224C9 12.216 3 12.2159 3 7.35207L3 3"/></svg>'
    };

    // SVG icons for stats
    const statsIcons = {
        routes: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="19" r="3"/><path d="M12 5H8.5C6.567 5 5 6.567 5 8.5C5 10.433 6.567 12 8.5 12H15.5C17.433 12 19 13.567 19 15.5C19 17.433 17.433 19 15.5 19H12"/></svg>',
        points: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"><path d="M7 18C5.17107 18.4117 4 19.0443 4 19.7537C4 20.9943 7.58172 22 12 22C16.4183 22 20 20.9943 20 19.7537C20 19.0443 18.8289 18.4117 17 18"/><path d="M14.5 9C14.5 10.3807 13.3807 11.5 12 11.5C10.6193 11.5 9.5 10.3807 9.5 9C9.5 7.61929 10.6193 6.5 12 6.5C13.3807 6.5 14.5 7.61929 14.5 9Z"/><path d="M13.2574 17.4936C12.9201 17.8184 12.4693 18 12.0002 18C11.531 18 11.0802 17.8184 10.7429 17.4936C7.6543 14.5008 3.51519 11.1575 5.53371 6.30373C6.6251 3.67932 9.24494 2 12.0002 2C14.7554 2 17.3752 3.67933 18.4666 6.30373C20.4826 11.1514 16.3536 14.5111 13.2574 17.4936Z"/></svg>'
    };

    // Point type config
    const pointTypeConfig = {
        'stay': {
            icon: pointTypeIcons.stay,
            labelKey: 'map.point_type_stay',
            label: __('map.point_type_stay'),
            color: '#FF6B6B'
        },
        'visit': {
            icon: pointTypeIcons.visit,
            labelKey: 'map.point_type_visit',
            label: __('map.point_type_visit'),
            color: '#4ECDC4',
            darkText: true
        },
        'food': {
            icon: pointTypeIcons.food,
            labelKey: 'map.point_type_food',
            label: __('map.point_type_food'),
            color: '#FFE66D',
            darkText: true
        }
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
                yearCollapsedStates: existingPrefs.yearCollapsedStates // Preserve collapsed states
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        } catch (e) {
            console.warn('Error saving preferences to localStorage:', e);
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
                    transportConfig.train.color = appConfig.transportColors.train || transportConfig.train.color;
                    transportConfig.walk.color = appConfig.transportColors.walk || transportConfig.walk.color;
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

        const unit = appConfig?.map?.distanceUnit || 'km';
        let value, label;

        if (transportType === 'plane') {
            value = meters / 1609.344;
            label = 'mi';
        } else if (transportType === 'ship') {
            value = meters / 1852;
            label = 'nm';
        } else {
            if (unit === 'mi') {
                value = meters / 1609.344;
                label = 'mi';
            } else {
                value = meters / 1000;
                label = 'km';
            }
        }

        const roundTripIcon = isRoundTrip ? ` <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ms-1 text-warning" style="vertical-align: text-bottom;" title="${__('routes.is_round_trip') || 'Ida y Vuelta'}"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></svg>` : '';
        return ` · ${Math.round(value).toLocaleString()} ${label}${roundTripIcon}`;
    }

    /**
     * Initialize the MapLibre GL map
     */
    function initMap() {
        // Get configured map style or default to voyager
        const mapStyleKey = appConfig?.map?.style || 'voyager';
        const mapStyleUrl = MAP_STYLES[mapStyleKey] || MAP_STYLES['voyager'];

        // Create MapLibre GL map with performance optimizations
        map = new maplibregl.Map({
            container: 'map',
            style: mapStyleUrl,
            center: [0, 20],
            zoom: 2,
            minZoom: 1,
            maxZoom: 18,
            attributionControl: true,
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
     * Render a route on the map
     */
    function renderRoute(route, trip) {
        if (!route.geojson || !route.geojson.geometry) {
            return;
        }

        const transportType = route.transport_type || 'car';

        // Skip simple plane routes (A-to-B) - they're handled by deck.gl arcs
        // Complex aerial routes with 3+ waypoints are rendered as lines
        const isPlaneRoute = transportType === 'plane';
        if (isPlaneRoute) {
            const coords = route.geojson.geometry?.coordinates || [];
            if (coords.length <= 2) {
                return; // Simple flight - handled by deck.gl arcs
            }
            // Complex aerial route - continue to render as line
        }

        const config = transportConfig[transportType] || transportConfig['car'];
        const isFuture = isFutureTrip(trip);
        const color = isFuture ? '#6B6B6B' : config.color;
        const sourceId = `route-${trip.id}-${route.id}`;
        const layerId = `route-layer-${trip.id}-${route.id}`;

        // Add source
        if (!map.getSource(sourceId)) {
            map.addSource(sourceId, {
                type: 'geojson',
                data: route.geojson
            });
            routeSourcesAdded.add(sourceId);
        }

        // Add layer
        if (!map.getLayer(layerId)) {
            // Plane routes are controlled by showFlightRoutes, others by showRoutes
            const initialVisibility = isPlaneRoute ? showFlightRoutes : showRoutes;

            const layerConfig = {
                id: layerId,
                type: 'line',
                source: sourceId,
                layout: {
                    'line-join': 'round',
                    'line-cap': 'round',
                    'visibility': initialVisibility ? 'visible' : 'none'
                },
                paint: {
                    'line-color': color,
                    'line-width': isFuture ? 3 : 4,
                    'line-opacity': 0.7
                },
                metadata: {
                    tripId: trip.id,
                    transportType: transportType
                }
            };

            // Add dash pattern if needed
            if (isFuture || config.dashArray) {
                layerConfig.paint['line-dasharray'] = isFuture ? [2, 6] : config.dashArray;
            }

            map.addLayer(layerConfig);

            // Add click handler for popup
            map.on('click', layerId, function (e) {
                e.preventDefault();
                const futureLabel = isFuture ? ` <span class="badge bg-secondary">Próximo</span>` : '';

                // Generar HTML de tags
                let tagsHtml = '';
                if (appConfig?.tripTagsEnabled && trip.tags && trip.tags.length > 0) {
                    tagsHtml = '<div class="mt-1 d-flex gap-1 flex-wrap">';
                    trip.tags.forEach(tag => {
                        tagsHtml += `<span class="badge bg-light text-dark border" style="font-size: 0.7em;">${escapeHtml(tag)}</span>`;
                    });
                    tagsHtml += '</div>';
                }

                popup.setLngLat(e.lngLat)
                    .setHTML(`
                        <div class="route-popup">
                            <strong>${config.icon} ${escapeHtml(trip.title)}</strong>${futureLabel}
                            ${tagsHtml}
                            <br>
                            <small class="text-muted">${__('map.transport')}: ${__('map.transport_' + transportType)}${formatDistance(route.distance_meters, transportType, route.is_round_trip)}</small>
                        </div>
                    `)
                    .addTo(map);
            });

            // Change cursor on hover
            map.on('mouseenter', layerId, function () {
                document.getElementById('map').style.cursor = 'pointer';
            });
            map.on('mouseleave', layerId, function () {
                document.getElementById('map').style.cursor = 'default';
            });
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
                                isFuture: isFuture,
                                distanceMeters: route.distance_meters,
                                isRoundTrip: route.is_round_trip,
                                color: isFuture ? [107, 107, 107, 150] : hexToRgba(transportConfig.plane.color, 180)
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

                    popup.setLngLat(info.coordinate)
                        .setHTML(`
                            <div class="route-popup">
                                <strong>${transportIcons.plane} ${escapeHtml(d.tripTitle)}</strong>${futureLabel}
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
                const el = document.createElement('div');
                el.className = 'marker-cluster-custom';
                el.innerHTML = `<span>${count}</span>`;
                el.style.cssText = `
                    background: #1e293b;
                    border: 2px solid white;
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
                    font-weight: 600;
                    color: white;
                    font-size: 13px;
                    cursor: pointer;
                `;

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
                const typeConfig = pointTypeConfig[point.type] || pointTypeConfig['visit'];
                const iconColor = typeConfig.darkText ? 'color: #000; stroke: #000;' : '';

                const el = document.createElement('div');
                el.className = 'custom-point-marker';
                el.innerHTML = `
                    <div class="point-marker-inner point-type-${point.type}" style="background-color: ${typeConfig.color}; border-color: ${point.tripColor};">
                        <span class="point-icon" style="${iconColor}">${typeConfig.icon}</span>
                    </div>
                `;

                const marker = new maplibregl.Marker({ element: el, anchor: 'bottom' })
                    .setLngLat(coords)
                    .addTo(map);

                // Click to show popup
                el.addEventListener('click', function (e) {
                    e.stopPropagation(); // Prevent map click from closing popup
                    const popupContent = createPointPopup(point, typeConfig);
                    popup.setLngLat(coords)
                        .setHTML(popupContent)
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
     * Create popup HTML for a point
     */
    function createPointPopup(point, typeConfig) {
        let html = '<div class="point-popup">';

        if (point.image_url) {
            const displayImage = point.thumbnail_url || point.image_url;
            html += `<img src="${displayImage}" alt="${escapeHtml(point.title)}" class="popup-image" onclick="openLightbox('${point.image_url}', '${escapeHtml(point.title)}')" title="${__('map.click_to_view_full')}">`;
        }

        html += '<div class="popup-content">';
        html += `<h6 class="popup-title">${escapeHtml(point.title)}</h6>`;

        const typeLabel = typeConfig.labelKey ? __(typeConfig.labelKey) : typeConfig.label;
        const textColor = typeConfig.darkText ? 'color: #000; --bs-badge-color: #000;' : '';
        html += `<span class="badge mb-2 d-inline-flex align-items-center gap-1" style="background-color: ${typeConfig.color}; ${textColor}">${typeConfig.icon} ${typeLabel}</span>`;

        html += `<p class="popup-trip mb-1"><span style="color: ${point.tripColor}; font-weight: bold;">${escapeHtml(point.tripTitle)}</span></p>`;

        // Tags del viaje
        if (appConfig?.tripTagsEnabled && point.tripTags && point.tripTags.length > 0) {
            html += '<div class="mb-2 d-flex gap-1 flex-wrap">';
            point.tripTags.forEach(tag => {
                html += `<span class="badge bg-light text-dark border" style="font-size: 0.65em;">${escapeHtml(tag)}</span>`;
            });
            html += '</div>';
        }

        if (point.visit_date) {
            html += `<p class="popup-date mb-1">${formatDate(point.visit_date)}</p>`;
        }

        if (point.description) {
            html += `<p class="popup-description">${escapeHtml(point.description)}</p>`;
        }

        // Use original coordinates if available (in case of offset for co-located points)
        const displayLat = point.originalLat !== undefined ? point.originalLat : point.latitude;
        const displayLon = point.originalLon !== undefined ? point.originalLon : point.longitude;
        html += `<p class="popup-coords">${displayLat.toFixed(6)}, ${displayLon.toFixed(6)}</p>`;
        html += '</div></div>';

        return html;
    }

    /**
     * Check if trip is in the future
     */
    function isFutureTrip(trip) {
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

        const transportOrder = [
            { type: 'plane', icon: transportIcons.plane, label: __('map.transport_plane') },
            { type: 'car', icon: transportIcons.car, label: __('map.transport_car') },
            { type: 'train', icon: transportIcons.train, label: __('map.transport_train') },
            { type: 'ship', icon: transportIcons.ship, label: __('map.transport_ship') },
            { type: 'walk', icon: transportIcons.walk, label: __('map.transport_walk') }
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

        const groupedTrips = groupTripsByYear(tripsData);
        const sortedYears = getSortedYearKeys(groupedTrips);
        const prefs = loadPreferences();
        const savedCollapsedStates = prefs.yearCollapsedStates || {};
        const currentYear = new Date().getFullYear().toString();

        sortedYears.forEach(function (year) {
            const trips = groupedTrips[year];
            const yearId = 'year-' + year.replace(/\s/g, '-');
            const isFutureGroup = year === 'future';
            const yearLabel = isFutureGroup ? __('map.upcoming_trips') : year;

            // Default: expand future and current year
            const defaultExpanded = isFutureGroup || year === currentYear;
            const isCollapsed = savedCollapsedStates[year] !== undefined ? savedCollapsedStates[year] : !defaultExpanded;

            const chevronDown = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>';
            const chevronRight = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>';

            let totalRoutes = 0, totalPoints = 0;
            trips.forEach(t => {
                totalRoutes += t.routes ? t.routes.length : 0;
                totalPoints += t.points ? t.points.length : 0;
            });

            const yearClass = isFutureGroup ? 'year-group year-future' : 'year-group';

            const $yearGroup = $(`
                <div class="${yearClass}" data-year="${year}">
                    <div class="year-header">
                        <div class="year-header-left">
                            <input class="form-check-input year-checkbox" type="checkbox" id="${yearId}-checkbox" data-year="${year}" checked>
                            <button class="year-toggle-btn" type="button" data-target="${yearId}">
                                <span class="year-chevron">${isCollapsed ? chevronRight : chevronDown}</span>
                                <span class="year-label">${yearLabel}</span>
                                <span class="year-count badge">${trips.length}</span>
                            </button>
                        </div>
                        <div class="year-stats">
                            <span title="${__('map.routes')}">${statsIcons.routes} ${totalRoutes}</span>
                            <span title="${__('map.points')}">${statsIcons.points} ${totalPoints}</span>
                        </div>
                    </div>
                    <div class="year-trips ${isCollapsed ? 'collapsed' : ''}" id="${yearId}"></div>
                </div>
            `);

            const $yearTrips = $yearGroup.find('.year-trips');

            trips.forEach(function (trip) {
                const isFuture = isFutureTrip(trip);
                const itemClass = isFuture ? 'trip-filter-item trip-future' : 'trip-filter-item';
                const colorIndicator = isFuture ? '#6B6B6B' : trip.color;

                // Generar HTML de tags
                let tagsHtml = '';
                if (appConfig?.tripTagsEnabled && trip.tags && trip.tags.length > 0) {
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

                $yearTrips.append(`
                    <div class="${itemClass}">
                        <div class="form-check d-flex align-items-start gap-2">
                            <input class="form-check-input trip-checkbox flex-shrink-0 mt-1" type="checkbox" id="trip-${trip.id}" value="${trip.id}" data-year="${year}" checked>
                            <div class="trip-color-dot mt-1" style="background-color: ${colorIndicator};"></div>
                            <label class="form-check-label flex-grow-1" for="trip-${trip.id}">
                                <span class="trip-title">${escapeHtml(trip.title)}</span>
                                <span class="trip-details">${formatDateRange(trip.start_date, trip.end_date)}</span>
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
            const year = $(this).closest('.year-group').data('year');
            const prefs = loadPreferences();
            if (!prefs.yearCollapsedStates) prefs.yearCollapsedStates = {};
            prefs.yearCollapsedStates[year] = isCollapsing;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        });

        $('.year-checkbox').on('change', function () {
            const year = $(this).data('year');
            const isChecked = $(this).is(':checked');
            $(`.trip-checkbox[data-year="${year}"]`).each(function () {
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
        sortedYears.forEach(year => updateYearCheckboxState(year));
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

    function hexToRgba(hex, alpha) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? [
            parseInt(result[1], 16),
            parseInt(result[2], 16),
            parseInt(result[3], 16),
            alpha || 255
        ] : [255, 68, 68, alpha || 255];
    }

    function formatDateRange(startDate, endDate) {
        if (!startDate && !endDate) return 'Sin fechas';
        const start = startDate ? formatDate(startDate) : '';
        const end = endDate ? formatDate(endDate) : '';
        if (start && end) return `${start} - ${end}`;
        return start || end;
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString + 'T00:00:00');
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

        initLightbox();
    }

    // ==================== LIGHTBOX ====================

    function initLightbox() {
        const lightbox = document.getElementById('imageLightbox');
        if (lightbox) {
            lightbox.addEventListener('click', closeLightbox);
        }
    }

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
