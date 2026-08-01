/**
 * map-renderer.js
 * Shared MapLibre GL rendering helpers: route layers, flight arcs, and map markers.
 * Depends on map-config.js (window.MapConfig must be loaded first).
 * Consumed by both public_map.js and trip_single.js.
 */

window.MapRenderer = (function () {
    'use strict';

    /**
     * Creates the DOM element for a cluster marker.
     * @param {number} count - Number of points in the cluster.
     * @returns {HTMLElement}
     */
    function createClusterMarkerEl(count) {
        const el = document.createElement('div');
        el.className = 'marker-cluster-custom';
        el.innerHTML = `<span>${count}</span>`;
        el.style.cssText = [
            'background:#1e293b', 'border:2px solid white', 'border-radius:50%',
            'width:40px', 'height:40px', 'display:flex', 'align-items:center',
            'justify-content:center', 'box-shadow:0 2px 6px rgba(0,0,0,.2)',
            'font-weight:600', 'color:white', 'font-size:13px', 'cursor:pointer'
        ].join(';');
        return el;
    }

    /**
     * Creates the DOM element for a single point marker.
     * Uses MapConfig.pointTypeConfig for icons and colors.
     * @param {{ type: string, tripColor: string }} point
     * @returns {HTMLElement}
     */
    function createPointMarkerEl(point) {
        const cfg = MapConfig.pointTypeConfig;
        const typeCfg = cfg[point.type] || cfg['visit'];
        const iconStyle = typeCfg.darkText ? 'color:#000;stroke:#000;' : '';

        const el = document.createElement('div');
        el.className = 'custom-point-marker';
        el.innerHTML = `
            <div class="point-marker-inner point-type-${point.type}"
                 style="background-color:${typeCfg.color};border-color:${point.tripColor || '#3388ff'};">
                <span class="point-icon" style="${iconStyle}">${typeCfg.icon}</span>
            </div>
        `;
        return el;
    }

    /**
     * Adds a route to the map as a MapLibre GL line layer.
     *
     * If the route is a simple A-to-B plane route (exactly 2 coordinates), it should
     * be rendered as a deck.gl arc instead of a line. In that case this function does
     * NOT add any layer and returns a flight-data entry object for the caller to
     * accumulate and pass to renderFlightArcs().
     *
     * @param {maplibregl.Map} map
     * @param {object} route  - { id, transport_type, geojson }
     * @param {string} sourceId - Unique source ID (e.g. "route-1-42")
     * @param {string} layerId  - Unique layer ID (e.g. "route-layer-1-42")
     * @param {object} [opts]
     * @param {boolean} [opts.isFuture=false]   - Future trips get grey dashed style.
     * @param {string}  [opts.visibility='visible'] - MapLibre layer visibility.
     * @returns {{ source, target, color }|null}
     *   Returns a flight-data entry if this is a plane arc, null if a layer was added.
     */
    function addRouteLayer(map, route, sourceId, layerId, opts) {
        opts = opts || {};
        var isFuture = opts.isFuture || false;
        var visibility = opts.visibility || 'visible';

        var geojson = MapConfig.normalizeGeojson(route.geojson);
        if (!geojson || !geojson.geometry) return null;

        var transportType = route.transport_type || 'car';
        var cfg = MapConfig.transportConfig[transportType] || MapConfig.transportConfig['car'];
        var coords = geojson.geometry.coordinates || [];

        // Simple A-to-B plane → caller will render as deck.gl arc
        if (transportType === 'plane' && coords.length === 2) {
            var arcColor = isFuture
                ? [107, 107, 107, 150]
                : MapConfig.hexToRgba(cfg.color, 180);
            return { source: coords[0], target: coords[1], color: arcColor };
        }

        // Add as MapLibre line layer
        if (!map.getSource(sourceId)) {
            map.addSource(sourceId, { type: 'geojson', data: geojson });
        }

        if (!map.getLayer(layerId)) {
            var color = isFuture ? '#6B6B6B' : cfg.color;
            var lineWidth = isFuture ? 3 : 4;
            var paint = {
                'line-color': color,
                'line-width': lineWidth,
                'line-opacity': 0.7
            };

            if (isFuture) {
                paint['line-dasharray'] = [2, 6];
            } else if (cfg.dashArray) {
                paint['line-dasharray'] = cfg.dashArray;
            }

            map.addLayer({
                id: layerId,
                type: 'line',
                source: sourceId,
                layout: {
                    'line-join': 'round',
                    'line-cap': 'round',
                    'visibility': visibility
                },
                paint: paint,
                metadata: { transportType: transportType }
            });
        }

        return null; // layer was added, not an arc
    }

    /**
     * Creates or updates the deck.gl ArcLayer overlay for flight arcs.
     *
     * @param {maplibregl.Map} map
     * @param {Array}  flightData      - Array of { source, target, color } entries.
     * @param {object} [existingOverlay] - Previous MapboxOverlay to update instead of creating a new one.
     * @returns {object} The deck.gl MapboxOverlay (new or updated).
     */
    function renderFlightArcs(map, flightData, existingOverlay) {
        if (typeof deck === 'undefined') {
            console.warn('map-renderer: deck.gl not loaded, skipping flight arcs');
            return existingOverlay || null;
        }

        var arcLayer = new deck.ArcLayer({
            id: 'flight-arcs',
            data: flightData,
            getSourcePosition: function (d) { return d.source; },
            getTargetPosition: function (d) { return d.target; },
            getSourceColor: function (d) { return d.color; },
            getTargetColor: function (d) { return d.color; },
            getWidth: 2,
            getHeight: 0.3,
            greatCircle: true,
            pickable: true,
            numSegments: 25,
            updateTriggers: { getSourcePosition: flightData.length, getTargetPosition: flightData.length }
        });

        if (existingOverlay) {
            existingOverlay.setProps({ layers: [arcLayer] });
            return existingOverlay;
        }

        var overlay = new deck.MapboxOverlay({ interleaved: true, layers: [arcLayer] });
        map.addControl(overlay);
        return overlay;
    }

    // ── Leaflet helpers ───────────────────────────────────────────────────────

    /**
     * Creates a Leaflet DivIcon for a single point marker, matching the MapLibre style.
     * @param {{ type: string }} point
     * @param {string} tripColor - Border/accent color from the trip
     * @returns {L.DivIcon|null}
     */
    function createLeafletPointIcon(point, tripColor) {
        if (typeof L === 'undefined') return null;
        var cfg = MapConfig.pointTypeConfig;
        var typeCfg = cfg[point.type] || cfg['visit'];
        var iconStyle = typeCfg.darkText ? 'color:#000;stroke:#000;' : '';
        return L.divIcon({
            className: 'custom-point-marker',
            html: '<div class="point-marker-inner point-type-' + (point.type || 'visit') + '"'
                + ' style="background-color:' + typeCfg.color + ';border-color:' + (tripColor || '#3388ff') + ';">'
                + '<span class="point-icon" style="' + iconStyle + '">' + typeCfg.icon + '</span>'
                + '</div>',
            iconSize: [36, 36],
            iconAnchor: [18, 36],
            popupAnchor: [0, -36]
        });
    }

    /**
     * Creates a Leaflet DivIcon for a cluster marker, matching the MapLibre cluster style.
     * @param {number} count
     * @returns {L.DivIcon|null}
     */
    function createLeafletClusterIcon(count) {
        if (typeof L === 'undefined') return null;
        return L.divIcon({
            html: '<div class="marker-cluster-custom"><span>' + count + '</span></div>',
            className: 'custom-cluster-icon',
            iconSize: L.point(40, 40)
        });
    }

    // ── Popup utilities (private) ─────────────────────────────────────────────

    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text ? text.replace(/[&<>"']/g, function (m) { return map[m]; }) : '';
    }

    function escapeJsString(text) {
        if (!text) return '';
        return text.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        var isoString = dateString;
        if (dateString.indexOf(' ') !== -1) {
            isoString = dateString.replace(' ', 'T');
        } else if (dateString.indexOf('T') === -1) {
            isoString = dateString + 'T00:00:00';
        }
        var date = new Date(isoString);
        return date.toLocaleDateString('es-ES', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    /**
     * Creates rich popup HTML for a POI.
     * Single source of truth for popup content — used by both the public map and the trip detail page.
     *
     * @param {object} point - Point data: { title, description, type, latitude, longitude,
     *                         image_url, thumbnail_url, visit_date, links, originalLat?, originalLon? }
     * @param {object} [opts]
     * @param {boolean} [opts.showImage=true]       - Show thumbnail (set false when image is shown elsewhere, e.g. carousel)
     * @param {string}  [opts.tripColor]            - Trip accent color
     * @param {string}  [opts.tripTitle]            - Trip title (omit when already on the trip page)
     * @param {Array}   [opts.tripTags]             - Array of tag strings
     * @param {boolean} [opts.tripTagsEnabled=false]
     * @param {boolean} [opts.showDescription=true] - Show point description
     * @param {boolean} [opts.showLinks=true]       - Show external links
     * @param {boolean} [opts.showCoordinates=true] - Show lat/lon coordinates
     * @returns {string} HTML string
     */
    function createPoiPopup(point, opts) {
        opts = opts || {};
        var showImage = opts.showImage !== false;
        var tripColor = opts.tripColor || null;
        var tripTitle = opts.tripTitle || null;
        var tripTags = opts.tripTags || null;
        var tripTagsEnabled = opts.tripTagsEnabled || false;
        var showDescription = opts.showDescription !== false;
        var showLinks = opts.showLinks !== false;
        var showCoordinates = opts.showCoordinates !== false;

        var t = function (key, fallback) {
            return (typeof window.__ === 'function') ? window.__(key) : (fallback || '');
        };

        var typeConfig = MapConfig.pointTypeConfig[point.type] || MapConfig.pointTypeConfig['visit'];

        var html = '<div class="point-popup">';

        if (showImage) {
            var images = (point.images && point.images.length) ? point.images : null;

            // Backward compatibility: points served by an endpoint without `images`
            if (!images && point.image_url) {
                images = [{ url: point.image_url, thumbnail_url: point.thumbnail_url, caption: null }];
            }

            if (images) {
                var poiId = point.id;
                window.__poiGalleries = window.__poiGalleries || {};
                window.__poiGalleries[poiId] = images;

                var firstImage = images[0];
                html += '<div class="popup-gallery" data-poi-id="' + escapeHtml(String(poiId)) + '" data-index="0">';
                html += '<img src="' + escapeHtml(firstImage.thumbnail_url || firstImage.url) + '"'
                     + ' alt="' + escapeHtml(point.title) + '"'
                     + ' class="popup-image"'
                     + ' title="' + t('map.click_to_view_full', '') + '">';

                if (images.length > 1) {
                    html += '<button type="button" class="popup-gallery-nav prev" data-dir="-1"'
                         + ' aria-label="' + t('map.gallery_prev', 'Previous image') + '">&#10094;</button>';
                    html += '<button type="button" class="popup-gallery-nav next" data-dir="1"'
                         + ' aria-label="' + t('map.gallery_next', 'Next image') + '">&#10095;</button>';
                    html += '<div class="popup-gallery-dots">';
                    images.forEach(function (img, i) {
                        html += '<span class="popup-gallery-dot' + (i === 0 ? ' is-active' : '') + '"'
                             + ' data-index="' + i + '"></span>';
                    });
                    html += '</div>';
                }

                html += '</div>';
            }
        }

        html += '<div class="popup-content">';
        html += '<h6 class="popup-title">' + escapeHtml(point.title) + '</h6>';

        var typeLabel = typeConfig.labelKey ? t(typeConfig.labelKey, point.type) : (typeConfig.label || point.type);
        var textColor = typeConfig.darkText ? 'color: #000; --bs-badge-color: #000;' : '';
        html += '<span class="badge mb-2 d-inline-flex align-items-center gap-1"'
            + ' style="background-color: ' + typeConfig.color + '; ' + textColor + '">'
            + typeConfig.icon + ' ' + typeLabel + '</span>';

        if (tripTitle) {
            html += '<p class="popup-trip mb-1"><span style="color: ' + escapeHtml(tripColor || 'inherit') + '; font-weight: bold;">'
                + escapeHtml(tripTitle) + '</span></p>';
        }

        if (tripTagsEnabled && tripTags && tripTags.length > 0) {
            html += '<div class="mb-2 d-flex gap-1 flex-wrap">';
            tripTags.forEach(function (tag) {
                html += '<span class="badge bg-light text-dark border" style="font-size: 0.65em;">' + escapeHtml(tag) + '</span>';
            });
            html += '</div>';
        }

        if (point.visit_date) {
            html += '<p class="popup-date mb-1">' + formatDate(point.visit_date) + '</p>';
        }

        if (showDescription && point.description) {
            html += '<p class="popup-description">' + escapeHtml(point.description) + '</p>';
        }

        if (showLinks && point.links && point.links.length > 0) {
            html += '<div class="popup-links">';
            point.links.forEach(function (link) {
                var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15"'
                    + ' fill="' + escapeHtml(link.color) + '" viewBox="0 0 16 16">' + link.svg_paths + '</svg>';
                html += '<a href="' + escapeHtml(link.url) + '" target="_blank" rel="noopener noreferrer"'
                    + ' class="popup-link-btn" title="' + escapeHtml(link.label) + '">' + svg + '</a>';
            });
            html += '</div>';
        }

        if (showCoordinates) {
            var displayLat = (point.originalLat !== undefined) ? point.originalLat : point.latitude;
            var displayLon = (point.originalLon !== undefined) ? point.originalLon : point.longitude;
            html += '<p class="popup-coords">' + parseFloat(displayLat).toFixed(6) + ', ' + parseFloat(displayLon).toFixed(6) + '</p>';
        }

        html += '</div></div>';
        return html;
    }

    // ── Carousel navigation ──────────────────────────────────────────────────
    //
    // Popups are injected as an HTML string, so per-element listeners can't be
    // attached at build time. Navigation is delegated on document instead.

    function showInCarousel(container, index) {
        var poiId = container.dataset.poiId;
        var images = (window.__poiGalleries || {})[poiId];
        if (!images || !images.length) return;

        // Wrap around
        index = ((index % images.length) + images.length) % images.length;

        var image = images[index];
        container.dataset.index = String(index);
        container.querySelector('.popup-image').src = image.thumbnail_url || image.url;

        container.querySelectorAll('.popup-gallery-dot').forEach(function (dot, i) {
            dot.classList.toggle('is-active', i === index);
        });
    }

    document.addEventListener('click', function (e) {
        var arrow = e.target.closest ? e.target.closest('.popup-gallery-nav') : null;
        if (arrow) {
            e.preventDefault();
            e.stopPropagation();
            var navContainer = arrow.closest('.popup-gallery');
            showInCarousel(navContainer, parseInt(navContainer.dataset.index, 10) + parseInt(arrow.dataset.dir, 10));
            return;
        }

        var dot = e.target.closest ? e.target.closest('.popup-gallery-dot') : null;
        if (dot) {
            e.preventDefault();
            e.stopPropagation();
            var dotContainer = dot.closest('.popup-gallery');
            showInCarousel(dotContainer, parseInt(dot.dataset.index, 10));
            return;
        }

        var image = e.target.closest ? e.target.closest('.popup-gallery .popup-image') : null;
        if (image && typeof window.openPoiGallery === 'function') {
            e.preventDefault();
            e.stopPropagation();
            var imageContainer = image.closest('.popup-gallery');
            window.openPoiGallery(imageContainer.dataset.poiId, parseInt(imageContainer.dataset.index, 10));
        }
    });

    // ── Public API ────────────────────────────────────────────────────────────

    return {
        createClusterMarkerEl: createClusterMarkerEl,
        createPointMarkerEl: createPointMarkerEl,
        addRouteLayer: addRouteLayer,
        renderFlightArcs: renderFlightArcs,
        createPoiPopup: createPoiPopup,
        createLeafletPointIcon: createLeafletPointIcon,
        createLeafletClusterIcon: createLeafletClusterIcon
    };
}());
