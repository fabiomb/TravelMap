# Galería de imágenes por punto de interés

- **Fecha:** 2026-08-01
- **Versión base:** 1.0.295
- **Estado:** diseño aprobado

## Problema

Cada punto de interés (POI) admite una sola imagen, almacenada en
`points_of_interest.image_path`. Para publicar más de una foto de un mismo lugar,
el usuario debe recurrir a links externos hacia redes sociales o galerías online.
El requerimiento es tener la galería completa dentro de TravelMap.

## Alcance

- Administración: agregar, ordenar, describir y borrar varias imágenes por POI.
- Mapa general (`index.php`): navegación entre imágenes en el popup y en el lightbox.
- Mapa de viaje (`trip.php`): el lightbox recorre la galería completa de un POI
  antes de pasar al siguiente punto.

Fuera de alcance: galerías para rutas (`routes.image_path`) y para viajes.

## Decisiones

| Decisión | Elegida | Descartada |
|---|---|---|
| Modelo de datos | Tabla `poi_images` + `image_path` como espejo de portada | Eliminar `image_path`; columna JSON |
| Texto por imagen | `caption` opcional por imagen | Heredar título y descripción del POI |
| Carga en admin | AJAX inmediato + drag & drop | POST del formulario; híbrido |
| Alcance del lightbox en `index.php` | Sólo la galería del POI abierto | Toda la galería del viaje |

### Por qué `image_path` sobrevive como espejo

Nueve archivos leen `points_of_interest.image_path`: `api/get_all_data.php`,
`api/get_trip.php`, `api/save_poi.php`, `api/import_exif_save_point.php`,
`mcp/tools/PoiTools.php`, `admin/points.php`, `admin/backup.php`, `trip.php` y
`src/models/Point.php`.

La verificación previa confirmó que **todos los escritores de POI pasan por
`Point::create()` o `Point::update()`** con `$data['image_path']`; ninguno emite
SQL directo. Concentrar la sincronización en el modelo deja a MCP, al importador
EXIF y a `save_poi` funcionando sin modificarlos.

El costo asumido es una columna denormalizada. Se acota manteniendo un único
punto de escritura: `PoiImage::syncCover()`.

## Modelo de datos

Migración `install/migrations/026_poi_images_table.php`:

