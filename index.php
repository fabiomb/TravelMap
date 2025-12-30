<?php
// Cargar configuración para las constantes
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(SITE_TITLE) ?></title>
    <meta name="description" content="<?= htmlspecialchars(SITE_DESCRIPTION) ?>">
    
    <?php if (!empty(SITE_FAVICON)): ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars(SITE_FAVICON) ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?= htmlspecialchars(SITE_FAVICON) ?>">
    <?php endif; ?>
    
    <!-- Bootstrap 5 CSS -->
    <link href="<?= ASSETS_URL ?>/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/css/leaflet.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/plugins/MarkerCluster.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/plugins/MarkerCluster.Default.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/public_map.css?a=1">
    
    <?php 
    // Insertar código de analytics u otros scripts personalizados
    if (!empty(SITE_ANALYTICS_CODE)): 
        echo SITE_ANALYTICS_CODE . "\n";
    endif; 
    ?>
</head>
<body>
    <!-- Mapa a pantalla completa -->
    <div id="map"></div>

    <!-- Botón para abrir el panel lateral -->
    <button class="btn btn-primary floating-menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#tripsPanel" aria-controls="tripsPanel">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
        </svg>
        <span class="ms-2">Mis Viajes</span>
    </button>

    <!-- Panel lateral con filtros (Offcanvas) -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="tripsPanel" aria-labelledby="tripsPanelLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="tripsPanelLabel">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-map-fill me-2" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.598-.49L10.5.99 5.598.01a.5.5 0 0 0-.196 0l-5 1A.5.5 0 0 0 0 1.5v14a.5.5 0 0 0 .598.49l4.902-.98 4.902.98a.5.5 0 0 0 .196 0l5-1A.5.5 0 0 0 16 14.5zM5 14.09V1.11l.5-.1.5.1v12.98l-.5.1zm5 .8V1.91l.5-.1.5.1v12.98l-.5.1z"/>
                </svg>
                Mis Viajes
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <!-- Buscador de lugares -->
            <div class="mb-4">
                <h6 class="text-muted text-uppercase small mb-3">Buscar Lugar</h6>
                <div class="input-group mb-2">
                    <span class="input-group-text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                        </svg>
                    </span>
                    <input type="text" 
                           class="form-control form-control-sm" 
                           id="publicPlaceSearch" 
                           placeholder="Ciudad, país o lugar..."
                           autocomplete="off">
                    <button class="btn btn-sm btn-outline-primary" type="button" id="publicSearchBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                        </svg>
                    </button>
                </div>
                <div id="publicSearchResults" class="list-group" style="display: none; max-height: 250px; overflow-y: auto;"></div>
            </div>

            <hr>

            <!-- Controles generales -->
            <div class="mb-4">
                <h6 class="text-muted text-uppercase small mb-3">Controles</h6>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="toggleRoutes" checked>
                    <label class="form-check-label" for="toggleRoutes">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bezier2 me-1" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M1 2.5A1.5 1.5 0 0 1 2.5 1h1A1.5 1.5 0 0 1 5 2.5h4.134a1 1 0 1 1 0 1h-2.01q.269.27.484.605C8.246 5.097 8.5 6.459 8.5 8c0 1.993.257 3.092.713 3.7.356.476.895.721 1.787.784A1.5 1.5 0 0 1 12.5 11h1a1.5 1.5 0 0 1 1.5 1.5v1a1.5 1.5 0 0 1-1.5 1.5h-1a1.5 1.5 0 0 1-1.5-1.5H6.866a1 1 0 1 1 0-1h1.711a3 3 0 0 1-.165-.2C7.743 11.407 7.5 10.007 7.5 8c0-1.46-.246-2.597-.733-3.355-.39-.605-.952-1-1.767-1.112A1.5 1.5 0 0 1 3.5 5h-1A1.5 1.5 0 0 1 1 3.5zM2.5 2a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm10 10a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/>
                        </svg>
                        Mostrar Rutas
                    </label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="togglePoints" checked>
                    <label class="form-check-label" for="togglePoints">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt-fill me-1" viewBox="0 0 16 16">
                            <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/>
                        </svg>
                        Mostrar Puntos
                    </label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="toggleFlightRoutes">
                    <label class="form-check-label" for="toggleFlightRoutes">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-airplane me-1" viewBox="0 0 16 16">
                            <path d="M6.428 1.151C6.708.591 7.213 0 8 0s1.292.592 1.572 1.151C9.861 1.73 10 2.431 10 3v3.691l5.17 2.585a1.5 1.5 0 0 1 .83 1.342V12a.5.5 0 0 1-.582.493l-5.507-.918-.375 2.253 1.318 1.318A.5.5 0 0 1 10.5 16h-5a.5.5 0 0 1-.354-.854l1.319-1.318-.376-2.253-5.507.918A.5.5 0 0 1 0 12v-1.382a1.5 1.5 0 0 1 .83-1.342L6 6.691V3c0-.568.14-1.271.428-1.849m.894.448C7.111 2.02 7 2.569 7 3v4a.5.5 0 0 1-.276.447l-5.448 2.724a.5.5 0 0 0-.276.447v.792l5.418-.903a.5.5 0 0 1 .575.41l.5 3a.5.5 0 0 1-.14.437L6.708 15h2.586l-.647-.646a.5.5 0 0 1-.14-.436l.5-3a.5.5 0 0 1 .576-.411L15 11.41v-.792a.5.5 0 0 0-.276-.447L9.276 7.447A.5.5 0 0 1 9 7V3c0-.432-.11-.979-.322-1.401C8.458 1.159 8.213 1 8 1s-.458.158-.678.599"/>
                        </svg>
                        Mostrar Rutas en Avión
                    </label>
                </div>
            </div>

            <hr>

            <!-- Lista de viajes -->
            <div>
                <h6 class="text-muted text-uppercase small mb-3">Viajes</h6>
                <div id="tripsList">
                    <!-- Se llenará dinámicamente con JavaScript -->
                    <div class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="small mt-2">Cargando viajes...</p>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllTrips">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-check-all me-1" viewBox="0 0 16 16">
                            <path d="M8.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L2.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093L8.95 4.992zm-.92 5.14.92.92a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 1 0-1.091-1.028L9.477 9.417l-.485-.486z"/>
                        </svg>
                        Seleccionar Todos
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllTrips">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-x-lg me-1" viewBox="0 0 16 16">
                            <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
                        </svg>
                        Deseleccionar Todos
                    </button>
                </div>
            </div>

            <!-- Footer del panel -->
            <div class="mt-4 pt-3 border-top text-center">
                <small class="text-muted">
                    <a href="admin/" class="text-decoration-none">Panel de Administración</a>
                </small>
            </div>
        </div>
    </div>

    <!-- Leyenda flotante -->
    <div class="legend-card card shadow-sm">
        <div class="card-body p-2">
            <h6 class="small mb-2 fw-bold">Leyenda de Transporte</h6>
            <div id="legendItems">
                <!-- Se llenará dinámicamente con JavaScript -->
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="<?= ASSETS_URL ?>/vendor/jquery/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap 5 JS -->
    <script src="<?= ASSETS_URL ?>/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- Leaflet JS -->
    <script src="<?= ASSETS_URL ?>/vendor/leaflet/js/leaflet.js"></script>
    <script src="<?= ASSETS_URL ?>/vendor/leaflet/plugins/leaflet.markercluster.js"></script>
    <script src="<?= ASSETS_URL ?>/vendor/leaflet/plugins/leaflet.curve.js"></script>
    
    <!-- API URL Config -->
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const API_URL = '<?= BASE_URL ?>/api/get_all_data.php';
    </script>
    
    <!-- Public Map JS -->
    <script src="<?= ASSETS_URL ?>/js/public_map.js?v=2"></script>
    
    <!-- Lightbox para imágenes -->
    <div id="imageLightbox" class="lightbox" style="display: none;">
        <div class="lightbox-content">
            <img id="lightboxImage" src="" alt="">
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="map-footer">
        <span>TravelMap - Creado por Fabio Baccaglioni</span>
        <a href="https://github.com/fabiomb/TravelMap" target="_blank" rel="noopener noreferrer" class="github-link" title="Ver en GitHub">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8"/>
            </svg>
        </a>
    </footer>
</body>
</html>
