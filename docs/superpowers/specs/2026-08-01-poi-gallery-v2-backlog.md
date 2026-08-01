# Galería de POI — pendientes para una v2

Hallazgos detectados durante la implementación de la galería de imágenes por punto
de interés que **no** se atendieron en esta versión, por decisión explícita: son
menores, de bajo impacto real, o casos borde que no justifican el costo de
resolverlos ahora.

Ninguno rompe funcionalidad para el uso normal. Están ordenados por lo que más
conviene mirar primero si algún día se retoma.

## Vale la pena revisar

**`MigrationRunner::expectedSchema()` no incluye `poi_images`.**
`validateSchema()` no va a detectar drift en esa tabla. No genera falsos
positivos, sólo silencio: si a la tabla le falta una columna, el instalador no
avisa.

**`ensureGalleryRow()` descarta el retorno de `PoiImage::add()`.**
En `src/models/Point.php`. Si `add()` falla en silencio —captura su propia
`PDOException` y devuelve `false`—, `create()` y `update()` igual reportan éxito
aunque no se haya creado la fila espejo. Sigue la convención de manejo de errores
del proyecto, así que cambiarlo implica decidir si esa convención cambia.

## Casos borde de concurrencia en el admin

El reorden de la galería usa confirmación pesimista: la grilla se bloquea
mientras hay un `reorder` en vuelo y el movimiento visual se aplica sólo cuando
el servidor confirma. Eso elimina el solapamiento de reorders. Quedan dos huecos
angostos:

**Subir y borrar no se bloquean durante un reorden.**
Fue una decisión deliberada y está verificada: `applyOrder()` en el cliente
agrega al final los ids que no vinieron en la lista, igual que hace el tail loop
de `PoiImage::reorder()` en `src/models/PoiImage.php`. Cliente y servidor
convergen en cualquier orden de llegada. Se documenta por si en el futuro cambia
alguno de los dos lados: **si se toca `PoiImage::reorder()`, hay que revisar
`applyOrder()` en el mismo commit.**

**El porcentaje de la barra de progreso puede saltar hacia atrás.**
Si entra una segunda tanda de fotos mientras la primera se está subiendo,
`queueTotal` se recalcula y el número puede ir de 80% a 40%. Cosmético: la barra
igual llega a 100% y se esconde bien.

## Scripts de verificación

Viven en `install/verify/poi_gallery/`, se ejecutan sólo por CLI y crean sus
propios datos de prueba, que borran en un `finally`.

**La aserción "la imagen ajena no cambió de POI" en `verify_task2.php` es
tautológica.** `reorder()` no tiene ninguna vía para escribir `poi_id`, así que
la comprobación no puede fallar. El filtro PHP que descarta ids ajenos queda sin
cobertura real — aunque su rotura tampoco tendría consecuencia funcional, porque
el SQL ya acota por `poi_id`.

**La creación del fixture en `verify_task2.php` está fuera del `try/finally`.**
Si falla a mitad, deja el viaje `_verify_trip` huérfano en la base. No toca datos
reales; sólo deja basura propia.

**`verify_task3.php` no imprime un resumen agregado.** A diferencia de
`verify_task2.php`, no lleva contador de fallos ni línea final de PASS/FAIL: hay
que leer las líneas `SI` una por una.

**Interpolación de `$id` en SQL crudo** en `Point::delete()` y en
`verify_task3.php`. No es explotable —`$id` es un entero de origen interno— pero
produciría SQL inválido en vez de un error claro si alguna vez llegara vacío.

## Cosmético

**`idx_poi_sort` no es `UNIQUE`.** El orden dentro de un POI se garantiza sólo a
nivel aplicación, en `PoiImage::reorder()`. Un `sort_order` duplicado no rompe
nada: el desempate por `id` deja el orden estable.

**La grilla del admin no tiene cursor de arrastre activo ni hueco de destino.**
Durante el reorden se pinta una guía de inserción, que alcanza, pero no hay
`:active` ni placeholder.

## Condición preexistente, ajena a este trabajo

**187 de 307 `image_path` apuntan a archivos que no están en disco.** El conteo
es idéntico en `points_of_interest.image_path` y en `poi_images`, así que la
migración no lo introdujo: la instalación local simplemente no tiene todos los
archivos de `uploads/`. No se tocó nada al respecto.
