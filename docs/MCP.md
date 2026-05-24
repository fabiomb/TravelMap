# MCP Server – TravelMap

El servidor MCP de TravelMap expone las operaciones de datos (viajes, rutas, POIs) como tools
invocables por cualquier cliente compatible con el protocolo MCP 2024-11-05.

---

## Transportes

El servidor tiene dos modos de operación independientes con el mismo conjunto de tools.

### stdio — acceso local

Ejecuta `mcp/server.php` como proceso hijo del cliente. La comunicación es NDJSON por stdin/stdout.
No requiere autenticación — la seguridad proviene del acceso al sistema de archivos.

Configuración en `.mcp.json` (raíz del proyecto):

```json
{
  "mcpServers": {
    "travelmap": {
      "command": "php",
      "args": ["mcp/server.php"],
      "env": {
        "DB_HOST": "127.0.0.1",
        "DB_PORT": "32306"
      }
    }
  }
}
```

Las variables `DB_HOST` y `DB_PORT` permiten apuntar a la BD de Docker desde el host. Sin ellas
el servidor intenta conectar al host `db` (nombre del servicio dentro de Docker).

### HTTP — acceso remoto

`mcp/http.php` es un endpoint POST que acepta JSON-RPC sobre HTTP. Sirve para clientes remotos
(otra máquina, Claude Desktop, Cursor, etc.).

```
POST https://tudominio.com/mcp/http.php
Content-Type: application/json
Authorization: Bearer <api-key>
```

El endpoint rechaza cuerpos mayores a 15 MB antes de leer el JSON. CORS está restringido por defecto
a same-origin; para permitir orígenes de navegador adicionales, definir `MCP_ALLOWED_ORIGINS` como
lista separada por comas (por ejemplo `https://cliente.example`). Usar `*` solo en entornos confiables.

---

## Autenticación (HTTP)

El transporte HTTP valida la identidad en dos capas, evaluadas en orden:

### Capa A — API Key estática (Bearer token)

Cada usuario puede tener una API Key permanente almacenada en `users.mcp_api_key`.
Formato: `tmk_` seguido de 64 caracteres hexadecimales (68 chars total).

Se genera y gestiona desde **Admin → Usuarios → editar usuario → card "Acceso MCP"**.
La key no expira; regenerarla invalida la clave existente de forma inmediata.

Uso en el cliente:
```json
{
  "mcpServers": {
    "travelmap": {
      "url": "https://tudominio.com/mcp/http.php",
      "headers": { "Authorization": "Bearer tmk_abc123..." }
    }
  }
}
```

### Capa B — Sesión web (cookie)

Si la petición no incluye Bearer token pero sí una cookie `PHPSESSID` válida de una sesión
admin activa, se permite el acceso. Útil desde herramientas embebidas en el navegador o
devtools, sin necesidad de configurar una key por separado.

---

## Configuración por cliente

| Cliente | Archivo de configuración | Propiedad URL |
|---|---|---|
| Claude Desktop (macOS) | `~/Library/Application Support/Claude/claude_desktop_config.json` | `url` |
| Claude Desktop (Windows) | `%APPDATA%\Claude\claude_desktop_config.json` | `url` |
| Claude Code | `.mcp.json` (proyecto) · `~/.claude/mcp.json` (global) | `url` |
| Cursor | `~/.cursor/mcp.json` · `.cursor/mcp.json` | `url` |
| Windsurf | `~/.codeium/windsurf/mcp_config.json` | `serverUrl` |
| Antigravity | ver `antigravity.google/docs/mcp` | `serverUrl` |
| JetBrains AI | Settings → Tools → AI Assistant → Model Context Protocol | `url` |

---

## Gestión de la API Key

Desde la interfaz de administración:

1. Ir a **Admin → Usuarios** y editar un usuario.
2. En la card **Acceso MCP**, pulsar **Generar API Key** (si no tiene) o **Regenerar API Key**.
3. Copiar la key con el botón de portapapeles — se muestra solo una vez sin necesidad de guardar.
4. Pegarla en el campo `Authorization` del cliente MCP.

La card también muestra el JSON de ejemplo listo para copiar, con un selector de cliente
(Claude, Cursor, Windsurf, Antigravity, JetBrains, Genérico) que ajusta la propiedad correcta.

---

## Migración de base de datos

La columna `mcp_api_key` se agrega mediante la migración **024**, ejecutable desde
`http://tudominio.com/install/` en la sección de migraciones pendientes.

```sql
-- Lo que aplica la migración 024:
ALTER TABLE users
  ADD COLUMN mcp_api_key VARCHAR(68) DEFAULT NULL,
  ADD UNIQUE INDEX idx_mcp_api_key (mcp_api_key);
```

---

## Tools disponibles

### Viajes — `TripTools`

| Tool | Descripción | Params requeridos | Params opcionales |
|---|---|---|---|
| `list_trips` | Lista viajes almacenados | — | `status`, `limit` (1-200), `order` |
| `search_trips` | Busca por texto, tag o rango de fechas | — | `query`, `tag`, `date_from`, `date_to`, `status`, `limit` |
| `get_trip` | Viaje completo con rutas, POIs, tags y links | `id` | `include_geojson` (bool) |
| `create_trip` | Crea un viaje nuevo; devuelve el id | `title` | `description`, `start_date`, `end_date`, `color_hex`, `status`, `show_routes_in_timeline`, `tags[]` |
| `update_trip` | Actualiza campos del viaje. Tags y links se reemplazan completos si se incluyen | `id` | `title`, `description`, `start_date`, `end_date`, `color_hex`, `status`, `show_routes_in_timeline`, `tags[]`, `links[]` |

