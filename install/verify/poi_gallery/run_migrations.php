<?php
// Sólo por línea de comandos: install/ es accesible por HTTP en XAMPP
// y este script modifica el esquema.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script sólo corre por línea de comandos.\n");
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../MigrationRunner.php';

$runner = new MigrationRunner(getDB());
foreach ($runner->runPending() as $resultado) {
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
