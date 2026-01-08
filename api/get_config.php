<?php
/**
 * API de Configuración
 * 
 * Devuelve las configuraciones necesarias para el cliente JavaScript
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Settings.php';

try {
    // Obtener conexión a la base de datos
    $conn = getDB();
    $settingsModel = new Settings($conn);
    
    // Obtener las configuraciones necesarias para el frontend
    $mapConfig = $settingsModel->getMapConfig();
    $transportColors = $settingsModel->getTransportColors();
    $tripTagsEnabled = $settingsModel->get('trip_tags_enabled', true);
    
    // Log para depuración (comentar en producción)
    error_log('Map Config: ' . json_encode($mapConfig));
    error_log('Transport Colors: ' . json_encode($transportColors));
    
    $config = [
        'success' => true,
        'data' => [
            'map' => $mapConfig,
            'transportColors' => $transportColors,
            'tripTagsEnabled' => $tripTagsEnabled
        ]
    ];
    
    echo json_encode($config, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener configuración: ' . $e->getMessage()
    ]);
}
