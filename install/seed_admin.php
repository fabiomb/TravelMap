<?php
/**
 * Script de Inicialización: Crear Usuario Administrador
 * 
 * Este script crea un usuario administrador inicial en la base de datos.
 * Usuario: admin
 * Contraseña: admin123
 * 
 * IMPORTANTE: Ejecutar este script solo UNA VEZ y luego eliminarlo o protegerlo.
 */

// Subir dos niveles para llegar a la raíz
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - TravelMap</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .credentials {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .credentials strong {
            color: #856404;
        }
        a.button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        a.button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Instalación TravelMap</h1>
        
        <?php
        $message = '';
        $success = false;
        
        try {
            $db = getDB();
            
            // Verificar si ya existe un usuario admin
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM users WHERE username = ?');
            $stmt->execute(['admin']);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                $message = '<div class="info">
                    <strong>ℹ️ Información:</strong> El usuario administrador ya existe en la base de datos.
                </div>';
            } else {
                // Crear el usuario administrador
                $username = 'admin';
                $password = 'admin123';
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
                $stmt->execute([$username, $password_hash]);
                
                $success = true;
                $message = '<div class="success">
                    <strong>✅ ¡Éxito!</strong> Usuario administrador creado correctamente.
                </div>
                <div class="credentials">
                    <strong>📋 Credenciales de acceso:</strong><br>
                    <strong>Usuario:</strong> admin<br>
                    <strong>Contraseña:</strong> admin123
                </div>
                <div class="info">
                    <strong>⚠️ Importante:</strong> 
                    <ul>
                        <li>Cambia la contraseña después del primer inicio de sesión</li>
                        <li>Elimina o protege esta carpeta /install/ por seguridad</li>
                    </ul>
                </div>';
            }
            
        } catch (PDOException $e) {
            $message = '<div class="error">
                <strong>❌ Error:</strong> ' . htmlspecialchars($e->getMessage()) . '
            </div>
            <div class="info">
                <strong>Posibles causas:</strong>
                <ul>
                    <li>La base de datos "travelmap" no existe (importa database.sql primero)</li>
                    <li>Credenciales de MySQL incorrectas en config/db.php</li>
                    <li>El servidor MySQL no está iniciado</li>
                </ul>
            </div>';
        }
        
        echo $message;
        
        if ($success || $result['count'] > 0) {
            echo '<p><a href="' . BASE_URL . '/login.php" class="button">Ir al Login</a></p>';
        }
        ?>
        
        <hr style="margin: 30px 0;">
        
        <h2>📝 Pasos siguientes:</h2>
        <ol>
            <li>Asegúrate de haber importado <code>database.sql</code> en MySQL</li>
            <li>Ejecuta este script para crear el usuario admin</li>
            <li>Accede al <a href="<?= BASE_URL ?>/login.php">panel de login</a></li>
            <li>Inicia sesión con las credenciales mostradas arriba</li>
            <li><strong>Elimina o protege la carpeta /install/</strong></li>
        </ol>
    </div>
</body>
</html>
