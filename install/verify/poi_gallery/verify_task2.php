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

$failures = 0;

function check(string $label, bool $ok, string $detail = ''): void {
    global $failures;
    if (!$ok) { $failures++; }
    echo $label . ': ' . ($ok ? 'SI' : 'NO' . ($detail !== '' ? " ({$detail})" : '')) . PHP_EOL;
}

function createPoi(PDO $db, int $tripId, string $title): int {
    $stmt = $db->prepare(
        "INSERT INTO points_of_interest (trip_id, title, type, latitude, longitude)
         VALUES (?, ?, 'visit', 0, 0)"
    );
    $stmt->execute([$tripId, $title]);
    return (int) $db->lastInsertId();
}

// ── Fixture propio ────────────────────────────────────────────────────────
$db->prepare(
    "INSERT INTO trips (title, description, status)
     VALUES ('_verify_trip', 'fixture de verificación', 'draft')"
)->execute();
$tripId = (int) $db->lastInsertId();

$poiId      = createPoi($db, $tripId, '_verify_poi');
$otherPoiId = createPoi($db, $tripId, '_verify_poi_otro');
$emptyPoiId = createPoi($db, $tripId, '_verify_poi_vacio');

$cover = fn() => $db->query("SELECT image_path FROM points_of_interest WHERE id = {$poiId}")->fetchColumn();

try {
    // ── add: agrega al final y fija la portada ────────────────────────────
    $a = $m->add($poiId, 'uploads/points/_verify_a.jpg');
    $b = $m->add($poiId, 'uploads/points/_verify_b.jpg');
    $c = $m->add($poiId, 'uploads/points/_verify_c.jpg');

    $order = array_column($m->getByPoiId($poiId), 'image_path');
    check('add agrega al final', $order === [
        'uploads/points/_verify_a.jpg',
        'uploads/points/_verify_b.jpg',
        'uploads/points/_verify_c.jpg',
    ], implode(',', $order));

    check('portada tras add', $cover() === 'uploads/points/_verify_a.jpg', (string) $cover());

    // ── reorder ───────────────────────────────────────────────────────────
    $m->reorder($poiId, [$c, $b, $a]);
    check('reorder aplica el orden pedido',
        array_column($m->getByPoiId($poiId), 'id') === [$c, $b, $a]);
    check('portada sigue al reorder', $cover() === 'uploads/points/_verify_c.jpg', (string) $cover());

    // Un id de OTRO POI debe descartarse sin alterar el orden propio
    $foreignImageId = $m->add($otherPoiId, 'uploads/points/_verify_ajena.jpg');
    $m->reorder($poiId, [$foreignImageId, $c, $b, $a]);
    check('reorder descarta ids ajenos',
        array_column($m->getByPoiId($poiId), 'id') === [$c, $b, $a]);
    check('la imagen ajena no cambió de POI',
        (int) $m->getById($foreignImageId)['poi_id'] === $otherPoiId);

    // ── captions ──────────────────────────────────────────────────────────
    $m->updateCaption($a, '  texto de prueba  ');
    check('caption recortado', $m->getById($a)['caption'] === 'texto de prueba');
    $m->updateCaption($a, '   ');
    check('caption vacío a NULL', $m->getById($a)['caption'] === null);

    // ── conteos ───────────────────────────────────────────────────────────
    check('countByPoiId', $m->countByPoiId($poiId) === 3);

    $counts = $m->countByPoiIds([$poiId, $otherPoiId, $emptyPoiId]);
    check('countByPoiIds cuenta cada POI',
        ($counts[$poiId] ?? 0) === 3 && ($counts[$otherPoiId] ?? 0) === 1);
    check('countByPoiIds omite POIs sin imágenes', !array_key_exists($emptyPoiId, $counts));

    // ── getByTripId ───────────────────────────────────────────────────────
    $byTrip = $m->getByTripId($tripId);
    check('getByTripId agrupa por poi_id',
        isset($byTrip[$poiId], $byTrip[$otherPoiId]) && count($byTrip) === 2);
    check('getByTripId respeta el orden de galería',
        array_column($byTrip[$poiId], 'id') === [$c, $b, $a]);
    check('getByTripId omite POIs sin imágenes', !isset($byTrip[$emptyPoiId]));

    // ── toApiArray ────────────────────────────────────────────────────────
    $api = PoiImage::toApiArray($m->getByPoiId($poiId));
    check('toApiArray con claves correctas',
        isset($api[0]['id'], $api[0]['url'])
        && array_key_exists('thumbnail_url', $api[0])
        && array_key_exists('caption', $api[0]));

    // ── delete: la portada rota a la siguiente ────────────────────────────
    // image_path se lee DESPUÉS del delete y ANTES de cualquier limpieza:
    // si syncCover() dejara de correr en delete(), estos dos checks fallan.
    $m->delete($c);
    check('borrar la portada promueve la siguiente',
        $cover() === 'uploads/points/_verify_b.jpg', (string) $cover());

    $m->delete($b);
    $m->delete($a);
    check('galería vacía deja image_path en NULL', $cover() === null, var_export($cover(), true));

} finally {
    // Borrar el viaje fixture alcanza: el CASCADE se lleva sus POIs y, con
    // ellos, sus filas de poi_images.
    $db->prepare('DELETE FROM trips WHERE id = ?')->execute([$tripId]);

    $leftover = (int) $db->query(
        "SELECT COUNT(*) FROM poi_images WHERE image_path LIKE '%_verify_%'"
    )->fetchColumn();
    check('sin filas de prueba residuales', $leftover === 0, (string) $leftover);

    echo ($failures === 0 ? 'TODAS LAS VERIFICACIONES PASARON' : "FALLARON {$failures} VERIFICACIONES") . PHP_EOL;
}
