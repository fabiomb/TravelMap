<?php
// Sólo por línea de comandos: install/ es accesible por HTTP en XAMPP
// y este script escribe en la base.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script sólo corre por línea de comandos.\n");
}

// Uso: php verify_task6.php <ruta al backup .json>
$path = $argv[1] ?? '';
if (!is_file($path)) { exit("Pasá la ruta del backup JSON.\n"); }

$backup = json_decode(file_get_contents($path), true);

$hasImages = isset($backup['data']['poi_images']);
echo 'incluye poi_images: ' . ($hasImages ? 'SI' : 'NO') . PHP_EOL;
if (!$hasImages) { exit(1); }

$rows = $backup['data']['poi_images'];
echo 'cantidad de filas: ' . count($rows) . PHP_EOL;

$keys = ['id', 'poi_id', 'image_path', 'caption', 'sort_order'];
$ok = true;
foreach ($rows as $row) {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $row)) { $ok = false; }
    }
}
echo 'todas las columnas presentes: ' . ($ok ? 'SI' : 'NO') . PHP_EOL;
echo 'declarado en includes: ' . (in_array('poi_images', $backup['includes'], true) ? 'SI' : 'NO') . PHP_EOL;
