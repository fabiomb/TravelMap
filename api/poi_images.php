<?php
/**
 * API Endpoint - POI Images
 *
 * Administración de la galería de imágenes de un punto de interés.
 * Despacha por `action`: upload, delete, reorder, caption.
 * Todas las acciones son inmediatas (sin POST del formulario) para no chocar
 * contra post_max_size al subir varias fotos.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/PoiImage.php';
require_once __DIR__ . '/../src/helpers/FileHelper.php';

/**
 * Corta la ejecución con un error JSON.
 */
function responder_error(int $codigo, string $mensaje): void {
    http_response_code($codigo);
    echo json_encode(['success' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Verifica que el POI exista. Devuelve su id.
 */
function exigir_poi(PDO $db, $valor): int {
    $poiId = (int) $valor;
    if ($poiId <= 0) {
        responder_error(400, 'Invalid poi_id');
    }
    $stmt = $db->prepare('SELECT 1 FROM points_of_interest WHERE id = ?');
    $stmt->execute([$poiId]);
    if (!$stmt->fetchColumn()) {
        responder_error(404, 'Point of interest not found');
    }
    return $poiId;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder_error(405, 'Method not allowed');
    }

    $db      = getDB();
    $galeria = new PoiImage();
    $action  = $_POST['action'] ?? '';

    switch ($action) {
        case 'upload':
            $poiId = exigir_poi($db, $_POST['poi_id'] ?? 0);

            if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
                responder_error(400, 'No image uploaded');
            }

            $subida = FileHelper::uploadImage($_FILES['image']);
            if (!$subida['success']) {
                responder_error(400, $subida['error']);
            }

            $imageId = $galeria->add($poiId, $subida['path']);
            if (!$imageId) {
                FileHelper::deleteFile($subida['path']);
                responder_error(500, 'Error saving image');
            }

            $fila = $galeria->getById($imageId);
            $api  = PoiImage::toApiArray([$fila])[0];
            $api['sort_order'] = (int) $fila['sort_order'];

            echo json_encode(['success' => true, 'image' => $api], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete':
            $imageId = (int) ($_POST['image_id'] ?? 0);
            if ($imageId <= 0 || !$galeria->getById($imageId)) {
                responder_error(400, 'Invalid image_id');
            }
            if (!$galeria->delete($imageId)) {
                responder_error(500, 'Error deleting image');
            }
            echo json_encode(['success' => true]);
            break;

        case 'reorder':
            $poiId = exigir_poi($db, $_POST['poi_id'] ?? 0);
            $ids   = $_POST['image_ids'] ?? [];
            if (!is_array($ids)) {
                responder_error(400, 'image_ids must be an array');
            }
            // reorder() ya descarta los ids que no pertenezcan al POI
            if (!$galeria->reorder($poiId, array_map('intval', $ids))) {
                responder_error(500, 'Error reordering images');
            }
            echo json_encode(['success' => true]);
            break;

        case 'caption':
            $imageId = (int) ($_POST['image_id'] ?? 0);
            if ($imageId <= 0 || !$galeria->getById($imageId)) {
                responder_error(400, 'Invalid image_id');
            }
            if (!$galeria->updateCaption($imageId, $_POST['caption'] ?? null)) {
                responder_error(500, 'Error saving caption');
            }
            echo json_encode(['success' => true]);
            break;

        default:
            responder_error(400, 'Unknown action');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
