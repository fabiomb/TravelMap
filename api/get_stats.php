<?php
/**
 * API de Estadísticas de Viaje
 * 
 * Devuelve distancias totales y conteos por tipo de transporte
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Route.php';
require_once __DIR__ . '/../src/models/Settings.php';

try {
    $db = getDB();
    $routeModel = new Route();
    $settingsModel = new Settings($db);
    
    // Obtener estadísticas base (en metros)
    $stats = $routeModel->getStatistics();
    
    // Obtener unidad preferida del sistema
    $preferredUnit = $settingsModel->get('distance_unit', 'km');
    
    // Preparar respuesta con conversiones
    $formattedStats = [];
    $totalMeters = 0;
    
    foreach ($stats as $stat) {
        $type = $stat['transport_type'];
        $meters = (float)$stat['total_meters'];
        $totalMeters += $meters;
        
        $convertedValue = 0;
        $unitLabel = '';
        
        // Determinar unidad a usar según configuración global
        if ($preferredUnit === 'mi') {
            $convertedValue = $meters / 1609.344;
            $unitLabel = 'mi';
        } elseif ($preferredUnit === 'nm') {
            $convertedValue = $meters / 1852;
            $unitLabel = 'nm';
        } else {
            $convertedValue = $meters / 1000;
            $unitLabel = 'km';
        }
        
        $formattedStats[] = [
            'transport_type' => $type,
            'total_meters' => $meters,
            'value' => round($convertedValue, 2),
            'unit' => $unitLabel,
            'route_count' => (int)$stat['route_count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $formattedStats,
            'total_meters' => $totalMeters,
            'preferred_unit' => $preferredUnit
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener estadísticas: ' . $e->getMessage()
    ]);
}