### Rutas — `RouteTools`

| Tool | Descripción | Params requeridos | Params opcionales |
|---|---|---|---|
| `plan_route` | Calcula ruta terrestre via BRouter y guarda un temporal. Usar `commit_route` para persistir. Transportes: `car`, `bike`, `walk`, `train`, `bus` | `from_lat`, `from_lon`, `to_lat`, `to_lon`, `transport_type` | `via[]` (máx 8 waypoints intermedios) |
| `commit_route` | Persiste la ruta calculada por `plan_route`; elimina el temporal | `trip_id`, `temp_path`, `transport_type` | `name`, `description`, `is_round_trip`, `color`, `start_datetime`, `end_datetime`, `links[]` |
| `create_route` | Crea una ruta desde una lista estructurada de coordenadas. No acepta blobs, CSV ni GeoJSON crudo en JSON-RPC | `trip_id`, `transport_type`, `coordinates[]` | `name`, `description`, `is_round_trip`, `color`, `start_datetime`, `end_datetime`, `links[]` |
| `update_route` | Actualiza metadatos de una ruta (no la geometría). Links se reemplazan completos si se incluyen | `id` | `name`, `description`, `transport_type`, `color`, `is_round_trip`, `start_datetime`, `end_datetime`, `links[]` |

Transportes válidos en `update_route`: `plane`, `car`, `bike`, `walk`, `ship`, `train`, `bus`, `aerial`.

### POIs — `PoiTools`

| Tool | Descripción | Params requeridos | Params opcionales |
|---|---|---|---|
| `search_pois` | Busca POIs por texto, viaje o tipo — cruza todos los viajes | — | `query`, `trip_id`, `type` (`stay`/`visit`/`food`), `limit` |
| `create_poi` | Crea un POI. Con `photo_token`, auto-rellena coords y fecha desde EXIF. Devuelve `suggested_place` (Nominatim) | `trip_id`, `type` | `title`, `latitude`, `longitude`, `description`, `icon`, `visit_date`, `photo_token`, `links[]` |
| `update_poi` | Actualiza datos de un POI. Puede reemplazar la foto con `photo_token` | `id` | `title`, `type`, `latitude`, `longitude`, `description`, `icon`, `visit_date`, `photo_token`, `links[]` |
| `inspect_uploaded_photo` | Inspecciona una foto subida por `mcp/upload.php` sin crear ni actualizar POIs | `photo_token` | — |
| `cleanup_uploaded_photo` | Elimina una foto temporal subida por `mcp/upload.php` | `photo_token` | — |

### Localización — `LocationTools`

| Tool | Descripción | Params requeridos | Params opcionales |
|---|---|---|---|
| `search_location` | Busca coordenadas de un lugar por nombre via Nominatim. Devuelve hasta 5 candidatos con lat/lng | `query` (mín 2 chars) | `limit` (1-10, default 5) |

---

## Flujos de uso típicos

### Crear un viaje con ruta calculada

```
1. create_trip   → title, dates, status
2. plan_route    → from/to coords, transport_type  →  devuelve temp_path
3. commit_route  → trip_id, temp_path              →  ruta guardada en BD
```

### Crear un viaje con ruta importada (GPX/BRouter)

```
1. create_trip   → title, dates, status
2. convertir archivo externo fuera del contexto del LLM
3. create_route  → trip_id, transport_type, coordinates[]
```

### Añadir POI con foto

```
1. POST /mcp/upload.php  → multipart/form-data, campo photo  →  photo_token + EXIF
2. inspect_uploaded_photo → photo_token  →  metadata EXIF y suggested_place
3. create_poi            → trip_id, type, photo_token
                         →  usa coords/fecha EXIF si no se envían manualmente
4. update_poi            → id, title  (si se quiere ajustar)
```

---

## Logs

El servidor escribe en `logs/mcp.log`. Los uploads binarios no viajan por JSON-RPC:
se suben por multipart a `mcp/upload.php` y las tools reciben solo tokens temporales.
Los payloads grandes se loguean por tamaño, nunca con el contenido completo.

```bash
tail -f logs/mcp.log
```

---

## Tests

```bash
# Requiere: php
bash mcp/tests/run_tests.sh

# Sin limpiar los registros creados:
bash mcp/tests/run_tests.sh --keep-data
```

Los tests verifican el handshake, la presencia de todas las tools, operaciones CRUD básicas
y casos de seguridad (token de foto inválido, trip inexistente, JSON malformado).

---

## Estructura de archivos

```
mcp/
├── server.php          Entrada stdio
├── http.php            Entrada HTTP (remoto)
├── upload.php          Upload multipart de fotos; devuelve photo_token
├── UploadedFiles.php   Gestión de uploads temporales y consumo por POIs
├── bootstrap.php       Carga config, DB, helpers y modelos
├── Dispatcher.php      Registro y enrutado de tools
├── JsonRpc.php         Framing NDJSON para stdio
├── Logger.php          Escritura en logs/mcp.log
├── Schema.php          Validador JSON Schema
└── tools/
    ├── TripTools.php       list_trips, search_trips, get_trip, create_trip, update_trip
    ├── RouteTools.php      plan_route, commit_route, create_route, update_route
    ├── PoiTools.php        search_pois, create_poi, update_poi, inspect_uploaded_photo, cleanup_uploaded_photo
    └── LocationTools.php   search_location
```
