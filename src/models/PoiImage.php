<?php
/**
 * Modelo: PoiImage
 *
 * Galería de imágenes de un punto de interés. Es el ÚNICO lugar del sistema
 * que escribe points_of_interest.image_path, que funciona como espejo de la
 * portada (la imagen de menor sort_order).
 */

require_once __DIR__ . '/../helpers/FileHelper.php';

class PoiImage {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Imágenes de un POI, en orden de galería.
     */
    public function getByPoiId(int $poiId): array {
        try {
            $stmt = $this->db->prepare('
                SELECT id, poi_id, image_path, caption, sort_order
                FROM poi_images
                WHERE poi_id = ?
                ORDER BY sort_order ASC, id ASC
            ');
            $stmt->execute([$poiId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error al obtener imágenes del POI: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Todas las imágenes de un viaje agrupadas por poi_id.
     * Una sola consulta: evita N+1 en las APIs públicas.
     */
    public function getByTripId(int $tripId): array {
        try {
            $stmt = $this->db->prepare('
                SELECT i.id, i.poi_id, i.image_path, i.caption, i.sort_order
                FROM poi_images i
                INNER JOIN points_of_interest p ON p.id = i.poi_id
                WHERE p.trip_id = ?
                ORDER BY i.poi_id ASC, i.sort_order ASC, i.id ASC
            ');
            $stmt->execute([$tripId]);

            $grouped = [];
            foreach ($stmt->fetchAll() as $row) {
                $grouped[(int) $row['poi_id']][] = $row;
            }
            return $grouped;
        } catch (PDOException $e) {
            error_log('Error al obtener imágenes del viaje: ' . $e->getMessage());
            return [];
        }
    }

    public function getById(int $imageId): ?array {
        try {
            $stmt = $this->db->prepare('SELECT * FROM poi_images WHERE id = ?');
            $stmt->execute([$imageId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener imagen: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Agrega una imagen al final de la galería.
     */
    public function add(int $poiId, string $imagePath) {
        try {
            $stmt = $this->db->prepare(
                'SELECT COALESCE(MAX(sort_order) + 1, 0) FROM poi_images WHERE poi_id = ?'
            );
            $stmt->execute([$poiId]);
            $order = (int) $stmt->fetchColumn();

            $stmt = $this->db->prepare(
                'INSERT INTO poi_images (poi_id, image_path, sort_order) VALUES (?, ?, ?)'
            );
            if (!$stmt->execute([$poiId, $imagePath, $order])) {
                return false;
            }

            $newId = (int) $this->db->lastInsertId();
            $this->syncCover($poiId);
            return $newId;
        } catch (PDOException $e) {
            error_log('Error al agregar imagen: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Borra la fila y el archivo en disco (FileHelper también borra el thumbnail).
     */
    public function delete(int $imageId): bool {
        try {
            $image = $this->getById($imageId);
            if (!$image) {
                return false;
            }

            $stmt = $this->db->prepare('DELETE FROM poi_images WHERE id = ?');
            $result = $stmt->execute([$imageId]);

            if ($result) {
                FileHelper::deleteFile($image['image_path']);
                $this->syncCover((int) $image['poi_id']);
            }
            return $result;
        } catch (PDOException $e) {
            error_log('Error al eliminar imagen: ' . $e->getMessage());
            return false;
        }
    }

    public function updateCaption(int $imageId, ?string $caption): bool {
        try {
            $caption = ($caption === null || trim($caption) === '') ? null : mb_substr(trim($caption), 0, 255);
            $stmt = $this->db->prepare('UPDATE poi_images SET caption = ? WHERE id = ?');
            return $stmt->execute([$caption, $imageId]);
        } catch (PDOException $e) {
            error_log('Error al actualizar caption: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reasigna sort_order según el orden recibido.
     * Ignora los ids que no pertenezcan al POI indicado.
     */
    public function reorder(int $poiId, array $imageIds): bool {
        try {
            $ownIds = [];
            foreach ($this->getByPoiId($poiId) as $row) {
                $ownIds[(int) $row['id']] = true;
            }

            $this->db->beginTransaction();
            $stmt = $this->db->prepare('UPDATE poi_images SET sort_order = ? WHERE id = ? AND poi_id = ?');

            $order = 0;
            foreach ($imageIds as $id) {
                $id = (int) $id;
                if (!isset($ownIds[$id])) {
                    continue;
                }
                $stmt->execute([$order, $id, $poiId]);
                unset($ownIds[$id]);
                $order++;
            }

            // Las que no vinieron en la lista quedan al final, en su orden previo
            foreach (array_keys($ownIds) as $id) {
                $stmt->execute([$order, $id, $poiId]);
                $order++;
            }

            $this->db->commit();
            $this->syncCover($poiId);
            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Error al reordenar imágenes: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sincroniza points_of_interest.image_path con la portada de la galería.
     * Único punto de escritura de la columna espejo.
     */
    public function syncCover(int $poiId): void {
        try {
            $stmt = $this->db->prepare('
                SELECT image_path FROM poi_images
                WHERE poi_id = ?
                ORDER BY sort_order ASC, id ASC
                LIMIT 1
            ');
            $stmt->execute([$poiId]);
            $cover = $stmt->fetchColumn();

            $stmt = $this->db->prepare('UPDATE points_of_interest SET image_path = ? WHERE id = ?');
            $stmt->execute([$cover !== false ? $cover : null, $poiId]);
        } catch (PDOException $e) {
            error_log('Error al sincronizar portada: ' . $e->getMessage());
        }
    }

    public function countByPoiId(int $poiId): int {
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM poi_images WHERE poi_id = ?');
            $stmt->execute([$poiId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Error al contar imágenes: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cantidad de imágenes para varios POIs en una sola consulta.
     * Evita el N+1 en los listados del admin.
     *
     * @return array [poi_id => cantidad]. Los POIs sin imágenes no aparecen.
     */
    public function countByPoiIds(array $poiIds): array {
        $poiIds = array_values(array_unique(array_map('intval', $poiIds)));
        if (empty($poiIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($poiIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT poi_id, COUNT(*) AS total FROM poi_images WHERE poi_id IN ({$placeholders}) GROUP BY poi_id"
            );
            $stmt->execute($poiIds);

            $counts = [];
            foreach ($stmt->fetchAll() as $row) {
                $counts[(int) $row['poi_id']] = (int) $row['total'];
            }
            return $counts;
        } catch (PDOException $e) {
            error_log('Error al contar imágenes por lote: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Formato de salida para las APIs públicas.
     */
    public static function toApiArray(array $rows): array {
        $output = [];
        foreach ($rows as $row) {
            $thumb = FileHelper::getThumbnailPath($row['image_path']);
            $output[] = [
                'id'            => (int) $row['id'],
                'url'           => BASE_URL . '/' . $row['image_path'],
                'thumbnail_url' => $thumb ? BASE_URL . '/' . $thumb : null,
                'caption'       => $row['caption'] ?? null,
            ];
        }
        return $output;
    }
}
