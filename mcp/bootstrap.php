<?php
/**
 * MCP Server Bootstrap
 *
 * Carga configuración, base de datos, helpers y modelos necesarios.
 * Configura error handler seguro (nunca expone stack traces al cliente).
 */

// Apagar output de errores (stdout es estrictamente JSON-RPC)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ── Configuración de longevidad del proceso ───────────────────────────────────
// El servidor MCP es un proceso de larga duración. Sin estas directivas PHP puede
// matar el proceso por idle aunque fgets(STDIN) esté bloqueado esperando mensajes.
set_time_limit(0);                        // Sin límite de tiempo de ejecución
ini_set('max_execution_time', '0');       // Redundante pero explícito
ini_set('default_socket_timeout', '-1'); // Evita timeout en streams bloqueados

// Definir ROOT_PATH si no está definido (cuando se ejecuta via CLI)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Cargar configuración central (define constantes, timezone, etc.)
// Usamos output buffering para capturar cualquier warning/notice que config.php
// pueda emitir a stdout (p.ej. "ROOT_PATH already defined" si config.php activa
// display_errors antes del define). stdout es estrictamente JSON-RPC NDJSON.
ob_start();
require_once ROOT_PATH . '/config/config.php';
ob_end_clean();
ini_set('display_errors', 0);   // revertir: config.php puede haberlo activado
require_once ROOT_PATH . '/config/db.php';

// Helpers
require_once ROOT_PATH . '/src/helpers/FileHelper.php';
require_once ROOT_PATH . '/src/helpers/BRouterClient.php';
require_once ROOT_PATH . '/src/helpers/ExifExtractor.php';
require_once ROOT_PATH . '/src/helpers/Geocoder.php';

// Modelos
require_once ROOT_PATH . '/src/models/User.php';
require_once ROOT_PATH . '/src/models/Settings.php';
require_once ROOT_PATH . '/src/models/Trip.php';
require_once ROOT_PATH . '/src/models/Route.php';
require_once ROOT_PATH . '/src/models/Point.php';
require_once ROOT_PATH . '/src/models/TripTag.php';
require_once ROOT_PATH . '/src/models/Link.php';

// Asegurar carpeta de logs
$logDir = ROOT_PATH . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}
