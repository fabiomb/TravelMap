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
    let pointsClusters = {}; // { tripId: clusterGroup }
    let allPointsCluster; // Cluster global para todos los puntos

    // Colores y configuraciones por tipo de transporte
    const transportConfig = {
        'plane': { color: '#FF4444', icon: '✈️', dashArray: '10, 5' },
        'ship': { color: '#00AAAA', icon: '🚢', dashArray: '10, 5' },
        'car': { color: '#4444FF', icon: '🚗', dashArray: null },
        'train': { color: '#FF8800', icon: '🚂', dashArray: null },
        'walk': { color: '#44FF44', icon: '🚶', dashArray: null }
    };

    // Iconos por tipo de punto
    const pointTypeConfig = {
        'stay': { emoji: '🏨', label: 'Alojamiento', color: '#FF6B6B' },
        'visit': { emoji: '📸', label: 'Punto de Visita', color: '#4ECDC4' },
        'food': { emoji: '🍽️', label: 'Restaurante', color: '#FFE66D' }
    };

    /**
     * Inicializa el mapa
     */
    function initMap() {
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

        // Cluster global para todos los puntos
        allPointsCluster = L.markerClusterGroup({
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true,
            spiderfyOnMaxZoom: true,
            maxClusterRadius: 80,
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

        console.log('Mapa público inicializado');
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
                    console.log('Datos cargados:', tripsData.length, 'viajes');
                    
                    renderTripsPanel();
                    renderAllTrips();
                    fitMapToContent();
                } else {
                    showError('No se encontraron viajes para mostrar');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar datos:', error);
                showError('Error al cargar los datos del servidor');
            }
        });
    }

    /**
     * Renderiza el panel de viajes con checkboxes
     */
    function renderTripsPanel() {
        const $tripsList = $('#tripsList');
        $tripsList.empty();

        if (tripsData.length === 0) {
            $tripsList.html('<p class="text-muted small text-center">No hay viajes disponibles</p>');
            return;
        }

        tripsData.forEach(function(trip) {
            const routesCount = trip.routes ? trip.routes.length : 0;
            const pointsCount = trip.points ? trip.points.length : 0;
            
            const $tripItem = $(`
                <div class="trip-filter-item">
                    <div class="form-check">
                        <input class="form-check-input trip-checkbox" 
                               type="checkbox" 
                               id="trip-${trip.id}" 
                               value="${trip.id}" 
                               checked>
                        <label class="form-check-label w-100" for="trip-${trip.id}">
                            <div class="d-flex align-items-start">
                                <div class="trip-color-indicator" style="background-color: ${trip.color};"></div>
                                <div class="flex-grow-1">
                                    <strong class="d-block">${escapeHtml(trip.title)}</strong>
                                    <small class="text-muted d-block">
                                        ${formatDateRange(trip.start_date, trip.end_date)}
                                    </small>
                                    <small class="text-muted">
                                        <span class="badge bg-secondary bg-opacity-25 text-dark me-1">${routesCount} rutas</span>
                                        <span class="badge bg-secondary bg-opacity-25 text-dark">${pointsCount} puntos</span>
                                    </small>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
            `);

            $tripsList.append($tripItem);
        });

        // Eventos de los checkboxes
        $('.trip-checkbox').on('change', onTripToggle);
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

        // Inicializar arrays de layers
        routesLayers[tripId] = [];
        pointsClusters[tripId] = L.markerClusterGroup({
            showCoverageOnHover: false,
            maxClusterRadius: 60
        });

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

        // Agregar cluster de puntos al cluster global
        pointsClusters[tripId].eachLayer(function(layer) {
            allPointsCluster.addLayer(layer);
        });
    }

    /**
     * Renderiza una ruta en el mapa
     */
    function renderRoute(route, trip) {
        if (!route.geojson || !route.geojson.geometry) {
            return;
        }

        const transportType = route.transport_type || 'car';
        const config = transportConfig[transportType] || transportConfig['car'];
        
        const color = route.color || config.color;
        const dashArray = config.dashArray;

        const layer = L.geoJSON(route.geojson, {
            style: {
                color: color,
                weight: 4,
                opacity: 0.7,
                dashArray: dashArray
            },
            onEachFeature: function(feature, layer) {
                const popupContent = `
                    <div class="route-popup">
                        <strong>${config.icon} ${escapeHtml(trip.title)}</strong><br>
                        <small class="text-muted">Transporte: ${transportType}</small>
                    </div>
                `;
                layer.bindPopup(popupContent);
            }
        });

        layer.addTo(map);
        routesLayers[trip.id].push(layer);
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

        pointsClusters[trip.id].addLayer(marker);
    }

    /**
     * Crea el contenido HTML del popup de un punto
     */
    function createPointPopup(point, trip, typeConfig) {
        let html = '<div class="point-popup">';
        
        // Imagen si existe
        if (point.image_url) {
            html += `<img src="${point.image_url}" alt="${escapeHtml(point.title)}" class="popup-image">`;
        }
        
        // Contenido
        html += '<div class="popup-content">';
        
        // Título
        html += `<h6 class="popup-title">${escapeHtml(point.title)}</h6>`;
        
        // Badge del tipo
        html += `<span class="badge mb-2" style="background-color: ${typeConfig.color};">${typeConfig.emoji} ${typeConfig.label}</span>`;
        
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

        // Agregar rutas a los bounds
        Object.values(routesLayers).forEach(function(layers) {
            layers.forEach(function(layer) {
                bounds.extend(layer.getBounds());
                hasContent = true;
            });
        });

        // Agregar puntos a los bounds
        if (allPointsCluster.getLayers().length > 0) {
            bounds.extend(allPointsCluster.getBounds());
            hasContent = true;
        }

        if (hasContent) {
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
        // Mostrar rutas
        if (routesLayers[tripId]) {
            routesLayers[tripId].forEach(function(layer) {
                if (!map.hasLayer(layer)) {
                    layer.addTo(map);
                }
            });
        }

        // Mostrar puntos
        if (pointsClusters[tripId]) {
            pointsClusters[tripId].eachLayer(function(marker) {
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

        // Ocultar puntos
        if (pointsClusters[tripId]) {
            pointsClusters[tripId].eachLayer(function(marker) {
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
     * Inicialización cuando el DOM está listo
     */
    $(document).ready(function () {
        // Inicializar mapa
        initMap();

        // Cargar datos
        loadData();

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
        });

        // Seleccionar todos los viajes
        $('#selectAllTrips').on('click', function() {
            $('.trip-checkbox').prop('checked', true).trigger('change');
        });

        // Deseleccionar todos los viajes
        $('#deselectAllTrips').on('click', function() {
            $('.trip-checkbox').prop('checked', false).trigger('change');
        });

        console.log('Public Map inicializado completamente');
    });

})();
