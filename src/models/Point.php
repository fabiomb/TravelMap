<?php
/**
 * Modelo: Point (Point of Interest)
 * 
 * Gestiona las operaciones CRUD para puntos de interés
 */

class Point {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Obtener todos los puntos de interés
     * 
     * @param int|null $trip_id Filtrar por viaje (opcional)
     * @param string $order_by Campo por el que ordenar
     * @return array Lista de puntos
     */
    public function getAll($trip_id = null, $order_by = 'visit_date DESC, created_at DESC') {
        try {
            if ($trip_id) {
                $stmt = $this->db->prepare("
                    SELECT p.*, t.title as trip_title, t.color_hex as trip_color
                    FROM points_of_interest p
                    LEFT JOIN trips t ON p.trip_id = t.id
                    WHERE p.trip_id = ?
                    ORDER BY {$order_by}
                ");
                $stmt->execute([$trip_id]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT p.*, t.title as trip_title, t.color_hex as trip_color
                    FROM points_of_interest p
                    LEFT JOIN trips t ON p.trip_id = t.id
                    ORDER BY {$order_by}
                ");
                $stmt->execute();
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error al obtener puntos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener un punto por ID
     * 
     * @param int $id ID del punto
     * @return array|null Datos del punto o null si no existe
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare('
                SELECT p.*, t.title as trip_title 
                FROM points_of_interest p
                LEFT JOIN trips t ON p.trip_id = t.id
                WHERE p.id = ?
            ');
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Error al obtener punto: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear un nuevo punto de interés
     * 
     * @param array $data Datos del punto
     * @return int|false ID del punto creado o false si falla
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO points_of_interest 
                (trip_id, title, description, type, icon, image_path, latitude, longitude, visit_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $result = $stmt->execute([
                $data['trip_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['type'],
                $data['icon'] ?? 'default',
                $data['image_path'] ?? null,
                $data['latitude'],
                $data['longitude'],
                $data['visit_date'] ?? null
            ]);

            return $result ? $this->db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log('Error al crear punto: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar un punto existente
     * 
     * @param int $id ID del punto
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     */
    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare('
                UPDATE points_of_interest 
                SET trip_id = ?, title = ?, description = ?, type = ?, 
                    icon = ?, image_path = ?, latitude = ?, longitude = ?, visit_date = ?
                WHERE id = ?
            ');
            
            return $stmt->execute([
                $data['trip_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['type'],
                $data['icon'] ?? 'default',
                $data['image_path'] ?? null,
                $data['latitude'],
                $data['longitude'],
                $data['visit_date'] ?? null,
                $id
            ]);
        } catch (PDOException $e) {
            error_log('Error al actualizar punto: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar un punto de interés
     * 
     * @param int $id ID del punto
     * @return bool True si se eliminó correctamente
     */
    public function delete($id) {
        try {
            // Obtener la imagen antes de eliminar para borrar el archivo
            $point = $this->getById($id);
            
            $stmt = $this->db->prepare('DELETE FROM points_of_interest WHERE id = ?');
            $result = $stmt->execute([$id]);
            
            // Si se eliminó correctamente y tenía imagen, borrar el archivo
            if ($result && $point && !empty($point['image_path'])) {
                $file_path = ROOT_PATH . '/' . $point['image_path'];
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log('Error al eliminar punto: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validar datos de punto de interés
     * 
     * @param array $data Datos a validar
     * @return array Array de errores (vacío si todo es válido)
     */
    public function validate($data) {
        $errors = [];

        // Trip ID requerido
        if (empty($data['trip_id']) || !is_numeric($data['trip_id'])) {
            $errors['trip_id'] = 'Debe seleccionar un viaje';
        }

        // Título requerido
        if (empty($data['title']) || trim($data['title']) === '') {
            $errors['title'] = 'El título es obligatorio';
        } elseif (strlen($data['title']) > 200) {
            $errors['title'] = 'El título no puede exceder 200 caracteres';
        }

        // Tipo requerido
        if (empty($data['type']) || !in_array($data['type'], ['stay', 'visit', 'food'])) {
            $errors['type'] = 'Debe seleccionar un tipo válido (Alojamiento, Visita, Comida)';
        }

        // Latitud requerida y válida
        if (empty($data['latitude']) && $data['latitude'] !== '0') {
            $errors['latitude'] = 'La latitud es obligatoria';
        } elseif (!is_numeric($data['latitude']) || $data['latitude'] < -90 || $data['latitude'] > 90) {
            $errors['latitude'] = 'La latitud debe estar entre -90 y 90';
        }

        // Longitud requerida y válida
        if (empty($data['longitude']) && $data['longitude'] !== '0') {
            $errors['longitude'] = 'La longitud es obligatoria';
        } elseif (!is_numeric($data['longitude']) || $data['longitude'] < -180 || $data['longitude'] > 180) {
            $errors['longitude'] = 'La longitud debe estar entre -180 y 180';
        }

        // Validar fecha si se proporciona
        if (!empty($data['visit_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $data['visit_date']);
            if (!$date || $date->format('Y-m-d') !== $data['visit_date']) {
                $errors['visit_date'] = 'La fecha debe estar en formato YYYY-MM-DD';
            }
        }

        return $errors;
    }

    /**
     * Obtener tipos de puntos disponibles
     * 
     * @return array Array asociativo de tipos
     */
    public static function getTypes() {
        return [
            'stay' => 'Alojamiento',
            'visit' => 'Punto de Visita',
            'food' => 'Restaurante/Comida'
        ];
    }

    /**
     * Obtener iconos disponibles por tipo
     * 
     * @param string $type Tipo de punto
     * @return string Nombre del icono
     */
    public static function getIconByType($type) {
        $icons = [
            'stay' => 'hotel',
            'visit' => 'camera',
            'food' => 'restaurant'
        ];
        return $icons[$type] ?? 'default';
    }
}
