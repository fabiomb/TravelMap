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
    let pendingTransportCallback = null; // Callback para el modal de selección de transporte
    let selectedTransportType = null;    // Tipo elegido en el modal
    let hasUnsavedChanges = false;        // Track unsaved changes
    let pendingNavigationUrl = null;      // URL to navigate after save/discard

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

    // Lista ordenada de tipos de transporte (compartida por renderLegend e initTransportModal)
    // Declarada aquí, después de transportIcons, para evitar temporal dead zone
    // Las etiquetas se rellenan al llamar buildTransportLabels()
    const orderedTransportTypes = [
        { type: 'plane', icon: transportIcons.plane, label: '' },
        { type: 'car',   icon: transportIcons.car,   label: '' },
        { type: 'bike',  icon: transportIcons.bike,  label: '' },
        { type: 'train', icon: transportIcons.train, label: '' },
        { type: 'ship',  icon: transportIcons.ship,  label: '' },
        { type: 'walk',  icon: transportIcons.walk,  label: '' },
        { type: 'bus',   icon: transportIcons.bus,   label: '' },
        { type: 'aerial',icon: transportIcons.aerial,label: '' }
    ];

    function buildTransportLabels() {
        const defaults = { plane: 'Avión', car: 'Auto', bike: 'Bicicleta', train: 'Tren', ship: 'Barco', walk: 'Caminata', bus: 'Bus', aerial: 'Aéreo' };
        orderedTransportTypes.forEach(function (t) {
            t.label = (typeof transportTypes !== 'undefined' && transportTypes[t.type]) || defaults[t.type];
        });
    }

    function normalizeRouteImageUrl(path) {
        if (!path) return '';

        const rawPath = String(path).trim();
        const base = String(BASE_URL || '').replace(/\/+$/, '');

        if (
            rawPath.startsWith('http://') ||
            rawPath.startsWith('https://') ||
            rawPath.startsWith('data:') ||
            rawPath.startsWith('blob:')
        ) {
            return rawPath;
        }

        if (rawPath.startsWith('/')) {
            return base + rawPath;
        }

        return base + '/' + rawPath.replace(/^\.\//, '');
    }

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

        // Usar la lista compartida de tipos de transporte (etiquetas ya construidas por buildTransportLabels)
        orderedTransportTypes.forEach(function (item) {
            const color = transportColors[item.type];

            const legendItem = $(`
                <div class="d-flex align-items-center mb-2">
                    <div style="width: 30px; height: 4px; background-color: ${color}; margin-right: 10px;"></div>
                    <small>${item.icon} ${item.label}</small>
                </div>
            `);

            legendContainer.append(legendItem);
        });
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
                            <th style="width: 40px; text-align: center;">#</th>
                            <th style="width: 50px; text-align: center;"></th>
                            <th>${__('routes.route_name') || 'Nombre'}</th>
                            <th style="width: 110px; text-align: center;">${__('routes.transport_type') || 'Tipo'}</th>
                            <th style="width: 100px; text-align: center;">${__('routes.is_round_trip') || 'Ida/Vuelta'}</th>
                            <th style="width: 90px; text-align: center;">${__('routes.distance') || 'Distancia'}</th>
                            <th style="width: 90px; min-width: 90px; white-space: nowrap; text-align: center;">${__('trips.actions') || 'Acciones'}</th>
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
            const routeName = route.name || '<em class="text-muted">—</em>';
            const hasDetails = route.name || route.description;

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
                        <div style="width: 20px; height: 4px; background-color: ${color}; margin: 0 auto;"></div>
                    </td>
                    <td class="text-center align-middle">${icon}</td>
                    <td class="align-middle small">
                        <span class="route-name-cell">${routeName}</span>
                        ${hasDetails ? '<i class="bi bi-pencil text-primary ms-1" style="font-size: 10px;"></i>' : ''}
                    </td>
                    <td class="align-middle">
                        <select class="form-select form-select-sm transport-type-selector" data-route-index="${index}">
                            ${optionsHtml}
                        </select>
                    </td>
                    <td class="text-center align-middle">
                        <div class="form-check d-inline-block">
                            <input class="form-check-input round-trip-checkbox" type="checkbox" data-route-index="${index}" ${route.is_round_trip === true ? 'checked' : ''}>
                        </div>
                    </td>
                    <td class="text-center align-middle small">
                        ${formatDistanceDisplay(route.distance_meters, route.transport_type, route.is_round_trip)}
                    </td>
                    <td class="text-center align-middle" style="white-space: nowrap;">
                        <button class="btn btn-sm btn-outline-primary edit-route-btn" data-route-index="${index}" title="${__('routes.edit_route') || 'Editar'}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/>
                            </svg>
                        </button>
                        <button class="btn btn-sm btn-outline-danger delete-route-btn" data-route-index="${index}" title="${__('map.delete_route')}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
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

        // Note: CARTO Voyager tiles include more multilingual labels than standard OSM
        // For full language support, consider using MapLibre renderer in settings
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            maxZoom: 19,
            subdomains: 'abcd'
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

        // Inicializar modal de edición de ruta
        initRouteModal();

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
                marker: true,
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
     * Maneja la creación de una nueva ruta o un marcador POI
     */
    function handleRouteCreated(e) {
        // If a marker was drawn, treat it as POI creation
        if (e.layerType === 'marker') {
            const latlng = e.layer.getLatLng();
            showCreatePoiModal(latlng.lat, latlng.lng);
            return;
        }

        const layer = e.layer;
        const distanceMeters = calculateLayerDistance(layer);

        showTransportModal(distanceMeters, function (transportType) {
            if (!transportType) return; // Usuario canceló

            const color = transportColors[transportType];
            layer.setStyle({ color: color, weight: 4, opacity: 0.8 });

            drawnItems.addLayer(layer);

            layer.transportType = transportType;
            layer.color = color;
            layer.isRoundTrip = false;
            layer.distanceMeters = distanceMeters;

            hasUnsavedChanges = true;
            updateRoutesData();
            console.log('Ruta creada:', transportType, layer.toGeoJSON());
        });
    }

    /**
     * Maneja la edición de rutas
     */
    function handleRoutesEdited(e) {
        hasUnsavedChanges = true;
        updateRoutesData();
        console.log('Rutas editadas');
    }

    /**
     * Maneja la eliminación de rutas
     */
    function handleRoutesDeleted(e) {
        hasUnsavedChanges = true;
        updateRoutesData();
        console.log('Rutas eliminadas');
    }

    /**
     * Inicializa el modal de selección de tipo de transporte (se crea una sola vez)
     */
    function initTransportModal() {
        if ($('#transportSelectModal').length > 0) return;

        let cardsHtml = '';
        orderedTransportTypes.forEach(function (t) {
            const color = transportColors[t.type] || '#666';
            // Scale the SVG icons up for the card display
            const icon = t.icon.replace(/width="16" height="16"/, 'width="32" height="32"');
            cardsHtml += `
            <div class="col-6 col-sm-4 col-md-3 mb-2">
                <button type="button"
                        class="btn btn-outline-secondary transport-type-card w-100 d-flex flex-column align-items-center justify-content-center gap-2 p-3"
                        data-type="${t.type}"
                        style="min-height:90px; border-radius:8px; transition:all 0.15s;"
                        onmouseover="this.style.borderColor='${color}';this.style.color='${color}';this.style.backgroundColor='${color}18';"
                        onmouseout="this.style.borderColor='';this.style.color='';this.style.backgroundColor='';">
                    <span class="transport-card-icon">${icon}</span>
                    <span class="transport-card-label small fw-semibold">${t.label}</span>
                </button>
            </div>`;
        });

        const modalHtml = `
        <div class="modal fade" id="transportSelectModal" tabindex="-1" aria-labelledby="transportSelectModalLabel" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="transportSelectModalLabel">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-signpost-split me-2" viewBox="0 0 16 16">
                                <path d="M7 7V1.414a1 1 0 0 1 2 0V2h5a1 1 0 0 1 .8.4l.975 1.3a.5.5 0 0 1 0 .6L14.8 5.6a1 1 0 0 1-.8.4H9v5h5a1 1 0 0 1 .8.4l.975 1.3a.5.5 0 0 1 0 .6l-.975 1.3a1 1 0 0 1-.8.4H2a1 1 0 0 1-1-1v-1a1 1 0 0 1 1-1h5V6H2a1 1 0 0 1-.8-.4L.225 4.3a.5.5 0 0 1 0-.6L1.2 2.4A1 1 0 0 1 2 2h5v5z"/>
                            </svg>
                            ${__('map.instruction_select_transport') || 'Selecciona el tipo de transporte'}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="transportModalDistance" class="alert alert-light d-flex align-items-center gap-2 mb-3" style="display:none !important;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-rulers flex-shrink-0" viewBox="0 0 16 16">
                                <path d="M1 0a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h5v-1H2v-1h4v-1H4v-1h2v-1H2v-1h4V9H4V8h2V7H2V6h4V4H4V3h2V2H2V1h4V0H1zm10 0v6h.041a.5.5 0 0 1 .277.635l-.415 1.105.842 1.684a.5.5 0 0 1-.17.65l-.993.662.993.662a.5.5 0 0 1 .17.649l-.842 1.684.415 1.105a.5.5 0 0 1-.277.635H11v1h4a1 1 0 0 0 1-1V1a1 1 0 0 0-1-1h-4zm1 1h2v1h-1v1h1v1h-1v1h1v1h-2V1zm2 9h-2v1h2v1h-1v1h1v1h-2v1h-1V9h3v1z"/>
                            </svg>
                            <span id="transportModalDistanceText"></span>
                        </div>
                        <div class="row g-2" id="transportCardsGrid">
                            ${cardsHtml}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${__('common.cancel') || 'Cancelar'}</button>
                    </div>
                </div>
            </div>
        </div>`;

        $('body').append(modalHtml);

        // Al cerrar el modal (cancel, ESC, o tras selección) disparar callback
        $('#transportSelectModal').on('hidden.bs.modal', function () {
            if (typeof pendingTransportCallback === 'function') {
                const cb = pendingTransportCallback;
                const type = selectedTransportType;
                pendingTransportCallback = null;
                selectedTransportType = null;
                cb(type); // null = cancelado
            }
        });

        // Selección de tarjeta
        $(document).on('click', '.transport-type-card', function () {
            selectedTransportType = $(this).data('type');
            bootstrap.Modal.getInstance($('#transportSelectModal')[0]).hide();
        });
    }

    /**
     * Muestra el modal de selección de tipo de transporte
     */
    function showTransportModal(distanceMeters, callback) {
        initTransportModal();
        pendingTransportCallback = callback;
        selectedTransportType = null;

        if (distanceMeters > 0) {
            const formatted = formatDistanceDisplay(distanceMeters, 'car', false).replace(' · ', '');
            $('#transportModalDistanceText').text(
                `${__('routes.detected_distance') || 'Distancia detectada'}: ${formatted}`
            );
            $('#transportModalDistance').css('display', 'flex');
        } else {
            $('#transportModalDistance').css('display', 'none');
        }

        new bootstrap.Modal($('#transportSelectModal')[0]).show();
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
                is_round_trip: layer.isRoundTrip === true,
                distance_meters: distanceForInfo,
                geojson: geojson,
                name: layer.routeName || '',
                description: layer.routeDescription || '',
                image_path: layer.routeImagePath || '',
                start_datetime: layer.startDatetime || '',
                end_datetime: layer.endDatetime || '',
                links: layer.routeLinks || [],
                layer: layer
            });
        });

        // Actualizar input hidden (sin las referencias a layers)
        console.log('Updating routes for save, count:', routesData.length);
        const routesDataForSave = routesData.map(route => ({
            transport_type: route.transport_type,
            color: route.color,
            is_round_trip: route.is_round_trip ? 1 : 0,
            geojson: route.geojson,
            name: route.name,
            description: route.description,
            image_path: route.image_path || '',
            start_datetime: route.start_datetime,
            end_datetime: route.end_datetime,
            links: route.links || []
        }));
        console.log('routesDataForSave[0] image_path:', routesDataForSave[0].image_path);
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
                l.isRoundTrip = route.is_round_trip === 1 || route.is_round_trip === true || route.is_round_trip === "1";
                l.distanceMeters = route.distance_meters;
                l.routeName = route.name || '';
                l.routeDescription = route.description || '';
                l.routeImagePath = route.image_path || '';
                l.startDatetime = route.start_datetime || '';
                l.endDatetime = route.end_datetime || '';
                l.routeLinks = route.links || [];
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
            addPoiMarker(point);
        });

        console.log('Puntos cargados:', existingPoints.length);
    }

    /**
     * Adds a POI marker to the map with click-to-delete behavior
     */
    function addPoiMarker(point) {
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

        marker.poiData = point;

        marker.bindPopup(`
            <strong>${point.title}</strong><br>
            <small class="text-muted">${point.type}</small><br>
            <small>Lat: ${point.latitude}, Lng: ${point.longitude}</small><br>
            <button class="btn btn-sm btn-outline-danger mt-2 delete-poi-btn" data-poi-id="${point.id}" style="font-size: 11px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                    <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                </svg>
                ${__('common.delete')}
            </button>
        `);

        marker.on('popupopen', function () {
            setTimeout(function () {
                const btn = document.querySelector('.delete-poi-btn[data-poi-id="' + point.id + '"]');
                if (btn) {
                    btn.addEventListener('click', function () {
                        deletePoi(point.id, marker);
                    });
                }
            }, 100);
        });

        pointMarkers.addLayer(marker);
        return marker;
    }

    /**
     * Deletes a POI via API and removes its marker
     */
    function deletePoi(poiId, marker) {
        if (!confirm(__('map.poi_delete_confirm'))) return;

        $.ajax({
            url: BASE_URL + '/api/delete_poi.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: poiId }),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    pointMarkers.removeLayer(marker);
                    // Remove from existingPoints array
                    const idx = existingPoints.findIndex(p => p.id === poiId);
                    if (idx > -1) existingPoints.splice(idx, 1);
                    console.log('POI deleted:', poiId);
                } else {
                    alert(response.error || __('map.poi_error_saving'));
                }
            },
            error: function () {
                alert(__('map.poi_error_saving'));
            }
        });
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

        const formatted = UnitManager.formatDistance(meters);
        const roundTripIcon = isRoundTrip ? ` <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ms-1 text-warning" style="vertical-align: text-bottom;"><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></svg>` : '';

        return `${formatted}${roundTripIcon}`;
    }

    /**
     * Inicialización cuando el DOM está listo
     */
    $(document).ready(function () {
        // Cargar configuración primero, luego inicializar el mapa
        loadConfig().always(function () {
            buildTransportLabels(); // Construir etiquetas con datos de PHP
            initMap();

            // Renderizar leyenda con colores configurados
            renderLegend();

            // Inicializar POI mode
            initPoiMode();

            // Inicializar unsaved changes warning
            initUnsavedChangesWarning();

            // Inicializar auto-route si está habilitado
            initAutoRoute();

            // Configurar event handlers
            setupEventHandlers();
        });
    });

    /**
     * Configuración de event handlers
     */
    function setupEventHandlers() {
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

    // =============================================
    // POI Creation (via native Leaflet Draw marker)
    // =============================================

    /**
     * Initialize POI modal in DOM
     */
    function initPoiMode() {
        // Create the POI modal in DOM
        initCreatePoiModal();
    }

    /**
     * Creates the POI creation modal HTML
     */
    function initCreatePoiModal() {
        if ($('#createPoiModal').length > 0) return;

        // Build POI type options
        const types = (typeof poiTypes !== 'undefined') ? poiTypes : { visit: 'Visit', stay: 'Stay', food: 'Food' };
        let typeOptionsHtml = '';
        Object.keys(types).forEach(function (key) {
            typeOptionsHtml += `<option value="${key}">${types[key]}</option>`;
        });

        const modalHtml = `
        <div class="modal fade" id="createPoiModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-geo-alt me-2" viewBox="0 0 16 16">
                                <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/>
                                <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                            </svg>
                            ${__('map.create_poi')}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="poiTitle" class="form-label">${__('map.poi_title')} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="poiTitle" maxlength="200" placeholder="${__('map.poi_title_placeholder')}" required>
                        </div>
                        <div class="mb-3">
                            <label for="poiType" class="form-label">${__('map.poi_type')}</label>
                            <select class="form-select" id="poiType">
                                ${typeOptionsHtml}
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">${__('points.latitude')}</label>
                                <input type="text" class="form-control form-control-sm" id="poiLat" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">${__('points.longitude')}</label>
                                <input type="text" class="form-control form-control-sm" id="poiLng" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="poiDate" class="form-label">${__('map.poi_date')}</label>
                                <input type="date" class="form-control" id="poiDate">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="poiTime" class="form-label">${__('map.poi_time')}</label>
                                <input type="time" class="form-control" id="poiTime">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${__('common.cancel')}</button>
                        <button type="button" class="btn btn-primary" id="savePoiBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-lg me-1" viewBox="0 0 16 16">
                                <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/>
                            </svg>
                            ${__('common.save')}
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

        $('body').append(modalHtml);

        // Save POI button handler
        $('#savePoiBtn').on('click', function () {
            savePoiFromModal();
        });

        // Enter key in title field triggers save
        $('#poiTitle').on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                savePoiFromModal();
            }
        });
    }

    /**
     * Opens the POI creation modal with pre-filled coordinates
     */
    function showCreatePoiModal(lat, lng) {
        $('#poiTitle').val('');
        $('#poiType').val('visit');
        $('#poiLat').val(lat.toFixed(6));
        $('#poiLng').val(lng.toFixed(6));
        $('#poiDate').val('');
        $('#poiTime').val('');

        const modal = new bootstrap.Modal($('#createPoiModal')[0]);
        modal.show();

        // Focus title field when modal is shown
        $('#createPoiModal').one('shown.bs.modal', function () {
            $('#poiTitle').focus();
        });
    }

    /**
     * Saves a POI from the modal form via API
     */
    function savePoiFromModal() {
        const title = $('#poiTitle').val().trim();
        if (!title) {
            $('#poiTitle').addClass('is-invalid');
            return;
        }
        $('#poiTitle').removeClass('is-invalid');

        const data = {
            trip_id: tripId,
            title: title,
            type: $('#poiType').val(),
            latitude: parseFloat($('#poiLat').val()),
            longitude: parseFloat($('#poiLng').val()),
            visit_date: $('#poiDate').val() || null,
            visit_time: $('#poiTime').val() || null
        };

        $('#savePoiBtn').prop('disabled', true);

        $.ajax({
            url: BASE_URL + '/api/save_poi.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            dataType: 'json',
            success: function (response) {
                if (response.success && response.point) {
                    // Add marker to map
                    addPoiMarker(response.point);
                    existingPoints.push(response.point);

                    // Close modal
                    bootstrap.Modal.getInstance($('#createPoiModal')[0]).hide();

                    console.log('POI saved:', response.point);
                } else {
                    const errors = response.errors || {};
                    const errorMsg = Object.values(errors).join(', ') || response.error || __('map.poi_error_saving');
                    alert(errorMsg);
                }
            },
            error: function () {
                alert(__('map.poi_error_saving'));
            },
            complete: function () {
                $('#savePoiBtn').prop('disabled', false);
            }
        });
    }

    // =============================================
    // Unsaved Changes Warning
    // =============================================

    /**
     * Initializes the unsaved changes warning system
     */
    function initUnsavedChangesWarning() {
        // Browser beforeunload event
        window.addEventListener('beforeunload', function (e) {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Intercept all navigation links on the page
        $(document).on('click', 'a[href]', function (e) {
            if (!hasUnsavedChanges) return;

            const href = $(this).attr('href');
            // Ignore anchors, javascript, and empty hrefs
            if (!href || href === '#' || href.startsWith('javascript:')) return;

            e.preventDefault();
            pendingNavigationUrl = href;
            showUnsavedChangesModal();
        });

        // Create modal
        initUnsavedChangesModal();

        // Mark changes as saved after successful form submit
        $('#routesForm').on('submit', function () {
            hasUnsavedChanges = false;
        });
    }

    /**
     * Creates the unsaved changes modal
     */
    function initUnsavedChangesModal() {
        if ($('#unsavedChangesModal').length > 0) return;

        const modalHtml = `
        <div class="modal fade" id="unsavedChangesModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-exclamation-triangle me-2" viewBox="0 0 16 16">
                                <path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057m1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/>
                                <path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/>
                            </svg>
                            ${__('map.unsaved_changes_title')}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>${__('map.unsaved_changes_message')}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" id="leaveWithoutSavingBtn">
                            ${__('map.leave_without_saving')}
                        </button>
                        <button type="button" class="btn btn-primary" id="saveAndLeaveBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-lg me-1" viewBox="0 0 16 16">
                                <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/>
                            </svg>
                            ${__('map.save_and_leave')}
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

        $('body').append(modalHtml);

        // "Leave without saving" button
        $('#leaveWithoutSavingBtn').on('click', function () {
            hasUnsavedChanges = false;
            bootstrap.Modal.getInstance($('#unsavedChangesModal')[0]).hide();
            if (pendingNavigationUrl) {
                window.location.href = pendingNavigationUrl;
            }
        });

        // "Save & Leave" button — submit the routes form, then navigate
        $('#saveAndLeaveBtn').on('click', function () {
            // Update routes data
            updateRoutesData();
            hasUnsavedChanges = false;

            // Submit form via AJAX so we can redirect after
            const formData = new FormData($('#routesForm')[0]);

            $.ajax({
                url: $('#routesForm').attr('action') || window.location.href,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                complete: function () {
                    bootstrap.Modal.getInstance($('#unsavedChangesModal')[0]).hide();
                    if (pendingNavigationUrl) {
                        window.location.href = pendingNavigationUrl;
                    }
                }
            });
        });
    }

    /**
     * Shows the unsaved changes confirmation modal
     */
    function showUnsavedChangesModal() {
        new bootstrap.Modal($('#unsavedChangesModal')[0]).show();
    }

    /**
     * Inicializa el modal para editar nombre y descripción de rutas
     */
    function initRouteModal() {
        // Crear modal HTML si no existe
        if ($('#routeEditModal').length === 0) {
            const linkTypes = Object.keys(window.routeLinkTypes || {
                'website': 'Website',
                'google_maps': 'Google Maps',
                'other': 'Enlace'
            });
            let linkOptionsHtml = '';
            linkTypes.forEach(type => {
                const label = window.routeLinkTypes?.[type]?.label || type;
                linkOptionsHtml += `<option value="${type}">${label}</option>`;
            });
            
            const modalHtml = `
            <div class="modal fade" id="routeEditModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${__('routes.edit_route') || 'Editar Ruta'}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">${__('routes.start_datetime') || 'Inicio'}</label>
                                    <div class="row g-1">
                                        <div class="col-7"><input type="date" class="form-control" id="routeStartDateInput"></div>
                                        <div class="col-5"><input type="time" class="form-control" id="routeStartTimeInput"></div>
                                    </div>
                                    <small class="form-text text-muted">${__('points.visit_time_optional') || 'Hora opcional'}</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">${__('routes.end_datetime') || 'Fin'}</label>
                                    <div class="row g-1">
                                        <div class="col-7"><input type="date" class="form-control" id="routeEndDateInput"></div>
                                        <div class="col-5"><input type="time" class="form-control" id="routeEndTimeInput"></div>
                                    </div>
                                    <small class="form-text text-muted">${__('points.visit_time_optional') || 'Hora opcional'}</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">${__('routes.route_name') || 'Nombre'}</label>
                                <input type="text" class="form-control" id="routeNameInput" placeholder="${__('routes.name_placeholder') || 'Ej: Vuelo EZE-MAD'}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">${__('routes.description') || 'Descripción'}</label>
                                <textarea class="form-control" id="routeDescInput" rows="3" placeholder="${__('routes.description_placeholder') || 'Notas sobre esta ruta...'}"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">${__('routes.image') || 'Imagen'}</label>
                                <div class="border rounded p-3 text-center" id="routeImageDropArea" style="cursor: pointer;">
                                    <input type="file" id="routeImageInput" accept="image/jpeg,image/png,image/jpg,image/gif" style="display: none;">
                                    <img id="routeImagePreview" src="" alt="" class="img-thumbnail mb-2" style="max-width: 100%; max-height: 200px; display: none;">
                                    <div id="routeImagePlaceholder">
                                        <p class="mb-1">${__('routes.drag_drop_image') || 'Arrastra una imagen o haz clic para seleccionar'}</p>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="routeSelectImageBtn">${__('routes.select_image') || 'Seleccionar'}</button>
                                    </div>
                                    <button type="button" class="btn btn-outline-danger btn-sm mt-2" id="routeRemoveImageBtn" style="display: none;">${__('routes.remove_image') || 'Quitar imagen'}</button>
                                </div>
                                <input type="hidden" id="routeImagePath" value="">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">${__('routes.external_links') || 'Links Externos'}</label>
                                <div id="route-links-container"></div>
                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="addRouteLinkBtn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5l2.404.961L10.404 2l-2.218-.887zm3.564 1.426L5.596 5 8 5.961l1.598-1.5zM7.5 14H5.092l1.406-1.406 2.002 2.002 2.002-2.002.998.998-.5.5a2.5 2.5 0 0 1-3.5 3.5l-.5-.5.998-.998 1.5 1.5 1.5-1.5-.998-.998.5-.5a2.5 2.5 0 0 1 3.5-3.5l.5.5-1.5 1.5-.998.998.5.5a2.5 2.5 0 0 1-3.5 3.5l-.5-.5-2.002 2.002z"/></svg>
                                    ${__('routes.add_link') || 'Agregar link'}
                                </button>
                                <template id="route-link-template">
                                    <div class="route-link-row input-group mb-2">
                                        <select class="form-select" style="max-width: 180px;">
                                            ${linkOptionsHtml}
                                        </select>
                                        <input type="url" class="form-control" placeholder="https://" required>
                                        <input type="text" class="form-control" placeholder="${__('routes.link_label_optional') || 'Etiqueta (opcional)'}" style="max-width: 140px;">
                                        <button type="button" class="btn btn-outline-danger remove-link-btn">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${__('common.cancel') || 'Cancelar'}</button>
                            <button type="button" class="btn btn-primary" id="saveRouteDetails">${__('common.save') || 'Guardar'}</button>
                        </div>
                    </div>
                </div>
            </div>`;
            $('body').append(modalHtml);
            
            // Event handlers para links
            $(document).on('click', '#addRouteLinkBtn', function() {
                const template = $('#route-link-template').html();
                $('#route-links-container').append(template);
            });
            
            $(document).on('click', '.remove-link-btn', function() {
                $(this).closest('.route-link-row').remove();
            });
        }

        let currentRouteIndex = null;

        // Botón de editar en cada fila
        $(document).on('click', '.edit-route-btn', function() {
            const index = parseInt($(this).data('route-index'));
            currentRouteIndex = index;
            const route = routesData[index];
            
            // Separar fecha y hora sin usar Date() para evitar problemas de zona horaria
            const splitDatetime = (dt) => {
                if (!dt) return { date: '', time: '' };
                const normalized = String(dt).replace('T', ' ');
                const parts = normalized.split(' ');
                const datePart = parts[0] || '';
                const timePart = parts[1] ? parts[1].slice(0, 5) : '';
                // Si la hora es 00:00 dejar vacío (sin hora definida, igual que point_form)
                return { date: datePart, time: (timePart === '00:00' ? '' : timePart) };
            };

            const startDt = splitDatetime(route.start_datetime);
            const endDt   = splitDatetime(route.end_datetime);
            $('#routeStartDateInput').val(startDt.date);
            $('#routeStartTimeInput').val(startDt.time);
            $('#routeEndDateInput').val(endDt.date);
            $('#routeEndTimeInput').val(endDt.time);
            $('#routeNameInput').val(route.name || '');
            $('#routeDescInput').val(route.description || '');
            
            // Cargar links
            $('#route-links-container').empty();
            if (route.links && route.links.length > 0) {
                route.links.forEach(link => {
                    const template = $('#route-link-template').html();
                    const row = $(template);
                    row.find('select').val(link.link_type || link.type || 'website');
                    row.find('input[type="url"]').val(link.url || '');
                    row.find('input[type="text"]').val(link.label || '');
                    $('#route-links-container').append(row);
                });
            }
            
            // Cargar imagen
            const routeImagePath = route.image_path || route.imageUrl || '';
            const routeImagePreviewUrl = normalizeRouteImageUrl(routeImagePath);
            console.log('Loading route image, path:', routeImagePath, 'route id:', route.id);
            $('#routeImagePath').val(routeImagePath);
            if (routeImagePath) {
                $('#routeImagePreview').attr('src', routeImagePreviewUrl).show();
                $('#routeImagePlaceholder').hide();
                $('#routeRemoveImageBtn').show();
            } else {
                $('#routeImagePreview').attr('src', '').hide();
                $('#routeImagePlaceholder').show();
                $('#routeRemoveImageBtn').hide();
            }
            
            const modal = new bootstrap.Modal($('#routeEditModal'));
            modal.show();
        });

        // Guardar cambios
        $('#saveRouteDetails').on('click', function() {
            if (currentRouteIndex !== null && routesData[currentRouteIndex]) {
                const route = routesData[currentRouteIndex];
                // Combinar fecha + hora; si no hay hora usar 00:00:00 (igual que point_form)
                const buildDatetime = (dateId, timeId) => {
                    const d = $(dateId).val();
                    if (!d) return null;
                    const t = $(timeId).val();
                    return d + ' ' + (t ? t + ':00' : '00:00:00');
                };
                route.start_datetime = buildDatetime('#routeStartDateInput', '#routeStartTimeInput');
                route.end_datetime   = buildDatetime('#routeEndDateInput',   '#routeEndTimeInput');
                route.name = $('#routeNameInput').val();
                route.description = $('#routeDescInput').val();
                route.image_path = $('#routeImagePath').val();
                console.log('Saving route image_path:', route.image_path, 'route id:', route.id);
                
                // Recolectar links
                route.links = [];
                $('#route-links-container .route-link-row').each(function() {
                    const linkType = $(this).find('select').val();
                    const linkUrl = $(this).find('input[type="url"]').val();
                    const linkLabel = $(this).find('input[type="text"]').val();
                    if (linkUrl) {
                        route.links.push({
                            link_type: linkType,
                            url: linkUrl,
                            label: linkLabel || ''
                        });
                    }
                });
                
                if (route.layer) {
                    route.layer.routeName = route.name;
                    route.layer.routeDescription = route.description;
                    route.layer.routeImagePath = route.image_path;
                    route.layer.startDatetime = route.start_datetime;
                    route.layer.endDatetime = route.end_datetime;
                    route.layer.routeLinks = route.links;
                }
                updateRoutesData();
                bootstrap.Modal.getInstance($('#routeEditModal')).hide();
            }
        });

        // Route image upload handling via AJAX
        const handleRouteImage = function(file) {
            const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                alert('Formato de imagen no válido. Usa JPEG, PNG o GIF.');
                return;
            }
            
            // Upload via AJAX
            $('#routeImagePlaceholder').html('<p class="mb-0 text-muted">Subiendo...</p>');
            
            const formData = new FormData();
            formData.append('image', file);
            
            console.log('Uploading to:', BASE_URL + '/api/upload_route_image.php');
            
            $.ajax({
                url: BASE_URL + '/api/upload_route_image.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Upload response:', response);
                    if (response.success) {
                        $('#routeImagePath').val(response.path);
                        $('#routeImagePreview').attr('src', normalizeRouteImageUrl(response.url || response.path)).show();
                        $('#routeImagePlaceholder').hide();
                        $('#routeRemoveImageBtn').show();
                    } else {
                        alert(response.error || 'Error al subir imagen');
                        resetImagePlaceholder();
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Upload error:', xhr.status, status, error);
                    console.log('Response text:', xhr.responseText);
                    let errorMsg = 'Error de conexión: ' + status;
                    if (xhr.status === 401) {
                        errorMsg = 'No autorizado. Debes iniciar sesión.';
                    } else if (xhr.status === 400) {
                        try {
                            const resp = JSON.parse(xhr.responseText);
                            console.log('Error response JSON:', resp);
                            errorMsg = resp.error || errorMsg;
                        } catch(e) {
                            console.log('Could not parse response:', xhr.responseText);
                        }
                    }
                    alert(errorMsg);
                    resetImagePlaceholder();
                }
            });
        };
        
        const openRouteImagePicker = function() {
            const input = document.getElementById('routeImageInput');
            if (input) {
                input.click();
            }
        };

