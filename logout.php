<?php
/**
 * Logout - Cerrar Sesión
 * 
 * Destruye la sesión y redirige al login
 */

require_once __DIR__ . '/includes/auth.php';

logout();

header('Location: ' . BASE_URL . '/login.php');
exit;
