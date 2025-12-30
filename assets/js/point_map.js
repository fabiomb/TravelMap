/**
 * Point Map - Mapa interactivo para seleccionar coordenadas de puntos
 * 
 * Permite seleccionar coordenadas haciendo clic en el mapa o arrastrando el marcador
 */

(function () {
    'use strict';

    let map;
    let marker;
    const defaultLat = 0;
    const defaultLng = 0;
    const defaultZoom = 2;

    /**
     * Inicializa el mapa
     */
    function initMap() {
        // Determinar coordenadas iniciales
        let lat = defaultLat;
        let lng = defaultLng;
        let zoom = defaultZoom;

        if (typeof initialLat !== 'undefined' && initialLat !== null && 
            typeof initialLng !== 'undefined' && initialLng !== null) {
            lat = parseFloat(initialLat);
            lng = parseFloat(initialLng);
            zoom = 13; // Zoom más cercano si ya hay coordenadas
        }

        // Crear el mapa
        map = L.map('pointMap').setView([lat, lng], zoom);

        // Capa base de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Si hay coordenadas iniciales, crear marcador
        if (lat !== defaultLat || lng !== defaultLng) {
            addMarker(lat, lng);
        }

        // Evento de clic en el mapa
        map.on('click', onMapClick);

        console.log('Point Map inicializado');
    }

    /**
     * Maneja el clic en el mapa
     */
    function onMapClick(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        // Si ya existe un marcador, removerlo
        if (marker) {
            map.removeLayer(marker);
        }

        // Agregar nuevo marcador
        addMarker(lat, lng);

        // Actualizar campos del formulario
        updateFormFields(lat, lng);
    }

    /**
     * Agrega un marcador arrastrable al mapa
     */
    function addMarker(lat, lng) {
        // Icono personalizado
        const icon = L.divIcon({
            className: 'custom-poi-marker',
            html: `<div style="background-color: #FF4444; color: white; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 3px 8px rgba(0,0,0,0.4); cursor: move;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="white" stroke-width="1.5"><circle cx="12" cy="6" r="4"/><path d="M5 16C3.7492 16.6327 3 17.4385 3 18.3158C3 20.3505 7.02944 22 12 22C16.9706 22 21 20.3505 21 18.3158C21 17.4385 20.2508 16.6327 19 16"/><path d="M12 10L12 18"/></svg></div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 36]
        });

        marker = L.marker([lat, lng], {
            icon: icon,
            draggable: true
        }).addTo(map);

        // Popup con coordenadas
        marker.bindPopup(`
            <strong>Ubicación seleccionada</strong><br>
            <small>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}</small>
        `).openPopup();

        // Evento al arrastrar el marcador
        marker.on('dragend', onMarkerDrag);
    }

    /**
     * Maneja el arrastre del marcador
     */
    function onMarkerDrag(e) {
        const position = marker.getLatLng();
        const lat = position.lat;
        const lng = position.lng;

        // Actualizar popup
        marker.setPopupContent(`
            <strong>Ubicación seleccionada</strong><br>
            <small>Lat: ${lat.toFixed(6)}<br>Lng: ${lng.toFixed(6)}</small>
        `);

        // Actualizar campos del formulario
        updateFormFields(lat, lng);

        console.log('Marcador arrastrado:', lat, lng);
    }

    /**
     * Actualiza los campos de latitud y longitud del formulario
     */
    function updateFormFields(lat, lng) {
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');

        if (latInput && lngInput) {
            latInput.value = lat.toFixed(6);
            lngInput.value = lng.toFixed(6);

            // Remover clases de error si existen
            latInput.classList.remove('is-invalid');
            lngInput.classList.remove('is-invalid');

            // Agregar efecto visual de actualización
            latInput.classList.add('bg-success-subtle');
            lngInput.classList.add('bg-success-subtle');

            setTimeout(() => {
                latInput.classList.remove('bg-success-subtle');
                lngInput.classList.remove('bg-success-subtle');
            }, 500);
        }

        console.log('Coordenadas actualizadas:', lat, lng);
    }

    /**
     * Sincroniza el mapa cuando se cambian manualmente las coordenadas
     */
    function syncMapFromInputs() {
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');

        if (!latInput || !lngInput) {
            return;
        }

        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);

        // Validar que sean números válidos
        if (isNaN(lat) || isNaN(lng)) {
            return;
        }

        // Validar rangos
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            return;
        }

        // Remover marcador anterior
        if (marker) {
            map.removeLayer(marker);
        }

        // Agregar nuevo marcador
        addMarker(lat, lng);

        // Centrar el mapa
        map.setView([lat, lng], 13);

        console.log('Mapa sincronizado desde inputs:', lat, lng);
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
     * Centra el mapa en un lugar y coloca el marcador
     */
    function goToPlace(lat, lng, name) {
        // Remover marcador anterior
        if (marker) {
            map.removeLayer(marker);
        }

        // Agregar nuevo marcador
        addMarker(lat, lng);

        // Centrar el mapa
        map.setView([lat, lng], 15);

        // Actualizar campos del formulario
        updateFormFields(lat, lng);

        // Ocultar resultados
        $('#searchResults').hide();
        $('#placeSearch').val('');

        console.log('Navegado a:', name, lat, lng);
    }

    /**
     * Inicialización cuando el DOM está listo
     */
    $(document).ready(function () {
        // Inicializar mapa
        initMap();

        // Botón de búsqueda
        $('#searchBtn').on('click', function() {
            const query = $('#placeSearch').val();
            searchPlace(query);
        });

        // Búsqueda al presionar Enter
        $('#placeSearch').on('keypress', function(e) {
            if (e.which === 13) { // Enter
                e.preventDefault();
                const query = $(this).val();
                searchPlace(query);
            }
        });

        // Ocultar resultados al hacer clic fuera
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#placeSearch, #searchResults, #searchBtn').length) {
                $('#searchResults').hide();
            }
        });

        // Sincronizar mapa cuando cambian los inputs de coordenadas
        $('#latitude, #longitude').on('blur', syncMapFromInputs);

        // También sincronizar al presionar Enter
        $('#latitude, #longitude').on('keypress', function (e) {
            if (e.which === 13) { // Enter
                e.preventDefault();
                syncMapFromInputs();
            }
        });

        console.log('Point Map listeners configurados');
    });

})();
