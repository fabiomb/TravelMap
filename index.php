<?php
// Cargar configuración para las constantes
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Map - Mis Viajes por el Mundo</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="<?= ASSETS_URL ?>/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/css/leaflet.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/plugins/MarkerCluster.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/plugins/MarkerCluster.Default.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/public_map.css">
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
            <div class="legend-item">
                <div class="legend-line" style="background-color: #FF4444;"></div>
                <small>✈️ Avión</small>
            </div>
            <div class="legend-item">
                <div class="legend-line" style="background-color: #4444FF;"></div>
                <small>🚗 Auto</small>
            </div>
            <div class="legend-item">
                <div class="legend-line" style="background-color: #FF8800;"></div>
                <small>🚂 Tren</small>
            </div>
            <div class="legend-item">
                <div class="legend-line" style="background-color: #00AAAA;"></div>
                <small>🚢 Barco</small>
            </div>
            <div class="legend-item">
                <div class="legend-line" style="background-color: #44FF44;"></div>
                <small>🚶 Caminata</small>
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
    
    <!-- Public Map JS -->
    <script>
       html>
<?php
// Cargar configuración para las constantes
require_once __DIR__ . '/config/config.php';
?>
