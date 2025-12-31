-- Migration: Thumbnail Settings
-- Description: Adds thumbnail configuration settings for map popup images
-- Version: 1.0
-- Date: 2025-01-01

-- Insert thumbnail settings with default values
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES 
('thumbnail_max_width', '400', 'number'),
('thumbnail_max_height', '300', 'number'),
('thumbnail_quality', '80', 'number')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Note: These settings control thumbnail generation for point images
-- thumbnail_max_width: Maximum width in pixels (default: 400)
-- thumbnail_max_height: Maximum height in pixels (default: 300)
-- thumbnail_quality: JPEG quality percentage (default: 80)
