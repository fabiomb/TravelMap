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
│   └── import_flights.php   # Importador de vuelos
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
│   │   └── public_map.css   # Estilos del mapa público
│   ├── js/                  # JavaScript personalizado
│   │   ├── admin.js         # Scripts del panel de administración
│   │   ├── i18n.js          # Scripts de internacionalización
│   │   ├── point_map.js     # Mapa para formulario de puntos
│   │   ├── public_map.js    # Mapa público principal
│   │   └── trip_map.js      # Mapa para edición de viajes
│   └── vendor/              # Librerías de terceros (locales)
│       ├── bootstrap/       # Bootstrap 5 (CSS y JS)
│       ├── jquery/          # jQuery 3.7.1
│       └── leaflet/         # Leaflet.js y plugins
│           └── plugins/     # Plugins de Leaflet (MarkerCluster, etc.)
│
├── data/                    # Datos estáticos del sistema
│   └── airports.json        # Base de datos de aeropuertos
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
│   ├── seed_admin.php       # Crear usuario administrador inicial
│   ├── generate_thumbnails.php      # Generar miniaturas
│   ├── migrate_language.php         # Migración de idiomas
│   ├── migrate_site_settings.php    # Migración de configuración
│   ├── migrate_image_settings.php   # Migración de ajustes de imagen
│   ├── migrate_thumbnail_settings.php  # Migración de miniaturas
│   ├── migration_settings.sql       # SQL de configuración
│   ├── migration_language.sql       # SQL de idiomas
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
├── version.php              # Versión actual del sistema
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
| `/api/get_config.php` | GET | Configuración pública del mapa |
| `/api/geocode.php` | GET | Geocodificación de direcciones |
| `/api/import_airbnb_point.php` | POST | Importa punto desde URL de Airbnb |

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

## Notas

- Todas las librerías de terceros están en `assets/vendor/`
- Las imágenes subidas se guardan en `uploads/points/` con nombres únicos
- Las miniaturas se generan automáticamente en `uploads/points/thumbs/`
- El punto de entrada principal es `index.php` para el público y `admin/` para administradores
- La versión actual del sistema está definida en `version.php`