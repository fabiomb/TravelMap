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
    echo json_encode(['success' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../src/models/PoiImage.php';
require_once __DIR__ . '/../src/helpers/FileHelper.php';

/**
 * Corta la ejecución con un error JSON.
 */
function respond_error(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Verifica que el POI exista. Devuelve su id.
 */
function require_poi(PDO $db, $value): int {
    $poiId = (int) $value;
    if ($poiId <= 0) {
        respond_error(400, 'Invalid poi_id');
    }
    $stmt = $db->prepare('SELECT 1 FROM points_of_interest WHERE id = ?');
    $stmt->execute([$poiId]);
    if (!$stmt->fetchColumn()) {
        respond_error(404, 'Point of interest not found');
    }
    return $poiId;
}

/**
 * Verifica que la imagen exista. Devuelve su id.
 */
function require_image(PoiImage $gallery, $value): int {
    $imageId = (int) $value;
    if ($imageId <= 0) {
        respond_error(400, 'Invalid image_id');
    }
    if (!$gallery->getById($imageId)) {
        respond_error(404, 'Image not found');
    }
    return $imageId;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond_error(405, 'Method not allowed');
    }

    require_once __DIR__ . '/../config/db.php';
    $gallery = new PoiImage();
    $action  = $_POST['action'] ?? '';

    switch ($action) {
        case 'upload':
            $db = getDB();
            $poiId = require_poi($db, $_POST['poi_id'] ?? 0);

            if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
                respond_error(400, 'No image uploaded');
            }

            $upload = FileHelper::uploadImage($_FILES['image']);
            if (!$upload['success']) {
                respond_error(400, $upload['error']);
            }

            $imageId = $gallery->add($poiId, $upload['path']);
            if (!$imageId) {
                FileHelper::deleteFile($upload['path']);
                respond_error(500, 'Error saving image');
            }

            $row = $gallery->getById($imageId);
            $api = PoiImage::toApiArray([$row])[0];
            $api['sort_order'] = (int) $row['sort_order'];

            echo json_encode(['success' => true, 'image' => $api], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete':
            $imageId = require_image($gallery, $_POST['image_id'] ?? 0);
            if (!$gallery->delete($imageId)) {
                respond_error(500, 'Error deleting image');
            }
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'reorder':
            $db = getDB();
            $poiId = require_poi($db, $_POST['poi_id'] ?? 0);
            $ids = $_POST['image_ids'] ?? [];
            if (!is_array($ids)) {
                respond_error(400, 'image_ids must be an array');
            }
            // reorder() ya descarta los ids que no pertenezcan al POI
            if (!$gallery->reorder($poiId, array_map('intval', $ids))) {
                respond_error(500, 'Error reordering images');
            }
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'caption':
            $imageId = require_image($gallery, $_POST['image_id'] ?? 0);
            if (!$gallery->updateCaption($imageId, $_POST['caption'] ?? null)) {
                respond_error(500, 'Error saving caption');
            }
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        default:
            respond_error(400, 'Unknown action');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
