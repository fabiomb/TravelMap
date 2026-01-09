# TravelMap - Diario de Viajes Interactivo V 1.0.134

Aplicación web completa para crear y visualizar mapas interactivos de viajes con puntos de interés, rutas georreferenciadas y gestión multimedia. Sistema desarrollado con tecnologías nativas sin dependencias de frameworks externos.

![TravelMap](https://github.com/fabiomb/TravelMap/blob/main/docs/travelmap.png)

## ✨ Características Principales

### Panel de Administración
- **Gestión de Viajes**: CRUD completo con título, descripción, fechas, color identificador y estado de publicación
- **Puntos de Interés**: Creación de marcadores con coordenadas, descripciones, categorías y galería de imágenes
- **Editor de Rutas**: Herramienta visual para dibujar rutas en el mapa con clasificación por tipo de transporte (coche, avión, tren, barco, pie)
- **Sistema de Autenticación**: Login seguro con sesiones, protección de rutas y gestión de usuarios
- **Mapas Interactivos**: Selección de coordenadas mediante click o arrastrar marcadores
- **Gestión Multimedia**: Subida y validación de imágenes con almacenamiento organizado
- **Panel de Configuración**: Sistema centralizado para personalizar opciones globales
  - **Sistema Multi-Idioma (i18n)**: 🌍
    - Soporte completo para múltiples idiomas (PHP y JavaScript)
    - Idiomas disponibles: Inglés (predeterminado) y Español
    - Configuración de idioma por defecto desde el panel de administración
    - Selector de idioma para usuarios en el frontend
    - Persistencia de preferencia en localStorage
    - Detección automática del idioma del navegador
    - Archivos de traducción independientes y fáciles de editar (JSON)
    - Idioma automático en MapLibre GL
  - **Estilos de Mapa Configurables**: 🗺️ **NUEVO**
    - Positron (claro, minimalista)
    - Voyager (colorido, detallado)
    - Dark Matter (modo oscuro)
    - OSM Liberty (estilo OpenStreetMap libre)
  - Tamaño máximo de carga de archivos
  - Tiempo de vida de sesiones
  - Zona horaria del sistema
  - Opciones de clustering de puntos en el mapa
  - Colores personalizados por tipo de transporte
  - **Procesamiento automático de imágenes**:
    - Redimensionamiento automático según dimensiones máximas configurables
    - Compresión JPEG con nivel de calidad ajustable
    - Preservación de transparencia en imágenes PNG
    - Optimización de peso de archivos sin pérdida visual significativa
  - **Personalización del sitio público**:
    - Título personalizado (aparece en pestaña del navegador y SEO)
    - Meta descripción para optimización en buscadores
    - Favicon personalizable
    - Integración de Google Analytics u otros scripts de análisis
- **Importador de Vuelos FlightRadar**: 🛫 
  - Importación desde archivos CSV exportados de FlightRadar/FlightDiary
  - Agrupación automática de vuelos en viajes según intervalos de tiempo
  - Vista previa antes de importar con opción de fusionar/separar viajes
  - Edición de títulos de viajes antes de importar
  - Movimiento de vuelos entre viajes
  - Base de datos de 70+ aeropuertos con coordenadas incluida
  - Creación automática de rutas con GeoJSON
- **Importador de Estadías de Airbnb**: 🏠 
  - Script para exportar viajes pasados desde Airbnb
  - Importación desde CSV con geocodificación automática
  - Vinculación automática con viajes existentes por fechas
  - Creación de puntos tipo "stay" (estadía)
- **Tags para viajes**: 🏠 
  - Organización con tags
  

### Visualizador Público
- **Mapa a Pantalla Completa**: Interfaz responsive con todos los viajes y puntos publicados
- **Renderizado WebGL de Alto Rendimiento**: 🚀 
  - Motor MapLibre GL para renderizado vectorial
  - deck.gl para arcos de vuelo animados con WebGL
  - Rendimiento optimizado para miles de puntos y rutas
- **Caché de Tiles Offline**: 📴 
  - Service Worker para cacheo automático de tiles del mapa
  - Soporte para navegación offline de áreas previamente visitadas
  - Actualización en segundo plano de tiles cacheados
- **Clustering Inteligente Configurable**: 
  - Supercluster para agrupación eficiente del lado del cliente
  - Opciones personalizables desde el panel de administración
  - Detección de inactividad para reducir uso de GPU
- **Selector de Idioma**: 🌍 Los usuarios pueden cambiar el idioma de la interfaz
- **Filtrado por Viaje**: Panel lateral con lista de viajes y filtros en tiempo real
- **Popups Detallados**: Información completa de cada punto con imágenes y descripción
- **Rutas Coloreadas Personalizables**: Visualización de trayectos diferenciados por viaje y tipo de transporte con colores configurables
- **API REST**: Endpoint JSON público para obtener todos los datos geográficos

## 🚀 Especificaciones Técnicas

### Stack Tecnológico
- **Backend**: PHP 8.x (Vanilla, sin frameworks)
  - PDO para conexión a base de datos
  - Password hashing con `password_hash()`
  - Sesiones con expiración configurada
  - Validación de tipos de archivo
- **Base de Datos**: MySQL/MariaDB
  - Foreign Keys con CASCADE
  - Almacenamiento GeoJSON para rutas
  - Índices optimizados
- **Frontend**: 
  - Bootstrap 5 (UI responsive)
  - jQuery 3.x (manipulación DOM)
  - HTML5 / CSS3
- **Mapas**: 
  - MapLibre GL JS (motor de mapas vectoriales WebGL)
  - deck.gl (overlays WebGL de alto rendimiento)
  - Supercluster (clustering eficiente)
  - Leaflet.js (motor alternativo)
  - Leaflet.draw (editor de geometrías)
  - Leaflet.markercluster (clustering Leaflet)
- **PWA / Offline**:
  - Service Worker para caché de tiles
  - Soporte offline parcial

### Arquitectura
- Patrón MVC simplificado
- Modelos: Trip, Point, Route, Settings con métodos CRUD
- Helpers: FileHelper para gestión de uploads, Language para i18n
- Configuración centralizada y dinámica desde base de datos
- Separación de código público/administrativo
- Sistema de configuraciones persistentes en base de datos

## 📋 Requisitos del Sistema

### Software Necesario
- **Servidor Web**: XAMPP, WAMP, LAMP o similar
- **PHP**: Versión 8.0 o superior
  - **Extensiones PHP Requeridas**:
    - `PDO` - Conexión a base de datos (generalmente viene activada)
    - `pdo_mysql` - Driver MySQL para PDO (generalmente viene activada)
    - `GD` - Procesamiento de imágenes (redimensionamiento y compresión)
    - `fileinfo` - Detección de tipos MIME (generalmente viene activada)
    - `curl` - Para geocodificación en importador de Airbnb
- **Base de Datos**: MySQL 5.7+ o MariaDB 10.3+
- **Navegador**: Chrome, Firefox, Safari o Edge (versión reciente con soporte WebGL)

### Verificar Extensiones PHP
Para verificar que las extensiones estén habilitadas, edita `php.ini` y asegúrate de que estas líneas estén **sin** punto y coma al inicio:
```ini
extension=gd
extension=pdo_mysql
extension=fileinfo
```

En XAMPP, el archivo `php.ini` generalmente está en:
- Windows: `C:\xampp\php\php.ini`
- Linux/Mac: `/opt/lampp/etc/php.ini`

Después de modificar `php.ini`, **reinicia Apache** para aplicar los cambios.

**Verificación rápida**: Puedes crear un archivo `info.php` con el siguiente contenido:
```php
<?php phpinfo(); ?>
```
Accédelo desde el navegador y busca las secciones "gd", "PDO" y "fileinfo".

### Librerías Locales (sin CDN)
Todas las librerías están incluidas localmente en `assets/vendor/`:
- Bootstrap 5 (CSS + JS)
- jQuery 3.7.1
- MapLibre GL JS
- deck.gl
- Supercluster
- Leaflet.js + plugins

**Nota**: Consulta [LIBRERIAS.md](LIBRERIAS.md) para instrucciones detalladas de descarga si necesitas actualizar las librerías.

## 🔧 Instalación

### 1. Clonar o Copiar el Proyecto
Coloca el proyecto en tu carpeta `htdocs` (XAMPP) o equivalente:
```
c:\xampp\htdocs\TravelMap
```

### 2. Crear la Base de Datos
- Abre phpMyAdmin o tu cliente MySQL
- Importa el archivo [database.sql](database.sql)
- Esto creará la base de datos `travelmap` con todas las tablas necesarias

### 3. Configurar la Conexión a la Base de Datos
Edita [config/db.php](config/db.php) si tus credenciales son diferentes:
```php
// Valores por defecto
'user' => 'root',
'password' => ''  // vacía
```

### 4. Ajustar la URL Base
Edita [config/config.php](config/config.php):
```php
$folder = 'TravelMap';  // Cambia si tu carpeta tiene otro nombre
```

### 5. Crear Usuario Administrador
Accede a la URL de instalación (solo una vez):
```
http://localhost/TravelMap/install/seed_admin.php
```

Esto creará el usuario administrador:
- **Usuario**: admin
- **Contraseña**: admin123

**⚠️ IMPORTANTE**: Elimina o protege la carpeta `install/` después de ejecutar este paso.

### 6. Acceder a la Aplicación

- **Panel Administrativo**: [http://localhost/TravelMap/admin/](http://localhost/TravelMap/admin/)
- **Vista Pública**: [http://localhost/TravelMap/](http://localhost/TravelMap/)

## 📖 Guía de Uso

1. Inicia sesión en el panel de administración con las credenciales creadas
2. (Opcional) Personaliza la configuración global desde el menú "Configuración"
   - **Configura el idioma por defecto del sitio** 🌍
   - **Selecciona el estilo de mapa preferido** 🗺️
   - Ajusta el tamaño máximo de carga de imágenes
   - Configura el tiempo de vida de sesiones
   - Establece tu zona horaria
   - Personaliza los colores de las rutas por tipo de transporte
   - Configura el comportamiento del clustering de puntos
   - **Personaliza el sitio público**: título, descripción, favicon y analytics
3. Crea un nuevo viaje definiendo título, descripción, fechas y color identificador
4. Agrega rutas dibujándolas directamente en el mapa y especificando el tipo de transporte
5. Añade puntos de interés con coordenadas (click en el mapa), descripción y fotos
6. Marca el viaje como "publicado" para que aparezca en el mapa público
7. Visualiza todos tus viajes en el mapa público con clustering y filtros
8. Los usuarios pueden cambiar el idioma del sitio usando el selector en el panel lateral

### Importar Vuelos desde FlightRadar 🛫

1. Exporta tu historial de vuelos desde [FlightRadar](https://my.flightradar24.com/settings/export)
2. Ve a **Admin > Importar Vuelos**
3. Sube el archivo CSV
4. Revisa la vista previa: fusiona viajes, mueve vuelos o edita títulos según necesites
5. Confirma la importación
6. Los viajes se crean como borradores para que puedas revisarlos

### Importar Estadías desde Airbnb 🏠

1. Exporta tus reservas pasadas de Airbnb (ver documentación en admin)
2. Ve a **Admin > Importar Airbnb**
3. Sube el archivo CSV
4. Los puntos se geocodifican automáticamente y se vinculan a viajes por fecha

## 🌍 Sistema Multi-Idioma (i18n)

TravelMap incluye un sistema completo de internacionalización:

### Características
- ✅ Soporte para múltiples idiomas (PHP y JavaScript)
- ✅ Idiomas disponibles: **Inglés** (predeterminado) y **Español**
- ✅ Configuración de idioma por defecto desde el panel de administración
- ✅ Selector de idioma para usuarios en el frontend
- ✅ Persistencia de preferencia en localStorage
- ✅ Detección automática del idioma del navegador
- ✅ Archivos de traducción JSON independientes y fáciles de editar

### Instalación del Sistema i18n

El sistema i18n requiere una migración de base de datos. Ver instrucciones completas en:
- **Guía de instalación**: [install/MULTILANGUAGE_INSTALLATION.md](install/MULTILANGUAGE_INSTALLATION.md)
- **Ejecutar migración**: Navegar a `install/migrate_language.php`

### Para Usuarios
- Cambiar idioma desde el selector en el panel lateral del mapa
- La preferencia se guarda automáticamente

### Para Desarrolladores
- **Documentación completa**: [docs/I18N.md](docs/I18N.md)
- **Guía rápida**: [docs/I18N_README.md](docs/I18N_README.md)
- **Agregar traducciones**: Editar archivos en `lang/`

### Agregar un Nuevo Idioma
¿Quieres contribuir traduciendo TravelMap a tu idioma? Ver [docs/I18N.md](docs/I18N.md) para instrucciones detalladas.

## 🚀 Optimizaciones de Rendimiento

### Renderizado WebGL
- MapLibre GL para renderizado vectorial eficiente
- deck.gl para arcos de vuelo animados sin impacto en rendimiento
- Detección de inactividad para reducir uso de GPU cuando no se interactúa

### Caché Inteligente
- Service Worker para cacheo de tiles del mapa
- Hasta 2000 tiles cacheados (~100MB)
- Actualización en segundo plano
- Soporte offline para áreas previamente visitadas

### Clustering Optimizado
- Supercluster para agrupación eficiente del lado del cliente
- Throttling de actualizaciones (100ms) para evitar recálculos excesivos
- Configuración de radio y zoom de deshabilitación desde admin

## 🔐 Seguridad

- Contraseñas hasheadas con algoritmo bcrypt (`password_hash()`)
- Sesiones con tiempo de expiración configurable
- Validación estricta de tipos de archivo en uploads (JPEG, PNG)
- Verificación de tipo MIME con `finfo_file()` antes de procesar imágenes
- Procesamiento automático de imágenes para optimizar tamaño y dimensiones
- Protección de rutas administrativas mediante autenticación
- Foreign Keys con restricciones CASCADE para integridad referencial
- Preparación de consultas SQL con PDO (prevención de SQL injection)

## 📁 Estructura del Proyecto

Ver [ESTRUCTURA.md](ESTRUCTURA.md) para detalles completos de la organización de carpetas y archivos.

## A futuro

* ~~Agregar traducciones en archivos de idioma para ampliar la base de usuarios~~ ✅ **IMPLEMENTADO**
* ~~Importador de vuelos desde FlightRadar~~ ✅ **IMPLEMENTADO**
* ~~Importador de estadías desde Airbnb~~ ✅ **IMPLEMENTADO**
* ~~Renderizado WebGL de alto rendimiento~~ ✅ **IMPLEMENTADO**
* ~~Caché offline de tiles~~ ✅ **IMPLEMENTADO**
* ~~Traducir completamente el panel de administración~~ ✅ **IMPLEMENTADO**
* Agregar más idiomas (Francés, Alemán, Portugués, etc.)
* ~~Permitir enlazar viajes en particular pasando parámetros~~ ✅ **IMPLEMENTADO**
* Incrustar el mapa en sitios de terceros para compartir
* Se aceptan ideas! Siempre manteniendo la simplicidad
* Crear url por viaje, sin carga de otros viajes, sólo el seleccionado, cuando id_trip está definido que muestre info completa de ese viaje

## 🤝 Contribuciones

Creado por Fabio Baccaglioni <fabiomb@gmail.com>

Contribuciones:
- [@Xyborg](https://github.com/Xyborg) - Importador FlightRadar CSV e Importador de Estadías Airbnb
- [@tucho235](https://github.com/tucho235) - Sistema de tags para los viajes

Este es un proyecto personal de código abierto. Siéntete libre de hacer fork y adaptarlo a tus necesidades.

## 📄 Licencia

GPL v3
Ver archivo [LICENSE](LICENSE) para más información.
