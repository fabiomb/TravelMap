<?php
/**
 * Gestión de Puntos de Interés
 * 
 * Listado de puntos con filtro por viaje y opciones CRUD
 */

// Cargar configuración primero
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Point.php';
require_once __DIR__ . '/../src/models/Trip.php';

$pointModel = new Point();
$tripModel = new Trip();
$message = '';
$message_type = '';

// Procesar eliminación
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $point_id = (int) $_GET['delete'];
    
    // Obtener el punto para eliminar su imagen
    $point = $pointModel->getById($point_id);
    
    if ($point && $pointModel->delete($point_id)) {
        // Eliminar imagen asociada si existe
        if (!empty($point['image_path'])) {
            require_once __DIR__ . '/../src/helpers/FileHelper.php';
            FileHelper::deleteFile($point['image_path']);
        }
        $message = 'Punto de interés eliminado correctamente';
        $message_type = 'success';
    } else {
        $message = 'Error al eliminar el punto de interés';
        $message_type = 'danger';
    }
}

// Ahora incluir header después de procesar
require_once __DIR__ . '/../includes/header.php';

// Obtener filtro de viaje
$filter_trip_id = isset($_GET['trip_id']) && is_numeric($_GET['trip_id']) ? (int) $_GET['trip_id'] : null;

// Obtener puntos (filtrados o todos)
$points = $pointModel->getAll($filter_trip_id);

// Obtener todos los viajes para el filtro
$trips = $tripModel->getAll('title ASC');

// Obtener tipos de puntos
$point_types = Point::getTypes();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h1 class="mb-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-geo-alt me-2" viewBox="0 0 16 16">
                <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/>
                <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
            </svg>
            Puntos de Interés
        </h1>
    </div>
    <div class="col-md-6 text-end">
        <a href="point_form.php<?= $filter_trip_id ? '?trip_id=' . $filter_trip_id : '' ?>" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle me-1" viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
            </svg>
            Nuevo Punto
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filtro por viaje -->
<div class="row mb-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label class="col-form-label">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-filter me-1" viewBox="0 0 16 16">
                                <path d="M6 10.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5m-2-3a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m-2-3a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11a.5.5 0 0 1-.5-.5"/>
                            </svg>
                            Filtrar por viaje:
                        </label>
                    </div>
                    <div class="col">
                        <select name="trip_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos los viajes</option>
                            <?php foreach ($trips as $trip): ?>
                                <option value="<?= $trip['id'] ?>" <?= $filter_trip_id == $trip['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($trip['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($filter_trip_id): ?>
                        <div class="col-auto">
                            <a href="points.php" class="btn btn-sm btn-outline-secondary">Limpiar filtro</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($points)): ?>
                    <div class="text-center py-5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-geo text-muted mb-3" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 1a3 3 0 1 0 0 6 3 3 0 0 0 0-6M4 4a4 4 0 1 1 4.5 3.969V13.5a.5.5 0 0 1-1 0V7.97A4 4 0 0 1 4 4"/>
                        </svg>
                        <h4 class="text-muted">No hay puntos de interés registrados</h4>
                        <p class="text-muted">
                            <?= $filter_trip_id ? 'Este viaje no tiene puntos de interés' : 'Comienza creando tu primer punto' ?>
                        </p>
                        <a href="point_form.php<?= $filter_trip_id ? '?trip_id=' . $filter_trip_id : '' ?>" class="btn btn-primary">Crear Punto</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80px;">Imagen</th>
                                    <th>Título</th>
                                    <th>Viaje</th>
                                    <th>Tipo</th>
                                    <th>Coordenadas</th>
                                    <th>Fecha</th>
                                    <th style="width: 150px;" class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($points as $point): ?>
                                    <tr>
                                        <td>
                                            <?php if ($point['image_path']): ?>
                                                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($point['image_path']) ?>" 
                                                     alt="<?= htmlspecialchars($point['title']) ?>" 
                                                     class="img-thumbnail" 
                                                     style="width: 60px; height: 60px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light d-flex align-items-center justify-content-center" 
                                                     style="width: 60px; height: 60px; border-radius: 4px;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-image text-muted" viewBox="0 0 16 16">
                                                        <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/>
                                                        <path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1z"/>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($point['title']) ?></strong>
                                            <?php if ($point['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(mb_substr($point['description'], 0, 50)) ?><?= mb_strlen($point['description']) > 50 ? '...' : '' ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background-color: <?= htmlspecialchars($point['trip_color']) ?>;">
                                                <?= htmlspecialchars($point['trip_title']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="d-flex align-items-center gap-1">
                                                <?= Point::getSvgIcon($point['type'], 14) ?>
                                                <?= htmlspecialchars($point_types[$point['type']] ?? $point['type']) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-muted font-monospace">
                                                <?= number_format($point['latitude'], 6) ?>,<br>
                                                <?= number_format($point['longitude'], 6) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($point['visit_date']): ?>
                                                <small><?= date('d/m/Y', strtotime($point['visit_date'])) ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">-</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="point_form.php?id=<?= $point['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16">
                                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/>
                                                    </svg>
                                                </a>
                                                <a href="?delete=<?= $point['id'] ?><?= $filter_trip_id ? '&trip_id=' . $filter_trip_id : '' ?>" class="btn btn-outline-danger btn-delete" title="Eliminar">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                                        <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <small class="text-muted">
                            Mostrando <?= count($points) ?> punto(s) de interés
                            <?= $filter_trip_id ? 'del viaje seleccionado' : 'en total' ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
