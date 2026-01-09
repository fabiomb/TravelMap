-- ============================================
-- Migration: Add Bike transport type
-- Date: 2026-01-08
-- Description: Adds 'bike' to the transport_type ENUM in routes table
-- ============================================

-- Modify the ENUM to include new transport types
ALTER TABLE routes 
MODIFY COLUMN transport_type ENUM('plane', 'car', 'walk', 'ship', 'train', 'bike', 'bus', 'aerial') NOT NULL;

-- Insert default color settings for new transport types
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('transport_color_bike', '#b88907', 'string', 'Color para rutas en motocicleta (bicicleta, motocicleta, cuatriciclos, etc.)')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
