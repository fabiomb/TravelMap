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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
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
    status ENUM('draft', 'published') DEFAULT 'draft',
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
    transport_type ENUM('plane', 'car', 'walk', 'ship', 'train', 'bus', 'aerial') NOT NULL,
    geojson_data LONGTEXT NOT NULL,
    color VARCHAR(7) DEFAULT '#3388ff',
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
    type ENUM('stay', 'visit', 'food') NOT NULL,
    icon VARCHAR(100) DEFAULT 'default',
    image_path VARCHAR(255),
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    visit_date DATE,
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
('transport_color_train', '#FF8800', 'string', 'Color para rutas en tren'),
('transport_color_walk', '#44FF44', 'string', 'Color para rutas caminando'),
('transport_color_bus', '#9C27B0', 'string', 'Color para rutas en bus'),
('transport_color_aerial', '#E91E63', 'string', 'Color para rutas en teleférico/aéreo'),
('transport_color_bus', '#9C27B0', 'string', 'Color para rutas en autobús'),
('transport_color_aerial', '#E91E63', 'string', 'Color para rutas aéreas (globos, zepelines)'),
('image_max_width', '1920', 'number', 'Ancho máximo de imágenes en píxeles'),
('image_max_height', '1080', 'number', 'Alto máximo de imágenes en píxeles'),
('image_quality', '85', 'number', 'Calidad de compresión JPEG (0-100)'),
('site_title', 'Travel Map - Mis Viajes por el Mundo', 'string', 'Título del sitio público'),
('site_description', 'Explora mis viajes por el mundo con mapas interactivos, rutas y fotografías', 'string', 'Descripción del sitio para SEO'),
('site_favicon', '', 'string', 'URL del favicon (ejemplo: /TravelMap/uploads/favicon.ico)'),
('site_analytics_code', '', 'string', 'Código de Google Analytics u otro script de análisis');

-- ============================================
-- Datos iniciales (opcional)
-- ============================================
-- El usuario administrador inicial se creará mediante el script seed_admin.php
-- Aquí solo definimos la estructura
