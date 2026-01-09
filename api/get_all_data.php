<?php
/**
 * API Endpoint - Get All Data
 * 
 * Devuelve un JSON con todos los viajes públicos, sus rutas y puntos de interés
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/Route.php';
require_once __DIR__ . '/../src/models/Point.php';
require_once __DIR__ . '/../src/models/TripTag.php';
require_once __DIR__ . '/../src/helpers/FileHelper.php';

try {
    $tripModel = new Trip();
    $routeModel = new Route();
    $pointModel = new Point();
    $tripTagModel = new TripTag();
    
    // Obtener todos los viajes publicados
    $trips = $tripModel->getAll('start_date DESC', 'published');
    
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
            
            $processedRoutes[] = [
                'id' => (int) $route['id'],
                'transport_type' => $route['transport_type'],
                'color' => $route['color'],
                'distance_meters' => $dist,
                'is_round_trip' => (bool) ($route['is_round_trip'] ?? false),
                'geojson' => json_decode($route['geojson_data'], true)
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
                'visit_date' => $point['visit_date']
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
