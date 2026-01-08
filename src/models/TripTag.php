<?php
/**
 * Modelo: TripTag
 * 
 * Gestiona los tags asociados a los viajes
 */

class TripTag {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Obtener todos los tags de un viaje
     * 
     * @param int $trip_id ID del viaje
     * @return array Lista de tags (strings)
     */
    public function getByTripId($trip_id) {
        try {
            $stmt = $this->db->prepare('
                SELECT tag_name 
                FROM trip_tags 
                WHERE trip_id = ? 
                ORDER BY tag_name ASC
            ');
            $stmt->execute([$trip_id]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log('Error al obtener tags del viaje: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Agregar un tag a un viaje
     * 
     * @param int $trip_id ID del viaje
     * @param string $tag_name Nombre del tag
     * @return bool True si se agregó (o ya existía), False si falló
     */
    public function add($trip_id, $tag_name) {
        try {
            $tag_name = trim($tag_name);
            if (empty($tag_name)) {
                return false;
            }

            // Usamos INSERT IGNORE para ignorar duplicados silenciosamente
            // O ON DUPLICATE KEY UPDATE id=id para MySQL compatible
            $stmt = $this->db->prepare('
                INSERT IGNORE INTO trip_tags (trip_id, tag_name) 
                VALUES (?, ?)
            ');
            return $stmt->execute([$trip_id, $tag_name]);
        } catch (PDOException $e) {
            error_log('Error al agregar tag: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar un tag específico de un viaje
     * 
     * @param int $trip_id ID del viaje
     * @param string $tag_name Nombre del tag
     * @return bool True si se eliminó correctamente
     */
    public function delete($trip_id, $tag_name) {
        try {
            $stmt = $this->db->prepare('
                DELETE FROM trip_tags 
                WHERE trip_id = ? AND tag_name = ?
            ');
            return $stmt->execute([$trip_id, $tag_name]);
        } catch (PDOException $e) {
            error_log('Error al eliminar tag: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar todos los tags de un viaje
     * 
     * @param int $trip_id ID del viaje
     * @return bool True si se eliminaron correctamente
     */
    public function deleteAll($trip_id) {
        try {
            $stmt = $this->db->prepare('DELETE FROM trip_tags WHERE trip_id = ?');
            return $stmt->execute([$trip_id]);
        } catch (PDOException $e) {
            error_log('Error al eliminar todos los tags: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sincronizar tags de un viaje (Merge)
     * 
     * Actualiza la lista de tags para que coincida exactamente con la lista proporcionada.
     * Agrega los nuevos y elimina los que no están en la lista.
     * 
     * @param int $trip_id ID del viaje
     * @param array $tags Lista de nuevos tags (strings)
     * @return bool True si la sincronización fue exitosa
     */
    public function sync($trip_id, $tags) {
        try {
            // Normalizar tags entrada (trim y filter empty)
            $newTags = array_filter(array_map('trim', $tags), function($t) { 
                return !empty($t); 
            });
            // Eliminar duplicados en la entrada (case-insensitive para ser seguro, aunque la BD lo maneja)
            $newTagsUnique = [];
            foreach ($newTags as $tag) {
                // Usamos lower case keys para deduplicar
                $newTagsUnique[mb_strtolower($tag)] = $tag;
            }
            $newTags = array_values($newTagsUnique);

            $this->db->beginTransaction();

            // 1. Obtener tags actuales
            $currentTags = $this->getByTripId($trip_id); // devuelve array de strings
            
            // Mapas para comparación case-insensitive
            $currentTagsMap = [];
            foreach ($currentTags as $t) $currentTagsMap[mb_strtolower($t)] = $t;
            
            $newTagsMap = [];
            foreach ($newTags as $t) $newTagsMap[mb_strtolower($t)] = $t;

            // 2. Identificar tags a borrar (están en current pero no en new)
            foreach ($currentTagsMap as $lower => $original) {
                if (!isset($newTagsMap[$lower])) {
                    $this->delete($trip_id, $original);
                }
            }

            // 3. Identificar tags a agregar (están en new pero no en current)
            foreach ($newTagsMap as $lower => $original) {
                if (!isset($currentTagsMap[$lower])) {
                    $this->add($trip_id, $original);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Error al sincronizar tags: ' . $e->getMessage());
            return false;
        }
    }
}
