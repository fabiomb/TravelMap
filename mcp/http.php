<?php
/**
 * TravelMap MCP Server — Transporte HTTP (Streamable HTTP / MCP 2024-11-05)
 *
 * Autenticación en dos capas (se evalúan en orden):
 *   A) Bearer tmk_<64hex>  → API Key estática almacenada en users.mcp_api_key
 *   B) Cookie PHPSESSID    → sesión web activa del admin (útil desde browser)
 *
 * Configuración del cliente remoto (.mcp.json en otra máquina):
 *   {
 *     "mcpServers": {
 *       "travelmap": {
 *         "url": "https://tudominio.com/mcp/http.php",
 *         "headers": { "Authorization": "Bearer <api-key>" }
 *       }
 *     }
 *   }
 */

define('ROOT_PATH', dirname(__DIR__));

// ── CORS ──────────────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ── Helpers de respuesta ──────────────────────────────────────────────────────
function rpcResult($id, $result): void
{
    echo json_encode(
        ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

function rpcError($id, int $code, string $message, ?array $data = null): void
{
    $err = ['code' => $code, 'message' => $message];
    if ($data !== null) {
        $err['data'] = $data;
    }
    echo json_encode(
        ['jsonrpc' => '2.0', 'id' => $id, 'error' => $err],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

// ── Bootstrap (carga DB y modelos) ────────────────────────────────────────────
ob_start();
require_once __DIR__ . '/bootstrap.php';
ob_end_clean();

// ── Autenticación ─────────────────────────────────────────────────────────────
$authOk = false;

// Capa A: Bearer API Key
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

// Capa B: sesión web (cookie PHPSESSID) — solo para same-origin
if (!$authOk && !empty($_COOKIE['PHPSESSID'])) {
    $origin   = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host     = $_SERVER['HTTP_HOST'] ?? '';
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $expected = $scheme . '://' . $host;
    if ($origin !== '' && $origin !== $expected) {
        http_response_code(403);
        rpcError(null, -32001, 'Forbidden: cross-origin session auth not allowed');
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
    rpcError(null, -32001, 'Unauthorized');
    exit;
}

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Dispatcher.php';
require_once __DIR__ . '/tools/TripTools.php';
require_once __DIR__ . '/tools/RouteTools.php';
require_once __DIR__ . '/tools/PoiTools.php';
require_once __DIR__ . '/tools/LocationTools.php';

// ── Parsear body JSON-RPC ─────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
$msg = json_decode($raw, true);

if (!is_array($msg)) {
    http_response_code(400);
    rpcError(null, -32700, 'Parse error: body no es JSON válido');
    exit;
}

$id     = $msg['id'] ?? null;
$method = $msg['method'] ?? '';
$params = $msg['params'] ?? [];

// ── Dispatcher ────────────────────────────────────────────────────────────────
$dispatcher = new Dispatcher();
TripTools::register($dispatcher);
RouteTools::register($dispatcher);
PoiTools::register($dispatcher);
LocationTools::register($dispatcher);

McpLogger::info("HTTP ← {$method}", ['id' => $id]);

// ── Routing ───────────────────────────────────────────────────────────────────
switch ($method) {

    case 'initialize':
        rpcResult($id, [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => ['tools' => ['listChanged' => false]],
            'serverInfo'      => ['name' => 'travelmap', 'version' => '0.1.0'],
        ]);
        break;

    case 'notifications/initialized':
        http_response_code(204);
        break;

    case 'tools/list':
        rpcResult($id, $dispatcher->toolsList());
        break;

    case 'tools/call':
        $toolName   = $params['name'] ?? '';
        $toolParams = $params['arguments'] ?? [];
        $requestId  = (string)($id ?? uniqid());

        McpLogger::toolCall($toolName, $toolParams, $requestId);

        try {
            $result = $dispatcher->toolsCall($toolName, $toolParams, $requestId);
            rpcResult($id, $result);
        } catch (ToolException $e) {
            McpLogger::error("ToolException [{$e->tag}]: " . $e->getMessage());
            $data = ['tag' => $e->tag, 'request_id' => $requestId];
            if ($e->extra) {
                $data = array_merge($data, $e->extra);
            }
            if ($e->rpcCode === -32602 || $e->rpcCode === -32601) {
                rpcError($id, $e->rpcCode, $e->getMessage(), $data);
            } else {
                rpcResult($id, [
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode(['error' => $e->getMessage(), 'tag' => $e->tag] + ($e->extra ?? [])),
                    ]],
                    'isError' => true,
                ]);
            }
        } catch (Throwable $e) {
            McpLogger::error('Uncaught en HTTP: ' . $e->getMessage(), [
                'file' => $e->getFile(), 'line' => $e->getLine(),
            ]);
            rpcError($id, -32603, 'Error interno del servidor');
        }
        break;

    case 'ping':
        rpcResult($id, new stdClass());
        break;

    default:
        if ($id !== null) {
            rpcError($id, -32601, "Método desconocido: {$method}");
        } else {
            http_response_code(204);
        }
        break;
}
