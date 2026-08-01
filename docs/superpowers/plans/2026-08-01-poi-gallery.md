# Galería de imágenes por POI — Plan de implementación

> **Para ejecutores agénticos:** SUB-SKILL REQUERIDA: usar `superpowers:subagent-driven-development` (recomendado) o `superpowers:executing-plans` para implementar tarea por tarea. Los pasos usan checkbox (`- [ ]`).

**Objetivo:** Permitir varias imágenes ordenables por punto de interés, administrables desde `/admin/point_form.php` y navegables en el mapa general y en el mapa de viaje.

**Arquitectura:** Tabla nueva `poi_images` con `sort_order`, más `points_of_interest.image_path` conservada como espejo de la portada para no romper a los consumidores existentes. La sincronización de esa columna vive en un único método, `PoiImage::syncCover()`. El front comparte el carrusel del popup vía `assets/js/map-renderer.js`, que ya usan las tres vistas.

**Stack:** PHP 8 + PDO (sin framework), MySQL/MariaDB, Bootstrap 5, jQuery 3.7.1, MapLibre GL y Leaflet, i18n propio por JSON.

**Spec:** [`docs/superpowers/specs/2026-08-01-poi-gallery-design.md`](../specs/2026-08-01-poi-gallery-design.md)

## Restricciones globales

- **Sin dependencias nuevas.** Todas las librerías del proyecto son locales en `assets/vendor/`. El drag & drop usa la HTML5 Drag and Drop API nativa.
- **Sin suite de tests.** No hay `composer.json` ni PHPUnit. La verificación es por script PHP de CLI y comprobación en navegador. Cada tarea trae sus comandos exactos.
- **Los scripts de verificación se commitean** en `install/verify/poi_gallery/`, para que la evidencia quede en el repositorio y sean reejecutables. Como `install/` es accesible por HTTP en XAMPP y estos scripts escriben en la base, **cada uno arranca con un guard de CLI**:

```php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script sólo corre por línea de comandos.\n");
}
```
- **Textos siempre por i18n.** Ningún string visible hardcodeado; claves nuevas en `lang/en.json` **y** `lang/es.json`, en la misma tarea que las usa.
- **Comentarios de código en español**, siguiendo el código existente. Identificadores, nombres de tabla y de columna en inglés, también siguiendo lo existente.
- **Commits convencionales**, sin atribución de IA.
- **Rama:** `feat/poi-gallery`.
- **Ruta del proyecto:** `C:\xampp\htdocs\TravelMap`. XAMPP, PHP en `C:\xampp\php\php.exe`.

## Mapa de archivos

**Se crean:**

| Archivo | Responsabilidad |
|---|---|
| `install/migrations/026_poi_images_table.php` | Tabla `poi_images` y backfill desde `image_path`. |
| `src/models/PoiImage.php` | CRUD, orden y sincronización de la portada. Único escritor de la columna espejo. |
| `api/poi_images.php` | Endpoint de administración con despacho por `action`. |
| `assets/js/poi_gallery_admin.js` | Subida, borrado, reordenamiento y captions en el formulario de admin. |
| `assets/js/poi_lightbox.js` | Lightbox con galería para `index.php`, compartido por los dos renderers. Dueño único del cierre por backdrop. |
| `assets/css/poi_gallery.css` | Estilos de carrusel y lightbox compartidos por `index.php` (ambos renderers) y `trip.php`. |
| `install/verify/poi_gallery/verify_task*.php` | Scripts de verificación por CLI, commiteados como evidencia reejecutable. |

**Se modifican:**

| Archivo | Cambio |
|---|---|
| `database.sql` | Definición de `poi_images` para instalaciones nuevas. |
| `src/models/Point.php` | Fila espejo en `create`/`update`; borrado de archivos en `delete`. |
| `admin/point_form.php` | Reemplaza el bloque de imagen única por la grilla de galería. |
| `admin/points.php` | Badge con cantidad de imágenes. |
| `admin/backup.php` | Export, restore y estadísticas de `poi_images`. |
| `api/get_all_data.php`, `api/get_trip.php`, `trip.php` | Arreglo `images` por punto. |
| `assets/js/map-renderer.js` | Carrusel en el popup y delegación de eventos. |
| `index.php` | Botones y contador en el markup del lightbox. |
| `assets/js/public_map.js`, `assets/js/public_map_leaflet.js` | Se les quita `initLightbox`: el cierre pasa a `poi_lightbox.js`. |
| `assets/js/trip_single.js` | Galería aplanada desde `TRIP_DATA`. |
| `assets/css/admin.css` | Grilla de la galería en el admin. |
| `assets/css/trip.css` | Badge de cantidad en el carrusel lateral. |
| `index.php`, `trip.php` | Cargan `poi_gallery.css`. |
| `lang/en.json`, `lang/es.json` | Claves nuevas. |
| `version.php`, `CHANGELOG.md`, `ESTRUCTURA.md` | Cierre de la feature. |

---

### Task 1: Migración y schema

**Archivos:**
- Crear: `install/migrations/026_poi_images_table.php`
- Modificar: `database.sql` (agregar tras el bloque de `links`, alrededor de la línea 211)
- Verificar: `install/verify/poi_gallery/verify_task1.php`

**Interfaces:**
- Consume: nada.
- Produce: tabla `poi_images` con columnas `id`, `poi_id`, `image_path`, `caption`, `sort_order`, `created_at`. Las tareas siguientes dependen de estos nombres exactos.

El runner descubre migraciones con `glob(install/migrations/*.php)` y espera una clase con los métodos estáticos `id()`, `description()`, `check()` y `up()`. `check()` devuelve `true` cuando la migración **ya está aplicada**.

- [ ] **Paso 1: Crear la migración**

```php
<?php
/**
 * Migration 026: Tabla poi_images
 *
 * Agrega galería de imágenes por punto de interés. La columna
 * points_of_interest.image_path se conserva como espejo de la portada
 * (la imagen de menor sort_order) para no romper a los consumidores
 * existentes: MCP, importador EXIF, save_poi y backups.
 */
class Migration_026_poi_images_table
{
    public static function id(): string
    {
        return '026_poi_images_table';
    }

    public static function description(): string
    {
        return 'Tabla poi_images: galería de imágenes por punto de interés';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->query("SHOW TABLES LIKE 'poi_images'");
        return (bool) $stmt->fetchColumn();
    }

    public static function up(PDO $db): void
    {
        // 1. Crear la tabla
        $db->exec("
            CREATE TABLE IF NOT EXISTS poi_images (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                poi_id     INT UNSIGNED NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                caption    VARCHAR(255) DEFAULT NULL,
                sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (poi_id) REFERENCES points_of_interest(id) ON DELETE CASCADE,
                INDEX idx_poi_sort (poi_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. Backfill: cada POI con imagen actual pasa a ser su portada
        $db->exec("
            INSERT INTO poi_images (poi_id, image_path, sort_order)
            SELECT id, image_path, 0
            FROM points_of_interest
            WHERE image_path IS NOT NULL AND image_path <> ''
        ");
    }
}
```

- [ ] **Paso 2: Agregar la tabla a `database.sql`**

Insertar después del bloque `CREATE TABLE ... links` (termina en la línea 211) y antes del comentario de `password_shares`:

```sql
-- ============================================
-- Tabla: poi_images
-- Descripción: Galería de imágenes por punto de interés.
--              points_of_interest.image_path se mantiene como espejo
--              de la portada (imagen de menor sort_order).
-- ============================================
CREATE TABLE IF NOT EXISTS poi_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    poi_id     INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    caption    VARCHAR(255) DEFAULT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poi_id) REFERENCES points_of_interest(id) ON DELETE CASCADE,
    INDEX idx_poi_sort (poi_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Paso 3: Escribir el script de verificación**

Crear `install/verify/poi_gallery/verify_task1.php`:

```php
<?php
// Sólo por línea de comandos: install/ es accesible por HTTP en XAMPP
// y este script escribe en la base.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script sólo corre por línea de comandos.
");
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db.php';

$db = getDB();

$exists = (bool) $db->query("SHOW TABLES LIKE 'poi_images'")->fetchColumn();
echo 'tabla poi_images existe: ' . ($exists ? 'SI' : 'NO') . PHP_EOL;

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
```

- [ ] **Paso 4: Ejecutar la verificación antes de migrar**

```bash
C:/xampp/php/php.exe "install/verify/poi_gallery/verify_task1.php"
```

Esperado: `tabla poi_images existe: NO`. Confirma que la migración todavía no corrió.

- [ ] **Paso 5: Aplicar la migración**

Crear `install/verify/poi_gallery/run_migrations.php`, con el mismo guard de CLI, para no depender del instalador web:

```php
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
```

Ejecutar:

```bash
C:/xampp/php/php.exe install/verify/poi_gallery/run_migrations.php
```

Esperado: una línea con `026_poi_images_table` aplicada correctamente. Si `runPending()` devuelve un formato distinto al esperado, leé `install/MigrationRunner.php:70` y ajustá el `echo` — el objetivo del paso es aplicar la migración, no el formato de salida.

- [ ] **Paso 6: Ejecutar la verificación después de migrar**

```bash
C:/xampp/php/php.exe "install/verify/poi_gallery/verify_task1.php"
```

Esperado: las cuatro líneas en `SI` / iguales.

- [ ] **Paso 7: Commit**

```bash
git add install/migrations/026_poi_images_table.php database.sql install/verify/poi_gallery/
git commit -m "feat(db): tabla poi_images con backfill desde image_path"
```

---

### Task 2: Modelo `PoiImage`

**Archivos:**
- Crear: `src/models/PoiImage.php`
- Verificar: `install/verify/poi_gallery/verify_task2.php`

**Interfaces:**
- Consume: tabla `poi_images` (Task 1); `FileHelper::deleteFile()` y `FileHelper::getThumbnailPath()` de `src/helpers/FileHelper.php`.
- Produce, y las tareas 3 a 8 dependen de estas firmas exactas:

```php
$m = new PoiImage();
$m->getByPoiId(int $poiId): array          // filas ordenadas por sort_order, id
$m->getByTripId(int $tripId): array        // [poi_id => array de filas]
$m->getById(int $imageId): ?array
$m->add(int $poiId, string $imagePath): int|false   // devuelve el id nuevo
$m->delete(int $imageId): bool
$m->updateCaption(int $imageId, ?string $caption): bool
$m->reorder(int $poiId, array $imageIds): bool
$m->syncCover(int $poiId): void
$m->countByPoiId(int $poiId): int
$m->countByPoiIds(array $poiIds): array    // [poi_id => cantidad], una sola consulta
PoiImage::toApiArray(array $rows): array   // [{id, url, thumbnail_url, caption}]
```

El estilo sigue a `src/models/Link.php`: constructor con `getDB()`, `try/catch (PDOException)` con `error_log`, y un `toApiArray()` estático para el formato de la API.

- [ ] **Paso 1: Crear el modelo**

```php
<?php
/**
 * Modelo: PoiImage
 *
 * Galería de imágenes de un punto de interés. Es el ÚNICO lugar del sistema
 * que escribe points_of_interest.image_path, que funciona como espejo de la
 * portada (la imagen de menor sort_order).
 */

