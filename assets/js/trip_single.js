/**
 * Trip Single Page Logic
 */

let map;
let markers = [];
let activeMarkerId = null;
let pendingPopupMarkerId = null;
let currentOpenPopup = null;
let currentOpenPopupPointId = null;
let isReopeningPopup = false;
let flyToToken = 0;

/**
 * Fly (or jump) the map to a given lat/lng using the configured zoom and speed.
 * @param {number} lat
 * @param {number} lng
 * @param {function} [onEnd] - optional callback fired after animation ends (MapLibre only)
 */
function doFlyTo(lat, lng, onEnd) {
    const cfg = (typeof TRIP_TOOLTIP_CONFIG !== 'undefined') ? TRIP_TOOLTIP_CONFIG : {};
    const zoom  = cfg.flyZoom  ?? 11;
    const speed = cfg.flySpeed ?? 'normal';

    if (MAP_RENDERER === 'leaflet') {
        if (speed === 'none') {
            map.setView([lat, lng], zoom, { animate: false });
            if (onEnd) onEnd();
        } else {
            const durations = { slow: 2.0, normal: 1.0, fast: 0.4 };
            map.flyTo([lat, lng], zoom, { animate: true, duration: durations[speed] ?? 1.0 });
            if (onEnd) map.once('moveend', onEnd);
        }
    } else {
        flyToToken++;
        const myToken = flyToToken;
        if (speed === 'none') {
            map.jumpTo({ center: [lng, lat], zoom });
            if (onEnd) onEnd();
        } else {
            const speeds = { slow: 0.5, normal: 1.2, fast: 2.5 };
            map.flyTo({ center: [lng, lat], zoom, speed: speeds[speed] ?? 1.2 });
            if (onEnd) {
                map.once('moveend', () => {
                    if (myToken !== flyToToken) return;
                    onEnd(myToken);
                });
            }
        }
    }
    return flyToToken;
}

document.addEventListener('DOMContentLoaded', () => {
    initResizer();
    initMap();
    initInteractions();
    initTimeline();
    initCarouselDrag();
    initMediaPanel();

    // Auto-update units if needed
    if (window.UnitManager) {
        UnitManager.updatePageUnits();
    }
});

function initMap() {
    if (typeof TRIP_DATA === 'undefined') {
        console.error('No trip data found');
        return;
    }

    if (MAP_RENDERER === 'leaflet') {
        initLeaflet();
    } else {
        initMapLibre();
    }
}

