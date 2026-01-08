-- Migration: Create trip_tags table
-- Description: Creates the table for storing trip tags
-- Date: 2026-01-06

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

-- Add setting to enable/disable trip tags
INSERT INTO settings (setting_key, setting_value, setting_type, description)
VALUES ('trip_tags_enabled', 'true', 'boolean', 'Habilitar sistema de etiquetas en los viajes')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
