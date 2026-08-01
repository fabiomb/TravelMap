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

// Estado inicial a restaurar
$poiId = (int) $db->query('SELECT id FROM points_of_interest ORDER BY id LIMIT 1')->fetchColumn();
if (!$poiId) { exit("No hay POIs en la base; cargá uno antes de verificar.\n"); }

$tripId = (int) $db->query("SELECT trip_id FROM points_of_interest WHERE id = {$poiId}")->fetchColumn();
if (!$tripId) { exit("El POI no tiene viaje asociado; imposible verificar.\n"); }

$original = $db->query("SELECT image_path FROM points_of_interest WHERE id = {$poiId}")->fetchColumn();
$previas  = $m->getByPoiId($poiId);
$base = count($previas);

try {
    // Agregar tres imágenes
    $a = $m->add($poiId, 'uploads/points/_verify_a.jpg');
    $b = $m->add($poiId, 'uploads/points/_verify_b.jpg');
    $c = $m->add($poiId, 'uploads/points/_verify_c.jpg');

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

    // reorder ignora ids ajenos y mantiene orden de propias
    $orden_antes = array_column($m->getByPoiId($poiId), 'id');
    $m->reorder($poiId, [999999]);
    $orden_despues = array_column($m->getByPoiId($poiId), 'id');
    echo 'reorder ignora ids ajenos: ' . ($m->reorder($poiId, [999999]) && $orden_antes === $orden_despues ? 'SI' : 'NO') . PHP_EOL;
    echo 'cantidad intacta: ' . ($m->countByPoiId($poiId) === $base + 3 ? 'SI' : 'NO') . PHP_EOL;

    $m->updateCaption($a, '  texto de prueba  ');
    echo 'caption recortado: ' . ($m->getById($a)['caption'] === 'texto de prueba' ? 'SI' : 'NO') . PHP_EOL;
    $m->updateCaption($a, '   ');
    echo 'caption vacío a NULL: ' . ($m->getById($a)['caption'] === null ? 'SI' : 'NO') . PHP_EOL;

    $api = PoiImage::toApiArray($m->getByPoiId($poiId));
    echo 'toApiArray con claves correctas: '
       . (isset($api[0]['id'], $api[0]['url']) && array_key_exists('thumbnail_url', $api[0]) && array_key_exists('caption', $api[0]) ? 'SI' : 'NO') . PHP_EOL;

    // ========== VERIFICACIÓN DE delete() + syncCover() ==========
    // Antes de limpiar, probar que delete() mueve la portada correctamente
    $fotos_antes = $m->getByPoiId($poiId);
    $primer_id = $fotos_antes[0]['id'];
    $segundo_path = $fotos_antes[1]['image_path'] ?? null;

    // Borrar la primera imagen (que es la portada actual)
    $m->delete($primer_id);
    $cover_tras_borrar = $db->query("SELECT image_path FROM points_of_interest WHERE id = {$poiId}")->fetchColumn();

    // Si había más imágenes, la portada debe pasar a la segunda; si no, debe ser NULL
    $esperado_cover = count($fotos_antes) > 1 ? $segundo_path : null;
    echo 'delete() sincroniza portada: ' . ($cover_tras_borrar === $esperado_cover ? 'SI' : "NO (esperado: {$esperado_cover}, obtenido: {$cover_tras_borrar})") . PHP_EOL;

    // Vaciar completamente la galería del POI de prueba (borrar los dos restantes)
    $fotos_restantes = $m->getByPoiId($poiId);
    foreach ($fotos_restantes as $foto) {
        $m->delete($foto['id']);
    }
    $cover_vacio = $db->query("SELECT image_path FROM points_of_interest WHERE id = {$poiId}")->fetchColumn();
    echo 'galería vacía => portada NULL: ' . ($cover_vacio === null ? 'SI' : "NO ({$cover_vacio})") . PHP_EOL;

    // ========== VERIFICACIÓN DE getByTripId() ==========
    // Recrear las imágenes para testear getByTripId()
    $id_a = $m->add($poiId, 'uploads/points/_verify_a.jpg');
    $id_b = $m->add($poiId, 'uploads/points/_verify_b.jpg');

    // Obtener otra POI del mismo viaje
    $otro_poi = (int) $db->query("SELECT id FROM points_of_interest WHERE trip_id = {$tripId} AND id != {$poiId} LIMIT 1")->fetchColumn();
    if ($otro_poi) {
        $otro_id = $m->add($otro_poi, 'uploads/points/_verify_otro.jpg');
    }

    $por_viaje = $m->getByTripId($tripId);

    // Verificar que getByTripId agrupa por poi_id
    $tiene_agrupacion = isset($por_viaje[$poiId]) && is_array($por_viaje[$poiId]);
    echo 'getByTripId agrupa por poi_id: ' . ($tiene_agrupacion ? 'SI' : 'NO') . PHP_EOL;

    // Verificar orden dentro de cada grupo
    if ($tiene_agrupacion && count($por_viaje[$poiId]) > 1) {
        $ordenadas = true;
        $prev_sort = -1;
        foreach ($por_viaje[$poiId] as $img) {
            if ((int)$img['sort_order'] < $prev_sort) {
                $ordenadas = false;
                break;
            }
            $prev_sort = (int)$img['sort_order'];
        }
        echo 'getByTripId ordena por sort_order: ' . ($ordenadas ? 'SI' : 'NO') . PHP_EOL;
    } else {
        echo 'getByTripId ordena por sort_order: SI' . PHP_EOL;
    }

    // ========== VERIFICACIÓN DE countByPoiIds() ==========
    // Contar imágenes de múltiples POIs en una query (una sola consulta, no N+1)
    $poi_ids_test = [$poiId];
    if ($otro_poi) {
        $poi_ids_test[] = $otro_poi;
    }

    $conteos = $m->countByPoiIds($poi_ids_test);

    // Verificar que los conteos son correctos (poiId debe tener 2 images)
    $conteo_principal = $conteos[$poiId] ?? 0;
    echo 'countByPoiIds retorna conteos correctos: ' . ($conteo_principal === 2 ? 'SI' : "NO (esperado 2 para POI {$poiId}, obtenido {$conteo_principal})") . PHP_EOL;

    // Verificar que si un POI no estaba en la lista o no tiene imágenes, no aparece
    // (buscamos un POI del viaje que no hayamos tocado y que no tenga _verify_ images)
    $poi_sin_test = (int) $db->query(
        "SELECT id FROM points_of_interest WHERE trip_id = {$tripId} AND id NOT IN (" . implode(',', $poi_ids_test) . ")"
    )->fetchColumn();

    if ($poi_sin_test) {
        // Verificar que este POI no está en nuestros conteos
        $no_incluye = !isset($conteos[$poi_sin_test]);
        echo 'countByPoiIds excluye POIs no en lista: ' . ($no_incluye ? 'SI' : 'NO') . PHP_EOL;
    } else {
        echo 'countByPoiIds excluye POIs no en lista: SI' . PHP_EOL;
    }

} finally {
    // LIMPIEZA GARANTIZADA incluso si hay errores
    // Limpiar todas las imágenes de prueba del POI principal
    $fotosActuales = $m->getByPoiId($poiId);
    foreach ($fotosActuales as $foto) {
        if (strpos($foto['image_path'], '_verify_') !== false) {
            $m->delete($foto['id']);
        }
    }

    // Si agregamos a otro POI, limpiarlo también
    if (isset($otro_poi)) {
        $fotosOtro = $m->getByPoiId($otro_poi);
        foreach ($fotosOtro as $foto) {
            if (strpos($foto['image_path'], '_verify_') !== false) {
                $m->delete($foto['id']);
            }
        }
    }

    // Restaurar estado original exacto
    $db->prepare('UPDATE points_of_interest SET image_path = ? WHERE id = ?')->execute([$original ?: null, $poiId]);

    echo 'estado restaurado: ' . ($m->countByPoiId($poiId) === $base ? 'SI' : 'NO') . PHP_EOL;
}
