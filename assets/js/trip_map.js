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
    let pointMarkers = null; // Se inicializa en initMap()
    let routesData = [];
    let appConfig = null; // Configuración cargada desde el servidor

    // SVG icons for transport types
    const transportIcons = {
        plane: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/></svg>',
        car: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 15.4222V18.5C22 18.9659 22 19.1989 21.9239 19.3827C21.8224 19.6277 21.6277 19.8224 21.3827 19.9239C21.1989 20 20.9659 20 20.5 20C20.0341 20 19.8011 20 19.6173 19.9239C19.3723 19.8224 19.1776 19.6277 19.0761 19.3827C19 19.1989 19 18.9659 19 18.5C19 18.0341 19 17.8011 18.9239 17.6173C18.8224 17.3723 18.6277 17.1776 18.3827 17.0761C18.1989 17 17.9659 17 17.5 17H6.5C6.03406 17 5.80109 17 5.61732 17.0761C5.37229 17.1776 5.17761 17.3723 5.07612 17.6173C5 17.8011 5 18.0341 5 18.5C5 18.9659 5 19.1989 4.92388 19.3827C4.82239 19.6277 4.62771 19.8224 4.38268 19.9239C4.19891 20 3.96594 20 3.5 20C3.03406 20 2.80109 20 2.61732 19.9239C2.37229 19.8224 2.17761 19.6277 2.07612 19.3827C2 19.1989 2 18.9659 2 18.5V15.4222C2 14.22 2 13.6188 2.17163 13.052C2.34326 12.4851 2.67671 11.9849 3.3436 10.9846L4 10L4.96154 7.69231C5.70726 5.90257 6.08013 5.0077 6.8359 4.50385C7.59167 4 8.56112 4 10.5 4H13.5C15.4389 4 16.4083 4 17.1641 4.50385C17.9199 5.0077 18.2927 5.90257 19.0385 7.69231L20 10L20.6564 10.9846C21.3233 11.9849 21.6567 12.4851 21.8284 13.052C22 13.6188 22 14.22 22 15.4222Z"/><path d="M2 8.5L4 10L5.76114 10.4403C5.91978 10.4799 6.08269 10.5 6.24621 10.5H17.7538C17.9173 10.5 18.0802 10.4799 18.2389 10.4403L20 10L22 8.5"/><path d="M18 14V14.01"/><path d="M6 14V14.01"/></svg>',
        bike: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"><g transform="translate(3 0)"><path d="m8.632 15.526c-1.162.003-2.102.944-2.106 2.105v4.264.041c0 1.163.943 2.106 2.106 2.106s2.106-.943 2.106-2.106c0-.014 0-.029 0-.043v.002-4.263c-.003-1.161-.944-2.102-2.104-2.106z"></path><path d="m16.263 2.631h-4.053c-.491-1.537-1.907-2.631-3.579-2.631s-3.087 1.094-3.571 2.604l-.007.027h-4c-.581 0-1.053.471-1.053 1.053s.471 1.053 1.053 1.053h4.053c.268.899.85 1.635 1.615 2.096l.016.009c-2.871.867-4.929 3.48-4.947 6.577v5.528c.009.956.781 1.728 1.736 1.737h1.422v-3c0-2.064 1.673-3.737 3.737-3.737s3.737 1.673 3.737 3.737v3h1.421c.957-.008 1.73-.781 1.738-1.737v-5.474c-.001-3.105-2.067-5.726-4.899-6.567l-.048-.012c.782-.471 1.363-1.206 1.625-2.08l.007-.026h4.053c.581-.002 1.051-.472 1.053-1.053-.023-.601-.505-1.083-1.104-1.105h-.002z"></path><path d="m8.631 5.84c-1.163 0-2.106-.943-2.106-2.106s.943-2.106 2.106-2.106 2.106.943 2.106 2.106c.001.018.001.039.001.06 0 1.13-.916 2.046-2.046 2.046-.021 0-.042 0-.063-.001h.003z"></path></g></svg>',
        train: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 3H6.73259C9.34372 3 10.6493 3 11.8679 3.40119C13.0866 3.80239 14.1368 4.57795 16.2373 6.12907L19.9289 8.85517C19.9692 8.88495 19.9894 8.89984 20.0084 8.91416C21.2491 9.84877 21.985 11.307 21.9998 12.8603C22 12.8841 22 12.9091 22 12.9593C22 12.9971 22 13.016 21.9997 13.032C21.9825 14.1115 21.1115 14.9825 20.032 14.9997C20.016 15 19.9971 15 19.9593 15H2"/><path d="M2 11H6.095C8.68885 11 9.98577 11 11.1857 11.451C12.3856 11.9019 13.3983 12.77 15.4238 14.5061L16 15"/><path d="M10 7H17"/><path d="M2 19H22"/><path d="M18 19V21"/><path d="M12 19V21"/><path d="M6 19V21"/></svg>',
        ship: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 21.1932C2.68524 22.2443 3.57104 22.2443 4.27299 21.1932C6.52985 17.7408 8.67954 23.6764 10.273 21.2321C12.703 17.5694 14.4508 23.9218 16.273 21.1932C18.6492 17.5582 20.1295 23.5776 22 21.5842"/><path d="M3.57228 17L2.07481 12.6457C1.80373 11.8574 2.30283 11 3.03273 11H20.8582C23.9522 11 19.9943 17 17.9966 17"/><path d="M18 11L15.201 7.50122C14.4419 6.55236 13.2926 6 12.0775 6H8C6.89543 6 6 6.89543 6 8V11"/><path d="M10 6V3C10 2.44772 9.55228 2 9 2H8"/></svg>',
        walk: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12.5L7.73811 9.89287C7.91034 9.63452 8.14035 9.41983 8.40993 9.26578L10.599 8.01487C11.1619 7.69323 11.8483 7.67417 12.4282 7.9641C13.0851 8.29255 13.4658 8.98636 13.7461 9.66522C14.2069 10.7814 15.3984 12 18 12"/><path d="M13 9L11.7772 14.5951M10.5 8.5L9.77457 11.7645C9.6069 12.519 9.88897 13.3025 10.4991 13.777L14 16.5L15.5 21"/><path d="M9.5 16L9 17.5L6.5 20.5"/><path d="M15 4.5C15 5.32843 14.3284 6 13.5 6C12.6716 6 12 5.32843 12 4.5C12 3.67157 12.6716 3 13.5 3C14.3284 3 15 3.67157 15 4.5Z"/></svg>',
        bus: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.00391 10V5M11.0039 10V5M16.0039 10V5.5"/><path d="M5.01609 17C3.59614 17 2.88616 17 2.44503 16.5607C2.00391 16.1213 2.00391 15.4142 2.00391 14V8C2.00391 6.58579 2.00391 5.87868 2.44503 5.43934C2.88616 5 3.59614 5 5.01609 5H12.1005C15.5742 5 17.311 5 18.6402 5.70624C19.619 6.22633 20.4346 7.0055 20.9971 7.95786C21.7609 9.25111 21.8332 10.9794 21.9779 14.436C22.0168 15.3678 22.0363 15.8337 21.8542 16.1862C21.7204 16.4454 21.5135 16.6601 21.2591 16.8041C20.913 17 20.4449 17 19.5085 17H19.0039M9.00391 17H15.0039"/><path d="M7.00391 19C8.10848 19 9.00391 18.1046 9.00391 17C9.00391 15.8954 8.10848 15 7.00391 15C5.89934 15 5.00391 15.8954 5.00391 17C5.00391 18.1046 5.89934 19 7.00391 19Z"/><path d="M17.0039 19C18.1085 19 19.0039 18.1046 19.0039 17C19.0039 15.8954 18.1085 15 17.0039 15C15.8993 15 15.0039 15.8954 15.0039 17C15.0039 18.1046 15.8993 19 17.0039 19Z"/><path d="M1.99609 10.0009H15.3641C15.9911 10.0009 16.2041 10.3681 16.6841 10.9441C17.2361 11.4841 17.6093 11.8628 18.1241 11.9401C18.8441 12.0481 21.5081 11.9941 21.5081 11.9941"/></svg>',
        aerial: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 8.93333C20 14 14.4615 18 12 18C9.53846 18 4 14 4 8.93333C4 5.10416 7.58172 2 12 2C16.4183 2 20 5.10416 20 8.93333Z"/><path d="M15 8.93333C15 14 12.9231 18 12 18C11.0769 18 9 14 9 8.93333C9 5.10416 10.3431 2 12 2C13.6569 2 15 5.10416 15 8.93333Z"/><path d="M9 20C9 19.535 9 19.3025 9.05111 19.1118C9.18981 18.5941 9.59413 18.1898 10.1118 18.0511C10.3025 18 10.535 18 11 18H13C13.465 18 13.6975 18 13.8882 18.0511C14.4059 18.1898 14.8102 18.5941 14.9489 19.1118C15 19.3025 15 19.535 15 20C15 20.465 15 20.6975 14.9489 20.8882C14.8102 21.4059 14.4059 21.8102 13.8882 21.9489C13.6975 22 13.465 22 13 22H11C10.535 22 10.3025 22 10.1118 21.9489C9.59413 21.8102 9.18981 21.4059 9.05111 20.8882C9 20.6975 9 20.465 9 20Z"/></svg>'
    };

    // Colores por tipo de transporte (valores por defecto)
    let transportColors = {
        'plane': '#FF4444',
        'car': '#4444FF',
        'bike': '#b88907',
        'train': '#FF8800',
        'ship': '#00AAAA',
        'walk': '#44FF44',
        'bus': '#9C27B0',
        'aerial': '#E91E63'
    };

    /**
     * Carga la configuración desde el servidor
     */
    function loadConfig() {
        return $.ajax({
            url: BASE_URL + '/api/get_config.php',
            method: 'GET',
            dataType: 'json'
        }).done(function (response) {
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
                        'bike': appConfig.transportColors.bike || transportColors.bike,
                        'train': appConfig.transportColors.train || transportColors.train,
                        'walk': appConfig.transportColors.walk || transportColors.walk,
                        'bus': appConfig.transportColors.bus || transportColors.bus,
                        'aerial': appConfig.transportColors.aerial || transportColors.aerial
                    };

                    console.log('transportColors actualizado (trip_map):', transportColors);
                }

                console.log('Configuración cargada en trip map:', appConfig);
            }
        }).fail(function (xhr, status, error) {
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

        // Orden de los tipos de transporte (use transportTypes from PHP if available)
        const transportOrder = [
            { type: 'plane', icon: transportIcons.plane, label: (typeof transportTypes !== 'undefined' && transportTypes.plane) || 'Avión' },
            { type: 'car', icon: transportIcons.car, label: (typeof transportTypes !== 'undefined' && transportTypes.car) || 'Auto' },
            { type: 'bike', icon: transportIcons.bike, label: (typeof transportTypes !== 'undefined' && transportTypes.bike) || 'Bicicleta' },
            { type: 'train', icon: transportIcons.train, label: (typeof transportTypes !== 'undefined' && transportTypes.train) || 'Tren' },
            { type: 'ship', icon: transportIcons.ship, label: (typeof transportTypes !== 'undefined' && transportTypes.ship) || 'Barco' },
            { type: 'walk', icon: transportIcons.walk, label: (typeof transportTypes !== 'undefined' && transportTypes.walk) || 'Caminata' },
            { type: 'bus', icon: transportIcons.bus, label: (typeof transportTypes !== 'undefined' && transportTypes.bus) || 'Bus' },
            { type: 'aerial', icon: transportIcons.aerial, label: (typeof transportTypes !== 'undefined' && transportTypes.aerial) || 'Aéreo' }
        ];

        transportOrder.forEach(function (item) {
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
     * Renderiza la lista de rutas con controles para editar
     */
    function renderRoutesList() {
        const routesListContainer = $('#routesList');
        const routesListCard = $('#routesListCard');
        const routesCount = $('#routesCount');

        if (routesListContainer.length === 0) return;

        routesListContainer.empty();
        const totalRoutes = routesData.length;
        routesCount.text(`(${totalRoutes})`);

        if (totalRoutes === 0) {
            routesListCard.hide();
            return;
        }

        routesListCard.show();

        // Create table layout for better horizontal display
        const table = $(`
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">#</th>
                            <th style="width: 60px; text-align: center;"></th>
                            <th>${__('routes.transport_type') || 'Transport Type'}</th>
                            <th style="width: 80px; text-align: center;">${__('routes.is_round_trip') || 'Round Trip'}</th>
                            <th style="width: 100px; text-align: center;">${__('routes.distance') || 'Distance'}</th>
                            <th style="width: 100px; text-align: center;">${__('trips.actions') || 'Actions'}</th>
                        </tr>
                    </thead>
                    <tbody id="routesTableBody"></tbody>
                </table>
            </div>
        `);

        routesListContainer.append(table);
        const tbody = $('#routesTableBody');

        routesData.forEach(function (route, index) {
            const color = route.color || transportColors[route.transport_type];
            const icon = transportIcons[route.transport_type] || '';

            // Build transport type selector
            const transportOrder = ['plane', 'car', 'bike', 'train', 'ship', 'walk', 'bus', 'aerial'];
            let optionsHtml = '';
            transportOrder.forEach(function (type) {
                const typeLabel = (typeof transportTypes !== 'undefined' && transportTypes[type]) || type;
                const selected = type === route.transport_type ? 'selected' : '';
                optionsHtml += `<option value="${type}" ${selected}>${typeLabel}</option>`;
            });

            const row = $(`
                <tr data-route-index="${index}" style="cursor: pointer;" title="${__('map.focus_route')}">
                    <td class="text-center align-middle">
                        <div style="width: 30px; height: 4px; background-color: ${color}; margin: 0 auto;"></div>
                    </td>
                    <td class="text-center align-middle">${icon}</td>
                    <td class="align-middle">
                        <select class="form-select form-select-sm transport-type-selector" data-route-index="${index}">
                            ${optionsHtml}
                        </select>
                    </td>
                    <td class="text-center align-middle">
                        <div class="form-check d-inline-block">
                            <input class="form-check-input round-trip-checkbox" type="checkbox" data-route-index="${index}" ${route.is_round_trip ? 'checked' : ''}>
                        </div>
                    </td>
                    <td class="text-center align-middle small">
                        ${formatDistanceDisplay(route.distance_meters, route.transport_type, route.is_round_trip)}
                    </td>
                    <td class="text-center align-middle">
                        <button class="btn btn-sm btn-outline-danger delete-route-btn" data-route-index="${index}" title="${__('map.delete_route')}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                            </svg>
                        </button>
                    </td>
                </tr>
            `);

            tbody.append(row);
        });

        // Attach event handlers
        $('.transport-type-selector').off('change').on('change', function () {
            const index = parseInt($(this).data('route-index'));
            const newType = $(this).val();
            changeRouteTransportType(index, newType);
        });

        $('.delete-route-btn').off('click').on('click', function () {
            const index = parseInt($(this).data('route-index'));
            deleteRoute(index);
        });

        $('.round-trip-checkbox').off('change').on('change', function () {
            const index = parseInt($(this).data('route-index'));
            const isRoundTrip = $(this).is(':checked');
            toggleRoundTrip(index, isRoundTrip);
        });

        // Highlight route on hover
        $('tr[data-route-index]').off('mouseenter mouseleave').hover(
            function () {
                const index = parseInt($(this).data('route-index'));
                highlightRoute(index, true);
            },
            function () {
                const index = parseInt($(this).data('route-index'));
                highlightRoute(index, false);
            }
        );

        // Click to focus on route
        $('tr[data-route-index]').off('click').on('click', function (e) {
            // Don't trigger if clicking on select or button
            if ($(e.target).closest('select, button').length > 0) return;

            const index = parseInt($(this).data('route-index'));
            focusOnRoute(index);
        });
    }

    /**
     * Destaca visualmente una ruta en el mapa
     */
    function highlightRoute(index, highlight) {
        if (index < 0 || index >= routesData.length) return;

        const route = routesData[index];
        if (!route.layer) return;

        if (highlight) {
            // Highlight: increase width and opacity
            route.layer.setStyle({
                weight: 8,
                opacity: 1
            });
            route.layer.bringToFront();
        } else {
            // Reset to normal
            route.layer.setStyle({
                weight: 4,
                opacity: 0.8
            });
        }
    }

    /**
     * Enfoca el mapa en una ruta específica
     */
    function focusOnRoute(index) {
        if (index < 0 || index >= routesData.length) return;

        const route = routesData[index];
        if (!route.layer) return;

        // Fit map to route bounds
        try {
            const bounds = route.layer.getBounds();
            map.fitBounds(bounds, {
                padding: [50, 50],
                maxZoom: 12
            });

            // Flash the route
            let flashCount = 0;
            const flashInterval = setInterval(function () {
                if (flashCount >= 4) {
                    clearInterval(flashInterval);
                    route.layer.setStyle({
                        weight: 4,
                        opacity: 0.8
                    });
                    return;
                }

                const isVisible = flashCount % 2 === 0;
                route.layer.setStyle({
                    weight: isVisible ? 8 : 4,
                    opacity: isVisible ? 1 : 0.3
                });

                flashCount++;
            }, 200);
        } catch (e) {
            console.warn('No se pudo enfocar en la ruta:', e);
        }
    }

    /**
     * Cambia el tipo de transporte de una ruta
     */
    function changeRouteTransportType(index, newType) {
        if (index < 0 || index >= routesData.length) return;

        const route = routesData[index];
        route.transport_type = newType;
        route.color = transportColors[newType];

        // Update the layer on the map
        if (route.layer) {
            route.layer.setStyle({ color: route.color });
            route.layer.transportType = newType;
            route.layer.color = route.color;
        }

        // Update the hidden form field with new data
        updateRoutesData();

        console.log(`Route ${index} transport type changed to ${newType}`);
    }

    /**
     * Alterna si una ruta es de ida y vuelta
     */
    function toggleRoundTrip(index, isRoundTrip) {
        if (index < 0 || index >= routesData.length) return;

        const route = routesData[index];
        route.is_round_trip = isRoundTrip;

        if (route.layer) {
            route.layer.isRoundTrip = isRoundTrip;
        }

        // Update the hidden form field
        updateRoutesData();

        console.log(`Route ${index} round trip set to ${isRoundTrip}`);
    }

    /**
     * Elimina una ruta específica
     */
    function deleteRoute(index) {
        if (index < 0 || index >= routesData.length) return;

        const route = routesData[index];

        // Remove from map
        if (route.layer && drawnItems.hasLayer(route.layer)) {
            drawnItems.removeLayer(route.layer);
        }

        // Remove from routesData
        routesData.splice(index, 1);

        // Re-render the list
        renderRoutesList();

        console.log(`Route ${index} deleted`);
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
        pointMarkers = L.layerGroup();
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
        const distanceMeters = calculateLayerDistance(layer);

        // Pedir tipo de transporte
        const transportType = promptTransportType(distanceMeters);

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
        layer.isRoundTrip = true; // Por defecto es round trip
        layer.distanceMeters = distanceMeters;

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
    function promptTransportType(distanceMeters) {
        const options = Object.keys(transportTypes);
        let message = '';

        if (distanceMeters > 0) {
            const formatted = formatDistanceDisplay(distanceMeters, 'car', false).replace(' · ', '');
            message += `${__('routes.detected_distance') || 'Distancia detectada'}: ${formatted}\n\n`;
        }

        message += `${__('map.instruction_select_transport') || 'Selecciona el tipo de transporte'}:\n\n`;

        options.forEach((key, index) => {
            message += `${index + 1}. ${transportTypes[key]}\n`;
        });

        message += `\n${__('routes.enter_transport_number') || 'Ingresa el número'} (1-' + options.length + '):`;

        const input = prompt(message);

        if (!input) {
            return null;
        }

        const index = parseInt(input) - 1;

        if (index >= 0 && index < options.length) {
            return options[index];
        }

        alert(__('routes.invalid_option_default') || 'Opción no válida. Seleccionando Auto por defecto.');
        return 'car';
    }

    /**
     * Actualiza el array de datos de rutas
     */
    function updateRoutesData() {
        routesData = [];

        drawnItems.eachLayer(function (layer) {
            const geojson = layer.toGeoJSON();
            const distance = calculateLayerDistance(layer);

            // Adjust distance if it's round trip
            const distanceForInfo = layer.isRoundTrip ? distance * 2 : distance;

            routesData.push({
                transport_type: layer.transportType || 'car',
                color: layer.color || transportColors['car'],
                is_round_trip: layer.isRoundTrip || false,
                distance_meters: distanceForInfo,
                geojson: geojson,
                layer: layer  // Keep reference to the layer
            });
        });

        // Actualizar input hidden (sin las referencias a layers)
        const routesDataForSave = routesData.map(route => ({
            transport_type: route.transport_type,
            color: route.color,
            is_round_trip: route.is_round_trip ? 1 : 0,
            geojson: route.geojson
        }));
        document.getElementById('routes_data').value = JSON.stringify(routesDataForSave);

        // Update routes list UI
        renderRoutesList();

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
                l.isRoundTrip = !!route.is_round_trip;
                l.distanceMeters = route.distance_meters;
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
                html: `<div style="background-color: #FF4444; color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="white" stroke-width="1.5"><circle cx="12" cy="6" r="4"/><path d="M5 16C3.7492 16.6327 3 17.4385 3 18.3158C3 20.3505 7.02944 22 12 22C16.9706 22 21 20.3505 21 18.3158C21 17.4385 20.2508 16.6327 19 16"/><path d="M12 10L12 18"/></svg></div>`,
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
        if (!confirm(__('map.confirm_delete_all'))) {
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
            if (!confirm(__('map.confirm_no_routes'))) {
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
            alert(__('map.search_min_chars'));
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
            success: function (results) {
                searchResults.empty();

                if (!results || results.length === 0) {
                    searchResults.html('<div class="list-group-item text-muted">No se encontraron resultados</div>');
                    return;
                }

                if (results.error) {
                    searchResults.html(`<div class="list-group-item text-danger">${results.error}</div>`);
                    return;
                }

                results.forEach(function (place) {
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

                    item.on('click', function () {
                        const lat = parseFloat($(this).data('lat'));
                        const lon = parseFloat($(this).data('lon'));
                        goToPlace(lat, lon, displayName);
                    });

                    searchResults.append(item);
                });
            },
            error: function (xhr, status, error) {
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
                html: `<div style="background-color: #0066FF; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="white" stroke-width="1.5"><circle cx="12" cy="6" r="4"/><path d="M5 16C3.7492 16.6327 3 17.4385 3 18.3158C3 20.3505 7.02944 22 12 22C16.9706 22 21 20.3505 21 18.3158C21 17.4385 20.2508 16.6327 19 16"/><path d="M12 10L12 18"/></svg></div>`,
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
     * Calcula la distancia de una capa (polyline)
     */
    function calculateLayerDistance(layer) {
        if (!layer || typeof layer.getLatLngs !== 'function') return 0;

        const latLngs = layer.getLatLngs();
        let totalDistance = 0;

        for (let i = 0; i < latLngs.length - 1; i++) {
            totalDistance += haversineDistance(
                latLngs[i].lat, latLngs[i].lng,
                latLngs[i + 1].lat, latLngs[i + 1].lng
            );
        }

        return totalDistance;
    }

    /**
     * Calcula la distancia entre dos puntos (fórmula de Haversine)
     */
    function haversineDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000; // Radio de la Tierra en metros
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    /**
     * Formatea la distancia para mostrar en la UI
     */
    function formatDistanceDisplay(meters, transportType, isRoundTrip) {
        if (!meters || meters <= 0) return '0 km';

        // Usar la unidad configurada si está disponible
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

        const roundTripIcon = isRoundTrip ? ` <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ms-1 text-warning" style="vertical-align: text-bottom;"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></svg>` : '';
        return `${Math.round(value).toLocaleString()} ${label}${roundTripIcon}`;
    }

    /**
     * Inicialización cuando el DOM está listo
     */
    $(document).ready(function () {
        // Cargar configuración primero, luego inicializar el mapa
        loadConfig().always(function () {
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
        $('#searchBtn').on('click', function () {
            const query = $('#placeSearch').val();
            searchPlace(query);
        });

        $('#placeSearch').on('keypress', function (e) {
            if (e.which === 13) { // Enter
                e.preventDefault();
                const query = $(this).val();
                searchPlace(query);
            }
        });

        // Cerrar resultados al hacer clic fuera
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#placeSearch, #searchBtn, #searchResults').length) {
                $('#searchResults').hide();
            }
        });

        console.log('Trip Map Editor inicializado');
    }

})();