function initMapLibre() {
    map = new maplibregl.Map({
        container: 'tripMap',
        style: MapConfig.getMapStyleUrl(MAP_STYLE),
        center: [0, 0],
        zoom: 1
    });

    let clusterMarkers = [];
    let supercluster   = null;

    map.on('load', () => {
        const flightData = [];

        // Add routes via shared renderer
        TRIP_DATA.routes.forEach(route => {
            const arcEntry = MapRenderer.addRouteLayer(
                map, route,
                `route-${route.id}`,
                `route-layer-${route.id}`
            );
            if (arcEntry) flightData.push(arcEntry);
        });

        // Render flight arcs via shared renderer
        if (flightData.length > 0) {
            MapRenderer.renderFlightArcs(map, flightData, null);
        }

        // Initialize Supercluster for points
        supercluster = new Supercluster({ radius: 50, maxZoom: 16, minZoom: 0 });

        supercluster.load(TRIP_DATA.points.map(point => ({
            type: 'Feature',
            properties: { ...point, tripColor: TRIP_DATA.color },
            geometry: { type: 'Point', coordinates: [point.longitude, point.latitude] }
        })));


        function updateClusters() {
            // Save which popup was open before destroying markers
            const reopenId = currentOpenPopupPointId;

            clusterMarkers.forEach(m => m.remove());
            clusterMarkers = [];
            markers = []; // Clear stale references — rebuilt below

            const bounds = map.getBounds();
            const zoom   = Math.floor(map.getZoom());

            supercluster.getClusters(
                [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()],
                zoom
            ).forEach(feature => {
                const coords = feature.geometry.coordinates;

                if (feature.properties.cluster) {
                    const el = MapRenderer.createClusterMarkerEl(feature.properties.point_count);
                    el.addEventListener('click', e => {
                        e.stopPropagation();
                        map.easeTo({
                            center: coords,
                            zoom: supercluster.getClusterExpansionZoom(feature.properties.cluster_id)
                        });
                    });
                    clusterMarkers.push(new maplibregl.Marker({ element: el }).setLngLat(coords).addTo(map));
                } else {
                    const point = feature.properties;
                    const el    = MapRenderer.createPointMarkerEl(point);

                    const tooltipCfg = (typeof TRIP_TOOLTIP_CONFIG !== 'undefined') ? TRIP_TOOLTIP_CONFIG : {};
                    const markerPopup = new maplibregl.Popup({ offset: 25, closeButton: true, closeOnClick: false })
                        .setHTML(MapRenderer.createPoiPopup(point, {
                            showImage: tooltipCfg.showImage !== false,
                            tripColor: TRIP_DATA.color,
                            tripTitle: tooltipCfg.showTripTitle ? TRIP_DATA.title : null,
                            showDescription: tooltipCfg.showDescription !== false,
                            showLinks: tooltipCfg.showLinks !== false,
                            showCoordinates: tooltipCfg.showCoordinates !== false
                        }));

                    markerPopup.on('open', () => {
                        if (isReopeningPopup) {
                            currentOpenPopup = markerPopup;
                            currentOpenPopupPointId = point.id;
                            return; // silent reopen — skip side effects
                        }

                        // Close previous popup before updating tracking vars
                        const prevPopup = currentOpenPopup;
                        currentOpenPopup = markerPopup;
                        currentOpenPopupPointId = point.id;
                        if (prevPopup && prevPopup !== markerPopup) {
                            prevPopup.remove();
                        }

                        // Update marker-selected classes immediately
                        if (activeMarkerId && markers[activeMarkerId]) {
                            const prevEl = markers[activeMarkerId].getElement();
                            if (prevEl) prevEl.classList.remove('marker-selected');
                        }
                        el.classList.add('marker-selected');
                        activeMarkerId = point.id;

                        highlightTimelineItem(point.id);
                        highlightCarouselItem(point.id);
                    });
                    markerPopup.on('close', () => {
                        if (!isReopeningPopup) {
                            if (currentOpenPopup === markerPopup) currentOpenPopup = null;
                            if (currentOpenPopupPointId === point.id) currentOpenPopupPointId = null;
                        }
                    });

                    const marker = new maplibregl.Marker({ element: el, anchor: 'bottom' })
                        .setLngLat(coords)
                        .setPopup(markerPopup)
                        .addTo(map);

                    markers[point.id] = marker;
                    if (String(point.id) === String(activeMarkerId)) {
                        el.classList.add('marker-selected');
                    }
                    clusterMarkers.push(marker);
                }
            });

            // Bug 2 fix: reopen popup silently after markers are rebuilt
            if (reopenId !== null && markers[reopenId]) {
                isReopeningPopup = true;
                markers[reopenId].togglePopup();
                isReopeningPopup = false;
            }
        }

        updateClusters();
        map.on('zoom', updateClusters);
        map.on('move', updateClusters);

        // Fit bounds to all content
        const bounds   = new maplibregl.LngLatBounds();
        let hasBounds  = false;

        TRIP_DATA.routes.forEach(route => {
            const geojson = MapConfig.normalizeGeojson(route.geojson);
            const coords  = geojson && geojson.geometry ? geojson.geometry.coordinates || [] : [];
            coords.forEach(coord => { bounds.extend(coord); hasBounds = true; });
        });

        TRIP_DATA.points.forEach(point => {
            if (point.latitude && point.longitude) {
                bounds.extend([point.longitude, point.latitude]);
                hasBounds = true;
            }
        });

        if (hasBounds) {
            map.fitBounds(bounds, { padding: 50 });
        }
    });

    map.addControl(new maplibregl.NavigationControl());
}

