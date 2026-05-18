<?php
/**
 * MCP Logger
 *
 * Escribe a logs/mcp.log con sanitización:
 * nunca loguea campos binarios o geometrías crudas.
 */

final class McpLogger
{
    private static string $logFile = '';

    public static function init(): void
    {
        self::$logFile = ROOT_PATH . '/logs/mcp.log';
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /**
     * Loguea una llamada a tool con parámetros sanitizados.
     * Los campos con datos binarios/grandes se reemplazan por su longitud.
     */
    public static function toolCall(string $tool, array $params, string $requestId = ''): void
    {
        $safe = self::sanitizeParams($params);
        self::write('CALL', "tool={$tool} request_id={$requestId}", $safe);
    }

    public static function toolResult(string $tool, array $result, float $durationMs, string $requestId = ''): void
    {
        $safe = self::sanitizeParams($result);
        self::write('RESULT', "tool={$tool} duration_ms=" . round($durationMs) . " request_id={$requestId}", $safe);
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function sanitizeParams(array $params, int $depth = 0): array
    {
        if ($depth > 3) return ['...'];

        $largeBinaryKeys = ['photo_base64', 'brouter_csv_base64', 'brouter_csv_text', 'geojson_data'];
        $out = [];

        foreach ($params as $k => $v) {
            if (in_array($k, $largeBinaryKeys, true)) {
                $out[$k] = '[' . strlen((string)$v) . ' bytes]';
            } elseif (is_array($v)) {
                $out[$k] = self::sanitizeParams($v, $depth + 1);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        if (self::$logFile === '') {
            self::$logFile = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__)) . '/logs/mcp.log';
        }

        $ts      = date('Y-m-d H:i:s');
        $ctx     = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $line    = "[{$ts}] [{$level}] {$message}{$ctx}" . PHP_EOL;

        @file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
        @chmod(self::$logFile, 0640);
    }
}
