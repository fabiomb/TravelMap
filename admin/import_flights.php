<?php
/**
 * Importador de Vuelos desde FlightRadar/FlightDiary CSV
 * 
 * Permite subir un CSV exportado de FlightRadar y crear viajes con rutas automáticamente
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/Route.php';
require_once __DIR__ . '/../src/models/Settings.php';

// Obtener conexión a la base de datos
$conn = getDB();
$tripModel = new Trip();
$routeModel = new Route();
$settingsModel = new Settings($conn);

// Cargar base de datos de aeropuertos
$airportsFile = __DIR__ . '/../data/airports.json';
$airports = [];
if (file_exists($airportsFile)) {
    $airports = json_decode(file_get_contents($airportsFile), true) ?? [];
}

// Configuración
$planeColor = $settingsModel->get('transport_color_plane') ?? '#FF4444';
$gapDays = 7; // Días de diferencia para agrupar vuelos en viajes

// Variables para resultados
$importResults = null;
$previewData = null;
$errors = [];

/**
 * Extrae el código IATA de una cadena del tipo "City / Airport (IATA/ICAO)"
 */
function extractIataCode($airportString) {
    if (preg_match('/\(([A-Z]{3})\//', $airportString, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Extrae el nombre de la ciudad de una cadena del tipo "City / Airport (IATA/ICAO)"
 */
function extractCityName($airportString) {
    if (preg_match('/^([^\/]+)/', $airportString, $matches)) {
        return trim($matches[1]);
    }
    return $airportString;
}

/**
 * Parsea el archivo CSV y retorna los vuelos
 */
function parseFlightsCSV($filepath, $airports) {
    $flights = [];
    $missingAirports = [];
    
    if (($handle = fopen($filepath, "r")) !== FALSE) {
        $header = null;
        $lineNum = 0;
        
        while (($row = fgetcsv($handle, 1000, ",", '"', "\\")) !== FALSE) {
            $lineNum++;
            
            // Saltar líneas vacías o la primera línea vacía
            if (empty($row) || (count($row) === 1 && empty($row[0]))) {
                continue;
            }
            
            // Primera línea con datos es el header
            if ($header === null) {
                $header = $row;
                continue;
            }
            
            // Mapear columnas
            $data = array_combine($header, $row);
            if (!$data) {
                continue;
            }
            
            $date = $data['Date'] ?? null;
            $from = $data['From'] ?? null;
            $to = $data['To'] ?? null;
            $flightNumber = $data['Flight number'] ?? '';
            $airline = $data['Airline'] ?? '';
            $note = $data['Note'] ?? '';
            
            if (!$date || !$from || !$to) {
                continue;
            }
            
            // Extraer códigos IATA
            $fromIata = extractIataCode($from);
            $toIata = extractIataCode($to);
            
            if (!$fromIata || !$toIata) {
                continue;
            }
            
            // Verificar que tenemos coordenadas para ambos aeropuertos
            $fromCoords = $airports[$fromIata] ?? null;
            $toCoords = $airports[$toIata] ?? null;
            
            if (!$fromCoords) {
                $missingAirports[$fromIata] = extractCityName($from);
            }
            if (!$toCoords) {
                $missingAirports[$toIata] = extractCityName($to);
            }
            
            if (!$fromCoords || !$toCoords) {
                continue;
            }
            
            $flights[] = [
                'date' => $date,
                'from_iata' => $fromIata,
                'to_iata' => $toIata,
                'from_city' => extractCityName($from),
                'to_city' => extractCityName($to),
                'from_coords' => $fromCoords,
                'to_coords' => $toCoords,
                'flight_number' => $flightNumber,
                'airline' => $airline,
                'note' => $note
            ];
        }
        fclose($handle);
    }
    
    // Ordenar por fecha
    usort($flights, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    return [
        'flights' => $flights,
        'missing_airports' => $missingAirports
    ];
}

/**
 * Agrupa vuelos consecutivos (dentro de X días) en viajes
 */
function groupFlightsIntoTrips($flights, $gapDays = 7) {
    if (empty($flights)) {
        return [];
    }
    
    $trips = [];
    $currentTrip = [$flights[0]];
    
    for ($i = 1; $i < count($flights); $i++) {
        $prevDate = strtotime($flights[$i - 1]['date']);
        $currDate = strtotime($flights[$i]['date']);
        $daysDiff = ($currDate - $prevDate) / (60 * 60 * 24);
        
        if ($daysDiff <= $gapDays) {
            // Mismo viaje
            $currentTrip[] = $flights[$i];
        } else {
            // Nuevo viaje
            $trips[] = $currentTrip;
            $currentTrip = [$flights[$i]];
        }
    }
    
    // Agregar el último viaje
    if (!empty($currentTrip)) {
        $trips[] = $currentTrip;
    }
    
    return $trips;
}

/**
 * Genera el título del viaje basado en los vuelos
 */
function generateTripTitle($flights) {
    $firstFlight = $flights[0];
    $lastFlight = $flights[count($flights) - 1];
    
    $startCity = $firstFlight['from_city'];
    $endCity = $lastFlight['to_city'];
    
    $startDate = new DateTime($firstFlight['date']);
    $endDate = new DateTime($lastFlight['date']);
    
    // Formato de fecha
    $months = ['Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Abr', 
               'May' => 'May', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
               'Sep' => 'Sep', 'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic'];
    
    if ($startDate->format('Y-m') === $endDate->format('Y-m')) {
        $dateRange = $startDate->format('M Y');
    } else if ($startDate->format('Y') === $endDate->format('Y')) {
        $dateRange = $startDate->format('M') . '-' . $endDate->format('M') . ' ' . $startDate->format('Y');
    } else {
        $dateRange = $startDate->format('M Y') . ' - ' . $endDate->format('M Y');
    }
    
    // Traducir meses
    foreach ($months as $en => $es) {
        $dateRange = str_replace($en, $es, $dateRange);
    }
    
    if ($startCity === $endCity) {
        return "$startCity ($dateRange)";
    }
    
    return "$startCity → $endCity ($dateRange)";
}

/**
 * Genera un color aleatorio para el viaje
 */
function generateTripColor() {
    $colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
        '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
        '#F8B500', '#00CED1', '#FF69B4', '#32CD32', '#FF4500'
    ];
    return $colors[array_rand($colors)];
}

/**
 * Crea el GeoJSON para una ruta de vuelo
 */
function createFlightGeoJSON($flight) {
    return json_encode([
        'type' => 'Feature',
        'properties' => [
            'from' => $flight['from_city'],
            'to' => $flight['to_city'],
            'flight' => $flight['flight_number']
        ],
        'geometry' => [
            'type' => 'LineString',
            'coordinates' => [
                [$flight['from_coords']['lng'], $flight['from_coords']['lat']],
                [$flight['to_coords']['lng'], $flight['to_coords']['lat']]
            ]
        ]
    ]);
}

// Procesar subida de archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Paso 1: Preview
        if ($_POST['action'] === 'preview' && isset($_FILES['csv_file'])) {
            $file = $_FILES['csv_file'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Error al subir el archivo: ' . $file['error'];
            } elseif ($file['type'] !== 'text/csv' && !str_ends_with($file['name'], '.csv')) {
                $errors[] = 'El archivo debe ser un CSV válido';
            } else {
                // Parsear CSV
                $parseResult = parseFlightsCSV($file['tmp_name'], $airports);
                $flights = $parseResult['flights'];
                $missingAirports = $parseResult['missing_airports'];
                
                if (empty($flights)) {
                    $errors[] = 'No se encontraron vuelos válidos en el archivo';
                } else {
                    // Agrupar en viajes
                    $tripGroups = groupFlightsIntoTrips($flights, $gapDays);
                    
                    // Guardar datos en sesión para el paso de importación
                    $_SESSION['import_flights_data'] = $flights;
                    $_SESSION['import_trips_groups'] = $tripGroups;
                    
                    $previewData = [
                        'total_flights' => count($flights),
                        'total_trips' => count($tripGroups),
                        'missing_airports' => $missingAirports,
                        'trips' => []
                    ];
                    
                    foreach ($tripGroups as $group) {
                        $previewData['trips'][] = [
                            'title' => generateTripTitle($group),
                            'flights_count' => count($group),
                            'start_date' => $group[0]['date'],
                            'end_date' => $group[count($group) - 1]['date'],
                            'flights' => $group
                        ];
                    }
                }
            }
        }
        
        // Paso 2: Importar
        if ($_POST['action'] === 'import') {
            $tripsToImport = [];
            
            // Check if we have modified trips from the form (user may have merged trips)
            if (!empty($_POST['modified_trips'])) {
                $tripsToImport = json_decode($_POST['modified_trips'], true);
            } elseif (isset($_SESSION['import_trips_groups'])) {
                // Fallback to session data (convert to same format)
                foreach ($_SESSION['import_trips_groups'] as $group) {
                    $tripsToImport[] = [
                        'title' => generateTripTitle($group),
                        'start_date' => $group[0]['date'],
                        'end_date' => $group[count($group) - 1]['date'],
                        'flights' => $group
                    ];
                }
            }
            
            if (empty($tripsToImport)) {
                $errors[] = 'No hay datos de viajes para importar. Por favor, sube el archivo CSV nuevamente.';
            } else {
                $tripsCreated = 0;
                $routesCreated = 0;
                
                try {
                    foreach ($tripsToImport as $tripData) {
                        $tripTitle = $tripData['title'];
                        $startDate = $tripData['start_date'];
                        $endDate = $tripData['end_date'];
                        $flights = $tripData['flights'];
                        $tripColor = generateTripColor();
                        
                        // Crear viaje
                        $tripId = $tripModel->create([
                            'title' => $tripTitle,
                            'description' => 'Viaje importado desde FlightRadar. ' . count($flights) . ' vuelos.',
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'color_hex' => $tripColor,
                            'status' => 'draft'
                        ]);
                        
                        if ($tripId) {
                            $tripsCreated++;
                            
                            // Crear rutas para cada vuelo
                            foreach ($flights as $flight) {
                                $geojson = createFlightGeoJSON($flight);
                                
                                $routeId = $routeModel->create([
                                    'trip_id' => $tripId,
                                    'transport_type' => 'plane',
                                    'geojson_data' => $geojson,
                                    'color' => $planeColor
                                ]);
                                
                                if ($routeId) {
                                    $routesCreated++;
                                }
                            }
                        }
                    }
                    
                    $importResults = [
                        'success' => true,
                        'trips_created' => $tripsCreated,
                        'routes_created' => $routesCreated
                    ];
                    
                    // Limpiar datos de sesión
                    unset($_SESSION['import_flights_data']);
                    unset($_SESSION['import_trips_groups']);
                    
                } catch (Exception $e) {
                    $errors[] = 'Error durante la importación: ' . $e->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.0002 12H6.00024V19C6.00024 20.4142 6.00024 21.1213 6.43958 21.5607C6.87892 22 7.58603 22 9.00024 22H10.0002V12Z" />
                <path d="M18.0002 15H10.0002V22H18.0002C19.4145 22 20.1216 22 20.5609 21.5607C21.0002 21.1213 21.0002 20.4142 21.0002 19V18C21.0002 16.5858 21.0002 15.8787 20.5609 15.4393C20.1216 15 19.4145 15 18.0002 15Z" />
                <path d="M21 6L20 7M16.5 7H20M20 7L17 10H16M20 7V10.5" />
                <path d="M12.2686 10.1181C11.9025 11.0296 11.7195 11.4854 11.3388 11.7427C10.9582 12 10.4671 12 9.4848 12H6.51178C5.5295 12 5.03836 12 4.65773 11.7427C4.27711 11.4854 4.09405 11.0296 3.72794 10.1181L3.57717 9.74278C3.07804 8.50009 2.82847 7.87874 3.12717 7.43937C3.42587 7 4.09785 7 5.44182 7H10.5548C11.8987 7 12.5707 7 12.8694 7.43937C13.1681 7.87874 12.9185 8.50009 12.4194 9.74278L12.2686 10.1181Z" />
                <path d="M9.99616 7H6.00407C5.18904 5.73219 4.8491 5.09829 5.06258 4.59641C5.34685 4.13381 6.15056 4 7.61989 4H8.38063C9.84995 4 10.6537 4.13381 10.9379 4.59641C11.1514 5.09829 10.8112 5.73219 9.99616 7Z" />
                <path d="M8 4V2" />
            </svg>
            <?= __('navigation.import_flights') ?>
        </h1>
        <p class="page-subtitle"><?= __('import.flights_description') ?? 'Upload your FlightRadar CSV export to create trips automatically' ?></p>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endforeach; ?>

<?php if ($importResults && $importResults['success']): ?>
    <!-- Import Results -->
    <div class="admin-card">
        <div class="admin-card-header" style="background: var(--admin-success); color: white; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
            <h3 class="admin-card-title" style="color: white;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: white;">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <?= __('import.completed') ?? 'Import Completed' ?>
            </h3>
        </div>
        <div class="admin-card-body" style="text-align: center; padding: 40px;">
            <div class="stats-grid" style="max-width: 500px; margin: 0 auto 32px;">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label"><?= __('trips.trips') ?? 'Trips Created' ?></div>
                        <div class="stat-value"><?= $importResults['trips_created'] ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label"><?= __('trips.routes') ?? 'Routes Added' ?></div>
                        <div class="stat-value"><?= $importResults['routes_created'] ?></div>
                    </div>
                </div>
            </div>
            <div style="display: flex; justify-content: center; gap: 12px;">
                <a href="<?= BASE_URL ?>/admin/trips.php" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233"/>
                    </svg>
                    <?= __('navigation.trips') ?>
                </a>
                <a href="<?= BASE_URL ?>/admin/import_flights.php" class="btn btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    <?= __('import.import_more') ?? 'Import More' ?>
                </a>
            </div>
        </div>
    </div>

<?php elseif ($previewData): ?>
    <!-- Preview -->
    <div class="admin-card">
        <div class="admin-card-header" style="background: var(--admin-info); color: white; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
            <h3 class="admin-card-title" style="color: white;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: white;">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <?= __('import.preview') ?? 'Import Preview' ?>
            </h3>
        </div>
        <div class="admin-card-body">
            <div class="stats-grid" style="margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label"><?= __('import.flights_detected') ?? 'Flights Detected' ?></div>
                        <div class="stat-value"><?= $previewData['total_flights'] ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label"><?= __('import.trips_to_create') ?? 'Trips to Create' ?></div>
                        <div class="stat-value" id="tripCount"><?= $previewData['total_trips'] ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon <?= count($previewData['missing_airports']) > 0 ? 'amber' : 'green' ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label"><?= __('import.missing_airports') ?? 'Missing Airports' ?></div>
                        <div class="stat-value"><?= count($previewData['missing_airports']) ?></div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($previewData['missing_airports'])): ?>
                <div class="alert alert-warning">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <div>
                        <strong><?= __('import.airports_without_coords') ?? 'Airports without coordinates:' ?></strong>
                        <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                            <?php foreach ($previewData['missing_airports'] as $iata => $city): ?>
                                <li><strong><?= htmlspecialchars($iata) ?></strong> - <?= htmlspecialchars($city) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Merge Controls -->
            <div class="admin-card" style="position: sticky; top: 0; z-index: 100; margin-bottom: 16px;">
                <div class="admin-card-body" style="padding: 12px 16px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                        <span style="font-size: 13px; color: var(--admin-text-muted);">
                            <?= __('import.select_trips_to_merge') ?? 'Select trips to merge · Click title to rename · Select flights to move' ?>
                        </span>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="btn btn-sm btn-secondary" id="selectAllBtn"><?= __('common.all') ?? 'All' ?></button>
                            <button type="button" class="btn btn-sm btn-secondary" id="deselectAllBtn"><?= __('common.none') ?? 'None' ?></button>
                            <button type="button" class="btn btn-sm btn-primary" id="mergeBtn" disabled>
                                <?= __('import.merge') ?? 'Merge' ?> (<span id="selectedCount">0</span>)
                            </button>
                        </div>
                    </div>
                    <!-- Flight actions bar (hidden by default) -->
                    <div id="flightActionsBar" style="display: none; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--admin-border);">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                            <span style="font-size: 13px; color: var(--admin-accent); font-weight: 500;">
                                <span id="selectedFlightsCount">0</span> <?= __('import.flights_selected') ?? 'flight(s) selected' ?>
                            </span>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <select id="moveToTripSelect" class="form-select form-select-sm" style="min-width: 200px;">
                                    <option value=""><?= __('import.move_to') ?? 'Move to...' ?></option>
                                    <option value="__new__"><?= __('import.new_trip') ?? '+ Create new trip' ?></option>
                                </select>
                                <button type="button" class="btn btn-sm btn-warning" id="moveFlightsBtn" disabled>
                                    <?= __('import.move') ?? 'Move' ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" id="clearFlightSelectionBtn">
                                    <?= __('common.cancel') ?? 'Cancel' ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <h4 style="font-size: 15px; font-weight: 600; margin-bottom: 16px;"><?= __('import.trips_to_be_created') ?? 'Trips to be created:' ?></h4>
            <div id="tripsContainer">
                <?php foreach ($previewData['trips'] as $index => $trip): ?>
                    <div class="admin-card trip-card" style="margin-bottom: 12px;" data-trip-index="<?= $index ?>">
                        <div class="admin-card-header" style="padding: 10px 16px;">
                            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                                <input type="checkbox" class="form-check-input trip-checkbox" data-trip-index="<?= $index ?>">
                                <span class="badge badge-info"><?= $trip['flights_count'] ?> vuelos</span>
                                <span class="trip-title-display" data-trip-index="<?= $index ?>" style="cursor: pointer; font-weight: 600;">
                                    <?= htmlspecialchars($trip['title']) ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px; opacity: 0.5; margin-left: 4px;">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </span>
                                <input type="text" class="form-control trip-title-input" data-trip-index="<?= $index ?>" value="<?= htmlspecialchars($trip['title']) ?>" style="max-width: 300px; display: none;">
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span class="cell-date"><?= date('d/m/Y', strtotime($trip['start_date'])) ?> - <?= date('d/m/Y', strtotime($trip['end_date'])) ?></span>
                                <button type="button" class="btn btn-sm btn-secondary toggle-flights-btn" data-target="flights-<?= $index ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="admin-card-body flights-detail" id="flights-<?= $index ?>" style="display: none; padding: 0;">
                            <div class="admin-table-wrapper">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" class="form-check-input select-all-flights" data-trip-index="<?= $index ?>" title="<?= __('common.select_all') ?? 'Select all' ?>">
                                            </th>
                                            <th style="width: 100px;"><?= __('common.date') ?></th>
                                            <th><?= __('import.origin') ?? 'Origin' ?></th>
                                            <th><?= __('import.destination') ?? 'Destination' ?></th>
                                            <th><?= __('import.flight') ?? 'Flight' ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($trip['flights'] as $fIndex => $flight): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input flight-checkbox" data-trip-index="<?= $index ?>" data-flight-index="<?= $fIndex ?>">
                                                </td>
                                                <td class="cell-date"><?= date('d/m/Y', strtotime($flight['date'])) ?></td>
                                                <td>
                                                    <span class="badge badge-secondary"><?= htmlspecialchars($flight['from_iata']) ?></span>
                                                    <?= htmlspecialchars($flight['from_city']) ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-secondary"><?= htmlspecialchars($flight['to_iata']) ?></span>
                                                    <?= htmlspecialchars($flight['to_city']) ?>
                                                </td>
                                                <td class="text-muted"><?= htmlspecialchars($flight['flight_number']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="admin-card-footer" style="display: flex; justify-content: flex-end; gap: 12px;">
            <form method="POST" id="importForm">
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="modified_trips" id="modifiedTripsInput" value="">
                <a href="<?= BASE_URL ?>/admin/import_flights.php" class="btn btn-secondary">
                    <?= __('common.cancel') ?>
                </a>
                <button type="submit" class="btn btn-success" id="importBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <?= __('import.import') ?? 'Import' ?> <span id="importTripCount"><?= $previewData['total_trips'] ?></span> <?= __('navigation.trips') ?>
                </button>
            </form>
        </div>
    </div>
    
    <script>
    (function() {
        let tripsData = <?= json_encode($previewData['trips']) ?>;
        
        const mergeBtn = document.getElementById('mergeBtn');
        const selectedCountSpan = document.getElementById('selectedCount');
        const tripCountDisplay = document.getElementById('tripCount');
        const importTripCount = document.getElementById('importTripCount');
        const modifiedTripsInput = document.getElementById('modifiedTripsInput');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const deselectAllBtn = document.getElementById('deselectAllBtn');
        
        // Flight selection elements
        const flightActionsBar = document.getElementById('flightActionsBar');
        const selectedFlightsCountSpan = document.getElementById('selectedFlightsCount');
        const moveToTripSelect = document.getElementById('moveToTripSelect');
        const moveFlightsBtn = document.getElementById('moveFlightsBtn');
        const clearFlightSelectionBtn = document.getElementById('clearFlightSelectionBtn');
        
        function updateSelectedCount() {
            const checked = document.querySelectorAll('.trip-checkbox:checked').length;
            selectedCountSpan.textContent = checked;
            mergeBtn.disabled = checked < 2;
        }
        
        function updateFlightSelectionUI() {
            const checkedFlights = document.querySelectorAll('.flight-checkbox:checked');
            const count = checkedFlights.length;
            selectedFlightsCountSpan.textContent = count;
            
            if (count > 0) {
                flightActionsBar.style.display = 'block';
                updateMoveToDropdown();
            } else {
                flightActionsBar.style.display = 'none';
            }
            
            moveFlightsBtn.disabled = count === 0 || !moveToTripSelect.value;
        }
        
        function updateMoveToDropdown() {
            // Get current trips for dropdown
            const currentValue = moveToTripSelect.value;
            moveToTripSelect.innerHTML = '<option value=""><?= __('import.move_to') ?? 'Move to...' ?></option>';
            moveToTripSelect.innerHTML += '<option value="__new__"><?= __('import.new_trip') ?? '+ Create new trip' ?></option>';
            
            tripsData.forEach((trip, index) => {
                const opt = document.createElement('option');
                opt.value = index;
                opt.textContent = trip.title;
                moveToTripSelect.appendChild(opt);
            });
            
            // Restore selection if still valid
            if (currentValue && (currentValue === '__new__' || parseInt(currentValue) < tripsData.length)) {
                moveToTripSelect.value = currentValue;
            }
        }
        
        function generateTripTitle(flights) {
            const firstFlight = flights[0];
            const lastFlight = flights[flights.length - 1];
            const startCity = firstFlight.from_city;
            const endCity = lastFlight.to_city;
            const startDate = new Date(firstFlight.date);
            const endDate = new Date(lastFlight.date);
            const months = {0: 'Ene', 1: 'Feb', 2: 'Mar', 3: 'Abr', 4: 'May', 5: 'Jun', 6: 'Jul', 7: 'Ago', 8: 'Sep', 9: 'Oct', 10: 'Nov', 11: 'Dic'};
            let dateRange;
            if (startDate.getFullYear() === endDate.getFullYear() && startDate.getMonth() === endDate.getMonth()) {
                dateRange = months[startDate.getMonth()] + ' ' + startDate.getFullYear();
            } else if (startDate.getFullYear() === endDate.getFullYear()) {
                dateRange = months[startDate.getMonth()] + '-' + months[endDate.getMonth()] + ' ' + startDate.getFullYear();
            } else {
                dateRange = months[startDate.getMonth()] + ' ' + startDate.getFullYear() + ' - ' + months[endDate.getMonth()] + ' ' + endDate.getFullYear();
            }
            if (startCity === endCity) return startCity + ' (' + dateRange + ')';
            return startCity + ' → ' + endCity + ' (' + dateRange + ')';
        }
        
        function formatDate(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleDateString('es-ES', {day: '2-digit', month: '2-digit', year: 'numeric'});
        }
        
        function updateTripData(trip) {
            trip.flights.sort((a, b) => new Date(a.date) - new Date(b.date));
            trip.flights_count = trip.flights.length;
            if (trip.flights.length > 0) {
                trip.start_date = trip.flights[0].date;
                trip.end_date = trip.flights[trip.flights.length - 1].date;
                // Only auto-generate title if it wasn't manually set
                if (!trip.titleManuallySet) {
                    trip.title = generateTripTitle(trip.flights);
                }
            }
        }
        
        function formatDateDisplay(dateStr) {
            const d = new Date(dateStr);
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year = d.getFullYear();
            return `${day}/${month}/${year}`;
        }
        
        function renderTrips() {
            const container = document.getElementById('tripsContainer');
            container.innerHTML = '';
            
            tripsData.forEach((trip, index) => {
                const tripCard = document.createElement('div');
                tripCard.className = 'admin-card trip-card';
                tripCard.style.marginBottom = '12px';
                tripCard.dataset.tripIndex = index;
                
                // Build flights table rows
                let flightRows = '';
                trip.flights.forEach((flight, fIndex) => {
                    flightRows += `
                        <tr>
                            <td><input type="checkbox" class="form-check-input flight-checkbox" data-trip-index="${index}" data-flight-index="${fIndex}"></td>
                            <td class="cell-date">${formatDateDisplay(flight.date)}</td>
                            <td><span class="badge badge-secondary">${flight.from_iata}</span> ${flight.from_city}</td>
                            <td><span class="badge badge-secondary">${flight.to_iata}</span> ${flight.to_city}</td>
                            <td class="text-muted">${flight.flight_number || ''}</td>
                        </tr>
                    `;
                });
                
                tripCard.innerHTML = `
                    <div class="admin-card-header" style="padding: 10px 16px;">
                        <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                            <input type="checkbox" class="form-check-input trip-checkbox" data-trip-index="${index}">
                            <span class="badge badge-info">${trip.flights.length} vuelos</span>
                            <span class="trip-title-display" data-trip-index="${index}" style="cursor: pointer; font-weight: 600;">
                                ${trip.title}
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px; opacity: 0.5; margin-left: 4px;">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </span>
                            <input type="text" class="form-control trip-title-input" data-trip-index="${index}" value="${trip.title}" style="max-width: 300px; display: none;">
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span class="cell-date">${formatDateDisplay(trip.start_date)} - ${formatDateDisplay(trip.end_date)}</span>
                            <button type="button" class="btn btn-sm btn-secondary toggle-flights-btn" data-target="flights-${index}">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="admin-card-body flights-detail" id="flights-${index}" style="display: none; padding: 0;">
                        <div class="admin-table-wrapper">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"><input type="checkbox" class="form-check-input select-all-flights" data-trip-index="${index}" title="<?= __('common.select_all') ?? 'Select all' ?>"></th>
                                        <th style="width: 100px;"><?= __('common.date') ?></th>
                                        <th><?= __('import.origin') ?? 'Origin' ?></th>
                                        <th><?= __('import.destination') ?? 'Destination' ?></th>
                                        <th><?= __('import.flight') ?? 'Flight' ?></th>
                                    </tr>
                                </thead>
                                <tbody>${flightRows}</tbody>
                            </table>
                        </div>
                    </div>
                `;
                
                container.appendChild(tripCard);
            });
            
            // Update counts
            tripCountDisplay.textContent = tripsData.length;
            importTripCount.textContent = tripsData.length;
            
            // Clear selections
            flightActionsBar.style.display = 'none';
            updateSelectedCount();
            
            // Re-attach event listeners
            attachEventListeners();
            updateMoveToDropdown();
        }
        
        function attachEventListeners() {
            document.querySelectorAll('.trip-checkbox').forEach(cb => {
                cb.addEventListener('change', updateSelectedCount);
            });
            
            document.querySelectorAll('.toggle-flights-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const targetId = this.dataset.target;
                    const target = document.getElementById(targetId);
                    const svg = this.querySelector('svg');
                    if (target.style.display === 'none') {
                        target.style.display = 'block';
                        svg.style.transform = 'rotate(180deg)';
                    } else {
                        target.style.display = 'none';
                        svg.style.transform = '';
                    }
                });
            });
            
            document.querySelectorAll('.trip-title-display').forEach(display => {
                display.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const index = this.dataset.tripIndex;
                    const input = document.querySelector(`.trip-title-input[data-trip-index="${index}"]`);
                    this.style.display = 'none';
                    input.style.display = 'block';
                    input.focus();
                    input.select();
                });
            });
            
            document.querySelectorAll('.trip-title-input').forEach(input => {
                const saveTitle = function() {
                    const index = parseInt(this.dataset.tripIndex);
                    const newTitle = this.value.trim();
                    const display = document.querySelector(`.trip-title-display[data-trip-index="${index}"]`);
                    if (newTitle) {
                        tripsData[index].title = newTitle;
                        tripsData[index].titleManuallySet = true;
                        display.innerHTML = newTitle + '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px; opacity: 0.5; margin-left: 4px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
                    }
                    this.style.display = 'none';
                    display.style.display = '';
                };
                input.addEventListener('blur', saveTitle);
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
                    else if (e.key === 'Escape') { this.value = tripsData[parseInt(this.dataset.tripIndex)].title; this.blur(); }
                });
            });
            
            // Flight checkboxes
            document.querySelectorAll('.flight-checkbox').forEach(cb => {
                cb.addEventListener('change', updateFlightSelectionUI);
            });
            
            // Select all flights in a trip
            document.querySelectorAll('.select-all-flights').forEach(cb => {
                cb.addEventListener('change', function() {
                    const tripIndex = this.dataset.tripIndex;
                    const flightCheckboxes = document.querySelectorAll(`.flight-checkbox[data-trip-index="${tripIndex}"]`);
                    flightCheckboxes.forEach(fcb => fcb.checked = this.checked);
                    updateFlightSelectionUI();
                });
            });
        }
        
        // Move to dropdown change
        moveToTripSelect.addEventListener('change', function() {
            const checkedFlights = document.querySelectorAll('.flight-checkbox:checked');
            moveFlightsBtn.disabled = checkedFlights.length === 0 || !this.value;
        });
        
        // Move flights button
        moveFlightsBtn.addEventListener('click', function() {
            const checkedFlights = document.querySelectorAll('.flight-checkbox:checked');
            if (checkedFlights.length === 0) return;
            
            const targetValue = moveToTripSelect.value;
            if (!targetValue) return;
            
            // Collect flights to move (group by source trip)
            const flightsToMove = [];
            const sourceTrips = {};
            
            checkedFlights.forEach(cb => {
                const tripIdx = parseInt(cb.dataset.tripIndex);
                const flightIdx = parseInt(cb.dataset.flightIndex);
                if (!sourceTrips[tripIdx]) sourceTrips[tripIdx] = [];
                sourceTrips[tripIdx].push(flightIdx);
                flightsToMove.push(tripsData[tripIdx].flights[flightIdx]);
            });
            
            // Remove flights from source trips (in reverse order to maintain indices)
            Object.keys(sourceTrips).sort((a, b) => b - a).forEach(tripIdx => {
                const indices = sourceTrips[tripIdx].sort((a, b) => b - a);
                indices.forEach(flightIdx => {
                    tripsData[tripIdx].flights.splice(flightIdx, 1);
                });
                // Update source trip
                if (tripsData[tripIdx].flights.length > 0) {
                    updateTripData(tripsData[tripIdx]);
                }
            });
            
            // Remove empty trips
            tripsData = tripsData.filter(trip => trip.flights.length > 0);
            
            // Add to target trip or create new one
            if (targetValue === '__new__') {
                const newTrip = {
                    title: generateTripTitle(flightsToMove),
                    flights: flightsToMove,
                    flights_count: flightsToMove.length,
                    start_date: flightsToMove[0].date,
                    end_date: flightsToMove[flightsToMove.length - 1].date
                };
                updateTripData(newTrip);
                tripsData.push(newTrip);
            } else {
                const targetIdx = parseInt(targetValue);
                if (targetIdx < tripsData.length) {
                    tripsData[targetIdx].flights = tripsData[targetIdx].flights.concat(flightsToMove);
                    updateTripData(tripsData[targetIdx]);
                }
            }
            
            // Sort trips by first flight date
            tripsData.sort((a, b) => new Date(a.start_date) - new Date(b.start_date));
            
            renderTrips();
        });
        
        // Clear flight selection
        clearFlightSelectionBtn.addEventListener('click', function() {
            document.querySelectorAll('.flight-checkbox').forEach(cb => cb.checked = false);
            document.querySelectorAll('.select-all-flights').forEach(cb => cb.checked = false);
            updateFlightSelectionUI();
        });
        
        mergeBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.trip-checkbox:checked');
            if (checkedBoxes.length < 2) return;
            const indices = Array.from(checkedBoxes).map(cb => parseInt(cb.dataset.tripIndex)).sort((a, b) => a - b);
            let mergedFlights = [];
            indices.forEach(idx => { mergedFlights = mergedFlights.concat(tripsData[idx].flights); });
            mergedFlights.sort((a, b) => new Date(a.date) - new Date(b.date));
            const mergedTrip = {
                title: generateTripTitle(mergedFlights),
                flights_count: mergedFlights.length,
                start_date: mergedFlights[0].date,
                end_date: mergedFlights[mergedFlights.length - 1].date,
                flights: mergedFlights
            };
            for (let i = indices.length - 1; i >= 0; i--) { tripsData.splice(indices[i], 1); }
            tripsData.splice(indices[0], 0, mergedTrip);
            renderTrips();
        });
        
        selectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('.trip-checkbox').forEach(cb => cb.checked = true);
            updateSelectedCount();
        });
        
        deselectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('.trip-checkbox').forEach(cb => cb.checked = false);
            updateSelectedCount();
        });
        
        document.getElementById('importForm').addEventListener('submit', function(e) {
            modifiedTripsInput.value = JSON.stringify(tripsData);
        });
        
        attachEventListeners();
        updateMoveToDropdown();
    })();
    </script>

