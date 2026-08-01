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

$fallos = 0;

function chequear(string $etiqueta, bool $ok, string $detalle = ''): void {
    global $fallos;
    if (!$ok) { $fallos++; }
    echo $etiqueta . ': ' . ($ok ? 'SI' : 'NO' . ($detalle !== '' ? " ({$detalle})" : '')) . PHP_EOL;
}

function crearPoi(PDO $db, int $tripId, string $titulo): int {
    $stmt = $db->prepare(
        "INSERT INTO points_of_interest (trip_id, title, type, latitude, longitude)
         VALUES (?, ?, 'visit', 0, 0)"
    );
    $stmt->execute([$tripId, $titulo]);
    return (int) $db->lastInsertId();
}

// ── Fixture propio ────────────────────────────────────────────────────────
$db->prepare(
    "INSERT INTO trips (title, description, status)
     VALUES ('_verify_trip', 'fixture de verificación', 'draft')"
)->execute();
$tripId = (int) $db->lastInsertId();

$poiId    = crearPoi($db, $tripId, '_verify_poi');
$poiOtro  = crearPoi($db, $tripId, '_verify_poi_otro');
$poiVacio = crearPoi($db, $tripId, '_verify_poi_vacio');

$portada = fn() => $db->query("SELECT image_path FROM points_of_interest WHERE id = {$poiId}")->fetchColumn();

try {
    // ── add: agrega al final y fija la portada ────────────────────────────
    $a = $m->add($poiId, 'uploads/points/_verify_a.jpg');
    $b = $m->add($poiId, 'uploads/points/_verify_b.jpg');
    $c = $m->add($poiId, 'uploads/points/_verify_c.jpg');

    $orden = array_column($m->getByPoiId($poiId), 'image_path');
    chequear('add agrega al final', $orden === [
        'uploads/points/_verify_a.jpg',
        'uploads/points/_verify_b.jpg',
        'uploads/points/_verify_c.jpg',
    ], implode(',', $orden));

    chequear('portada tras add', $portada() === 'uploads/points/_verify_a.jpg', (string) $portada());

    // ── reorder ───────────────────────────────────────────────────────────
    $m->reorder($poiId, [$c, $b, $a]);
    chequear('reorder aplica el orden pedido',
        array_column($m->getByPoiId($poiId), 'id') === [$c, $b, $a]);
    chequear('portada sigue al reorder', $portada() === 'uploads/points/_verify_c.jpg', (string) $portada());

    // Un id de OTRO POI debe descartarse sin alterar el orden propio
    $ajena = $m->add($poiOtro, 'uploads/points/_verify_ajena.jpg');
    $m->reorder($poiId, [$ajena, $c, $b, $a]);
    chequear('reorder descarta ids ajenos',
        array_column($m->getByPoiId($poiId), 'id') === [$c, $b, $a]);
    chequear('la imagen ajena no cambió de POI',
        (int) $m->getById($ajena)['poi_id'] === $poiOtro);

    // ── captions ──────────────────────────────────────────────────────────
    $m->updateCaption($a, '  texto de prueba  ');
    chequear('caption recortado', $m->getById($a)['caption'] === 'texto de prueba');
    $m->updateCaption($a, '   ');
    chequear('caption vacío a NULL', $m->getById($a)['caption'] === null);

    // ── conteos ───────────────────────────────────────────────────────────
    chequear('countByPoiId', $m->countByPoiId($poiId) === 3);

    $conteos = $m->countByPoiIds([$poiId, $poiOtro, $poiVacio]);
    chequear('countByPoiIds cuenta cada POI',
        ($conteos[$poiId] ?? 0) === 3 && ($conteos[$poiOtro] ?? 0) === 1);
    chequear('countByPoiIds omite POIs sin imágenes', !array_key_exists($poiVacio, $conteos));

    // ── getByTripId ───────────────────────────────────────────────────────
    $porViaje = $m->getByTripId($tripId);
    chequear('getByTripId agrupa por poi_id',
        isset($porViaje[$poiId], $porViaje[$poiOtro]) && count($porViaje) === 2);
    chequear('getByTripId respeta el orden de galería',
        array_column($porViaje[$poiId], 'id') === [$c, $b, $a]);
    chequear('getByTripId omite POIs sin imágenes', !isset($porViaje[$poiVacio]));

    // ── toApiArray ────────────────────────────────────────────────────────
    $api = PoiImage::toApiArray($m->getByPoiId($poiId));
    chequear('toApiArray con claves correctas',
        isset($api[0]['id'], $api[0]['url'])
        && array_key_exists('thumbnail_url', $api[0])
        && array_key_exists('caption', $api[0]));

    // ── delete: la portada rota a la siguiente ────────────────────────────
    // image_path se lee DESPUÉS del delete y ANTES de cualquier limpieza:
    // si syncCover() dejara de correr en delete(), estos dos checks fallan.
    $m->delete($c);
    chequear('borrar la portada promueve la siguiente',
        $portada() === 'uploads/points/_verify_b.jpg', (string) $portada());

    $m->delete($b);
    $m->delete($a);
    chequear('galería vacía deja image_path en NULL', $portada() === null, var_export($portada(), true));

} finally {
    // Borrar el viaje fixture alcanza: el CASCADE se lleva sus POIs y, con
    // ellos, sus filas de poi_images.
    $db->prepare('DELETE FROM trips WHERE id = ?')->execute([$tripId]);

    $residuales = (int) $db->query(
        "SELECT COUNT(*) FROM poi_images WHERE image_path LIKE '%_verify_%'"
    )->fetchColumn();
    chequear('sin filas de prueba residuales', $residuales === 0, (string) $residuales);

    echo ($fallos === 0 ? 'TODAS LAS VERIFICACIONES PASARON' : "FALLARON {$fallos} VERIFICACIONES") . PHP_EOL;
}
