<?php
/**
 * API Endpoint - Get Single Trip Data
 * 
 * Devuelve un JSON con los datos de un viaje específico
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/public_access.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/Route.php';
require_once __DIR__ . '/../src/models/Point.php';
require_once __DIR__ . '/../src/models/TripTag.php';
require_once __DIR__ . '/../src/models/PoiImage.php';
require_once __DIR__ . '/../src/helpers/FileHelper.php';

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID de viaje no especificado');
    }

    $tripId = (int)$_GET['id'];
    
    // Verificar acceso al sitio público
    $accessInfo = check_public_access();
    if (!$accessInfo['access'] || !is_trip_accessible($tripId, $accessInfo)) {
        http_response_code(403);
        throw new Exception('Acceso denegado');
    }
    
    $tripModel = new Trip();
    $routeModel = new Route();
    $pointModel = new Point();
    $tripTagModel = new TripTag();
    $poiImageModel = new PoiImage();

    // Obtener el viaje
    $trip = $tripModel->getById($tripId);
    
    if (!$trip) {
        throw new Exception('Viaje no encontrado');
    }

    // Los viajes privados solo son visibles para el administrador logueado
    if (!empty($trip['is_private']) && empty($accessInfo['is_admin'])) {
        http_response_code(403);
        throw new Exception('Acceso denegado');
    }
    
    // Obtener rutas del viaje
    $routes = $routeModel->getByTripId($tripId);
    
    // Obtener tags
    $tags = $tripTagModel->getByTripId($tripId);
    
    // Procesar rutas
    $processedRoutes = [];
    $totalDistance = 0;
    foreach ($routes as $route) {
        $dist = (int) ($route['distance_meters'] ?? 0);
        $totalDistance += $dist;
        
        $processedRoutes[] = [
            'id' => (int) $route['id'],
            'transport_type' => $route['transport_type'],
            'color' => $route['color'],
            'distance_meters' => $dist,
            'is_round_trip' => (bool) ($route['is_round_trip'] ?? false),
            'geojson' => json_decode($route['geojson_data'], true)
        ];
    }
    
    // Obtener puntos
    $points = $pointModel->getAll($tripId);

    // Resolver imágenes de galería del viaje en una sola consulta
    $tripImages = $poiImageModel->getByTripId($tripId);

    // Procesar puntos
    $processedPoints = [];
    foreach ($points as $point) {
        $thumbnail_url = null;
        if (!empty($point['image_path'])) {
            $thumb_path = FileHelper::getThumbnailPath($point['image_path']);
            $thumbnail_url = $thumb_path ? BASE_URL . '/' . $thumb_path : null;
        }
        
        $processedPoints[] = [
            'id' => (int) $point['id'],
            'title' => $point['title'],
            'description' => $point['description'],
            'location' => $point['location'], // Asegurar que este campo existe en el modelo/DB si se usa
            'type' => $point['type'],
            'icon' => $point['icon'],
            'image_url' => !empty($point['image_path']) ? BASE_URL . '/' . $point['image_path'] : null,
            'thumbnail_url' => $thumbnail_url,
            'latitude' => (float) $point['latitude'],
            'longitude' => (float) $point['longitude'],
            'visit_date' => $point['visit_date'],
            'images' => PoiImage::toApiArray($tripImages[(int) $point['id']] ?? [])
        ];
    }
    
    $response = [
        'success' => true,
        'data' => [
            'id' => (int) $trip['id'],
            'title' => $trip['title'],
            'description' => $trip['description'],
            'start_date' => $trip['start_date'],
            'end_date' => $trip['end_date'],
            'color' => $trip['color_hex'],
            'status' => $trip['status'],
            'tags' => $tags,
            'total_distance_meters' => $totalDistance,
            'routes' => $processedRoutes,
            'points' => $processedPoints
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
