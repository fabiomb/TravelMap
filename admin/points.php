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
        $message = __('points.deleted_success');
        $message_type = 'success';
    } else {
        $message = __('points.error_deleting');
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

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.5 9C14.5 10.3807 13.3807 11.5 12 11.5C10.6193 11.5 9.5 10.3807 9.5 9C9.5 7.61929 10.6193 6.5 12 6.5C13.3807 6.5 14.5 7.61929 14.5 9Z"/>
                <path d="M13.2574 17.4936C12.9201 17.8184 12.4693 18 12.0002 18C11.531 18 11.0802 17.8184 10.7429 17.4936C7.6543 14.5008 3.51519 11.1575 5.53371 6.30373C6.6251 3.67932 9.24494 2 12.0002 2C14.7554 2 17.3752 3.67933 18.4666 6.30373C20.4826 11.1514 16.3536 14.5111 13.2574 17.4936Z"/>
                <path d="M7 18C5.17107 18.4117 4 19.0443 4 19.7537C4 20.9943 7.58172 22 12 22C16.4183 22 20 20.9943 20 19.7537C20 19.0443 18.8289 18.4117 17 18"/>
            </svg>
            <?= __('points.title') ?>
        </h1>
    </div>
    <div class="page-actions">
        <a href="point_form.php<?= $filter_trip_id ? '?trip_id=' . $filter_trip_id : '' ?>" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <?= __('points.new_point') ?>
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <?php if ($message_type === 'success'): ?>
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            <?php else: ?>
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            <?php endif; ?>
        </svg>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
<?php endif; ?>

<!-- Filter Card -->
<div class="admin-card" style="margin-bottom: 20px;">
    <div class="admin-card-body" style="padding: 12px 20px;">
        <form method="GET" action="" style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
            <label class="form-label" style="margin: 0; display: flex; align-items: center; gap: 6px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                </svg>
                <?= __('points.filter_by_trip') ?>
            </label>
            <select name="trip_id" class="form-control form-select" style="width: auto; min-width: 250px;" onchange="this.form.submit()">
                <option value=""><?= __('points.all_trips') ?></option>
                <?php foreach ($trips as $trip): ?>
                    <option value="<?= $trip['id'] ?>" <?= $filter_trip_id == $trip['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($trip['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($filter_trip_id): ?>
                <a href="points.php" class="btn btn-secondary btn-sm"><?= __('points.clear_filter') ?></a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($points)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.5 9C14.5 10.3807 13.3807 11.5 12 11.5C10.6193 11.5 9.5 10.3807 9.5 9C9.5 7.61929 10.6193 6.5 12 6.5C13.3807 6.5 14.5 7.61929 14.5 9Z"/>
                    <path d="M13.2574 17.4936C12.9201 17.8184 12.4693 18 12.0002 18C11.531 18 11.0802 17.8184 10.7429 17.4936C7.6543 14.5008 3.51519 11.1575 5.53371 6.30373C6.6251 3.67932 9.24494 2 12.0002 2C14.7554 2 17.3752 3.67933 18.4666 6.30373C20.4826 11.1514 16.3536 14.5111 13.2574 17.4936Z"/>
                </svg>
                <h4 class="empty-state-title"><?= __('points.no_points') ?></h4>
                <p class="empty-state-text">
                    <?= $filter_trip_id ? __('common.info') : __('messages.processing') ?>
                </p>
                <a href="point_form.php<?= $filter_trip_id ? '?trip_id=' . $filter_trip_id : '' ?>" class="btn btn-primary"><?= __('points.new_point') ?></a>
            </div>
        <?php else: ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 70px;"><?= __('points.image') ?></th>
                            <th><?= __('points.title_field') ?></th>
                            <th style="width: 180px;"><?= __('points.trip') ?></th>
                            <th style="width: 140px;"><?= __('points.type') ?></th>
                            <th style="width: 130px;"><?= __('points.coordinates') ?></th>
                            <th style="width: 100px;"><?= __('common.date') ?></th>
                            <th style="width: 100px;" class="table-actions"><?= __('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($points as $point): ?>
                            <tr>
                                <td>
                                    <?php if ($point['image_path']): ?>
                                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($point['image_path']) ?>" 
                                             alt="<?= htmlspecialchars($point['title']) ?>" 
                                             class="table-thumb">
                                    <?php else: ?>
                                        <div class="table-thumb-placeholder">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                <polyline points="21 15 16 10 5 21"></polyline>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="cell-title"><?= htmlspecialchars($point['title']) ?></div>
                                    <?php if ($point['description']): ?>
                                        <div class="cell-subtitle"><?= htmlspecialchars(mb_substr($point['description'], 0, 50)) ?><?= mb_strlen($point['description']) > 50 ? '...' : '' ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-trip" style="--trip-color: <?= htmlspecialchars($point['trip_color']) ?>;">
                                        <?= htmlspecialchars($point['trip_title']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--admin-text-muted);">
                                        <?= Point::getSvgIcon($point['type'], 14) ?>
                                        <?= htmlspecialchars($point_types[$point['type']] ?? $point['type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="cell-mono">
                                        <?= number_format($point['latitude'], 6) ?>,<br>
                                        <?= number_format($point['longitude'], 6) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($point['visit_date']): ?>
                                        <span class="cell-date"><?= date('d/m/Y', strtotime($point['visit_date'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <div class="btn-group">
                                        <a href="point_form.php?id=<?= $point['id'] ?>" class="btn btn-icon btn-sm btn-outline-primary" title="<?= __('common.edit') ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </a>
                                        <a href="?delete=<?= $point['id'] ?><?= $filter_trip_id ? '&trip_id=' . $filter_trip_id : '' ?>" class="btn btn-icon btn-sm btn-outline-danger btn-delete" title="<?= __('common.delete') ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-card-footer" style="font-size: 12px; color: var(--admin-text-muted);">
                <?= count($points) ?> <?= __('points.title') ?>
                <?= $filter_trip_id ? '(' . __('points.filter_by_trip') . ')' : '' ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
