<?php
/**
 * Formulario de Punto de Interés
 * 
 * Crear o editar un punto de interés con subida de imágenes
 */

// Cargar configuración y dependencias ANTES de header.php para permitir redirects
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Point.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/helpers/FileHelper.php';

$pointModel = new Point();
$tripModel = new Trip();
$errors = [];
$success = false;
$point = null;
$is_edit = false;

// Verificar si es edición
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $point_id = (int) $_GET['id'];
    $point = $pointModel->getById($point_id);
    
    if (!$point) {
        header('Location: points.php');
        exit;
    }
    $is_edit = true;
}

// Obtener todos los viajes para el select
$trips = $tripModel->getAll('title ASC');

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'trip_id' => $_POST['trip_id'] ?? null,
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'type' => $_POST['type'] ?? '',
        'icon' => $_POST['icon'] ?? 'default',
        'latitude' => $_POST['latitude'] ?? '',
        'longitude' => $_POST['longitude'] ?? '',
        'visit_date' => $_POST['visit_date'] ?? null,
        'image_path' => $is_edit ? $point['image_path'] : null
    ];

    // Validar datos
    $errors = $pointModel->validate($data);

    // Procesar imagen si se subió
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_result = FileHelper::uploadImage($_FILES['image']);
        
        if ($upload_result['success']) {
            // Si es edición y tenía imagen anterior, eliminarla
            if ($is_edit && !empty($point['image_path'])) {
                FileHelper::deleteFile($point['image_path']);
            }
            $data['image_path'] = $upload_result['path'];
        } else {
            $errors['image'] = $upload_result['error'];
        }
    }

    if (empty($errors)) {
        if ($is_edit) {
            // Actualizar
            if ($pointModel->update($point_id, $data)) {
                $success = true;
                $point = $pointModel->getById($point_id); // Recargar datos
                $message = __('points.updated_success');
            } else {
                $errors['general'] = __('points.error_saving');
            }
        } else {
            // Crear
            $new_id = $pointModel->create($data);
            if ($new_id) {
                $success = true;
                $message = __('points.saved_success');
                // Redirigir a edición del nuevo punto
                header("Location: point_form.php?id={$new_id}&success=1");
                exit;
            } else {
                $errors['general'] = __('points.error_saving');
            }
        }
    }
}

// Ahora sí incluir header.php (después de procesar y posibles redirects)
require_once __DIR__ . '/../includes/header.php';

// Valores por defecto para formulario
$form_data = $point ?? [
    'trip_id' => $_POST['trip_id'] ?? ($_GET['trip_id'] ?? ''),
    'title' => $_POST['title'] ?? '',
    'description' => $_POST['description'] ?? '',
    'type' => $_POST['type'] ?? 'visit',
    'icon' => $_POST['icon'] ?? 'default',
    'latitude' => $_POST['latitude'] ?? '',
    'longitude' => $_POST['longitude'] ?? '',
    'visit_date' => $_POST['visit_date'] ?? '',
    'image_path' => null
];

