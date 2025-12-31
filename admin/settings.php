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
        if (isset($_POST['map_cluster_enabled'])) {
            $updates['map_cluster_enabled'] = [
                'value' => $_POST['map_cluster_enabled'] === '1',
                'type' => 'boolean'
            ];
        }
        
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
                'value' => $_POST['site_analytics_code'], // No trim para preservar formato
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

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="h3">
            <i class="bi bi-gear"></i> <?= __('settings.system_configuration') ?>
        </h1>
        <p class="text-muted"><?= __('settings.manage_global_options') ?></p>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['error_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<form method="POST" action="settings.php">
    <input type="hidden" name="action" value="update">
    
    <!-- Configuraciones Generales -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-sliders"></i> <?= __('settings.general_configuration') ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="max_upload_size" class="form-label">
                        <?= __('settings.max_upload_size_mb') ?>
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="max_upload_size" 
                        name="max_upload_size" 
                        value="<?= htmlspecialchars(round(($currentSettings['max_upload_size'] ?? 8388608) / 1048576, 2)) ?>"
                        min="1" 
                        max="100"
                        step="0.1"
                        required
                    >
                    <small class="form-text text-muted">
                        <?= __('settings.max_upload_description') ?>
                    </small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="session_lifetime" class="form-label">
                        <?= __('settings.session_lifetime_hours') ?>
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="session_lifetime" 
                        name="session_lifetime" 
                        value="<?= htmlspecialchars(round(($currentSettings['session_lifetime'] ?? 86400) / 3600, 1)) ?>"
                        min="1" 
                        max="720"
                        step="0.5"
                        required
                    >
                    <small class="form-text text-muted">
                        <?= __('settings.session_lifetime_description') ?>
                    </small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="timezone" class="form-label">
                        <?= __('settings.timezone_description') ?>
                    </label>
                    <select class="form-select" id="timezone" name="timezone" required>
                        <?php 
                        $currentTimezone = $currentSettings['timezone'] ?? 'America/Argentina/Buenos_Aires';
                        foreach ($timezones as $value => $label): 
                        ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= $value === $currentTimezone ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">
                        <?= __('settings.timezone_description') ?>
                    </small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="default_language" class="form-label">
                        <?= __('settings.default_language_description') ?>
                    </label>
                    <select class="form-select" id="default_language" name="default_language" required>
                        <?php 
                        $currentLanguage = $currentSettings['default_language'] ?? 'en';
                        $languages = [
                            'en' => 'English',
                            'es' => 'Español'
                        ];
                        foreach ($languages as $code => $name): 
                        ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $code === $currentLanguage ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">
                        <?= __('settings.default_language_description') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Configuraciones de Imágenes -->
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="bi bi-image"></i> <?= __('settings.image_configuration') ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="image_max_width" class="form-label">
                        <?= __('settings.image_max_width') ?>
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="image_max_width" 
                        name="image_max_width" 
                        value="<?= htmlspecialchars($currentSettings['image_max_width'] ?? 1920) ?>"
                        min="800" 
                        max="4096"
                        step="1"
                        required
                    >
                    <small class="form-text text-muted">
                        <?= __('settings.image_max_width_description') ?>
                    </small>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="image_max_height" class="form-label">
                        <?= __('settings.image_max_height') ?>
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="image_max_height" 
                        name="image_max_height" 
                        value="<?= htmlspecialchars($currentSettings['image_max_height'] ?? 1080) ?>"
                        min="600" 
                        max="4096"
                        step="1"
                        required
                    >
                    <small class="form-text text-muted">
                        <?= __('settings.image_max_height_description') ?>
                    </small>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="image_quality" class="form-label">
                        <?= __('settings.image_quality_jpeg') ?>
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="image_quality" 
                        name="image_quality" 
                        value="<?= htmlspecialchars($currentSettings['image_quality'] ?? 85) ?>"
                        min="50" 
                        max="100"
                        step="5"
                        required
                    >
                    <small class="form-text text-muted">
                        <?= __('settings.image_quality_description') ?>
                    </small>
                </div>
            </div>
            
            <hr class="my-4">
            
            <h6 class="text-muted mb-3">
                <i class="bi bi-card-image"></i> <?= __('settings.thumbnail_configuration') ?? 'Thumbnails (Miniaturas)' ?>
            </h6>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="thumbnail_max_width" class="form-label">
                        <?= __('settings.thumbnail_max_width') ?? 'Ancho máximo thumbnail (px)' ?>
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="thumbnail_max_width" 
                        name="thumbnail_max_width" 
                        value="<?= htmlspecialchars($currentSettings['thumbnail_max_width'] ?? 400) ?>"
                        min="100" 
                        max="800"
                        step="1"
                        required
                    >
                    <small class="form-text text-muted">
                        <?= __('settings.thumbnail_width_description') ?? 'Ancho máximo para miniaturas en popups del mapa' ?>
                    </small>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="thumbnail_max_height" class="form-label">
                        <?= __('settings.thumbnail_max_height') ?? 'Alto máximo thumbnail (px)' ?>
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="thumbnail_max_height" 
                        name="thumbnail_max_height" 
                        value="<?= htmlspecialchars($currentSettings['thumbnail_max_height'] ?? 300) ?>"
                        min="100" 
                        max="600"
                        step="1"
                        required
                    >
                    <small class="form-text text-muted">
                        <?= __('settings.thumbnail_height_description') ?? 'Alto máximo para miniaturas en popups del mapa' ?>
                    </small>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="thumbnail_quality" class="form-label">
                        <?= __('settings.thumbnail_quality') ?? 'Calidad thumbnail (%)' ?>
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="thumbnail_quality" 
                        name="thumbnail_quality" 
                        value="<?= htmlspecialchars($currentSettings['thumbnail_quality'] ?? 80) ?>"
                        min="50" 
                        max="100"
                        step="5"
                        required
                    >
                    <small class="form-text text-muted">
                        <?= __('settings.thumbnail_quality_description') ?? 'Calidad de compresión para miniaturas' ?>
                    </small>
                </div>
            </div>
            
            <div class="alert alert-info mb-0" role="alert">
                <strong><i class="bi bi-info-circle"></i> <?= __('common.info') ?>:</strong> 
                <?= __('settings.image_processing_note') ?>
                <br><small><?= __('settings.thumbnail_note') ?? 'Los thumbnails se generan automáticamente al subir imágenes y se usan en los popups del mapa para carga más rápida.' ?></small>
            </div>
        </div>
    </div>
    
    <!-- Configuraciones del Sitio Público -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="bi bi-globe"></i> <?= __('settings.public_site_configuration') ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="site_title" class="form-label">
                        <?= __('settings.site_title_required') ?> <span class="text-danger">*</span>
                    </label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="site_title" 
                        name="site_title" 
                        value="<?= htmlspecialchars($currentSettings['site_title'] ?? 'Travel Map - Mis Viajes por el Mundo') ?>"
                        maxlength="100"
                        required
                    >
                    <small class="form-text text-muted">
                        <?= __('settings.site_title_description') ?>
                    </small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="site_favicon" class="form-label">
                        <?= __('settings.site_favicon') ?>
                    </label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="site_favicon" 
                        name="site_favicon" 
                        value="<?= htmlspecialchars($currentSettings['site_favicon'] ?? '') ?>"
                        placeholder="/TravelMap/uploads/favicon.ico"
                    >
                    <small class="form-text text-muted">
                        <?= __('settings.site_favicon_description') ?>
                    </small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="site_description" class="form-label">
                        <?= __('settings.site_description_required') ?>
                    </label>
                    <textarea 
                        class="form-control" 
                        id="site_description" 
                        name="site_description" 
                        rows="2"
                        maxlength="160"
                    ><?= htmlspecialchars($currentSettings['site_description'] ?? 'Explora mis viajes por el mundo con mapas interactivos, rutas y fotografías') ?></textarea>
                    <small class="form-text text-muted">
                        <?= __('settings.site_description_description') ?>
                    </small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="site_analytics_code" class="form-label">
                        <?= __('settings.site_analytics_code') ?>
                    </label>
                    <textarea 
                        class="form-control font-monospace small" 
                        id="site_analytics_code" 
                        name="site_analytics_code" 
                        rows="6"
                        placeholder="<!-- Google Analytics, Facebook Pixel, etc. -->&#10;<script>&#10;  // Tu código aquí&#10;</script>"
                    ><?= htmlspecialchars($currentSettings['site_analytics_code'] ?? '') ?></textarea>
                    <small class="form-text text-muted">
                        <?= __('settings.site_analytics_description') ?>
                    </small>
                </div>
            </div>
            
            <div class="alert alert-info mb-0" role="alert">
                <strong><i class="bi bi-info-circle"></i> Nota:</strong> 
                Estos cambios afectarán únicamente a la página pública (<code>index.php</code>). El panel de administración no se verá afectado.
            </div>
        </div>
    </div>
    
    <!-- Configuraciones del Mapa -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="bi bi-map"></i> <?= __('settings.map_configuration') ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="form-check form-switch">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            id="map_cluster_enabled" 
                            name="map_cluster_enabled" 
                            value="1"
                            <?= ($currentSettings['map_cluster_enabled'] ?? true) ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="map_cluster_enabled">
                            <?= __('settings.enable_point_clustering') ?>
                        </label>
                    </div>
                    <small class="form-text text-muted">
                        <?= __('settings.enable_clustering_description') ?>
                    </small>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="map_cluster_max_radius" class="form-label">
                        <?= __('settings.cluster_max_radius') ?>
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="map_cluster_max_radius" 
                        name="map_cluster_max_radius" 
                        value="<?= htmlspecialchars($currentSettings['map_cluster_max_radius'] ?? 30) ?>"
                        min="10" 
                        max="200"
                        required
                    >
                    <small class="form-text text-muted">
                        <?= __('settings.cluster_max_radius_description') ?>
                    </small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="map_cluster_disable_at_zoom" class="form-label">
                        <?= __('settings.disable_clustering_at_zoom') ?>
                    </label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="map_cluster_disable_at_zoom" 
                        name="map_cluster_disable_at_zoom" 
                        value="<?= htmlspecialchars($currentSettings['map_cluster_disable_at_zoom'] ?? 15) ?>"
                        min="1" 
                        max="20"
                        required
                    >
                    <small class="form-text text-muted">
                        <?= __('settings.disable_clustering_description') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Colores de Transporte -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-palette"></i> <?= __('settings.transport_colors') ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="transport_color_plane" class="form-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/></svg>
                        <?= __('settings.transport_plane') ?>
                    </label>
                    <div class="input-group">
                        <input 
                            type="color" 
                            class="form-control form-control-color" 
                            id="transport_color_plane" 
                            name="transport_color_plane" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_plane'] ?? '#FF4444') ?>"
                            title="Color para rutas en avión"
                        >
                        <input 
                            type="text" 
                            class="form-control" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_plane'] ?? '#FF4444') ?>"
                            readonly
                        >
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="transport_color_ship" class="form-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" class="me-1"><path d="M2 21.1932C2.68524 22.2443 3.57104 22.2443 4.27299 21.1932C6.52985 17.7408 8.67954 23.6764 10.273 21.2321C12.703 17.5694 14.4508 23.9218 16.273 21.1932C18.6492 17.5582 20.1295 23.5776 22 21.5842"/><path d="M3.57228 17L2.07481 12.6457C1.80373 11.8574 2.30283 11 3.03273 11H20.8582C23.9522 11 19.9943 17 17.9966 17"/><path d="M18 11L15.201 7.50122C14.4419 6.55236 13.2926 6 12.0775 6H8C6.89543 6 6 6.89543 6 8V11"/><path d="M10 6V3C10 2.44772 9.55228 2 9 2H8"/></svg>
                        <?= __('settings.transport_ship') ?>
                    </label>
                    <div class="input-group">
                        <input 
                            type="color" 
                            class="form-control form-control-color" 
                            id="transport_color_ship" 
                            name="transport_color_ship" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_ship'] ?? '#00AAAA') ?>"
                            title="Color para rutas en barco"
                        >
                        <input 
                            type="text" 
                            class="form-control" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_ship'] ?? '#00AAAA') ?>"
                            readonly
                        >
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="transport_color_car" class="form-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M22 15.4222V18.5C22 18.9659 22 19.1989 21.9239 19.3827C21.8224 19.6277 21.6277 19.8224 21.3827 19.9239C21.1989 20 20.9659 20 20.5 20C20.0341 20 19.8011 20 19.6173 19.9239C19.3723 19.8224 19.1776 19.6277 19.0761 19.3827C19 19.1989 19 18.9659 19 18.5C19 18.0341 19 17.8011 18.9239 17.6173C18.8224 17.3723 18.6277 17.1776 18.3827 17.0761C18.1989 17 17.9659 17 17.5 17H6.5C6.03406 17 5.80109 17 5.61732 17.0761C5.37229 17.1776 5.17761 17.3723 5.07612 17.6173C5 17.8011 5 18.0341 5 18.5C5 18.9659 5 19.1989 4.92388 19.3827C4.82239 19.6277 4.62771 19.8224 4.38268 19.9239C4.19891 20 3.96594 20 3.5 20C3.03406 20 2.80109 20 2.61732 19.9239C2.37229 19.8224 2.17761 19.6277 2.07612 19.3827C2 19.1989 2 18.9659 2 18.5V15.4222C2 14.22 2 13.6188 2.17163 13.052C2.34326 12.4851 2.67671 11.9849 3.3436 10.9846L4 10L4.96154 7.69231C5.70726 5.90257 6.08013 5.0077 6.8359 4.50385C7.59167 4 8.56112 4 10.5 4H13.5C15.4389 4 16.4083 4 17.1641 4.50385C17.9199 5.0077 18.2927 5.90257 19.0385 7.69231L20 10L20.6564 10.9846C21.3233 11.9849 21.6567 12.4851 21.8284 13.052C22 13.6188 22 14.22 22 15.4222Z"/><path d="M2 8.5L4 10L5.76114 10.4403C5.91978 10.4799 6.08269 10.5 6.24621 10.5H17.7538C17.9173 10.5 18.0802 10.4799 18.2389 10.4403L20 10L22 8.5"/><path d="M18 14V14.01"/><path d="M6 14V14.01"/></svg>
                        <?= __('settings.transport_car') ?>
                    </label>
                    <div class="input-group">
                        <input 
                            type="color" 
                            class="form-control form-control-color" 
                            id="transport_color_car" 
                            name="transport_color_car" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_car'] ?? '#4444FF') ?>"
                            title="Color para rutas en auto"
                        >
                        <input 
                            type="text" 
                            class="form-control" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_car'] ?? '#4444FF') ?>"
                            readonly
                        >
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="transport_color_train" class="form-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" class="me-1"><path d="M2 3H6.73259C9.34372 3 10.6493 3 11.8679 3.40119C13.0866 3.80239 14.1368 4.57795 16.2373 6.12907L19.9289 8.85517C19.9692 8.88495 19.9894 8.89984 20.0084 8.91416C21.2491 9.84877 21.985 11.307 21.9998 12.8603C22 12.8841 22 12.9091 22 12.9593C22 12.9971 22 13.016 21.9997 13.032C21.9825 14.1115 21.1115 14.9825 20.032 14.9997C20.016 15 19.9971 15 19.9593 15H2"/><path d="M2 11H6.095C8.68885 11 9.98577 11 11.1857 11.451C12.3856 11.9019 13.3983 12.77 15.4238 14.5061L16 15"/><path d="M10 7H17"/><path d="M2 19H22"/><path d="M18 19V21"/><path d="M12 19V21"/><path d="M6 19V21"/></svg>
                        <?= __('settings.transport_train') ?>
                    </label>
                    <div class="input-group">
                        <input 
                            type="color" 
                            class="form-control form-control-color" 
                            id="transport_color_train" 
                            name="transport_color_train" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_train'] ?? '#FF8800') ?>"
                            title="Color para rutas en tren"
                        >
                        <input 
                            type="text" 
                            class="form-control" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_train'] ?? '#FF8800') ?>"
                            readonly
                        >
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="transport_color_walk" class="form-label">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M6 12.5L7.73811 9.89287C7.91034 9.63452 8.14035 9.41983 8.40993 9.26578L10.599 8.01487C11.1619 7.69323 11.8483 7.67417 12.4282 7.9641C13.0851 8.29255 13.4658 8.98636 13.7461 9.66522C14.2069 10.7814 15.3984 12 18 12"/><path d="M13 9L11.7772 14.5951M10.5 8.5L9.77457 11.7645C9.6069 12.519 9.88897 13.3025 10.4991 13.777L14 16.5L15.5 21"/><path d="M9.5 16L9 17.5L6.5 20.5"/><path d="M15 4.5C15 5.32843 14.3284 6 13.5 6C12.6716 6 12 5.32843 12 4.5C12 3.67157 12.6716 3 13.5 3C14.3284 3 15 3.67157 15 4.5Z"/></svg>
                        <?= __('settings.transport_walk') ?>
                    </label>
                    <div class="input-group">
                        <input 
                            type="color" 
                            class="form-control form-control-color" 
                            id="transport_color_walk" 
                            name="transport_color_walk" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_walk'] ?? '#44FF44') ?>"
                            title="Color para rutas caminando"
                        >
                        <input 
                            type="text" 
                            class="form-control" 
                            value="<?= htmlspecialchars($currentSettings['transport_color_walk'] ?? '#44FF44') ?>"
                            readonly
                        >
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info mt-3">
                <i class="bi bi-info-circle"></i> 
                <strong><?= __('common.info') ?>:</strong> <?= __('settings.transport_color_description') ?>
            </div>
        </div>
    </div>
    
    <!-- Botones de acción -->
    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i> <?= __('common.cancel') ?>
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> <?= __('common.save') ?>
        </button>
    </div>
</form>

<script>
// Sincronizar los selectores de color con los inputs de texto
document.querySelectorAll('input[type="color"]').forEach(colorInput => {
    const textInput = colorInput.nextElementSibling;
    
    colorInput.addEventListener('input', function() {
        textInput.value = this.value.toUpperCase();
    });
});

// Actualizar el valor en bytes cuando cambia el tamaño en MB
document.getElementById('max_upload_size').addEventListener('change', function() {
    const mb = parseFloat(this.value);
    const bytes = Math.round(mb * 1048576);
    // Crear un campo oculto con el valor en bytes
    let hiddenInput = document.getElementById('max_upload_size_bytes');
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'max_upload_size_bytes';
        hiddenInput.name = 'max_upload_size';
        this.parentNode.appendChild(hiddenInput);
        this.removeAttribute('name'); // Remover el name del input visible
    }
    hiddenInput.value = bytes;
});

// Actualizar el valor en segundos cuando cambia la duración en horas
document.getElementById('session_lifetime').addEventListener('change', function() {
    const hours = parseFloat(this.value);
    const seconds = Math.round(hours * 3600);
    // Crear un campo oculto con el valor en segundos
    let hiddenInput = document.getElementById('session_lifetime_seconds');
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = 'session_lifetime_seconds';
        hiddenInput.name = 'session_lifetime';
        this.parentNode.appendChild(hiddenInput);
        this.removeAttribute('name'); // Remover el name del input visible
    }
    hiddenInput.value = seconds;
});

// Inicializar los valores al cargar la página
document.getElementById('max_upload_size').dispatchEvent(new Event('change'));
document.getElementById('session_lifetime').dispatchEvent(new Event('change'));
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
