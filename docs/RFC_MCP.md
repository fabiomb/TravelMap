# RFC: Servidor MCP para TravelMap

**Estado:** Borrador  
**Fecha:** 2026-04-24  
**Autor:** Victor Rosset

---

## 1. Motivación

TravelMap es una aplicación web PHP para registrar viajes con rutas y puntos de interés. El objetivo de este RFC es incorporar un servidor MCP (*Model Context Protocol*) que permita a cualquier LLM compatible operar sobre los datos de la aplicación en lenguaje natural, sin exponer la base de datos directamente ni requerir que el usuario opere la interfaz web.

El servidor debe ser operable tanto en local (durante desarrollo o desde Claude Code) como en remoto (desde Claude Desktop u otros clientes), usando el mismo conjunto de herramientas.

---

## 2. Elección de tecnología: PHP

### 2.1 Contexto

El stack actual de TravelMap es PHP 8 + PDO/MySQL sobre Apache. La evaluación considera principalmente PHP frente a Python, dado que Python es el lenguaje con mayor adopción en el ecosistema MCP.

### 2.2 Argumentos a favor de PHP

**Reutilización directa del stack existente.** El servidor MCP puede importar los modelos (`Trip`, `Route`, `Point`), helpers (`BRouterClient`, `ExifExtractor`, `Geocoder`, `FileHelper`) y la conexión PDO sin ninguna capa de adaptación. Una implementación en Python requeriría reimplementar esta lógica o invocarla como subproceso, añadiendo complejidad y puntos de fallo.

**Sin dependencias adicionales en producción.** El servidor PHP corre bajo el mismo Apache y la misma instalación de PHP que la web. Un proceso Python requeriría instalar y mantener un runtime separado (`venv`, dependencias pip, versión de Python) en el servidor, con su ciclo de actualizaciones propio.

**Simplicidad del protocolo.** MCP 2024-11-05 es JSON-RPC 2.0 transportado por stdin/stdout o HTTP. PHP maneja ambos sin librerías externas. La ausencia de un SDK oficial de PHP para MCP no supone un obstáculo real dado que el protocolo es suficientemente simple.

**Rendimiento adecuado.** Las herramientas MCP son mayoritariamente operaciones sobre BD (SELECT, INSERT, UPDATE). PHP 8+ tiene rendimiento más que suficiente para esta carga. No hay cómputo intensivo ni streaming de datos de alta frecuencia.

### 2.3 Desventaja reconocida

Python dispone de SDK oficial de Anthropic para MCP, al igual que TypeScript. Esto daría acceso a tipado de esquemas más rico y a actualizaciones automáticas del protocolo. Este punto se acepta como deuda técnica conocida: si el protocolo MCP evoluciona significativamente, habrá que actualizar el framing manualmente.

### 2.4 Decisión

Se elige PHP. El beneficio de mantener un único lenguaje y stack en el proyecto supera el coste de no disponer de SDK oficial.

---

## 3. Transporte

El servidor expone el mismo conjunto de 15 herramientas a través de dos transportes independientes. El modo de transporte se selecciona por punto de entrada, no por configuración en tiempo de ejecución.

### 3.1 stdio (local)

| Aspecto | Detalle |
|---|---|
| Punto de entrada | `mcp/server.php` |
| Protocolo | NDJSON por stdin/stdout (una línea JSON por mensaje) |
| Invocación | Proceso hijo lanzado por el cliente MCP |
| Autenticación | Ninguna (ver §4) |
| Caso de uso | Claude Code en desarrollo local, acceso desde el mismo servidor |

Configuración en `.mcp.json`:
```json
{
  "mcpServers": {
    "travelmap": {
      "command": "php",
      "args": ["mcp/server.php"]
    }
  }
}
```

### 3.2 HTTP (remoto)

| Aspecto | Detalle |
|---|---|
| Punto de entrada | `mcp/http.php` |
| Protocolo | HTTP POST, `Content-Type: application/json`, cuerpo JSON-RPC |
| Autenticación | Bearer token (ver §4) |
| Caso de uso | Claude Desktop, Cursor, Windsurf u otros clientes en máquina remota |

Configuración en el cliente:
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

Los dos transportes comparten el mismo `Dispatcher` y el mismo conjunto de tools. No hay comportamiento diferente a nivel de herramienta según el transporte usado.

---

## 4. Autenticación

### 4.1 stdio — sin autenticación

El transporte stdio no implementa autenticación. La capa de seguridad es el propio sistema operativo: solo puede iniciar el proceso quien tenga acceso al sistema de archivos del servidor y permiso de ejecución de PHP. Esto es equivalente al modelo de seguridad de cualquier CLI.

### 4.2 HTTP — API Key (Bearer token)

El transporte HTTP requiere autenticación explícita. Se propone un esquema de API Key estática por usuario, evaluado mediante el header HTTP estándar `Authorization: Bearer <key>`.

**Almacenamiento.** Cada usuario de la aplicación puede tener asociada una API Key almacenada en la columna `users.mcp_api_key`. La columna admite `NULL` (usuario sin acceso MCP habilitado).

```sql
ALTER TABLE users
  ADD COLUMN mcp_api_key VARCHAR(68) DEFAULT NULL,
  ADD UNIQUE INDEX idx_mcp_api_key (mcp_api_key);
```

**Formato de la key.** Prefijo fijo `tmk_` seguido de 64 caracteres hexadecimales generados con `random_bytes(32)`. Longitud total: 68 caracteres. El prefijo permite identificar visualmente el tipo de token y facilita el escaneo en repositorios (`tmk_` como patrón de búsqueda en herramientas de detección de secretos).

