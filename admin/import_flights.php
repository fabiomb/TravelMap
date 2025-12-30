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

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="h3">
            <i class="bi bi-cloud-upload"></i> Importar Vuelos desde FlightRadar (ex FlightDiary)
        </h1>
        <p class="text-muted">Sube tu exportación CSV de FlightRadar/FlightDiary para crear viajes automáticamente</p>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>

<?php if ($importResults && $importResults['success']): ?>
    <!-- Resultados de Importación -->
    <div class="card border-success mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-check-circle"></i> Importación Completada</h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-6">
                    <div class="display-4 text-success"><?= $importResults['trips_created'] ?></div>
                    <p class="text-muted">Viajes creados</p>
                </div>
                <div class="col-md-6">
                    <div class="display-4 text-primary"><?= $importResults['routes_created'] ?></div>
                    <p class="text-muted">Rutas de vuelo añadidas</p>
                </div>
            </div>
            <hr>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?= BASE_URL ?>/admin/trips.php" class="btn btn-primary">
                    <i class="bi bi-airplane"></i> Ver Viajes
                </a>
                <a href="<?= BASE_URL ?>/admin/import_flights.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-repeat"></i> Importar Más
                </a>
            </div>
        </div>
    </div>

<?php elseif ($previewData): ?>
    <!-- Vista Previa -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-eye"></i> Vista Previa de Importación</h5>
        </div>
        <div class="card-body">
            <div class="row text-center mb-4">
                <div class="col-md-4">
                    <div class="display-5 text-info"><?= $previewData['total_flights'] ?></div>
                    <p class="text-muted">Vuelos detectados</p>
                </div>
                <div class="col-md-4">
                    <div class="display-5 text-success" id="tripCount"><?= $previewData['total_trips'] ?></div>
                    <p class="text-muted">Viajes a crear</p>
                </div>
                <div class="col-md-4">
                    <div class="display-5 <?= count($previewData['missing_airports']) > 0 ? 'text-warning' : 'text-success' ?>">
                        <?= count($previewData['missing_airports']) ?>
                    </div>
                    <p class="text-muted">Aeropuertos no encontrados</p>
                </div>
            </div>
            
            <?php if (!empty($previewData['missing_airports'])): ?>
                <div class="alert alert-warning">
                    <strong><i class="bi bi-exclamation-triangle"></i> Aeropuertos sin coordenadas:</strong>
                    Los siguientes vuelos fueron omitidos porque no se encontraron las coordenadas:
                    <ul class="mb-0 mt-2">
                        <?php foreach ($previewData['missing_airports'] as $iata => $city): ?>
                            <li><strong><?= htmlspecialchars($iata) ?></strong> - <?= htmlspecialchars($city) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Controls - Sticky -->
            <div class="alert alert-light alert-permanent border mb-3" id="mergeControls" 
                 style="position: sticky; top: 56px; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <!-- Trip Controls Row -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <div>
                        <i class="bi bi-airplane text-primary"></i>
                        <strong>Viajes:</strong> 
                        <small class="text-muted">Selecciona viajes para unirlos · Clic en título para renombrar</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllBtn">
                            <i class="bi bi-check-all"></i> Todos
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllBtn">
                            <i class="bi bi-x"></i> Ninguno
                        </button>
                        <button type="button" class="btn btn-warning btn-sm" id="mergeBtn" disabled>
                            <i class="bi bi-union"></i> Unir (<span id="selectedCount">0</span>)
                        </button>
                    </div>
                </div>
                <hr class="my-2">
                <!-- Flight Controls Row -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2" id="flightControlsRow">
                    <div class="d-flex align-items-center gap-1">
                        <i class="bi bi-arrow-left-right text-success"></i>
                        <strong>Vuelos:</strong> 
                        <small class="text-muted d-none d-md-inline">Selecciona vuelos y muévelos</small>
                    </div>
                    <div class="d-flex gap-1 align-items-center flex-nowrap">
                        <span class="badge bg-secondary text-nowrap" id="selectedFlightsCount">0</span>
                        <select class="form-select form-select-sm" id="moveToTripSelect" style="width: 140px;" disabled>
                            <option value="">Mover a...</option>
                        </select>
                        <button type="button" class="btn btn-success btn-sm text-nowrap" id="moveFlightsBtn" disabled>
                            <i class="bi bi-arrow-right"></i>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm text-nowrap" id="newTripFromFlightsBtn" disabled>
                            <i class="bi bi-plus-circle"></i> Nuevo
                        </button>
                    </div>
                </div>
            </div>
            
            <h5 class="mt-4 mb-3">Viajes que se crearán:</h5>
            <div id="tripsContainer">
                <?php foreach ($previewData['trips'] as $index => $trip): ?>
                    <div class="card mb-2 trip-card" data-trip-index="<?= $index ?>">
                        <div class="card-header d-flex align-items-center gap-2 py-2">
                            <input type="checkbox" class="form-check-input trip-checkbox" 
                                   data-trip-index="<?= $index ?>" style="margin: 0;">
                            <span class="badge bg-primary trip-flight-count"><?= $trip['flights_count'] ?> vuelos</span>
                            <span class="trip-title-display" data-trip-index="<?= $index ?>" 
                                  style="cursor: pointer;" title="Clic para editar">
                                <strong><?= htmlspecialchars($trip['title']) ?></strong>
                                <i class="bi bi-pencil-fill text-muted ms-1" style="font-size: 0.7em;"></i>
                            </span>
                            <input type="text" class="form-control form-control-sm trip-title-input d-none" 
                                   data-trip-index="<?= $index ?>" value="<?= htmlspecialchars($trip['title']) ?>"
                                   style="max-width: 300px;">
                            <small class="text-muted ms-auto trip-dates">
                                <?= date('d/m/Y', strtotime($trip['start_date'])) ?> - <?= date('d/m/Y', strtotime($trip['end_date'])) ?>
                            </small>
                            <button type="button" class="btn btn-sm btn-outline-secondary toggle-flights-btn" 
                                    data-target="flights-<?= $index ?>">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>
                        <div class="card-body p-0 flights-detail" id="flights-<?= $index ?>" style="display: none;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 30px;">
                                            <input type="checkbox" class="form-check-input select-all-flights" 
                                                   data-trip-index="<?= $index ?>" title="Seleccionar todos">
                                        </th>
                                        <th>Fecha</th>
                                        <th>Origen</th>
                                        <th>Destino</th>
                                        <th>Vuelo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trip['flights'] as $fIndex => $flight): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input flight-checkbox" 
                                                       data-trip-index="<?= $index ?>" data-flight-index="<?= $fIndex ?>">
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($flight['date'])) ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($flight['from_iata']) ?></span>
                                                <?= htmlspecialchars($flight['from_city']) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($flight['to_iata']) ?></span>
                                                <?= htmlspecialchars($flight['to_city']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($flight['flight_number']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <hr>
            <form method="POST" id="importForm" class="d-flex justify-content-end gap-2">
                <input type="hidden" name="action" value="import">
                <input type="hidden" name="modified_trips" id="modifiedTripsInput" value="">
                <a href="<?= BASE_URL ?>/admin/import_flights.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-success" id="importBtn">
                    <i class="bi bi-check-circle"></i> Importar <span id="importTripCount"><?= $previewData['total_trips'] ?></span> Viajes
                </button>
            </form>
        </div>
    </div>
    
    <script>
    (function() {
        // Store trips data in JavaScript
        let tripsData = <?= json_encode($previewData['trips']) ?>;
        
        const mergeBtn = document.getElementById('mergeBtn');
        const selectedCountSpan = document.getElementById('selectedCount');
        const tripCountDisplay = document.getElementById('tripCount');
        const importTripCount = document.getElementById('importTripCount');
        const modifiedTripsInput = document.getElementById('modifiedTripsInput');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const deselectAllBtn = document.getElementById('deselectAllBtn');
        
        // Update selected count
        function updateSelectedCount() {
            const checked = document.querySelectorAll('.trip-checkbox:checked').length;
            selectedCountSpan.textContent = checked;
            mergeBtn.disabled = checked < 2;
        }
        
        // Generate trip title from flights
        function generateTripTitle(flights) {
            const firstFlight = flights[0];
            const lastFlight = flights[flights.length - 1];
            
            const startCity = firstFlight.from_city;
            const endCity = lastFlight.to_city;
            
            const startDate = new Date(firstFlight.date);
            const endDate = new Date(lastFlight.date);
            
            const months = {0: 'Ene', 1: 'Feb', 2: 'Mar', 3: 'Abr', 4: 'May', 5: 'Jun',
                           6: 'Jul', 7: 'Ago', 8: 'Sep', 9: 'Oct', 10: 'Nov', 11: 'Dic'};
            
            let dateRange;
            if (startDate.getFullYear() === endDate.getFullYear() && startDate.getMonth() === endDate.getMonth()) {
                dateRange = months[startDate.getMonth()] + ' ' + startDate.getFullYear();
            } else if (startDate.getFullYear() === endDate.getFullYear()) {
                dateRange = months[startDate.getMonth()] + '-' + months[endDate.getMonth()] + ' ' + startDate.getFullYear();
            } else {
                dateRange = months[startDate.getMonth()] + ' ' + startDate.getFullYear() + ' - ' + 
                           months[endDate.getMonth()] + ' ' + endDate.getFullYear();
            }
            
            if (startCity === endCity) {
                return startCity + ' (' + dateRange + ')';
            }
            return startCity + ' → ' + endCity + ' (' + dateRange + ')';
        }
        
        // Format date for display
        function formatDate(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleDateString('es-ES', {day: '2-digit', month: '2-digit', year: 'numeric'});
        }
        
        // Render trips to DOM
        function renderTrips() {
            const container = document.getElementById('tripsContainer');
            container.innerHTML = '';
            
            tripsData.forEach((trip, index) => {
                const card = document.createElement('div');
                card.className = 'card mb-2 trip-card';
                card.dataset.tripIndex = index;
                
                let flightsHtml = '';
                trip.flights.forEach((flight, fIndex) => {
                    flightsHtml += `
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input flight-checkbox" 
                                       data-trip-index="${index}" data-flight-index="${fIndex}">
                            </td>
                            <td>${formatDate(flight.date)}</td>
                            <td>
                                <span class="badge bg-secondary">${flight.from_iata}</span>
                                ${flight.from_city}
                            </td>
                            <td>
                                <span class="badge bg-secondary">${flight.to_iata}</span>
                                ${flight.to_city}
                            </td>
                            <td>${flight.flight_number || ''}</td>
                        </tr>
                    `;
                });
                
                // Escape HTML in title for safe display
                const escapedTitle = trip.title.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                
                card.innerHTML = `
                    <div class="card-header d-flex align-items-center gap-2 py-2">
                        <input type="checkbox" class="form-check-input trip-checkbox" 
                               data-trip-index="${index}" style="margin: 0;">
                        <span class="badge bg-primary trip-flight-count">${trip.flights.length} vuelos</span>
                        <span class="trip-title-display" data-trip-index="${index}" 
                              style="cursor: pointer;" title="Clic para editar">
                            <strong>${escapedTitle}</strong>
                            <i class="bi bi-pencil-fill text-muted ms-1" style="font-size: 0.7em;"></i>
                        </span>
                        <input type="text" class="form-control form-control-sm trip-title-input d-none" 
                               data-trip-index="${index}" value="${escapedTitle}"
                               style="max-width: 300px;">
                        <small class="text-muted ms-auto trip-dates">
                            ${formatDate(trip.start_date)} - ${formatDate(trip.end_date)}
                        </small>
                        <button type="button" class="btn btn-sm btn-outline-secondary toggle-flights-btn" 
                                data-target="flights-${index}">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="card-body p-0 flights-detail" id="flights-${index}" style="display: none;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30px;">
                                        <input type="checkbox" class="form-check-input select-all-flights" 
                                               data-trip-index="${index}" title="Seleccionar todos">
                                    </th>
                                    <th>Fecha</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Vuelo</th>
                                </tr>
                            </thead>
                            <tbody>${flightsHtml}</tbody>
                        </table>
                    </div>
                `;
                
                container.appendChild(card);
            });
            
            // Update counts
            tripCountDisplay.textContent = tripsData.length;
            importTripCount.textContent = tripsData.length;
            
            // Re-attach event listeners
            attachEventListeners();
            updateSelectedCount();
        }
        
        // Attach event listeners to checkboxes, toggle buttons, and title editing
        function attachEventListeners() {
            document.querySelectorAll('.trip-checkbox').forEach(cb => {
                cb.addEventListener('change', updateSelectedCount);
            });
            
            document.querySelectorAll('.toggle-flights-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const targetId = this.dataset.target;
                    const target = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (target.style.display === 'none') {
                        target.style.display = 'block';
                        icon.className = 'bi bi-chevron-up';
                    } else {
                        target.style.display = 'none';
                        icon.className = 'bi bi-chevron-down';
                    }
                });
            });
            
            // Title click to edit
            document.querySelectorAll('.trip-title-display').forEach(display => {
                display.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const index = this.dataset.tripIndex;
                    const input = document.querySelector(`.trip-title-input[data-trip-index="${index}"]`);
                    
                    // Hide display, show input
                    this.classList.add('d-none');
                    input.classList.remove('d-none');
                    input.focus();
                    input.select();
                });
            });
            
            // Title input blur/enter to save
            document.querySelectorAll('.trip-title-input').forEach(input => {
                const saveTitle = function() {
                    const index = parseInt(this.dataset.tripIndex);
                    const newTitle = this.value.trim();
                    const display = document.querySelector(`.trip-title-display[data-trip-index="${index}"]`);
                    
                    if (newTitle) {
                        tripsData[index].title = newTitle;
                        display.querySelector('strong').textContent = newTitle;
                    }
                    
                    // Hide input, show display
                    this.classList.add('d-none');
                    display.classList.remove('d-none');
                };
                
                input.addEventListener('blur', saveTitle);
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.blur();
                    } else if (e.key === 'Escape') {
                        // Revert to original
                        const index = parseInt(this.dataset.tripIndex);
                        this.value = tripsData[index].title;
                        this.blur();
                    }
                });
            });
            
            // Flight checkbox handlers
            document.querySelectorAll('.flight-checkbox').forEach(cb => {
                cb.addEventListener('change', updateFlightSelection);
            });
            
            // Select all flights in a trip
            document.querySelectorAll('.select-all-flights').forEach(cb => {
                cb.addEventListener('change', function() {
                    const tripIndex = this.dataset.tripIndex;
                    const isChecked = this.checked;
                    document.querySelectorAll(`.flight-checkbox[data-trip-index="${tripIndex}"]`)
                        .forEach(fcb => fcb.checked = isChecked);
                    updateFlightSelection();
                });
            });
        }
        
        // Flight selection state
        const moveToTripSelect = document.getElementById('moveToTripSelect');
        const moveFlightsBtn = document.getElementById('moveFlightsBtn');
        const newTripFromFlightsBtn = document.getElementById('newTripFromFlightsBtn');
        const selectedFlightsCountSpan = document.getElementById('selectedFlightsCount');
        
        // Update flight selection UI
        function updateFlightSelection() {
            const checkedFlights = document.querySelectorAll('.flight-checkbox:checked');
            const count = checkedFlights.length;
            
            selectedFlightsCountSpan.textContent = count;
            
            // Enable/disable buttons
            moveFlightsBtn.disabled = count === 0;
            newTripFromFlightsBtn.disabled = count === 0;
            moveToTripSelect.disabled = count === 0;
            
            // Update move-to dropdown
            updateMoveToDropdown();
        }
        
        // Update the "Move to" dropdown with available trips
        function updateMoveToDropdown() {
            const checkedFlights = document.querySelectorAll('.flight-checkbox:checked');
            
            // Get which trips have selected flights
            const tripsWithSelectedFlights = new Set();
            checkedFlights.forEach(cb => {
                tripsWithSelectedFlights.add(parseInt(cb.dataset.tripIndex));
            });
            
            // Build dropdown options
            moveToTripSelect.innerHTML = '<option value="">Mover a...</option>';
            tripsData.forEach((trip, index) => {
                // Only show trips that don't have ALL the selected flights
                if (!tripsWithSelectedFlights.has(index) || tripsWithSelectedFlights.size > 1) {
                    const opt = document.createElement('option');
                    opt.value = index;
                    opt.textContent = trip.title.substring(0, 40) + (trip.title.length > 40 ? '...' : '');
                    moveToTripSelect.appendChild(opt);
                }
            });
        }
        
        // Move flights to another trip
        moveFlightsBtn.addEventListener('click', function() {
            const targetTripIndex = parseInt(moveToTripSelect.value);
            if (isNaN(targetTripIndex)) {
                alert('Selecciona un viaje de destino');
                return;
            }
            
            moveSelectedFlightsToTrip(targetTripIndex);
        });
        
        // Create new trip from selected flights
        newTripFromFlightsBtn.addEventListener('click', function() {
            const checkedFlights = document.querySelectorAll('.flight-checkbox:checked');
            if (checkedFlights.length === 0) return;
            
            // Collect selected flights
            const flightsToMove = [];
            const sourceTrips = new Map(); // tripIndex -> [flightIndices]
            
            checkedFlights.forEach(cb => {
                const tripIndex = parseInt(cb.dataset.tripIndex);
                const flightIndex = parseInt(cb.dataset.flightIndex);
                
                if (!sourceTrips.has(tripIndex)) {
                    sourceTrips.set(tripIndex, []);
                }
                sourceTrips.get(tripIndex).push(flightIndex);
                flightsToMove.push(tripsData[tripIndex].flights[flightIndex]);
            });
            
            // Sort flights by date
            flightsToMove.sort((a, b) => new Date(a.date) - new Date(b.date));
            
            // Create new trip
            const newTrip = {
                title: generateTripTitle(flightsToMove),
                flights_count: flightsToMove.length,
                start_date: flightsToMove[0].date,
                end_date: flightsToMove[flightsToMove.length - 1].date,
                flights: flightsToMove
            };
            
            // Remove flights from source trips (in reverse order)
            sourceTrips.forEach((flightIndices, tripIndex) => {
                flightIndices.sort((a, b) => b - a); // Reverse order
                flightIndices.forEach(fIndex => {
                    tripsData[tripIndex].flights.splice(fIndex, 1);
                });
                // Update trip metadata
                if (tripsData[tripIndex].flights.length > 0) {
                    updateTripMetadata(tripIndex);
                }
            });
            
            // Remove empty trips
            tripsData = tripsData.filter(trip => trip.flights.length > 0);
            
            // Add new trip
            tripsData.push(newTrip);
            
            // Sort trips by start date
            tripsData.sort((a, b) => new Date(a.start_date) - new Date(b.start_date));
            
            // Re-render
            renderTrips();
        });
        
        // Move selected flights to a specific trip
        function moveSelectedFlightsToTrip(targetTripIndex) {
            const checkedFlights = document.querySelectorAll('.flight-checkbox:checked');
            if (checkedFlights.length === 0) return;
            
            // Collect selected flights
            const flightsToMove = [];
            const sourceTrips = new Map();
            
            checkedFlights.forEach(cb => {
                const tripIndex = parseInt(cb.dataset.tripIndex);
                const flightIndex = parseInt(cb.dataset.flightIndex);
                
                if (tripIndex === targetTripIndex) return; // Skip if same trip
                
                if (!sourceTrips.has(tripIndex)) {
                    sourceTrips.set(tripIndex, []);
                }
                sourceTrips.get(tripIndex).push(flightIndex);
                flightsToMove.push(tripsData[tripIndex].flights[flightIndex]);
            });
            
            if (flightsToMove.length === 0) {
                alert('No hay vuelos para mover');
                return;
            }
            
            // Add flights to target trip
            tripsData[targetTripIndex].flights = tripsData[targetTripIndex].flights.concat(flightsToMove);
            
            // Sort target trip flights by date
            tripsData[targetTripIndex].flights.sort((a, b) => new Date(a.date) - new Date(b.date));
            
            // Update target trip metadata
            updateTripMetadata(targetTripIndex);
            
            // Remove flights from source trips
            sourceTrips.forEach((flightIndices, tripIndex) => {
                flightIndices.sort((a, b) => b - a);
                flightIndices.forEach(fIndex => {
                    tripsData[tripIndex].flights.splice(fIndex, 1);
                });
                if (tripsData[tripIndex].flights.length > 0) {
                    updateTripMetadata(tripIndex);
                }
            });
            
            // Remove empty trips
            tripsData = tripsData.filter(trip => trip.flights.length > 0);
            
            // Re-render
            renderTrips();
        }
        
        // Update trip metadata (dates, count) after flight changes
        function updateTripMetadata(tripIndex) {
            const trip = tripsData[tripIndex];
            if (!trip || trip.flights.length === 0) return;
            
            trip.flights.sort((a, b) => new Date(a.date) - new Date(b.date));
            trip.flights_count = trip.flights.length;
            trip.start_date = trip.flights[0].date;
            trip.end_date = trip.flights[trip.flights.length - 1].date;
            // Don't auto-regenerate title - keep user's custom title
        }
        
        // Merge selected trips
        mergeBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.trip-checkbox:checked');
            if (checkedBoxes.length < 2) return;
            
            // Get indices of selected trips (sorted)
            const indices = Array.from(checkedBoxes)
                .map(cb => parseInt(cb.dataset.tripIndex))
                .sort((a, b) => a - b);
            
            // Merge all selected trips into the first one
            let mergedFlights = [];
            indices.forEach(idx => {
                mergedFlights = mergedFlights.concat(tripsData[idx].flights);
            });
            
            // Sort flights by date
            mergedFlights.sort((a, b) => new Date(a.date) - new Date(b.date));
            
            // Create merged trip
            const mergedTrip = {
                title: generateTripTitle(mergedFlights),
                flights_count: mergedFlights.length,
                start_date: mergedFlights[0].date,
                end_date: mergedFlights[mergedFlights.length - 1].date,
                flights: mergedFlights
            };
            
            // Remove old trips (in reverse order to preserve indices)
            for (let i = indices.length - 1; i >= 0; i--) {
                tripsData.splice(indices[i], 1);
            }
            
            // Insert merged trip at the position of the first selected trip
            tripsData.splice(indices[0], 0, mergedTrip);
            
            // Re-render
            renderTrips();
        });
        
        // Select all
        selectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('.trip-checkbox').forEach(cb => cb.checked = true);
            updateSelectedCount();
        });
        
        // Deselect all
        deselectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('.trip-checkbox').forEach(cb => cb.checked = false);
            updateSelectedCount();
        });
        
        // Before form submit, save the modified trips data
        document.getElementById('importForm').addEventListener('submit', function(e) {
            modifiedTripsInput.value = JSON.stringify(tripsData);
        });
        
        // Initial event listeners
        attachEventListeners();
    })();
    </script>

