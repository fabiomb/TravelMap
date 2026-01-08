# Sistema de Etiquetas (Tags) para Viajes

## Descripción General

El sistema de etiquetas permite categorizar y organizar viajes mediante tags personalizables. Esta funcionalidad mejora la organización, búsqueda y visualización de los viajes en toda la aplicación.

## Características Principales

### 1. Gestión de Tags en el Formulario de Viajes

**Ubicación**: Admin → Trips → Nuevo/Editar Viaje

**Funcionalidad**:
- Interfaz tipo "pill badge" para agregar/eliminar tags
- Agregar tags presionando Enter, Tab o Coma
- Eliminar tags haciendo clic en el ícono ×
- Máximo 10 tags por viaje
- Máximo 50 caracteres por tag
- Caracteres permitidos: letras, números, espacios y guiones

**Validación**:
- Los tags vacíos se ignoran automáticamente
- Los caracteres inválidos generan un error
- Tags demasiado largos o exceso de tags muestran mensajes de error
- Mensajes de error traducidos (español/inglés)

### 2. Visualización en el Panel de Administración

**Ubicación**: Admin → Trips (lista de viajes)

**Características**:
- Tags visibles como badges debajo del título del viaje
- Vista previa rápida sin necesidad de abrir el viaje
- Styling consistente con Bootstrap
- No afecta el diseño responsive de la tabla

### 3. Visualización en el Mapa Público

**Ubicación**: Mapa público → Panel lateral de viajes

**Características**:
- Tags mostrados debajo de la fecha de cada viaje
- Visible tanto en MapLibre como en Leaflet
- Layout flexible con wrap automático
- Mismo estilo que en popups (consistencia visual)

### 4. Visualización en Popups del Mapa

**Funcionalidad**:
- Tags aparecen en popups de rutas
- Tags en popups de vuelos
- Tags en popups de puntos de interés
- Implementado en ambos renderers (MapLibre y Leaflet)

## Arquitectura Técnica

### Estructura de Base de Datos

**Tabla**: `trip_tags`
```sql
CREATE TABLE trip_tags (
    trip_id INT NOT NULL,
    tag VARCHAR(100) NOT NULL,
    PRIMARY KEY (trip_id, tag),
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
);
```

**Setting**: `trip_tags_enabled` - Habilita/deshabilita la funcionalidad globalmente

### Modelo de Datos

**Archivo**: `src/models/TripTag.php`

**Métodos principales**:
- `add($trip_id, $tag)` - Agregar un tag
- `delete($trip_id, $tag)` - Eliminar un tag específico
- `getByTripId($trip_id)` - Obtener todos los tags de un viaje
- `deleteAllForTrip($trip_id)` - Eliminar todos los tags de un viaje
- `sync($trip_id, $tags)` - Sincronizar tags eficientemente

**Características del método `sync()`**:
- Compara tags existentes con nuevos
- Solo agrega tags nuevos y elimina los eliminados
- Maneja deduplicación insensible a mayúsculas/minúsculas
- Minimiza operaciones de base de datos

### Integración con API

**Archivo**: `api/get_all_data.php`

**Funcionalidad**:
- Carga tags para cada viaje
- Incluye tags en la respuesta JSON
- Formato: `"tags": ["Tag1", "Tag2"]`
- Los tags están disponibles tanto para trips como para points

### Sistema de Respaldo

**Archivo**: `admin/backup.php`

**Características**:
- La tabla `trip_tags` se incluye en respaldos
- Soporta todos los modos de restauración:
  - Merge (skip existing)
  - Merge (update existing)
  - Replace all
- Garantiza integridad de datos en migraciones

## Migración e Instalación

### Scripts de Migración

**PHP**: `install/migrate_trip_tags.php`
- Crea la tabla `trip_tags` si no existe
- Agrega el setting `trip_tags_enabled`
- Verifica datos existentes antes de modificar
- Proporciona feedback de éxito/error

**SQL**: `install/migration_trip_tags.sql`
- Versión SQL pura para ejecución manual
- Útil para ambientes de producción

### Ejecución de la Migración

```bash
# Desde el navegador
http://tu-dominio.com/install/migrate_trip_tags.php

# O ejecutar el SQL manualmente en tu base de datos
mysql -u usuario -p base_datos < install/migration_trip_tags.sql
```

## Internacionalización (i18n)

### Traducciones Disponibles

**Español** (`lang/es.json`):
- `trips.tags`: "Etiquetas"
- `trips.add_tags`: "Agregar etiquetas..."
- `trips.tags_help`: "Escribe y presiona Enter, Tab o Coma para agregar etiquetas."
- `trips.tag_too_long`: "La etiqueta es demasiado larga (máximo 50 caracteres)"
- `trips.tag_invalid_chars`: "La etiqueta contiene caracteres inválidos..."
- `trips.too_many_tags`: "Demasiadas etiquetas (máximo 10)"

**Inglés** (`lang/en.json`):
- Traducciones equivalentes en inglés

## Archivos Modificados/Creados

### Backend (PHP)
- ✅ `src/models/TripTag.php` (NUEVO)
- ✅ `admin/trip_form.php` (Modificado)
- ✅ `admin/trips.php` (Modificado)
- ✅ `admin/backup.php` (Modificado)
- ✅ `api/get_all_data.php` (Modificado)
- ✅ `install/migrate_trip_tags.php` (NUEVO)
- ✅ `install/migration_trip_tags.sql` (NUEVO)

