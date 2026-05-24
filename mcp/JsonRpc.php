<?php
/**
 * MCP JsonRpc — Framing NDJSON sobre stdio.
 *
 * MCP stdio transport: una línea JSON-RPC 2.0 por mensaje.
 * stdin → mensajes del cliente.
 * stdout → respuestas al cliente (SÓLO JSON, nunca texto libre).
 * stderr → logs humanos (Claude Desktop los muestra en el panel de debug).
 */

final class JsonRpc
{
    /** Lee un mensaje del cliente desde stdin. Bloquea hasta recibir una línea. */
    public static function read(): ?array
    {
        while (true) {
            $line = fgets(STDIN);
            if ($line === false) {
                return null; // EOF — cliente cerró la conexión
            }
            $line = trim($line);
            if ($line === '') {
                continue; // ignorar líneas vacías
            }

            $msg = json_decode($line, true);
            if ($msg === null) {
                // JSON inválido: enviar error y continuar leyendo
                self::sendError(null, -32700, 'Parse error: JSON inválido');
                continue;
            }

            return $msg;
        }
    }

    /** Envía una respuesta de éxito al cliente. */
    public static function sendResult($id, $result): void
    {
        self::send([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ]);
    }

    /** Envía un error JSON-RPC al cliente. */
    public static function sendError($id, int $code, string $message, $data = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $error['data'] = $data;
        }
        self::send([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => $error,
        ]);
    }

    /** Envía una notificación (sin id, sin respuesta esperada). */
    public static function sendNotification(string $method, $params = null): void
    {
        $msg = ['jsonrpc' => '2.0', 'method' => $method];
        if ($params !== null) {
            $msg['params'] = $params;
        }
        self::send($msg);
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function send(array $msg): void
    {
        $line = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        fwrite(STDOUT, $line . "\n");
        fflush(STDOUT);
    }
}
