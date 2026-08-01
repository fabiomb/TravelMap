<?php
// Sólo por línea de comandos: install/ es accesible por HTTP en XAMPP
// y este script escribe en la base.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script sólo corre por línea de comandos.\n");
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../src/models/PoiImage.php';

$db = getDB();
$m  = new PoiImage();

$poiId = (int) $db->query('SELECT id FROM points_of_interest ORDER BY id LIMIT 1')->fetchColumn();
if (!$poiId) { exit("No hay POIs en la base; cargá uno antes de verificar.\n"); }

$original = $db->query("SELECT image_path FROM points_of_interest WHERE id = {$poiId}")->fetchColumn();
$previas  = $m->getByPoiId($poiId);

$a = $m->add($poiId, 'uploads/points/_verify_a.jpg');
$b = $m->add($poiId, 'uploads/points/_verify_b.jpg');
$c = $m->add($poiId, 'uploads/points/_verify_c.jpg');
$base = count($previas);

$orden = array_column($m->getByPoiId($poiId), 'image_path');
echo 'agrega al final: ' . ($orden[$base] === 'uploads/points/_verify_a.jpg' ? 'SI' : 'NO') . PHP_EOL;

$cover = $db->query("SELECT image_path FROM points_of_interest WHERE id = {$poiId}")->fetchColumn();
$esperado = $base === 0 ? 'uploads/points/_verify_a.jpg' : $original;
echo 'portada tras add: ' . ($cover === $esperado ? 'SI' : "NO ({$cover})") . PHP_EOL;

// Reordenar poniendo C primero
$ids = array_column($m->getByPoiId($poiId), 'id');
$reordenado = array_merge([$c], array_values(array_diff($ids, [$c])));
$m->reorder($poiId, $reordenado);
$primera = $m->getByPoiId($poiId)[0];
echo 'reorder mueve al frente: ' . ($primera['image_path'] === 'uploads/points/_verify_c.jpg' ? 'SI' : 'NO') . PHP_EOL;

$cover = $db->query("SELECT image_path FROM points_of_interest WHERE id = {$poiId}")->fetchColumn();
echo 'portada sigue al reorder: ' . ($cover === 'uploads/points/_verify_c.jpg' ? 'SI' : "NO ({$cover})") . PHP_EOL;

// reorder ignora ids ajenos
echo 'reorder ignora ids ajenos: ' . ($m->reorder($poiId, [999999]) ? 'SI' : 'NO') . PHP_EOL;
echo 'cantidad intacta: ' . ($m->countByPoiId($poiId) === $base + 3 ? 'SI' : 'NO') . PHP_EOL;

$m->updateCaption($a, '  texto de prueba  ');
echo 'caption recortado: ' . ($m->getById($a)['caption'] === 'texto de prueba' ? 'SI' : 'NO') . PHP_EOL;
$m->updateCaption($a, '   ');
echo 'caption vacío a NULL: ' . ($m->getById($a)['caption'] === null ? 'SI' : 'NO') . PHP_EOL;

$api = PoiImage::toApiArray($m->getByPoiId($poiId));
echo 'toApiArray con claves correctas: '
   . (isset($api[0]['id'], $api[0]['url']) && array_key_exists('thumbnail_url', $api[0]) && array_key_exists('caption', $api[0]) ? 'SI' : 'NO') . PHP_EOL;

// Limpieza
foreach ([$a, $b, $c] as $id) { $m->delete($id); }
$db->prepare('UPDATE points_of_interest SET image_path = ? WHERE id = ?')->execute([$original ?: null, $poiId]);
echo 'estado restaurado: ' . ($m->countByPoiId($poiId) === $base ? 'SI' : 'NO') . PHP_EOL;
