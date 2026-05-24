<?php
/**
 * Importar Rutas desde BRouter CSV
 *
 * Permite importar rutas generadas con brouter.de:
 * tren, auto, barco, bicicleta, caminata, bus, etc.
 *
 * Coloca este archivo en: admin/import_brouter.php
 *
 * Schema de la tabla `routes` (verificado contra dump real):
 *   id, trip_id, transport_type ENUM(...), geojson_data longtext NOT NULL,
 *   is_round_trip tinyint(1), distance_meters int UNSIGNED, color varchar(7),
 *   created_at, updated_at
 * NO existen columnas `name` ni `date` en routes.
 * Esos datos van en geojson_data.properties (igual que el resto de rutas).
 */

// ob_start al inicio: evita que cualquier output de los includes
// (notices, warnings, BOM) corrompa las respuestas JSON del endpoint de preview.
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/Route.php';
require_once __DIR__ . '/../src/helpers/BRouterParser.php';

$db        = getDB();
$tripModel = new Trip();
// $trips se carga justo antes del HTML — no en la ruta JSON que hace exit early.

// ─── Constantes ───────────────────────────────────────────────────────────────
defined('MAX_CSV_BYTES')    || define('MAX_CSV_BYTES',    5 * 1024 * 1024); // 5 MB
defined('MAX_WAYPOINTS')    || define('MAX_WAYPOINTS',    50_000);
defined('MAX_DIST_METERS')  || define('MAX_DIST_METERS',  4_294_967_295);   // int UNSIGNED max
// ENUM exacto de la columna transport_type en la tabla routes
defined('ALLOWED_TRANSPORT') || define('ALLOWED_TRANSPORT',
    ['plane', 'car', 'bike', 'walk', 'ship', 'train', 'bus', 'aerial']
);

// ─── CSRF ─────────────────────────────────────────────────────────────────────
// session_start() ya fue llamado dentro de auth.php → start_session()
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$message     = '';
$messageType = '';

