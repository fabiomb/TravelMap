<?php
/**
 * Formulario de Viaje
 * 
 * Crear o editar un viaje
 */

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../src/models/Trip.php';

$tripModel = new Trip();
$errors = [];
$success = false;
$trip = null;
$is_edit = false;

// Verificar si es edición
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $trip_id = (int) $_GET['id'];
    $trip = $tripModel->getById($trip_id);
    
    if (!$trip) {
        header('Location: trips.php');
        exit;
    }
    $is_edit = true;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'start_date' => $_POST['start_date'] ?? null,
        'end_date' => $_POST['end_date'] ?? null,
        'color_hex' => $_POST['color_hex'] ?? '#3388ff',
        'status' => $_POST['status'] ?? 'draft'
    ];

    // Validar datos
    $errors = $tripModel->validate($data);

    if (empty($errors)) {
        if ($is_edit) {
            // Actualizar
            if ($tripModel->update($trip_id, $data)) {
                $success = true;
                $trip = $tripModel->getById($trip_id); // Recargar datos
                $message = 'Viaje actualizado correctamente';
            } else {
                $errors['general'] = 'Error al actualizar el viaje';
            }
        } else {
            // Crear
            $new_id = $tripModel->create($data);
            if ($new_id) {
                $success = true;
                $message = 'Viaje creado correctamente';
                // Redirigir a edición del nuevo viaje
                header("Location: trip_form.php?id={$new_id}&success=1");
                exit;
            } else {
                $errors['general'] = 'Error al crear el viaje';
            }
        }
    }
}

// Valores por defecto para formulario
$form_data = $trip ?? [
    'title' => $_POST['title'] ?? '',
    'description' => $_POST['description'] ?? '',
    'start_date' => $_POST['start_date'] ?? '',
    'end_date' => $_POST['end_date'] ?? '',
    'color_hex' => $_POST['color_hex'] ?? '#3388ff',
    'status' => $_POST['status'] ?? 'draft'
];
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="mb-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-<?= $is_edit ? 'pencil' : 'plus-circle' ?> me-2" viewBox="0 0 16 16">
                <?php if ($is_edit): ?>
                    <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/>
                <?php else: ?>
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                <?php endif; ?>
            </svg>
            <?= $is_edit ? 'Editar Viaje' : 'Nuevo Viaje' ?>
        </h1>
    </div>
    <div class="col-md-4 text-end">
        <a href="trips.php" class="btn btn-outline-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left me-1" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/>
            </svg>
            Volver al listado
        </a>
    </div>
</div>

<?php if ($success && isset($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle me-2" viewBox="0 0 16 16">
            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
            <path d="m10.97 4.97-.02.022-3.473 4.425-2.093-2.094a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/>
        </svg>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Viaje creado correctamente
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($errors['general']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="">
                    <!-- Título -->
                    <div class="mb-3">
                        <label for="title" class="form-label">Título del Viaje <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" 
                               id="title" 
                               name="title" 
                               value="<?= htmlspecialchars($form_data['title']) ?>" 
                               required 
                               maxlength="200"
                               placeholder="Ej: Viaje a Europa 2025">
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Descripción -->
                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="4"
                                  placeholder="Describe tu viaje..."><?= htmlspecialchars($form_data['description'] ?? '') ?></textarea>
                        <small class="form-text text-muted">Opcional: Agrega detalles sobre tu viaje</small>
                    </div>

                    <div class="row">
                        <!-- Fecha de Inicio -->
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Fecha de Inicio</label>
                            <input type="date" 
                                   class="form-control <?= isset($errors['dates']) ? 'is-invalid' : '' ?>" 
                                   id="start_date" 
                                   name="start_date" 
                                   value="<?= htmlspecialchars($form_data['start_date'] ?? '') ?>">
                            <?php if (isset($errors['dates'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['dates']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Fecha de Fin -->
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">Fecha de Fin</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="end_date" 
                                   name="end_date" 
                                   value="<?= htmlspecialchars($form_data['end_date'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <!-- Color -->
                        <div class="col-md-6 mb-3">
                            <label for="color_hex" class="form-label">Color del Viaje</label>
                            <div class="input-group">
                                <input type="color" 
                                       class="form-control form-control-color <?= isset($errors['color_hex']) ? 'is-invalid' : '' ?>" 
                                       id="color_hex" 
                                       name="color_hex" 
                                       value="<?= htmlspecialchars($form_data['color_hex']) ?>"
                                       title="Selecciona un color">
                                <input type="text" 
                                       class="form-control" 
                                       id="color_hex_text" 
                                       value="<?= htmlspecialchars($form_data['color_hex']) ?>" 
                                       readonly>
                            </div>
                            <?php if (isset($errors['color_hex'])): ?>
                                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['color_hex']) ?></div>
                            <?php endif; ?>
                            <small class="form-text text-muted">Este color se usará para diferenciar el viaje en el mapa</small>
                        </div>

                        <!-- Estado -->
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Estado</label>
                            <select class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" 
                                    id="status" 
                                    name="status">
                                <option value="draft" <?= $form_data['status'] === 'draft' ? 'selected' : '' ?>>Borrador</option>
                                <option value="public" <?= $form_data['status'] === 'public' ? 'selected' : '' ?>>Público</option>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['status']) ?></div>
                            <?php endif; ?>
                            <small class="form-text text-muted">Solo los viajes públicos se mostrarán en el mapa</small>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="trips.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-save me-1" viewBox="0 0 16 16">
                                <path d="M2 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H9.5a1 1 0 0 0-1 1v7.293l2.646-2.647a.5.5 0 0 1 .708.708l-3.5 3.5a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L7.5 9.293V2a2 2 0 0 1 2-2H14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h2.5a.5.5 0 0 1 0 1z"/>
                            </svg>
                            <?= $is_edit ? 'Guardar Cambios' : 'Crear Viaje' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-info-circle me-2" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                        <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533z"/>
                        <path d="M9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
                    </svg>
                    Ayuda
                </h5>
            </div>
            <div class="card-body">
                <h6>Campos obligatorios</h6>
                <ul>
                    <li><strong>Título:</strong> Nombre identificativo del viaje</li>
                </ul>

                <h6 class="mt-3">Campos opcionales</h6>
                <ul>
                    <li><strong>Descripción:</strong> Detalles adicionales</li>
                    <li><strong>Fechas:</strong> Período del viaje</li>
                    <li><strong>Color:</strong> Para visualización en el mapa</li>
                    <li><strong>Estado:</strong> Borrador o Público</li>
                </ul>

                <?php if ($is_edit): ?>
                    <hr>
                    <h6>Estadísticas</h6>
                    <ul class="list-unstyled">
                        <li><strong>Puntos de interés:</strong> <?= $tripModel->countPoints($trip['id']) ?></li>
                        <li><strong>Rutas:</strong> <?= $tripModel->countRoutes($trip['id']) ?></li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Sincronizar color picker con input de texto
document.getElementById('color_hex').addEventListener('input', function(e) {
    document.getElementById('color_hex_text').value = e.target.value;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