require_once __DIR__ . '/../helpers/FileHelper.php';

class PoiImage {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Imágenes de un POI, en orden de galería.
     */
    public function getByPoiId(int $poiId): array {
        try {
            $stmt = $this->db->prepare('
                SELECT id, poi_id, image_path, caption, sort_order
                FROM poi_images
                WHERE poi_id = ?
                ORDER BY sort_order ASC, id ASC
            ');
            $stmt->execute([$poiId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error al obtener imágenes del POI: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Todas las imágenes de un viaje agrupadas por poi_id.
     * Una sola consulta: evita N+1 en las APIs públicas.
     */
    public function getByTripId(int $tripId): array {
        try {
            $stmt = $this->db->prepare('
                SELECT i.id, i.poi_id, i.image_path, i.caption, i.sort_order
                FROM poi_images i
                INNER JOIN points_of_interest p ON p.id = i.poi_id
                WHERE p.trip_id = ?
                ORDER BY i.poi_id ASC, i.sort_order ASC, i.id ASC
            ');
            $stmt->execute([$tripId]);

            $agrupadas = [];
            foreach ($stmt->fetchAll() as $fila) {
                $agrupadas[(int) $fila['poi_id']][] = $fila;
            }
            return $agrupadas;
        } catch (PDOException $e) {
            error_log('Error al obtener imágenes del viaje: ' . $e->getMessage());
            return [];
        }
    }

    public function getById(int $imageId): ?array {
        try {
            $stmt = $this->db->prepare('SELECT * FROM poi_images WHERE id = ?');
            $stmt->execute([$imageId]);
            $fila = $stmt->fetch();
            return $fila ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener imagen: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Agrega una imagen al final de la galería.
     */
    public function add(int $poiId, string $imagePath) {
        try {
            $stmt = $this->db->prepare(
                'SELECT COALESCE(MAX(sort_order) + 1, 0) FROM poi_images WHERE poi_id = ?'
            );
            $stmt->execute([$poiId]);
            $orden = (int) $stmt->fetchColumn();

            $stmt = $this->db->prepare(
                'INSERT INTO poi_images (poi_id, image_path, sort_order) VALUES (?, ?, ?)'
            );
            if (!$stmt->execute([$poiId, $imagePath, $orden])) {
                return false;
            }

            $nuevoId = (int) $this->db->lastInsertId();
            $this->syncCover($poiId);
            return $nuevoId;
        } catch (PDOException $e) {
            error_log('Error al agregar imagen: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Borra la fila y el archivo en disco (FileHelper también borra el thumbnail).
     */
    public function delete(int $imageId): bool {
        try {
            $imagen = $this->getById($imageId);
            if (!$imagen) {
                return false;
            }

            $stmt = $this->db->prepare('DELETE FROM poi_images WHERE id = ?');
            $resultado = $stmt->execute([$imageId]);

            if ($resultado) {
                FileHelper::deleteFile($imagen['image_path']);
                $this->syncCover((int) $imagen['poi_id']);
            }
            return $resultado;
        } catch (PDOException $e) {
            error_log('Error al eliminar imagen: ' . $e->getMessage());
            return false;
        }
    }

    public function updateCaption(int $imageId, ?string $caption): bool {
        try {
            $caption = ($caption === null || trim($caption) === '') ? null : mb_substr(trim($caption), 0, 255);
            $stmt = $this->db->prepare('UPDATE poi_images SET caption = ? WHERE id = ?');
            return $stmt->execute([$caption, $imageId]);
        } catch (PDOException $e) {
            error_log('Error al actualizar caption: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reasigna sort_order según el orden recibido.
     * Ignora los ids que no pertenezcan al POI indicado.
     */
    public function reorder(int $poiId, array $imageIds): bool {
        try {
            $propias = [];
            foreach ($this->getByPoiId($poiId) as $fila) {
                $propias[(int) $fila['id']] = true;
            }

            $this->db->beginTransaction();
            $stmt = $this->db->prepare('UPDATE poi_images SET sort_order = ? WHERE id = ? AND poi_id = ?');

            $orden = 0;
            foreach ($imageIds as $id) {
                $id = (int) $id;
                if (!isset($propias[$id])) {
                    continue;
                }
                $stmt->execute([$orden, $id, $poiId]);
                unset($propias[$id]);
                $orden++;
            }

            // Las que no vinieron en la lista quedan al final, en su orden previo
            foreach (array_keys($propias) as $id) {
                $stmt->execute([$orden, $id, $poiId]);
                $orden++;
            }

            $this->db->commit();
            $this->syncCover($poiId);
            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Error al reordenar imágenes: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sincroniza points_of_interest.image_path con la portada de la galería.
     * Único punto de escritura de la columna espejo.
     */
    public function syncCover(int $poiId): void {
        try {
            $stmt = $this->db->prepare('
                SELECT image_path FROM poi_images
                WHERE poi_id = ?
                ORDER BY sort_order ASC, id ASC
                LIMIT 1
            ');
            $stmt->execute([$poiId]);
            $portada = $stmt->fetchColumn();

            $stmt = $this->db->prepare('UPDATE points_of_interest SET image_path = ? WHERE id = ?');
            $stmt->execute([$portada !== false ? $portada : null, $poiId]);
        } catch (PDOException $e) {
            error_log('Error al sincronizar portada: ' . $e->getMessage());
        }
    }

    public function countByPoiId(int $poiId): int {
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM poi_images WHERE poi_id = ?');
            $stmt->execute([$poiId]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Error al contar imágenes: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cantidad de imágenes para varios POIs en una sola consulta.
     * Evita el N+1 en los listados del admin.
     *
     * @return array [poi_id => cantidad]. Los POIs sin imágenes no aparecen.
     */
    public function countByPoiIds(array $poiIds): array {
        $poiIds = array_values(array_unique(array_map('intval', $poiIds)));
        if (empty($poiIds)) {
            return [];
        }

        try {
            $marcadores = implode(',', array_fill(0, count($poiIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT poi_id, COUNT(*) AS total FROM poi_images WHERE poi_id IN ({$marcadores}) GROUP BY poi_id"
            );
            $stmt->execute($poiIds);

            $conteos = [];
            foreach ($stmt->fetchAll() as $fila) {
                $conteos[(int) $fila['poi_id']] = (int) $fila['total'];
            }
            return $conteos;
        } catch (PDOException $e) {
            error_log('Error al contar imágenes por lote: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Formato de salida para las APIs públicas.
     */
    public static function toApiArray(array $rows): array {
        $salida = [];
        foreach ($rows as $fila) {
            $thumb = FileHelper::getThumbnailPath($fila['image_path']);
            $salida[] = [
                'id'            => (int) $fila['id'],
                'url'           => BASE_URL . '/' . $fila['image_path'],
                'thumbnail_url' => $thumb ? BASE_URL . '/' . $thumb : null,
                'caption'       => $fila['caption'] ?? null,
            ];
        }
        return $salida;
    }
}
```

- [ ] **Paso 2: Escribir el script de verificación**

Crear `install/verify/poi_gallery/verify_task2.php`. Usa un POI real de la base, agrega tres imágenes ficticias, verifica orden y portada, y limpia todo al final.

```php
<?php
// Sólo por línea de comandos: install/ es accesible por HTTP en XAMPP
// y este script escribe en la base.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script sólo corre por línea de comandos.
");
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
```

- [ ] **Paso 3: Ejecutar la verificación y confirmar que falla**

```bash
C:/xampp/php/php.exe "install/verify/poi_gallery/verify_task2.php"
```

Esperado antes de escribir el modelo: error fatal de PHP, `Failed opening required .../PoiImage.php`. Ejecutalo **antes** del Paso 1 si querés ver el rojo primero.

- [ ] **Paso 4: Ejecutar la verificación con el modelo ya escrito**

```bash
C:/xampp/php/php.exe "install/verify/poi_gallery/verify_task2.php"
```

Esperado: todas las líneas en `SI`.

- [ ] **Paso 5: Commit**

```bash
git add src/models/PoiImage.php install/verify/poi_gallery/verify_task2.php
git commit -m "feat(models): modelo PoiImage con orden y sincronización de portada"
```

---

### Task 3: Integración con el modelo `Point`

Esto es lo que mantiene funcionando a MCP, al importador EXIF y a `save_poi` sin tocarlos.

**Archivos:**
- Modificar: `src/models/Point.php` (`create()` líneas 77-102, `update()` líneas 111-136, `delete()` líneas 144-169)
- Verificar: `install/verify/poi_gallery/verify_task3.php`

**Interfaces:**
- Consume: `PoiImage::add()`, `PoiImage::countByPoiId()`, `PoiImage::getByPoiId()` (Task 2).
- Produce: garantía de que todo POI con `image_path` no vacío tiene al menos una fila en `poi_images`, sin importar por dónde se lo creó.

- [ ] **Paso 1: Agregar el require al inicio del archivo**

En `src/models/Point.php`, después del bloque docblock inicial y antes de `class Point {`:

```php
require_once __DIR__ . '/PoiImage.php';
```

- [ ] **Paso 2: Crear la fila espejo en `create()`**

Reemplazar el `return` final de `create()` (línea 97) por:

```php
            if (!$result) {
                return false;
            }

            $nuevoId = (int) $this->db->lastInsertId();
            $this->ensureGalleryRow($nuevoId, $data['image_path'] ?? null);
            return $nuevoId;
```

- [ ] **Paso 3: Crear la fila espejo en `update()`**

Reemplazar el `return $stmt->execute([...]);` de `update()` por la asignación del resultado y la llamada:

```php
            $result = $stmt->execute([
                $data['trip_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['type'],
                $data['icon'] ?? 'default',
                $data['image_path'] ?? null,
                $data['latitude'],
                $data['longitude'],
                $data['visit_date'] ?? null,
                $id
            ]);

            if ($result) {
                $this->ensureGalleryRow((int) $id, $data['image_path'] ?? null);
            }
            return $result;
```

- [ ] **Paso 4: Agregar el método privado `ensureGalleryRow()`**

Insertarlo justo después de `update()`:

```php
    /**
     * Garantiza que un POI con imagen tenga su fila en poi_images.
     *
     * Los escritores externos (MCP, importador EXIF, save_poi) sólo conocen
     * image_path. Este método los cubre sin obligarlos a conocer la galería.
     * Si el POI ya tiene imágenes, no toca nada: la galería manda.
     */
    private function ensureGalleryRow(int $pointId, $imagePath): void {
        if (empty($imagePath)) {
            return;
        }

        $galeria = new PoiImage();
        if ($galeria->countByPoiId($pointId) > 0) {
            return;
        }

        $galeria->add($pointId, $imagePath);
    }
```

- [ ] **Paso 5: Borrar los archivos de la galería en `delete()`**

En `delete()`, el `ON DELETE CASCADE` elimina las filas pero deja los archivos en disco. Reemplazar el cuerpo del `try` por:

```php
            // Recolectar rutas ANTES del DELETE: el CASCADE borra las filas
            // de poi_images pero no los archivos en disco.
            $point = $this->getById($id);
            $galeria = new PoiImage();
            $rutas = array_column($galeria->getByPoiId((int) $id), 'image_path');

            if ($point && !empty($point['image_path'])) {
                $rutas[] = $point['image_path'];
            }

            // Eliminar links asociados (sin FK cascade en tabla polimórfica)
            $this->db->prepare('DELETE FROM links WHERE entity_type = ? AND entity_id = ?')
                     ->execute(['poi', $id]);

            $stmt = $this->db->prepare('DELETE FROM points_of_interest WHERE id = ?');
            $result = $stmt->execute([$id]);

            if ($result) {
                foreach (array_unique(array_filter($rutas)) as $ruta) {
                    FileHelper::deleteFile($ruta);
                }
            }

            return $result;
```

`FileHelper` ya se carga en los puntos de entrada que usan el modelo; agregar `require_once __DIR__ . '/../helpers/FileHelper.php';` junto al require de `PoiImage.php` para que el modelo no dependa de eso.

- [ ] **Paso 6: Escribir el script de verificación**

Crear `install/verify/poi_gallery/verify_task3.php`:

```php
<?php
// Sólo por línea de comandos: install/ es accesible por HTTP en XAMPP
// y este script escribe en la base.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script sólo corre por línea de comandos.
");
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../src/models/Point.php';
require_once __DIR__ . '/../../../src/models/PoiImage.php';

$db = getDB();
$point = new Point();
$galeria = new PoiImage();

$tripId = (int) $db->query('SELECT id FROM trips ORDER BY id LIMIT 1')->fetchColumn();
if (!$tripId) { exit("No hay viajes en la base.\n"); }

// Archivos reales para comprobar el borrado en disco
$dir = ROOT_PATH . '/uploads/points';
@mkdir($dir, 0777, true);
file_put_contents($dir . '/_verify_cover.jpg', 'x');
file_put_contents($dir . '/_verify_extra.jpg', 'x');

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
echo 'borra archivo portada: ' . (!file_exists($dir . '/_verify_cover.jpg') ? 'SI' : 'NO') . PHP_EOL;
echo 'borra archivo extra: ' . (!file_exists($dir . '/_verify_extra.jpg') ? 'SI' : 'NO') . PHP_EOL;

@unlink($dir . '/_verify_cover.jpg');
@unlink($dir . '/_verify_extra.jpg');
```

- [ ] **Paso 7: Ejecutar la verificación**

```bash
C:/xampp/php/php.exe "install/verify/poi_gallery/verify_task3.php"
```

Esperado: las seis líneas en `SI`. Si `borra archivo extra` da `NO`, el paso 5 quedó mal: se están recolectando las rutas después del `DELETE`.

- [ ] **Paso 8: Commit**

```bash
git add src/models/Point.php install/verify/poi_gallery/verify_task3.php
git commit -m "feat(models): Point sincroniza la galería y limpia archivos al borrar"
```

---

### Task 4: Endpoint `api/poi_images.php`

**Archivos:**
- Crear: `api/poi_images.php`
- Verificar: `install/verify/poi_gallery/verify_task4.sh` (o los `curl` sueltos del paso 3)

**Interfaces:**
- Consume: `PoiImage` (Task 2), `FileHelper::uploadImage()`, `is_logged_in()` de `includes/auth.php`.
- Produce, y `assets/js/poi_gallery_admin.js` (Task 5) depende de este contrato exacto:

| `action` | Método | Parámetros | Respuesta OK |
|---|---|---|---|
| `upload` | POST multipart | `poi_id`, archivo `image` | `{success:true, image:{id,url,thumbnail_url,caption,sort_order}}` |
| `delete` | POST form | `image_id` | `{success:true}` |
| `reorder` | POST form | `poi_id`, `image_ids[]` | `{success:true}` |
| `caption` | POST form | `image_id`, `caption` | `{success:true}` |

Errores: `{success:false, error:"..."}` con 401 sin sesión, 400 por parámetros inválidos, 405 si no es POST, 500 ante excepción. El patrón de auth y de manejo de errores copia a `api/save_poi.php`.

- [ ] **Paso 1: Crear el endpoint**

```php
<?php
/**
 * API Endpoint - POI Images
 *
 * Administración de la galería de imágenes de un punto de interés.
 * Despacha por `action`: upload, delete, reorder, caption.
 * Todas las acciones son inmediatas (sin POST del formulario) para no chocar
 * contra post_max_size al subir varias fotos.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/PoiImage.php';
require_once __DIR__ . '/../src/helpers/FileHelper.php';

/**
 * Corta la ejecución con un error JSON.
 */
function responder_error(int $codigo, string $mensaje): void {
    http_response_code($codigo);
    echo json_encode(['success' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Verifica que el POI exista. Devuelve su id.
 */
function exigir_poi(PDO $db, $valor): int {
    $poiId = (int) $valor;
    if ($poiId <= 0) {
        responder_error(400, 'Invalid poi_id');
    }
    $stmt = $db->prepare('SELECT 1 FROM points_of_interest WHERE id = ?');
    $stmt->execute([$poiId]);
    if (!$stmt->fetchColumn()) {
        responder_error(404, 'Point of interest not found');
    }
    return $poiId;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder_error(405, 'Method not allowed');
    }

    $db      = getDB();
    $galeria = new PoiImage();
    $action  = $_POST['action'] ?? '';

    switch ($action) {
        case 'upload':
            $poiId = exigir_poi($db, $_POST['poi_id'] ?? 0);

            if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
                responder_error(400, 'No image uploaded');
            }

            $subida = FileHelper::uploadImage($_FILES['image']);
            if (!$subida['success']) {
                responder_error(400, $subida['error']);
            }

            $imageId = $galeria->add($poiId, $subida['path']);
            if (!$imageId) {
                FileHelper::deleteFile($subida['path']);
                responder_error(500, 'Error saving image');
            }

            $fila = $galeria->getById($imageId);
            $api  = PoiImage::toApiArray([$fila])[0];
            $api['sort_order'] = (int) $fila['sort_order'];

            echo json_encode(['success' => true, 'image' => $api], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete':
            $imageId = (int) ($_POST['image_id'] ?? 0);
            if ($imageId <= 0 || !$galeria->getById($imageId)) {
                responder_error(400, 'Invalid image_id');
            }
            if (!$galeria->delete($imageId)) {
                responder_error(500, 'Error deleting image');
            }
            echo json_encode(['success' => true]);
            break;

        case 'reorder':
            $poiId = exigir_poi($db, $_POST['poi_id'] ?? 0);
            $ids   = $_POST['image_ids'] ?? [];
            if (!is_array($ids)) {
                responder_error(400, 'image_ids must be an array');
            }
            // reorder() ya descarta los ids que no pertenezcan al POI
            if (!$galeria->reorder($poiId, array_map('intval', $ids))) {
                responder_error(500, 'Error reordering images');
            }
            echo json_encode(['success' => true]);
            break;

        case 'caption':
            $imageId = (int) ($_POST['image_id'] ?? 0);
            if ($imageId <= 0 || !$galeria->getById($imageId)) {
                responder_error(400, 'Invalid image_id');
            }
            if (!$galeria->updateCaption($imageId, $_POST['caption'] ?? null)) {
                responder_error(500, 'Error saving caption');
            }
            echo json_encode(['success' => true]);
            break;

        default:
            responder_error(400, 'Unknown action');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
```

- [ ] **Paso 2: Verificar el rechazo sin sesión**

```bash
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost/TravelMap/api/poi_images.php -d "action=delete&image_id=1"
```

Esperado: `401`.

- [ ] **Paso 3: Verificar el rechazo por método**

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/TravelMap/api/poi_images.php
```

Esperado: `401` (la sesión se chequea antes que el método; es el orden correcto, no filtra información a anónimos).

- [ ] **Paso 4: Verificar las acciones con sesión**

Las cuatro acciones se ejercitan de punta a punta en la Task 5, desde el formulario real. Acá alcanza con confirmar que el archivo no tiene errores de sintaxis:

```bash
C:/xampp/php/php.exe -l api/poi_images.php
```

Esperado: `No syntax errors detected`.

- [ ] **Paso 5: Commit**

```bash
git add api/poi_images.php
git commit -m "feat(api): endpoint de administración de galería de POI"
```

---

### Task 5: Formulario de administración

**Archivos:**
- Crear: `assets/js/poi_gallery_admin.js`
- Modificar: `admin/point_form.php` (bloque de imagen, líneas 392-448; requires línea 18; scripts al final), `admin/points.php` (celda de miniatura, líneas 163-177; require inicial), `assets/css/admin.css` (agregar al final), `lang/en.json`, `lang/es.json`
- Verificar: navegador

**Interfaces:**
- Consume: `api/poi_images.php` (Task 4), `PoiImage::getByPoiId()` y `countByPoiId()` (Task 2).
- Produce: contrato DOM que sólo usa esta tarea.

```html
<div id="poi-gallery" data-poi-id="12">
  <div class="poi-gallery-grid" id="poiGalleryGrid">
    <div class="poi-gallery-item" draggable="true" data-image-id="34">
      <img src="...thumb...">
      <span class="poi-gallery-cover-badge">Portada</span>
      <input type="text" class="poi-gallery-caption" value="...">
      <button type="button" class="poi-gallery-delete">…</button>
    </div>
  </div>
</div>
```

- [ ] **Paso 1: Agregar las claves de i18n**

En `lang/es.json`, dentro del objeto `"points"`:

```json
"gallery": "Galería de imágenes",
"gallery_add": "Agregar fotos",
"gallery_drag_hint": "Arrastrá las fotos para cambiar el orden. La primera es la portada.",
"gallery_cover": "Portada",
"gallery_caption_placeholder": "Descripción de la foto (opcional)",
"gallery_delete_confirm": "¿Eliminar esta foto? No se puede deshacer.",
"gallery_empty": "Todavía no hay fotos en este punto.",
"gallery_save_first": "Guardá el punto para poder agregar fotos.",
"gallery_upload_error": "No se pudo subir la imagen",
"gallery_photo_count": "fotos"
```

En `lang/en.json`, dentro del mismo objeto:

```json
"gallery": "Image gallery",
"gallery_add": "Add photos",
"gallery_drag_hint": "Drag photos to reorder. The first one is the cover.",
"gallery_cover": "Cover",
"gallery_caption_placeholder": "Photo caption (optional)",
"gallery_delete_confirm": "Delete this photo? This cannot be undone.",
"gallery_empty": "This point has no photos yet.",
"gallery_save_first": "Save the point to add photos.",
"gallery_upload_error": "Could not upload the image",
"gallery_photo_count": "photos"
```

- [ ] **Paso 2: Reemplazar el bloque de imagen en `point_form.php`**

Sustituir todo el bloque `<!-- Imagen con Drag & Drop -->` (líneas 392-448) por:

```php
                    <!-- Galería de imágenes -->
                    <div class="mb-3">
                        <label class="form-label"><?= __('points.gallery') ?></label>

                        <?php if (!$is_edit): ?>
                            <div class="alert alert-info py-2 mb-0">
                                <?= __('points.gallery_save_first') ?>
                            </div>
                        <?php else: ?>
                            <div id="poi-gallery" data-poi-id="<?= (int) $point['id'] ?>">
                                <div id="poiGalleryDrop" class="border border-secondary rounded p-4 text-center mb-3"
                                     style="background-color: #f8f9fa; cursor: pointer;">
                                    <p class="mb-2 fw-bold"><?= __('points.drag_drop_image') ?></p>
                                    <p class="mb-2 text-muted"><?= __('points.or') ?></p>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="poiGallerySelect">
                                        <?= __('points.gallery_add') ?>
                                    </button>
                                    <p class="small text-muted mt-2 mb-0">
                                        <?= __('points.max_upload_note') ?> <?= round(MAX_UPLOAD_SIZE / 1024 / 1024, 2) ?>MB
                                    </p>
                                    <div class="progress mt-3 d-none" id="poiGalleryProgress">
                                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                </div>

                                <input type="file" class="d-none" id="poiGalleryInput"
                                       accept="image/jpeg,image/png,image/jpg,image/gif" multiple>

                                <p class="small text-muted"><?= __('points.gallery_drag_hint') ?></p>

                                <div class="poi-gallery-grid" id="poiGalleryGrid">
                                    <?php foreach ($gallery_images as $img): ?>
                                        <?php $thumb = FileHelper::getThumbnailPath($img['image_path']); ?>
                                        <div class="poi-gallery-item" draggable="true" data-image-id="<?= (int) $img['id'] ?>">
                                            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($thumb ?: $img['image_path']) ?>"
                                                 alt="<?= htmlspecialchars($img['caption'] ?? '') ?>">
                                            <span class="poi-gallery-cover-badge"><?= __('points.gallery_cover') ?></span>
                                            <input type="text" class="poi-gallery-caption form-control form-control-sm"
                                                   value="<?= htmlspecialchars($img['caption'] ?? '') ?>"
                                                   placeholder="<?= __('points.gallery_caption_placeholder') ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger poi-gallery-delete">
                                                <?= __('common.remove') ?>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <p class="text-muted<?= empty($gallery_images) ? '' : ' d-none' ?>" id="poiGalleryEmpty">
                                    <?= __('points.gallery_empty') ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
```

- [ ] **Paso 3: Cargar la galería en el PHP de `point_form.php`**

Agregar el require junto a los demás (después de la línea 18):

```php
require_once __DIR__ . '/../src/models/PoiImage.php';
```

Instanciar el modelo **antes** del bloque POST, junto a `$pointModel` y `$poiLinkModel` (línea 22), porque el Paso 4 lo necesita dentro de ese bloque:

```php
$galleryModel = new PoiImage();
```

Y cargar las imágenes junto a `$existing_links` (línea 153), ya después del POST, para que refleje el estado final:

```php
$gallery_images = ($is_edit && $point) ? $galleryModel->getByPoiId((int) $point['id']) : [];
```

- [ ] **Paso 4: Quitar el manejo de imagen única del POST**

En el bloque POST de `point_form.php`, eliminar el `if (isset($_FILES['image']) ...)` completo (líneas 74-86). La subida ahora es por AJAX.

También eliminar del final del archivo el bloque `<script>` de drag & drop de imagen única (líneas 617-734), que apunta a elementos que ya no existen.

**Cuidado con la portada acá.** `$data['image_path']` toma el valor que tenía el punto **cuando se renderizó la página** (línea 67). Si el usuario sube o reordena fotos por AJAX y recién después guarda el formulario, `update()` escribiría el valor viejo y pisaría la portada correcta. Por eso, después del `$pointModel->update($point_id, $data)` exitoso (línea 108), resincronizar:

```php
                $galleryModel->syncCover($point_id);
```

`$galleryModel` se instancia en el Paso 3; moverlo arriba del bloque POST para que esté disponible. `syncCover()` es idempotente: si la galería está vacía deja `image_path` en `NULL`, que es lo correcto.

- [ ] **Paso 5: Escribir `assets/js/poi_gallery_admin.js`**

```javascript
/**
 * Galería de imágenes del formulario de POI.
 *
 * Subida, borrado, reordenamiento y captions, todo inmediato contra
 * api/poi_images.php. Se sube de a un archivo por request para no chocar
 * contra post_max_size.
 */
(function () {
    const raiz = document.getElementById('poi-gallery');
    if (!raiz) return;

    const poiId    = raiz.dataset.poiId;
    const grilla   = document.getElementById('poiGalleryGrid');
    const zona     = document.getElementById('poiGalleryDrop');
    const input    = document.getElementById('poiGalleryInput');
    const boton    = document.getElementById('poiGallerySelect');
    const vacio    = document.getElementById('poiGalleryEmpty');
    const progreso = document.getElementById('poiGalleryProgress');
    const barra    = progreso.querySelector('.progress-bar');

    const ENDPOINT = BASE_URL + '/api/poi_images.php';

    function pedir(datos) {
        return fetch(ENDPOINT, { method: 'POST', body: datos, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    // ── Estado visual ────────────────────────────────────────────────────────

    function refrescar() {
        const items = grilla.querySelectorAll('.poi-gallery-item');
        items.forEach(function (item, i) {
            item.classList.toggle('is-cover', i === 0);
        });
        vacio.classList.toggle('d-none', items.length > 0);
    }

    function ordenActual() {
        return Array.from(grilla.querySelectorAll('.poi-gallery-item'))
                    .map(function (el) { return el.dataset.imageId; });
    }

    function guardarOrden() {
        const datos = new FormData();
        datos.append('action', 'reorder');
        datos.append('poi_id', poiId);
        ordenActual().forEach(function (id) { datos.append('image_ids[]', id); });
        return pedir(datos);
    }

    // ── Alta ─────────────────────────────────────────────────────────────────

    function construirItem(imagen) {
        const item = document.createElement('div');
        item.className = 'poi-gallery-item';
        item.draggable = true;
        item.dataset.imageId = imagen.id;

        const img = document.createElement('img');
        img.src = imagen.thumbnail_url || imagen.url;
        item.appendChild(img);

        const badge = document.createElement('span');
        badge.className = 'poi-gallery-cover-badge';
        badge.textContent = __('points.gallery_cover');
        item.appendChild(badge);

        const caption = document.createElement('input');
        caption.type = 'text';
        caption.className = 'poi-gallery-caption form-control form-control-sm';
        caption.placeholder = __('points.gallery_caption_placeholder');
        caption.value = imagen.caption || '';
        item.appendChild(caption);

        const borrar = document.createElement('button');
        borrar.type = 'button';
        borrar.className = 'btn btn-sm btn-outline-danger poi-gallery-delete';
        borrar.textContent = __('common.remove');
        item.appendChild(borrar);

        conectarItem(item);
        return item;
    }

    function subirUno(archivo) {
        const datos = new FormData();
        datos.append('action', 'upload');
        datos.append('poi_id', poiId);
        datos.append('image', archivo);

        return pedir(datos).then(function (resp) {
            if (!resp.success) {
                alert(__('points.gallery_upload_error') + ': ' + (resp.error || ''));
                return;
            }
            grilla.appendChild(construirItem(resp.image));
            refrescar();
        });
    }

    // Secuencial: un archivo por request
    function subirVarios(archivos) {
        const lista = Array.from(archivos);
        if (lista.length === 0) return;

        progreso.classList.remove('d-none');
        let hechos = 0;

        function siguiente() {
            if (lista.length === 0) {
                progreso.classList.add('d-none');
                barra.style.width = '0%';
                return;
            }
            return subirUno(lista.shift()).then(function () {
                hechos++;
                barra.style.width = Math.round((hechos / (hechos + lista.length)) * 100) + '%';
                return siguiente();
            });
        }

        siguiente();
    }

    boton.addEventListener('click', function (e) { e.stopPropagation(); input.click(); });
    zona.addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () { subirVarios(this.files); input.value = ''; });

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (evento) {
        zona.addEventListener(evento, function (e) { e.preventDefault(); e.stopPropagation(); });
    });
    zona.addEventListener('drop', function (e) { subirVarios(e.dataTransfer.files); });

    // ── Borrado y caption ────────────────────────────────────────────────────

    function conectarItem(item) {
        item.querySelector('.poi-gallery-delete').addEventListener('click', function () {
            if (!confirm(__('points.gallery_delete_confirm'))) return;

            const datos = new FormData();
            datos.append('action', 'delete');
            datos.append('image_id', item.dataset.imageId);

            pedir(datos).then(function (resp) {
                if (resp.success) { item.remove(); refrescar(); }
            });
        });

        item.querySelector('.poi-gallery-caption').addEventListener('blur', function () {
            const datos = new FormData();
            datos.append('action', 'caption');
            datos.append('image_id', item.dataset.imageId);
            datos.append('caption', this.value);
            pedir(datos);
        });

        item.addEventListener('dragstart', function (e) {
            arrastrado = item;
            item.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', function () {
            item.classList.remove('is-dragging');
            arrastrado = null;
            refrescar();
            guardarOrden();
        });

        item.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!arrastrado || arrastrado === item) return;

            const caja = item.getBoundingClientRect();
            const despues = (e.clientX - caja.left) > (caja.width / 2);
            grilla.insertBefore(arrastrado, despues ? item.nextSibling : item);
        });
    }

    let arrastrado = null;

    grilla.querySelectorAll('.poi-gallery-item').forEach(conectarItem);
    refrescar();
})();
```

- [ ] **Paso 6: Cargar el script en `point_form.php`**

Después de la etiqueta de `point_map.js` (línea 589):

```php
<script src="<?= ASSETS_URL ?>/js/poi_gallery_admin.js?v=<?php echo $version; ?>"></script>
```

`i18n.js` ya define `__()` global y `BASE_URL` ya está declarado en la línea 581.

- [ ] **Paso 7: Agregar los estilos a `assets/css/admin.css`**

```css
/* ── Galería de imágenes del POI ─────────────────────────────────────────── */
.poi-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
}

.poi-gallery-item {
    position: relative;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 8px;
    background: #fff;
    cursor: grab;
}

.poi-gallery-item.is-dragging {
    opacity: .4;
}

.poi-gallery-item img {
    width: 100%;
    height: 100px;
    object-fit: cover;
    border-radius: 4px;
    margin-bottom: 6px;
    pointer-events: none;
}

.poi-gallery-cover-badge {
    display: none;
    position: absolute;
    top: 12px;
    left: 12px;
    background: rgba(13, 110, 253, .9);
    color: #fff;
    font-size: .7rem;
    padding: 2px 6px;
    border-radius: 3px;
}

.poi-gallery-item.is-cover .poi-gallery-cover-badge {
    display: block;
}

.poi-gallery-caption {
    margin-bottom: 6px;
}

.poi-gallery-delete {
    width: 100%;
}
```

- [ ] **Paso 8: Agregar el badge de cantidad en `admin/points.php`**

Agregar el require al inicio, junto a los demás modelos:

```php
require_once __DIR__ . '/../src/models/PoiImage.php';
```

Antes del `foreach` de la tabla (línea 161), resolver los conteos de **todos** los puntos en una sola consulta. Nada de `countByPoiId()` dentro del bucle: con un listado de cientos de puntos serían cientos de queries.

```php
$galleryModel  = new PoiImage();
$galleryCounts = $galleryModel->countByPoiIds(array_column($points, 'id'));
```

Y dentro del `<td>` de la miniatura, después del `<img>` (línea 167):

```php
                                        <?php $galleryCount = $galleryCounts[(int) $point['id']] ?? 0; ?>
                                        <?php if ($galleryCount > 1): ?>
                                            <span class="badge bg-secondary"><?= $galleryCount ?> <?= __('points.gallery_photo_count') ?></span>
                                        <?php endif; ?>
```

- [ ] **Paso 9: Verificar en el navegador**

Abrir `http://localhost/TravelMap/admin/point_form.php?id=<un POI existente>` y comprobar, con la consola de red abierta:

1. La foto que ya tenía el punto aparece en la grilla con el badge **Portada**.
2. Arrastrar tres fotos nuevas las sube de a una: tres requests `upload` separados, no uno solo.
3. Reordenar por drag & drop dispara un `reorder` al soltar, y el badge Portada salta a la primera.
4. Escribir un caption y salir del campo dispara un `caption`.
5. Borrar pide confirmación y dispara un `delete`.
6. Recargar la página: el orden, los captions y la portada se mantienen.
7. En `admin/points.php` la miniatura del punto muestra el badge con la cantidad.

- [ ] **Paso 10: Verificar que la portada quedó sincronizada**

```bash
C:/xampp/php/php.exe -r "require 'C:/xampp/htdocs/TravelMap/config/config.php'; require 'C:/xampp/htdocs/TravelMap/config/db.php'; $d=getDB(); foreach($d->query('SELECT p.id, p.image_path, (SELECT image_path FROM poi_images WHERE poi_id=p.id ORDER BY sort_order,id LIMIT 1) c FROM points_of_interest p')->fetchAll() as $r){ if(($r['image_path']?:null)!==($r['c']?:null)) echo \"DESINCRONIZADO POI {$r['id']}\n\"; } echo \"listo\n\";"
```

Esperado: sólo `listo`, sin líneas `DESINCRONIZADO`.

- [ ] **Paso 11: Commit**

```bash
git add assets/js/poi_gallery_admin.js admin/point_form.php admin/points.php assets/css/admin.css lang/en.json lang/es.json
git commit -m "feat(admin): galería de imágenes por POI con orden y captions"
```

---

### Task 6: Backups

**Archivos:**
- Modificar: `admin/backup.php` (stats líneas 29-34 y 43-75; export tras línea 172; restore tras línea 523)
- Verificar: navegador y `install/verify/poi_gallery/verify_task6.php`

**Interfaces:**
- Consume: tabla `poi_images` (Task 1), `$idMap['points']` que el archivo ya construye en la línea 517.
- Produce: clave `poi_images` dentro de `$backup['data']`, con las filas crudas de la tabla.

Los archivos de imagen no requieren cambios: el modo ZIP ya empaqueta `uploads/points` completo con un `glob()` del directorio (líneas 219-233), no fila por fila.

- [ ] **Paso 1: Agregar el contador a las estadísticas**

En el array `$stats` (líneas 29-34), agregar:

```php
    'gallery_images' => 0,
```

Y en el bloque `try` de estadísticas, después del conteo de links (línea 65):

```php
    // Contar imágenes de galería
    if ((bool) $db->query("SHOW TABLES LIKE 'poi_images'")->fetchColumn()) {
        $stmt = $db->query('SELECT COUNT(*) as total FROM poi_images');
        $stats['gallery_images'] = (int) $stmt->fetch()['total'];
    }
```

- [ ] **Paso 2: Exportar la tabla**

Después del bloque `// Export tags` (termina en la línea 172) y antes de `// Export links`:

```php
            // Export gallery images (sigue al flag de puntos)
            if ($includePoints && (bool) $db->query("SHOW TABLES LIKE 'poi_images'")->fetchColumn()) {
                $stmt = $db->query('SELECT * FROM poi_images ORDER BY poi_id, sort_order, id');
                $backup['data']['poi_images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $backup['includes'][] = 'poi_images';
            }
```

- [ ] **Paso 3: Restaurar la tabla**

Después del bloque de importación de puntos (termina en la línea 523, con `$imported['points_updated']`) y antes de `// Import tags`:

```php
                // Import gallery images (con poi_id remapeado)
                if (isset($backupData['data']['poi_images'])) {
                    $imported['gallery_images'] = 0;

                    foreach ($backupData['data']['poi_images'] as $img) {
                        $newPoiId = $idMap['points'][(int) $img['poi_id']] ?? null;
                        if (!$newPoiId) continue;

                        // Evita duplicar al re-restaurar el mismo backup
                        $stmt = $db->prepare('SELECT id FROM poi_images WHERE poi_id = ? AND image_path = ?');
                        $stmt->execute([$newPoiId, $img['image_path']]);
                        if ($stmt->fetch() && $restoreMode !== 'replace') continue;

                        $stmt = $db->prepare(
                            'INSERT INTO poi_images (poi_id, image_path, caption, sort_order) VALUES (?, ?, ?, ?)'
                        );
                        $stmt->execute([
                            $newPoiId,
                            $img['image_path'],
                            $img['caption'] ?? null,
                            (int) ($img['sort_order'] ?? 0)
                        ]);
                        $imported['gallery_images']++;
                    }
                }
```

- [ ] **Paso 4: Inicializar el contador de importación**

En el array `$imported` (línea 354), agregar `'gallery_images' => 0` para que el resumen no muestre un índice indefinido.

- [ ] **Paso 5: Escribir el script de verificación**

Crear `install/verify/poi_gallery/verify_task6.php`, que valida un backup ya generado:

```php
<?php
// Sólo por línea de comandos: install/ es accesible por HTTP en XAMPP
// y este script escribe en la base.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script sólo corre por línea de comandos.
");
}

// Uso: php verify_task6.php <ruta al backup .json>
$ruta = $argv[1] ?? '';
if (!is_file($ruta)) { exit("Pasá la ruta del backup JSON.\n"); }

$backup = json_decode(file_get_contents($ruta), true);

$tiene = isset($backup['data']['poi_images']);
echo 'incluye poi_images: ' . ($tiene ? 'SI' : 'NO') . PHP_EOL;
if (!$tiene) { exit(1); }

$filas = $backup['data']['poi_images'];
echo 'cantidad de filas: ' . count($filas) . PHP_EOL;

$claves = ['id', 'poi_id', 'image_path', 'caption', 'sort_order'];
$ok = true;
foreach ($filas as $f) {
    foreach ($claves as $c) {
        if (!array_key_exists($c, $f)) { $ok = false; }
    }
}
echo 'todas las columnas presentes: ' . ($ok ? 'SI' : 'NO') . PHP_EOL;
echo 'declarado en includes: ' . (in_array('poi_images', $backup['includes'], true) ? 'SI' : 'NO') . PHP_EOL;
```

- [ ] **Paso 6: Verificar el ciclo completo**

1. En `http://localhost/TravelMap/admin/backup.php`, crear un backup JSON con puntos incluidos y descargarlo.
2. Correr:

```bash
C:/xampp/php/php.exe "install/verify/poi_gallery/verify_task6.php" "C:/Users/fabio/Downloads/backup_<timestamp>.json"
```

Esperado: `SI` en las tres líneas y una cantidad igual a la de `poi_images` en la base.

3. Restaurar ese backup en modo `replace` y confirmar en el admin que el POI de prueba recupera todas sus fotos, en el mismo orden.
4. Restaurar un backup **anterior** a este cambio (sin la clave `poi_images`) y confirmar que no hay errores y que los puntos entran con su imagen: `Point::create()` genera la fila espejo.

- [ ] **Paso 7: Commit**

```bash
git add admin/backup.php install/verify/poi_gallery/verify_task6.php
git commit -m "feat(backup): export y restore de la galería de imágenes"
```

---

### Task 7: APIs públicas

**Archivos:**
- Modificar: `api/get_all_data.php` (bloque de puntos, líneas 112-140), `api/get_trip.php` (líneas 78-96), `trip.php` (líneas 120-145)
- Verificar: `curl`

**Interfaces:**
- Consume: `PoiImage::getByTripId()` y `PoiImage::toApiArray()` (Task 2).
- Produce, y las tareas 8 a 10 dependen de esta forma exacta:

```json
{
  "id": 12, "title": "...", "image_url": "...", "thumbnail_url": "...",
  "images": [ { "id": 34, "url": "...", "thumbnail_url": "...", "caption": null } ]
}
```

`images` siempre está presente; es `[]` cuando el punto no tiene fotos. `image_url` y `thumbnail_url` se conservan apuntando a la portada.

- [ ] **Paso 1: Modificar `api/get_all_data.php`**

Agregar el require junto a los demás modelos (después de la línea 17):

```php
require_once __DIR__ . '/../src/models/PoiImage.php';
```

Instanciar junto a los otros modelos (después de la línea 36):

```php
    $poiImageModel = new PoiImage();
```

Dentro del `foreach ($trips as $trip)`, antes del `foreach ($points as $point)` (línea 117), resolver las imágenes del viaje en una consulta:

```php
        $tripImages = $poiImageModel->getByTripId((int) $trip['id']);
```

Y agregar la clave al array de cada punto, después de `'links' => $links,` (línea 138):

```php
                'images' => PoiImage::toApiArray($tripImages[(int) $point['id']] ?? []),
```

- [ ] **Paso 2: Modificar `api/get_trip.php`**

Mismo patrón: require, instancia, `$tripImages = $poiImageModel->getByTripId($tripId);` antes del bucle de puntos, y `'images' => PoiImage::toApiArray($tripImages[(int) $point['id']] ?? []),` junto a `thumbnail_url` (línea 94).

- [ ] **Paso 3: Modificar `trip.php`**

Mismo patrón, en el bloque que arma `$processedPoints` (líneas 120-145). El require va junto a los otros modelos del encabezado del archivo.

- [ ] **Paso 4: Verificar la salida de la API**

```bash
curl -s "http://localhost/TravelMap/api/get_all_data.php" | C:/xampp/php/php.exe -r "$d=json_decode(stream_get_contents(STDIN),true); $p=$d['data']['trips'][0]['points'][0]; echo 'tiene images: '.(array_key_exists('images',$p)?'SI':'NO').PHP_EOL; echo 'images es array: '.(is_array($p['images'])?'SI':'NO').PHP_EOL; echo 'image_url conservado: '.(array_key_exists('image_url',$p)?'SI':'NO').PHP_EOL;"
```

Esperado: las tres líneas en `SI`.

- [ ] **Paso 5: Verificar que la portada coincide con la primera imagen**

```bash
curl -s "http://localhost/TravelMap/api/get_all_data.php" | C:/xampp/php/php.exe -r "$d=json_decode(stream_get_contents(STDIN),true); $mal=0; foreach($d['data']['trips'] as $t){ foreach($t['points'] as $p){ if(!empty($p['images']) && $p['images'][0]['url']!==$p['image_url']) $mal++; } } echo ($mal===0?'portadas coherentes: SI':\"portadas incoherentes: $mal\").PHP_EOL;"
```

Esperado: `portadas coherentes: SI`.

- [ ] **Paso 6: Commit**

```bash
git add api/get_all_data.php api/get_trip.php trip.php
git commit -m "feat(api): exponer la galería de imágenes por punto"
```

---

### Task 8: Carrusel en el popup del mapa

Lo comparten `index.php` y `trip.php`, con sus dos renderers.

**Archivos:**
- Crear: `assets/css/poi_gallery.css`
- Modificar: `assets/js/map-renderer.js` (`createPoiPopup` líneas 245-322, API pública líneas 324-335), `index.php` (`<head>`, después del bloque condicional de CSS que termina en la línea 56), `trip.php` (`<head>`, junto a la línea 217), `lang/en.json`, `lang/es.json`
- Verificar: navegador

**Por qué un archivo de CSS nuevo:** `public_map.css` sólo se carga en la rama MapLibre de `index.php` (línea 55); la rama Leaflet carga `public_map_leaflet.css` (línea 50). Meter ahí los estilos de galería dejaría el carrusel sin estilo con el renderer Leaflet. Un archivo propio cargado fuera del condicional sirve a los dos renderers y a `trip.php`, sin duplicar reglas.

**Interfaces:**
- Consume: `point.images` (Task 7).
- Produce, y las tareas 9 y 10 dependen de esto:
  - `window.__poiGalleries` — objeto `{ [poiId]: [{url, thumbnail_url, caption}] }`, poblado al construir cada popup.
  - `window.openPoiGallery(poiId, index)` — la define cada vista. `map-renderer.js` sólo la invoca; si no existe, el click no hace nada.
  - Contrato DOM del carrusel:

```html
<div class="popup-gallery" data-poi-id="12" data-index="0">
  <img class="popup-image" src="...">
  <button class="popup-gallery-nav prev" data-dir="-1">‹</button>
  <button class="popup-gallery-nav next" data-dir="1">›</button>
  <div class="popup-gallery-dots">
    <span class="popup-gallery-dot is-active" data-index="0"></span>
  </div>
</div>
```

- [ ] **Paso 1: Agregar las claves de i18n**

En `lang/es.json`, dentro del objeto `"map"`:

```json
"gallery_prev": "Imagen anterior",
"gallery_next": "Imagen siguiente",
"gallery_counter": "{current} de {total}"
```

En `lang/en.json`:

```json
"gallery_prev": "Previous image",
"gallery_next": "Next image",
"gallery_counter": "{current} of {total}"
```

- [ ] **Paso 2: Reemplazar el bloque de imagen en `createPoiPopup`**

Sustituir el `if (showImage && point.image_url) { ... }` (líneas 264-271) por:

```javascript
        if (showImage) {
            var imagenes = (point.images && point.images.length) ? point.images : null;

            // Compatibilidad: puntos servidos por un endpoint sin `images`
            if (!imagenes && point.image_url) {
                imagenes = [{ url: point.image_url, thumbnail_url: point.thumbnail_url, caption: null }];
            }

            if (imagenes) {
                var poiId = point.id;
                window.__poiGalleries = window.__poiGalleries || {};
                window.__poiGalleries[poiId] = imagenes;

                var primera = imagenes[0];
                html += '<div class="popup-gallery" data-poi-id="' + escapeHtml(String(poiId)) + '" data-index="0">';
                html += '<img src="' + escapeHtml(primera.thumbnail_url || primera.url) + '"'
                     + ' alt="' + escapeHtml(point.title) + '"'
                     + ' class="popup-image"'
                     + ' title="' + t('map.click_to_view_full', '') + '">';

                if (imagenes.length > 1) {
                    html += '<button type="button" class="popup-gallery-nav prev" data-dir="-1"'
                         + ' aria-label="' + t('map.gallery_prev', 'Previous image') + '">&#10094;</button>';
                    html += '<button type="button" class="popup-gallery-nav next" data-dir="1"'
                         + ' aria-label="' + t('map.gallery_next', 'Next image') + '">&#10095;</button>';
                    html += '<div class="popup-gallery-dots">';
                    imagenes.forEach(function (img, i) {
                        html += '<span class="popup-gallery-dot' + (i === 0 ? ' is-active' : '') + '"'
                             + ' data-index="' + i + '"></span>';
                    });
                    html += '</div>';
                }

                html += '</div>';
            }
        }
```

- [ ] **Paso 3: Agregar la delegación de eventos**

Antes del bloque `// ── Public API ──` (línea 324), dentro del mismo IIFE:

```javascript
    // ── Navegación del carrusel ───────────────────────────────────────────────
    //
    // Los popups se inyectan como cadena HTML, así que no se pueden enganchar
    // listeners por elemento al construirlos. Se delega en document.

    function mostrarEnCarrusel(contenedor, indice) {
        var poiId = contenedor.dataset.poiId;
        var imagenes = (window.__poiGalleries || {})[poiId];
        if (!imagenes || !imagenes.length) return;

        // Vuelta circular
        indice = ((indice % imagenes.length) + imagenes.length) % imagenes.length;

        var imagen = imagenes[indice];
        contenedor.dataset.index = String(indice);
        contenedor.querySelector('.popup-image').src = imagen.thumbnail_url || imagen.url;

        contenedor.querySelectorAll('.popup-gallery-dot').forEach(function (punto, i) {
            punto.classList.toggle('is-active', i === indice);
        });
    }

    document.addEventListener('click', function (e) {
        var flecha = e.target.closest ? e.target.closest('.popup-gallery-nav') : null;
        if (flecha) {
            e.preventDefault();
            e.stopPropagation();
            var cont = flecha.closest('.popup-gallery');
            mostrarEnCarrusel(cont, parseInt(cont.dataset.index, 10) + parseInt(flecha.dataset.dir, 10));
            return;
        }

        var punto = e.target.closest ? e.target.closest('.popup-gallery-dot') : null;
        if (punto) {
            e.preventDefault();
            e.stopPropagation();
            var cont2 = punto.closest('.popup-gallery');
            mostrarEnCarrusel(cont2, parseInt(punto.dataset.index, 10));
            return;
        }

        var imagen = e.target.closest ? e.target.closest('.popup-gallery .popup-image') : null;
        if (imagen && typeof window.openPoiGallery === 'function') {
            e.preventDefault();
            e.stopPropagation();
            var cont3 = imagen.closest('.popup-gallery');
            window.openPoiGallery(cont3.dataset.poiId, parseInt(cont3.dataset.index, 10));
        }
    });
```

- [ ] **Paso 4: Crear `assets/css/poi_gallery.css`**

Este archivo concentra **todo** el CSS de galería y lightbox que comparten `index.php` y `trip.php`. La Task 9 le agrega los estilos del lightbox; acá va sólo el carrusel del popup.

```css
/**
 * Galería de imágenes de POI.
 *
 * Compartido por index.php (ambos renderers) y trip.php.
 * Se carga fuera del condicional de renderer: public_map.css sólo existe
 * en la rama MapLibre.
 */

/* ── Carrusel del popup de POI ───────────────────────────────────────────── */
.popup-gallery {
    position: relative;
}

.popup-gallery .popup-image {
    cursor: pointer;
    display: block;
}

.popup-gallery-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    background: rgba(0, 0, 0, .45);
    color: #fff;
    width: 26px;
    height: 34px;
    line-height: 1;
    cursor: pointer;
    font-size: 16px;
    padding: 0;
}

.popup-gallery-nav.prev { left: 0; border-radius: 0 4px 4px 0; }
.popup-gallery-nav.next { right: 0; border-radius: 4px 0 0 4px; }
.popup-gallery-nav:hover { background: rgba(0, 0, 0, .75); }

.popup-gallery-dots {
    display: flex;
    justify-content: center;
    gap: 5px;
    padding: 5px 0 2px;
}

.popup-gallery-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: rgba(0, 0, 0, .25);
    cursor: pointer;
}

.popup-gallery-dot.is-active {
    background: rgba(0, 0, 0, .75);
}
```

Cargarlo en `index.php`, **después** del `<?php endif; ?>` del bloque condicional de CSS (línea 56), para que llegue a los dos renderers:

```php
    <!-- Galería de imágenes de POI (compartida por ambos renderers) -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/poi_gallery.css?v=<?php echo $version; ?>">
```

Y en `trip.php`, junto a los otros `<link>` (línea 217):

```php
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/poi_gallery.css?v=<?php echo $version; ?>">
```

- [ ] **Paso 5: Verificar en el navegador**

En `http://localhost/TravelMap/` abrir un POI con varias fotos:

1. Aparecen las flechas y los puntitos debajo de la imagen.
2. Las flechas cambian la foto sin cerrar el popup y sin mover el mapa.
3. El puntito activo acompaña el cambio.
4. Hacer click en un puntito salta a esa foto.
5. En un POI con una sola foto **no** hay flechas ni puntitos, y se ve igual que antes.
6. Repetir con el renderer Leaflet: cambiar `map_style` desde `admin/settings.php` para forzar `public_map_leaflet.js` y confirmar el mismo comportamiento.

- [ ] **Paso 6: Commit**

```bash
git add assets/js/map-renderer.js assets/css/poi_gallery.css index.php trip.php lang/en.json lang/es.json
git commit -m "feat(map): carrusel de galería en el popup de POI"
```

---

### Task 9: Lightbox con galería en `index.php`

**Archivos:**
- Crear: `assets/js/poi_lightbox.js`
- Modificar: `index.php` (markup del lightbox líneas 363-374; scripts línea 360), `assets/js/public_map.js` (borrar `initLightbox` líneas 1620-1625 y su llamada en la línea 1615), `assets/js/public_map_leaflet.js` (borrar `initLightbox` líneas 1893-1901 y su llamada en la línea 1873), `assets/css/poi_gallery.css` (creado en la Task 8)
- Verificar: navegador

**Interfaces:**
- Consume: `window.__poiGalleries` (Task 8).
- Produce: `window.openPoiGallery(poiId, index)` y `window.changeImage(step)`, que el carrusel del popup invoca.

`window.openLightbox(url, altText)` **no se toca**: la usan las imágenes de rutas, que no tienen galería.

Hay un detalle que rompe la feature si se pasa por alto: hoy los dos renderers cierran el lightbox con un listener sobre **todo** el contenedor (`lightbox.addEventListener('click', closeLightbox)`). Con botones adentro, cualquier click en una flecha lo cerraría. Hay que restringir el cierre al backdrop.

Ese listener está duplicado hoy en los dos renderers. En vez de corregir la misma lógica en dos lugares, se borra de ambos y pasa a ser responsabilidad exclusiva de `poi_lightbox.js`, que se carga después de cualquiera de los dos.

- [ ] **Paso 1: Borrar `initLightbox` de `public_map.js`**

Eliminar la función completa (líneas 1620-1625) y la llamada `initLightbox();` de la línea 1615. El comentario de sección `// ==================== LIGHTBOX ====================` se conserva: `openLightbox` y `closeLightbox` siguen ahí.

- [ ] **Paso 2: Borrar `initLightbox` de `public_map_leaflet.js`**

Eliminar la función completa (líneas 1893-1901, con su docblock) y la llamada `initLightbox();` de la línea 1873. No debe quedar ninguna referencia a `initLightbox` en el archivo:

```bash
rg -n "initLightbox" assets/js/
```

Esperado: sin resultados.

- [ ] **Paso 3: Agregar los controles al markup de `index.php`**

Reemplazar el bloque del lightbox (líneas 363-374) por:

```php
    <!-- Lightbox para imágenes -->
    <div id="imageLightbox" class="lightbox" style="display: none;">
        <button class="lightbox-close" onclick="closeLightbox()" aria-label="<?= __('common.close') ?? 'Close' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
            </svg>
        </button>
        <button class="lightbox-prev" id="lightboxPrev" onclick="changeImage(-1)" style="display: none;" aria-label="<?= __('map.gallery_prev') ?>">&#10094;</button>
        <button class="lightbox-next" id="lightboxNext" onclick="changeImage(1)" style="display: none;" aria-label="<?= __('map.gallery_next') ?>">&#10095;</button>
        <div class="lightbox-content">
            <img id="lightboxImage" src="" alt="">
            <div class="lightbox-caption" id="lightboxCaption"></div>
        </div>
        <span class="lightbox-counter" id="lightboxCounter" style="display: none;"></span>
        <span class="lightbox-hint"><?= __('map.click_anywhere_to_close') ?? 'Click anywhere to close' ?></span>
    </div>
```

- [ ] **Paso 4: Escribir `assets/js/poi_lightbox.js`**

```javascript
/**
 * Lightbox con galería para el mapa general.
 *
 * Recorre sólo las imágenes del POI abierto: index.php muestra todos los
 * viajes a la vez y encadenar puntos sin relación no aporta contexto.
 * Compartido por los dos renderers (MapLibre y Leaflet).
 */
(function () {
    var galeriaActual = [];
    var indiceActual  = 0;

    function elemento(id) { return document.getElementById(id); }

    function pintar() {
        var imagen = galeriaActual[indiceActual];
        if (!imagen) return;

        elemento('lightboxImage').src = imagen.url;
        elemento('lightboxImage').alt = imagen.caption || '';

        var pie = elemento('lightboxCaption');
        pie.textContent = imagen.caption || '';
        pie.style.display = imagen.caption ? 'block' : 'none';

        var varias = galeriaActual.length > 1;
        elemento('lightboxPrev').style.display = varias ? 'block' : 'none';
        elemento('lightboxNext').style.display = varias ? 'block' : 'none';

        var contador = elemento('lightboxCounter');
        if (varias) {
            var plantilla = (typeof window.__ === 'function')
                ? window.__('map.gallery_counter') : '{current} / {total}';
            contador.textContent = plantilla
                .replace('{current}', indiceActual + 1)
                .replace('{total}', galeriaActual.length);
            contador.style.display = 'block';
        } else {
            contador.style.display = 'none';
        }
    }

    /**
     * Abre el lightbox en la galería de un POI.
     */
    window.openPoiGallery = function (poiId, indice) {
        var imagenes = (window.__poiGalleries || {})[poiId];
        if (!imagenes || !imagenes.length) return;

        galeriaActual = imagenes;
        indiceActual  = indice || 0;

        pintar();
        elemento('imageLightbox').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    /**
     * Avanza dentro de la galería, con vuelta circular.
     */
    window.changeImage = function (paso) {
        if (galeriaActual.length < 2) return;
        var total = galeriaActual.length;
        indiceActual = ((indiceActual + paso) % total + total) % total;
        pintar();
    };

    // Las imágenes de ruta usan openLightbox() y no tienen galería:
    // hay que limpiar el estado para que no queden flechas de la vez anterior.
    var abrirSuelta = window.openLightbox;
    window.openLightbox = function (url, alt) {
        galeriaActual = [];
        indiceActual  = 0;
        elemento('lightboxPrev').style.display = 'none';
        elemento('lightboxNext').style.display = 'none';
        elemento('lightboxCounter').style.display = 'none';
        elemento('lightboxCaption').style.display = 'none';
        if (typeof abrirSuelta === 'function') abrirSuelta(url, alt);
    };

    // Cierre por backdrop. Antes vivía duplicado en los dos renderers y cerraba
    // ante CUALQUIER click: con las flechas adentro, eso rompía la navegación.
    var lightbox = elemento('imageLightbox');
    if (lightbox) {
        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox) window.closeLightbox();
        });
    }

    document.addEventListener('keydown', function (e) {
        var caja = elemento('imageLightbox');
        if (!caja || caja.style.display !== 'flex') return;
        if (e.key === 'ArrowLeft')  window.changeImage(-1);
        if (e.key === 'ArrowRight') window.changeImage(1);
    });
})();
```

El `Escape` lo siguen manejando los renderers, que ya tienen su propio listener.

- [ ] **Paso 5: Cargar el script en `index.php`**

Después del bloque condicional de renderers (línea 361), para que `window.openLightbox` ya exista cuando `poi_lightbox.js` la envuelve:

```php
    <!-- Lightbox con galería (compartido por ambos renderers) -->
    <script src="<?= ASSETS_URL ?>/js/poi_lightbox.js?v=<?php echo $version; ?>"></script>
```

- [ ] **Paso 6: Agregar los estilos**

Al final de `assets/css/poi_gallery.css`, el archivo creado en la Task 8. Estas reglas las usan tanto `index.php` como `trip.php`, así que van una sola vez acá y **no** se repiten en `trip.css`:

```css
/* ── Navegación del lightbox ─────────────────────────────────────────────── */
.lightbox-prev,
.lightbox-next {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, .45);
    color: #fff;
    border: 0;
    font-size: 28px;
    padding: 12px 18px;
    cursor: pointer;
    z-index: 2;
}

.lightbox-prev { left: 20px; }
.lightbox-next { right: 20px; }

.lightbox-prev:hover,
.lightbox-next:hover { background: rgba(0, 0, 0, .75); }

.lightbox-counter {
    position: absolute;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    color: #fff;
    background: rgba(0, 0, 0, .5);
    padding: 4px 12px;
    border-radius: 12px;
    font-size: .85rem;
}

.lightbox-caption {
    color: #fff;
    text-align: center;
    padding: 10px 20px;
    max-width: 800px;
    margin: 0 auto;
}
```

- [ ] **Paso 7: Verificar en el navegador**

En `http://localhost/TravelMap/`:

1. Abrir un POI con varias fotos y hacer click en la imagen del popup: el lightbox abre en la foto que estaba visible, no siempre en la primera.
2. Las flechas recorren la galería y dan la vuelta al llegar al final.
3. El contador muestra la posición correcta.
4. Las teclas ← y → hacen lo mismo.
5. Hacer click en una flecha **no** cierra el lightbox. Este es el punto que rompía antes del arreglo del backdrop.
6. Hacer click en el fondo sí lo cierra.
7. Abrir la imagen de una **ruta**: se muestra sin flechas, sin contador y sin restos de la galería anterior.
8. Repetir todo con el renderer Leaflet.

- [ ] **Paso 8: Commit**

```bash
git add assets/js/poi_lightbox.js index.php assets/js/public_map.js assets/js/public_map_leaflet.js assets/css/poi_gallery.css
git commit -m "feat(map): lightbox con navegación de galería en el mapa general"
```

---

### Task 10: Galería completa en `trip.php`

**Archivos:**
- Modificar: `assets/js/trip_single.js` (`initGallery` líneas 594-606, `openLightbox` líneas 574-588, `viewImageFromData` líneas 628-677, `showLightboxImage` líneas 679-708), `trip.php` (carrusel líneas 393-411; contador en el lightbox), `assets/css/trip.css`
- Verificar: navegador

**Interfaces:**
- Consume: `TRIP_DATA.points[].images` (Task 7), `window.__poiGalleries` (Task 8).
- Produce: `window.openPoiGallery(poiId, index)` con semántica **de viaje**: traduce el índice local del POI a su posición en el arreglo aplanado.

Diferencia central con la Task 9: acá `galleryItems` recorre **todo el viaje**, agotando la galería de cada POI antes de saltar al siguiente.

- [ ] **Paso 1: Reconstruir `initGallery()` desde `TRIP_DATA`**

Reemplazar `initGallery()` (líneas 594-606) por:

```javascript
// Construye la galería aplanada del viaje.
//
// Orden: puntos por visit_date, y dentro de cada punto sus imágenes por
// sort_order. Así changeImage() agota la galería de un POI antes de pasar
// al siguiente. Se arma desde TRIP_DATA y no del DOM, porque el carrusel
// lateral sólo muestra la portada de cada punto.
function initGallery() {
    galleryItems = [];
    if (typeof TRIP_DATA === 'undefined' || !TRIP_DATA.points) return;

    const puntos = TRIP_DATA.points
        .filter(function (p) { return p.images && p.images.length > 0; })
        .slice()
        .sort(function (a, b) {
            const fa = Date.parse(a.visit_date || '1970-01-01T00:00:00');
            const fb = Date.parse(b.visit_date || '1970-01-01T00:00:00');
            return fa - fb;
        });

    puntos.forEach(function (punto) {
        punto.images.forEach(function (imagen, i) {
            galleryItems.push({
                url:      imagen.url,
                title:    punto.title,
                desc:     imagen.caption || punto.description || '',
                pointId:  String(punto.id),
                localIndex: i
            });
        });
    });
}
```

- [ ] **Paso 2: Reemplazar el alias `openLightbox` por `openPoiGallery`**

Sustituir el bloque de `window.openLightbox` (líneas 574-588) por:

```javascript
// Llamada desde el carrusel del popup (map-renderer.js).
// Traduce el índice local del POI a su posición en la galería del viaje.
window.openPoiGallery = function (poiId, indiceLocal) {
    if (galleryItems.length === 0) initGallery();

    const indice = galleryItems.findIndex(function (item) {
        return item.pointId === String(poiId) && item.localIndex === (indiceLocal || 0);
    });

    if (indice !== -1) showLightboxImage(indice);
};

// Compatibilidad: imágenes sueltas sin galería (rutas).
window.openLightbox = function (imageUrl) {
    if (galleryItems.length === 0) initGallery();
    const indice = galleryItems.findIndex(function (item) { return item.url === imageUrl; });

    if (indice !== -1) {
        showLightboxImage(indice);
        return;
    }

    document.getElementById('lightboxImage').src = imageUrl;
    document.getElementById('lightboxTitle').textContent = '';
    document.getElementById('lightboxDesc').style.display = 'none';
    document.querySelector('.lightbox-footer').classList.remove('has-content');
    document.getElementById('imageLightbox').style.display = 'flex';
};
```

- [ ] **Paso 3: Ajustar `viewImageFromData` para abrir en la portada**

En `viewImageFromData` (líneas 671-674), reemplazar la búsqueda por URL:

```javascript
    if (!showImage) {
        const indice = galleryItems.findIndex(function (item) {
            return item.pointId === String(pointId) && item.localIndex === 0;
        });
        if (indice !== -1) showLightboxImage(indice);
    }
```

- [ ] **Paso 4: Agregar el contador a `showLightboxImage`**

Al final de `showLightboxImage` (antes de `lightbox.style.display = 'flex';`, línea 707):

```javascript
    const contador = document.getElementById('lightboxCounter');
    if (contador) {
        const plantilla = (typeof window.__ === 'function')
            ? window.__('map.gallery_counter') : '{current} / {total}';
        contador.textContent = plantilla
            .replace('{current}', index + 1)
            .replace('{total}', galleryItems.length);
        contador.style.display = galleryItems.length > 1 ? 'block' : 'none';
    }
```

- [ ] **Paso 5: Agregar el contador al markup de `trip.php`**

Dentro del bloque del lightbox (línea 444), antes de `lightbox-hint`:

```php
        <span class="lightbox-counter" id="lightboxCounter" style="display: none;"></span>
```

- [ ] **Paso 6: Agregar el badge de cantidad al carrusel lateral**

En el `foreach` del carrusel de `trip.php` (líneas 397-409), dentro de `.media-photo` y después del `<img>`:

```php
                                    <?php $imgCount = count($p['images'] ?? []); ?>
                                    <?php if ($imgCount > 1): ?>
                                        <span class="media-count-badge"><?= $imgCount ?></span>
                                    <?php endif; ?>
```

El carrusel sigue mostrando **una** tarjeta por POI, con `thumbnail_url` (la portada). No cambia el filtro `$pointsWithImages`.

- [ ] **Paso 7: Agregar los estilos a `assets/css/trip.css`**

```css
/* ── Indicadores de galería ──────────────────────────────────────────────── */
.media-photo {
    position: relative;
}

.media-count-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    background: rgba(0, 0, 0, .65);
    color: #fff;
    font-size: .7rem;
    padding: 1px 6px;
    border-radius: 10px;
}
```

`.lightbox-counter` **no va acá**: ya está definido en `assets/css/poi_gallery.css` (Task 9) y `trip.php` carga ese archivo desde la Task 8. Repetir la regla es duplicación pura.

- [ ] **Paso 8: Verificar en el navegador**

En `http://localhost/TravelMap/trip.php?id=<un viaje con POIs de varias fotos>`:

1. El carrusel lateral muestra una tarjeta por punto, con la **primera** imagen según el orden del admin, y el badge con la cantidad.
2. Abrir el lightbox desde el carrusel: arranca en la portada de ese punto.
3. Con la flecha derecha recorre **todas** las fotos de ese punto y recién después salta al primer punto siguiente. Este es el requisito central de la tarea.
4. El contador refleja la posición sobre el total del viaje.
5. La descripción muestra el caption de la imagen; si está vacío, la descripción del punto.
6. Abrir el lightbox desde el carrusel del popup del mapa: arranca en la foto que estaba visible en el popup, no en la portada.
7. Un punto sin fotos no aparece en la galería ni rompe la navegación.

- [ ] **Paso 9: Commit**

```bash
git add assets/js/trip_single.js trip.php assets/css/trip.css
git commit -m "feat(trip): el lightbox recorre la galería completa de cada punto"
```

---

### Task 11: Cierre

**Archivos:**
- Modificar: `version.php`, `CHANGELOG.md`, `ESTRUCTURA.md`, `docs/API.md`

- [ ] **Paso 1: Subir la versión**

En `version.php`, pasar de `1.0.295` a `1.0.300`, actualizando también el comentario de la primera línea. El número aparece como `?v=` en todos los assets, así que este cambio es también el cache-bust de los JS y CSS nuevos.

- [ ] **Paso 2: Entrada en el CHANGELOG**

Agregar al inicio de `CHANGELOG.md`, con el formato de las entradas existentes: galería de imágenes por POI, orden y captions desde el admin, navegación en popup y lightbox en ambos mapas, soporte en backups.

- [ ] **Paso 3: Actualizar `ESTRUCTURA.md`**

Agregar `PoiImage.php` a la tabla de modelos, `poi_images.php` a la tabla de APIs, `poi_gallery_admin.js` / `poi_lightbox.js` al árbol de `assets/js/`, `poi_gallery.css` al de `assets/css/`, y `install/verify/poi_gallery/` con una línea explicando que son scripts de verificación por CLI.

- [ ] **Paso 4: Documentar el campo nuevo en `docs/API.md`**

Agregar `images` a la respuesta de puntos de `get_all_data.php` y `get_trip.php`, aclarando que `image_url` sigue siendo la portada.

- [ ] **Paso 5: Verificación final completa**

Recorrer los diez puntos de la sección Verificación del spec. Los ítems 1 a 8 ya quedaron cubiertos por las tareas anteriores; falta confirmar de punta a punta:

```bash
C:/xampp/php/php.exe "install/verify/poi_gallery/verify_task1.php"
```

Y en el navegador, el alta de un POI vía MCP y vía importador EXIF, comprobando que la foto aparece en la galería del formulario.

- [ ] **Paso 6: Commit**

```bash
git add version.php CHANGELOG.md ESTRUCTURA.md docs/API.md
git commit -m "chore: versión 1.0.300 con galería de imágenes por POI"
```

---

## Notas para quien ejecute

- **Los scripts de verificación se commitean** en `install/verify/poi_gallery/`, cada uno con el guard de CLI de las restricciones globales. Se ejecutan siempre con `C:/xampp/php/php.exe <ruta>` desde la raíz del proyecto.
- **La sincronización de la portada es invariante.** Si en cualquier momento `points_of_interest.image_path` deja de coincidir con la primera fila de `poi_images`, hay un escritor que no pasa por `PoiImage`. El one-liner del Paso 10 de la Task 5 lo detecta.
- **`index.php` tiene dos renderers.** Todo lo que se toque para MapLibre hay que verificarlo también en Leaflet. `map_style` se cambia desde `admin/settings.php`.
- **El orden de carga de scripts importa** en la Task 9: `poi_lightbox.js` envuelve a `window.openLightbox`, así que debe cargarse *después* del renderer.
