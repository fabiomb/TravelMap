<?php
// Sólo por línea de comandos: install/ es accesible por HTTP en XAMPP
// y este script escribe en la base.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script sólo corre por línea de comandos.\n");
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../src/models/Point.php';
require_once __DIR__ . '/../../../src/models/PoiImage.php';

$db = getDB();
$point = new Point();
$galeria = new PoiImage();

// Archivos propios, con prefijo _verify_, para comprobar el borrado en disco.
// Nunca pueden colisionar con una imagen real: los crea este mismo script.
$dir = ROOT_PATH . '/uploads/points';
$coverFile = $dir . '/_verify_cover.jpg';
$extraFile = $dir . '/_verify_extra.jpg';

// Fixture propio: viaje y POI creados por este script. Se guardan aquí para
// que el finally pueda limpiar incluso si algo falla a mitad de camino.
$tripId = null;

try {
    // Viaje fixture propio: el script no toca datos reales en ningún momento.
    $db->prepare(
        "INSERT INTO trips (title, description, status)
         VALUES ('_verify_trip_point', 'fixture de verificación', 'draft')"
    )->execute();
    $tripId = (int) $db->lastInsertId();

    @mkdir($dir, 0777, true);
    file_put_contents($coverFile, 'x');
    file_put_contents($extraFile, 'x');

    // 1. Alta con image_path, como la hacen MCP / EXIF / save_poi
    $id = $point->create([
        'trip_id' => $tripId, 'title' => '_verify_poi', 'description' => '',
        'type' => 'visit', 'icon' => 'default',
        'latitude' => '0.0', 'longitude' => '0.0', 'visit_date' => null,
        'image_path' => 'uploads/points/_verify_cover.jpg',
    ]);
    echo 'create genera fila espejo: ' . ($galeria->countByPoiId($id) === 1 ? 'SI' : 'NO') . PHP_EOL;

    // 2. Un update posterior no duplica la fila
    $point->update($id, [
        'trip_id' => $tripId, 'title' => '_verify_poi', 'description' => 'editado',
        'type' => 'visit', 'icon' => 'default',
        'latitude' => '0.0', 'longitude' => '0.0', 'visit_date' => null,
        'image_path' => 'uploads/points/_verify_cover.jpg',
    ]);
    echo 'update no duplica: ' . ($galeria->countByPoiId($id) === 1 ? 'SI' : 'NO') . PHP_EOL;

    // 3. Con galería existente, update no la pisa
    $galeria->add($id, 'uploads/points/_verify_extra.jpg');
    $point->update($id, [
        'trip_id' => $tripId, 'title' => '_verify_poi', 'description' => 'editado',
        'type' => 'visit', 'icon' => 'default',
        'latitude' => '0.0', 'longitude' => '0.0', 'visit_date' => null,
        'image_path' => 'uploads/points/_verify_cover.jpg',
    ]);
    echo 'update respeta la galería: ' . ($galeria->countByPoiId($id) === 2 ? 'SI' : 'NO') . PHP_EOL;

    // 4. Borrar el POI borra filas y archivos
    $point->delete($id);
    $quedan = (int) $db->query("SELECT COUNT(*) FROM poi_images WHERE poi_id = {$id}")->fetchColumn();
    echo 'cascade borra filas: ' . ($quedan === 0 ? 'SI' : 'NO') . PHP_EOL;
    echo 'borra archivo portada: ' . (!file_exists($coverFile) ? 'SI' : 'NO') . PHP_EOL;
    echo 'borra archivo extra: ' . (!file_exists($extraFile) ? 'SI' : 'NO') . PHP_EOL;
} finally {
    // Limpieza del fixture, incluso si una aserción anterior lanzó una excepción.
    // El CASCADE se lleva POIs y filas de galería que hayan quedado del viaje.
    if ($tripId !== null) {
        $db->prepare('DELETE FROM trips WHERE id = ?')->execute([$tripId]);
    }
    @unlink($coverFile);
    @unlink($extraFile);
}

$residuales = (int) $db->query(
    "SELECT COUNT(*) FROM points_of_interest WHERE title LIKE '\_verify\_%'"
)->fetchColumn();
echo 'sin POIs de prueba residuales: ' . ($residuales === 0 ? 'SI' : "NO ({$residuales})") . PHP_EOL;