function initLeaflet() {
    map = L.map('tripMap', { closePopupOnClick: false }).setView([0, 0], 2);

    const tileUrl = MapConfig.getRasterTileUrl(MAP_STYLE);

    L.tileLayer(tileUrl, {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
    }).addTo(map);

    const bounds = L.latLngBounds();
    let hasLayer = false;

    // Helper function to create curved flight paths
    function createCurvedFlightPath(startCoord, endCoord, curvature) {
        const start = L.latLng(startCoord[1], startCoord[0]);
        const end = L.latLng(endCoord[1], endCoord[0]);

        const midLat = (start.lat + end.lat) / 2;
        const midLng = (start.lng + end.lng) / 2;
        const distance = start.distanceTo(end);

        const baseHeight = distance / 5;
        const curveHeight = Math.max(baseHeight, distance * 0.15);
        const offsetDegrees = (curveHeight / 111320) * (curvature || 1);

        const controlPoint = L.latLng(midLat + offsetDegrees, midLng);

        const points = [];
        const segments = Math.max(20, Math.floor(distance / 50000));

        for (let i = 0; i <= segments; i++) {
            const t = i / segments;
            const lat = (1 - t) * (1 - t) * start.lat +
                2 * (1 - t) * t * controlPoint.lat +
                t * t * end.lat;
            const lng = (1 - t) * (1 - t) * start.lng +
                2 * (1 - t) * t * controlPoint.lng +
                t * t * end.lng;
            points.push([lat, lng]);
        }

        return points;
    }

    // Add Routes
    TRIP_DATA.routes.forEach(route => {
        if (!route.geojson) return;

        const geojson        = MapConfig.normalizeGeojson(route.geojson);
        if (!geojson || !geojson.geometry) return;

        const transportType  = route.transport_type || 'car';
        const cfg            = MapConfig.transportConfig[transportType] || MapConfig.transportConfig['car'];
        const coords         = geojson.geometry.coordinates || [];
        const isPlaneRoute   = transportType === 'plane';

        if (isPlaneRoute) {
            const latLngs = [];
            if (coords.length === 2) {
                latLngs.push(...createCurvedFlightPath(coords[0], coords[1], 1));
            } else {
                for (let i = 0; i < coords.length - 1; i++) {
                    latLngs.push(...createCurvedFlightPath(coords[i], coords[i + 1], 0.8));
                }
            }

            const layer = L.polyline(latLngs, {
                color:       cfg.color,
                weight:      2,
                opacity:     0.6,
                dashArray:   '4, 6',
                smoothFactor: 1
            }).addTo(map);

            bounds.extend(layer.getBounds());
            hasLayer = true;
        } else {
            const dashArray = cfg.dashArray ? cfg.dashArray.join(', ') : null;
            const layer = L.geoJSON(geojson, {
                style: {
                    color:     cfg.color,
                    weight:    4,
                    opacity:   0.7,
                    dashArray: dashArray
                }
            }).addTo(map);

            bounds.extend(layer.getBounds());
            hasLayer = true;
        }
    });

    // Add Points with clustering
    const markerClusterGroup = L.markerClusterGroup({
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
        iconCreateFunction: function (cluster) {
            return MapRenderer.createLeafletClusterIcon(cluster.getChildCount());
        }
    });

    TRIP_DATA.points.forEach(point => {
        if (!point.latitude || !point.longitude) return;

        const icon = MapRenderer.createLeafletPointIcon(point, TRIP_DATA.color);

        const tooltipCfg = (typeof TRIP_TOOLTIP_CONFIG !== 'undefined') ? TRIP_TOOLTIP_CONFIG : {};
        const marker = L.marker([point.latitude, point.longitude], { icon: icon })
            .bindPopup(MapRenderer.createPoiPopup(point, {
                showImage: tooltipCfg.showImage !== false,
                tripColor: TRIP_DATA.color,
                tripTitle: tooltipCfg.showTripTitle ? TRIP_DATA.title : null,
                showDescription: tooltipCfg.showDescription !== false,
                showLinks: tooltipCfg.showLinks !== false,
                showCoordinates: tooltipCfg.showCoordinates !== false
            }), { maxWidth: 360, className: 'custom-popup' });

        marker.on('popupopen', () => {
            if (activeMarkerId && markers[activeMarkerId]) {
                const prevEl = markers[activeMarkerId].getElement();
                if (prevEl) prevEl.classList.remove('marker-selected');
            }
            const el = marker.getElement();
            if (el) el.classList.add('marker-selected');
            activeMarkerId = point.id;
            highlightTimelineItem(point.id);
            highlightCarouselItem(point.id);
        });

        marker.on('popupclose', () => {
            const el = marker.getElement();
            if (el) el.classList.remove('marker-selected');
            if (String(activeMarkerId) === String(point.id)) activeMarkerId = null;
        });

        marker.on('add', () => {
            if (String(point.id) === String(activeMarkerId)) {
                const el = marker.getElement();
                if (el) el.classList.add('marker-selected');
            }
            if (String(point.id) === String(pendingPopupMarkerId)) {
                marker.openPopup();
                pendingPopupMarkerId = null;
            }
        });

        markerClusterGroup.addLayer(marker);
        markers[point.id] = marker;
        bounds.extend(marker.getLatLng());
        hasLayer = true;
    });

    map.addLayer(markerClusterGroup);

    if (hasLayer) {
        map.fitBounds(bounds, { padding: [50, 50] });
    }
}

