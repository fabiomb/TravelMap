-- ============================================
-- Migration: Add Bus and Aerial transport types
-- Date: 2026-01-02
-- Description: Adds 'bus' and 'aerial' (hot air balloons, zeppelins, etc.) 
--              to the transport_type ENUM in routes table
-- ============================================

-- Modify the ENUM to include new transport types
ALTER TABLE routes 
MODIFY COLUMN transport_type ENUM('plane', 'car', 'walk', 'ship', 'train', 'bus', 'aerial') NOT NULL;

-- Insert default color settings for new transport types
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('transport_color_bus', '#9C27B0', 'string', 'Color para rutas en autobús'),
('transport_color_aerial', '#E91E63', 'string', 'Color para rutas aéreas (globos, zepelines)')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
