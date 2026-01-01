<?php
/**
 * Backup Management - TravelMap
 * 
 * Create, download, delete, and restore backups of trips, routes, points, and settings
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SECURITY: Validate authentication BEFORE any processing
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/Route.php';
require_once __DIR__ . '/../src/models/Point.php';
require_once __DIR__ . '/../src/models/Settings.php';

$db = getDB();
$tripModel = new Trip();
$routeModel = new Route();
$pointModel = new Point();
$settingsModel = new Settings($db);

// Backup directory
$backupDir = ROOT_PATH . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Get statistics
$stats = [
    'trips' => 0,
    'routes' => 0,
    'points' => 0,
    'settings' => 0,
    'images_count' => 0,
    'images_size' => 0
];

try {
    $stmt = $db->query('SELECT COUNT(*) as total FROM trips');
    $stats['trips'] = (int)$stmt->fetch()['total'];
    
    $stmt = $db->query('SELECT COUNT(*) as total FROM routes');
    $stats['routes'] = (int)$stmt->fetch()['total'];
    
    $stmt = $db->query('SELECT COUNT(*) as total FROM points_of_interest');
    $stats['points'] = (int)$stmt->fetch()['total'];
    
    $stmt = $db->query('SELECT COUNT(*) as total FROM settings');
    $stats['settings'] = (int)$stmt->fetch()['total'];
    
    // Count images
    $uploadsDir = ROOT_PATH . '/uploads/points';
    if (is_dir($uploadsDir)) {
        $images = glob($uploadsDir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        $stats['images_count'] = count($images);
        foreach ($images as $img) {
            $stats['images_size'] += filesize($img);
        }
    }
} catch (PDOException $e) {
    error_log('Backup stats error: ' . $e->getMessage());
}

// Get existing backups
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/*.{json,zip}', GLOB_BRACE);
    foreach ($files as $file) {
        $filename = basename($file);
        $backups[] = [
            'filename' => $filename,
            'path' => $file,
            'size' => filesize($file),
            'date' => filemtime($file),
            'type' => pathinfo($file, PATHINFO_EXTENSION)
        ];
    }
    // Sort by date descending
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Handle form submissions
$message = null;
$messageType = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Delete backup
    if ($action === 'delete' && isset($_POST['filename'])) {
        $filename = basename($_POST['filename']); // Security: only filename
        $filepath = $backupDir . '/' . $filename;
        
        if (file_exists($filepath) && strpos(realpath($filepath), realpath($backupDir)) === 0) {
            if (unlink($filepath)) {
                $message = __('backup.deleted_success') ?? 'Backup deleted successfully';
                $messageType = 'success';
                // Refresh backup list
                header('Location: ' . $_SERVER['PHP_SELF'] . '?deleted=1');
                exit;
            } else {
                $message = __('backup.error_deleting') ?? 'Error deleting backup';
                $messageType = 'danger';
            }
        }
    }
    
    // Create backup
    if ($action === 'create') {
        $includeTrips = isset($_POST['include_trips']);
        $includeRoutes = isset($_POST['include_routes']);
        $includePoints = isset($_POST['include_points']);
        $includeSettings = isset($_POST['include_settings']);
        $includeImages = isset($_POST['include_images']);
        $saveToServer = isset($_POST['save_to_server']);
        
        $backup = [
            'version' => '1.0',
            'exported_at' => date('c'),
            'travelmap_version' => $version ?? '1.0.0',
            'includes' => [],
            'data' => []
        ];
        
        try {
            // Export trips
            if ($includeTrips) {
                $stmt = $db->query('SELECT * FROM trips ORDER BY id');
                $backup['data']['trips'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $backup['includes'][] = 'trips';
            }
            
            // Export routes
            if ($includeRoutes) {
                $stmt = $db->query('SELECT * FROM routes ORDER BY id');
                $backup['data']['routes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $backup['includes'][] = 'routes';
            }
            
            // Export points
            if ($includePoints) {
                $stmt = $db->query('SELECT * FROM points_of_interest ORDER BY id');
                $backup['data']['points_of_interest'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $backup['includes'][] = 'points';
            }
            
            // Export settings
            if ($includeSettings) {
                $stmt = $db->query('SELECT setting_key, setting_value, setting_type, description FROM settings ORDER BY setting_key');
                $backup['data']['settings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $backup['includes'][] = 'settings';
            }
            
            $timestamp = date('Y-m-d_His');
            
            // With images - create ZIP
            if ($includeImages && $stats['images_count'] > 0) {
                $zipFilename = "backup_{$timestamp}.zip";
                $zipPath = $backupDir . '/' . $zipFilename;
                
                $zip = new ZipArchive();
                if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                    // Add JSON data
                    $zip->addFromString('data.json', json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    
                    // Add images
                    $uploadsDir = ROOT_PATH . '/uploads/points';
                    if (is_dir($uploadsDir)) {
                        $images = glob($uploadsDir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
                        foreach ($images as $img) {
                            $zip->addFile($img, 'uploads/points/' . basename($img));
                        }
                        // Add thumbs
                        $thumbsDir = $uploadsDir . '/thumbs';
                        if (is_dir($thumbsDir)) {
                            $thumbs = glob($thumbsDir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
                            foreach ($thumbs as $thumb) {
                                $zip->addFile($thumb, 'uploads/points/thumbs/' . basename($thumb));
                            }
                        }
                    }
                    
                    $zip->close();
                    
                    if ($saveToServer) {
                        $message = __('backup.created_success') ?? 'Backup created successfully';
                        $messageType = 'success';
                        header('Location: ' . $_SERVER['PHP_SELF'] . '?created=1');
                        exit;
                    } else {
                        // Download
                        header('Content-Type: application/zip');
                        header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
                        header('Content-Length: ' . filesize($zipPath));
                        readfile($zipPath);
                        unlink($zipPath); // Delete after download
                        exit;
                    }
                }
            } else {
                // JSON only
                $jsonFilename = "backup_{$timestamp}.json";
                $jsonContent = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                
                if ($saveToServer) {
                    file_put_contents($backupDir . '/' . $jsonFilename, $jsonContent);
                    $message = __('backup.created_success') ?? 'Backup created successfully';
                    $messageType = 'success';
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?created=1');
                    exit;
                } else {
                    // Download
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="' . $jsonFilename . '"');
                    header('Content-Length: ' . strlen($jsonContent));
                    echo $jsonContent;
                    exit;
                }
            }
        } catch (Exception $e) {
            $message = __('backup.error_creating') ?? 'Error creating backup: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    // Restore backup
    if ($action === 'restore') {
        $restoreMode = $_POST['restore_mode'] ?? 'merge_skip';
        $backupData = null;
        
        // From uploaded file
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['backup_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if ($ext === 'json') {
                $backupData = json_decode(file_get_contents($file['tmp_name']), true);
            } elseif ($ext === 'zip') {
                $zip = new ZipArchive();
                if ($zip->open($file['tmp_name']) === TRUE) {
                    $jsonContent = $zip->getFromName('data.json');
                    if ($jsonContent) {
                        $backupData = json_decode($jsonContent, true);
                        
                        // Extract images if present
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $name = $zip->getNameIndex($i);
                            if (strpos($name, 'uploads/points/') === 0 && substr($name, -1) !== '/') {
                                $targetPath = ROOT_PATH . '/' . $name;
                                $targetDir = dirname($targetPath);
                                if (!is_dir($targetDir)) {
                                    mkdir($targetDir, 0755, true);
                                }
                                file_put_contents($targetPath, $zip->getFromIndex($i));
                            }
                        }
                    }
                    $zip->close();
                }
            }
        }
        // From server backup
        elseif (isset($_POST['restore_filename'])) {
            $filename = basename($_POST['restore_filename']);
            $filepath = $backupDir . '/' . $filename;
            
            if (file_exists($filepath)) {
                $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
                
                if ($ext === 'json') {
                    $backupData = json_decode(file_get_contents($filepath), true);
                } elseif ($ext === 'zip') {
                    $zip = new ZipArchive();
                    if ($zip->open($filepath) === TRUE) {
                        $jsonContent = $zip->getFromName('data.json');
                        if ($jsonContent) {
                            $backupData = json_decode($jsonContent, true);
                            
                            // Extract images
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $name = $zip->getNameIndex($i);
                                if (strpos($name, 'uploads/points/') === 0 && substr($name, -1) !== '/') {
                                    $targetPath = ROOT_PATH . '/' . $name;
                                    $targetDir = dirname($targetPath);
                                    if (!is_dir($targetDir)) {
                                        mkdir($targetDir, 0755, true);
                                    }
                                    file_put_contents($targetPath, $zip->getFromIndex($i));
                                }
                            }
                        }
                        $zip->close();
                    }
                }
            }
        }
        
        if ($backupData && isset($backupData['data'])) {
            try {
                $db->beginTransaction();
                
                $imported = ['trips' => 0, 'routes' => 0, 'points' => 0, 'settings' => 0];
                $idMap = ['trips' => []];
                
                // Replace mode - clear tables first
                if ($restoreMode === 'replace') {
                    if (isset($backupData['data']['routes'])) {
                        $db->exec('DELETE FROM routes');
                    }
                    if (isset($backupData['data']['points_of_interest'])) {
                        $db->exec('DELETE FROM points_of_interest');
                    }
                    if (isset($backupData['data']['trips'])) {
                        $db->exec('DELETE FROM trips');
                    }
                    if (isset($backupData['data']['settings'])) {
                        $db->exec('DELETE FROM settings');
                    }
                }
                
                // Import trips
                if (isset($backupData['data']['trips'])) {
                    foreach ($backupData['data']['trips'] as $trip) {
                        $oldId = $trip['id'];
                        unset($trip['id'], $trip['created_at'], $trip['updated_at']);
                        
                        // Check if exists (by title and dates)
                        $stmt = $db->prepare('SELECT id FROM trips WHERE title = ? AND start_date = ? AND end_date = ?');
                        $stmt->execute([$trip['title'], $trip['start_date'] ?? null, $trip['end_date'] ?? null]);
                        $existing = $stmt->fetch();
                        
                        if ($existing) {
                            if ($restoreMode === 'merge_update') {
                                // Update existing
                                $stmt = $db->prepare('UPDATE trips SET description = ?, color_hex = ?, status = ? WHERE id = ?');
                                $stmt->execute([$trip['description'], $trip['color_hex'], $trip['status'], $existing['id']]);
                                $idMap['trips'][$oldId] = $existing['id'];
                            } else {
                                // Skip (merge_skip) or already deleted (replace)
                                $idMap['trips'][$oldId] = $existing['id'];
                            }
                        } else {
                            // Insert new
                            $stmt = $db->prepare('INSERT INTO trips (title, description, start_date, end_date, color_hex, status) VALUES (?, ?, ?, ?, ?, ?)');
                            $stmt->execute([
                                $trip['title'],
                                $trip['description'] ?? null,
                                $trip['start_date'] ?? null,
                                $trip['end_date'] ?? null,
                                $trip['color_hex'] ?? '#3388ff',
                                $trip['status'] ?? 'draft'
                            ]);
                            $idMap['trips'][$oldId] = $db->lastInsertId();
                            $imported['trips']++;
                        }
                    }
                }
                
                // Import routes (with mapped trip_id)
                if (isset($backupData['data']['routes'])) {
                    foreach ($backupData['data']['routes'] as $route) {
                        $oldTripId = $route['trip_id'];
                        $newTripId = $idMap['trips'][$oldTripId] ?? null;
                        
                        if (!$newTripId) continue;
                        
                        unset($route['id'], $route['created_at'], $route['updated_at']);
                        
                        // Check if exists
                        $stmt = $db->prepare('SELECT id FROM routes WHERE trip_id = ? AND transport_type = ? AND geojson_data = ?');
                        $stmt->execute([$newTripId, $route['transport_type'], $route['geojson_data']]);
                        $existing = $stmt->fetch();
                        
                        if (!$existing || $restoreMode === 'replace') {
                            $stmt = $db->prepare('INSERT INTO routes (trip_id, transport_type, geojson_data, color) VALUES (?, ?, ?, ?)');
                            $stmt->execute([
                                $newTripId,
                                $route['transport_type'],
                                $route['geojson_data'],
                                $route['color'] ?? '#3388ff'
                            ]);
                            $imported['routes']++;
                        }
                    }
                }
                
                // Import points (with mapped trip_id)
                if (isset($backupData['data']['points_of_interest'])) {
                    foreach ($backupData['data']['points_of_interest'] as $point) {
                        $oldTripId = $point['trip_id'];
                        $newTripId = $idMap['trips'][$oldTripId] ?? null;
                        
                        if (!$newTripId) continue;
                        
                        unset($point['id'], $point['created_at'], $point['updated_at']);
                        
                        // Check if exists
                        $stmt = $db->prepare('SELECT id FROM points_of_interest WHERE trip_id = ? AND title = ? AND latitude = ? AND longitude = ?');
                        $stmt->execute([$newTripId, $point['title'], $point['latitude'], $point['longitude']]);
                        $existing = $stmt->fetch();
                        
                        if ($existing) {
                            if ($restoreMode === 'merge_update') {
                                $stmt = $db->prepare('UPDATE points_of_interest SET description = ?, type = ?, icon = ?, image_path = ?, visit_date = ? WHERE id = ?');
                                $stmt->execute([
                                    $point['description'],
                                    $point['type'],
                                    $point['icon'] ?? 'default',
                                    $point['image_path'],
                                    $point['visit_date'],
                                    $existing['id']
                                ]);
                            }
                        } else {
                            $stmt = $db->prepare('INSERT INTO points_of_interest (trip_id, title, description, type, icon, image_path, latitude, longitude, visit_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                            $stmt->execute([
                                $newTripId,
                                $point['title'],
                                $point['description'] ?? null,
                                $point['type'],
                                $point['icon'] ?? 'default',
                                $point['image_path'] ?? null,
                                $point['latitude'],
                                $point['longitude'],
                                $point['visit_date'] ?? null
                            ]);
                            $imported['points']++;
                        }
                    }
                }
                
                // Import settings
                if (isset($backupData['data']['settings'])) {
                    foreach ($backupData['data']['settings'] as $setting) {
                        $stmt = $db->prepare('SELECT id FROM settings WHERE setting_key = ?');
                        $stmt->execute([$setting['setting_key']]);
                        $existing = $stmt->fetch();
                        
                        if ($existing) {
                            if ($restoreMode === 'merge_update' || $restoreMode === 'replace') {
                                $stmt = $db->prepare('UPDATE settings SET setting_value = ?, setting_type = ?, description = ? WHERE setting_key = ?');
                                $stmt->execute([
                                    $setting['setting_value'],
                                    $setting['setting_type'],
                                    $setting['description'],
                                    $setting['setting_key']
                                ]);
                                $imported['settings']++;
                            }
                        } else {
                            $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)');
                            $stmt->execute([
                                $setting['setting_key'],
                                $setting['setting_value'],
                                $setting['setting_type'],
                                $setting['description']
                            ]);
                            $imported['settings']++;
                        }
                    }
                }
                
                $db->commit();
                
                $summary = [];
                if ($imported['trips'] > 0) $summary[] = $imported['trips'] . ' trips';
                if ($imported['routes'] > 0) $summary[] = $imported['routes'] . ' routes';
                if ($imported['points'] > 0) $summary[] = $imported['points'] . ' points';
                if ($imported['settings'] > 0) $summary[] = $imported['settings'] . ' settings';
                
                $message = (__('backup.restored_success') ?? 'Backup restored successfully') . ': ' . implode(', ', $summary);
                $messageType = 'success';
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = (__('backup.error_restoring') ?? 'Error restoring backup') . ': ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = __('backup.invalid_file') ?? 'Invalid backup file';
            $messageType = 'danger';
        }
    }
}

// Handle GET messages
if (isset($_GET['created'])) {
    $message = __('backup.created_success') ?? 'Backup created successfully';
    $messageType = 'success';
}
if (isset($_GET['deleted'])) {
    $message = __('backup.deleted_success') ?? 'Backup deleted successfully';
    $messageType = 'success';
}

// Refresh backup list after operations
if ($message) {
    $backups = [];
    if (is_dir($backupDir)) {
        $files = glob($backupDir . '/*.{json,zip}', GLOB_BRACE);
        foreach ($files as $file) {
            $filename = basename($file);
            $backups[] = [
                'filename' => $filename,
                'path' => $file,
                'size' => filesize($file),
                'date' => filemtime($file),
                'type' => pathinfo($file, PATHINFO_EXTENSION)
            ];
        }
        usort($backups, function($a, $b) {
            return $b['date'] - $a['date'];
        });
    }
}

// Format file size
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 21.5V7M15 19C14.4102 19.6068 12.8403 22 12 22C11.1597 22 9.58984 19.6068 9 19" />
                <path d="M20.2327 11.5C21.4109 12.062 22 12.4405 22 13.0001C22 13.6934 21.0958 14.1087 19.2873 14.9395L15.8901 16.5M3.76727 11.5C2.58909 12.062 2 12.4405 2 13.0001C2 13.6934 2.90423 14.1087 4.7127 14.9395L8.1099 16.5" />
                <path d="M8.11012 10.5L4.7127 8.93936C2.90423 8.10863 2 7.69326 2 7C2 6.30674 2.90423 5.89137 4.7127 5.06064L9.60573 2.81298C10.7856 2.27099 11.3755 2 12 2C12.6245 2 13.2144 2.27099 14.3943 2.81298L19.2873 5.06064C21.0958 5.89137 22 6.30674 22 7C22 7.69326 21.0958 8.10863 19.2873 8.93937L15.8899 10.5" />
            </svg>
            <?= __('backup.title') ?? 'Backup & Restore' ?>
        </h1>
        <p class="page-subtitle"><?= __('backup.description') ?? 'Export and import your travel data' ?></p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <?php if ($messageType === 'success'): ?>
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            <?php else: ?>
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            <?php endif; ?>
        </svg>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Create Backup -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15"/>
                    <path d="M17 8L12 3L7 8"/>
                    <path d="M12 3V15"/>
                </svg>
                <?= __('backup.create_backup') ?? 'Create Backup' ?>
            </h3>
        </div>
        <div class="admin-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div style="margin-bottom: 20px;">
                    <label class="form-label" style="font-weight: 600; margin-bottom: 12px; display: block;">
                        <?= __('backup.include_data') ?? 'Include in backup:' ?>
                    </label>
                    
                    <div class="form-check" style="margin-bottom: 10px;">
                        <input type="checkbox" class="form-check-input" id="include_trips" name="include_trips" checked>
                        <label class="form-check-label" for="include_trips">
                            <strong><?= __('navigation.trips') ?></strong>
                            <span class="badge badge-info" style="margin-left: 8px;"><?= $stats['trips'] ?></span>
                        </label>
                    </div>
                    
                    <div class="form-check" style="margin-bottom: 10px;">
                        <input type="checkbox" class="form-check-input" id="include_routes" name="include_routes" checked>
                        <label class="form-check-label" for="include_routes">
                            <strong><?= __('trips.routes') ?? 'Routes' ?></strong>
                            <span class="badge badge-info" style="margin-left: 8px;"><?= $stats['routes'] ?></span>
                        </label>
                    </div>
                    
                    <div class="form-check" style="margin-bottom: 10px;">
                        <input type="checkbox" class="form-check-input" id="include_points" name="include_points" checked>
                        <label class="form-check-label" for="include_points">
                            <strong><?= __('navigation.points') ?></strong>
                            <span class="badge badge-info" style="margin-left: 8px;"><?= $stats['points'] ?></span>
                        </label>
                    </div>
                    
                    <div class="form-check" style="margin-bottom: 10px;">
                        <input type="checkbox" class="form-check-input" id="include_settings" name="include_settings">
                        <label class="form-check-label" for="include_settings">
                            <strong><?= __('navigation.settings') ?></strong>
                            <span class="badge badge-secondary" style="margin-left: 8px;"><?= $stats['settings'] ?></span>
                            <small class="text-muted d-block" style="margin-left: 24px;"><?= __('backup.settings_note') ?? 'System configuration (optional)' ?></small>
                        </label>
                    </div>
                    
                    <div class="form-check" style="margin-bottom: 10px; padding-top: 10px; border-top: 1px solid var(--admin-border);">
                        <input type="checkbox" class="form-check-input" id="include_images" name="include_images">
                        <label class="form-check-label" for="include_images">
                            <strong><?= __('backup.include_images') ?? 'Include Images' ?></strong>
                            <?php if ($stats['images_count'] > 0): ?>
                                <span class="badge badge-warning" style="margin-left: 8px;">
                                    <?= $stats['images_count'] ?> (<?= formatBytes($stats['images_size']) ?>)
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary" style="margin-left: 8px;">0</span>
                            <?php endif; ?>
                            <small class="text-muted d-block" style="margin-left: 24px;"><?= __('backup.images_note') ?? 'Creates ZIP file with images' ?></small>
                        </label>
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button type="submit" name="save_to_server" value="1" class="btn btn-secondary" style="flex: 1;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;">
                            <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                            <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                            <line x1="6" y1="6" x2="6.01" y2="6"></line>
                            <line x1="6" y1="18" x2="6.01" y2="18"></line>
                        </svg>
                        <?= __('backup.save_to_server') ?? 'Save to Server' ?>
                    </button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;">
                            <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15"/>
                            <path d="M7 10L12 15L17 10"/>
                            <path d="M12 15V3"/>
                        </svg>
                        <?= __('common.download') ?? 'Download' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Restore Backup -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 15V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V15"/>
                    <path d="M7 10L12 15L17 10"/>
                    <path d="M12 15V3"/>
                </svg>
                <?= __('backup.restore_backup') ?? 'Restore Backup' ?>
            </h3>
        </div>
        <div class="admin-card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="restore">
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="backup_file" class="form-label">
                        <?= __('backup.upload_file') ?? 'Upload Backup File' ?>
                    </label>
                    <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".json,.zip" style="padding: 12px;">
                    <small class="text-muted"><?= __('backup.accepted_formats') ?? 'Accepted formats: .json, .zip' ?></small>
                </div>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">
                        <?= __('backup.restore_mode') ?? 'Restore Mode' ?>
                    </label>
                    <div class="form-check">
                        <input type="radio" class="form-check-input" id="mode_merge_skip" name="restore_mode" value="merge_skip" checked>
                        <label class="form-check-label" for="mode_merge_skip">
                            <strong><?= __('backup.mode_merge_skip') ?? 'Merge (skip existing)' ?></strong>
                            <small class="text-muted d-block"><?= __('backup.mode_merge_skip_desc') ?? 'Import only new items, keep existing data' ?></small>
                        </label>
                    </div>
                    <div class="form-check" style="margin-top: 8px;">
                        <input type="radio" class="form-check-input" id="mode_merge_update" name="restore_mode" value="merge_update">
                        <label class="form-check-label" for="mode_merge_update">
                            <strong><?= __('backup.mode_merge_update') ?? 'Merge (update existing)' ?></strong>
                            <small class="text-muted d-block"><?= __('backup.mode_merge_update_desc') ?? 'Import new items and update existing ones' ?></small>
                        </label>
                    </div>
                    <div class="form-check" style="margin-top: 8px;">
                        <input type="radio" class="form-check-input" id="mode_replace" name="restore_mode" value="replace">
                        <label class="form-check-label" for="mode_replace">
                            <strong style="color: var(--admin-danger);"><?= __('backup.mode_replace') ?? 'Replace all' ?></strong>
                            <small class="text-muted d-block"><?= __('backup.mode_replace_desc') ?? 'Delete existing data and import from backup' ?></small>
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-warning w-100">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;">
                        <polyline points="1 4 1 10 7 10"></polyline>
                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                    </svg>
                    <?= __('backup.restore') ?? 'Restore Backup' ?>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Saved Backups -->
<div class="admin-card" style="margin-top: 24px;">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                <line x1="6" y1="6" x2="6.01" y2="6"></line>
                <line x1="6" y1="18" x2="6.01" y2="18"></line>
            </svg>
            <?= __('backup.saved_backups') ?? 'Saved Backups' ?>
        </h3>
    </div>
    <div class="admin-card-body">
        <?php if (empty($backups)): ?>
            <p class="text-muted" style="text-align: center; padding: 40px 0;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 48px; height: 48px; opacity: 0.3; margin-bottom: 12px;">
                    <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                    <polyline points="13 2 13 9 20 9"></polyline>
                </svg>
                <br>
                <?= __('backup.no_backups') ?? 'No backups saved on server yet' ?>
            </p>
        <?php else: ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?= __('common.name') ?? 'Name' ?></th>
                            <th style="width: 100px;"><?= __('backup.type') ?? 'Type' ?></th>
                            <th style="width: 100px;"><?= __('backup.size') ?? 'Size' ?></th>
                            <th style="width: 150px;"><?= __('common.date') ?? 'Date' ?></th>
                            <th style="width: 150px;"><?= __('common.actions') ?? 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td>
                                    <code style="font-size: 12px;"><?= htmlspecialchars($backup['filename']) ?></code>
                                </td>
                                <td>
                                    <?php if ($backup['type'] === 'zip'): ?>
                                        <span class="badge badge-warning">ZIP</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">JSON</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatBytes($backup['size']) ?></td>
                                <td class="cell-date"><?= date('d/m/Y H:i', $backup['date']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <!-- Download -->
                                        <a href="<?= BASE_URL ?>/api/backup_download.php?file=<?= urlencode($backup['filename']) ?>" 
                                           class="btn btn-sm btn-primary" title="<?= __('common.download') ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;">
                                                <path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15"/>
                                                <path d="M7 10L12 15L17 10"/>
                                                <path d="M12 15V3"/>
                                            </svg>
                                        </a>
                                        
                                        <!-- Restore -->
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('<?= __('backup.confirm_restore') ?? 'Are you sure you want to restore this backup?' ?>');">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="restore_filename" value="<?= htmlspecialchars($backup['filename']) ?>">
                                            <input type="hidden" name="restore_mode" value="merge_skip">
                                            <button type="submit" class="btn btn-sm btn-warning" title="<?= __('backup.restore') ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;">
                                                    <polyline points="1 4 1 10 7 10"></polyline>
                                                    <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                                                </svg>
                                            </button>
                                        </form>
                                        
                                        <!-- Delete -->
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('<?= __('backup.confirm_delete') ?? 'Are you sure you want to delete this backup?' ?>');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="filename" value="<?= htmlspecialchars($backup['filename']) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="<?= __('common.delete') ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Info Card -->
<div class="admin-card" style="margin-top: 24px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);">
    <div class="admin-card-body" style="padding: 20px;">
        <div style="display: flex; gap: 16px; align-items: flex-start;">
            <div style="flex-shrink: 0; background: #0ea5e9; color: white; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            </div>
            <div>
                <h4 style="margin: 0 0 8px 0; color: #0369a1; font-size: 15px;">
                    <?= __('backup.transfer_tip_title') ?? 'Transferring to Another Server' ?>
                </h4>
                <p style="margin: 0; color: #0c4a6e; font-size: 13px; line-height: 1.6;">
                    <?= __('backup.transfer_tip_text') ?? 'To move your data to a remote server: 1) Create a backup without settings (to keep target server config). 2) Download the backup file. 3) On the target server, upload and restore with "Merge (skip existing)" mode. For images, use the ZIP option with "Include Images" checked.' ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