// ═══════════════════════════════════════════════════════════════════════════════
// PROCESAMIENTO POST
// ═══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ── Paso 1: Preview — responde JSON ────────────────────────────────────────
    if ($_POST['action'] === 'preview') {

        ob_clean(); // limpiar cualquier output buffereado antes de enviar JSON
        header('Content-Type: application/json');

        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido.']);
            exit;
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Error al subir el archivo.']);
            exit;
        }

        if ($_FILES['csv_file']['size'] > MAX_CSV_BYTES) {
            echo json_encode(['success' => false, 'error' => 'El archivo supera el límite de 5 MB.']);
            exit;
        }

        // Validar MIME real — no confiar en extensión ni en $_FILES['type']
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['csv_file']['tmp_name']);
        if (!in_array($mimeType, ['text/plain', 'text/csv', 'application/csv', 'application/octet-stream'], true)) {
            echo json_encode(['success' => false, 'error' => 'El archivo debe ser un CSV de texto plano.']);
            exit;
        }

        $result = BRouterParser::parseFromFile($_FILES['csv_file']['tmp_name']);

        if (!$result['success']) {
            echo json_encode($result);
            exit;
        }

        // Solo lo necesario para el INSERT va a sesión — el GeoJSON completo NO al cliente
        $_SESSION['pending_brouter_import'] = [
            'geojson_data'    => $result['geojson_data'],
            'distance_meters' => $result['distance_meters'],
            'waypoints_count' => $result['waypoints_count'],
            'distance_km'     => $result['distance_km'],
            'duration_min'    => $result['duration_min'],
            'rail_type'       => $result['rail_type'],
        ];

        unset($result['geojson_data']); // no sale al cliente
        echo json_encode($result);
        exit;
    }

    // ── Paso 2: Import — inserta en routes ────────────────────────────────────
    if ($_POST['action'] === 'import') {

        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            $message     = 'Token de seguridad inválido. Recargá la página e intentá de nuevo.';
            $messageType = 'danger';

        } elseif (!isset($_SESSION['pending_brouter_import'])) {
            $message     = 'No hay datos de importación pendientes. Subí el CSV de nuevo.';
            $messageType = 'danger';

        } else {
            $tripId      = (int)($_POST['trip_id'] ?? 0);
            $rawName     = trim($_POST['route_name'] ?? '');
            $routeName   = mb_substr(mb_convert_encoding($rawName, 'UTF-8', 'UTF-8'), 0, 200, 'UTF-8');
            $routeDate   = trim($_POST['route_date'] ?? '');
            $isRoundTrip = isset($_POST['is_round_trip']) ? 1 : 0;

            // Color: exactamente varchar(7) = #RRGGBB
            $routeColor = trim($_POST['route_color'] ?? '#3388ff');
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $routeColor)) {
                $routeColor = '#3388ff';
            }

            // Fecha: validar formato estricto YYYY-MM-DD
            $dateObj = DateTime::createFromFormat('Y-m-d', $routeDate);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $routeDate) {
                $routeDate = '';
            }

            // transport_type: whitelist exacta del ENUM de la tabla
            $transportType = trim($_POST['transport_type'] ?? 'train');
            if (!in_array($transportType, ALLOWED_TRANSPORT, true)) {
                $transportType = 'train';
            }

            if (!$tripId || !$routeName) {
                $message     = 'Debés indicar el viaje y el nombre del trayecto.';
                $messageType = 'warning';

            } else {
                // [FIX #1 + #2] Un solo try/catch cubre prepare() + execute() del tripCheck
                // y prepare() + execute() del INSERT — cualquier fallo de BD queda contenido.
                try {
                    $tripCheck = $db->prepare("SELECT id FROM trips WHERE id = :id LIMIT 1");
                    $tripCheck->execute([':id' => $tripId]);

                    if (!$tripCheck->fetch()) {
                        $message     = 'El viaje seleccionado no existe.';
                        $messageType = 'danger';

                    } else {
                        $pending    = $_SESSION['pending_brouter_import'];
                        $geojsonArr = json_decode($pending['geojson_data'], true);

                        if ($geojsonArr === null || json_last_error() !== JSON_ERROR_NONE) {
                            $message     = 'Error al procesar los datos de la ruta. Subí el CSV de nuevo.';
                            $messageType = 'danger';
                            unset($_SESSION['pending_brouter_import']);

                        } else {
                            // Enriquecer propiedades con nombre y fecha
                            // (no hay columnas name/date en routes — van en geojson_data.properties)
                            if (!is_array($geojsonArr['properties'])) {
                                $geojsonArr['properties'] = [];
                            }
                            $geojsonArr['properties']['name'] = $routeName;
                            if ($routeDate) {
                                $geojsonArr['properties']['date'] = $routeDate;
                            }

                            $geojsonFinal = json_encode($geojsonArr, JSON_UNESCAPED_UNICODE);
                            if ($geojsonFinal === false) {
                                $message     = 'Error al serializar los datos. Subí el CSV de nuevo.';
                                $messageType = 'danger';
                                unset($_SESSION['pending_brouter_import']);

                            } else {
                                // Validar rango de distance_meters para int UNSIGNED
                                $distanceMeters = max(0, min((int)$pending['distance_meters'], MAX_DIST_METERS));

                                // Si is_round_trip, duplicar la distancia (igual que Route::create())
                                if ($isRoundTrip) {
                                    $distanceMeters = min($distanceMeters * 2, MAX_DIST_METERS);
                                }

                                // INSERT alineado 100% con la schema real de routes
                                $stmt = $db->prepare("
                                    INSERT INTO routes
                                        (trip_id, transport_type, geojson_data, is_round_trip, distance_meters, color, created_at)
                                    VALUES
                                        (:trip_id, :transport_type, :geojson_data, :is_round_trip, :distance_meters, :color, NOW())
                                ");
                                $stmt->execute([
                                    ':trip_id'         => $tripId,
                                    ':transport_type'  => $transportType,
                                    ':geojson_data'    => $geojsonFinal,
                                    ':is_round_trip'   => $isRoundTrip,
                                    ':distance_meters' => $distanceMeters,
                                    ':color'           => $routeColor,
                                ]);

                                unset($_SESSION['pending_brouter_import']);

                                // Rotar CSRF tras acción exitosa (evita replay con el mismo token)
                                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                                $csrfToken = $_SESSION['csrf_token'];

                                $msgTripsLabel = htmlspecialchars(
                                    __('navigation.trips') ?: 'Ver viaje',
                                    ENT_QUOTES, 'UTF-8'
                                );
                                $message = (__('import.brouter_success') ?: 'Ruta importada correctamente.')
                                    . ' <a href="' . BASE_URL . '/admin/trips.php?id=' . (int)$tripId . '">'
                                    . $msgTripsLabel . '</a>';
                                $messageType = 'success';
                            }
                        }
                    }

                } catch (\PDOException $e) {
                    // No exponer detalles del error de BD al usuario
                    error_log('import_brouter DB error: ' . $e->getMessage());
                    $message     = 'Error en la base de datos. Intentá de nuevo.';
                    $messageType = 'danger';
                    unset($_SESSION['pending_brouter_import']);
                }
            }
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// RENDER
// header.php abre: <div class="admin-wrapper"><aside>...</aside><main class="admin-main"><div class="admin-content">
// footer.php cierra: </div><!-- /.admin-content --><footer>...</footer></main></div>
// Bootstrap + jQuery los carga footer.php — NO repetir aquí.
// ═══════════════════════════════════════════════════════════════════════════════
// Cargar viajes solo para el HTML (no se necesita en las respuestas JSON)
$trips = $tripModel->getAll('start_date DESC');

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #preview-map {
        height: 380px;
        border-radius: var(--radius-md);
        border: 1px solid var(--admin-border);
        margin-bottom: 16px;
    }
    .brouter-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 16px;
    }
    @media (max-width: 768px) {
        .brouter-stat-grid { grid-template-columns: repeat(2, 1fr); }
    }
    .brouter-stat {
        background: var(--admin-bg-alt);
        border-radius: var(--radius-md);
        padding: 12px 16px;
        text-align: center;
    }
    .brouter-stat-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--admin-text);
    }
    .brouter-stat-label {
        font-size: 0.72rem;
        color: var(--admin-text-muted);
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-top: 2px;
    }
    .drop-zone {
        border: 2px dashed var(--admin-border);
        border-radius: var(--radius-md);
        padding: 2.5rem 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s, background .2s;
    }
    .drop-zone:hover, .drop-zone.drag-over {
        border-color: var(--admin-primary);
        background: var(--admin-bg-alt);
    }
    .drop-zone-icon { font-size: 2.2rem; margin-bottom: 8px; }
    .step { display: none; }
    .step.active { display: block; }
