<?php
/**
 * API: Get Automated Route
 * 
 * Queries the configured routing service (BRouter/Google Maps) to get a route
 * between two points and saves it to the database.
 * 
 * POST parameters:
 *  - trip_id: int
 *  - from_lat, from_lng: float (origin)
 *  - to_lat, to_lng: float (destination)
 *  - transport_type: string (plane, car, bike, walk, ship, train, bus, aerial)
 *  - name: string (optional, route name)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Settings.php';
require_once __DIR__ . '/../src/models/Route.php';
require_once __DIR__ . '/../src/helpers/RoutingService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $conn = getDB();
    $settingsModel = new Settings($conn);
    $routingService = new RoutingService($settingsModel);

    if (!$routingService->isEnabled()) {
        echo json_encode(['success' => false, 'error' => 'Routing service is not enabled']);
        exit;
    }

    // Validate required parameters
    $required = ['trip_id', 'from_lat', 'from_lng', 'to_lat', 'to_lng', 'transport_type'];
    foreach ($required as $param) {
        if (!isset($_POST[$param]) || $_POST[$param] === '') {
            echo json_encode(['success' => false, 'error' => "Missing required parameter: $param"]);
            exit;
        }
    }

    $tripId = (int) $_POST['trip_id'];
    $fromLat = (float) $_POST['from_lat'];
    $fromLng = (float) $_POST['from_lng'];
    $toLat = (float) $_POST['to_lat'];
    $toLng = (float) $_POST['to_lng'];
    $transportType = $_POST['transport_type'];
    $routeName = trim($_POST['name'] ?? '');

    // Validate transport type
    $allowedTransport = ['plane', 'car', 'bike', 'walk', 'ship', 'train', 'bus', 'aerial'];
    if (!in_array($transportType, $allowedTransport)) {
        echo json_encode(['success' => false, 'error' => 'Invalid transport type']);
        exit;
    }

    // Validate coordinates
    if ($fromLat < -90 || $fromLat > 90 || $toLat < -90 || $toLat > 90 ||
        $fromLng < -180 || $fromLng > 180 || $toLng < -180 || $toLng > 180) {
        echo json_encode(['success' => false, 'error' => 'Invalid coordinates']);
        exit;
    }

    // Get the route from the service
    $result = $routingService->getRoute($fromLat, $fromLng, $toLat, $toLng, $transportType);

    // Save the route to database
    $routeModel = new Route();
    $color = Route::getColorByTransport($transportType);

    $routeId = $routeModel->create([
        'trip_id'        => $tripId,
        'transport_type' => $transportType,
        'geojson_data'   => json_encode($result['geojson']),
        'is_round_trip'  => 0,
        'color'          => $color,
        'name'           => $routeName ?: null,
        'description'    => null,
        'image_path'     => null,
        'start_datetime' => null,
        'end_datetime'   => null
    ]);

    if (!$routeId) {
        throw new Exception('Failed to save route to database');
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'route_id'        => $routeId,
            'geojson'         => $result['geojson'],
            'distance_meters' => $result['distance_meters'],
            'transport_type'  => $transportType,
            'service_type'    => $result['service_type'] ?? $routingService->getServiceType(),
            'color'           => $color,
            'name'            => $routeName
        ]
    ]);

} catch (Exception $e) {
    error_log('Routing API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