<?php else: ?>
    <!-- Formulario de Subida -->
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-spreadsheet"></i> Subir Archivo CSV</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="preview">
                        
                        <div class="mb-4">
                            <label for="csv_file" class="form-label">
                                Archivo CSV de FlightRadar/FlightDiary <span class="text-danger">*</span>
                            </label>
                            <input type="file" class="form-control form-control-lg" id="csv_file" 
                                   name="csv_file" accept=".csv" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-upload"></i> Analizar Archivo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Cómo funciona</h6>
                </div>
                <div class="card-body">
                    <ol class="mb-0">
                        <li class="mb-2">
                            <strong>Exporta y Sube tu CSV</strong><br>
                            <small class="text-muted">
                                <a href="https://my.flightradar24.com/settings/export" target="_blank">Exportar desde FlightRadar</a> <i class="bi bi-box-arrow-up-right text-primary" style="font-size: 0.8em;"></i> 
                            </small>
                        </li>
                        <li class="mb-2">
                            <strong>Vista previa</strong><br>
                            <small class="text-muted">Revisa los viajes que se crearán antes de confirmar</small>
                        </li>
                        <li class="mb-2">
                            <strong>Importar</strong><br>
                            <small class="text-muted">Los vuelos se agrupan automáticamente en viajes (separados por más de 7 días)</small>
                        </li>
                        <li>
                            <strong>Revisar</strong><br>
                            <small class="text-muted">Los viajes se crean como "Borrador" para que puedas editarlos</small>
                        </li>
                    </ol>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header bg-warning">
                    <h6 class="mb-0"><i class="bi bi-airplane"></i> Aeropuertos soportados</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        Se incluyen <?= count($airports) ?> aeropuertos con coordenadas.
                        Si tu archivo tiene aeropuertos no reconocidos, esos vuelos se omitirán.
                    </p>
                    <details>
                        <summary class="text-primary" style="cursor: pointer;">Ver lista de aeropuertos</summary>
                        <div class="mt-2" style="max-height: 200px; overflow-y: auto;">
                            <small>
                                <?php 
                                $codes = array_keys($airports);
                                sort($codes);
                                echo implode(', ', $codes);
                                ?>
                            </small>
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Bootstrap Icons (inline for this page) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
