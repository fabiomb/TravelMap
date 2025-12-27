-- ============================================
-- Base de Datos: TravelMap
-- Descripción: Sistema de Diario de Viajes Interactivo
-- Fecha: 2025-12-25
-- ============================================

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS travelmap
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE travelmap;

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
    status ENUM('public', 'draft', 'planned') DEFAULT 'draft',
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
    transport_type ENUM('plane', 'car', 'walk', 'ship', 'train') NOT NULL,
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
-- Datos iniciales (opcional)
-- ============================================
-- El usuario administrador inicial se creará mediante el script seed_admin.php
-- Aquí solo definimos la estructura