```sql
CREATE TABLE IF NOT EXISTS poi_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    poi_id     INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    caption    VARCHAR(255) DEFAULT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (poi_id) REFERENCES points_of_interest(id) ON DELETE CASCADE,
    INDEX idx_poi_sort (poi_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Backfill en la misma migración: cada POI con `image_path` no vacío genera una
fila con `sort_order = 0` y `caption = NULL`. `database.sql` se actualiza con la
tabla nueva.

Los thumbnails se siguen derivando por convención mediante
`FileHelper::getThumbnailPath()`; no se almacenan en la base.

## Modelo `src/models/PoiImage.php`

| Método | Responsabilidad |
|---|---|
| `getByPoiId($poiId)` | Imágenes de un POI ordenadas por `sort_order`, `id`. |
| `getByTripId($tripId)` | Todas las imágenes del viaje agrupadas por `poi_id`, en una sola consulta. Evita N+1 en las APIs. |
| `add($poiId, $imagePath)` | Inserta con `sort_order = MAX + 1`. |
| `delete($imageId)` | Borra la fila y el archivo con `FileHelper::deleteFile()`. |
| `updateCaption($imageId, $caption)` | Actualiza el texto de una imagen. |
| `reorder($poiId, array $imageIds)` | Reasigna `sort_order` según el orden recibido. Ignora ids que no pertenezcan al POI. |
| `syncCover($poiId)` | Escribe en `points_of_interest.image_path` la imagen de menor `sort_order`, o `NULL` si no queda ninguna. |
| `countByPoiId($poiId)` | Cantidad de imágenes, para badges en el admin. |

`syncCover()` se invoca al final de `add()`, `delete()` y `reorder()`. Es el
único lugar del sistema que escribe la columna espejo.

### Cambios en `src/models/Point.php`

1. `create()` y `update()`: si `$data['image_path']` no está vacío y el POI no
   tiene filas en `poi_images`, se inserta la fila correspondiente. Esto cubre a
   MCP, al importador EXIF y a `save_poi` sin tocarlos.
2. `delete()`: el `ON DELETE CASCADE` elimina las filas pero **no los archivos**.
   Antes del `DELETE` hay que recolectar los `image_path` de `poi_images` y
   borrarlos con `FileHelper::deleteFile()`, que ya se ocupa del thumbnail.

## Endpoint de administración

`api/poi_images.php`, un único archivo con despacho por `action`:

| `action` | Entrada | Salida |
|---|---|---|
| `upload` | `poi_id`, archivo `image` (multipart) | `{ id, url, thumbnail_url, sort_order }` |
| `delete` | `image_id` | `{ success, cover_url }` |
| `reorder` | `poi_id`, `image_ids[]` | `{ success, cover_url }` |
| `caption` | `image_id`, `caption` | `{ success }` |

Reglas comunes: `require_auth()` al inicio, validación de que la imagen
pertenece al POI indicado, respuesta JSON y códigos HTTP coherentes con el resto
de `api/`.

Se eligió un endpoint con `action` en lugar de cuatro archivos porque el caption
también se guarda de forma inmediata (on-blur); separarlo en el POST del
formulario reintroduce la inconsistencia que se descartó en la opción híbrida.

## Formulario de administración (`admin/point_form.php`)

- Grilla de miniaturas reordenables por drag & drop con la HTML5 Drag and Drop
  API. Sin librerías nuevas.
- Cada miniatura: caption inline (guardado on-blur), botón de borrado con
  confirmación, e indicador visual de portada en la primera posición.
- Subida por drag & drop o selector, de a un archivo por request, con barra de
  progreso. Así se esquiva el límite de `post_max_size` de PHP, que con
  `MAX_UPLOAD_SIZE` en 8 MB por imagen se agota con pocas fotos.
- En alta de POI la sección se muestra deshabilitada con la indicación de guardar
  primero el punto. El formulario ya redirige a `point_form.php?id=X` al crear.
- El bloque actual de imagen única y su lógica de reemplazo se retiran: subir una
  imagen nueva ahora **agrega** en vez de reemplazar.

`admin/points.php` muestra la cantidad de imágenes por POI.

## API pública

`api/get_all_data.php`, `api/get_trip.php` y el bloque de datos de `trip.php`
agregan a cada punto:

```json
"images": [
  { "id": 12, "url": "...", "thumbnail_url": "...", "caption": "..." }
]
```

`image_url` y `thumbnail_url` se mantienen apuntando a la portada. Ningún
consumidor actual se rompe. Las tres vistas usan `PoiImage::getByTripId()` para
resolver las imágenes de un viaje en una consulta.

## Backups (`admin/backup.php`)

`admin/backup.php` no vuelca la base entera: enumera las tablas explícitamente y,
al restaurar, reinserta con listas de columnas fijas remapeando los IDs nuevos.
Sin intervenir este archivo, los backups perderían las galerías sin avisar.

- **Export:** incluir `poi_images` en `$backup['data']`, condicionado a que la
  tabla exista (mismo patrón defensivo que ya usa con `links` frente a las
  tablas legacy `poi_links` / `route_links`).
- **Restore:** insertar las filas remapeando `poi_id` al id nuevo del POI,
  usando el `$idMap['points']` que el archivo ya construye.
- **Archivos:** no requieren cambios. El modo ZIP opcional ya empaqueta
  `uploads/points` completo con un `glob()` del directorio, no fila por fila, así
  que las imágenes de galería entran solas.
- El contador de estadísticas de la pantalla suma las imágenes de galería.

Los backups viejos, sin la clave `poi_images`, se restauran igual: los POI
entran con su `image_path` y `Point::create()` genera la fila espejo.

## Front-end compartido (`assets/js/map-renderer.js`)

`createPoiPopup()` renderiza un carrusel cuando `images.length > 1`:

- flechas de navegación superpuestas sobre la imagen;
- puntos indicadores debajo de la imagen, uno por foto, con el activo destacado;
- con una sola imagen el popup se ve igual que hoy.

El popup se inyecta como cadena HTML, por lo que la navegación no puede depender
de `onclick` inline con estado. Se resuelve con delegación de eventos sobre
`document` y un registro `window.__poiGalleries[poiId] = images` poblado al
construir el popup.

El click sobre la imagen abre el lightbox en el índice visible.

Los estilos van en `assets/css/public_map.css`, junto al resto de `.point-popup`.

## Mapa general (`index.php`)

- Función nueva `openPoiGallery(poiId, index)`.
- `openLightbox(url, title)` se mantiene sin cambios: la usan las imágenes de
  rutas, que no tienen galería. No se introducen firmas duales.
- El lightbox suma botones anterior y siguiente, contador `3 / 8` y navegación
  por teclado con flechas izquierda y derecha.
- El recorrido se limita a la galería del POI abierto, con vuelta circular.

## Mapa de viaje (`trip.php` y `assets/js/trip_single.js`)

- El carrusel lateral sigue mostrando **una tarjeta por POI**, con la portada
  (`sort_order` más bajo), más un badge con la cantidad cuando hay más de una
  imagen.
- `initGallery()` deja de leer el DOM y se construye desde `TRIP_DATA`:
  puntos ordenados por `visit_date` y, dentro de cada punto, imágenes ordenadas
  por `sort_order`. Ese arreglo aplanado es el que recorre `changeImage(±1)`,
  de modo que la navegación agota la galería de un POI antes de pasar al
  siguiente.
- `viewImageFromData()` abre el lightbox en el índice de la portada del POI.
- El título del lightbox muestra el título del POI; la descripción muestra el
  caption de la imagen y, si está vacío, la descripción del POI.

## Internacionalización

Claves nuevas en `lang/en.json` y `lang/es.json` para el admin (agregar fotos,
arrastrar para ordenar, portada, caption, confirmación de borrado, contador de
fotos) y para el front (contador del lightbox, navegación). Ningún texto queda
fijo en el código.

## Verificación

El proyecto no cuenta con suite de tests: no hay `composer.json`, ni PHPUnit, ni
carpeta de tests. Montar esa infraestructura excede esta feature y se trata como
trabajo aparte. La verificación de este cambio es manual y reproducible:

1. Migración aplicada sobre datos existentes: cada POI que tenía imagen queda con
   exactamente una fila en `poi_images` y su `image_path` sin alterar.
2. POI con 0, 1 y N imágenes: popup, lightbox y carrusel se comportan según lo
   especificado en cada caso.
3. Reordenar la galería actualiza la portada en el mapa tras recargar.
4. Borrar la portada promueve la siguiente imagen y actualiza `image_path`.
5. Borrar un POI completo no deja archivos huérfanos en `uploads/points/` ni en
   `uploads/points/thumbs/`.
6. Alta de POI vía MCP (`PoiTools`) y vía importador EXIF: se crea la fila espejo
   en `poi_images` y la imagen aparece en la galería.
7. `api/get_all_data.php` y `api/get_trip.php` siguen devolviendo `image_url` y
   `thumbnail_url` con la portada.
8. Verificación de permisos: los cuatro `action` de `api/poi_images.php` rechazan
   peticiones sin sesión y rechazan `image_id` que no pertenezca al `poi_id`.
9. Ciclo completo de backup: exportar un viaje con galerías, restaurar en modo
   `replace` y confirmar que cada POI recupera sus imágenes en el mismo orden.
10. Restaurar un backup anterior a este cambio: los POI entran con su imagen y la
    galería queda con una sola foto.

## Riesgos

- **Columna espejo desincronizada.** Mitigado concentrando la escritura en
  `PoiImage::syncCover()` y cubriendo los escritores externos desde
  `Point::create/update`.
- **Archivos huérfanos.** El `CASCADE` no borra archivos; `Point::delete()` debe
  recolectar los paths antes del `DELETE`. Está en el punto 5 de verificación.
- **Popups como cadena HTML.** La delegación de eventos es la única vía viable;
  registrar las galerías en `window.__poiGalleries` obliga a limpiar el registro
  cuando se recargan los datos del mapa.