<?php else: ?>
    <!-- Upload Form -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    <?= __('import.upload_csv') ?? 'Upload CSV File' ?>
                </h3>
            </div>
            <div class="admin-card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="preview">
                    
                    <div class="form-group">
                        <label for="csv_file" class="form-label">
                            <?= __('import.flightradar_csv') ?? 'FlightRadar CSV File' ?> <span class="required">*</span>
                        </label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required style="padding: 12px;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <?= __('import.analyze_file') ?? 'Analyze File' ?>
                    </button>
                </form>
            </div>
        </div>
        
        <div>
            <div class="admin-card" style="margin-bottom: 16px;">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <?= __('import.how_it_works') ?? 'How it works' ?>
                    </h3>
                </div>
                <div class="admin-card-body">
                    <ol style="font-size: 13px; color: var(--admin-text-muted); padding-left: 20px; margin: 0;">
                        <li style="margin-bottom: 8px;">
                            <strong><?= __('import.step1') ?? 'Export your CSV' ?></strong><br>
                            <a href="https://my.flightradar24.com/settings/export" target="_blank" style="color: var(--admin-info);">
                                FlightRadar Export
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px;">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                        </li>
                        <li style="margin-bottom: 8px;"><strong><?= __('import.step2') ?? 'Preview' ?></strong><br><small>Review trips before confirming</small></li>
                        <li style="margin-bottom: 8px;"><strong><?= __('import.step3') ?? 'Import' ?></strong><br><small>Flights grouped by gaps > 7 days</small></li>
                        <li><strong><?= __('import.step4') ?? 'Review' ?></strong><br><small>Trips created as drafts</small></li>
                    </ol>
                </div>
            </div>
            
            <div class="admin-card">
                <div class="admin-card-header" style="background: #fef3c7;">
                    <h3 class="admin-card-title" style="color: #92400e;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #92400e;">
                            <path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233"/>
                        </svg>
                        <?= __('import.supported_airports') ?? 'Supported Airports' ?>
                    </h3>
                </div>
                <div class="admin-card-body">
                    <p style="font-size: 12px; color: var(--admin-text-muted); margin-bottom: 8px;">
                        <?= count($airports) ?> <?= __('import.airports_with_coords') ?? 'airports with coordinates included.' ?>
                    </p>
                    <details>
                        <summary style="font-size: 12px; color: var(--admin-info); cursor: pointer;"><?= __('import.view_list') ?? 'View list' ?></summary>
                        <div style="max-height: 150px; overflow-y: auto; margin-top: 8px; font-size: 11px; font-family: monospace;">
                            <?php 
                            $codes = array_keys($airports);
                            sort($codes);
                            echo implode(', ', $codes);
                            ?>
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
