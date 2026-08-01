# Estructura del Proyecto TravelMap

## Descripción de Carpetas

```
TravelMap/
│
├── config/                  # Configuración del sistema
│   ├── config.php           # Constantes globales (BASE_URL, ROOT_PATH)
│   └── db.php               # Conexión a base de datos (PDO)
│
├── src/                     # Código fuente organizado
│   ├── models/              # Clases de modelos
│   │   ├── User.php         # Modelo de usuarios
│   │   ├── Trip.php         # Modelo de viajes
│   │   ├── Route.php        # Modelo de rutas
│   │   ├── Point.php        # Modelo de puntos de interés
│   │   └── Settings.php     # Modelo de configuración del sistema
│   └── helpers/             # Funciones auxiliares
│       ├── FileHelper.php   # Manejo de archivos e imágenes
│       └── Language.php     # Sistema de internacionalización (i18n)
│
├── admin/                   # Panel de administración
│   ├── index.php            # Dashboard principal
│   ├── trips.php            # Listado de viajes
│   ├── trip_form.php        # Formulario crear/editar viaje
│   ├── trip_edit_map.php    # Editor de mapa del viaje
│   ├── points.php           # Listado de puntos de interés
│   ├── point_form.php       # Formulario crear/editar punto
│   ├── users.php            # Listado de usuarios
│   ├── user_form.php        # Formulario crear/editar usuario
│   ├── settings.php         # Configuración del sistema
│   ├── import_airbnb.php    # Importador de reservas Airbnb
│   └── import_flights.php   # Importador de vuelos FlightRadar
│
├── api/                     # Endpoints REST para frontend
│   ├── get_all_data.php     # API para obtener datos de viajes
│   ├── get_config.php       # API para obtener configuración pública
│   ├── geocode.php          # API de geocodificación
│   └── import_airbnb_point.php  # API para importar puntos desde Airbnb
│
├── assets/                  # Recursos estáticos
│   ├── css/                 # Estilos CSS personalizados
│   │   ├── admin.css        # Estilos del panel de administración
│   │   ├── public_map.css   # Estilos del mapa público
│   │   ├── poi_gallery.css  # Carrusel y lightbox de galería (index.php y trip.php)
│   │   └── public_map_leaflet.css  # Estilos para versión Leaflet
│   ├── js/                  # JavaScript personalizado
│   │   ├── admin.js         # Scripts del panel de administración
│   │   ├── i18n.js          # Scripts de internacionalización
│   │   ├── point_map.js     # Mapa para formulario de puntos
│   │   ├── poi_gallery_admin.js  # Galería de POI en el formulario de admin
│   │   ├── poi_lightbox.js  # Lightbox con galería del mapa general
│   │   ├── public_map.js    # Mapa público (MapLibre GL + deck.gl)
│   │   ├── public_map_leaflet.js  # Mapa público (versión Leaflet)
│   │   └── trip_map.js      # Mapa para edición de viajes
│   └── vendor/              # Librerías de terceros (locales)
│       ├── bootstrap/       # Bootstrap 5 (CSS y JS)
│       ├── jquery/          # jQuery 3.7.1
│       ├── maplibre/        # MapLibre GL JS (mapas vectoriales WebGL)
│       │   ├── maplibre-gl.js
│       │   ├── maplibre-gl.css
│       │   ├── maplibre-gl-draw.js
│       │   └── maplibre-gl-draw.css
│       ├── deckgl/          # deck.gl (overlays WebGL)
│       │   └── deck.gl.min.js
│       ├── supercluster/    # Supercluster (clustering eficiente)
│       │   └── supercluster.min.js
│       └── leaflet/         # Leaflet.js y plugins
│           └── plugins/     # Plugins de Leaflet (MarkerCluster, etc.)
│
├── data/                    # Datos estáticos del sistema
│   └── airports.json        # Base de datos de aeropuertos (70+)
│
├── docs/                    # Documentación del proyecto
│   ├── README.md            # Documentación general
│   ├── CONFIGURACION.md     # Guía de configuración
│   ├── CONFIGURACION_SITIO.md   # Configuración del sitio
│   ├── CAMBIOS_CONFIGURACION.md # Historial de cambios
│   ├── I18N.md              # Documentación de internacionalización
│   ├── I18N_README.md       # Guía rápida de i18n
│   ├── INDEX_I18N.md        # Índice de traducciones
│   ├── QUICKSTART_I18N.md   # Inicio rápido i18n
│   ├── IMPLEMENTATION_SUMMARY.md  # Resumen de implementación
│   ├── PROCESAMIENTO_IMAGENES.md  # Procesamiento de imágenes
│   ├── CORRECCION_ORIENTACION_IMAGENES.md  # Corrección EXIF
│   └── travelmap.png        # Captura del proyecto
│
├── includes/                # Archivos reutilizables
│   ├── header.php           # Cabecera común
│   ├── footer.php           # Pie de página común
│   └── auth.php             # Funciones de autenticación
│
├── install/                 # Scripts de instalación y migración
│   └── verify/              # Scripts de verificación por CLI (no accesibles por HTTP)
│   ├── seed_admin.php       # Crear usuario administrador inicial
│   ├── generate_thumbnails.php      # Generar miniaturas
│   ├── migrate_language.php         # Migración de idiomas
│   ├── migrate_map_style.php        # Migración de estilos de mapa
│   ├── migrate_site_settings.php    # Migración de configuración
│   ├── migrate_image_settings.php   # Migración de ajustes de imagen
│   ├── migrate_thumbnail_settings.php  # Migración de miniaturas
│   ├── migration_settings.sql       # SQL de configuración
│   ├── migration_language.sql       # SQL de idiomas
│   ├── migration_map_style.sql      # SQL de estilos de mapa
│   ├── migration_site_settings.sql  # SQL del sitio
│   ├── migration_image_settings.sql # SQL de imágenes
│   ├── migration_thumbnail_settings.sql  # SQL de miniaturas
│   └── MULTILANGUAGE_INSTALLATION.md    # Guía de instalación multilenguaje
│
├── lang/                    # Archivos de internacionalización
│   ├── en.json              # Traducciones en inglés
│   └── es.json              # Traducciones en español
│
├── uploads/                 # Archivos subidos por usuarios
│   └── points/              # Imágenes de puntos de interés
│       └── thumbs/          # Miniaturas de imágenes
│
├── index.php                # Página pública (visualizador de mapas)
├── login.php                # Página de inicio de sesión
├── logout.php               # Cerrar sesión
├── sw.js                    # Service Worker para caché de tiles
├── version.php              # Versión actual del sistema (1.0.67)
├── database.sql             # Script SQL para crear base de datos
├── instructions.md          # Documentación del proyecto
├── ESTRUCTURA.md            # Este archivo
├── LIBRERIAS.md             # Documentación de librerías usadas
├── README.md                # Instrucciones generales
└── LICENSE                  # Licencia del proyecto
```

