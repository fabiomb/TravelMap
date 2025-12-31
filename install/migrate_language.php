<?php
/**
 * Migration Script: Language Settings
 * 
 * This script adds the default_language setting to the database
 * Run this once to enable the i18n system
 */

// Load configuration
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Settings.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// HTML Header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TravelMap - Language Migration</title>
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
        .step {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌍 TravelMap - Language System Migration</h1>
        
        <div class="info">
            <strong>ℹ️ Information:</strong> This migration adds support for multi-language functionality to TravelMap.
        </div>

<?php

try {
    // Get database connection
    $conn = getDB();
    $settings = new Settings($conn);
    
    echo '<div class="step">';
    echo '<h3>Step 1: Checking database connection</h3>';
    echo '<div class="success">✓ Database connection successful</div>';
    echo '</div>';
    
    echo '<div class="step">';
    echo '<h3>Step 2: Adding default_language setting</h3>';
    
    // Check if setting already exists
    $existing = $settings->get('default_language');
    
    if ($existing !== null) {
        echo '<div class="info">⚠️ Setting <code>default_language</code> already exists with value: <strong>' . htmlspecialchars($existing) . '</strong></div>';
        echo '<p>Updating to ensure correct configuration...</p>';
    }
    
    // Insert or update the setting
    $result = $settings->set(
        'default_language',
        'en',
        'string',
        'Default language for the site (en, es, etc.)'
    );
    
    if ($result) {
        echo '<div class="success">✓ Successfully configured <code>default_language</code> setting</div>';
    } else {
        throw new Exception('Failed to set default_language configuration');
    }
    
    echo '</div>';
    
    echo '<div class="step">';
    echo '<h3>Step 3: Verifying configuration</h3>';
    
    $defaultLang = $settings->get('default_language');
    
    if ($defaultLang === 'en') {
        echo '<div class="success">✓ Configuration verified successfully</div>';
        echo '<p>Default language is set to: <strong>English (en)</strong></p>';
    } else {
        throw new Exception('Configuration verification failed');
    }
    
    echo '</div>';
    
    echo '<div class="step">';
    echo '<h3>✅ Migration Completed Successfully!</h3>';
    echo '<p>The i18n system is now ready to use. You can:</p>';
    echo '<ul>';
    echo '<li>Change the default language from <strong>Admin → Settings</strong></li>';
    echo '<li>Users can select their preferred language from the map interface</li>';
    echo '<li>Add new languages by creating files in the <code>lang/</code> directory</li>';
    echo '</ul>';
    echo '<p><strong>Available languages:</strong></p>';
    echo '<ul>';
    echo '<li>🇬🇧 English (en) - Default</li>';
    echo '<li>🇪🇸 Spanish (es)</li>';
    echo '</ul>';
    echo '<a href="../admin/settings.php" class="button">Go to Settings</a> ';
    echo '<a href="../index.php" class="button">View Map</a>';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="step">';
    echo '<div class="error">';
    echo '<strong>❌ Migration Error:</strong><br>';
    echo htmlspecialchars($e->getMessage());
    echo '</div>';
    echo '<p><strong>Troubleshooting:</strong></p>';
    echo '<ul>';
    echo '<li>Verify database connection settings in <code>config/db.php</code></li>';
    echo '<li>Check that the <code>settings</code> table exists</li>';
    echo '<li>Ensure the database user has INSERT/UPDATE permissions</li>';
    echo '<li>Review error logs for more details</li>';
    echo '</ul>';
    echo '</div>';
}

?>

        <hr style="margin: 30px 0;">
        
        <div class="info">
            <strong>📚 Next Steps:</strong>
            <ul>
                <li>Read <code>docs/I18N.md</code> for complete documentation</li>
                <li>Read <code>docs/I18N_README.md</code> for a quick start guide</li>
                <li>Test the language selector in the map interface</li>
                <li>Consider translating admin panel pages</li>
            </ul>
        </div>
    </div>
</body>
</html>
