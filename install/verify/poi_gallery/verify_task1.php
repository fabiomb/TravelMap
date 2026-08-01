<?php
// Sólo por línea de comandos: install/ es accesible por HTTP en XAMPP
// y este script escribe en la base.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script sólo corre por línea de comandos.\n");
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db.php';

$db = getDB();

$exists = (bool) $db->query("SHOW TABLES LIKE 'poi_images'")->fetchColumn();
echo 'tabla poi_images existe: ' . ($exists ? 'SI' : 'NO') . PHP_EOL;

if ($exists) {
    $conImagen = (int) $db->query(
        "SELECT COUNT(*) FROM points_of_interest WHERE image_path IS NOT NULL AND image_path <> ''"
    )->fetchColumn();
    $filas = (int) $db->query('SELECT COUNT(*) FROM poi_images')->fetchColumn();

    echo "POIs con image_path: {$conImagen}" . PHP_EOL;
    echo "filas en poi_images: {$filas}" . PHP_EOL;
    echo 'backfill correcto: ' . ($conImagen === $filas ? 'SI' : 'NO') . PHP_EOL;

    $duplicados = (int) $db->query(
        'SELECT COUNT(*) FROM (SELECT poi_id FROM poi_images GROUP BY poi_id, sort_order HAVING COUNT(*) > 1) d'
    )->fetchColumn();
    echo 'sin orden duplicado por POI: ' . ($duplicados === 0 ? 'SI' : 'NO') . PHP_EOL;
}
