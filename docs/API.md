# API – TravelMap

La API pública devuelve datos en JSON. No requiere autenticación para los endpoints de lectura.

## Endpoints

### GET /api/get_all_data.php

Devuelve todos los viajes publicados con sus rutas, puntos de interés y tags.

**Respuesta**:
```json
{
  "trips": [
    {
      "id": 1,
      "title": "...",
      "description": "...",
      "start_date": "...",
      "end_date": "...",
      "color": "#FF6B6B",
      "status": "published",
      "tags": ["europa", "verano"],
      "routes": [...],
      "points": [...]
    }
  ]
}
```

#### Estructura de un punto de interés

Cada elemento de `points` incluye su galería de imágenes:

```json
{
  "id": 12,
  "title": "...",
  "description": "...",
  "type": "visit",
  "image_url": "https://.../uploads/points/foto1.jpg",
  "thumbnail_url": "https://.../uploads/points/thumbs/foto1.jpg",
  "images": [
    {
      "id": 34,
      "url": "https://.../uploads/points/foto1.jpg",
      "thumbnail_url": "https://.../uploads/points/thumbs/foto1.jpg",
      "caption": "Texto opcional de la foto"
    }
  ],
  "latitude": -34.603722,
  "longitude": -58.381592,
  "visit_date": "2026-03-14 10:30:00",
  "links": []
}
```

`images` siempre está presente y viene ordenado como lo dejó el administrador; es un arreglo vacío cuando el punto no tiene fotos. `image_url` y `thumbnail_url` apuntan a la **portada**, es decir la primera imagen de `images`, y se mantienen por compatibilidad.

---

### GET /api/get_trip.php?id={id}

Devuelve los datos de un único viaje.

**Parámetros**:
- `id` (requerido): ID del viaje

Cada punto incluye su arreglo `images` con el mismo formato descrito arriba.

---

### GET /api/get_stats.php

Devuelve estadísticas agregadas: distancia total y recuento de tramos por tipo de transporte.

**Parámetro opcional**:
- `unit`: `km` (por defecto) o `mi`

---

### GET /api/get_config.php

Devuelve la configuración pública necesaria para el cliente JavaScript (estilos de mapa, idioma, opciones de clustering, etc.).

---

## Parámetros de URL del mapa público

El mapa en `index.php` acepta parámetros por query string para controlar el estado inicial:

| Parámetro | Formato | Ejemplo | Descripción |
|---|---|---|---|
| `center` | `lat,lng` | `?center=40.41,-3.70` | Coordenadas del centro del mapa al cargar |
| `zoom` | número | `?zoom=8` | Nivel de zoom inicial (1–20) |
| `trips` | IDs separados por coma | `?trips=1,3` | Viajes visibles al cargar |
| `routes` | `0` o `1` | `?routes=1` | Mostrar/ocultar rutas |
| `points` | `0` o `1` | `?points=1` | Mostrar/ocultar puntos de interés |
| `flights` | `0` o `1` | `?flights=0` | Mostrar/ocultar rutas aéreas |

El botón **"Compartir enlace"** del panel lateral genera automáticamente una URL con el estado actual del mapa.
