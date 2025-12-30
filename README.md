# TravelMap - Diario de Viajes Interactivo V 1.0

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
- **Importador Flight Radar**: FlightRadar CSV import por [@Xyborg](https://github.com/Xyborg)

### Visualizador Público
- **Mapa a Pantalla Completa**: Interfaz responsive con todos los viajes y puntos publicados
- **Clustering Inteligente Configurable**: Agrupación automática de puntos cercanos con Leaflet.markercluster, con opciones personalizables desde el panel de administración
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
  - Leaflet.js (motor de mapas)
  - Leaflet.draw (editor de geometrías)
  - Leaflet.markercluster (clustering)
  - Leaflet.polylineDecorator (decoradores de rutas)

### Arquitectura
- Patrón MVC simplificado
- Modelos: Trip, Point, Route, Settings con métodos CRUD
- Helpers: FileHelper para gestión de uploads
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
- **Base de Datos**: MySQL 5.7+ o MariaDB 10.3+
- **Navegador**: Chrome, Firefox, Safari o Edge (versión reciente)

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

* Agregar traducciones en archivos de idioma para ampliar la base de usuarios
* Permitir enlazar viajes en particular pasando parámetros
* Incrustar el mapa en sitios de terceros para compartir
* Se aceptan ideas! Siempre manteniendo la simplicidad

## 🤝 Contribuciones

Creado por Fabio Baccaglioni <fabiomb@gmail.com>
Este es un proyecto personal de código abierto. Siéntete libre de hacer fork y adaptarlo a tus necesidades.

## 📄 Licencia

GPL v3
Ver archivo [LICENSE](LICENSE) para más información.
