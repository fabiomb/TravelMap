<?php
/**
 * Migration 024: Routing Service Settings
 *
 * Adds settings for the optional automated routing service.
 * Supports BRouter (online/self-hosted) and Google Maps Directions API.
 */
class Migration_024_routing_service_settings
{
    public static function id(): string
    {
        return '024_routing_service_settings';
    }

    public static function description(): string
    {
        return 'Add routing service settings (BRouter, Google Maps)';
    }

    public static function check(PDO $db): bool
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
        $stmt->execute(['routing_service_enabled']);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function up(PDO $db): void
    {
        $db->exec("
            INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
            ('routing_service_enabled',    'false',                       'boolean', 'Enable automated routing service'),
            ('routing_service_type',       'brouter_online',              'string',  'Routing service type: brouter_online, brouter_custom, google_maps'),
            ('routing_brouter_url',        'https://brouter.de/brouter',  'string',  'BRouter API base URL (for custom/self-hosted instances)'),
            ('routing_google_api_key',     '',                            'string',  'Google Maps Directions API key')
        ");
    }
}
