<?php
/**
 * Modelo: Trip
 * 
 * Gestiona las operaciones CRUD para viajes
 */

class Trip {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Obtener todos los viajes
     * 
     * @param string $order_by Campo por el que ordenar
     * @return array Lista de viajes
     */
    public function getAll($order_by = 'created_at DESC') {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id, title, description, start_date, end_date, 
                    color_hex, status, created_at, updated_at
                FROM trips 
                ORDER BY {$order_by}
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error al obtener viajes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener un viaje por ID
     * 
     * @param int $id ID del viaje
     * @return array|null Datos del viaje o null si no existe
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare('SELECT * FROM trips WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Error al obtener viaje: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear un nuevo viaje
     * 
     * @param array $data Datos del viaje
     * @return int|false ID del viaje creado o false si falla
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO trips (title, description, start_date, end_date, color_hex, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            
            $result = $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['color_hex'] ?? '#3388ff',
                $data['status'] ?? 'draft'
            ]);

            return $result ? $this->db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log('Error al crear viaje: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar un viaje existente
     * 
     * @param int $id ID del viaje
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     */
    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare('
                UPDATE trips 
                SET title = ?, description = ?, start_date = ?, 
                    end_date = ?, color_hex = ?, status = ?
                WHERE id = ?
            ');
            
            return $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['color_hex'] ?? '#3388ff',
                $data['status'] ?? 'draft',
                $id
            ]);
        } catch (PDOException $e) {
            error_log('Error al actualizar viaje: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar un viaje
     * 
     * @param int $id ID del viaje
     * @return bool True si se eliminó correctamente
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare('DELETE FROM trips WHERE id = ?');
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log('Error al eliminar viaje: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Contar puntos de interés de un viaje
     * 
     * @param int $trip_id ID del viaje
     * @return int Número de puntos
     */
    public function countPoints($trip_id) {
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) as total FROM points_of_interest WHERE trip_id = ?');
            $stmt->execute([$trip_id]);
            return (int) $stmt->fetch()['total'];
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Contar rutas de un viaje
     * 
     * @param int $trip_id ID del viaje
     * @return int Número de rutas
     */
    public function countRoutes($trip_id) {
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) as total FROM routes WHERE trip_id = ?');
            $stmt->execute([$trip_id]);
            return (int) $stmt->fetch()['total'];
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Validar datos de viaje
     * 
     * @param array $data Datos a validar
     * @return array Array de errores (vacío si todo es válido)
     */
    public function validate($data) {
        $errors = [];

        // Título requerido
        if (empty($data['title']) || trim($data['title']) === '') {
            $errors['title'] = 'El título es obligatorio';
        } elseif (strlen($data['title']) > 200) {
            $errors['title'] = 'El título no puede exceder 200 caracteres';
        }

        // Validar fechas si se proporcionan
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            $start = strtotime($data['start_date']);
            $end = strtotime($data['end_date']);
            
            if ($start > $end) {
                $errors['dates'] = 'La fecha de inicio no puede ser posterior a la fecha de fin';
            }
        }

        // Validar color hex
        if (!empty($data['color_hex']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color_hex'])) {
            $errors['color_hex'] = 'El color debe estar en formato hexadecimal (#RRGGBB)';
        }

        // Validar status
        if (!empty($data['status']) && !in_array($data['status'], ['public', 'draft'])) {
            $errors['status'] = 'El estado debe ser "public" o "draft"';
        }

        return $errors;
    }
}
