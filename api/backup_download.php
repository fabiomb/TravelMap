<?php
/**
 * Backup Download API
 * 
 * Securely download backup files with authentication
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SECURITY: Require authentication
require_auth();

$filename = isset($_GET['file']) ? basename($_GET['file']) : '';
$backupDir = ROOT_PATH . '/backups';
$filepath = $backupDir . '/' . $filename;

// Validate file exists and is within backup directory
if (empty($filename) || !file_exists($filepath) || strpos(realpath($filepath), realpath($backupDir)) !== 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Backup file not found']);
    exit;
}

// Validate extension
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, ['json', 'zip'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

// Set appropriate content type
$contentType = $ext === 'zip' ? 'application/zip' : 'application/json';

// Send file
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($filepath);
exit;
