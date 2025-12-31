-- Migration: Add default_language setting
-- Description: Adds the default_language configuration to the settings table
-- Date: 2025-12-30

-- Insert default language setting (English as default)
INSERT INTO settings (setting_key, setting_value, setting_type, description)
VALUES (
    'default_language',
    'en',
    'string',
    'Default language for the site (en, es, etc.)'
)
ON DUPLICATE KEY UPDATE
    setting_value = 'en',
    setting_type = 'string',
    description = 'Default language for the site (en, es, etc.)';

-- Verify the insertion
SELECT * FROM settings WHERE setting_key = 'default_language';
