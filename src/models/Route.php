<?php
/**
 * Modelo: Route
 * 
 * Gestiona las operaciones CRUD para rutas de viajes
 */

class Route {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Obtener todas las rutas de un viaje
     * 
     * @param int $trip_id ID del viaje
     * @return array Lista de rutas
     */
    public function getByTripId($trip_id) {
        try {
            $stmt = $this->db->prepare('
                SELECT * FROM routes 
                WHERE trip_id = ? 
                ORDER BY created_at ASC
            ');
            $stmt->execute([$trip_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error al obtener rutas: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener una ruta por ID
     * 
     * @param int $id ID de la ruta
     * @return array|null Datos de la ruta o null si no existe
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare('SELECT * FROM routes WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Error al obtener ruta: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear una nueva ruta
     * 
     * @param array $data Datos de la ruta
     * @return int|false ID de la ruta creada o false si falla
     */
    public function create($data) {
        try {
            $distance = 0;
            if (isset($data['geojson_data'])) {
                $distance = self::calculateDistance($data['geojson_data']);
                if (!empty($data['is_round_trip'])) {
                    $distance *= 2;
                }
            }

            $stmt = $this->db->prepare('
                INSERT INTO routes (trip_id, transport_type, geojson_data, is_round_trip, distance_meters, color)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            
            $result = $stmt->execute([
                $data['trip_id'],
                $data['transport_type'],
                $data['geojson_data'],
                $data['is_round_trip'] ?? 1,
                $distance,
                $data['color'] ?? '#3388ff'
            ]);

            return $result ? $this->db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log('Error al crear ruta: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar una ruta existente
     * 
     * @param int $id ID de la ruta
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     */
    public function update($id, $data) {
        try {
            $distance = 0;
            if (isset($data['geojson_data'])) {
                $distance = self::calculateDistance($data['geojson_data']);
                if (!empty($data['is_round_trip'])) {
                    $distance *= 2;
                }
            }

            $stmt = $this->db->prepare('
                UPDATE routes 
                SET transport_type = ?, geojson_data = ?, is_round_trip = ?, distance_meters = ?, color = ?
                WHERE id = ?
            ');
            
            return $stmt->execute([
                $data['transport_type'],
                $data['geojson_data'],
                $data['is_round_trip'] ?? 0,
                $distance,
                $data['color'] ?? '#3388ff',
                $id
            ]);
        } catch (PDOException $e) {
            error_log('Error al actualizar ruta: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar una ruta
     * 
     * @param int $id ID de la ruta
     * @return bool True si se eliminó correctamente
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare('DELETE FROM routes WHERE id = ?');
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log('Error al eliminar ruta: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar todas las rutas de un viaje
     * 
     * @param int $trip_id ID del viaje
     * @return bool True si se eliminaron correctamente
     */
    public function deleteByTripId($trip_id) {
        try {
            $stmt = $this->db->prepare('DELETE FROM routes WHERE trip_id = ?');
            return $stmt->execute([$trip_id]);
        } catch (PDOException $e) {
            error_log('Error al eliminar rutas del viaje: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Guardar múltiples rutas desde GeoJSON
     * 
     * @param int $trip_id ID del viaje
     * @param array $routes_data Array de rutas con datos
     * @return bool True si se guardó correctamente
     */
    public function saveMultipleRoutes($trip_id, $routes_data) {
        try {
            // Eliminar rutas existentes del viaje
            $this->deleteByTripId($trip_id);
            
            // Insertar nuevas rutas
            foreach ($routes_data as $route) {
                $this->create([
                    'trip_id' => $trip_id,
                    'transport_type' => $route['transport_type'] ?? 'car',
                    'geojson_data' => $route['geojson_data'],
                    'is_round_trip' => $route['is_round_trip'] ?? 1,
                    'color' => $route['color'] ?? $this->getColorByTransport($route['transport_type'] ?? 'car')
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Error al guardar múltiples rutas: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Calcula la distancia total en metros de un GeoJSON LineString
     * 
     * @param string $geojson JSON de la ruta
     * @return int Distancia en metros
     */
    public static function calculateDistance($geojson) {
        $data = json_decode($geojson, true);
        if (!$data || !isset($data['geometry']['coordinates'])) {
            return 0;
        }

        $coords = $data['geometry']['coordinates'];
        $totalDistance = 0;

        for ($i = 0; $i < count($coords) - 1; $i++) {
            $totalDistance += self::haversineDistance(
                $coords[$i][1], $coords[$i][0],
                $coords[$i+1][1], $coords[$i+1][0]
            );
        }

        return (int) round($totalDistance);
    }

    /**
     * Fórmula de Haversine para distancia entre dos puntos
     */
    private static function haversineDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371000; // Metros

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Obtener color según tipo de transporte
     * 
     * @param string $transport_type Tipo de transporte
     * @return string Color hexadecimal
     */
    public static function getColorByTransport($transport_type) {
        $colors = [
            'plane' => '#FF4444',    // Rojo
            'car' => '#4444FF',      // Azul
            'bike' => '#b88907',     // Marrón
            'walk' => '#44FF44',     // Verde
            'ship' => '#00AAAA',     // Cyan
            'train' => '#FF8800',    // Naranja
            'bus' => '#9C27B0',      // Púrpura
            'aerial' => '#E91E63'    // Rosa
        ];
        return $colors[$transport_type] ?? '#3388ff';
    }

    /**
     * Obtener tipos de transporte disponibles
     * 
     * @return array Array asociativo de tipos
     */
    public static function getTransportTypes() {
        return [
            'plane' => __('map.transport_plane'),
            'car' => __('map.transport_car'),
            'bike' => __('map.transport_bike'),
            'walk' => __('map.transport_walk'),
            'ship' => __('map.transport_ship'),
            'train' => __('map.transport_train'),
            'bus' => __('map.transport_bus'),
            'aerial' => __('map.transport_aerial')
        ];
    }

    /**
     * SVG icons for transport types
     */
    private static $transportIcons = [
        'plane' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/></svg>',
        'car' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 15.4222V18.5C22 18.9659 22 19.1989 21.9239 19.3827C21.8224 19.6277 21.6277 19.8224 21.3827 19.9239C21.1989 20 20.9659 20 20.5 20C20.0341 20 19.8011 20 19.6173 19.9239C19.3723 19.8224 19.1776 19.6277 19.0761 19.3827C19 19.1989 19 18.9659 19 18.5C19 18.0341 19 17.8011 18.9239 17.6173C18.8224 17.3723 18.6277 17.1776 18.3827 17.0761C18.1989 17 17.9659 17 17.5 17H6.5C6.03406 17 5.80109 17 5.61732 17.0761C5.37229 17.1776 5.17761 17.3723 5.07612 17.6173C5 17.8011 5 18.0341 5 18.5C5 18.9659 5 19.1989 4.92388 19.3827C4.82239 19.6277 4.62771 19.8224 4.38268 19.9239C4.19891 20 3.96594 20 3.5 20C3.03406 20 2.80109 20 2.61732 19.9239C2.37229 19.8224 2.17761 19.6277 2.07612 19.3827C2 19.1989 2 18.9659 2 18.5V15.4222C2 14.22 2 13.6188 2.17163 13.052C2.34326 12.4851 2.67671 11.9849 3.3436 10.9846L4 10L4.96154 7.69231C5.70726 5.90257 6.08013 5.0077 6.8359 4.50385C7.59167 4 8.56112 4 10.5 4H13.5C15.4389 4 16.4083 4 17.1641 4.50385C17.9199 5.0077 18.2927 5.90257 19.0385 7.69231L20 10L20.6564 10.9846C21.3233 11.9849 21.6567 12.4851 21.8284 13.052C22 13.6188 22 14.22 22 15.4222Z"/><path d="M2 8.5L4 10L5.76114 10.4403C5.91978 10.4799 6.08269 10.5 6.24621 10.5H17.7538C17.9173 10.5 18.0802 10.4799 18.2389 10.4403L20 10L22 8.5"/><path d="M18 14V14.01"/><path d="M6 14V14.01"/></svg>',
        'bike' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"><g transform="translate(3 0)"><path d="m8.632 15.526c-1.162.003-2.102.944-2.106 2.105v4.264.041c0 1.163.943 2.106 2.106 2.106s2.106-.943 2.106-2.106c0-.014 0-.029 0-.043v.002-4.263c-.003-1.161-.944-2.102-2.104-2.106z"></path><path d="m16.263 2.631h-4.053c-.491-1.537-1.907-2.631-3.579-2.631s-3.087 1.094-3.571 2.604l-.007.027h-4c-.581 0-1.053.471-1.053 1.053s.471 1.053 1.053 1.053h4.053c.268.899.85 1.635 1.615 2.096l.016.009c-2.871.867-4.929 3.48-4.947 6.577v5.528c.009.956.781 1.728 1.736 1.737h1.422v-3c0-2.064 1.673-3.737 3.737-3.737s3.737 1.673 3.737 3.737v3h1.421c.957-.008 1.73-.781 1.738-1.737v-5.474c-.001-3.105-2.067-5.726-4.899-6.567l-.048-.012c.782-.471 1.363-1.206 1.625-2.08l.007-.026h4.053c.581-.002 1.051-.472 1.053-1.053-.023-.601-.505-1.083-1.104-1.105h-.002z"></path><path d="m8.631 5.84c-1.163 0-2.106-.943-2.106-2.106s.943-2.106 2.106-2.106 2.106.943 2.106 2.106c.001.018.001.039.001.06 0 1.13-.916 2.046-2.046 2.046-.021 0-.042 0-.063-.001h.003z"></path></g></svg>',
        'train' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 3H6.73259C9.34372 3 10.6493 3 11.8679 3.40119C13.0866 3.80239 14.1368 4.57795 16.2373 6.12907L19.9289 8.85517C19.9692 8.88495 19.9894 8.89984 20.0084 8.91416C21.2491 9.84877 21.985 11.307 21.9998 12.8603C22 12.8841 22 12.9091 22 12.9593C22 12.9971 22 13.016 21.9997 13.032C21.9825 14.1115 21.1115 14.9825 20.032 14.9997C20.016 15 19.9971 15 19.9593 15H2"/><path d="M2 11H6.095C8.68885 11 9.98577 11 11.1857 11.451C12.3856 11.9019 13.3983 12.77 15.4238 14.5061L16 15"/><path d="M10 7H17"/><path d="M2 19H22"/><path d="M18 19V21"/><path d="M12 19V21"/><path d="M6 19V21"/></svg>',
        'ship' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M2 21.1932C2.68524 22.2443 3.57104 22.2443 4.27299 21.1932C6.52985 17.7408 8.67954 23.6764 10.273 21.2321C12.703 17.5694 14.4508 23.9218 16.273 21.1932C18.6492 17.5582 20.1295 23.5776 22 21.5842"/><path d="M3.57228 17L2.07481 12.6457C1.80373 11.8574 2.30283 11 3.03273 11H20.8582C23.9522 11 19.9943 17 17.9966 17"/><path d="M18 11L15.201 7.50122C14.4419 6.55236 13.2926 6 12.0775 6H8C6.89543 6 6 6.89543 6 8V11"/><path d="M10 6V3C10 2.44772 9.55228 2 9 2H8"/></svg>',
        'walk' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 12.5L7.73811 9.89287C7.91034 9.63452 8.14035 9.41983 8.40993 9.26578L10.599 8.01487C11.1619 7.69323 11.8483 7.67417 12.4282 7.9641C13.0851 8.29255 13.4658 8.98636 13.7461 9.66522C14.2069 10.7814 15.3984 12 18 12"/><path d="M13 9L11.7772 14.5951M10.5 8.5L9.77457 11.7645C9.6069 12.519 9.88897 13.3025 10.4991 13.777L14 16.5L15.5 21"/><path d="M9.5 16L9 17.5L6.5 20.5"/><path d="M15 4.5C15 5.32843 14.3284 6 13.5 6C12.6716 6 12 5.32843 12 4.5C12 3.67157 12.6716 3 13.5 3C14.3284 3 15 3.67157 15 4.5Z"/></svg>',
        'bus' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.00391 10V5M11.0039 10V5M16.0039 10V5.5"/><path d="M5.01609 17C3.59614 17 2.88616 17 2.44503 16.5607C2.00391 16.1213 2.00391 15.4142 2.00391 14V8C2.00391 6.58579 2.00391 5.87868 2.44503 5.43934C2.88616 5 3.59614 5 5.01609 5H12.1005C15.5742 5 17.311 5 18.6402 5.70624C19.619 6.22633 20.4346 7.0055 20.9971 7.95786C21.7609 9.25111 21.8332 10.9794 21.9779 14.436C22.0168 15.3678 22.0363 15.8337 21.8542 16.1862C21.7204 16.4454 21.5135 16.6601 21.2591 16.8041C20.913 17 20.4449 17 19.5085 17H19.0039M9.00391 17H15.0039"/><path d="M7.00391 19C8.10848 19 9.00391 18.1046 9.00391 17C9.00391 15.8954 8.10848 15 7.00391 15C5.89934 15 5.00391 15.8954 5.00391 17C5.00391 18.1046 5.89934 19 7.00391 19Z"/><path d="M17.0039 19C18.1085 19 19.0039 18.1046 19.0039 17C19.0039 15.8954 18.1085 15 17.0039 15C15.8993 15 15.0039 15.8954 15.0039 17C15.0039 18.1046 15.8993 19 17.0039 19Z"/><path d="M1.99609 10.0009H15.3641C15.9911 10.0009 16.2041 10.3681 16.6841 10.9441C17.2361 11.4841 17.6093 11.8628 18.1241 11.9401C18.8441 12.0481 21.5081 11.9941 21.5081 11.9941"/></svg>',
        'aerial' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 8.93333C20 14 14.4615 18 12 18C9.53846 18 4 14 4 8.93333C4 5.10416 7.58172 2 12 2C16.4183 2 20 5.10416 20 8.93333Z"/><path d="M15 8.93333C15 14 12.9231 18 12 18C11.0769 18 9 14 9 8.93333C9 5.10416 10.3431 2 12 2C13.6569 2 15 5.10416 15 8.93333Z"/><path d="M9 20C9 19.535 9 19.3025 9.05111 19.1118C9.18981 18.5941 9.59413 18.1898 10.1118 18.0511C10.3025 18 10.535 18 11 18H13C13.465 18 13.6975 18 13.8882 18.0511C14.4059 18.1898 14.8102 18.5941 14.9489 19.1118C15 19.3025 15 19.535 15 20C15 20.465 15 20.6975 14.9489 20.8882C14.8102 21.4059 14.4059 21.8102 13.8882 21.9489C13.6975 22 13.465 22 13 22H11C10.535 22 10.3025 22 10.1118 21.9489C9.59413 21.8102 9.18981 21.4059 9.05111 20.8882C9 20.6975 9 20.465 9 20Z"/></svg>'
    ];

    /**
     * Obtener icono según tipo de transporte
     * 
     * @param string $transport_type Tipo de transporte
     * @return string SVG icon
     */
    public static function getIconByTransport($transport_type) {
        return self::$transportIcons[$transport_type] ?? self::$transportIcons['car'];
    }

    /**
     * Obtiene estadísticas globales de viajes por tipo de transporte
     * 
     * @return array Estadísticas agregadas
     */
    public function getStatistics() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    transport_type, 
                    SUM(distance_meters * (CASE WHEN is_round_trip = 1 THEN 2 ELSE 1 END)) as total_meters,
                    COUNT(*) as route_count
                FROM routes 
                GROUP BY transport_type
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener estadísticas de rutas: ' . $e->getMessage());
            return [];
        }
    }
}
