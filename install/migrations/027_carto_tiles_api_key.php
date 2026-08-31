<?php
/**
 * Migration 027: CARTO Tiles API Key
 *
 * CARTO now requires an API key to serve its basemap tiles.
 * Adds the setting that every Leaflet map appends to the CARTO tile URLs.
 */
class Migration_027_carto_tiles_api_key
{
    public static function id(): string
    {
        return '027_carto_tiles_api_key';
    }

    public static function description(): string
    {
        return 'Add CARTO basemap tiles API key setting';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
        $stmt->execute(['map_tiles_api_key']);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
            ('map_tiles_api_key', '', 'string', 'CARTO basemaps API key (required by the CARTO map styles)')
        ");
    }
}