### Frontend (JavaScript/CSS)
- ✅ `assets/js/public_map.js` (Modificado)
- ✅ `assets/js/public_map_leaflet.js` (Modificado)

### Internacionalización
- ✅ `lang/es.json` (Modificado)
- ✅ `lang/en.json` (Modificado)

## Guía de Uso

### Para Administradores

#### 1. Agregar Tags a un Viaje

1. Ir a **Admin → Trips**
2. Crear nuevo viaje o editar existente
3. En el campo "Tags", escribir el nombre del tag
4. Presionar Enter, Tab o Coma para agregar
5. Repetir para más tags
6. Guardar el viaje

#### 2. Eliminar Tags

1. En el formulario de edición de viaje
2. Hacer clic en el ícono × del tag a eliminar
3. Guardar cambios

#### 3. Ver Tags en la Lista de Viajes

- Los tags aparecen automáticamente como badges debajo del título
- Facilita identificar categorías sin abrir cada viaje

### Para Usuarios del Mapa Público

#### 1. Visualizar Tags

- **En el panel lateral**: Abrir el menú de viajes, los tags aparecen debajo de la fecha
- **En popups de rutas**: Hacer clic en una ruta del mapa
- **En popups de puntos**: Hacer clic en un marcador de punto de interés

## Reglas de Validación

| Regla | Límite | Comportamiento |
|-------|--------|----------------|
| **Longitud máxima** | 50 caracteres | Error si se excede |
| **Cantidad máxima** | 10 tags por viaje | Error si se excede |
| **Caracteres permitidos** | Letras, números, espacios, guiones | Error si hay caracteres inválidos |
| **Tags vacíos** | N/A | Se ignoran automáticamente |
| **Mayúsculas/Minúsculas** | N/A | Tratados como iguales en el modelo |

**Expresión regular de validación**:
```javascript
/^[\p{L}\p{N}\s\-]+$/u
```

## Notas de Rendimiento

### Optimizaciones Implementadas

✅ **API**: Tags cargados en una sola llamada con los datos del viaje
✅ **Frontend**: No hay llamadas adicionales, datos ya disponibles
✅ **Modelo**: Método `sync()` minimiza operaciones de DB
✅ **Caché**: Los datos se cargan una vez por sesión

### Consideraciones

⚠️ **Admin List**: Actualmente hace una query por viaje para cargar tags
💡 **Mejora futura**: Podría optimizarse con un JOIN para cargar todos los tags de una vez

## Casos de Uso Sugeridos

### Categorización por Tipo
- `Playa`, `Montaña`, `Ciudad`, `Aventura`

### Categorización por Temporada
- `Verano`, `Invierno`, `Primavera`, `Otoño`

### Categorización por Compañía
- `Familia`, `Pareja`, `Amigos`, `Solo`

### Categorización por Tema
- `Trabajo`, `Vacaciones`, `Fin de semana`, `Negocios`

### Multi-categorización
- Un viaje puede tener: `Verano`, `Playa`, `Familia`, `Vacaciones`

## Compatibilidad

- ✅ **PHP**: 7.4+
- ✅ **MySQL**: 5.7+ / MariaDB 10.2+
- ✅ **Navegadores**: Modernos con soporte para ES6+
- ✅ **MapLibre GL**: v2.x
- ✅ **Leaflet**: v1.x

## Mejoras Futuras Sugeridas

### Alta Prioridad
1. **Click en tag para filtrar**: Filtrar viajes por tag en el sidebar
2. **Tag autocomplete**: Sugerir tags existentes al escribir
3. **Unificar tags**: Modo para mergear tags similares

### Media Prioridad
4. **Estadísticas de tags**: Dashboard con tags más usados
5. **Colores por tag**: Asignar colores personalizados a tags
6. **Tag cloud**: Visualización de tags populares

### Baja Prioridad
7. **Categorías de tags**: Agrupar tags en categorías
8. **Tags jerárquicos**: Tags padre/hijo
9. **Búsqueda por tags**: Buscador avanzado en admin

## Solución de Problemas

### Los tags no aparecen en la lista de admin

**Causa**: La migración no se ejecutó correctamente

**Solución**:
```bash
# Ejecutar manualmente
http://tu-dominio.com/install/migrate_trip_tags.php
```

### Error al guardar tags

**Causa**: Validación fallida

**Solución**:
- Verificar que los tags no excedan 50 caracteres
- Verificar que no haya más de 10 tags
- Verificar que solo contengan caracteres permitidos

### Tags no aparecen en el mapa público

**Causa**: API no incluye tags en la respuesta

**Solución**:
- Verificar que `api/get_all_data.php` incluye la carga de tags
- Limpiar caché del navegador
- Verificar la consola del navegador por errores

## Soporte y Contribuciones

Para reportar bugs o sugerir mejoras relacionadas con el sistema de tags, por favor crear un issue en el repositorio del proyecto.

---

**Versión del documento**: 1.0  
**Última actualización**: Enero 2026  
**Autor**: Sistema de documentación TravelMap
