#!/usr/bin/env php
<?php
/**
 * TravelMap MCP Server — Punto de entrada stdio
 *
 * Protocolo: MCP 2024-11-05, transporte stdio (NDJSON).
 * Una línea JSON-RPC 2.0 por mensaje en stdin/stdout.
 * stderr: logs humanos (Claude Desktop los muestra en su panel de debug).
 *
 * Uso: php mcp/server.php
 * Config Claude Desktop: ver mcp/README.md
 */

// ── Preludio ──────────────────────────────────────────────────────────────────
// Definir ROOT_PATH antes del bootstrap
define('ROOT_PATH', dirname(__DIR__));

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/JsonRpc.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Dispatcher.php';
require_once __DIR__ . '/UploadedFiles.php';
require_once __DIR__ . '/tools/TripTools.php';
require_once __DIR__ . '/tools/RouteTools.php';
require_once __DIR__ . '/tools/PoiTools.php';
require_once __DIR__ . '/tools/LocationTools.php';

McpLogger::init();

// ── Error handler global ──────────────────────────────────────────────────────
// Convierte excepciones y fatales en -32603 sin filtrar detalles internos al cliente.

set_exception_handler(function (Throwable $e) {
    $reqId = $GLOBALS['__mcp_current_request_id'] ?? null;
    McpLogger::error('Uncaught exception: ' . $e->getMessage(), [
        'file' => $e->getFile(), 'line' => $e->getLine(),
    ]);
    JsonRpc::sendError($reqId, -32603, 'Error interno del servidor', ['request_id' => uniqid()]);
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $reqId = $GLOBALS['__mcp_current_request_id'] ?? null;
        McpLogger::error('Fatal error: ' . $err['message'], ['file' => $err['file'], 'line' => $err['line']]);
        JsonRpc::sendError($reqId, -32603, 'Error fatal del servidor');
    }
});

// ── Dispatcher ────────────────────────────────────────────────────────────────
$dispatcher = new Dispatcher();

TripTools::register($dispatcher);
RouteTools::register($dispatcher);
PoiTools::register($dispatcher);
LocationTools::register($dispatcher);

// ── Configurar streams ────────────────────────────────────────────────────────
// Forzar modo bloqueante y sin timeout en stdin/stdout para que el proceso
// no muera por idle mientras espera mensajes del cliente MCP.
stream_set_blocking(STDIN, true);
stream_set_blocking(STDOUT, true);
stream_set_timeout(STDIN, 0);  // Sin timeout de stream (0 = infinito)

// ── Bucle principal ───────────────────────────────────────────────────────────
fwrite(STDERR, "[TravelMap MCP] Servidor listo. Esperando mensajes en stdin...\n");

while (true) {
    $msg = JsonRpc::read();
    if ($msg === null) {
        fwrite(STDERR, "[TravelMap MCP] EOF — cliente desconectado.\n");
        break;
    }

    $id     = $msg['id'] ?? null;
    $method = $msg['method'] ?? '';
    $params = $msg['params'] ?? [];

    $GLOBALS['__mcp_current_request_id'] = $id;

    McpLogger::info("← {$method}", ['id' => $id]);

    switch ($method) {

        // ── Handshake ──────────────────────────────────────────────────────────
        case 'initialize':
            JsonRpc::sendResult($id, [
                'protocolVersion' => '2024-11-05',
                'capabilities'    => ['tools' => ['listChanged' => false]],
                'serverInfo'      => ['name' => 'travelmap', 'version' => '0.1.0'],
            ]);
            break;

        case 'notifications/initialized':
            // Notificación — no requiere respuesta
            break;

        // ── Tools ──────────────────────────────────────────────────────────────
        case 'tools/list':
            JsonRpc::sendResult($id, $dispatcher->toolsList());
            break;

        case 'tools/call':
            $toolName   = $params['name'] ?? '';
            $toolParams = $params['arguments'] ?? [];
            $requestId  = (string)($id ?? uniqid());

            McpLogger::toolCall($toolName, $toolParams, $requestId);

            try {
                $result = $dispatcher->toolsCall($toolName, $toolParams, $requestId);
                JsonRpc::sendResult($id, $result);
            } catch (ToolException $e) {
                McpLogger::error("ToolException [{$e->tag}]: " . $e->getMessage());
                $data = ['tag' => $e->tag, 'request_id' => $requestId];
                if ($e->extra) {
                    $data = array_merge($data, $e->extra);
                }
                // Para errores de dominio devolvemos un resultado con isError=true
                // (el cliente MCP puede distinguirlo del error de protocolo)
                if ($e->rpcCode === -32602 || $e->rpcCode === -32601) {
                    JsonRpc::sendError($id, $e->rpcCode, $e->getMessage(), $data);
                } else {
                    JsonRpc::sendResult($id, [
                        'content' => [[
                            'type' => 'text',
                            'text' => json_encode(['error' => $e->getMessage(), 'tag' => $e->tag] + ($e->extra ?? [])),
                        ]],
                        'isError' => true,
                    ]);
                }
            }
            break;

        // ── Ping ───────────────────────────────────────────────────────────────
        case 'ping':
            JsonRpc::sendResult($id, new stdClass()); // {} vacío
            break;

        // ── Método desconocido ─────────────────────────────────────────────────
        default:
            if ($id !== null) {
                // Solo respondemos si el mensaje tiene id (requests vs notifications)
                JsonRpc::sendError($id, -32601, "Método desconocido: {$method}");
            }
            break;
    }
}
