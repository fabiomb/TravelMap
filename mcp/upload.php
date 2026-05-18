<?php
/**
 * TravelMap MCP Upload Endpoint
 *
 * Multipart upload companion for MCP tools. Binary files are uploaded here and
 * tools receive only lightweight tokens.
 */

define('ROOT_PATH', dirname(__DIR__));

header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
header('Access-Control-Allow-Methods: POST, OPTIONS');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $sameOrigin = $host !== '' && $origin === $scheme . '://' . $host;
    $allowedOrigins = array_filter(array_map('trim', explode(',', getenv('MCP_ALLOWED_ORIGINS') ?: '')));
    $corsAllowed = $sameOrigin || in_array($origin, $allowedOrigins, true) || in_array('*', $allowedOrigins, true);
    if ($corsAllowed) {
        header('Access-Control-Allow-Origin: ' . (in_array('*', $allowedOrigins, true) ? '*' : $origin));
        header('Vary: Origin');
    } elseif ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(403);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

ob_start();
require_once __DIR__ . '/bootstrap.php';
ob_end_clean();
require_once __DIR__ . '/UploadedFiles.php';

$authOk = false;
$authHeader = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '')
    ?: '';

if (preg_match('/^Bearer\s+(tmk_[0-9a-f]{64})$/', $authHeader, $m)) {
    $userModel = new User();
    if ($userModel->findByMcpApiKey($m[1]) !== null) {
        $authOk = true;
    }
}

if (!$authOk && !empty($_COOKIE['PHPSESSID'])) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $expected = $scheme . '://' . $host;
    if ($origin !== '' && $origin !== $expected) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden: cross-origin session auth not allowed']);
        exit;
    }
    session_name('PHPSESSID');
    session_start();
    if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
        $authOk = true;
    }
}

if (!$authOk) {
    http_response_code(401);
    header('WWW-Authenticate: Bearer realm="TravelMap MCP"');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$kind = $_POST['kind'] ?? 'photo';
if ($kind !== 'photo') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unsupported upload kind']);
    exit;
}

$file = $_FILES['photo'] ?? $_FILES['file'] ?? null;
if (!is_array($file)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing multipart file field "photo"']);
    exit;
}

$result = McpUploadedFiles::savePhotoUpload($file);
if (!$result['success']) {
    http_response_code(400);
}
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
