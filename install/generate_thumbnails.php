<?php
/**
 * Generate Thumbnails for Existing Images
 * 
 * Scans uploads/points folder and creates thumbnails for images that don't have one.
 * 
 * Run: php install/generate_thumbnails.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Settings.php';
require_once __DIR__ . '/../src/helpers/FileHelper.php';

echo "=== Generate Thumbnails for Existing Images ===\n\n";

// Get settings
try {
    $conn = getDB();
    $settingsModel = new Settings($conn);
    
    $thumb_width = (int)$settingsModel->get('thumbnail_max_width', 400);
    $thumb_height = (int)$settingsModel->get('thumbnail_max_height', 300);
    $thumb_quality = (int)$settingsModel->get('thumbnail_quality', 80);
    
    echo "Settings:\n";
    echo "  - Max Width: {$thumb_width}px\n";
    echo "  - Max Height: {$thumb_height}px\n";
    echo "  - Quality: {$thumb_quality}%\n\n";
    
} catch (Exception $e) {
    echo "⚠ Could not load settings, using defaults\n";
    $thumb_width = 400;
    $thumb_height = 300;
    $thumb_quality = 80;
}

// Scan uploads/points folder
$points_folder = ROOT_PATH . '/uploads/points';
$thumbs_folder = $points_folder . '/thumbs';

if (!is_dir($points_folder)) {
    echo "❌ Points folder not found: {$points_folder}\n";
    exit(1);
}

// Create thumbs folder if it doesn't exist
if (!is_dir($thumbs_folder)) {
    if (!mkdir($thumbs_folder, 0755, true)) {
        echo "❌ Could not create thumbs folder: {$thumbs_folder}\n";
        exit(1);
    }
    echo "✓ Created thumbs folder\n\n";
}

// Get all image files
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
$images = [];

$files = scandir($points_folder);
foreach ($files as $file) {
    if ($file === '.' || $file === '..' || $file === 'thumbs' || $file === '.htaccess') {
        continue;
    }
    
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, $allowed_extensions)) {
        $images[] = $file;
    }
}

$total = count($images);
echo "Found {$total} images to process\n\n";

if ($total === 0) {
    echo "No images found. Nothing to do.\n";
    exit(0);
}

$created = 0;
$skipped = 0;
$failed = 0;

foreach ($images as $index => $image) {
    $num = $index + 1;
    $source_path = $points_folder . '/' . $image;
    $thumb_path = $thumbs_folder . '/' . $image;
    
    // Skip if thumbnail already exists
    if (file_exists($thumb_path)) {
        echo "[{$num}/{$total}] ⏭ Skipped (exists): {$image}\n";
        $skipped++;
        continue;
    }
    
    // Create thumbnail
    if (FileHelper::createThumbnail($source_path, $thumb_path, $thumb_width, $thumb_height, $thumb_quality)) {
        $original_size = filesize($source_path);
        $thumb_size = filesize($thumb_path);
        $reduction = round((1 - $thumb_size / $original_size) * 100);
        echo "[{$num}/{$total}] ✓ Created: {$image} (size reduced by {$reduction}%)\n";
        $created++;
    } else {
        echo "[{$num}/{$total}] ❌ Failed: {$image}\n";
        $failed++;
    }
}

echo "\n=== Summary ===\n";
echo "Created: {$created}\n";
echo "Skipped: {$skipped}\n";
echo "Failed:  {$failed}\n";
echo "\n✅ Done!\n";
