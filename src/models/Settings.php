<?php
/**
 * Settings Model
 * 
 * Gestiona las configuraciones del sistema almacenadas en base de datos
 */

class Settings {
    private $db;
    private static $cache = [];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Obtiene el valor de una configuración
     * 
     * @param string $key Clave de la configuración
     * @param mixed $default Valor por defecto si no existe
     * @return mixed Valor de la configuración
     */
    public function get($key, $default = null) {
        // Usar cache para evitar múltiples consultas
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        try {
            $stmt = $this->db->prepare("SELECT setting_value, setting_type FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            
            if ($row) {
                $value = $this->castValue($row['setting_value'], $row['setting_type']);
                self::$cache[$key] = $value;
                return $value;
            }
            
            return $default;
        } catch (Exception $e) {
            error_log("Error getting setting '$key': " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Establece el valor de una configuración
     * 
     * @param string $key Clave de la configuración
     * @param mixed $value Valor a establecer
     * @param string $type Tipo de dato (string, number, boolean, json)
     * @param string $description Descripción de la configuración
     * @return bool True si se guardó correctamente
     */
    public function set($key, $value, $type = 'string', $description = '') {
        try {
            // Convertir el valor según el tipo
            $valueStr = $this->valueToString($value, $type);
            
            $stmt = $this->db->prepare("
                INSERT INTO settings (setting_key, setting_value, setting_type, description)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    setting_type = VALUES(setting_type),
                    description = VALUES(description)
            ");
            
            $result = $stmt->execute([$key, $valueStr, $type, $description]);
            
            // Actualizar cache
            if ($result) {
                self::$cache[$key] = $value;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error setting '$key': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene todas las configuraciones
     * 
     * @return array Array asociativo con todas las configuraciones
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value, setting_type, description FROM settings ORDER BY setting_key");
            $settings = [];
            
            while ($row = $stmt->fetch()) {
                $settings[] = [
                    'key' => $row['setting_key'],
                    'value' => $this->castValue($row['setting_value'], $row['setting_type']),
                    'type' => $row['setting_type'],
                    'description' => $row['description']
                ];
            }
            
            return $settings;
        } catch (Exception $e) {
            error_log("Error getting all settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene todas las configuraciones como array asociativo key => value
     * 
     * @return array Array con configuraciones
     */
    public function getAllAsArray() {
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value, setting_type FROM settings");
            $settings = [];
            
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $this->castValue($row['setting_value'], $row['setting_type']);
            }
            
            return $settings;
        } catch (Exception $e) {
            error_log("Error getting settings array: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Actualiza múltiples configuraciones
     * 
     * @param array $settings Array asociativo key => value
     * @return bool True si todas se actualizaron correctamente
     */
    public function updateMultiple($settings) {
        $success = true;
        
        foreach ($settings as $key => $data) {
            $value = $data['value'] ?? $data;
            $type = $data['type'] ?? 'string';
            
            if (!$this->set($key, $value, $type)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Elimina una configuración
     * 
     * @param string $key Clave de la configuración
     * @return bool True si se eliminó correctamente
     */
    public function delete($key) {
        try {
            $stmt = $this->db->prepare("DELETE FROM settings WHERE setting_key = ?");
            $result = $stmt->execute([$key]);
            
            // Limpiar cache
            if ($result && isset(self::$cache[$key])) {
                unset(self::$cache[$key]);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error deleting setting '$key': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene las configuraciones de colores de transporte
     * 
     * @return array Array asociativo con los colores por tipo de transporte
     */
    public function getTransportColors() {
        $colors = [
            'plane' => $this->get('transport_color_plane', '#FF4444'),
            'ship' => $this->get('transport_color_ship', '#00AAAA'),
            'car' => $this->get('transport_color_car', '#4444FF'),
            'train' => $this->get('transport_color_train', '#FF8800'),
            'walk' => $this->get('transport_color_walk', '#44FF44'),
            'bus' => $this->get('transport_color_bus', '#9C27B0'),
            'aerial' => $this->get('transport_color_aerial', '#E91E63'),
            'bike' => $this->get('transport_color_bike', '#b88907')
        ];
        
        return $colors;
    }
    
    /**
     * Obtiene las configuraciones del mapa
     * 
     * @return array Array con las configuraciones del mapa
     */
    public function getMapConfig() {
        return [
            'clusterEnabled' => $this->get('map_cluster_enabled', true),
            'maxClusterRadius' => $this->get('map_cluster_max_radius', 30),
            'disableClusteringAtZoom' => $this->get('map_cluster_disable_at_zoom', 15),
            'style' => $this->get('map_style', 'voyager'),
            'distanceUnit' => $this->get('distance_unit', 'km')
        ];
    }
    
    /**
     * Limpia el cache de configuraciones
     */
    public static function clearCache() {
        self::$cache = [];
    }
    
    /**
     * Convierte un string de la BD al tipo correcto
     * 
     * @param string $value Valor como string
     * @param string $type Tipo de dato
     * @return mixed Valor convertido
     */
    private function castValue($value, $type) {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? (strpos($value, '.') !== false ? (float)$value : (int)$value) : 0;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true) ?: [];
            case 'string':
            default:
                return $value;
        }
    }
    
    /**
     * Convierte un valor al formato string para la BD
     * 
     * @param mixed $value Valor a convertir
     * @param string $type Tipo de dato
     * @return string Valor como string
     */
    private function valueToString($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'json':
                return json_encode($value);
            case 'number':
            case 'string':
            default:
                return (string)$value;
        }
    }
}
