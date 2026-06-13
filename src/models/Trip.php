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
     * @param string|null $status Filtrar por estado: 'published', 'draft' o null para todos
     * @return array Lista de viajes
     */
    public function getAll($order_by = 'created_at DESC', $status = null, ?int $limit = null) {
        try {
            $sql = "SELECT
                        id, title, description, start_date, end_date,
                        color_hex, status, is_private, show_routes_in_timeline, created_at, updated_at
                    FROM trips";

            if ($status !== null) {
                $sql .= " WHERE status = :status";
            }

            $sql .= " ORDER BY {$order_by}";

            if ($limit !== null) {
                $sql .= " LIMIT " . (int)$limit;
            }

            $stmt = $this->db->prepare($sql);

            if ($status !== null) {
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            }

            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error al obtener viajes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene contadores de rutas y POIs para un conjunto de trip IDs en 2 queries.
     *
     * @param int[] $ids
     * @return array ['route_counts' => [id => n, ...], 'poi_counts' => [id => n, ...]]
     */
    public function getBatchCounts(array $ids): array
    {
        if (empty($ids)) {
            return ['route_counts' => [], 'poi_counts' => []];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = $this->db->prepare(
                "SELECT trip_id, COUNT(*) AS n FROM routes WHERE trip_id IN ({$placeholders}) GROUP BY trip_id"
            );
            $stmt->execute($ids);
            $routeCounts = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'n', 'trip_id');

            $stmt = $this->db->prepare(
                "SELECT trip_id, COUNT(*) AS n FROM points_of_interest WHERE trip_id IN ({$placeholders}) GROUP BY trip_id"
            );
            $stmt->execute($ids);
            $poiCounts = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'n', 'trip_id');

            return ['route_counts' => $routeCounts, 'poi_counts' => $poiCounts];
        } catch (PDOException $e) {
            error_log('Error en getBatchCounts: ' . $e->getMessage());
            return ['route_counts' => [], 'poi_counts' => []];
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
            // Curar fechas: si son vacías, nulas o '0000-00-00', poner null
            $start_date = isset($data['start_date']) && ($data['start_date'] !== '' && $data['start_date'] !== '0000-00-00') ? $data['start_date'] : null;
            $end_date = isset($data['end_date']) && ($data['end_date'] !== '' && $data['end_date'] !== '0000-00-00') ? $data['end_date'] : null;

            $stmt = $this->db->prepare('
                INSERT INTO trips (title, description, start_date, end_date, color_hex, status, is_private, show_routes_in_timeline)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $result = $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $start_date,
                $end_date,
                $data['color_hex'] ?? '#3388ff',
                $data['status'] ?? 'draft',
                !empty($data['is_private']) ? 1 : 0,
                array_key_exists('show_routes_in_timeline', $data) ? $data['show_routes_in_timeline'] : null
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
     * @param array $data Datos a actualizar (solo los campos proporcionados)
     * @return bool True si se actualizó correctamente
     */
    public function update($id, $data) {
        try {
            // Curar fechas: si son vacías, nulas o '0000-00-00', poner null
            if (array_key_exists('start_date', $data)) {
                $data['start_date'] = ($data['start_date'] !== '' && $data['start_date'] !== '0000-00-00') ? $data['start_date'] : null;
            }
            if (array_key_exists('end_date', $data)) {
                $data['end_date'] = ($data['end_date'] !== '' && $data['end_date'] !== '0000-00-00') ? $data['end_date'] : null;
            }

            // Build dynamic update query based on provided fields
            $allowedFields = ['title', 'description', 'start_date', 'end_date', 'color_hex', 'status', 'is_private', 'show_routes_in_timeline'];
            $setClauses = [];
            $values = [];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $setClauses[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($setClauses)) {
                return false; // Nothing to update
            }

            $values[] = $id; // Add ID for WHERE clause

            $sql = 'UPDATE trips SET ' . implode(', ', $setClauses) . ' WHERE id = ?';
            $stmt = $this->db->prepare($sql);

            return $stmt->execute($values);
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
            // Eliminar links de POIs y rutas del viaje antes del cascade delete
            $this->db->prepare(
                'DELETE FROM links WHERE entity_type = ? AND entity_id IN (SELECT id FROM points_of_interest WHERE trip_id = ?)'
            )->execute(['poi', $id]);
            $this->db->prepare(
                'DELETE FROM links WHERE entity_type = ? AND entity_id IN (SELECT id FROM routes WHERE trip_id = ?)'
            )->execute(['route', $id]);

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
     * Buscar viajes por texto libre, tag o rango de fechas.
     *
     * @param string|null $query    Texto a buscar en title/description (LIKE).
     * @param string|null $tag      Filtrar por tag exacto.
     * @param string|null $dateFrom Fecha mínima (Y-m-d). Filtra trips que terminan >= esta fecha.
     * @param string|null $dateTo   Fecha máxima (Y-m-d). Filtra trips que empiezan <= esta fecha.
     * @param string|null $status   'draft' | 'published' | null para todos.
     * @param int         $limit    Máximo de resultados (1-100).
     * @return array Lista de viajes.
     */
    public function search(
        ?string $query,
        ?string $tag,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $status,
        int $limit = 25
    ): array {
        $limit = max(1, min(100, $limit));

        // Escapar wildcards LIKE en PHP, luego bindear como string normal
        $qLike = null;
        if ($query !== null && $query !== '') {
            $qLike = '%' . addcslashes($query, '%_\\') . '%';
        }

        // Validar fechas
        $from = null;
        if ($dateFrom !== null && DateTime::createFromFormat('Y-m-d', $dateFrom) !== false) {
            $from = $dateFrom;
        }
        $to = null;
        if ($dateTo !== null && DateTime::createFromFormat('Y-m-d', $dateTo) !== false) {
            $to = $dateTo;
        }

        try {
            $sql = '
                SELECT DISTINCT t.id, t.title, t.description, t.start_date, t.end_date,
                                t.status, t.color_hex, t.show_routes_in_timeline, t.created_at
                FROM trips t
                LEFT JOIN trip_tags tt ON tt.trip_id = t.id
                WHERE (:q IS NULL OR t.title LIKE :q_like OR t.description LIKE :q_like)
                  AND (:tag IS NULL OR tt.tag_name = :tag)
                  AND (:fromDate IS NULL OR t.end_date   >= :fromDate OR t.end_date IS NULL)
                  AND (:toDate   IS NULL OR t.start_date <= :toDate   OR t.start_date IS NULL)
                  AND (:status IS NULL OR t.status = :status)
                ORDER BY t.start_date DESC, t.id DESC
                LIMIT :lim
            ';

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':q',        $qLike !== null ? 1 : null, $qLike !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':q_like',   $qLike,    $qLike   !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':tag',      $tag,      $tag     !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':fromDate', $from,     $from    !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':toDate',   $to,       $to      !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':status',   $status,   $status  !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':lim',      $limit,    PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error en Trip::search: ' . $e->getMessage());
            return [];
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
        if (!empty($data['status']) && !in_array($data['status'], ['draft', 'published', 'planned'])) {
            $errors['status'] = 'El estado debe ser "draft", "published" o "planned"';
        }

        return $errors;
    }
}
