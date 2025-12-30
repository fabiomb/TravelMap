/**
 * Trip Map Editor - Leaflet Integration
 * 
 * Gestiona el mapa interactivo para dibujar rutas de viaje
 */

(function () {
    'use strict';

    // Variables globales
    let map;
    let drawnItems;
    let drawControl;
    let pointMarkers = L.layerGroup();
    let routesData = [];
    let appConfig = null; // Configuración cargada desde el servidor

    // SVG icons for transport types
    const transportIcons = {
        plane: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/></svg>',
        car: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 15.4222V18.5C22 18.9659 22 19.1989 21.9239 19.3827C21.8224 19.6277 21.6277 19.8224 21.3827 19.9239C21.1989 20 20.9659 20 20.5 20C20.0341 20 19.8011 20 19.6173 19.9239C19.3723 19.8224 19.1776 19.6277 19.0761 19.3827C19 19.1989 19 18.9659 19 18.5C19 18.0341 19 17.8011 18.9239 17.6173C18.8224 17.3723 18.6277 17.1776 18.3827 17.0761C18.1989 17 17.9659 17 17.5 17H6.5C6.03406 17 5.80109 17 5.61732 17.0761C5.37229 17.1776 5.17761 17.3723 5.07612 17.6173C5 17.8011 5 18.0341 5 18.5C5 18.9659 5 19.1989 4.92388 19.3827C4.82239 19.6277 4.62771 19.8224 4.38268 19.9239C4.19891 20 3.96594 20 3.5 20C3.03406 20 2.80109 20 2.61732 19.9239C2.37229 19.8224 2.17761 19.6277 2.07612 19.3827C2 19.1989 2 18.9659 2 18.5V15.4222C2 14.22 2 13.6188 2.17163 13.052C2.34326 12.4851 2.67671 11.9849 3.3436 10.9846L4 10L4.96154 7.69231C5.70726 5.90257 6.08013 5.0077 6.8359 4.50385C7.59167 4 8.56112 4 10.5 4H13.5C15.4389 4 16.4083 4 17.1641 4.50385C17.9199 5.0077 18.2927 5.90257 19.0385 7.69231L20 10L20.6564 10.9846C21.3233 11.9849 21.6567 12.4851 21.8284 13.052C22 13.6188 22 14.22 22 15.4222Z"/><path d="M2 8.5L4 10L5.76114 10.4403C5.91978 10.4799 6.08269 10.5 6.24621 10.5H17.7538C17.9173 10.5 18.0802 10.4799 18.2389 10.4403L20 10L22 8.5"/><path d="M18 14V14.01"/><path d="M6 14V14.01"/></svg>',
        train: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 3H6.73259C9.34372 3 10.6493 3 11.8679 3.40119C13.0866 3.80239 14.1368 4.57795 16.2373 6.12907L19.9289 8.85517C19.9692 8.88495 19.9894 8.89984 20.0084 8.91416C21.2491 9.84877 21.985 11.307 21.9998 12.8603C22 12.8841 22 12.9091 22 12.9593C22 12.9971 22 13.016 21.9997 13.032C21.9825 14.1115 21.1115 14.9825 20.032 14.9997C20.016 15 19.9971 15 19.9593 15H2"/><path d="M2 11H6.095C8.68885 11 9.98577 11 11.1857 11.451C12.3856 11.9019 13.3983 12.77 15.4238 14.5061L16 15"/><path d="M10 7H17"/><path d="M2 19H22"/><path d="M18 19V21"/><path d="M12 19V21"/><path d="M6 19V21"/></svg>',
        ship: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 21.1932C2.68524 22.2443 3.57104 22.2443 4.27299 21.1932C6.52985 17.7408 8.67954 23.6764 10.273 21.2321C12.703 17.5694 14.4508 23.9218 16.273 21.1932C18.6492 17.5582 20.1295 23.5776 22 21.5842"/><path d="M3.57228 17L2.07481 12.6457C1.80373 11.8574 2.30283 11 3.03273 11H20.8582C23.9522 11 19.9943 17 17.9966 17"/><path d="M18 11L15.201 7.50122C14.4419 6.55236 13.2926 6 12.0775 6H8C6.89543 6 6 6.89543 6 8V11"/><path d="M10 6V3C10 2.44772 9.55228 2 9 2H8"/></svg>',
        walk: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12.5L7.73811 9.89287C7.91034 9.63452 8.14035 9.41983 8.40993 9.26578L10.599 8.01487C11.1619 7.69323 11.8483 7.67417 12.4282 7.9641C13.0851 8.29255 13.4658 8.98636 13.7461 9.66522C14.2069 10.7814 15.3984 12 18 12"/><path d="M13 9L11.7772 14.5951M10.5 8.5L9.77457 11.7645C9.6069 12.519 9.88897 13.3025 10.4991 13.777L14 16.5L15.5 21"/><path d="M9.5 16L9 17.5L6.5 20.5"/><path d="M15 4.5C15 5.32843 14.3284 6 13.5 6C12.6716 6 12 5.32843 12 4.5C12 3.67157 12.6716 3 13.5 3C14.3284 3 15 3.67157 15 4.5Z"/></svg>'
    };

    // Colores por tipo de transporte (valores por defecto)
    let transportColors = {
        'plane': '#FF4444',
        'car': '#4444FF',
        'train': '#FF8800',
        'ship': '#00AAAA',
        'walk': '#44FF44'
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
            console.log('Respuesta de configuración (trip_map):', response);
            
            if (response.success && response.data) {
                appConfig = response.data;
                
                // Actualizar colores de transporte con la configuración del servidor
                if (appConfig.transportColors) {
                    console.log('Colores recibidos (trip_map):', appConfig.transportColors);
                    
                    transportColors = {
                        'plane': appConfig.transportColors.plane || transportColors.plane,
                        'ship': appConfig.transportColors.ship || transportColors.ship,
                        'car': appConfig.transportColors.car || transportColors.car,
                        'train': appConfig.transportColors.train || transportColors.train,
                        'walk': appConfig.transportColors.walk || transportColors.walk
                    };
                    
                    console.log('transportColors actualizado (trip_map):', transportColors);
                }
                
                console.log('Configuración cargada en trip map:', appConfig);
            }
        }).fail(function(xhr, status, error) {
            console.error('Error al cargar configuración (trip_map):', error, xhr.responseText);
            console.warn('No se pudo cargar la configuración, usando valores por defecto');
        });
    }

    /**
     * Renderiza la leyenda de transporte con los colores configurados
     */
    function renderLegend() {
        const legendContainer = $('#transportLegend');
        
        if (legendContainer.length === 0) {
            console.warn('No se encontró el contenedor #transportLegend');
            return;
        }
        
        legendContainer.empty();
        
        // Orden de los tipos de transporte
        const transportOrder = [
            { type: 'plane', icon: transportIcons.plane, label: 'Avión' },
            { type: 'car', icon: transportIcons.car, label: 'Auto' },
            { type: 'train', icon: transportIcons.train, label: 'Tren' },
            { type: 'ship', icon: transportIcons.ship, label: 'Barco' },
            { type: 'walk', icon: transportIcons.walk, label: 'Caminata' }
        ];
        
        transportOrder.forEach(function(item) {
            const color = transportColors[item.type];
            
            const legendItem = $(`
                <div class="d-flex align-items-center mb-2">
                    <div style="width: 30px; height: 4px; background-color: ${color}; margin-right: 10px;"></div>
                    <small>${item.icon} ${item.label}</small>
                </div>
            `);
            
            legendContainer.append(legendItem);
        });
        
        console.log('Leyenda de trip editor renderizada');
    }

    /**
     * Inicializa el mapa
     */
    function initMap() {
        // Crear el mapa centrado en el mundo
        map = L.map('map').setView([20, 0], 2);

        // Capa base de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Grupo para las rutas dibujadas
        drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        // Grupo para los marcadores de puntos
        map.addLayer(pointMarkers);

        // Configurar controles de dibujo
        initDrawControls();

        // Cargar rutas existentes
        loadExistingRoutes();

        // Cargar puntos existentes
        loadExistingPoints();

        // Ajustar vista si hay contenido
        adjustMapView();
    }

    /**
     * Inicializa los controles de dibujo
     */
    function initDrawControls() {
        // Usar el color de 'car' de la configuración como color por defecto para dibujar
        const defaultDrawColor = transportColors['car'] || '#4444FF';
        
        drawControl = new L.Control.Draw({
            position: 'topright',
            draw: {
                polyline: {
                    shapeOptions: {
                        color: defaultDrawColor,
                        weight: 4,
                        opacity: 0.8
                    },
                    showLength: true,
                    metric: true,
                    feet: false
                },
                polygon: false,
                circle: false,
                circlemarker: false,
                marker: false,
                rectangle: false
            },
            edit: {
                featureGroup: drawnItems,
                remove: true
            }
        });

        map.addControl(drawControl);

        // Evento al crear una nueva ruta
        map.on(L.Draw.Event.CREATED, handleRouteCreated);

        // Evento al editar rutas
        map.on(L.Draw.Event.EDITED, handleRoutesEdited);

        // Evento al eliminar rutas
        map.on(L.Draw.Event.DELETED, handleRoutesDeleted);
    }

    /**
     * Maneja la creación de una nueva ruta
     */
    function handleRouteCreated(e) {
        const layer = e.layer;

        // Pedir tipo de transporte
        const transportType = promptTransportType();

        if (!transportType) {
            return; // Usuario canceló
        }

        // Asignar color según el transporte
        const color = transportColors[transportType];
        layer.setStyle({
            color: color,
            weight: 4,
            opacity: 0.8
        });

        // Agregar la capa al grupo
        drawnItems.addLayer(layer);

        // Guardar metadata en la capa
        layer.transportType = transportType;
        layer.color = color;

        // Actualizar datos
        updateRoutesData();

        console.log('Ruta creada:', transportType, layer.toGeoJSON());
    }

    /**
     * Maneja la edición de rutas
     */
    function handleRoutesEdited(e) {
        updateRoutesData();
        console.log('Rutas editadas');
    }

    /**
     * Maneja la eliminación de rutas
     */
    function handleRoutesDeleted(e) {
        updateRoutesData();
        console.log('Rutas eliminadas');
    }

    /**
     * Pide al usuario el tipo de transporte
     */
    function promptTransportType() {
        const options = Object.keys(transportTypes);
        let message = 'Selecciona el tipo de transporte:\n\n';

        options.forEach((key, index) => {
            message += `${index + 1}. ${transportTypes[key]}\n`;
        });

        message += '\nIngresa el número (1-' + options.length + '):';

        const input = prompt(message);

        if (!input) {
            return null;
        }

        const index = parseInt(input) - 1;

        if (index >= 0 && index < options.length) {
            return options[index];
        }

        alert('Opción no válida. Seleccionando Auto por defecto.');
        return 'car';
    }

    /**
     * Actualiza el array de datos de rutas
     */
    function updateRoutesData() {
        routesData = [];

        drawnItems.eachLayer(function (layer) {
            const geojson = layer.toGeoJSON();

            routesData.push({
                transport_type: layer.transportType || 'car',
                color: layer.color || transportColors['car'],
                geojson: geojson
            });
        });

        // Actualizar input hidden
        document.getElementById('routes_data').value = JSON.stringify(routesData);

        console.log('Rutas actualizadas:', routesData.length);
    }

    /**
     * Carga las rutas existentes desde el servidor
     */
    function loadExistingRoutes() {
        if (!existingRoutes || existingRoutes.length === 0) {
            console.log('No hay rutas existentes');
            return;
        }

        existingRoutes.forEach(function (route) {
            const geojson = route.geojson;
            const transportType = route.transport_type || 'car';
            
            // Priorizar color de configuración sobre color guardado en BD
            const color = transportColors[transportType] || route.color;
            
            const layer = L.geoJSON(geojson, {
                style: {
                    color: color,
                    weight: 4,
                    opacity: 0.8
                }
            });

            // Agregar metadata
            layer.eachLayer(function (l) {
                l.transportType = transportType;
                l.color = color;
                drawnItems.addLayer(l);
            });
        });

        // Actualizar datos
        updateRoutesData();

        console.log('Rutas cargadas:', existingRoutes.length);
    }

    /**
     * Carga los puntos de interés existentes
     */
    function loadExistingPoints() {
        if (!existingPoints || existingPoints.length === 0) {
            console.log('No hay puntos existentes');
            return;
        }

        existingPoints.forEach(function (point) {
            // Icono personalizado
            const icon = L.divIcon({
                className: 'custom-poi-marker',
                html: `<div style="background-color: #FF4444; color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); font-weight: bold;">📍</div>`,
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });

            const marker = L.marker([point.latitude, point.longitude], {
                icon: icon,
                title: point.title
            });

            marker.bindPopup(`
                <strong>${point.title}</strong><br>
                <small class="text-muted">${point.type}</small><br>
                <small>Lat: ${point.latitude}, Lng: ${point.longitude}</small>
            `);

            pointMarkers.addLayer(marker);
        });

        console.log('Puntos cargados:', existingPoints.length);
    }

    /**
     * Ajusta la vista del mapa para mostrar todo el contenido
     */
    function adjustMapView() {
        const allLayers = [];

        // Agregar rutas
        drawnItems.eachLayer(function (layer) {
            allLayers.push(layer);
        });

        // Agregar puntos
        pointMarkers.eachLayer(function (layer) {
            allLayers.push(layer);
        });

        if (allLayers.length > 0) {
            const group = new L.featureGroup(allLayers);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    }

    /**
     * Limpia todas las rutas
     */
    function clearAllRoutes() {
        if (!confirm('¿Estás seguro de que quieres eliminar todas las rutas?')) {
            return;
        }

        drawnItems.clearLayers();
        updateRoutesData();
    }

    /**
     * Maneja el envío del formulario
     */
    function handleFormSubmit(e) {
        // Actualizar datos antes de enviar
        updateRoutesData();

        if (routesData.length === 0) {
            if (!confirm('No has dibujado ninguna ruta. ¿Deseas continuar y eliminar todas las rutas existentes?')) {
                e.preventDefault();
                return false;
            }
        }

        return true;
    }

    /**
     * Busca lugares usando Nominatim (OpenStreetMap)
     */
    function searchPlace(query) {
        if (!query || query.trim().length < 3) {
            alert('Por favor, ingresa al menos 3 caracteres para buscar');
            return;
        }

        const searchResults = $('#searchResults');
        searchResults.html('<div class="list-group-item"><div class="spinner-border spinner-border-sm me-2"></div>Buscando...</div>');
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
                    searchResults.html('<div class="list-group-item text-muted">No se encontraron resultados</div>');
                    return;
                }

                if (results.error) {
                    searchResults.html(`<div class="list-group-item text-danger">${results.error}</div>`);
                    return;
                }

                results.forEach(function(place) {
                    const displayName = place.display_name;
                    const lat = parseFloat(place.lat);
                    const lon = parseFloat(place.lon);
                    
                    const item = $(`
                        <button type="button" class="list-group-item list-group-item-action" data-lat="${lat}" data-lon="${lon}">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-geo-alt-fill me-1" viewBox="0 0 16 16">
                                        <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/>
                                    </svg>
                                    ${place.name || place.type}
                                </h6>
                            </div>
                            <p class="mb-1 small text-muted">${displayName}</p>
                        </button>
                    `);

                    item.on('click', function() {
                        const lat = parseFloat($(this).data('lat'));
                        const lon = parseFloat($(this).data('lon'));
                        goToPlace(lat, lon, displayName);
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
                
                searchResults.html(`<div class="list-group-item text-danger">${errorMsg}</div>`);
            }
        });
    }

    /**
     * Navega a un lugar específico en el mapa
     */
    function goToPlace(lat, lon, name) {
        // Centrar mapa en el lugar
        map.setView([lat, lon], 13);

        // Ocultar resultados
        $('#searchResults').hide().empty();

        // Limpiar input
        $('#placeSearch').val('');

        // Mostrar marcador temporal
        const tempMarker = L.marker([lat, lon], {
            icon: L.divIcon({
                className: 'temp-search-marker',
                html: `<div style="background-color: #0066FF; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3); font-size: 16px;">📍</div>`,
                iconSize: [30, 30],
                iconAnchor: [15, 30]
            })
        }).addTo(map);

        tempMarker.bindPopup(`
            <strong>${name}</strong><br>
            <small>Lat: ${lat.toFixed(6)}, Lon: ${lon.toFixed(6)}</small>
        `).openPopup();

        // Remover marcador después de 10 segundos
        setTimeout(() => {
            map.removeLayer(tempMarker);
        }, 10000);

        console.log('Navegado a:', name, lat, lon);
    }

    /**
     * Inicialización cuando el DOM está listo
     */
    $(document).ready(function () {
        // Cargar configuración primero, luego inicializar el mapa
        loadConfig().always(function() {
            // Inicializar mapa con la configuración cargada
            initMap();
            
            // Renderizar leyenda con colores configurados
            renderLegend();

            // Configurar event handlers
            setupEventHandlers();
        });
    });

    /**
     * Configuración de event handlers
     */
    function setupEventHandlers() {
        // Evento para limpiar todas las rutas
        $('#clearAllRoutes').on('click', clearAllRoutes);

        // Evento para el formulario
        $('#routesForm').on('submit', handleFormSubmit);

        // Eventos del buscador
        $('#searchBtn').on('click', function() {
            const query = $('#placeSearch').val();
            searchPlace(query);
        });

        $('#placeSearch').on('keypress', function(e) {
            if (e.which === 13) { // Enter
                e.preventDefault();
                const query = $(this).val();
                searchPlace(query);
            }
        });

        // Cerrar resultados al hacer clic fuera
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#placeSearch, #searchBtn, #searchResults').length) {
                $('#searchResults').hide();
            }
        });

        console.log('Trip Map Editor inicializado');
    }

})();
