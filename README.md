# TravelMap

Aplicación web para crear y visualizar mapas interactivos de viajes con puntos de interés, rutas georreferenciadas y gestión multimedia.

![TravelMap](docs/travelmap.png)

## Características

**Panel de administración**
- Gestión de viajes (título, descripción, fechas, color, tags, estado de publicación)
- Puntos de interés con coordenadas, descripción, galería de imágenes y links externos tipificados
- Editor de rutas visual con clasificación por tipo de transporte
- Generación automática de rutas entre dos puntos (BRouter / Google Maps, opcional)
- Gestión de usuarios y configuración global del sitio
- Importadores: vuelos (FlightRadar CSV), estadías (Airbnb CSV), rutas (BRouter, GPX/OpenRailRouting), imágenes con geolocalización EXIF

**Vista pública**
- Mapa a pantalla completa con renderizado WebGL (MapLibre GL + deck.gl)
- Clustering configurable, filtrado por viaje y por tag
- Página individual por viaje con galería y timeline
- Caché offline de tiles via Service Worker
- Selector de idioma (Inglés / Español)
- URL compartible con estado del mapa
- Posibilidad de restringir el acceso mediante contraseñas.

## Requisitos

- PHP 8.0+ con extensiones: `pdo_mysql`, `gd`, `fileinfo`, `curl`
- MySQL 5.7+ o MariaDB 10.3+
- Navegador con soporte WebGL

## Instalación

Ver [docs/INSTALACION.md](docs/INSTALACION.md) para el procedimiento completo.

### Manual

1. Clonar o copiar el proyecto
2. Crear la base de datos (`database.sql`)
3. Copiar `config/config.example.php` → `config/config.php` y `config/db.example.php` → `config/db.php`
4. Acceder a `http://localhost/TravelMap/install/` y seguir el asistente
5. Eliminar o proteger la carpeta `install/` al terminar

### Automática

1. Acceder a `http://localhost/TravelMap/install/` y seguir el asistente
2. Eliminar o proteger la carpeta `install/` al terminar

## Documentación

| Documento | Descripción |
|---|---|
| [docs/INSTALACION.md](docs/INSTALACION.md) | Instalación y actualización |
| [docs/CONFIGURACION.md](docs/CONFIGURACION.md) | Opciones del panel de administración |
| [docs/IMPORTADORES.md](docs/IMPORTADORES.md) | Guía de importadores disponibles |
| [docs/I18N.md](docs/I18N.md) | Sistema multi-idioma y cómo agregar idiomas |
| [docs/API.md](docs/API.md) | Endpoints de la API pública |
| [CHANGELOG.md](CHANGELOG.md) | Historial de cambios |
| [ESTRUCTURA.md](ESTRUCTURA.md) | Estructura de carpetas y archivos |

## Contribuciones

Creado por Fabio Baccaglioni [@fabiomb](https://github.com/fabiomb/)

- [@Xyborg](https://github.com/Xyborg)
- [@tucho235](https://github.com/tucho235)
- [@herver1971](https://github.com/herver1971)
- [@GermanXander](https://github.com/GermanXander)

## Licencia

GPL v3 — ver [LICENSE](LICENSE)
