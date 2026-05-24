-- ============================================
-- Base de Datos: TravelMap
-- Descripción: Sistema de Diario de Viajes Interactivo
-- Fecha: 2025-12-25
-- ============================================

-- Crear base de datos
-- CREATE DATABASE IF NOT EXISTS travelmap
-- CHARACTER SET utf8mb4 
-- COLLATE utf8mb4_unicode_ci;

-- USE travelmap;

-- ============================================
-- Tabla: users
-- Descripción: Almacena los usuarios del sistema
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    mcp_api_key VARCHAR(68) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    UNIQUE INDEX idx_mcp_api_key (mcp_api_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: trips
-- Descripción: Almacena información de viajes
-- ============================================
CREATE TABLE IF NOT EXISTS trips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    color_hex VARCHAR(7) DEFAULT '#3388ff',
    status ENUM('draft', 'published', 'planned') DEFAULT 'draft',
    show_routes_in_timeline TINYINT(1) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: routes
-- Descripción: Almacena las rutas de cada viaje
-- ============================================
CREATE TABLE IF NOT EXISTS routes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id INT UNSIGNED NOT NULL,
    transport_type ENUM('plane', 'car', 'bike', 'walk', 'ship', 'train', 'bus', 'aerial') NOT NULL,
    geojson_data LONGTEXT NOT NULL,
    is_round_trip TINYINT(1) DEFAULT 1,
    distance_meters INT UNSIGNED DEFAULT 0,
    color VARCHAR(7) DEFAULT '#3388ff',
    name VARCHAR(200) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    start_datetime DATETIME DEFAULT NULL,
    end_datetime DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    INDEX idx_trip_id (trip_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: points_of_interest
-- Descripción: Almacena los puntos de interés de cada viaje
-- ============================================
CREATE TABLE IF NOT EXISTS points_of_interest (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    type ENUM('stay', 'visit', 'food', 'waypoint') NOT NULL,
    icon VARCHAR(100) DEFAULT 'default',
    image_path VARCHAR(255),
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    visit_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    INDEX idx_trip_id (trip_id),
    INDEX idx_type (type),
    INDEX idx_coordinates (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: settings
-- Descripción: Almacena configuraciones del sistema
-- ============================================
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Datos iniciales para settings
-- ============================================
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('max_upload_size', '8388608', 'number', 'Tamaño máximo de carga en bytes (8MB por defecto)'),
('session_lifetime', '86400', 'number', 'Tiempo de vida de la sesión en segundos (24 horas por defecto)'),
('timezone', 'America/Argentina/Buenos_Aires', 'string', 'Zona horaria del sistema'),
('map_cluster_enabled', 'true', 'boolean', 'Habilitar clustering de puntos en el mapa público'),
('map_cluster_max_radius', '30', 'number', 'Radio máximo del cluster en píxeles'),
('map_cluster_disable_at_zoom', '15', 'number', 'Nivel de zoom donde se desactiva el clustering'),
('transport_color_plane', '#FF4444', 'string', 'Color para rutas en avión'),
('transport_color_ship', '#00AAAA', 'string', 'Color para rutas en barco'),
('transport_color_car', '#4444FF', 'string', 'Color para rutas en auto'),
('transport_color_bike', '#b88907', 'string', 'Color para rutas en motocicleta'),
('transport_color_train', '#FF8800', 'string', 'Color para rutas en tren'),
('transport_color_walk', '#44FF44', 'string', 'Color para rutas caminando'),
('transport_color_bus', '#9C27B0', 'string', 'Color para rutas en bus'),
('transport_color_aerial', '#E91E63', 'string', 'Color para rutas en teleférico/aéreo'),
('image_max_width', '1920', 'number', 'Ancho máximo de imágenes en píxeles'),
('image_max_height', '1080', 'number', 'Alto máximo de imágenes en píxeles'),
('image_quality', '85', 'number', 'Calidad de compresión JPEG (0-100)'),
('site_title', 'Travel Map - Mis Viajes por el Mundo', 'string', 'Título del sitio público'),
('site_description', 'Explora mis viajes por el mundo con mapas interactivos, rutas y fotografías', 'string', 'Descripción del sitio para SEO'),
('site_favicon', '', 'string', 'URL del favicon (ejemplo: /TravelMap/uploads/favicon.ico)'),
('site_analytics_code', '', 'string', 'Código de Google Analytics u otro script de análisis'),
('trip_tags_enabled', 'true', 'boolean', 'Habilitar sistema de etiquetas en los viajes'),
('distance_unit', 'km', 'string', 'Unidad de distancia preferida (km para Kilómetros, mi para Millas)'),
('default_language', 'en', 'string', 'Idioma por defecto del sitio (en, es, etc.)'),
('map_style', 'voyager', 'string', 'Estilo del mapa base (positron, voyager, dark-matter, osm-liberty)'),
('thumbnail_max_width', '400', 'number', 'Ancho máximo de miniaturas en píxeles'),
('thumbnail_max_height', '300', 'number', 'Alto máximo de miniaturas en píxeles'),
('thumbnail_quality', '80', 'number', 'Calidad de compresión JPEG para miniaturas (0-100)'),
('trip_timeline_show_routes', 'false', 'boolean', 'Mostrar rutas en el timeline de la página de viaje por defecto');

-- ============================================
-- Datos iniciales (opcional)
-- ============================================
-- El usuario administrador inicial se creará mediante el script seed_admin.php
-- Aquí solo definimos la estructura

-- ============================================
-- Tabla: trip_tags
-- Descripción: Almacena etiquetas configurables para los viajes
-- ============================================
CREATE TABLE IF NOT EXISTS trip_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id INT UNSIGNED NOT NULL,
    tag_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    INDEX idx_trip_tag (trip_id),
    -- Case-insensitive uniqueness enforced by collation utf8mb4_unicode_ci
    UNIQUE KEY unique_trip_tag (trip_id, tag_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: geocode_cache
-- Descripción: Cache de resultados de geocodificación inversa (Nominatim)
-- Reduce rate limiting y mejora performance
-- ============================================
CREATE TABLE IF NOT EXISTS geocode_cache (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Coordenadas (con precisión de 6 decimales = ~0.1m)
    latitude DECIMAL(10, 6) NOT NULL,
    longitude DECIMAL(11, 6) NOT NULL,
    
    -- Resultados de la búsqueda
    city VARCHAR(255) NOT NULL,
    display_name TEXT,
    country VARCHAR(255),
    
    -- Control de tiempo
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    
    -- Índices para búsquedas rápidas
    UNIQUE KEY unique_coords (latitude, longitude),
    KEY idx_expires (expires_at),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: links
-- Descripción: Links externos tipificados para entidades del sistema
--              (poi, route, trip). Tabla polimórfica — usa entity_type
--              + entity_id en lugar de FKs específicas por entidad.
-- ============================================
CREATE TABLE IF NOT EXISTS links (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('poi', 'route', 'trip') NOT NULL,
    entity_id   INT UNSIGNED NOT NULL,
    link_type   ENUM(
                    'website', 'google_maps', 'instagram', 'facebook',
                    'twitter', 'tripadvisor', 'booking', 'airbnb',
                    'youtube', 'wikipedia', 'google_photos', 'other'
                ) NOT NULL DEFAULT 'website',
    url         VARCHAR(500) NOT NULL,
    label       VARCHAR(100) DEFAULT NULL,
    sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: password_shares
-- Descripción: Contraseñas temporales para compartir acceso al mapa público
-- ============================================
CREATE TABLE IF NOT EXISTS password_shares (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    password    VARCHAR(255) NOT NULL UNIQUE,
    trips       VARCHAR(1000) NOT NULL COMMENT 'Lista de IDs de viajes separados por coma, o * para todos',
    description VARCHAR(100) NULL DEFAULT NULL COMMENT 'Descripción opcional de uso de la contraseña',
    created_at  DATE NOT NULL DEFAULT (CURRENT_DATE),
    expires_at  DATE NULL DEFAULT NULL,
    active      BOOLEAN NOT NULL DEFAULT TRUE,
    INDEX idx_expires_at (expires_at),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Tabla: schema_migrations
-- Descripción: Registro de migraciones aplicadas (gestionado por el instalador)
-- ============================================
CREATE TABLE IF NOT EXISTS schema_migrations (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration_id VARCHAR(200) NOT NULL UNIQUE,
    description  VARCHAR(500),
    applied_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_migration_id (migration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
