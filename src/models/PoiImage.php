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

            $agrupadas = [];
            foreach ($stmt->fetchAll() as $fila) {
                $agrupadas[(int) $fila['poi_id']][] = $fila;
            }
            return $agrupadas;
        } catch (PDOException $e) {
            error_log('Error al obtener imágenes del viaje: ' . $e->getMessage());
            return [];
        }
    }

    public function getById(int $imageId): ?array {
        try {
            $stmt = $this->db->prepare('SELECT * FROM poi_images WHERE id = ?');
            $stmt->execute([$imageId]);
            $fila = $stmt->fetch();
            return $fila ?: null;
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
            $orden = (int) $stmt->fetchColumn();

            $stmt = $this->db->prepare(
                'INSERT INTO poi_images (poi_id, image_path, sort_order) VALUES (?, ?, ?)'
            );
            if (!$stmt->execute([$poiId, $imagePath, $orden])) {
                return false;
            }

            $nuevoId = (int) $this->db->lastInsertId();
            $this->syncCover($poiId);
            return $nuevoId;
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
            $imagen = $this->getById($imageId);
            if (!$imagen) {
                return false;
            }

            $stmt = $this->db->prepare('DELETE FROM poi_images WHERE id = ?');
            $resultado = $stmt->execute([$imageId]);

            if ($resultado) {
                FileHelper::deleteFile($imagen['image_path']);
                $this->syncCover((int) $imagen['poi_id']);
            }
            return $resultado;
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
            $propias = [];
            foreach ($this->getByPoiId($poiId) as $fila) {
                $propias[(int) $fila['id']] = true;
            }

            $this->db->beginTransaction();
            $stmt = $this->db->prepare('UPDATE poi_images SET sort_order = ? WHERE id = ? AND poi_id = ?');

            $orden = 0;
            foreach ($imageIds as $id) {
                $id = (int) $id;
                if (!isset($propias[$id])) {
                    continue;
                }
                $stmt->execute([$orden, $id, $poiId]);
                unset($propias[$id]);
                $orden++;
            }

            // Las que no vinieron en la lista quedan al final, en su orden previo
            foreach (array_keys($propias) as $id) {
                $stmt->execute([$orden, $id, $poiId]);
                $orden++;
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
            $portada = $stmt->fetchColumn();

            $stmt = $this->db->prepare('UPDATE points_of_interest SET image_path = ? WHERE id = ?');
            $stmt->execute([$portada !== false ? $portada : null, $poiId]);
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
            $marcadores = implode(',', array_fill(0, count($poiIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT poi_id, COUNT(*) AS total FROM poi_images WHERE poi_id IN ({$marcadores}) GROUP BY poi_id"
            );
            $stmt->execute($poiIds);

            $conteos = [];
            foreach ($stmt->fetchAll() as $fila) {
                $conteos[(int) $fila['poi_id']] = (int) $fila['total'];
            }
            return $conteos;
        } catch (PDOException $e) {
            error_log('Error al contar imágenes por lote: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Formato de salida para las APIs públicas.
     */
    public static function toApiArray(array $rows): array {
        $salida = [];
        foreach ($rows as $fila) {
            $thumb = FileHelper::getThumbnailPath($fila['image_path']);
            $salida[] = [
                'id'            => (int) $fila['id'],
                'url'           => BASE_URL . '/' . $fila['image_path'],
                'thumbnail_url' => $thumb ? BASE_URL . '/' . $thumb : null,
                'caption'       => $fila['caption'] ?? null,
            ];
        }
        return $salida;
    }
}
