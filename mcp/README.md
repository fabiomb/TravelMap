# TravelMap MCP Server

Servidor MCP para TravelMap. Permite usar un LLM (Claude Desktop, Claude Code, Cursor, etc.)
para operar viajes, rutas y POIs en lenguaje natural.

Documentación completa: [`docs/MCP.md`](../docs/MCP.md)

---

## Transportes

| Modo | Archivo | Autenticación |
|---|---|---|
| stdio (local) | `mcp/server.php` | ninguna — proceso local |
| HTTP (remoto) | `mcp/http.php` | Bearer API Key o sesión web |

---

## Configuración rápida

### stdio — Claude Code (proyecto local)

`.mcp.json` en la raíz del proyecto:

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

### HTTP — cliente remoto

```json
{
  "mcpServers": {
    "travelmap": {
      "url": "https://tudominio.com/mcp/http.php",
      "headers": { "Authorization": "Bearer tmk_..." }
    }
  }
}
```

La API Key se genera desde **Admin → Usuarios → editar → card "Acceso MCP"**.

---

## Tools disponibles (15)

### Viajes
| Tool | Descripción |
|---|---|
| `list_trips` | Lista viajes con filtros opcionales |
| `search_trips` | Busca por texto, tag o rango de fechas |
| `get_trip` | Viaje completo con rutas, POIs, tags y links |
| `create_trip` | Crea un nuevo viaje |
| `update_trip` | Actualiza campos, tags y links |

### Rutas
| Tool | Descripción |
|---|---|
| `plan_route` | Calcula ruta via BRouter y guarda temporal |
| `commit_route` | Persiste la ruta de `plan_route` en BD |
| `create_route` | Crea ruta desde coordenadas estructuradas |
| `update_route` | Actualiza metadatos de una ruta |

### POIs
| Tool | Descripción |
|---|---|
| `search_pois` | Busca POIs por texto, viaje o tipo |
| `create_poi` | Crea un POI; puede consumir `photo_token` y auto-rellenar coords/fecha desde EXIF |
| `update_poi` | Actualiza datos de un POI; puede reemplazar foto con `photo_token` |
| `inspect_uploaded_photo` | Inspecciona metadata EXIF de una foto temporal |
| `cleanup_uploaded_photo` | Elimina una foto temporal no consumida |

### Localización
| Tool | Descripción |
|---|---|
| `search_location` | Geocodifica un lugar por nombre via Nominatim |

---

## Requisitos

- PHP ≥ 8.0 con extensiones: `pdo_mysql`, `gd`, `fileinfo`, `exif`, `curl`
- MySQL / MariaDB con el schema de TravelMap instalado (incluida migración 024)
- Permisos de escritura en `uploads/` y `logs/`

---

## Tests

```bash
bash mcp/tests/run_tests.sh

# Sin limpiar registros creados:
bash mcp/tests/run_tests.sh --keep-data
```

---

## Logs

```bash
tail -f logs/mcp.log
```

---

## Estructura

```
mcp/
├── server.php          Entrada stdio
├── http.php            Entrada HTTP
├── bootstrap.php       Config, DB, helpers, modelos
├── Dispatcher.php      Registro y enrutado de tools
├── JsonRpc.php         Framing NDJSON (stdio)
├── Logger.php          logs/mcp.log
├── Schema.php          Validador JSON Schema
└── tools/
    ├── TripTools.php       5 tools de viajes
    ├── RouteTools.php      4 tools de rutas
    ├── PoiTools.php        3 tools de POIs
    └── LocationTools.php   1 tool de geocodificación
```
