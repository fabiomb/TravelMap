<?php
/**
 * Editor de Mapa del Viaje
 * 
 * Permite dibujar rutas y gestionar el mapa del viaje
 */

// Cargar configuración y dependencias ANTES de header.php para permitir redirects
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/Route.php';
require_once __DIR__ . '/../src/models/Point.php';

$tripModel = new Trip();
$routeModel = new Route();
$pointModel = new Point();
$message = '';
$message_type = '';

// Verificar que existe el ID del viaje
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: trips.php');
    exit;
}

$trip_id = (int) $_GET['id'];
$trip = $tripModel->getById($trip_id);

if (!$trip) {
    header('Location: trips.php');
    exit;
}

// Procesar guardado de rutas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['routes_data'])) {
    $routes_json = $_POST['routes_data'];
    
    if (!empty($routes_json)) {
        $routes_array = json_decode($routes_json, true);
        
        if ($routes_array !== null && is_array($routes_array)) {
            // Eliminar rutas existentes
            $routeModel->deleteByTripId($trip_id);
            
            // Crear nuevas rutas
            foreach ($routes_array as $route_data) {
                $routeModel->create([
                    'trip_id' => $trip_id,
                    'transport_type' => $route_data['transport_type'] ?? 'car',
                    'geojson_data' => json_encode($route_data['geojson']),
                    'is_round_trip' => $route_data['is_round_trip'] ?? 0,
                    'color' => $route_data['color'] ?? Route::getColorByTransport($route_data['transport_type'] ?? 'car')
                ]);
            }
            
            $message = __('routes.saved_success');
            $message_type = 'success';
        } else {
            $message = __('routes.error_saving');
            $message_type = 'danger';
        }
    } else {
        // Si está vacío, eliminar todas las rutas
        $routeModel->deleteByTripId($trip_id);
        $message = __('routes.deleted_success');
        $message_type = 'success';
    }
}

// Obtener rutas existentes
$routes = $routeModel->getByTripId($trip_id);

// Obtener puntos del viaje
$points = $pointModel->getAll($trip_id);

// Preparar datos para JavaScript
$routes_js = [];
foreach ($routes as $route) {
    $routes_js[] = [
        'id' => $route['id'],
        'transport_type' => $route['transport_type'],
        'geojson' => json_decode($route['geojson_data'], true),
        'is_round_trip' => (int)$route['is_round_trip'],
        'color' => $route['color']
    ];
}

$points_js = [];
foreach ($points as $point) {
    $points_js[] = [
        'id' => $point['id'],
        'title' => $point['title'],
        'latitude' => (float) $point['latitude'],
        'longitude' => (float) $point['longitude'],
        'type' => $point['type']
    ];
}

$transport_types = Route::getTransportTypes();

// Ahora sí incluir header.php (después de procesar y posibles redirects)
require_once __DIR__ . '/../includes/header.php';

?>