</style>

<!-- Page Header — clase confirmada en admin.css y en import_flights.php -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 17l4-8 4 4 4-6 4 4"/>
                <circle cx="3" cy="17" r="1.5"/>
                <circle cx="21" cy="11" r="1.5"/>
            </svg>
            <?= __('navigation.import_brouter') ?: 'Importar Rutas BRouter' ?>
        </h1>
        <p class="page-subtitle">
            <?= __('import.brouter_description') ?: 'Importa rutas generadas con' ?>
            <a href="https://brouter.de/" target="_blank" rel="noopener noreferrer">brouter.de</a>:
            <?= __('import.brouter_types') ?: 'tren, auto, barco, bicicleta, caminata, bus, etc.' ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/admin/" class="btn btn-secondary btn-sm">
            ← <?= __('common.back') ?: 'Volver' ?>
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?> alert-dismissible" role="alert">
        <?= $message ?>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<!-- ═══ PASO 1: Subir CSV ════════════════════════════════════════════════════ -->
<div id="step-1" class="step active">
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <?= __('import.step1') ?: 'Paso 1' ?> —
                <?= __('import.upload_csv') ?: 'Subir CSV de BRouter' ?>
            </h3>
        </div>
        <div class="admin-card-body">
            <div style="display: grid; grid-template-columns: 1fr 280px; gap: 24px;">
                <div>
                    <div class="drop-zone" id="drop-zone"
                         onclick="document.getElementById('csv_file').click()">
                        <div class="drop-zone-icon">📁</div>
                        <p style="font-weight: 600; margin-bottom: 4px;">
                            <?= __('import.drop_csv') ?: 'Arrastrá el CSV aquí o hacé clic para seleccionar' ?>
                        </p>
                        <p style="color: var(--admin-text-muted); font-size: 0.85rem; margin: 0;">
                            <?= __('import.brouter_hint') ?: 'Exportado desde brouter.de — máximo 5 MB.' ?>
                        </p>
                        <input type="file" id="csv_file" name="csv_file"
                               accept=".csv,text/plain,text/csv" style="display: none;">
                        <p id="file-name" style="margin-top: 10px; font-weight: 600; color: var(--admin-primary);"></p>
                    </div>
                </div>
                <div>
                    <p style="font-weight: 600; margin-bottom: 8px; font-size: 0.9rem;">
                        <?= __('import.brouter_how') ?: '¿Cómo exportar desde BRouter?' ?>
                    </p>
                    <ol style="padding-left: 1.2rem; font-size: 0.85rem; color: var(--admin-text-muted); margin: 0;">
                        <li style="margin-bottom: 6px;">
                            <?= __('import.brouter_step1') ?: 'Abrí' ?>
                            <a href="https://brouter.de/brouter-web/" target="_blank" rel="noopener"
                               style="color: var(--admin-info);">brouter.de</a>
                        </li>
                        <li style="margin-bottom: 6px;"><?= __('import.brouter_step2') ?: 'Trazá tu ruta en el mapa' ?></li>
                        <li style="margin-bottom: 6px;"><?= __('import.brouter_step3') ?: 'Clic en Export → CSV' ?></li>
                        <li><?= __('import.brouter_step4') ?: 'Subí ese archivo aquí' ?></li>
                    </ol>
                </div>
            </div>
        </div>
        <div class="admin-card-footer">
            <button type="button" id="btn-preview" class="btn btn-primary" disabled>
                <span id="spinner-preview" style="display: none;">⏳ </span>
                <?= __('import.preview') ?: 'Vista previa' ?> →
            </button>
        </div>
    </div>
