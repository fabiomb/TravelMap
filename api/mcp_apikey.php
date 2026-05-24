<?php
/**
 * API: MCP API Key
 *
 * GET    → devuelve la API key actual del usuario (null si no tiene).
 * POST   → genera y guarda una nueva API key.
 * DELETE → revoca (elimina) la API key del usuario.
 *
 * POST y DELETE requieren token CSRF en el header X-CSRF-Token
 * o en el body JSON { "csrf_token": "..." }.
 * Solo accesible para usuarios con sesión activa (admin).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/User.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$userId = (int) get_current_user_id();
$model  = new User();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    echo json_encode([
        'success' => true,
        'api_key' => $model->getMcpApiKey($userId),
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Leer CSRF del header o del body JSON
    $csrfFromHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($csrfFromHeader === '') {
        $body = json_decode(file_get_contents('php://input'), true);
        $csrfFromHeader = $body['csrf_token'] ?? '';
    }

    csrf_verify($csrfFromHeader);

    $key = User::generateApiKey();
    $model->setMcpApiKey($userId, $key);

    echo json_encode(['success' => true, 'api_key' => $key]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $csrfFromHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($csrfFromHeader === '') {
        $body = json_decode(file_get_contents('php://input'), true);
        $csrfFromHeader = $body['csrf_token'] ?? '';
    }

    csrf_verify($csrfFromHeader);

    $model->setMcpApiKey($userId, null);

    echo json_encode(['success' => true, 'api_key' => null]);

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
}