<div class="row mb-3">
    <div class="col-md-8">
        <h1 class="mb-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-map me-2" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15.817.113A.5.5 0 0 1 16 .5v14a.5.5 0 0 1-.402.49l-5 1a.5.5 0 0 1-.196 0L5.5 15.01l-4.902.98A.5.5 0 0 1 0 15.5v-14a.5.5 0 0 1 .402-.49l5-1a.5.5 0 0 1 .196 0L10.5.99l4.902-.98a.5.5 0 0 1 .415.103M10 1.91l-4-.8v12.98l4 .8zm1 12.98 4-.8V1.11l-4 .8zm-6-.8V1.11l-4 .8v12.98z"/>
            </svg>
            <?= __('trips.map_editor') ?> - <?= htmlspecialchars($trip['title']) ?>
        </h1>
        <small class="text-muted"><?= __('trips.draw_routes_on_map') ?></small>
    </div>
    <div class="col-md-4 text-end">
        <a href="trip_form.php?id=<?= $trip_id ?>" class="btn btn-outline-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil me-1" viewBox="0 0 16 16">
                <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/>
            </svg>
            <?= __('trips.edit_trip') ?>
        </a>
        <a href="trips.php" class="btn btn-outline-secondary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left me-1" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/>
            </svg>
            <?= __('common.back') ?>
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Mapa -->
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-globe me-2" viewBox="0 0 16 16">
                            <path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m7.5-6.923c-.67.204-1.335.82-1.887 1.855A8 8 0 0 0 5.145 4H7.5zM4.09 4a9.3 9.3 0 0 1 .64-1.539 7 7 0 0 1 .597-.933A7.03 7.03 0 0 0 2.255 4zm-.582 3.5c.03-.877.138-1.718.312-2.5H1.674a7 7 0 0 0-.656 2.5zM4.847 5a12.5 12.5 0 0 0-.338 2.5H7.5V5zM8.5 5v2.5h3.99a12.5 12.5 0 0 0-.337-2.5zM4.51 8.5a12.5 12.5 0 0 0 .337 2.5H7.5V8.5zm3.99 0V11h2.653c.187-.765.306-1.608.338-2.5zM5.145 12q.208.58.468 1.068c.552 1.035 1.218 1.65 1.887 1.855V12zm.182 2.472a7 7 0 0 1-.597-.933A9.3 9.3 0 0 1 4.09 12H2.255a7 7 0 0 0 3.072 2.472M3.82 11a13.7 13.7 0 0 1-.312-2.5h-2.49c.062.89.291 1.733.656 2.5zm6.853 3.472A7 7 0 0 0 13.745 12H11.91a9.3 9.3 0 0 1-.64 1.539 7 7 0 0 1-.597.933M8.5 12v2.923c.67-.204 1.335-.82 1.887-1.855q.26-.487.468-1.068zm3.68-1h2.146c.365-.767.594-1.61.656-2.5h-2.49a13.7 13.7 0 0 1-.312 2.5m2.802-3.5a7 7 0 0 0-.656-2.5H12.18c.174.782.282 1.623.312 2.5zM11.27 2.461c.247.464.462.98.64 1.539h1.835a7 7 0 0 0-3.072-2.472c.218.284.418.598.597.933M10.855 4a8 8 0 0 0-.468-1.068C9.835 1.897 9.17 1.282 8.5 1.077V4z"/>
                        </svg>
                        <?= __('map.interactive_map') ?>
                    </h5>
                    <button type="button" class="btn btn-sm btn-danger" id="clearAllRoutes">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-trash me-1" viewBox="0 0 16 16">
                            <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                            <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                        </svg>
                        <?= __('map.clear_all') ?>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Buscador de lugares -->
                <div class="input-group mb-3">
                    <span class="input-group-text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                        </svg>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           id="placeSearch" 
                           placeholder="<?= __('map.search_location') ?>..."
                           autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                        <?= __('common.search') ?>
                    </button>
                </div>
                <div id="searchResults" class="list-group mb-3" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                
                <div id="map" style="height: 550px; width: 100%;"></div>
            </div>
        </div>

        <!-- Routes List -->
        <div class="card border-0 shadow-sm mb-3" id="routesListCard" style="display: none;">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-list-ul me-1" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
                    </svg>
                    <?= __('map.routes') ?> <span id="routesCount">(0)</span>
                </h6>
                <small class="text-muted"><?= __('map.click_to_edit_routes') ?></small>
            </div>
            <div class="card-body p-2" id="routesList">
                <!-- Routes list rendered by JavaScript -->
            </div>
        </div>

        <!-- Formulario para guardar -->
        <form method="POST" action="" id="routesForm">
            <input type="hidden" name="routes_data" id="routes_data">
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                        <circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="19" r="3"></circle>
                        <path d="M12 5H8.5C6.567 5 5 6.567 5 8.5C5 10.433 6.567 12 8.5 12H15.5C17.433 12 19 13.567 19 15.5C19 17.433 17.433 19 15.5 19H12"></path>
                    </svg>
                    <?= __('map.save_trip_routes') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Panel de ayuda -->
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-info-circle me-2" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                        <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533z"/>
                        <path d="M9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
                    </svg>
                    <?= __('map.instructions') ?>
                </h5>
            </div>
            <div class="card-body">
                <h6><?= __('map.draw_routes') ?></h6>
                <ol class="small">
                    <li><?= __('map.instruction_click_polyline') ?> <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bezier2" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1 2.5A1.5 1.5 0 0 1 2.5 1h1A1.5 1.5 0 0 1 5 2.5h4.134a1 1 0 1 1 0 1h-2.01q.269.27.484.605C8.246 5.097 8.5 6.459 8.5 8c0 1.993.257 3.092.713 3.7.356.476.895.721 1.787.784A1.5 1.5 0 0 1 12.5 11h1a1.5 1.5 0 0 1 1.5 1.5v1a1.5 1.5 0 0 1-1.5 1.5h-1a1.5 1.5 0 0 1-1.5-1.5H6.866a1 1 0 1 1 0-1h1.711a3 3 0 0 1-.165-.2C7.743 11.407 7.5 10.007 7.5 8c0-1.46-.246-2.597-.733-3.355-.39-.605-.952-1-1.767-1.112A1.5 1.5 0 0 1 3.5 5h-1A1.5 1.5 0 0 1 1 3.5zM2.5 2a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm10 10a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/></svg></li>
                    <li><?= __('map.instruction_click_map') ?></li>
                    <li><?= __('map.instruction_double_click') ?></li>
                    <li><?= __('map.instruction_select_transport') ?></li>
                    <li><?= __('map.instruction_repeat') ?></li>
                </ol>

                <h6 class="mt-3"><?= __('map.edit_routes') ?></h6>
                <ul class="small">
                    <li><?= __('map.instruction_click_edit') ?> <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg></li>
                    <li><?= __('map.instruction_drag_points') ?></li>
                    <li><?= __('map.instruction_save_changes') ?></li>
                </ul>

                <h6 class="mt-3"><?= __('map.delete') ?></h6>
                <ul class="small">
                    <li><?= __('map.instruction_trash_button') ?> <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></li>
                </ul>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0"><?= __('map.transport_types') ?></h6>
            </div>
            <div class="card-body" id="transportLegend">
                <!-- La leyenda se renderiza dinámicamente con JavaScript -->
            </div>
        </div>
        
        <?php if (!empty($points)): ?>
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><?= __('map.trip_points') ?> (<?= count($points) ?>)</h6>
                </div>
                <div class="card-body p-2" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($points as $point): ?>
                        <div class="small border-bottom py-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-geo-alt-fill text-danger" viewBox="0 0 16 16">
                                <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/>
                            </svg>
                            <?= htmlspecialchars($point['title']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- jQuery primero (requerido por trip_map.js) -->
<script src="<?= ASSETS_URL ?>/vendor/jquery/jquery-3.7.1.min.js"></script>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/css/leaflet.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/plugins/leaflet.draw.css">

<!-- Leaflet JS -->
<script src="<?= ASSETS_URL ?>/vendor/leaflet/js/leaflet.js"></script>
<script src="<?= ASSETS_URL ?>/vendor/leaflet/plugins/leaflet.draw.js"></script>

<script>
// Configuración base
const BASE_URL = '<?= BASE_URL ?>';

// Datos del viaje
const tripId = <?= $trip_id ?>;
const tripColor = '<?= htmlspecialchars($trip['color_hex']) ?>';
const existingRoutes = <?= json_encode($routes_js) ?>;
const existingPoints = <?= json_encode($points_js) ?>;
const transportTypes = <?= json_encode($transport_types) ?>;

// Traducciones para JS
const PHP_TRANSLATIONS = <?= $lang->getTranslationsAsJson() ?>;
</script>

<!-- i18n JS -->
<script src="<?= ASSETS_URL ?>/js/i18n.js?v=<?php echo $version; ?>"></script>

<script>
// Inicializar i18n
i18n.init();
</script>


<!-- Script del mapa -->
<script src="<?= ASSETS_URL ?>/js/trip_map.js?v=<?php echo $version; ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
