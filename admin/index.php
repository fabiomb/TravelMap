<?php
/**
 * Dashboard - Panel de Administración
 * 
 * Página principal del panel de control con resumen de estadísticas
 */

require_once __DIR__ . '/../includes/header.php';

// Obtener estadísticas básicas
try {
    $db = getDB();
    
    // Contar viajes
    $stmt = $db->query('SELECT COUNT(*) as total FROM trips');
    $total_trips = $stmt->fetch()['total'];
    
    // Contar viajes públicos
    $stmt = $db->query('SELECT COUNT(*) as total FROM trips WHERE status = "public"');
    $public_trips = $stmt->fetch()['total'];
    
    // Contar puntos de interés
    $stmt = $db->query('SELECT COUNT(*) as total FROM points_of_interest');
    $total_points = $stmt->fetch()['total'];
    
    // Contar usuarios
    $stmt = $db->query('SELECT COUNT(*) as total FROM users');
    $total_users = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $total_trips = $public_trips = $total_points = $total_users = 0;
}
?>

<div class="row">
    <div class="col-12">
        <h1 class="mb-4">
            Dashboard
        </h1>
    </div>
</div>

<!-- Tarjetas de Estadísticas -->
<div class="row g-4 mb-4">
    <!-- Total Viajes -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-gradient text-white rounded-3 p-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-airplane" viewBox="0 0 16 16">
                                <path d="M6.428 1.151C6.708.591 7.213 0 8 0s1.292.592 1.572 1.151C9.861 1.73 10 2.431 10 3v3.691l5.17 2.585a1.5 1.5 0 0 1 .83 1.342V12a.5.5 0 0 1-.582.493l-5.507-.918-.375 2.253 1.318 1.318A.5.5 0 0 1 10.5 16h-5a.5.5 0 0 1-.354-.854l1.319-1.318-.376-2.253-5.507.918A.5.5 0 0 1 0 12v-1.382a1.5 1.5 0 0 1 .83-1.342L6 6.691V3c0-.568.14-1.271.428-1.849"/>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title text-muted mb-1">Total Viajes</h5>
                        <h2 class="mb-0"><?= $total_trips ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Viajes Públicos -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-gradient text-white rounded-3 p-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title text-muted mb-1">Públicos</h5>
                        <h2 class="mb-0"><?= $public_trips ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Puntos de Interés -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-warning bg-gradient text-white rounded-3 p-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-geo-alt" viewBox="0 0 16 16">
                                <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/>
                                <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title text-muted mb-1">Puntos</h5>
                        <h2 class="mb-0"><?= $total_points ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Usuarios -->
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-info bg-gradient text-white rounded-3 p-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
                                <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title text-muted mb-1">Usuarios</h5>
                        <h2 class="mb-0"><?= $total_users ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mensaje de Bienvenida -->
<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4">
                <h4 class="mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-hand-thumbs-up me-2" viewBox="0 0 16 16">
                        <path d="M8.864.046C7.908-.193 7.02.53 6.956 1.466c-.072 1.051-.23 2.016-.428 2.59-.125.36-.479 1.013-1.04 1.639-.557.623-1.282 1.178-2.131 1.41C2.685 7.288 2 7.87 2 8.72v4.001c0 .845.682 1.464 1.448 1.545 1.07.114 1.564.415 2.068.723l.048.03c.272.165.578.348.97.484.397.136.861.217 1.466.217h3.5c.937 0 1.599-.477 1.934-1.064a1.86 1.86 0 0 0 .254-.912c0-.152-.023-.312-.077-.464.201-.263.38-.578.488-.901.11-.33.172-.762.004-1.149.069-.13.12-.269.159-.403.077-.27.113-.568.113-.857 0-.288-.036-.585-.113-.856a2 2 0 0 0-.138-.362 1.9 1.9 0 0 0 .234-1.734c-.206-.592-.682-1.1-1.2-1.272-.847-.282-1.803-.276-2.516-.211a10 10 0 0 0-.443.05 9.4 9.4 0 0 0-.062-4.509A1.38 1.38 0 0 0 9.125.111zM11.5 14.721H8c-.51 0-.863-.069-1.14-.164-.281-.097-.506-.228-.776-.393l-.04-.024c-.555-.339-1.198-.731-2.49-.868-.333-.036-.554-.29-.554-.55V8.72c0-.254.226-.543.62-.65 1.095-.3 1.977-.996 2.614-1.708.635-.71 1.064-1.475 1.238-1.978.243-.7.407-1.768.482-2.85.025-.362.36-.594.667-.518l.262.066c.16.04.258.143.288.255a8.34 8.34 0 0 1-.145 4.725.5.5 0 0 0 .595.644l.003-.001.014-.003.058-.014a9 9 0 0 1 1.036-.157c.663-.06 1.457-.054 2.11.164.175.058.45.3.57.65.107.308.087.67-.266 1.022l-.353.353.353.354c.043.043.105.141.154.315.048.167.075.37.075.581 0 .212-.027.414-.075.582-.05.174-.111.272-.154.315l-.353.353.353.354c.047.047.109.177.005.488a2.2 2.2 0 0 1-.505.805l-.353.353.353.354c.006.005.041.05.041.17a.9.9 0 0 1-.121.416c-.165.288-.503.56-1.066.56z"/>
                    </svg>
                    Bienvenido, <?= htmlspecialchars($username) ?>!
                </h4>
            </div>
            <div class="card-body">
                <p class="lead">Panel de administración de TravelMap</p>
                <p>Desde aquí puedes gestionar todos tus viajes, rutas y puntos de interés. Utiliza el menú superior para navegar entre las diferentes secciones.</p>
                
                <div class="alert alert-info alert-permanent border-0 mt-3" role="alert">
                    <h5 class="alert-heading">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-info-circle me-2" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                            <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533z"/>
                            <path d="M9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
                        </svg>
                        Acciones rápidas
                    </h5>
                    <ul class="mb-0">
                        <li><a href="<?= BASE_URL ?>/admin/trips.php">Gestionar viajes</a> - Crea, edita o elimina viajes</li>
                        <li><a href="<?= BASE_URL ?>/admin/users.php">Gestionar usuarios</a> - Administra los usuarios del sistema</li>
                        <li><a href="<?= BASE_URL ?>/" target="_blank">Ver sitio público</a> - Visualiza cómo se ven tus viajes</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4">
                <h5 class="mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-list-check me-2" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M3.854 2.146a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708L2 3.293l1.146-1.147a.5.5 0 0 1 .708 0m0 4a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708L2 7.293l1.146-1.147a.5.5 0 0 1 .708 0m0 4a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 0 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0"/>
                    </svg>
                    Estado del Sistema
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Base de datos
                        <span class="badge bg-success rounded-pill">Conectada</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        PHP Version
                        <span class="badge bg-primary rounded-pill"><?= PHP_VERSION ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        Última actividad
                        <span class="badge bg-secondary rounded-pill">Ahora</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
