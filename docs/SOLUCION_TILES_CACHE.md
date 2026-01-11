# Solución: Tiles no actualizados después de cambiar a CARTO

## Problema

Después de cambiar los tiles de OpenStreetMap estándar a CARTO Voyager, los mapas siguen mostrando los tiles viejos de OSM.

## Causa

El **Service Worker** y el **navegador** tienen cacheados los tiles viejos de OpenStreetMap que se descargaron anteriormente.

## Verificación

Para verificar qué tiles se están cargando:

1. Abre el mapa público (`index.php`)
2. Abre las DevTools (F12)
3. Ve a la pestaña **Network** (Red)
4. Filtra por "png" o "tile"
5. Recarga la página (F5)
6. Verifica las URLs de las imágenes que se cargan:
   - ✅ **Correcto**: `basemaps.cartocdn.com/rastertiles/voyager/`
   - ❌ **Incorrecto**: `tile.openstreetmap.org/`

## Soluciones

### Solución 1: Limpiar caché completa (RECOMENDADA)

1. **Visitar la herramienta de limpieza**:
   ```
   http://localhost/TravelMap/clear_map_cache.html
   ```

2. **Hacer clic en los botones en este orden**:
   - Primero: "⚠️ Desregistrar Service Worker"
   - Segundo: "🗑️ Limpiar Toda la Caché del Sitio"

3. **Cerrar y reabrir el navegador completamente**

4. **Visitar el mapa de nuevo**

### Solución 2: Limpieza manual del navegador

1. **Presionar**: `Ctrl + Shift + Delete` (Windows) o `Cmd + Shift + Delete` (Mac)

2. **Seleccionar**:
   - ✅ Imágenes y archivos en caché
   - ✅ Datos de sitios web (opcional pero recomendado)

3. **Rango de tiempo**: "Desde siempre" o "Todo"

4. **Borrar datos**

5. **Cerrar y reabrir el navegador**

### Solución 3: Modo Incógnito (prueba temporal)

1. Abre una ventana de incógnito: `Ctrl + Shift + N`
2. Visita el sitio
3. Si aquí se ven los tiles de CARTO, confirma que es un problema de caché
4. Procede con Solución 1 o 2

### Solución 4: Limpiar desde DevTools

1. Abre DevTools (F12)
2. Ve a **Application** > **Storage**
3. En el panel izquierdo, selecciona:
   - **Service Workers** > Click en "Unregister"
   - **Cache Storage** > Elimina todas las entradas que empiecen con `travelmap-`
4. Ve a **Network** > Click en "Disable cache" (checkbox arriba)
5. Recarga con `Ctrl + Shift + R`

## Verificación Post-Limpieza

Después de limpiar la caché:

1. Abre las DevTools (F12)
2. Ve a **Network**
3. Recarga la página
4. Busca requests de imágenes
5. Verifica que las URLs sean de CARTO:
   ```
   https://a.basemaps.cartocdn.com/rastertiles/voyager/2/1/1.png
   https://b.basemaps.cartocdn.com/rastertiles/voyager/2/2/1.png
   ```

6. **Comprueba visualmente**: Los nombres de lugares deberían verse más claros y con mejor tipografía que OSM estándar

## Características de los tiles CARTO vs OSM

### CARTO Voyager
- ✅ Mejor tipografía y diseño
- ✅ Colores más suaves
- ✅ Nombres principalmente en inglés con algunos locales
- ✅ Mejor para visualización general
- ⚠️ No cambia idioma dinámicamente (limitación de tiles raster)

### OSM Estándar
- Tipografía más básica
- Colores más fuertes
- Nombres principalmente en idioma local de cada región
- Aspecto más "tradicional"

## Prevención

Para evitar este problema en el futuro:

1. El Service Worker ahora usa **versión v3** del caché
2. La estrategia es **network-first** (red primero, caché después)
3. Cada cambio de tiles requiere incrementar la versión del SW

## Archivos Modificados

- `sw.js`: Versión del caché actualizada a `v3`
- `public_map_leaflet.js`: Ya configurado para usar CARTO
- `trip_map.js`: Ya configurado para usar CARTO
- `point_map.js`: Ya configurado para usar CARTO

## Comandos de Consola (Avanzado)

Para limpiar caché programáticamente:

```javascript
// En la consola del navegador (F12)

// 1. Desregistrar Service Worker
navigator.serviceWorker.getRegistrations().then(registrations => {
    registrations.forEach(reg => reg.unregister());
    console.log('Service Workers desregistrados');
});

// 2. Limpiar caché
caches.keys().then(names => {
    names.forEach(name => caches.delete(name));
    console.log('Caché limpiada');
});

// 3. Recargar página
setTimeout(() => location.reload(), 1000);
```

## Nota Importante

**Los tiles raster (PNG) no soportan cambio de idioma dinámico**. Aunque uses CARTO en lugar de OSM, ambos son tiles raster y no pueden cambiar el idioma de las etiquetas.

**Para soporte real de multilenguaje**, debes:
- Cambiar a **MapLibre GL** en Configuración > Mapa
- O usar un servicio con API key (Maptiler, Mapbox)

Ver: [MAPA_MULTILENGUAJE.md](MAPA_MULTILENGUAJE.md) para más información.