## Modelos (src/models/)

| Modelo | Descripción |
|--------|-------------|
| `User.php` | Gestión de usuarios y autenticación |
| `Trip.php` | Viajes con rutas y puntos de interés |
| `Route.php` | Rutas entre puntos (vuelos, trayectos) |
| `Point.php` | Puntos de interés con imágenes |
| `PoiImage.php` | Galería de imágenes de un POI; único escritor de la portada |
| `Settings.php` | Configuración global del sistema |

## Helpers (src/helpers/)

| Helper | Descripción |
|--------|-------------|
| `FileHelper.php` | Subida de archivos, procesamiento de imágenes, corrección EXIF |
| `Language.php` | Sistema de internacionalización, detección de idioma |

## APIs Disponibles

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/get_all_data.php` | GET | Obtiene todos los viajes, rutas y puntos |
| `/api/poi_images.php` | POST | Administración de la galería de un POI (upload, delete, reorder, caption) |
| `/api/get_config.php` | GET | Configuración pública del mapa |
| `/api/geocode.php` | GET | Geocodificación de direcciones |
| `/api/import_airbnb_point.php` | POST | Importa punto desde URL de Airbnb |

## Importadores (admin/)

| Archivo | Descripción |
|---------|-------------|
| `import_flights.php` | Importa vuelos desde CSV de FlightRadar/FlightDiary |
| `import_airbnb.php` | Importa estadías desde CSV de Airbnb |

## Motor de Mapas

TravelMap utiliza dos motores de mapas:

### MapLibre GL + deck.gl (Principal)
- **MapLibre GL JS**: Renderizado vectorial WebGL de alto rendimiento
- **deck.gl**: Overlays WebGL para arcos de vuelo animados
- **Supercluster**: Clustering eficiente del lado del cliente
- **Estilos disponibles**: Positron, Voyager, Dark Matter, OSM Liberty

### Leaflet (Alternativo/Respaldo)
- **Leaflet.js**: Motor de mapas raster tradicional
- **Leaflet.markercluster**: Clustering de marcadores
- **Leaflet.draw**: Editor de geometrías

## Service Worker (sw.js)

Proporciona caché de tiles del mapa para:
- Carga más rápida de tiles visitados
- Soporte offline parcial
- Actualización en segundo plano
- Límite de ~100MB / 2000 tiles

### Dominios cacheados:
- basemaps.cartocdn.com
- tiles.openfreemap.org
- api.maptiler.com
- tile.openstreetmap.org

## Datos Estáticos (data/)

| Archivo | Descripción |
|---------|-------------|
| `airports.json` | Base de datos de 70+ aeropuertos con códigos IATA y coordenadas |

## Seguridad

- **uploads/**: Debe tener permisos de escritura pero NO debe permitir ejecución de PHP
- **config/**: Debe estar protegido del acceso directo mediante .htaccess
- **src/**: Contiene código PHP que no debe ser accesible directamente desde el navegador
- **install/**: Scripts de migración que solo deben ejecutarse una vez

## Internacionalización (i18n)

El sistema soporta múltiples idiomas:
- Los archivos de traducción están en `lang/` (JSON)
- `Language.php` gestiona la detección y carga de traducciones
- `i18n.js` maneja las traducciones en el frontend
- Idiomas soportados: Español (es), Inglés (en)

## Optimizaciones de Rendimiento

- **WebGL Rendering**: MapLibre GL + deck.gl para renderizado eficiente
- **Tile Caching**: Service Worker para caché de tiles
- **Idle Detection**: Reducción de uso GPU cuando no hay interacción
- **Throttled Updates**: Actualizaciones de clusters limitadas a 100ms
- **Supercluster**: Clustering del lado del cliente para miles de puntos

## Notas

- Todas las librerías de terceros están en `assets/vendor/`
- Las imágenes subidas se guardan en `uploads/points/` con nombres únicos
- Las miniaturas se generan automáticamente en `uploads/points/thumbs/`
- El punto de entrada principal es `index.php` para el público y `admin/` para administradores
- La versión actual del sistema está definida en `version.php`
- El Service Worker (`sw.js`) maneja el caché de tiles del mapa
