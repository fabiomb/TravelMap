-- ============================================
-- Migration: Add Distance and Statistics fields
-- Date: 2026-01-08
-- Description: Adds fields for distance calculation and statistics
-- ============================================

-- Add columns to routes table
ALTER TABLE routes 
ADD COLUMN is_round_trip TINYINT(1) DEFAULT 0 AFTER geojson_data,
ADD COLUMN distance_meters BIGINT UNSIGNED DEFAULT 0 AFTER is_round_trip;

-- Add distance unit setting
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('distance_unit', 'km', 'string', 'Unidad de distancia preferida (km para Kilómetros, mi para Millas)');