$point_types = Point::getTypes();
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
            <?= $is_edit ? __('points.edit_point') : __('points.new_point') ?>
        </h1>
    </div>
    <div class="col-md-4 text-end">
        <a href="points.php<?= isset($_GET['trip_id']) ? '?trip_id=' . $_GET['trip_id'] : '' ?>" class="btn btn-outline-secondary">
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
        Punto de interés creado correctamente
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
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-geo-alt me-2" viewBox="0 0 16 16">
                    <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/>
                    <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                </svg>
                <?= __('points.point_info') ?>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <!-- Viaje -->
                    <div class="mb-3">
                        <label for="trip_id" class="form-label"><?= __('points.trip') ?> <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['trip_id']) ? 'is-invalid' : '' ?>" 
                                id="trip_id" 
                                name="trip_id" 
                                required>
                            <option value=""><?= __('forms.select_trip') ?></option>
                            <?php foreach ($trips as $trip): ?>
                                <option value="<?= $trip['id'] ?>" <?= $form_data['trip_id'] == $trip['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($trip['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['trip_id'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['trip_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Título -->
                    <div class="mb-3">
                        <label for="title" class="form-label"><?= __('points.title_field') ?> <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" 
                               id="title" 
                               name="title" 
                               value="<?= htmlspecialchars($form_data['title']) ?>" 
                               required 
                               maxlength="200"
                               placeholder="<?= __('forms.example') ?>: Torre Eiffel">
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Descripción -->
                    <div class="mb-3">
                        <label for="description" class="form-label"><?= __('points.description') ?></label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"
                                  placeholder="<?= __('forms.describe_place') ?>"><?= htmlspecialchars($form_data['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <!-- Tipo -->
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label"><?= __('points.type') ?> <span class="text-danger">*</span></label>
                            <select class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>" 
                                    id="type" 
                                    name="type" 
                                    required>
                                <?php foreach ($point_types as $type_key => $type_label): ?>
                                    <option value="<?= $type_key ?>" <?= $form_data['type'] === $type_key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type_label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['type'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['type']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Fecha -->
                        <div class="col-md-6 mb-3">
                            <label for="visit_date" class="form-label"><?= __('points.visit_date') ?></label>
                            <input type="date" 
                                   class="form-control" 
                                   id="visit_date" 
                                   name="visit_date" 
                                   value="<?= htmlspecialchars($form_data['visit_date'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Coordenadas -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="latitude" class="form-label"><?= __('points.latitude') ?> <span class="text-danger">*</span></label>
                            <input type="number" 
                                   class="form-control <?= isset($errors['latitude']) ? 'is-invalid' : '' ?>" 
                                   id="latitude" 
                                   name="latitude" 
                                   value="<?= htmlspecialchars($form_data['latitude']) ?>" 
                                   step="0.000001" 
                                   min="-90" 
                                   max="90" 
                                   required
                                   placeholder="-34.603722">
                            <?php if (isset($errors['latitude'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['latitude']) ?></div>
                            <?php else: ?>
                                <small class="form-text text-muted"><?= __('forms.range') ?>: -90 a 90</small>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="longitude" class="form-label"><?= __('points.longitude') ?> <span class="text-danger">*</span></label>
                            <input type="number" 
                                   class="form-control <?= isset($errors['longitude']) ? 'is-invalid' : '' ?>" 
                                   id="longitude" 
                                   name="longitude" 
                                   value="<?= htmlspecialchars($form_data['longitude']) ?>" 
                                   step="0.000001" 
                                   min="-180" 
                                   max="180" 
                                   required
                                   placeholder="-58.381592">
                            <?php if (isset($errors['longitude'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['longitude']) ?></div>
                            <?php else: ?>
                                <small class="form-text text-muted"><?= __('forms.range') ?>: -180 a 180</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Mapa Interactivo -->
                    <div class="mb-3">
                        <label class="form-label">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt me-1" viewBox="0 0 16 16">
                                <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/>
                                <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                            </svg>
                            <?= __('forms.map_location') ?>
                        </label>
                        
                        <!-- Buscador de lugares -->
                        <div class="input-group mb-2">
                            <span class="input-group-text">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                                </svg>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="placeSearch" 
                                   placeholder="<?= __('points.search_place') ?>"
                                   autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                                <?= __('points.search') ?>
                            </button>
                        </div>
                        <div id="searchResults" class="list-group mb-2" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                        
                        <div id="pointMap" style="height: 400px; width: 100%; border: 1px solid #ddd; border-radius: 4px;"></div>
                        <small class="form-text text-muted mt-1 d-block">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-info-circle me-1" viewBox="0 0 16 16">
                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                                <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533z"/>
                                <path d="M9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
                            </svg>
                            <?= __('points.click_map_to_select') ?>
                        </small>
                    </div>

                    <!-- Imagen con Drag & Drop -->
                    <div class="mb-3">
                        <label for="image" class="form-label"><?= __('points.image') ?></label>
                        
                        <!-- Área de Drag & Drop -->
                        <div id="dropArea" class="border rounded p-4 text-center <?= isset($errors['image']) ? 'border-danger' : 'border-secondary' ?>" 
                             style="background-color: #f8f9fa; cursor: pointer; transition: all 0.3s;">
                            <div id="dropAreaContent">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-cloud-upload text-muted mb-2" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M4.406 1.342A5.53 5.53 0 0 1 8 0c2.69 0 4.923 2 5.166 4.579C14.758 4.804 16 6.137 16 7.773 16 9.569 14.502 11 12.687 11H10a.5.5 0 0 1 0-1h2.688C13.979 10 15 8.988 15 7.773c0-1.216-1.02-2.228-2.313-2.228h-.5v-.5C12.188 2.825 10.328 1 8 1a4.53 4.53 0 0 0-2.941 1.1c-.757.652-1.153 1.438-1.153 2.055v.448l-.445.049C2.064 4.805 1 5.952 1 7.318 1 8.785 2.23 10 3.781 10H6a.5.5 0 0 1 0 1H3.781C1.708 11 0 9.366 0 7.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383"/>
                                    <path fill-rule="evenodd" d="M7.646 4.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 5.707V14.5a.5.5 0 0 1-1 0V5.707L5.354 7.854a.5.5 0 1 1-.708-.708z"/>
                                </svg>
                                <p class="mb-2 fw-bold"><?= __('points.drag_drop_image') ?></p>
                                <p class="mb-2 text-muted"><?= __('points.or') ?></p>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="selectFileBtn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-folder2-open me-1" viewBox="0 0 16 16">
                                        <path d="M1 3.5A1.5 1.5 0 0 1 2.5 2h2.764c.958 0 1.76.56 2.311 1.184C7.985 3.648 8.48 4 9 4h4.5A1.5 1.5 0 0 1 15 5.5v.64c.57.265.94.876.856 1.546l-.64 5.124A2.5 2.5 0 0 1 12.733 15H3.266a2.5 2.5 0 0 1-2.481-2.19l-.64-5.124A1.5 1.5 0 0 1 1 6.14zM2 6h12v-.5a.5.5 0 0 0-.5-.5H9c-.964 0-1.71-.629-2.174-1.154C6.374 3.334 5.82 3 5.264 3H2.5a.5.5 0 0 0-.5.5zm-.367 1a.5.5 0 0 0-.496.562l.64 5.124A1.5 1.5 0 0 0 3.266 14h9.468a1.5 1.5 0 0 0 1.489-1.314l.64-5.124A.5.5 0 0 0 14.367 7z"/>
                                    </svg>
                                    <?= __('points.select_file') ?>
                                </button>
                                <p class="small text-muted mt-2 mb-0"><?= __('points.max_upload_note') ?> <?php echo round(MAX_UPLOAD_SIZE / 1024 / 1024, 2); ?>MB</p>
                            </div>
                            <div id="previewArea" style="display: none;">
                                <img id="imagePreview" src="" alt="Vista previa" class="img-thumbnail mb-2" style="max-width: 100%; max-height: 300px;">
                                <p id="fileName" class="mb-2 fw-bold"></p>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="removeImageBtn">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash me-1" viewBox="0 0 16 16">
                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                                    </svg>
                                    <?= __('common.remove') ?>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Input file oculto -->
                        <input type="file" 
                               class="d-none" 
                               id="image" 
                               name="image" 
                               accept="image/jpeg,image/png,image/jpg,image/gif">
                        
                        <?php if (isset($errors['image'])): ?>
                            <div class="text-danger small mt-1"><?= htmlspecialchars($errors['image']) ?></div>
                        <?php endif; ?>

                        <?php if ($is_edit && !empty($point['image_path'])): ?>
                            <div class="mt-3">
                                <label class="form-label"><?= __('points.current_image') ?>:</label><br>
                                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($point['image_path']) ?>" 
                                     alt="Imagen actual" 
                                     class="img-thumbnail" 
                                     style="max-width: 200px;">
                                <p class="small text-muted mt-1"><?= __('points.upload_new_replace') ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="points.php<?= isset($_GET['trip_id']) ? '?trip_id=' . $_GET['trip_id'] : '' ?>" class="btn btn-secondary"><?= __('common.cancel') ?></a>
                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-save me-1" viewBox="0 0 16 16">
                                <path d="M2 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H9.5a1 1 0 0 0-1 1v7.293l2.646-2.647a.5.5 0 0 1 .708.708l-3.5 3.5a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L7.5 9.293V2a2 2 0 0 1 2-2H14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h2.5a.5.5 0 0 1 0 1z"/>
                            </svg>
                            <?= $is_edit ? __('forms.save_changes') : __('points.create_point') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
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
                    <li><strong><?= __('points.trip') ?>:</strong> <?= __('points.belongs_to_trip') ?></li>
                    <li><strong><?= __('points.title_field') ?>:</strong> <?= __('points.place_name') ?></li>
                    <li><strong><?= __('points.type') ?>:</strong> <?= __('points.point_category') ?></li>
                    <li><strong><?= __('points.coordinates') ?>:</strong> <?= __('points.exact_location') ?></li>
                </ul>

                <h6 class="mt-3"><?= __('points.point_types_title') ?></h6>
                <ul class="list-unstyled point-types-list">
                    <li><?= Point::getSvgIcon('stay', 16) ?> <strong><?= __('points.types.stay') ?>:</strong> <?= __('points.hotels_hostels') ?></li>
                    <li><?= Point::getSvgIcon('visit', 16) ?> <strong><?= __('points.types.visit') ?>:</strong> <?= __('points.tourist_attractions') ?></li>
                    <li><?= Point::getSvgIcon('food', 16) ?> <strong><?= __('points.types.food') ?>:</strong> <?= __('points.food_places') ?></li>
                </ul>

                <h6 class="mt-3"><?= __('points.get_coordinates') ?></h6>
                <p class="small"><?= __('points.use_interactive_map') ?></p>
                <ul class="small">
                    <li>🗿️ <?= __('points.click_map_place_marker') ?></li>
                    <li>🔄 <?= __('points.drag_marker_adjust') ?></li>
                    <li>✏️ <?= __('points.coordinates_auto_update') ?></li>
                </ul>
                <p class="small mt-2"><?= __('points.also_use_google_maps') ?> <a href="https://www.google.com/maps" target="_blank">Google Maps</a> <?= __('points.copy_coordinates_manually') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- jQuery primero (requerido por point_map.js) -->
<script src="<?= ASSETS_URL ?>/vendor/jquery/jquery-3.7.1.min.js"></script>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/css/leaflet.css">

<!-- Leaflet JS -->
<script src="<?= ASSETS_URL ?>/vendor/leaflet/js/leaflet.js"></script>

<script>
// Configuración base
const BASE_URL = '<?= BASE_URL ?>';

// Pasar datos PHP a JavaScript
const initialLat = <?= !empty($form_data['latitude']) ? $form_data['latitude'] : 'null' ?>;
const initialLng = <?= !empty($form_data['longitude']) ? $form_data['longitude'] : 'null' ?>;
</script>

<!-- Script del mapa de puntos -->
<script src="<?= ASSETS_URL ?>/js/point_map.js"></script>

<!-- Script para Drag & Drop de imágenes -->
<script>
$(document).ready(function() {
    const dropArea = $('#dropArea');
    const fileInput = $('#image');
    const selectFileBtn = $('#selectFileBtn');
    const removeImageBtn = $('#removeImageBtn');
    const dropAreaContent = $('#dropAreaContent');
    const previewArea = $('#previewArea');
    const imagePreview = $('#imagePreview');
    const fileName = $('#fileName');

    // Prevenir comportamiento por defecto del navegador
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.on(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    // Efectos visuales al arrastrar
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.on(eventName, function() {
            $(this).css({
                'background-color': '#e3f2fd',
                'border-color': '#2196F3',
                'border-width': '2px'
            });
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.on(eventName, function() {
            $(this).css({
                'background-color': '#f8f9fa',
                'border-color': '#6c757d',
                'border-width': '1px'
            });
        });
    });

    // Manejar drop
    dropArea.on('drop', function(e) {
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            handleFile(files[0]);
        }
    });

    // Click en el área para abrir selector
    dropArea.on('click', function(e) {
        if (!$(e.target).closest('#removeImageBtn').length && !$(e.target).closest('#imagePreview').length) {
            fileInput.click();
        }
    });

    // Click en botón seleccionar
    selectFileBtn.on('click', function(e) {
        e.stopPropagation();
        fileInput.click();
    });

    // Cuando se selecciona archivo con input
    fileInput.on('change', function() {
        if (this.files && this.files.length > 0) {
            handleFile(this.files[0]);
        }
    });

    // Botón para quitar imagen
    removeImageBtn.on('click', function(e) {
        e.stopPropagation();
        clearImage();
    });

    // Función para manejar el archivo
    function handleFile(file) {
        // Validar tipo de archivo
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            alert('<?= __('points.invalid_image_format') ?>');
            return;
        }

        // Validar tamaño
        const maxSize = <?php echo MAX_UPLOAD_SIZE; ?>; // Tomado de config.php
        if (file.size > maxSize) {
            const maxMB = (maxSize / 1024 / 1024).toFixed(2);
            alert(`<?= __('points.file_too_large') ?> ${maxMB}MB`);
            return;
        }

        // Crear DataTransfer para asignar el archivo al input
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileInput[0].files = dataTransfer.files;

        // Mostrar preview
        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.attr('src', e.target.result);
            fileName.text(file.name);
            dropAreaContent.hide();
            previewArea.show();
        };
        reader.readAsDataURL(file);
    }

    // Función para limpiar imagen
    function clearImage() {
        fileInput.val('');
        imagePreview.attr('src', '');
        fileName.text('');
        previewArea.hide();
        dropAreaContent.show();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