</div>

<!-- ═══ PASO 2: Preview ══════════════════════════════════════════════════════ -->
<div id="step-2" class="step">
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <?= __('import.step2') ?: 'Paso 2' ?> —
                <?= __('import.preview') ?: 'Previsualización de la ruta' ?>
            </h3>
        </div>
        <div class="admin-card-body">
            <div class="brouter-stat-grid">
                <div class="brouter-stat">
                    <div class="brouter-stat-value" id="stat-distance">—</div>
                    <div class="brouter-stat-label"><?= __('import.distance_km') ?: 'Distancia (km)' ?></div>
                </div>
                <div class="brouter-stat">
                    <div class="brouter-stat-value" id="stat-duration">—</div>
                    <div class="brouter-stat-label"><?= __('import.duration_min') ?: 'Duración (min)' ?></div>
                </div>
                <div class="brouter-stat">
                    <div class="brouter-stat-value" id="stat-waypoints">—</div>
                    <div class="brouter-stat-label"><?= __('import.waypoints') ?: 'Waypoints' ?></div>
                </div>
                <div class="brouter-stat">
                    <div class="brouter-stat-value" id="stat-type">—</div>
                    <div class="brouter-stat-label"><?= __('import.rail_subtype') ?: 'Subtipo de vía' ?></div>
                </div>
            </div>
            <div id="preview-map"></div>
        </div>
        <div class="admin-card-footer">
            <button type="button" id="btn-back-1" class="btn btn-secondary">
                ← <?= __('import.upload_another') ?: 'Subir otro CSV' ?>
            </button>
            <button type="button" id="btn-go-step3" class="btn btn-primary">
                <?= __('common.next') ?: 'Continuar' ?> →
            </button>
        </div>
    </div>
</div>

