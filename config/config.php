<?php
/**
 * Configuración Global del Proyecto
 * 
 * Define constantes para rutas y URLs utilizadas en toda la aplicación
 */

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configuración de errores (en producción cambiar a 0)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ruta raíz del proyecto (ajustar según tu instalación)
define('ROOT_PATH', dirname(__DIR__));

// URL base del proyecto (ajustar según tu servidor)
// Para XAMPP típicamente es: http://localhost/TravelMap
// Detectar automáticamente o configurar manualmente
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$folder = '/TravelMap'; // Cambiar si tu carpeta tiene otro nombre

define('BASE_URL', $protocol . '://' . $host . $folder);

// Rutas de directorios importantes
define('CONFIG_PATH', ROOT_PATH . '/config');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('SRC_PATH', ROOT_PATH . '/src');

// URLs de recursos
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// Configuración de archivos
define('MAX_UPLOAD_SIZE', 8 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Configuración de sesión
define('SESSION_LIFETIME', 3600 * 24); // 24 horas
