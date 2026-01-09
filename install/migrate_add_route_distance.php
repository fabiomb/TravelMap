<?php
/**
 * Migración: Añadir campos de distancia y estadísticas a las rutas
 * Fecha: 2026-01-08
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Route.php';

function migrate_add_route_distance() {
    $db = getDB();
    
    try {
        echo "Iniciando migración: Añadir campos de distancia...\n";

        // 1. Añadir columnas a tabla routes si no existen
        try {
            $db->exec("ALTER TABLE routes 
                       ADD COLUMN is_round_trip TINYINT(1) DEFAULT 0 AFTER geojson_data,
                       ADD COLUMN distance_meters BIGINT UNSIGNED DEFAULT 0 AFTER is_round_trip");
            echo "Columnas añadidas a la tabla 'routes'.\n";
        } catch (PDOException $e) {
            if ($e->getCode() == '42S21') {
                echo "Aviso: Las columnas ya existen.\n";
            } else {
                throw $e;
            }
        }

        // 2. Calcular distancias para rutas existentes
        echo "Calculando distancias para rutas existentes...\n";
        $stmt = $db->query("SELECT id, geojson_data FROM routes WHERE distance_meters = 0");
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updateStmt = $db->prepare("UPDATE routes SET distance_meters = ? WHERE id = ?");
        $count = 0;
        
        foreach ($routes as $route) {
            $distance = Route::calculateDistance($route['geojson_data']);
            $updateStmt->execute([$distance, $route['id']]);
            $count++;
        }
        echo "Se actualizaron $count rutas con sus respectivas distancias.\n";

        // 3. Añadir configuración de unidad de distancia
        try {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, description) 
                                  VALUES ('distance_unit', 'km', 'string', 'Unidad de distancia preferida (km para Kilómetros, mi para Millas)')");
            $stmt->execute();
            echo "Configuración 'distance_unit' añadida.\n";
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                echo "Aviso: La configuración 'distance_unit' ya existe.\n";
            } else {
                throw $e;
            }
        }

        echo "Migración completada con éxito.\n";
        return true;
    } catch (PDOException $e) {
        echo "Error en la migración: " . $e->getMessage() . "\n";
        return false;
    }
}

// Ejecutar si se llama directamente
if (php_sapi_name() === 'cli' || basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    migrate_add_route_distance();
}