**Generación y rotación.** La key se genera y regenera desde la interfaz de administración (Admin → Usuarios → editar usuario). Regenerar invalida la key anterior de forma inmediata. No hay expiración automática.

**Flujo de validación:**
1. El servidor extrae el valor del header `Authorization`.
2. Si el header no está presente o no tiene el formato `Bearer <token>`, se devuelve HTTP 401.
3. Se busca el token en `users.mcp_api_key` mediante consulta parametrizada.
4. Si no existe, HTTP 401. Si existe, la petición se procesa con los permisos del usuario asociado.

**Capa de fallback — sesión web.** Si la petición no incluye header Bearer pero sí una cookie `PHPSESSID` con sesión de administrador activa, se permite el acceso. Esto facilita el uso desde herramientas embebidas en el navegador sin requerir configuración adicional.

---

## 5. Herramientas (Tools)

El servidor expone 15 herramientas agrupadas en cuatro categorías. Todas aceptan y devuelven JSON. El esquema de cada herramienta se valida antes de ejecutar la operación; los errores de validación se devuelven como JSON-RPC error con código `-32602`.

### 5.1 Viajes

| Tool | Descripción |
|---|---|
| `list_trips` | Lista viajes con filtros opcionales de estado y orden |
| `search_trips` | Búsqueda por texto libre, tag o rango de fechas |
| `get_trip` | Devuelve un viaje completo con rutas, POIs, tags y links |
| `create_trip` | Crea un viaje nuevo; devuelve el `id` generado |
| `update_trip` | Actualiza campos individuales; tags y links se reemplazan completos |

### 5.2 Rutas

| Tool | Descripción |
|---|---|
| `plan_route` | Calcula una ruta terrestre via BRouter y guarda el GeoJSON en disco temporal. Devuelve metadata (distancia, duración, `temp_path`) sin pasar la geometría por el contexto del LLM |
| `commit_route` | Persiste en BD la ruta calculada por `plan_route` leyendo el archivo temporal; lo elimina tras persistir |
| `create_route` | Crea una ruta desde una lista estructurada de coordenadas; no acepta blobs, CSV ni GeoJSON crudo en JSON-RPC |
| `update_route` | Actualiza metadatos de una ruta; la geometría no puede modificarse |

El flujo `plan_route` → `commit_route` existe para evitar que geometrías de cientos de kilobytes transiten por el contexto del modelo. El GeoJSON se guarda en `uploads/mcp_temp/` y el LLM solo recibe el path relativo. Para importaciones externas, `create_route` queda limitado a coordenadas estructuradas y acotadas; los blobs deben procesarse fuera del contexto del modelo antes de invocar la tool.

### 5.3 Puntos de interés (POIs)

| Tool | Descripción |
|---|---|
| `search_pois` | Busca POIs por texto, viaje o tipo; sin filtros lista todos los POIs de un viaje |
| `create_poi` | Crea un POI; si recibe `photo_token`, consume la foto temporal y puede tomar coordenadas y fecha desde EXIF |
| `update_poi` | Actualiza datos de un POI; puede reemplazar la foto con `photo_token` |
| `inspect_uploaded_photo` | Inspecciona EXIF y ubicación sugerida para una foto temporal sin crear un POI |
| `cleanup_uploaded_photo` | Descarta una foto temporal no consumida |

### 5.4 Localización

| Tool | Descripción |
|---|---|
| `search_location` | Geocodifica un nombre de lugar via Nominatim (OpenStreetMap); devuelve hasta 5 candidatos con coordenadas |

---

## 6. Seguridad

### 6.1 Validación de entrada

Cada herramienta declara un JSON Schema. El validador (`Schema.php`) verifica tipos, longitudes, valores de enum y patrones antes de invocar la lógica de negocio. Ningún valor del LLM llega a una consulta SQL sin pasar por esta capa.

### 6.2 Consultas parametrizadas

Todas las operaciones sobre BD usan PDO con *prepared statements* y *bind parameters*. No existe interpolación de valores de usuario en SQL.

### 6.3 Path traversal

La herramienta `commit_route` valida que `temp_path` resuelva dentro de `uploads/mcp_temp/` antes de leer el archivo. Cualquier intento de acceder a rutas fuera de ese directorio devuelve error `INVALID_PATH`.

### 6.4 Subida de archivos

Los archivos no se envían como base64 dentro de JSON-RPC. Las fotos se suben por `mcp/upload.php` usando `multipart/form-data`; el endpoint valida tamaño, tipo real con `fileinfo`/`GD`, extensión permitida y EXIF, guarda el archivo en un área temporal y devuelve un `photo_token`. Las tools de POI reciben solo ese token y lo consumen para mover la imagen al almacenamiento final.

### 6.5 Transporte HTTP

Se recomienda exponer `mcp/http.php` exclusivamente sobre HTTPS. Sin TLS el Bearer token viaja en claro. El `.htaccess` del directorio `mcp/` puede forzar redirección a HTTPS o denegar acceso HTTP plano según la política del despliegue.

### 6.6 Logs

El servidor registra todas las invocaciones en `logs/mcp.log`. Los binarios no pasan por JSON-RPC y los payloads grandes se loguean solo por tamaño, nunca con el contenido completo, para evitar que datos sensibles o fotos persistan en logs.

---

## 7. Lo que queda fuera de este RFC

- Rate limiting en el endpoint HTTP (a evaluar si el servidor queda expuesto públicamente).
- Soporte SSE (Server-Sent Events) para streaming de respuestas largas.
- Permisos granulares por usuario (actualmente cualquier usuario con API Key tiene acceso completo a todas las herramientas).
- Revocación masiva de keys (actualmente solo por regeneración individual).
