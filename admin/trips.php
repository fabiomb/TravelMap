<?php
/**
 * Gestión de Viajes
 * 
 * Listado de viajes con opciones para crear, editar y eliminar
 */

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../src/models/Trip.php';

$tripModel = new Trip();
$message = '';
$message_type = '';

// Procesar eliminación
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $trip_id = (int) $_GET['delete'];
    if ($tripModel->delete($trip_id)) {
        $message = 'Viaje eliminado correctamente';
        $message_type = 'success';
    } else {
        $message = 'Error al eliminar el viaje';
        $message_type = 'danger';
    }
}

// Obtener todos los viajes
$trips = $tripModel->getAll('start_date DESC, created_at DESC');
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h1 class="mb-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-airplane me-2" viewBox="0 0 16 16">
                <path d="M6.428 1.151C6.708.591 7.213 0 8 0s1.292.592 1.572 1.151C9.861 1.73 10 2.431 10 3v3.691l5.17 2.585a1.5 1.5 0 0 1 .83 1.342V12a.5.5 0 0 1-.582.493l-5.507-.918-.375 2.253 1.318 1.318A.5.5 0 0 1 10.5 16h-5a.5.5 0 0 1-.354-.854l1.319-1.318-.376-2.253-5.507.918A.5.5 0 0 1 0 12v-1.382a1.5 1.5 0 0 1 .83-1.342L6 6.691V3c0-.568.14-1.271.428-1.849"/>
            </svg>
            Gestión de Viajes
        </h1>
    </div>
    <div class="col-md-6 text-end">
        <a href="trip_form.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle me-1" viewBox="0 0 16 16">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
            </svg>
            Nuevo Viaje
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($trips)): ?>
                    <div class="text-center py-5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-inbox text-muted mb-3" viewBox="0 0 16 16">
                            <path d="M4.98 4a.5.5 0 0 0-.39.188L1.54 8H6a.5.5 0 0 1 .5.5 1.5 1.5 0 1 0 3 0A.5.5 0 0 1 10 8h4.46l-3.05-3.812A.5.5 0 0 0 11.02 4zm9.954 5H10.45a2.5 2.5 0 0 1-4.9 0H1.066l.32 2.562a.5.5 0 0 0 .497.438h12.234a.5.5 0 0 0 .496-.438zM3.809 3.563A1.5 1.5 0 0 1 4.981 3h6.038a1.5 1.5 0 0 1 1.172.563l3.7 4.625a.5.5 0 0 1 .105.374l-.39 3.124A1.5 1.5 0 0 1 14.117 13H1.883a1.5 1.5 0 0 1-1.489-1.314l-.39-3.124a.5.5 0 0 1 .106-.374z"/>
                        </svg>
                        <h4 class="text-muted">No hay viajes registrados</h4>
                        <p class="text-muted">Comienza creando tu primer viaje</p>
                        <a href="trip_form.php" class="btn btn-primary">Crear Viaje</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">Color</th>
                                    <th>Título</th>
                                    <th>Fechas</th>
                                    <th>Estado</th>
                                    <th>Puntos</th>
                                    <th style="width: 200px;" class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trips as $trip): ?>
                                    <tr>
                                        <td>
                                            <div style="width: 30px; height: 30px; background-color: <?= htmlspecialchars($trip['color_hex']) ?>; border-radius: 4px; border: 1px solid #ddd;"></div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($trip['title']) ?></strong>
                                            <?php if ($trip['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars(mb_substr($trip['description'], 0, 60)) ?><?= mb_strlen($trip['description']) > 60 ? '...' : '' ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($trip['start_date'] && $trip['end_date']): ?>
                                                <small>
                                                    <?= date('d/m/Y', strtotime($trip['start_date'])) ?><br>
                                                    <?= date('d/m/Y', strtotime($trip['end_date'])) ?>
                                                </small>
                                            <?php elseif ($trip['start_date']): ?>
                                                <small><?= date('d/m/Y', strtotime($trip['start_date'])) ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Sin fechas</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($trip['status'] === 'public'): ?>
                                                <span class="badge bg-success">Público</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Borrador</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= $tripModel->countPoints($trip['id']) ?> puntos
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="trip_form.php?id=<?= $trip['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16">
                                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/>
                                                    </svg>
                                                </a>
                                                <a href="?delete=<?= $trip['id'] ?>" class="btn btn-outline-danger btn-delete" title="Eliminar">
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