<!-- ═══ PASO 3: Confirmación ═════════════════════════════════════════════════ -->
<div id="step-3" class="step">
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <?= __('import.step3') ?: 'Paso 3' ?> —
                <?= __('import.select_trip_confirm') ?: 'Confirmar importación' ?>
            </h3>
        </div>
        <form method="POST">
            <div class="admin-card-body">
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">

                    <!-- Viaje destino -->
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('import.select_trip') ?: 'Viaje al que pertenece' ?> <span style="color:var(--admin-danger)">*</span>
                        </label>
                        <select name="trip_id" class="form-select" required>
                            <option value="">— <?= __('import.select_trip') ?: 'Seleccionar viaje' ?> —</option>
                            <?php foreach ($trips as $trip): ?>
                                <option value="<?= (int)$trip['id'] ?>">
                                    <?= htmlspecialchars($trip['title'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($trip['start_date'])): ?>
                                        (<?= htmlspecialchars(
                                            date('d/m/Y', strtotime($trip['start_date'])),
                                            ENT_QUOTES, 'UTF-8'
                                        ) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: var(--admin-text-muted); font-size: 0.8rem;">
                            <a href="<?= BASE_URL ?>/admin/trips.php?action=new" target="_blank">
                                <?= __('import.or_create_new') ?: 'Crear un viaje nuevo' ?>
                            </a>
                            <?= __('import.and_refresh') ?: 'y refrescar esta página.' ?>
                        </small>
                    </div>

                    <!-- Nombre del trayecto → geojson_data.properties.name -->
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('common.name') ?: 'Nombre del trayecto' ?> <span style="color:var(--admin-danger)">*</span>
                        </label>
                        <input type="text" name="route_name" id="route_name"
                               class="form-control" required maxlength="200"
                               placeholder="<?= __('import.brouter_name_placeholder') ?: 'Ej: Tren Atenas → Spata' ?>">
                        <small style="color: var(--admin-text-muted); font-size: 0.8rem;">
                            <?= __('import.name_stored_in_metadata') ?: 'Se guarda en los metadatos de la ruta.' ?>
                        </small>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px;">

                    <!-- transport_type: ENUM exacto de la tabla routes -->
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('routes.transport_type') ?: 'Tipo de transporte' ?>
                        </label>
                        <select name="transport_type" id="transport_type" class="form-select">
                            <option value="train">🚂 <?= __('map.transport_train') ?: 'Tren / Metro / Tranvía' ?></option>
                            <option value="car">🚗 <?= __('map.transport_car') ?: 'Auto' ?></option>
                            <option value="bike">🚲 <?= __('map.transport_bike') ?: 'Bicicleta' ?></option>
                            <option value="walk">🚶 <?= __('map.transport_walk') ?: 'Caminata' ?></option>
                            <option value="ship">⛵ <?= __('map.transport_ship') ?: 'Barco / Ferry' ?></option>
                            <option value="bus">🚌 <?= __('map.transport_bus') ?: 'Bus' ?></option>
                            <option value="aerial">🚡 <?= __('map.transport_aerial') ?: 'Teleférico / Aéreo' ?></option>
                        </select>
                    </div>

                    <!-- Color: varchar(7), se auto-completa al cambiar transport_type -->
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('common.color') ?: 'Color de la ruta' ?>
                        </label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="color" name="route_color" id="route_color"
                                   class="form-control" style="width: 56px; padding: 2px 4px; cursor: pointer;"
                                   value="#FF8800">
                            <span style="font-size: 0.8rem; color: var(--admin-text-muted);">
                                <?= __('import.color_hint') ?: 'Se sincroniza con el tipo elegido.' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Fecha → geojson_data.properties.date (no existe columna en routes) -->
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('common.date') ?: 'Fecha del viaje' ?>
                        </label>
                        <input type="date" name="route_date" class="form-control"
                               value="<?= date('Y-m-d') ?>">
                        <small style="color: var(--admin-text-muted); font-size: 0.8rem;">
                            <?= __('import.date_in_metadata') ?: 'Se guarda en los metadatos.' ?>
                        </small>
                    </div>

                    <!-- is_round_trip: tinyint(1) -->
                    <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 6px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" name="is_round_trip" value="1"
                                   class="form-check-input" id="is_round_trip">
                            <span><?= __('trips.is_round_trip') ?: 'Ida y vuelta' ?></span>
                        </label>
                    </div>

                </div>
            </div>
            <div class="admin-card-footer">
                <button type="button" id="btn-back-2" class="btn btn-secondary">
                    ← <?= __('import.start_over') ?: 'Volver a la preview' ?>
                </button>
                <button type="submit" class="btn btn-success">
                    ✅ <?= __('import.import') ?: 'Importar ruta' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Leaflet — rutas reales confirmadas en el árbol del proyecto -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/css/leaflet.css">
<script src="<?= ASSETS_URL ?>/vendor/leaflet/js/leaflet.js"></script>

<script>
// Colores que coinciden exactamente con Route::getColorByTransport() y con settings
const TRANSPORT_COLORS = {
    train:  '#FF8800',
    car:    '#4444FF',
    bike:   '#b88907',
    walk:   '#44FF44',
    ship:   '#00AAAA',
    bus:    '#9C27B0',
    aerial: '#E91E63',
    plane:  '#FF4444'
};

const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

let previewMap   = null;
let previewLayer = null;
let startMarker  = null;
let endMarker    = null;

const dropZone   = document.getElementById('drop-zone');
const fileInput  = document.getElementById('csv_file');
const btnPreview = document.getElementById('btn-preview');
const fileNameEl = document.getElementById('file-name');

// ─── Drop zone ────────────────────────────────────────────────────────────────
fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
        fileNameEl.textContent = '📄 ' + fileInput.files[0].name; // textContent: nunca ejecuta HTML
        btnPreview.disabled = false;
    }
});
dropZone.addEventListener('dragover',  e  => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    const dt = new DataTransfer();
    dt.items.add(e.dataTransfer.files[0]);
    fileInput.files = dt.files;
    fileInput.dispatchEvent(new Event('change'));
});

