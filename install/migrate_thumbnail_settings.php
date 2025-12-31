<?php
/**
 * Migration: Thumbnail Settings
 * 
 * Adds thumbnail configuration settings for faster map popup loading.
 * 
 * Run: php install/migrate_thumbnail_settings.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

echo "=== Thumbnail Settings Migration ===\n\n";

try {
    $conn = getDB();
    
    // Settings to add
    $settings = [
        ['thumbnail_max_width', '400', 'number'],
        ['thumbnail_max_height', '300', 'number'],
        ['thumbnail_quality', '80', 'number']
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_type) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_key = setting_key
    ");
    
    foreach ($settings as $setting) {
        $stmt->execute($setting);
        echo "✓ Added setting: {$setting[0]} = {$setting[1]}\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "\nThumbnail settings are now available in Admin → Settings → Image Configuration\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
