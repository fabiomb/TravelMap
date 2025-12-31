<?php
/**
 * Gestión de Configuraciones
 * 
 * Permite configurar opciones globales del sistema
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Settings.php';

// Obtener conexión a la base de datos
$conn = getDB();
$settingsModel = new Settings($conn);

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $updates = [];
        
        // Configuraciones generales
        if (isset($_POST['max_upload_size'])) {
            $updates['max_upload_size'] = [
                'value' => (int)$_POST['max_upload_size'],
                'type' => 'number'
            ];
        }
        
        if (isset($_POST['session_lifetime'])) {
            $updates['session_lifetime'] = [
                'value' => (int)$_POST['session_lifetime'],
                'type' => 'number'
            ];
        }
        
        if (isset($_POST['timezone'])) {
            $updates['timezone'] = [
                'value' => $_POST['timezone'],
                'type' => 'string'
            ];
        }
        
        if (isset($_POST['default_language'])) {
            $updates['default_language'] = [
                'value' => $_POST['default_language'],
                'type' => 'string'
            ];
        }
        
        // Configuraciones del mapa
        // Checkbox: always process (unchecked = not sent in POST)
        $updates['map_cluster_enabled'] = [
            'value' => isset($_POST['map_cluster_enabled']) && $_POST['map_cluster_enabled'] === '1',
            'type' => 'boolean'
        ];
        
        if (isset($_POST['map_cluster_max_radius'])) {
            $updates['map_cluster_max_radius'] = [
                'value' => (int)$_POST['map_cluster_max_radius'],
                'type' => 'number'
            ];
        }
        
        if (isset($_POST['map_cluster_disable_at_zoom'])) {
            $updates['map_cluster_disable_at_zoom'] = [
                'value' => (int)$_POST['map_cluster_disable_at_zoom'],
                'type' => 'number'
            ];
        }
        
        // Colores de transporte
        $transportTypes = ['plane', 'ship', 'car', 'train', 'walk'];
        foreach ($transportTypes as $type) {
            $key = 'transport_color_' . $type;
            if (isset($_POST[$key])) {
                $updates[$key] = [
                    'value' => $_POST[$key],
                    'type' => 'string'
                ];
            }
        }
        
        // Configuraciones de imagen
        if (isset($_POST['image_max_width'])) {
            $updates['image_max_width'] = [
                'value' => (int)$_POST['image_max_width'],
                'type' => 'number'
            ];
        }
        
        if (isset($_POST['image_max_height'])) {
            $updates['image_max_height'] = [
                'value' => (int)$_POST['image_max_height'],
                'type' => 'number'
            ];
        }
        
        if (isset($_POST['image_quality'])) {
            $updates['image_quality'] = [
                'value' => (int)$_POST['image_quality'],
                'type' => 'number'
            ];
        }
        
        // Configuraciones de thumbnails
        if (isset($_POST['thumbnail_max_width'])) {
            $updates['thumbnail_max_width'] = [
                'value' => (int)$_POST['thumbnail_max_width'],
                'type' => 'number'
            ];
        }
        
        if (isset($_POST['thumbnail_max_height'])) {
            $updates['thumbnail_max_height'] = [
                'value' => (int)$_POST['thumbnail_max_height'],
                'type' => 'number'
            ];
        }
        
        if (isset($_POST['thumbnail_quality'])) {
            $updates['thumbnail_quality'] = [
                'value' => (int)$_POST['thumbnail_quality'],
                'type' => 'number'
            ];
        }
        
        // Configuraciones del sitio público
        if (isset($_POST['site_title'])) {
            $updates['site_title'] = [
                'value' => trim($_POST['site_title']),
                'type' => 'string'
            ];
        }
        
        if (isset($_POST['site_description'])) {
            $updates['site_description'] = [
                'value' => trim($_POST['site_description']),
                'type' => 'string'
            ];
        }
        
        if (isset($_POST['site_favicon'])) {
            $updates['site_favicon'] = [
                'value' => trim($_POST['site_favicon']),
                'type' => 'string'
            ];
        }
        
        if (isset($_POST['site_analytics_code'])) {
            $updates['site_analytics_code'] = [
                'value' => $_POST['site_analytics_code'],
                'type' => 'string'
            ];
        }
        
        // Actualizar todas las configuraciones
        if ($settingsModel->updateMultiple($updates)) {
            $_SESSION['success_message'] = __('settings.updated_successfully');
        } else {
            $_SESSION['error_message'] = __('settings.error_updating');
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = __('common.error') . ': ' . $e->getMessage();
    }
    
    header('Location: settings.php');
    exit;
}

// Obtener configuraciones actuales
$currentSettings = $settingsModel->getAllAsArray();

// Zonas horarias comunes
$timezones = [
    'America/Argentina/Buenos_Aires' => 'Buenos Aires (GMT-3)',
    'America/Mexico_City' => 'Ciudad de México (GMT-6)',
    'America/Bogota' => 'Bogotá (GMT-5)',
    'America/Lima' => 'Lima (GMT-5)',
    'America/Santiago' => 'Santiago (GMT-3)',
    'America/Sao_Paulo' => 'São Paulo (GMT-3)',
    'America/New_York' => 'Nueva York (GMT-5)',
    'America/Chicago' => 'Chicago (GMT-6)',
    'America/Los_Angeles' => 'Los Ángeles (GMT-8)',
    'Europe/Madrid' => 'Madrid (GMT+1)',
    'Europe/London' => 'Londres (GMT+0)',
    'Europe/Paris' => 'París (GMT+1)',
    'Europe/Berlin' => 'Berlín (GMT+1)',
    'Europe/Rome' => 'Roma (GMT+1)',
    'Asia/Tokyo' => 'Tokio (GMT+9)',
    'Asia/Shanghai' => 'Shanghái (GMT+8)',
    'Asia/Dubai' => 'Dubái (GMT+4)',
    'Australia/Sydney' => 'Sídney (GMT+11)',
    'Pacific/Auckland' => 'Auckland (GMT+13)',
    'UTC' => 'UTC (GMT+0)'
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            <?= __('settings.system_configuration') ?>
        </h1>
        <p class="page-subtitle"><?= __('settings.manage_global_options') ?></p>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span><?= htmlspecialchars($_SESSION['success_message']) ?></span>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span><?= htmlspecialchars($_SESSION['error_message']) ?></span>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- Tabs -->
<ul class="admin-tabs" id="settingsTabs">
    <li class="tab-item">
        <button class="tab-link active" data-tab="general">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            <?= __('settings.general') ?? 'General' ?>
        </button>
    </li>
    <li class="tab-item">
        <button class="tab-link" data-tab="images">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                <polyline points="21 15 16 10 5 21"></polyline>
            </svg>
            <?= __('settings.images') ?? 'Images' ?>
        </button>
    </li>
    <li class="tab-item">
        <button class="tab-link" data-tab="map">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon>
                <line x1="8" y1="2" x2="8" y2="18"></line>
                <line x1="16" y1="6" x2="16" y2="22"></line>
            </svg>
            <?= __('settings.map') ?? 'Map' ?>
        </button>
    </li>
    <li class="tab-item">
        <button class="tab-link" data-tab="site">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="2" y1="12" x2="22" y2="12"></line>
                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
            </svg>
            <?= __('settings.site') ?? 'Site' ?>
        </button>
    </li>
    <li class="tab-item">
        <button class="tab-link" data-tab="server">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                <line x1="6" y1="6" x2="6.01" y2="6"></line>
                <line x1="6" y1="18" x2="6.01" y2="18"></line>
            </svg>
            <?= __('settings.server') ?? 'Server' ?>
        </button>
    </li>
</ul>

<form method="POST" action="settings.php">
    <input type="hidden" name="action" value="update">
    
    <!-- General Tab -->
    <div class="tab-content active" id="tab-general">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><?= __('settings.general_configuration') ?></h3>
        </div>
            <div class="admin-card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                    <div class="form-group">
                    <label for="max_upload_size" class="form-label">
                        <?= __('settings.max_upload_size_mb') ?>
                    </label>
                        <input type="number" class="form-control" id="max_upload_size" name="max_upload_size" 
                        value="<?= htmlspecialchars(round(($currentSettings['max_upload_size'] ?? 8388608) / 1048576, 2)) ?>"
                               min="1" max="100" step="0.1" required>
                        <div class="form-hint"><?= __('settings.max_upload_description') ?></div>
                </div>
                
                    <div class="form-group">
                    <label for="session_lifetime" class="form-label">
                        <?= __('settings.session_lifetime_hours') ?>
                    </label>
                        <input type="number" class="form-control" id="session_lifetime" name="session_lifetime" 
                        value="<?= htmlspecialchars(round(($currentSettings['session_lifetime'] ?? 86400) / 3600, 1)) ?>"
                               min="1" max="720" step="0.5" required>
                        <div class="form-hint"><?= __('settings.session_lifetime_description') ?></div>
            </div>
            
                    <div class="form-group">
                        <label for="timezone" class="form-label"><?= __('settings.timezone') ?? 'Timezone' ?></label>
                        <select class="form-control form-select" id="timezone" name="timezone" required>
                        <?php 
                        $currentTimezone = $currentSettings['timezone'] ?? 'America/Argentina/Buenos_Aires';
                            foreach ($timezones as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= $value === $currentTimezone ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                        <div class="form-hint"><?= __('settings.timezone_description') ?></div>
            </div>
            
                    <div class="form-group">
                        <label for="default_language" class="form-label"><?= __('settings.default_language') ?? 'Default Language' ?></label>
                        <select class="form-control form-select" id="default_language" name="default_language" required>
                        <?php 
                        $currentLanguage = $currentSettings['default_language'] ?? 'en';
                            $languages = ['en' => 'English', 'es' => 'Español'];
                            foreach ($languages as $code => $name): ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $code === $currentLanguage ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                        <div class="form-hint"><?= __('settings.default_language_description') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Images Tab -->
    <div class="tab-content" id="tab-images">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><?= __('settings.image_configuration') ?></h3>
        </div>
            <div class="admin-card-body">
                <h4 style="font-size: 14px; font-weight: 600; color: var(--admin-text); margin-bottom: 16px;">
                    <?= __('settings.full_images') ?? 'Full Size Images' ?>
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 32px;">
                    <div class="form-group">
                        <label for="image_max_width" class="form-label"><?= __('settings.image_max_width') ?></label>
                        <input type="number" class="form-control" id="image_max_width" name="image_max_width" 
                        value="<?= htmlspecialchars($currentSettings['image_max_width'] ?? 1920) ?>"
                               min="800" max="4096" required>
                        <div class="form-hint"><?= __('settings.image_max_width_description') ?></div>
                </div>
                
                    <div class="form-group">
                        <label for="image_max_height" class="form-label"><?= __('settings.image_max_height') ?></label>
                        <input type="number" class="form-control" id="image_max_height" name="image_max_height" 
                        value="<?= htmlspecialchars($currentSettings['image_max_height'] ?? 1080) ?>"
                               min="600" max="4096" required>
                        <div class="form-hint"><?= __('settings.image_max_height_description') ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="image_quality" class="form-label"><?= __('settings.image_quality_jpeg') ?></label>
                        <input type="number" class="form-control" id="image_quality" name="image_quality" 
                               value="<?= htmlspecialchars($currentSettings['image_quality'] ?? 85) ?>"
                               min="50" max="100" step="5" required>
                        <div class="form-hint"><?= __('settings.image_quality_description') ?></div>
                    </div>
                </div>
                
                <h4 style="font-size: 14px; font-weight: 600; color: var(--admin-text); margin-bottom: 16px;">
                    <?= __('settings.thumbnail_configuration') ?? 'Thumbnails' ?>
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="thumbnail_max_width" class="form-label"><?= __('settings.thumbnail_max_width') ?? 'Max Width (px)' ?></label>
                        <input type="number" class="form-control" id="thumbnail_max_width" name="thumbnail_max_width" 
                               value="<?= htmlspecialchars($currentSettings['thumbnail_max_width'] ?? 400) ?>"
                               min="100" max="800" required>
                        <div class="form-hint"><?= __('settings.thumbnail_width_description') ?? 'Max width for thumbnails' ?></div>
            </div>
            
                    <div class="form-group">
                        <label for="thumbnail_max_height" class="form-label"><?= __('settings.thumbnail_max_height') ?? 'Max Height (px)' ?></label>
                        <input type="number" class="form-control" id="thumbnail_max_height" name="thumbnail_max_height" 
                               value="<?= htmlspecialchars($currentSettings['thumbnail_max_height'] ?? 300) ?>"
                               min="100" max="600" required>
                        <div class="form-hint"><?= __('settings.thumbnail_height_description') ?? 'Max height for thumbnails' ?></div>
                </div>
                
                    <div class="form-group">
                        <label for="thumbnail_quality" class="form-label"><?= __('settings.thumbnail_quality') ?? 'Quality (%)' ?></label>
                        <input type="number" class="form-control" id="thumbnail_quality" name="thumbnail_quality" 
                               value="<?= htmlspecialchars($currentSettings['thumbnail_quality'] ?? 80) ?>"
                               min="50" max="100" step="5" required>
                        <div class="form-hint"><?= __('settings.thumbnail_quality_description') ?? 'Compression quality' ?></div>
                    </div>
                </div>
                
                <div class="alert alert-info alert-permanent mt-4 mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <span><?= __('settings.image_processing_note') ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Map Tab -->
    <div class="tab-content" id="tab-map">
        <div class="admin-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><?= __('settings.map_configuration') ?></h3>
        </div>
            <div class="admin-card-body">
                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="map_cluster_enabled" name="map_cluster_enabled" value="1"
                               <?= ($currentSettings['map_cluster_enabled'] ?? true) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= __('settings.enable_point_clustering') ?></span>
                    </label>
                    <div class="form-hint" style="margin-left: 44px;"><?= __('settings.enable_clustering_description') ?></div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="map_cluster_max_radius" class="form-label"><?= __('settings.cluster_max_radius') ?></label>
                        <input type="number" class="form-control" id="map_cluster_max_radius" name="map_cluster_max_radius" 
                               value="<?= htmlspecialchars($currentSettings['map_cluster_max_radius'] ?? 30) ?>"
                               min="10" max="200" required>
                        <div class="form-hint"><?= __('settings.cluster_max_radius_description') ?></div>
            </div>
            
                    <div class="form-group">
                        <label for="map_cluster_disable_at_zoom" class="form-label"><?= __('settings.disable_clustering_at_zoom') ?></label>
                        <input type="number" class="form-control" id="map_cluster_disable_at_zoom" name="map_cluster_disable_at_zoom" 
                               value="<?= htmlspecialchars($currentSettings['map_cluster_disable_at_zoom'] ?? 15) ?>"
                               min="1" max="20" required>
                        <div class="form-hint"><?= __('settings.disable_clustering_description') ?></div>
                    </div>
                </div>
                </div>
            </div>
            
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><?= __('settings.transport_colors') ?></h3>
            </div>
            <div class="admin-card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <?php
                    $transportTypes = [
                        'plane' => __('settings.transport_plane'),
                        'ship' => __('settings.transport_ship'),
                        'car' => __('settings.transport_car'),
                        'train' => __('settings.transport_train'),
                        'walk' => __('settings.transport_walk')
                    ];
                    $defaultColors = [
                        'plane' => '#FF4444',
                        'ship' => '#00AAAA',
                        'car' => '#4444FF',
                        'train' => '#FF8800',
                        'walk' => '#44FF44'
                    ];
                    foreach ($transportTypes as $type => $label):
                        $key = 'transport_color_' . $type;
                        $color = $currentSettings[$key] ?? $defaultColors[$type];
                    ?>
                    <div class="form-group">
                        <label for="<?= $key ?>" class="form-label"><?= $label ?></label>
                        <div style="display: flex; gap: 8px;">
                            <input type="color" class="form-control form-control-color" id="<?= $key ?>" name="<?= $key ?>" 
                                   value="<?= htmlspecialchars($color) ?>">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($color) ?>" 
                                   id="<?= $key ?>_text" readonly style="flex: 1;">
        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="alert alert-info alert-permanent mt-4 mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <span><?= __('settings.transport_color_description') ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Site Tab -->
    <div class="tab-content" id="tab-site">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><?= __('settings.public_site_configuration') ?></h3>
        </div>
            <div class="admin-card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="site_title" class="form-label">
                            <?= __('settings.site_title_required') ?> <span class="required">*</span>
                    </label>
                        <input type="text" class="form-control" id="site_title" name="site_title" 
                               value="<?= htmlspecialchars($currentSettings['site_title'] ?? 'Travel Map - Mis Viajes por el Mundo') ?>"
                               maxlength="100" required>
                        <div class="form-hint"><?= __('settings.site_title_description') ?></div>
                </div>
                
                    <div class="form-group">
                        <label for="site_favicon" class="form-label"><?= __('settings.site_favicon') ?></label>
                        <input type="text" class="form-control" id="site_favicon" name="site_favicon" 
                               value="<?= htmlspecialchars($currentSettings['site_favicon'] ?? '') ?>"
                               placeholder="/TravelMap/uploads/favicon.ico">
                        <div class="form-hint"><?= __('settings.site_favicon_description') ?></div>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="site_description" class="form-label"><?= __('settings.site_description_required') ?></label>
                    <textarea class="form-control" id="site_description" name="site_description" rows="2" maxlength="160"><?= htmlspecialchars($currentSettings['site_description'] ?? 'Explora mis viajes por el mundo con mapas interactivos, rutas y fotografías') ?></textarea>
                    <div class="form-hint"><?= __('settings.site_description_description') ?></div>
            </div>
            
                <div class="form-group">
                    <label for="site_analytics_code" class="form-label"><?= __('settings.site_analytics_code') ?></label>
                    <textarea class="form-control" id="site_analytics_code" name="site_analytics_code" rows="5" 
                              style="font-family: monospace; font-size: 12px;"
                              placeholder="<!-- Google Analytics, Facebook Pixel, etc. -->"><?= htmlspecialchars($currentSettings['site_analytics_code'] ?? '') ?></textarea>
                    <div class="form-hint"><?= __('settings.site_analytics_description') ?></div>
                </div>
                
                <div class="alert alert-info alert-permanent mt-4 mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <span><?= __('settings.site_changes_note') ?? 'These changes only affect the public map page (index.php). The admin panel remains unchanged.' ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Server Tab -->
    <div class="tab-content" id="tab-server">
        <?php
        // Gather server information
        $serverInfo = [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads'),
        ];
        
        // Check required extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'gd', 'json', 'mbstring', 'session'];
        $optionalExtensions = ['exif', 'curl', 'zip', 'imagick'];
        
        // GD info
        $gdInfo = function_exists('gd_info') ? gd_info() : [];
        
        // Database info
        $dbVersion = 'N/A';
        $dbSizeFormatted = 'N/A';
        $dbName = 'N/A';
        try {
            $db = getDB();
            $dbVersion = $db->query('SELECT VERSION()')->fetchColumn();
            
            // Get current database name
            $dbName = $db->query('SELECT DATABASE()')->fetchColumn();
            
            // Try to get database size (may fail on shared hosting)
            try {
                $stmt = $db->prepare("
                    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = ?
                ");
                $stmt->execute([$dbName]);
                $dbSizeResult = $stmt->fetchColumn();
                $dbSizeFormatted = $dbSizeResult ? $dbSizeResult . ' MB' : 'N/A';
            } catch (Exception $e) {
                // information_schema access denied on some hosts
                $dbSizeFormatted = 'N/A';
            }
        } catch (Exception $e) {
            // Database connection issue
        }
        
        // Disk space
        $uploadDir = __DIR__ . '/../uploads';
        $diskFree = @disk_free_space($uploadDir);
        $diskTotal = @disk_total_space($uploadDir);
        ?>
        
        <div class="admin-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px; margin-right: 8px;">
                        <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                        <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                        <line x1="6" y1="6" x2="6.01" y2="6"></line>
                        <line x1="6" y1="18" x2="6.01" y2="18"></line>
                    </svg>
                    <?= __('settings.server_information') ?? 'Server Information' ?>
                </h3>
            </div>
            <div class="admin-card-body">
                <div class="server-info-grid">
                    <div class="server-info-item">
                        <span class="server-info-label">PHP Version</span>
                        <span class="server-info-value"><?= htmlspecialchars($serverInfo['php_version']) ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">Server Software</span>
                        <span class="server-info-value"><?= htmlspecialchars($serverInfo['server_software']) ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">Operating System</span>
                        <span class="server-info-value"><?= htmlspecialchars($serverInfo['os']) ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">MySQL Version</span>
                        <span class="server-info-value"><?= htmlspecialchars($dbVersion) ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">Database</span>
                        <span class="server-info-value"><?= htmlspecialchars($dbName) ?></span>
                    </div>
                    <?php if ($dbSizeFormatted !== 'N/A'): ?>
                    <div class="server-info-item">
                        <span class="server-info-label">Database Size</span>
                        <span class="server-info-value"><?= htmlspecialchars($dbSizeFormatted) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="server-info-item">
                        <span class="server-info-label">Memory Limit</span>
                        <span class="server-info-value"><?= htmlspecialchars($serverInfo['memory_limit']) ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">Max Execution Time</span>
                        <span class="server-info-value"><?= htmlspecialchars($serverInfo['max_execution_time']) ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">Upload Max Filesize</span>
                        <span class="server-info-value"><?= htmlspecialchars($serverInfo['upload_max_filesize']) ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">Post Max Size</span>
                        <span class="server-info-value"><?= htmlspecialchars($serverInfo['post_max_size']) ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">Max File Uploads</span>
                        <span class="server-info-value"><?= htmlspecialchars($serverInfo['max_file_uploads']) ?></span>
                    </div>
                    <?php if ($diskFree && $diskTotal): ?>
                    <div class="server-info-item">
                        <span class="server-info-label">Disk Space (Uploads)</span>
                        <span class="server-info-value"><?= round($diskFree / 1024 / 1024 / 1024, 2) ?> GB free / <?= round($diskTotal / 1024 / 1024 / 1024, 2) ?> GB total</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="admin-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px; margin-right: 8px;">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                    </svg>
                    <?= __('settings.php_extensions') ?? 'PHP Extensions' ?>
                </h3>
            </div>
            <div class="admin-card-body">
                <h4 style="font-size: 13px; font-weight: 600; color: var(--admin-text); margin-bottom: 12px;">
                    <?= __('settings.required_extensions') ?? 'Required Extensions' ?>
                </h4>
                <div class="extensions-grid" style="margin-bottom: 20px;">
                    <?php foreach ($requiredExtensions as $ext): 
                        $loaded = extension_loaded($ext);
                    ?>
                    <div class="extension-item <?= $loaded ? 'loaded' : 'missing' ?>">
                        <span class="extension-status">
                            <?php if ($loaded): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            <?php endif; ?>
                        </span>
                        <span class="extension-name"><?= htmlspecialchars($ext) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <h4 style="font-size: 13px; font-weight: 600; color: var(--admin-text); margin-bottom: 12px;">
                    <?= __('settings.optional_extensions') ?? 'Optional Extensions' ?>
                </h4>
                <div class="extensions-grid">
                    <?php foreach ($optionalExtensions as $ext): 
                        $loaded = extension_loaded($ext);
                    ?>
                    <div class="extension-item <?= $loaded ? 'loaded' : 'optional-missing' ?>">
                        <span class="extension-status">
                            <?php if ($loaded): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <?php endif; ?>
                        </span>
                        <span class="extension-name"><?= htmlspecialchars($ext) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($gdInfo)): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px; margin-right: 8px;">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    <?= __('settings.gd_library_info') ?? 'GD Library Info' ?>
                </h3>
            </div>
            <div class="admin-card-body">
                <div class="server-info-grid">
                    <div class="server-info-item">
                        <span class="server-info-label">GD Version</span>
                        <span class="server-info-value"><?= htmlspecialchars($gdInfo['GD Version'] ?? 'Unknown') ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">JPEG Support</span>
                        <span class="server-info-value"><?= !empty($gdInfo['JPEG Support']) ? '✓ Yes' : '✗ No' ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">PNG Support</span>
                        <span class="server-info-value"><?= !empty($gdInfo['PNG Support']) ? '✓ Yes' : '✗ No' ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">GIF Support</span>
                        <span class="server-info-value"><?= !empty($gdInfo['GIF Read Support']) ? '✓ Yes' : '✗ No' ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">WebP Support</span>
                        <span class="server-info-value"><?= !empty($gdInfo['WebP Support']) ? '✓ Yes' : '✗ No' ?></span>
                    </div>
                    <div class="server-info-item">
                        <span class="server-info-label">FreeType Support</span>
                        <span class="server-info-value"><?= !empty($gdInfo['FreeType Support']) ? '✓ Yes' : '✗ No' ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Save Button -->
    <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
        <a href="index.php" class="btn btn-secondary"><?= __('common.cancel') ?></a>
        <button type="submit" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            <?= __('common.save') ?>
        </button>
    </div>
</form>

<script>
// Tab functionality
document.querySelectorAll('.tab-link').forEach(tab => {
    tab.addEventListener('click', function() {
        // Remove active from all tabs
        document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // Add active to clicked tab
        this.classList.add('active');
        document.getElementById('tab-' + this.dataset.tab).classList.add('active');
    });
});

// Sync color inputs
document.querySelectorAll('input[type="color"]').forEach(colorInput => {
    const textInput = document.getElementById(colorInput.id + '_text');
    if (textInput) {
    colorInput.addEventListener('input', function() {
        textInput.value = this.value.toUpperCase();
    });
    }
});

// Convert values before submit
document.getElementById('max_upload_size').addEventListener('change', function() {
    const mb = parseFloat(this.value);
    const bytes = Math.round(mb * 1048576);
    let hiddenInput = document.getElementById('max_upload_size_bytes');
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'max_upload_size_bytes';
        hiddenInput.name = 'max_upload_size';
        this.parentNode.appendChild(hiddenInput);
        this.removeAttribute('name');
    }
    hiddenInput.value = bytes;
});

document.getElementById('session_lifetime').addEventListener('change', function() {
    const hours = parseFloat(this.value);
    const seconds = Math.round(hours * 3600);
    let hiddenInput = document.getElementById('session_lifetime_seconds');
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'session_lifetime_seconds';
        hiddenInput.name = 'session_lifetime';
        this.parentNode.appendChild(hiddenInput);
        this.removeAttribute('name');
    }
    hiddenInput.value = seconds;
});

// Initialize values
document.getElementById('max_upload_size').dispatchEvent(new Event('change'));
document.getElementById('session_lifetime').dispatchEvent(new Event('change'));
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