// ─── Paso 1 → 2: Preview ─────────────────────────────────────────────────────
btnPreview.addEventListener('click', () => {
    const spinner = document.getElementById('spinner-preview');
    spinner.style.display = 'inline';
    btnPreview.disabled = true;

    const formData = new FormData();
    formData.append('action',     'preview');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('csv_file',   fileInput.files[0]);

    fetch('', { method: 'POST', body: formData })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(data => {
            spinner.style.display = 'none';
            if (!data.success) {
                alert('Error: ' + data.error); // data.error: string del servidor, alert no ejecuta HTML
                btnPreview.disabled = false;
                return;
            }
            showPreview(data);
        })
        .catch(err => {
            spinner.style.display = 'none';
            alert('Error de conexión: ' + err.message);
            btnPreview.disabled = false;
        });
});

function showPreview(data) {
    // Siempre textContent para datos del servidor — nunca innerHTML
    document.getElementById('stat-distance').textContent  = data.distance_km + ' km';
    document.getElementById('stat-duration').textContent  = data.duration_min + ' min';
    document.getElementById('stat-waypoints').textContent = data.waypoints_count.toLocaleString();
    document.getElementById('stat-type').textContent      = data.rail_type;

    goToStep(2);

    // Nombre sugerido a partir del nombre del archivo, limitado a maxlength del campo
    const fname = fileInput.files[0].name.replace(/\.csv$/i, '').replace(/[_-]/g, ' ');
    document.getElementById('route_name').value = fname.substring(0, 200);

    initPreviewMap(data);
}

function initPreviewMap(data) {
    if (!previewMap) {
        previewMap = L.map('preview-map');
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '© OpenStreetMap © CARTO',
            maxZoom: 19
        }).addTo(previewMap);
    }

    // Limpiar capas anteriores si el usuario vuelve y sube otro CSV
    if (previewLayer) { previewMap.removeLayer(previewLayer); previewLayer = null; }
    if (startMarker)  { previewMap.removeLayer(startMarker);  startMarker  = null; }
    if (endMarker)    { previewMap.removeLayer(endMarker);    endMarker    = null; }

    // BRouter: [lon, lat] → Leaflet necesita [lat, lon]
    const latlngs = data.coordinates.map(c => [c[1], c[0]]);
    const color   = document.getElementById('route_color').value;

    previewLayer = L.polyline(latlngs, { color, weight: 4, opacity: 0.9 }).addTo(previewMap);

    const mkStart = L.divIcon({ html: '🟢', className: '', iconSize: [20, 20] });
    const mkEnd   = L.divIcon({ html: '🔴', className: '', iconSize: [20, 20] });
    startMarker = L.marker(latlngs[0],                  { icon: mkStart }).addTo(previewMap).bindTooltip('Inicio');
    endMarker   = L.marker(latlngs[latlngs.length - 1], { icon: mkEnd   }).addTo(previewMap).bindTooltip('Fin');

    previewMap.fitBounds(previewLayer.getBounds(), { padding: [20, 20] });
}

// ─── Sincronizar color con tipo de transporte ─────────────────────────────────
// Usa los mismos valores que Route::getColorByTransport() y que settings
document.getElementById('transport_type').addEventListener('change', function () {
    const color = TRANSPORT_COLORS[this.value];
    if (color) {
        document.getElementById('route_color').value = color;
        if (previewLayer) previewLayer.setStyle({ color });
    }
});

// ─── Navegación entre pasos ───────────────────────────────────────────────────
function goToStep(n) {
    document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
    document.getElementById('step-' + n).classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.getElementById('btn-go-step3').addEventListener('click', () => goToStep(3));
document.getElementById('btn-back-1').addEventListener('click',   () => {
    goToStep(1);
    btnPreview.disabled = false;
});
document.getElementById('btn-back-2').addEventListener('click',   () => goToStep(2));
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