const resetImagePlaceholder = function() {
            const dragDropText = 'Arrastra una imagen o haz clic para seleccionar';
            const selectBtnText = 'Seleccionar';
            $('#routeImagePlaceholder').html(
                '<p class="mb-1">' + dragDropText + '</p>' +
                '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="$(\'#routeImageInput\').click()">' + 
                selectBtnText + '</button>'
            );
        };

        $('#routeImageDropArea').on('dragenter dragover', function(e) {
            e.preventDefault();
            $(this).css({'border-color': '#0d6efd', 'background-color': '#f8f9fa'});
        });
        
        $('#routeImageDropArea').on('dragleave drop', function(e) {
            e.preventDefault();
            $(this).css({'border-color': '#6c757d', 'background-color': '#fff'});
        });
        
        $('#routeImageDropArea').on('drop', function(e) {
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                handleRouteImage(files[0]);
            }
        });

        $(document).on('change', '#routeImageInput', function(e) {
            const files = e.target && e.target.files ? e.target.files : [];
            if (files.length > 0) {
                handleRouteImage(files[0]);
            }
        });

        $('#routeImageDropArea').on('click', function(e) {
            if (
                $(e.target).is('#routeImageInput') ||
                $(e.target).closest('#routeImageInput').length ||
                $(e.target).closest('#routeRemoveImageBtn').length ||
                $(e.target).closest('#routeImagePreview').length ||
                $(e.target).closest('#routeSelectImageBtn').length
            ) {
                return;
            }

            openRouteImagePicker();
        });
        
        $(document).on('click', '#routeSelectImageBtn', function(e) {
            e.stopPropagation();
            openRouteImagePicker();
        });

        $('#routeRemoveImageBtn').on('click', function() {
            $('#routeImageInput').val('');
            $('#routeImagePath').val('');
            $('#routeImagePreview').attr('src', '').hide();
            $('#routeImagePlaceholder').show();
            $('#routeRemoveImageBtn').hide();
        });
    }

    // =============================================
    // Automated Route Generation
    // =============================================

    /**
     * Initialize auto-route button and modal handlers.
     * Only shows the button if routing service is enabled in config.
     */
    function initAutoRoute() {
        const isEnabled = appConfig && appConfig.routingServiceEnabled;

        if (!isEnabled) {
            // Hide the button if it exists
            $('#autoRouteBtn').hide();
            return;
        }

        // Show button before the save form
        const $saveForm = $('#routesForm');
        if ($saveForm.length && $('#autoRouteBtn').length === 0) {
            const btnHtml = `
                <button type="button" class="btn btn-outline-info btn-lg w-100 mb-3" id="autoRouteBtn" data-bs-toggle="modal" data-bs-target="#autoRouteModal">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                        <circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="19" r="3"></circle>
                        <path d="M12 5H8.5C6.567 5 5 6.567 5 8.5C5 10.433 6.567 12 8.5 12H15.5C17.433 12 19 13.567 19 15.5C19 17.433 17.433 19 15.5 19H12"></path>
                    </svg>
                    ${__('routes.auto_route') || 'Create Automated Route'}
                </button>
            `;
            $saveForm.before(btnHtml);
        }

        // Setup geocoding search for origin
        setupAutoRouteSearch('autoRouteOriginSearch', 'autoRouteOriginSearchBtn', 'autoRouteOriginResults', 'autoRouteFromLat', 'autoRouteFromLng');
        // Setup geocoding search for destination
        setupAutoRouteSearch('autoRouteDestSearch', 'autoRouteDestSearchBtn', 'autoRouteDestResults', 'autoRouteToLat', 'autoRouteToLng');

        // Validate form to enable/disable submit button
        $('#autoRouteFromLat, #autoRouteFromLng, #autoRouteToLat, #autoRouteToLng').on('input', validateAutoRouteForm);

        // Submit handler
        $('#autoRouteSubmit').off('click').on('click', submitAutoRoute);

        // Reset when modal is hidden
        $('#autoRouteModal').on('hidden.bs.modal', function () {
            $('#autoRouteOriginSearch, #autoRouteDestSearch, #autoRouteName').val('');
            $('#autoRouteFromLat, #autoRouteFromLng, #autoRouteToLat, #autoRouteToLng').val('');
            $('#autoRouteError').hide();
            $('#autoRouteSubmit').prop('disabled', true);
            $('#autoRouteSpinner').hide();
            $('#autoRouteOriginResults, #autoRouteDestResults').hide().empty();
        });
    }

    /**
     * Setup geocoding search for an auto-route input field.
     */
    function setupAutoRouteSearch(searchInputId, searchBtnId, resultsId, latInputId, lngInputId) {
        const $searchInput = $(`#${searchInputId}`);
        const $searchBtn = $(`#${searchBtnId}`);
        const $results = $(`#${resultsId}`);
        const $latInput = $(`#${latInputId}`);
        const $lngInput = $(`#${lngInputId}`);

        function doSearch() {
            const query = $searchInput.val().trim();
            if (!query) return;

            console.log('[TravelMap Geocoding] service=nominatim query="' + query + '"');
            $.ajax({
                url: BASE_URL + '/api/geocode.php',
                data: { q: query },
                dataType: 'json'
            }).done(function (data) {
                $results.empty().hide();
                if (data && data.length > 0) {
                    console.log('[TravelMap Geocoding] nominatim → ' + data.length + ' result(s) for "' + query + '"');
                    data.forEach(function (place) {
                        const item = $(`<a href="#" class="list-group-item list-group-item-action py-1 px-2">${place.display_name}</a>`);
                        item.on('click', function (e) {
                            e.preventDefault();
                            $latInput.val(parseFloat(place.lat).toFixed(6));
                            $lngInput.val(parseFloat(place.lon).toFixed(6));
                            $searchInput.val(place.display_name);
                            $results.hide();
                            validateAutoRouteForm();
                        });
                        $results.append(item);
                    });
                    $results.show();
                }
            });
        }

        $searchBtn.on('click', doSearch);
        $searchInput.on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                doSearch();
            }
        });
    }

    /**
     * Validate auto-route form fields and enable/disable submit.
     */
    function validateAutoRouteForm() {
        const fromLat = parseFloat($('#autoRouteFromLat').val());
        const fromLng = parseFloat($('#autoRouteFromLng').val());
        const toLat = parseFloat($('#autoRouteToLat').val());
        const toLng = parseFloat($('#autoRouteToLng').val());

        const valid = !isNaN(fromLat) && !isNaN(fromLng) && !isNaN(toLat) && !isNaN(toLng) &&
                      fromLat >= -90 && fromLat <= 90 && toLat >= -90 && toLat <= 90 &&
                      fromLng >= -180 && fromLng <= 180 && toLng >= -180 && toLng <= 180;

        $('#autoRouteSubmit').prop('disabled', !valid);
    }

    /**
     * Submit the auto-route request to the API.
     */
    function submitAutoRoute() {
        const $btn = $('#autoRouteSubmit');
        const $spinner = $('#autoRouteSpinner');
        const $error = $('#autoRouteError');

        $btn.prop('disabled', true);
        $spinner.show();
        $error.hide();

        const postData = {
            trip_id: tripId,
            from_lat: parseFloat($('#autoRouteFromLat').val()),
            from_lng: parseFloat($('#autoRouteFromLng').val()),
            to_lat: parseFloat($('#autoRouteToLat').val()),
            to_lng: parseFloat($('#autoRouteToLng').val()),
            transport_type: $('#autoRouteTransport').val(),
            name: $('#autoRouteName').val()
        };

        console.log('[TravelMap Routing] requesting route transport=' + postData.transport_type,
            'from=' + postData.from_lat + ',' + postData.from_lng,
            'to=' + postData.to_lat + ',' + postData.to_lng);
        $.ajax({
            url: BASE_URL + '/api/get_route_from_service.php',
            method: 'POST',
            data: postData,
            dataType: 'json'
        }).done(function (response) {
            if (response.success && response.data) {
                // Add the route to the map
                addAutoRouteToMap(response.data);

                // Close the modal
                const modal = bootstrap.Modal.getInstance($('#autoRouteModal')[0]);
                if (modal) modal.hide();

                hasUnsavedChanges = true;
            } else {
                $error.text(response.error || 'Unknown error').show();
                $btn.prop('disabled', false);
            }
        }).fail(function (xhr) {
            let msg = 'Error connecting to routing service';
            try {
                const resp = JSON.parse(xhr.responseText);
                if (resp.error) msg = resp.error;
            } catch (e) {}
            $error.text(msg).show();
            $btn.prop('disabled', false);
        }).always(function () {
            $spinner.hide();
        });
    }

    /**
     * Add a route returned by the routing service to the map and routes list.
     */
    function addAutoRouteToMap(data) {
        const geojson = data.geojson;
        const coordinates = geojson.geometry.coordinates;
        const color = data.color || transportColors[data.transport_type] || '#3388ff';

        // Convert [lng, lat] to Leaflet [lat, lng]
        const latLngs = coordinates.map(function (c) {
            return [c[1], c[0]];
        });

        // Create polyline and add to map
        const polyline = L.polyline(latLngs, {
            color: color,
            weight: 4,
            opacity: 0.8
        });

        // Set layer properties so updateRoutesData() can read them on form submit
        polyline.transportType = data.transport_type;
        polyline.color = color;
        polyline.isRoundTrip = false;
        polyline.routeName = data.name || '';
        polyline.routeDescription = '';
        polyline.routeImagePath = '';
        polyline.startDatetime = '';
        polyline.endDatetime = '';
        polyline.routeLinks = [];

        drawnItems.addLayer(polyline);

        // Add to routesData
        routesData.push({
            layer: polyline,
            transport_type: data.transport_type,
            color: color,
            is_round_trip: false,
            distance_meters: data.distance_meters || 0,
            name: data.name || '',
            description: '',
            image_path: '',
            start_datetime: '',
            end_datetime: '',
            links: [],
            geojson: geojson
        });

        // Re-render routes list
        renderRoutesList();

        // Fit map to show the new route
        if (latLngs.length > 0) {
            map.fitBounds(polyline.getBounds(), { padding: [50, 50] });
        }

        const distKm = data.distance_meters ? (data.distance_meters / 1000).toFixed(1) + ' km' : 'N/A';
        console.log('[TravelMap Routing] service=' + (data.service_type || 'unknown'),
            '| transport=' + data.transport_type,
            '| distance=' + distKm,
            '| route_id=' + data.route_id);
    }

})();
