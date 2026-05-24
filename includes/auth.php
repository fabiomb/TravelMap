<?php
/**
 * Sistema de Autenticación
 * 
 * Gestiona el inicio de sesión, cierre de sesión y verificación de usuarios
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/User.php';

/**
 * Inicia sesión de forma segura
 */
function start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Intenta autenticar a un usuario
 * 
 * @param string $username Nombre de usuario
 * @param string $password Contraseña en texto plano
 * @return bool True si el login fue exitoso, false en caso contrario
 */
function login($username, $password) {
    start_session();
    
    try {
        $userModel = new User();
        $user = $userModel->verifyLogin($username, $password);
        
        if ($user) {
            // Regenerar ID de sesión para prevenir session fixation
            session_regenerate_id(true);

            // Guardar información del usuario en sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();

            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log('Error en login: ' . $e->getMessage());
        return false;
    }
}

/**
 * Cierra la sesión del usuario
 */
function logout() {
    start_session();
    
    // Limpiar todas las variables de sesión
    $_SESSION = [];
    
    // Destruir la cookie de sesión
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destruir la sesión
    session_destroy();
}

/**
 * Verifica si el usuario está autenticado
 * 
 * @return bool True si está logueado, false en caso contrario
 */
function is_logged_in() {
    start_session();
    
    // Verificar si existe la sesión
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    // Verificar timeout de sesión (opcional)
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive > SESSION_LIFETIME) {
            logout();
            return false;
        }
    }
    
    // Actualizar tiempo de última actividad
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Requiere autenticación para acceder a una página
 * Si no está logueado, redirige al login
 * 
 * @param string $redirect_to URL a donde redirigir si no está autenticado
 */
function require_auth($redirect_to = '/login.php') {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . $redirect_to);
        exit;
    }
}

/**
 * Obtiene el nombre del usuario actual
 * 
 * @return string|null Nombre de usuario o null si no está logueado
 */
function get_current_username() {
    start_session();
    return $_SESSION['username'] ?? null;
}

/**
 * Obtiene el ID del usuario actual
 *
 * @return int|null ID del usuario o null si no está logueado
 */
function get_current_user_id() {
    start_session();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Devuelve el token CSRF de la sesión actual, creándolo si no existe.
 */
function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica que el token CSRF recibido coincida con el de la sesión.
 * Falla con 403 si no coincide.
 */
function csrf_verify(string $token): void {
    start_session();
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!$expected || !hash_equals($expected, $token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'CSRF token inválido']);
        exit;
    }
}
