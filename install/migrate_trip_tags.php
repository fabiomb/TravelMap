<?php
/**
 * Migration: Create trip_tags table
 * 
 * Run this script ONCE from your browser:
 * http://localhost/TravelMap/install/migrate_trip_tags.php
 * 
 * After execution, delete or protect this file.
 */

require_once __DIR__ . '/../config/db.php';

// Output configuration
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration - Trip Tags</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            border-left: 4px solid #28a745;
            margin: 15px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            border-left: 4px solid #dc3545;
            margin: 15px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 12px;
            border-radius: 4px;
            border-left: 4px solid #17a2b8;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏷️ Migration: Trip Tags</h1>
        
        <div class="info">
            <strong>ℹ️ Information:</strong> This script will create the <code>trip_tags</code> table to allow adding configurable tags to your trips.
        </div>

<?php

try {
    // Get database connection
    $conn = getDB();
    
    echo "<h3>Checking database status...</h3>";
    
    // Check if table already exists
    $stmt = $conn->query("SHOW TABLES LIKE 'trip_tags'");
    $exists = $stmt->rowCount() > 0;
    
    if ($exists) {
        echo '<div class="info">';
        echo '<strong>✓ Table <code>trip_tags</code> already exists.</strong><br>';
        echo 'Skipping table creation.';
        echo '</div>';
    } else {
        echo "<p>Creating table <code>trip_tags</code>...</p>";
        
        // Read SQL file to extract table creation
        $sqlFile = __DIR__ . '/migration_trip_tags.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("Migration SQL file not found: " . basename($sqlFile));
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Simple split to separate table creation from other possible commands if needed,
        // but for now we can just run the whole thing if the table didn't exist.
        // However, since we added the INSERT at the end, running the whole file is fine.
        // But if table exists, we still want to ensure the setting exists.
        
        $conn->exec($sql);
        
        echo '<div class="success">';
        echo '<strong>✓ Table created successfully!</strong><br>';
        echo '</div>';
    }

    echo "<h3>Checking settings...</h3>";
    // Check/Insert setting regardless of table existence
    $sqlSetting = "INSERT INTO settings (setting_key, setting_value, setting_type, description)
                   VALUES ('trip_tags_enabled', 'true', 'boolean', 'Habilitar sistema de etiquetas en los viajes')
                   ON DUPLICATE KEY UPDATE setting_value = setting_value";
    
    $conn->exec($sqlSetting);
    echo '<div class="success">';
    echo '<strong>✓ Setting <code>trip_tags_enabled</code> configuration applied.</strong>';
    echo '</div>';

    
    // Check table structure
    echo "<h3>Table Structure:</h3>";
    $columns = $conn->query("DESCRIBE trip_tags")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #eee;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><b>{$col['Field']}</b></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo '<div class="error">';
    echo '<strong>❌ Database Error:</strong><br>';
    echo htmlspecialchars($e->getMessage());
    echo '</div>';
} catch (Exception $e) {
    echo '<div class="error">';
    echo '<strong>❌ Error:</strong><br>';
    echo htmlspecialchars($e->getMessage());
    echo '</div>';
}

?>
        <p style="margin-top: 30px;">
            <a href="../index.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Go to Home</a>
        </p>
    </div>
</body>
</html>
