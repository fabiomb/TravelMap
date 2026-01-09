<?php
/**
 * Migration: Add Bike transport type
 * 
 * This script adds 'bike' to the transport_type ENUM
 * and creates default color settings for it.
 */

require_once __DIR__ . '/../config/db.php';

echo "=== Migration: Add Bike Transport Type ===\n\n";

try {
    $db = getDB();
    
    // 1. Modify the ENUM to include new transport types
    echo "1. Updating routes.transport_type ENUM...\n";
    
    // Check current ENUM values
    $stmt = $db->query("SHOW COLUMNS FROM routes LIKE 'transport_type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (strpos($column['Type'], 'bike') === false) {
        $db->exec("
            ALTER TABLE routes 
            MODIFY COLUMN transport_type ENUM('plane', 'car', 'walk', 'ship', 'train', 'bike', 'bus', 'aerial') NOT NULL
        ");
        echo "   ✓ ENUM updated successfully\n";
    } else {
        echo "   - ENUM already contains bike, skipping\n";
    }
    
    // 2. Add default colors for new transport types
    echo "\n2. Adding default color settings...\n";
    
    $newSettings = [
        ['transport_color_bike', '#b88907', 'string', 'Color para rutas en motocicleta (bicicleta, motocicleta, cuatriciclos, etc.)']
    ];
    
    $insertStmt = $db->prepare("
        INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) 
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($newSettings as $setting) {
        $insertStmt->execute($setting);
        if ($insertStmt->rowCount() > 0) {
            echo "   ✓ Added setting: {$setting[0]}\n";
        } else {
            echo "   - Setting {$setting[0]} already exists, skipping\n";
        }
    }
    
    echo "\n=== Migration completed successfully! ===\n";
    
} catch (PDOException $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