function initTimeline() {
    const markerEls = document.querySelectorAll('.point-marker');
    if (markerEls.length < 2) return;

    const container = document.querySelector('.timeline-points');
    if (!container) return;

    const svgNS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(svgNS, 'svg');
    svg.classList.add('timeline-route-svg');
    container.appendChild(svg);

    const tripColor = (typeof TRIP_DATA !== 'undefined' && TRIP_DATA.color) ? TRIP_DATA.color : '#3b82f6';

    function drawPath() {
        svg.innerHTML = '';

        const containerRect = container.getBoundingClientRect();

        const pts = Array.from(markerEls).map(el => {
            const r = el.getBoundingClientRect();
            return {
                x: r.left - containerRect.left + r.width / 2,
                y: r.top - containerRect.top + r.height / 2
            };
        });

        // Build S-curve path alternating left/right between each pair
        let d = `M ${pts[0].x.toFixed(1)} ${pts[0].y.toFixed(1)}`;

        for (let i = 1; i < pts.length; i++) {
            const prev = pts[i - 1];
            const curr = pts[i];
            const dy = curr.y - prev.y;
            // Alternate the bulge direction each segment
            const flip = i % 2 === 1 ? 1 : -1;
            const amplitude = Math.min(Math.abs(dy) * 0.45, 52);

            const cp1x = (prev.x + amplitude * flip).toFixed(1);
            const cp1y = (prev.y + dy * 0.33).toFixed(1);
            const cp2x = (curr.x + amplitude * flip).toFixed(1);
            const cp2y = (curr.y - dy * 0.33).toFixed(1);

            d += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${curr.x.toFixed(1)} ${curr.y.toFixed(1)}`;
        }

        const path = document.createElementNS(svgNS, 'path');
        path.setAttribute('d', d);
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', tripColor);
        path.setAttribute('stroke-width', '2');
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');

        // Animate path drawing then settle into dashed style
        const length = path.getTotalLength ? Math.ceil(path.getTotalLength()) : 2000;
        path.style.setProperty('--path-length', length);
        path.setAttribute('stroke-dasharray', `${length}`);
        path.style.animation = `drawRoutePath 1.2s ease forwards`;

        // After draw animation, switch to dashed appearance
        path.addEventListener('animationend', () => {
            path.setAttribute('stroke-dasharray', '6 5');
            path.setAttribute('opacity', '0.55');
            path.style.animation = '';
        }, { once: true });

        svg.appendChild(path);
    }

    // Draw after layout is painted
    requestAnimationFrame(drawPath);
    window.addEventListener('resize', drawPath);
}

function highlightTimelineItem(pointId) {
    document.querySelectorAll('.timeline-point').forEach(p => p.classList.remove('active'));
    const target = document.querySelector(`.timeline-point[data-id="${pointId}"]`);
    if (!target) return;
    target.classList.add('active');
    target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function highlightCarouselItem(pointId) {
    document.querySelectorAll('.media-item').forEach(item => item.classList.remove('active'));
    const target = document.querySelector(`.media-item[data-point-id="${pointId}"]`);
    if (!target) return;
    target.classList.add('active');
    target.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
}

function initInteractions() {
    // Timeline clicks
    document.querySelectorAll('.timeline-point').forEach(el => {
        el.addEventListener('click', () => {
            const lat = parseFloat(el.dataset.lat);
            const lng = parseFloat(el.dataset.lng);
            const id = el.dataset.id;
            const routeId = el.dataset.routeId;

            // Handle route click
            if (routeId) {
                const route = TRIP_DATA.routes.find(r => String(r.id) === String(routeId));
                if (route && route.geojson) {
                    // Highlight active item in timeline
                    document.querySelectorAll('.timeline-point').forEach(p => p.classList.remove('active'));
                    el.classList.add('active');

                    // Fit map bounds to route
                    fitToRoute(route);
                }
                return;
            }

            // Handle point click (original behavior)
            if (isNaN(lat) || isNaN(lng)) return;

            // Highlight active point in timeline
            document.querySelectorAll('.timeline-point').forEach(p => p.classList.remove('active'));
            el.classList.add('active');

            // Scroll carousel to matching photo
            highlightCarouselItem(id);

            // Highlight active marker on map
            if (activeMarkerId && markers[activeMarkerId]) {
                const prevEl = markers[activeMarkerId].getElement();
                if (prevEl) prevEl.classList.remove('marker-selected');
            }
            activeMarkerId = id;
            if (markers[id]) {
                const curEl = markers[id].getElement();
                if (curEl) curEl.classList.add('marker-selected');
            }

            // Fly to map
            if (MAP_RENDERER === 'leaflet') {
                pendingPopupMarkerId = id;
                doFlyTo(lat, lng);
                if (markers[id] && markers[id]._map) { markers[id].openPopup(); pendingPopupMarkerId = null; }
            } else {
                doFlyTo(lat, lng, (myToken) => {
                    if (myToken !== flyToToken) return; // cancelled by newer flyTo
                    if (markers[id] && markers[id].getPopup()) markers[id].togglePopup();
                });
            }
        });
    });
}

function fitToRoute(route) {
    if (!route.geojson || !route.geojson.geometry || !route.geojson.geometry.coordinates) return;

    const coords = route.geojson.geometry.coordinates;
    if (coords.length === 0) return;

    if (MAP_RENDERER === 'leaflet') {
        const latLngs = coords.map(c => L.latLng(c[1], c[0]));
        const bounds = L.latLngBounds(latLngs);
        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 12 });
    } else {
        const maplibregl = window.maplibregl;
        if (!maplibregl) return;
        const bounds = coords.reduce((b, c) => b.extend(c), new maplibregl.LngLatBounds(coords[0], coords[0]));
        map.fitBounds(bounds, { padding: 50, maxZoom: 12 });
    }
}

// Llamada desde el carrusel del popup (map-renderer.js).
// Traduce el índice local del POI a su posición en la galería del viaje.
window.openPoiGallery = function (poiId, localIndex) {
    if (galleryItems.length === 0) initGallery();

    const index = galleryItems.findIndex(function (item) {
        return item.pointId === String(poiId) && item.localIndex === (localIndex || 0);
    });

    if (index !== -1) showLightboxImage(index);
};

// Compatibilidad: imágenes sueltas sin galería (rutas).
window.openLightbox = function (imageUrl) {
    if (galleryItems.length === 0) initGallery();
    const index = galleryItems.findIndex(function (item) { return item.url === imageUrl; });

    if (index !== -1) {
        showLightboxImage(index);
        return;
    }

    document.getElementById('lightboxImage').src = imageUrl;
    document.getElementById('lightboxTitle').textContent = '';
    document.getElementById('lightboxDesc').style.display = 'none';
    document.querySelector('.lightbox-footer').classList.remove('has-content');
    document.getElementById('imageLightbox').style.display = 'flex';
};

// Lightbox variables
let currentImageIndex = -1;
let galleryItems = [];

// Construye la galería aplanada del viaje.
//
// Orden: puntos por visit_date, y dentro de cada punto sus imágenes por
// sort_order. Así changeImage() agota la galería de un POI antes de pasar
// al siguiente. Se arma desde TRIP_DATA y no del DOM, porque el carrusel
// lateral sólo muestra la portada de cada punto.
function initGallery() {
    galleryItems = [];
    if (typeof TRIP_DATA === 'undefined' || !TRIP_DATA.points) return;

    const points = TRIP_DATA.points
        .filter(function (p) { return p.images && p.images.length > 0; })
        .slice()
        .sort(function (a, b) {
            const fa = Date.parse(a.visit_date || '1970-01-01T00:00:00');
            const fb = Date.parse(b.visit_date || '1970-01-01T00:00:00');
            return fa - fb;
        });

    points.forEach(function (point) {
        point.images.forEach(function (image, i) {
            galleryItems.push({
                url:      image.url,
                title:    point.title,
                desc:     image.caption || point.description || '',
                pointId:  String(point.id),
                localIndex: i
            });
        });
    });
}

// Call initGallery on load
document.addEventListener('DOMContentLoaded', () => {
    initGallery();
});

// Lightbox functions (global scope)
window.viewImage = function (url) {
    // Deprecated for direct calls, but kept for compatibility
    // Try to find index in current gallery
    if (galleryItems.length === 0) initGallery();
    const index = galleryItems.findIndex(item => item.url === url);
    if (index !== -1) {
        showLightboxImage(index);
    } else {
        // Fallback for isolated images
        const lightbox = document.getElementById('imageLightbox');
        const img = document.getElementById('lightboxImage');
        img.src = url;
        document.getElementById('lightboxTitle').textContent = '';
        document.getElementById('lightboxDesc').textContent = '';
        document.querySelector('.lightbox-footer').classList.remove('has-content');
        lightbox.style.display = 'flex';
    }
};

window.viewImageFromData = function (element) {
    if (galleryItems.length === 0) initGallery();

    const pointId = element.dataset.pointId;
    const url     = element.dataset.img;
    const showImage = (typeof TRIP_TOOLTIP_CONFIG !== 'undefined') ? TRIP_TOOLTIP_CONFIG.showImage !== false : true;

    if (!pointId) return;

    // Highlight timeline and carousel immediately on click
    highlightTimelineItem(pointId);
    highlightCarouselItem(pointId);

    // Get lat/lng from the matching timeline element
    const timelineEl = document.querySelector(`.timeline-point[data-id="${pointId}"]`);
    const lat = timelineEl ? parseFloat(timelineEl.dataset.lat) : NaN;
    const lng = timelineEl ? parseFloat(timelineEl.dataset.lng) : NaN;

    // Update active marker highlight
    if (activeMarkerId && markers[activeMarkerId]) {
        const prevEl = markers[activeMarkerId].getElement();
        if (prevEl) prevEl.classList.remove('marker-selected');
    }
    activeMarkerId = pointId;
    if (markers[pointId]) {
        const curEl = markers[pointId].getElement();
        if (curEl) curEl.classList.add('marker-selected');
    }

    // Close any open tooltip
    if (MAP_RENDERER === 'leaflet') {
        pendingPopupMarkerId = null;
        if (map) map.closePopup();
    } else {
        if (currentOpenPopup) {
            currentOpenPopup.remove();
            currentOpenPopup = null;
            currentOpenPopupPointId = null;
        }
    }

    // If tooltip has no header image, the carousel is the only way to see the photo: open lightbox.
    // If tooltip has header image, the lightbox is accessible from the tooltip image — just fly.
    if (!showImage) {
        const index = galleryItems.findIndex(function (item) {
            return item.pointId === String(pointId) && item.localIndex === 0;
        });
        if (index !== -1) showLightboxImage(index);
    }

    if (!isNaN(lat) && !isNaN(lng)) doFlyTo(lat, lng);
};

function showLightboxImage(index) {
    if (index < 0 || index >= galleryItems.length) return;

    currentImageIndex = index;
    const item = galleryItems[index];

    const lightbox = document.getElementById('imageLightbox');
    const img = document.getElementById('lightboxImage');
    const titleEl = document.getElementById('lightboxTitle');
    const descEl = document.getElementById('lightboxDesc');
    const footer = document.querySelector('.lightbox-footer');

    img.src = item.url;
    titleEl.textContent = item.title;

    if (item.desc) {
        descEl.textContent = item.desc;
        descEl.style.display = 'block';
    } else {
        descEl.style.display = 'none';
    }

    if (item.title || item.desc) {
        footer.classList.add('has-content');
    } else {
        footer.classList.remove('has-content');
    }

    const counter = document.getElementById('lightboxCounter');
    if (counter) {
        const template = (typeof window.__ === 'function')
            ? window.__('map.gallery_counter') : '{current} / {total}';
        counter.textContent = template
            .replace('{current}', index + 1)
            .replace('{total}', galleryItems.length);
        counter.style.display = galleryItems.length > 1 ? 'block' : 'none';
    }

    lightbox.style.display = 'flex';
}

window.changeImage = function (step) {
    const newIndex = currentImageIndex + step;
    if (newIndex >= 0 && newIndex < galleryItems.length) {
        showLightboxImage(newIndex);
    } else if (newIndex < 0) {
        showLightboxImage(galleryItems.length - 1); // Loop to last
    } else if (newIndex >= galleryItems.length) {
        showLightboxImage(0); // Loop to first
    }
};

window.closeLightbox = function () {
    document.getElementById('imageLightbox').style.display = 'none';
};

// Keyboard navigation
document.addEventListener('keydown', (e) => {
    const lightbox = document.getElementById('imageLightbox');
    if (lightbox.style.display === 'flex') {
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') changeImage(-1);
        if (e.key === 'ArrowRight') changeImage(1);
    }
});

// ==================== CAROUSEL DRAG ====================

function initCarouselDrag() {
    const container = document.querySelector('.media-carousel');
    if (!container) return;

    const DRAG_THRESHOLD = 5;
    let isDown      = false;
    let startX      = 0;
    let scrollLeft  = 0;
    let hasDragged  = false;

    // — Mouse drag —
    container.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        isDown     = true;
        hasDragged = false;
        startX     = e.pageX - container.offsetLeft;
        scrollLeft = container.scrollLeft;
        container.classList.add('is-dragging');
        container.style.scrollBehavior = 'auto';
        container.style.scrollSnapType = 'none';
        e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        const x    = e.pageX - container.offsetLeft;
        const walk = x - startX;
        if (Math.abs(walk) > DRAG_THRESHOLD) hasDragged = true;
        container.scrollLeft = scrollLeft - walk;
    });

    document.addEventListener('mouseup', () => {
        if (!isDown) return;
        isDown = false;
        container.classList.remove('is-dragging');
        container.style.scrollBehavior = '';
        container.style.scrollSnapType = '';
    });

    // Block the click that fires right after releasing a drag
    container.addEventListener('click', (e) => {
        if (hasDragged) {
            e.stopPropagation();
            e.preventDefault();
            hasDragged = false;
        }
    }, true);

    // — Touch drag —
    let touchStartX    = 0;
    let touchScrollLeft = 0;

    container.addEventListener('touchstart', (e) => {
        touchStartX     = e.touches[0].pageX;
        touchScrollLeft = container.scrollLeft;
        container.style.scrollBehavior = 'auto';
        container.style.scrollSnapType = 'none';
    }, { passive: true });

    container.addEventListener('touchmove', (e) => {
        const walk = touchStartX - e.touches[0].pageX;
        container.scrollLeft = touchScrollLeft + walk;
    }, { passive: true });

    container.addEventListener('touchend', () => {
        container.style.scrollBehavior = '';
        container.style.scrollSnapType = '';
    }, { passive: true });
}

// Carousel Navigation
window.scrollCarousel = function (direction) {
    const container = document.querySelector('.media-carousel');
    if (!container) return;

    const scrollAmount = 300; // Aproximadamente el ancho de un media-item + gap
    const targetScroll = container.scrollLeft + (direction * scrollAmount);

    container.scrollTo({
        left: targetScroll,
        behavior: 'smooth'
    });
};

// ==================== MEDIA PANEL (collapse + resize) ====================

const MEDIA_HEIGHT_STORAGE_KEY    = 'travelmap_trip_media_height';
const MEDIA_COLLAPSED_STORAGE_KEY = 'travelmap_trip_media_collapsed';
const MEDIA_MIN_HEIGHT     = 96;  // header + resizer + a sliver of content
const MEDIA_MIN_MAP_HEIGHT = 160; // never let the map be squeezed below this

function initMediaPanel() {
    const media   = document.getElementById('tripMedia');
    const toggle  = document.getElementById('mediaToggle');
    const resizer = document.getElementById('mediaResizer');
    const visuals = document.querySelector('.trip-visuals');
    const mapEl   = document.getElementById('tripMap');

    if (!media || !toggle || !visuals || !mapEl) return;

    const desktop = window.matchMedia('(min-width: 992px)');

    let collapsed = localStorage.getItem(MEDIA_COLLAPSED_STORAGE_KEY) === '1';
    let height    = parseFloat(localStorage.getItem(MEDIA_HEIGHT_STORAGE_KEY)) || null;

    function clampHeight(px) {
        const max = Math.max(MEDIA_MIN_HEIGHT, visuals.getBoundingClientRect().height - MEDIA_MIN_MAP_HEIGHT);
        return Math.min(Math.max(px, MEDIA_MIN_HEIGHT), max);
    }

    function applyHeight(px) {
        media.style.flex   = `0 0 ${px}px`;
        media.style.height = `${px}px`;
        mapEl.style.flex   = '1 1 auto';
        mapEl.style.height = 'auto';
    }

    function clearHeight() {
        media.style.flex   = '';
        media.style.height = '';
        mapEl.style.flex   = '';
        mapEl.style.height = '';
    }

    function render() {
        media.classList.toggle('is-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', String(!collapsed));

        const label = collapsed ? toggle.dataset.labelExpand : toggle.dataset.labelCollapse;
        if (label) toggle.setAttribute('title', label);

        if (!collapsed && desktop.matches && height) {
            applyHeight(clampHeight(height));
        } else {
            // Collapsed or mobile: let the stylesheet drive the layout
            clearHeight();
        }

        notifyMapResize();
    }

    toggle.addEventListener('click', () => {
        collapsed = !collapsed;
        localStorage.setItem(MEDIA_COLLAPSED_STORAGE_KEY, collapsed ? '1' : '0');
        render();
    });

    if (resizer) {
        let isResizing  = false;
        let startY      = 0;
        let startHeight = 0;

        resizer.addEventListener('mousedown', (e) => {
            if (!desktop.matches || collapsed) return;
            isResizing  = true;
            startY      = e.clientY;
            startHeight = media.getBoundingClientRect().height;
            resizer.classList.add('is-resizing');
            document.body.style.cursor = 'row-resize';
            document.body.style.userSelect = 'none';
            e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
            if (!isResizing) return;
            // Dragging up grows the panel, so the delta is inverted
            height = clampHeight(startHeight - (e.clientY - startY));
            applyHeight(height);
            notifyMapResize();
        });

        document.addEventListener('mouseup', () => {
            if (!isResizing) return;
            isResizing = false;
            resizer.classList.remove('is-resizing');
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            localStorage.setItem(MEDIA_HEIGHT_STORAGE_KEY, height);
            notifyMapResize();
        });
    }

    // Re-apply sizing when crossing the desktop breakpoint
    if (typeof desktop.addEventListener === 'function') {
        desktop.addEventListener('change', render);
    } else if (typeof desktop.addListener === 'function') {
        desktop.addListener(render);
    }

    render();
}

// ==================== PANEL RESIZER ====================

const RESIZER_STORAGE_KEY = 'travelmap_trip_split';

function initResizer() {
    const resizer   = document.getElementById('tripResizer');
    const leftPanel = document.querySelector('.trip-details');
    const container = document.querySelector('.trip-container');

    if (!resizer || !leftPanel || !container) return;

    // Only active on desktop
    const mq = window.matchMedia('(min-width: 992px)');
    if (!mq.matches) return;

    // Restore saved width
    const saved = localStorage.getItem(RESIZER_STORAGE_KEY);
    if (saved) {
        applyLeftWidth(leftPanel, parseFloat(saved));
    }

    let isResizing = false;
    let startX = 0;
    let startWidth = 0;

    resizer.addEventListener('mousedown', (e) => {
        isResizing = true;
        startX = e.clientX;
        startWidth = leftPanel.getBoundingClientRect().width;
        resizer.classList.add('is-resizing');
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
        e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
        if (!isResizing) return;
        const containerWidth = container.getBoundingClientRect().width;
        const minPx = 220;
        const maxPx = containerWidth - 220 - 6; // 6px = resizer width
        const newWidth = Math.min(Math.max(startWidth + (e.clientX - startX), minPx), maxPx);
        applyLeftWidth(leftPanel, newWidth);
        // Notify map to resize during drag
        notifyMapResize();
    });

    document.addEventListener('mouseup', () => {
        if (!isResizing) return;
        isResizing = false;
        resizer.classList.remove('is-resizing');
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        // Persist preference
        localStorage.setItem(RESIZER_STORAGE_KEY, leftPanel.getBoundingClientRect().width);
        notifyMapResize();
    });
}

function applyLeftWidth(leftPanel, widthPx) {
    leftPanel.style.flex = `0 0 ${widthPx}px`;
    leftPanel.style.width = `${widthPx}px`;
}

function notifyMapResize() {
    if (map) {
        if (typeof map.resize === 'function') {
            map.resize(); // MapLibre GL
        } else if (typeof map.invalidateSize === 'function') {
            map.invalidateSize(); // Leaflet
        }
    }
}
