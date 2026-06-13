<?php
/**
 * API Endpoint - Get All Data
 * 
 * Devuelve un JSON con todos los viajes públicos, sus rutas y puntos de interés
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/public_access.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/Route.php';
require_once __DIR__ . '/../src/models/Point.php';
require_once __DIR__ . '/../src/models/TripTag.php';
require_once __DIR__ . '/../src/models/Link.php';
require_once __DIR__ . '/../src/helpers/FileHelper.php';

try {
    // Verificar acceso al sitio público (admin logueado o contraseña válida)
    $accessInfo = check_public_access();
    
    if (!$accessInfo['access']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $allowedTrips = $accessInfo['trips'];

    $tripModel      = new Trip();
    $routeModel     = new Route();
    $pointModel     = new Point();
    $tripTagModel   = new TripTag();
    $linkModel = new Link();
    
    // Obtener todos los viajes publicados y planificados
    $publishedTrips = $tripModel->getAll('start_date DESC', 'published');
    $plannedTrips = $tripModel->getAll('start_date DESC', 'planned');
    $allTrips = array_merge($publishedTrips, $plannedTrips);

    // Los viajes privados solo son visibles para el administrador logueado
    $isAdmin = !empty($accessInfo['is_admin']);
    if (!$isAdmin) {
        $allTrips = array_filter($allTrips, function ($trip) {
            return empty($trip['is_private']);
        });
    }

    // Filtrar viajes según contraseña compartida
    $trips = [];
    if ($allowedTrips === '*') {
        $trips = $allTrips;
    } else {
        $allowedIds = array_map('intval', explode(',', $allowedTrips));
        foreach ($allTrips as $trip) {
            if (in_array((int) $trip['id'], $allowedIds, true)) {
                $trips[] = $trip;
            }
        }
    }
    
    $response = [
        'success' => true,
        'data' => [
            'trips' => []
        ]
    ];
    
    foreach ($trips as $trip) {
        // Obtener rutas del viaje
        $routes = $routeModel->getByTripId($trip['id']);
        
        // Obtener tags del viaje
        $tags = $tripTagModel->getByTripId($trip['id']);
        
        // Procesar rutas para convertir GeoJSON y calcular distancia total
        $processedRoutes = [];
        $totalDistance = 0;
        foreach ($routes as $route) {
            $dist = (int) ($route['distance_meters'] ?? 0);
            $totalDistance += $dist;
            
            // Obtener links de la ruta
            $links = Link::toApiArray($linkModel->getByRouteId((int) $route['id']));
            
            // Obtener thumbnail si existe
            $thumbnail_url = null;
            if (!empty($route['image_path'])) {
                $thumb_path = FileHelper::getThumbnailPath($route['image_path']);
                $thumbnail_url = $thumb_path ? BASE_URL . '/' . $thumb_path : null;
            }
            
            $processedRoutes[] = [
                'id' => (int) $route['id'],
                'transport_type' => $route['transport_type'],
                'color' => $route['color'],
                'distance_meters' => $dist,
                'is_round_trip' => (bool) ($route['is_round_trip'] ?? false),
                'geojson' => json_decode($route['geojson_data'], true),
                'name' => $route['name'] ?? null,
                'description' => $route['description'] ?? null,
                'image_url' => !empty($route['image_path']) ? BASE_URL . '/' . $route['image_path'] : null,
                'thumbnail_url' => $thumbnail_url,
                'start_datetime' => $route['start_datetime'] ?? null,
                'end_datetime' => $route['end_datetime'] ?? null,
                'links' => $links,
            ];
        }
        
        // Obtener puntos de interés del viaje
        $points = $pointModel->getAll($trip['id']);
        
        // Procesar puntos
        $processedPoints = [];
        foreach ($points as $point) {
            // Obtener thumbnail si existe
            $thumbnail_url = null;
            if (!empty($point['image_path'])) {
                $thumb_path = FileHelper::getThumbnailPath($point['image_path']);
                $thumbnail_url = $thumb_path ? BASE_URL . '/' . $thumb_path : null;
            }
            
            $links = Link::toApiArray($linkModel->getByPoiId((int) $point['id']));

            $processedPoints[] = [
                'id' => (int) $point['id'],
                'title' => $point['title'],
                'description' => $point['description'],
                'type' => $point['type'],
                'icon' => $point['icon'],
                'image_url' => !empty($point['image_path']) ? BASE_URL . '/' . $point['image_path'] : null,
                'thumbnail_url' => $thumbnail_url,
                'latitude' => (float) $point['latitude'],
                'longitude' => (float) $point['longitude'],
                'visit_date' => $point['visit_date'],
                'links' => $links,
            ];
        }
        
        // Agregar viaje al response
        $response['data']['trips'][] = [
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
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener los datos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
