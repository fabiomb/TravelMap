<?php
/**
 * Formulario de Viaje
 * 
 * Crear o editar un viaje
 */

// Cargar configuración y dependencias ANTES de header.php para permitir redirects
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();
require_once __DIR__ . '/../config/db.php';
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
                $message = __('common.updated_success');
            } else {
                $errors['general'] = __('common.error_updating');
            }
        } else {
            // Crear
            $new_id = $tripModel->create($data);
            if ($new_id) {
                $success = true;
                $message = __('common.created_success');
                // Redirigir a edición del nuevo viaje
                header("Location: trip_form.php?id={$new_id}&success=1");
                exit;
            } else {
                $errors['general'] = __('common.error_creating');
            }
        }
    }
}

// Ahora sí incluir header.php (después de procesar y posibles redirects)
require_once __DIR__ . '/../includes/header.php';

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
            <?= $is_edit ? __('trips.edit_trip') : __('trips.new_trip') ?>
        </h1>
    </div>
    <div class="col-md-4 text-end">
        <a href="trips.php" class="btn btn-outline-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left me-1" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/>
            </svg>
            <?= __('common.back_to_list') ?>
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
        <?= __('trips.saved_success') ?>
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
        <div class="card">
            <div class="card-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-compass me-2" viewBox="0 0 16 16">
                    <path d="M8 16.016a7.5 7.5 0 0 0 1.962-14.74A1 1 0 0 0 9 0H7a1 1 0 0 0-.962 1.276A7.5 7.5 0 0 0 8 16.016m6.5-7.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0"/>
                    <path d="m6.94 7.44 4.95-2.83-2.83 4.95-4.949 2.83 2.828-4.95z"/>
                </svg>
                <?= __('trips.trip_info') ?>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <!-- Título -->
                    <div class="mb-3">
                        <label for="title" class="form-label"><?= __('trips.title_field') ?> <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" 
                               id="title" 
                               name="title" 
                               value="<?= htmlspecialchars($form_data['title']) ?>" 
                               required 
                               maxlength="200"
                               placeholder="<?= __('forms.example') ?>: Viaje a Europa 2025">
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Descripción -->
                    <div class="mb-3">
                        <label for="description" class="form-label"><?= __('trips.description') ?></label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="4"
                                  placeholder="<?= __('forms.describe_trip') ?>"><?= htmlspecialchars($form_data['description'] ?? '') ?></textarea>
                        <small class="form-text text-muted"><?= __('forms.optional_add_details') ?></small>
                    </div>

                    <div class="row">
                        <!-- Fecha de Inicio -->
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label"><?= __('trips.start_date') ?></label>
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
                            <label for="end_date" class="form-label"><?= __('trips.end_date') ?></label>
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
                            <label for="color_hex" class="form-label"><?= __('trips.color') ?></label>
                            <div class="input-group">
                                <input type="color" 
                                       class="form-control form-control-color <?= isset($errors['color_hex']) ? 'is-invalid' : '' ?>" 
                                       id="color_hex" 
                                       name="color_hex" 
                                       value="<?= htmlspecialchars($form_data['color_hex']) ?>"
                                       title="<?= __('forms.select_color') ?>">
                                <input type="text" 
                                       class="form-control" 
                                       id="color_hex_text" 
                                       value="<?= htmlspecialchars($form_data['color_hex']) ?>" 
                                       readonly>
                            </div>
                            <?php if (isset($errors['color_hex'])): ?>
                                <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['color_hex']) ?></div>
                            <?php endif; ?>
                            <small class="form-text text-muted"><?= __('forms.color_for_map') ?></small>
                        </div>

                        <!-- Estado -->
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label"><?= __('trips.status') ?></label>
                            <select class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" 
                                    id="status" 
                                    name="status">
                                <option value="draft" <?= $form_data['status'] === 'draft' ? 'selected' : '' ?>><?= __('trips.draft') ?></option>
                                <option value="public" <?= $form_data['status'] === 'public' ? 'selected' : '' ?>><?= __('trips.public') ?></option>
                                <option value="planned" <?= $form_data['status'] === 'planned' ? 'selected' : '' ?>><?= __('forms.planned') ?></option>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['status']) ?></div>
                            <?php endif; ?>
                            <small class="form-text text-muted"><?= __('forms.public_and_planned_shown') ?></small>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="trips.php" class="btn btn-secondary"><?= __('common.cancel') ?></a>
                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-save me-1" viewBox="0 0 16 16">
                                <path d="M2 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H9.5a1 1 0 0 0-1 1v7.293l2.646-2.647a.5.5 0 0 1 .708.708l-3.5 3.5a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L7.5 9.293V2a2 2 0 0 1 2-2H14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h2.5a.5.5 0 0 1 0 1z"/>
                            </svg>
                            <?= $is_edit ? __('forms.save_changes') : __('forms.create') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-info-circle me-2" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                        <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533z"/>
                        <path d="M9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
                    </svg>
                    <?= __('forms.help') ?>
                </h5>
            </div>
            <div class="card-body">
                <h6><?= __('forms.required_fields') ?></h6>
                <ul>
                    <li><strong><?= __('trips.title_field') ?>:</strong> <?= __('trips.name') ?> <?= __('trips.identify_name') ?></li>
                </ul>

                <h6 class="mt-3"><?= __('forms.optional_fields') ?></h6>
                <ul>
                    <li><strong><?= __('trips.description') ?>:</strong> <?= __('trips.additional_details') ?></li>
                    <li><strong><?= __('common.date') ?>:</strong> <?= __('trips.travel_period') ?></li>
                    <li><strong><?= __('trips.color') ?>:</strong> <?= __('trips.map_visualization') ?></li>
                    <li><strong><?= __('trips.status') ?>:</strong> <?= __('trips.draft') ?> o <?= __('trips.public') ?></li>
                </ul>

                <?php if ($is_edit): ?>
                    <hr>
                    <h6><?= __('forms.statistics') ?></h6>
                    <ul class="list-unstyled">
                        <li><strong><?= __('trips.points') ?>:</strong> <?= $tripModel->countPoints($trip['id']) ?></li>
                        <li><strong><?= __('trips.routes') ?>:</strong> <?= $tripModel->countRoutes($trip['id']) ?></li>
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
