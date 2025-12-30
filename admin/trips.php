<?php
/**
 * Gestión de Viajes
 * 
 * Listado de viajes con opciones para crear, editar y eliminar
 */

// Cargar configuración primero
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();
require_once __DIR__ . '/../config/db.php';
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

// Procesar acción masiva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $trip_ids = isset($_POST['trip_ids']) ? array_map('intval', $_POST['trip_ids']) : [];
    
    if (!empty($trip_ids)) {
        $success_count = 0;
        
        if ($action === 'publish') {
            foreach ($trip_ids as $id) {
                if ($tripModel->update($id, ['status' => 'public'])) {
                    $success_count++;
                }
            }
            $message = "$success_count viaje(s) publicado(s) correctamente";
            $message_type = 'success';
        } elseif ($action === 'draft') {
            foreach ($trip_ids as $id) {
                if ($tripModel->update($id, ['status' => 'draft'])) {
                    $success_count++;
                }
            }
            $message = "$success_count viaje(s) cambiado(s) a borrador";
            $message_type = 'success';
        } elseif ($action === 'delete') {
            foreach ($trip_ids as $id) {
                if ($tripModel->delete($id)) {
                    $success_count++;
                }
            }
            $message = "$success_count viaje(s) eliminado(s) correctamente";
            $message_type = 'success';
        }
    } else {
        $message = 'No se seleccionaron viajes';
        $message_type = 'warning';
    }
}

// Ahora incluir header después de procesar
require_once __DIR__ . '/../includes/header.php';

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
        <div class="card border-0">
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
                    <form method="POST" id="bulkForm">
                        <!-- Bulk Action Toolbar -->
                        <div class="alert alert-light alert-permanent border mb-3 d-none" id="bulkToolbar">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <strong><span id="selectedCount">0</span> viaje(s) seleccionado(s)</strong>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="bulk_action" value="publish" class="btn btn-success btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                            <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                            <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                                        </svg>
                                        Publicar
                                    </button>
                                    <button type="submit" name="bulk_action" value="draft" class="btn btn-secondary btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-eye-slash" viewBox="0 0 16 16">
                                            <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7 7 0 0 0-2.79.588l.77.771A6 6 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755q-.247.248-.517.486z"/>
                                            <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829"/>
                                            <path d="M3.35 5.47q-.27.24-.518.487A13 13 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7 7 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12z"/>
                                        </svg>
                                        Borrador
                                    </button>
                                    <button type="submit" name="bulk_action" value="delete" class="btn btn-danger btn-sm" 
                                            onclick="return confirm('¿Eliminar los viajes seleccionados? Esta acción no se puede deshacer.')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                            <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                            <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                                        </svg>
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" class="form-check-input" id="selectAll" title="Seleccionar todos">
                                        </th>
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
                                                <input type="checkbox" class="form-check-input trip-select" 
                                                       name="trip_ids[]" value="<?= $trip['id'] ?>">
                                            </td>
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
                                                    <a href="trip_edit_map.php?id=<?= $trip['id'] ?>" class="btn btn-outline-success" title="Editor de Mapa">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-map" viewBox="0 0 16 16">
                                                            <path fill-rule="evenodd" d="M15.817.113A.5.5 0 0 1 16 .5v14a.5.5 0 0 1-.402.49l-5 1a.5.5 0 0 1-.196 0L5.5 15.01l-4.902.98A.5.5 0 0 1 0 15.5v-14a.5.5 0 0 1 .402-.49l5-1a.5.5 0 0 1 .196 0L10.5.99l4.902-.98a.5.5 0 0 1 .415.103M10 1.91l-4-.8v12.98l4 .8zm1 12.98 4-.8V1.11l-4 .8zm-6-.8V1.11l-4 .8v12.98z"/>
                                                        </svg>
                                                    </a>
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
                    </form>
                    
                    <script>
                    (function() {
                        const selectAll = document.getElementById('selectAll');
                        const checkboxes = document.querySelectorAll('.trip-select');
                        const toolbar = document.getElementById('bulkToolbar');
                        const countSpan = document.getElementById('selectedCount');
                        
                        function updateToolbar() {
                            const checked = document.querySelectorAll('.trip-select:checked').length;
                            countSpan.textContent = checked;
                            
                            if (checked > 0) {
                                toolbar.classList.remove('d-none');
                            } else {
                                toolbar.classList.add('d-none');
                            }
                            
                            // Update select all state
                            selectAll.checked = checked === checkboxes.length && checkboxes.length > 0;
                            selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
                        }
                        
                        selectAll.addEventListener('change', function() {
                            checkboxes.forEach(cb => cb.checked = this.checked);
                            updateToolbar();
                        });
                        
                        checkboxes.forEach(cb => {
                            cb.addEventListener('change', updateToolbar);
                        });
                    })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
