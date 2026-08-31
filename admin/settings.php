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
        if (isset($_POST['map_style'])) {
            $updates['map_style'] = [
                'value' => $_POST['map_style'],
                'type' => 'string'
            ];
        }
        
        if (isset($_POST['map_tiles_api_key'])) {
            $updates['map_tiles_api_key'] = [
                'value' => trim($_POST['map_tiles_api_key']),
                'type' => 'string'
            ];
        }

        if (isset($_POST['map_renderer'])) {
            $updates['map_renderer'] = [
                'value' => $_POST['map_renderer'],
                'type' => 'string'
            ];
        }

        if (isset($_POST['distance_unit'])) {
            $updates['distance_unit'] = [
                'value' => $_POST['distance_unit'],
                'type' => 'string'
            ];
        }
        
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

        // Trip Tags toggle (checkbox)
        $updates['trip_tags_enabled'] = [
            'value' => isset($_POST['trip_tags_enabled']) && $_POST['trip_tags_enabled'] === '1',
            'type' => 'boolean'
        ];

        // Colores de transporte
        $transportTypes = ['plane', 'ship', 'car', 'bike', 'train', 'walk', 'bus', 'aerial'];
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
        
        // Configuraciones de la página de viaje individual
        $updates['trip_page_enabled'] = [
            'value' => isset($_POST['trip_page_enabled']) && $_POST['trip_page_enabled'] === '1',
            'type' => 'boolean'
        ];
        $updates['trip_timeline_show_routes'] = [
            'value' => isset($_POST['trip_timeline_show_routes']) && $_POST['trip_timeline_show_routes'] === '1',
            'type' => 'boolean'
        ];
        $updates['trip_tooltip_show_image'] = [
            'value' => isset($_POST['trip_tooltip_show_image']) && $_POST['trip_tooltip_show_image'] === '1',
            'type' => 'boolean'
        ];
        $updates['trip_tooltip_show_trip_title'] = [
            'value' => isset($_POST['trip_tooltip_show_trip_title']) && $_POST['trip_tooltip_show_trip_title'] === '1',
            'type' => 'boolean'
        ];
        $updates['trip_tooltip_show_description'] = [
            'value' => isset($_POST['trip_tooltip_show_description']) && $_POST['trip_tooltip_show_description'] === '1',
            'type' => 'boolean'
        ];
        $updates['trip_tooltip_show_links'] = [
            'value' => isset($_POST['trip_tooltip_show_links']) && $_POST['trip_tooltip_show_links'] === '1',
            'type' => 'boolean'
        ];
        $updates['trip_tooltip_show_coordinates'] = [
            'value' => isset($_POST['trip_tooltip_show_coordinates']) && $_POST['trip_tooltip_show_coordinates'] === '1',
            'type' => 'boolean'
        ];
        if (isset($_POST['trip_poi_fly_zoom'])) {
            $updates['trip_poi_fly_zoom'] = [
                'value' => max(1, min(20, (int)$_POST['trip_poi_fly_zoom'])),
                'type' => 'number'
            ];
        }
        $allowedSpeeds = ['none', 'slow', 'normal', 'fast'];
        if (isset($_POST['trip_poi_fly_speed']) && in_array($_POST['trip_poi_fly_speed'], $allowedSpeeds)) {
            $updates['trip_poi_fly_speed'] = [
                'value' => $_POST['trip_poi_fly_speed'],
                'type' => 'string'
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
        
        // Footer note toggle (checkbox)
        $updates['show_footer_note'] = [
            'value' => isset($_POST['show_footer_note']) && $_POST['show_footer_note'] === '1',
            'type' => 'boolean'
        ];
        
        if (isset($_POST['footer_note_text'])) {
            $updates['footer_note_text'] = [
                'value' => trim($_POST['footer_note_text']),
                'type' => 'string'
            ];
        }
        
        // Require password toggle (checkbox)
        $updates['requires_pass'] = [
            'value' => isset($_POST['requires_pass']) && $_POST['requires_pass'] === '1',
            'type' => 'boolean'
        ];

        // Routing service settings
        $updates['routing_service_enabled'] = [
            'value' => isset($_POST['routing_service_enabled']) && $_POST['routing_service_enabled'] === '1',
            'type' => 'boolean'
        ];
        if (isset($_POST['routing_service_type'])) {
            $allowedServiceTypes = ['brouter_online', 'brouter_custom', 'google_maps'];
            if (in_array($_POST['routing_service_type'], $allowedServiceTypes)) {
                $updates['routing_service_type'] = [
                    'value' => $_POST['routing_service_type'],
                    'type' => 'string'
                ];
            }
        }
        if (isset($_POST['routing_brouter_url'])) {
            $updates['routing_brouter_url'] = [
                'value' => trim($_POST['routing_brouter_url']),
                'type' => 'string'
            ];
        }
        if (isset($_POST['routing_google_api_key'])) {
            $updates['routing_google_api_key'] = [
                'value' => trim($_POST['routing_google_api_key']),
                'type' => 'string'
            ];
        }

        if ($settingsModel->updateMultiple($updates)) {
            $_SESSION['success_message'] = __('settings.updated_successfully');
        } else {
            $_SESSION['error_message'] = __('settings.error_updating');
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = __('common.error') . ': ' . $e->getMessage();
    }
    
    // Preserve active tab after redirect
    $activeTab = isset($_POST['active_tab']) ? $_POST['active_tab'] : 'general';
    header('Location: settings.php#' . $activeTab);
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
        <button class="tab-link" data-tab="trip">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"></path>
            </svg>
            <?= __('settings.trip') ?? 'Trip' ?>
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
        <button class="tab-link" data-tab="routes">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="18" cy="5" r="3"></circle>
                <circle cx="6" cy="19" r="3"></circle>
                <path d="M12 5H8.5C6.567 5 5 6.567 5 8.5C5 10.433 6.567 12 8.5 12H15.5C17.433 12 19 13.567 19 15.5C19 17.433 17.433 19 15.5 19H12"></path>
            </svg>
            <?= __('settings.routes') ?? 'Routes' ?>
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

<form method="POST" action="settings.php" id="settingsForm">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="active_tab" id="active_tab" value="general">
    
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
                               min="100" max="1024" required>
                        <div class="form-hint"><?= __('settings.thumbnail_width_description') ?? 'Max width for thumbnails' ?></div>
            </div>
            
                    <div class="form-group">
                        <label for="thumbnail_max_height" class="form-label"><?= __('settings.thumbnail_max_height') ?? 'Max Height (px)' ?></label>
                        <input type="number" class="form-control" id="thumbnail_max_height" name="thumbnail_max_height" 
                               value="<?= htmlspecialchars($currentSettings['thumbnail_max_height'] ?? 300) ?>"
                               min="100" max="1024" required>
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
        <!-- Map Renderer Selection -->
        <div class="admin-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><?= __('settings.map_renderer') ?? 'Map Renderer' ?></h3>
            </div>
            <div class="admin-card-body">
                <?php $currentRenderer = $currentSettings['map_renderer'] ?? 'maplibre'; ?>
                <!-- Hidden select for form submission -->
                <input type="hidden" id="map_renderer" name="map_renderer" value="<?= htmlspecialchars($currentRenderer) ?>">
                
                <p class="form-hint" style="margin-bottom: 16px;"><?= __('settings.map_renderer_description') ?? 'MapLibre uses WebGL for curved flight arcs but may be heavy on some devices (AMD/Intel Windows). Leaflet is lighter and works better on older devices.' ?></p>
                
                <div class="renderer-cards" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <!-- MapLibre Card -->
                    <label class="renderer-card <?= $currentRenderer === 'maplibre' ? 'active' : '' ?>" data-renderer="maplibre" style="cursor: pointer; padding: 16px; border-radius: 10px; border: 2px solid var(--admin-border); background: transparent; transition: all 0.2s ease; display: block;">
                        <input type="radio" name="renderer_radio" value="maplibre" <?= $currentRenderer === 'maplibre' ? 'checked' : '' ?> style="display: none;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                            <svg width="28" height="28" viewBox="0 0 110.14971 110.14971" xmlns="http://www.w3.org/2000/svg">
                                <rect style="fill:#295daa" width="110.14971" height="110.14971" x="0" y="0" ry="23.5"/>
                                <path fill="#82b4fe" d="m 55.27037,15.88644 c -13.050838,-0.0847 -23.732067,9.79276 -23.811706,22.0173 -0.07038,10.82464 5.282141,16.10916 11.078368,21.83157 4.040982,3.98939 8.297338,8.1915 11.082068,14.63199 0.18786,0.15266 0.31354,0.25453 0.36751,0.30268 a 1.3663083,1.3663083 0 0 0 0.89906,0.34025 1.368425,1.368425 0 0 0 0.90381,-0.32834 c 0.0145,-0.013 0.0413,-0.0244 0.0741,-0.0384 0.09,-0.0386 0.22675,-0.0974 0.29739,-0.26008 2.77548,-6.37911 7.09242,-10.5246 11.21516,-14.4833 5.87084,-5.63747 11.34745,-10.8966 11.41757,-21.68842 0.0796,-12.22481 -10.47221,-22.24008 -23.52331,-22.32528 z m -0.22754,35.01734 c 6.46218,0.0421 11.73586,-5.24139 11.77845,-11.80094 0.0426,-6.55955 -5.16175,-11.91128 -11.6242,-11.95335 -6.462448,-0.0421 -11.735858,5.2414 -11.778721,11.80095 -0.0426,6.55955 5.161757,11.91127 11.624201,11.95334 z"/>
                                <path fill="#ffffff" d="m 46.583899,78.79378 c -1.078177,-0.007 -1.960033,1.18533 -1.969558,2.66303 l -0.04868,7.4795 c -0.0098,1.4777 0.856457,2.68129 1.934898,2.68843 l 16.506031,0.10743 c 1.07818,0.007 1.96003,-1.18534 1.96956,-2.66304 l 0.0487,-7.4795 c 0.01,-1.47796 -0.85646,-2.68155 -1.9349,-2.68843 z"/>
                            </svg>
                            <strong style="font-size: 15px;">MapLibre GL</strong>
                        </div>
                        <ul style="font-size: 12px; margin: 0; padding-left: 20px; color: var(--admin-text-muted); line-height: 1.8;">
                            <li><?= __('settings.maplibre_feature_1') ?? 'Curved 3D flight arcs' ?></li>
                            <li><?= __('settings.maplibre_feature_2') ?? 'Smooth WebGL rendering' ?></li>
                            <li><?= __('settings.maplibre_feature_3') ?? 'Modern visual effects' ?></li>
                            <li style="color: #f59e0b;"><?= __('settings.maplibre_warning') ?? 'Heavy on AMD/Intel Windows' ?></li>
                        </ul>
                    </label>
                    
                    <!-- Leaflet Card -->
                    <label class="renderer-card <?= $currentRenderer === 'leaflet' ? 'active' : '' ?>" data-renderer="leaflet" style="cursor: pointer; padding: 16px; border-radius: 10px; border: 2px solid var(--admin-border); background: transparent; transition: all 0.2s ease; display: block;">
                        <input type="radio" name="renderer_radio" value="leaflet" <?= $currentRenderer === 'leaflet' ? 'checked' : '' ?> style="display: none;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                            <svg width="28" height="28" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                                <path fill="#3d9970" d="M22.938 2.076l-11.522 26.974-0.717-0.721z"/>
                                <path fill="#3d9970" d="M23.11 1.004c-0.444 0.717-10.537 5.923-13.566 10.808-3.029 4.886-3.894 8.732-2.795 12.349 1.098 3.615 3.198 3.453 4.394 4.645 1.026 1.314 2.609 2.151 4.389 2.151 0.362 0 0.716-0.035 1.059-0.101l-0.035 0.006c3.395-0.766 6.667-3.032 8.295-9.382 0.533-2.171 0.839-4.664 0.839-7.228 0-2.755-0.353-5.427-1.017-7.974l0.049 0.219c-0.492-2.18-1.046-4.016-1.712-5.798l0.1 0.305z"/>
                            </svg>
                            <strong style="font-size: 15px;">Leaflet</strong>
                        </div>
                        <ul style="font-size: 12px; margin: 0; padding-left: 20px; color: var(--admin-text-muted); line-height: 1.8;">
                            <li><?= __('settings.leaflet_feature_1') ?? 'Lightweight DOM rendering' ?></li>
                            <li><?= __('settings.leaflet_feature_2') ?? 'Works on all devices' ?></li>
                            <li><?= __('settings.leaflet_feature_3') ?? 'Lower memory usage' ?></li>
                            <li style="color:rgb(137, 24, 54);"><?= __('settings.leaflet_note') ?? 'Straight flight lines only' ?></li>
                        </ul>
                    </label>
                </div>
                
                <style>
                    .renderer-card:hover {
                        border-color: var(--admin-primary) !important;
                        background: rgba(37, 99, 235, 0.03) !important;
                    }
                    .renderer-card.active {
                        border-color: var(--admin-primary) !important;
                        background: rgba(37, 99, 235, 0.08) !important;
                        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
                    }
                </style>
            </div>
        </div>
        
        <div class="admin-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><?= __('settings.map_style') ?? 'Map Style' ?></h3>
            </div>
            <div class="admin-card-body">
                <?php
                $mapStyles = [
                    'voyager' => [
                        'name' => __('settings.map_style_voyager') ?? 'Voyager (Colorful)',
                        'description' => __('settings.map_style_voyager_desc') ?? 'Classic look with green vegetation, brown terrain, blue water'
                    ],
                    'positron' => [
                        'name' => __('settings.map_style_positron') ?? 'Positron (Light)',
                        'description' => __('settings.map_style_positron_desc') ?? 'Light grey minimal style'
                    ],
                    'dark-matter' => [
                        'name' => __('settings.map_style_dark_matter') ?? 'Dark Matter',
                        'description' => __('settings.map_style_dark_matter_desc') ?? 'Dark theme for night mode'
                    ],
                    'osm-liberty' => [
                        'name' => __('settings.map_style_osm_liberty') ?? 'OSM Liberty',
                        'description' => __('settings.map_style_osm_liberty_desc') ?? 'OpenStreetMap classic style'
                    ]
                ];
                $currentMapStyle = $currentSettings['map_style'] ?? 'voyager';
                ?>
                <!-- Hidden input for form submission -->
                <input type="hidden" id="map_style" name="map_style" value="<?= htmlspecialchars($currentMapStyle) ?>">
                
                <div class="map-style-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
                    <?php foreach ($mapStyles as $styleKey => $styleInfo): 
                        $isActive = $styleKey === $currentMapStyle;
                    ?>
                    <label class="map-style-card <?= $isActive ? 'active' : '' ?>" data-style="<?= $styleKey ?>" style="cursor: pointer; display: flex; flex-direction: column; align-items: center; padding: 12px; border: 2px solid var(--admin-border); border-radius: 10px; transition: all 0.2s;">
                        <div class="style-preview-box style-<?= $styleKey ?>" style="width: 100%; height: 70px; border-radius: 6px; margin-bottom: 10px;"></div>
                        <span style="font-size: 13px; font-weight: 500; text-align: center;"><?= htmlspecialchars($styleInfo['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                
                <style>
                    .map-style-card:hover {
                        border-color: var(--admin-primary) !important;
                        background: rgba(37, 99, 235, 0.03);
                    }
                    .map-style-card.active {
                        border-color: var(--admin-primary) !important;
                        background: rgba(37, 99, 235, 0.08);
                        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
                    }
                    .style-preview-box {
                        box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
                    }
                    .style-voyager {
                        background: linear-gradient(135deg, #a8d5a2 0%, #f5deb3 50%, #87ceeb 100%);
                    }
                    .style-positron {
                        background: linear-gradient(135deg, #e8e8e8 0%, #f5f5f5 50%, #ddd 100%);
                    }
                    .style-dark-matter {
                        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f0f0f 100%);
                    }
                    .style-osm-liberty {
                        background: linear-gradient(135deg, #c5e8b7 0%, #fff2cc 50%, #b3d9ff 100%);
                    }
                </style>

                <div class="form-group" style="margin-top: 24px;">
                    <label for="map_tiles_api_key" class="form-label"><?= __('settings.map_tiles_api_key') ?? 'CARTO API Key' ?></label>
                    <input type="text" class="form-control" id="map_tiles_api_key" name="map_tiles_api_key"
                           value="<?= htmlspecialchars($currentSettings['map_tiles_api_key'] ?? '') ?>"
                           placeholder="<?= __('settings.map_tiles_api_key_placeholder') ?? 'Paste your CARTO API key' ?>"
                           autocomplete="off" spellcheck="false">
                    <div class="form-hint"><?= __('settings.map_tiles_api_key_desc') ?? 'Required by the Voyager, Positron and Dark Matter styles: CARTO no longer serves basemap tiles without a key. Get one from your account at carto.com. OSM Liberty does not need a key.' ?></div>
                </div>

                <div class="alert alert-warning alert-permanent mb-0" style="margin-top: 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <span><?= __('settings.map_tiles_api_key_note') ?? 'Without a valid key the CARTO styles render a blank map. The key travels to the browser with every tile request, so restrict it by domain in your CARTO account.' ?></span>
                </div>
            </div>
        </div>
        
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

                <div class="form-group" style="margin-top: 24px;">
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="trip_tags_enabled" name="trip_tags_enabled" value="1"
                               <?= ($currentSettings['trip_tags_enabled'] ?? true) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= __('settings.trip_tags_enabled', 'Habilitar sistema de etiquetas (Tags)') ?></span>
                    </label>
                    <div class="form-hint" style="margin-left: 44px;"><?= __('settings.trip_tags_enabled_desc', 'Muestra las etiquetas en el mapa público y permite gestionarlas en el administrador.') ?></div>
                </div>

                <div class="form-group" style="margin-top: 24px;">
                    <label for="distance_unit" class="form-label"><?= __('settings.distance_unit_fallback') ?? 'Unidad de Distancia (Servidor / Predeterminada)' ?></label>
                    <select class="form-control form-select" id="distance_unit" name="distance_unit">
                        <option value="km" <?= ($currentSettings['distance_unit'] ?? 'km') === 'km' ? 'selected' : '' ?>><?= __('settings.unit_km') ?? 'Kilómetros (km)' ?></option>
                        <option value="mi" <?= ($currentSettings['distance_unit'] ?? 'km') === 'mi' ? 'selected' : '' ?>><?= __('settings.unit_mi') ?? 'Millas (mi)' ?></option>
                    </select>
                    <div class="form-hint"><?= __('settings.distance_unit_fallback_description') ?? 'Unidad usada como respaldo si no se detecta la preferencia del navegador del usuario.' ?></div>
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
                        'bike' => __('settings.transport_bike'),
                        'train' => __('settings.transport_train'),
                        'walk' => __('settings.transport_walk'),
                        'bus' => __('settings.transport_bus'),
                        'aerial' => __('settings.transport_aerial')
                    ];
                    $defaultColors = [
                        'plane' => '#FF4444',
                        'ship' => '#00AAAA',
                        'car' => '#4444FF',
                        'bike' => '#b88907',
                        'train' => '#FF8800',
                        'walk' => '#44FF44',
                        'bus' => '#9C27B0',
                        'aerial' => '#E91E63'
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
    
    <!-- Trip Tab -->
    <div class="tab-content" id="tab-trip">
        <div class="admin-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><?= __('settings.trip_page_settings', 'Trip Page') ?></h3>
            </div>
            <div class="admin-card-body">
                <div class="form-group">
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="trip_page_enabled" name="trip_page_enabled" value="1"
                               <?= ($currentSettings['trip_page_enabled'] ?? true) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= __('settings.trip_page_enabled', 'Habilitar página dedicada de viaje') ?></span>
                    </label>
                    <div class="form-hint" style="margin-left: 44px;"><?= __('settings.trip_page_enabled_desc', 'Muestra un enlace a la página detallada de cada viaje en el mapa y en el listado. Si está desactivado, la página trip.php no es accesible.') ?></div>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="trip_timeline_show_routes" name="trip_timeline_show_routes" value="1"
                               <?= ($currentSettings['trip_timeline_show_routes'] ?? false) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= __('settings.trip_timeline_show_routes') ?></span>
                    </label>
                    <div class="form-hint" style="margin-left: 44px;"><?= __('settings.trip_timeline_show_routes_desc') ?></div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><?= __('settings.trip_tooltip_settings', 'Popup de puntos de interés') ?></h3>
            </div>
            <div class="admin-card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">

                    <!-- Toggles -->
                    <div>
                        <p class="form-hint" style="margin-bottom: 20px;"><?= __('settings.trip_tooltip_settings_desc', 'Configura qué información se muestra en el popup al hacer clic sobre un punto en la página individual del viaje. El título del punto siempre se muestra. En el mapa general siempre se muestra todo.') ?></p>
                        <div class="form-group">
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input tooltip-toggle" id="trip_tooltip_show_image" name="trip_tooltip_show_image" value="1"
                                       <?= ($currentSettings['trip_tooltip_show_image'] ?? true) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= __('settings.trip_tooltip_show_image', 'Imagen de cabecera') ?></span>
                            </label>
                            <div class="form-hint" style="margin-left: 44px;"><?= __('settings.trip_tooltip_show_image_desc', 'Muestra la miniatura de la imagen del punto en el popup.') ?></div>
                        </div>

                        <div class="form-group" style="margin-top: 16px;">
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input tooltip-toggle" id="trip_tooltip_show_trip_title" name="trip_tooltip_show_trip_title" value="1"
                                       <?= ($currentSettings['trip_tooltip_show_trip_title'] ?? false) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= __('settings.trip_tooltip_show_trip_title', 'Título del viaje') ?></span>
                            </label>
                            <div class="form-hint" style="margin-left: 44px;"><?= __('settings.trip_tooltip_show_trip_title_desc', 'Muestra el nombre del viaje en el popup del punto.') ?></div>
                        </div>

                        <div class="form-group" style="margin-top: 16px;">
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input tooltip-toggle" id="trip_tooltip_show_description" name="trip_tooltip_show_description" value="1"
                                       <?= ($currentSettings['trip_tooltip_show_description'] ?? true) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= __('settings.trip_tooltip_show_description', 'Descripción') ?></span>
                            </label>
                            <div class="form-hint" style="margin-left: 44px;"><?= __('settings.trip_tooltip_show_description_desc', 'Muestra la descripción del punto en el popup.') ?></div>
                        </div>

                        <div class="form-group" style="margin-top: 16px;">
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input tooltip-toggle" id="trip_tooltip_show_links" name="trip_tooltip_show_links" value="1"
                                       <?= ($currentSettings['trip_tooltip_show_links'] ?? true) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= __('settings.trip_tooltip_show_links', 'Links') ?></span>
                            </label>
                            <div class="form-hint" style="margin-left: 44px;"><?= __('settings.trip_tooltip_show_links_desc', 'Muestra los enlaces externos del punto en el popup.') ?></div>
                        </div>

                        <div class="form-group" style="margin-top: 16px;">
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input tooltip-toggle" id="trip_tooltip_show_coordinates" name="trip_tooltip_show_coordinates" value="1"
                                       <?= ($currentSettings['trip_tooltip_show_coordinates'] ?? true) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= __('settings.trip_tooltip_show_coordinates', 'Coordenadas') ?></span>
                            </label>
                            <div class="form-hint" style="margin-left: 44px;"><?= __('settings.trip_tooltip_show_coordinates_desc', 'Muestra las coordenadas geográficas del punto en el popup.') ?></div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div>
                        <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--admin-text-muted); margin-bottom: 12px;"><?= __('settings.preview', 'Preview') ?></div>
                        <div style="background: #e8edf2; border-radius: 12px; padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 200px;">
                            <div id="tooltip-preview" style="
                                width: 260px;
                                background: #fff;
                                border-radius: 10px;
                                box-shadow: 0 4px 20px rgba(0,0,0,0.18);
                                overflow: hidden;
                                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                                font-size: 14px;
                            ">
                                <!-- Image -->
                                <div id="prev-image" style="width:100%; height:130px; overflow:hidden; display:block;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="130" viewBox="0 0 260 130" preserveAspectRatio="xMidYMid slice">
                                        <defs>
                                            <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#87CEEB"/>
                                                <stop offset="100%" stop-color="#b8dff5"/>
                                            </linearGradient>
                                            <linearGradient id="mtn1" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#6b7c64"/>
                                                <stop offset="100%" stop-color="#4a5c43"/>
                                            </linearGradient>
                                            <linearGradient id="mtn2" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#8a9b82"/>
                                                <stop offset="100%" stop-color="#5a7050"/>
                                            </linearGradient>
                                            <linearGradient id="snow" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#ffffff"/>
                                                <stop offset="60%" stop-color="#dce8f0"/>
                                            </linearGradient>
                                        </defs>
                                        <!-- Sky -->
                                        <rect width="260" height="130" fill="url(#sky)"/>
                                        <!-- Sun -->
                                        <circle cx="200" cy="28" r="16" fill="#FFD700" opacity="0.85"/>
                                        <circle cx="200" cy="28" r="22" fill="#FFD700" opacity="0.18"/>
                                        <!-- Clouds -->
                                        <ellipse cx="60" cy="22" rx="28" ry="10" fill="white" opacity="0.7"/>
                                        <ellipse cx="80" cy="18" rx="20" ry="9" fill="white" opacity="0.7"/>
                                        <ellipse cx="40" cy="20" rx="18" ry="8" fill="white" opacity="0.6"/>
                                        <!-- Back mountain -->
                                        <polygon points="30,100 100,38 170,100" fill="url(#mtn2)"/>
                                        <!-- Front mountain left -->
                                        <polygon points="-10,130 70,45 150,130" fill="url(#mtn1)"/>
                                        <!-- Front mountain right -->
                                        <polygon points="110,130 190,50 270,130" fill="url(#mtn1)"/>
                                        <!-- Snow caps -->
                                        <polygon points="70,45 80,58 60,58" fill="url(#snow)" opacity="0.9"/>
                                        <polygon points="190,50 202,65 178,65" fill="url(#snow)" opacity="0.9"/>
                                        <polygon points="100,38 111,53 89,53" fill="url(#snow)" opacity="0.8"/>
                                        <!-- Ground -->
                                        <rect y="110" width="260" height="20" fill="#4a7a3a" opacity="0.6"/>
                                    </svg>
                                </div>
                                <!-- Content -->
                                <div style="padding: 13px 14px 12px;">
                                    <!-- Title — always visible -->
                                    <div style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 7px;">Machu Picchu</div>
                                    <!-- Type badge -->
                                    <span style="display:inline-flex; align-items:center; gap:4px; background:#4ECDC4; color:#1e293b; border-radius:50rem; padding:3px 9px; font-size:11px; font-weight:600; margin-bottom:8px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="11" height="11" fill="currentColor" stroke="none"><path d="M8.31253 4.7812L7.6885 4.36517V4.36517L8.31253 4.7812ZM7.5 6V6.75C7.75076 6.75 7.98494 6.62467 8.12404 6.41603L7.5 6ZM2.17224 8.83886L1.45453 8.62115L2.17224 8.83886ZM4.83886 6.17224L4.62115 5.45453H4.62115L4.83886 6.17224ZM3.46243 20.092L3.93822 19.5123L3.93822 19.5123L3.46243 20.092ZM2.90796 19.5376L3.48772 19.0618L3.48772 19.0618L2.90796 19.5376ZM21.092 19.5376L20.5123 19.0618L20.5123 19.0618L21.092 19.5376ZM20.5376 20.092L20.0618 19.5123L20.0618 19.5123L20.5376 20.092ZM14.0195 3.89791C14.3847 4.09336 14.8392 3.95575 15.0346 3.59054C15.2301 3.22534 15.0924 2.77084 14.7272 2.57539L14.0195 3.89791ZM22.5455 8.62115C22.4252 8.22477 22.0064 8.00092 21.61 8.12116C21.2137 8.2414 20.9898 8.6602 21.1101 9.05658L22.5455 8.62115ZM21.25 11.5V13.5H22.75V11.5H21.25ZM14.5 20.25H9.5V21.75H14.5V20.25ZM2.75 13.5V11.5H1.25V13.5H2.75ZM12.3593 2.25H11.6407V3.75H12.3593V2.25ZM7.6885 4.36517L6.87596 5.58397L8.12404 6.41603L8.93657 5.19722L7.6885 4.36517ZM11.6407 2.25C11.1305 2.25 10.6969 2.24925 10.3369 2.28282C9.96142 2.31783 9.61234 2.39366 9.27276 2.57539L9.98055 3.89791C10.0831 3.84299 10.2171 3.80049 10.4762 3.77634C10.7506 3.75075 11.1031 3.75 11.6407 3.75V2.25ZM8.93657 5.19722C9.23482 4.74985 9.43093 4.45704 9.60448 4.24286C9.76825 4.04074 9.87794 3.95282 9.98055 3.89791L9.27276 2.57539C8.93318 2.75713 8.67645 3.00553 8.43904 3.29853C8.2114 3.57947 7.97154 3.94062 7.6885 4.36517L8.93657 5.19722ZM2.75 11.5C2.75 10.0499 2.75814 9.49107 2.88994 9.05657L1.45453 8.62115C1.24186 9.32224 1.25 10.159 1.25 11.5H2.75ZM7.5 5.25C6.159 5.25 5.32224 5.24186 4.62115 5.45453L5.05657 6.88994C5.49107 6.75814 6.04987 6.75 7.5 6.75V5.25ZM2.88994 9.05657C3.20503 8.01787 4.01787 7.20503 5.05657 6.88994L4.62115 5.45453C3.10304 5.91505 1.91505 7.10304 1.45453 8.62115L2.88994 9.05657ZM9.5 20.25C7.83789 20.25 6.65724 20.2488 5.75133 20.1417C4.86197 20.0366 4.33563 19.8384 3.93822 19.5123L2.98663 20.6718C3.69558 21.2536 4.54428 21.5095 5.57525 21.6313C6.58966 21.7512 7.87463 21.75 9.5 21.75V20.25ZM1.25 13.5C1.25 15.1254 1.24877 16.4103 1.36868 17.4248C1.49054 18.4557 1.74638 19.3044 2.3282 20.0134L3.48772 19.0618C3.16158 18.6644 2.96343 18.138 2.85831 17.2487C2.75123 16.3428 2.75 15.1621 2.75 13.5H1.25ZM3.93822 19.5123C3.77366 19.3772 3.62277 19.2263 3.48772 19.0618L2.3282 20.0134C2.52558 20.2539 2.74612 20.4744 2.98663 20.6718L3.93822 19.5123ZM21.25 13.5C21.25 15.1621 21.2488 16.3428 21.1417 17.2487C21.0366 18.138 20.8384 18.6644 20.5123 19.0618L21.6718 20.0134C22.2536 19.3044 22.5095 18.4557 22.6313 17.4248C22.7512 16.4103 22.75 15.1254 22.75 13.5H21.25ZM14.5 21.75C16.1254 21.75 17.4103 21.7512 18.4248 21.6313C19.4557 21.5095 20.3044 21.2536 21.0134 20.6718L20.0618 19.5123C19.6644 19.8384 19.138 20.0366 18.2487 20.1417C17.3428 20.2488 16.1621 20.25 14.5 20.25V21.75ZM20.5123 19.0618C20.3772 19.2263 20.2263 19.3772 20.0618 19.5123L21.0134 20.6718C21.2539 20.4744 21.4744 20.2539 21.6718 20.0134L20.5123 19.0618ZM12.3593 3.75C12.8969 3.75 13.2494 3.75075 13.5238 3.77634C13.7829 3.80049 13.9169 3.84299 14.0195 3.89791L14.7272 2.57539C14.3877 2.39366 14.0386 2.31783 13.6631 2.28282C13.3031 2.24925 12.8695 2.25 12.3593 2.25V3.75ZM22.75 11.5C22.75 10.159 22.7581 9.32224 22.5455 8.62115L21.1101 9.05658C21.2419 9.49107 21.25 10.0499 21.25 11.5H22.75Z"/><path d="M16 13C16 15.2091 14.2091 17 12 17C9.79086 17 8 15.2091 8 13C8 10.7909 9.79086 9 12 9C14.2091 9 16 10.7909 16 13Z" stroke="currentColor" stroke-width="1.25" fill="none"/><path d="M17.9737 3.02148C17.9795 2.99284 18.0205 2.99284 18.0263 3.02148C18.3302 4.50808 19.4919 5.66984 20.9785 5.97368C21.0072 5.97954 21.0072 6.02046 20.9785 6.02632C19.4919 6.33016 18.3302 7.49192 18.0263 8.97852C18.0205 9.00716 17.9795 9.00716 17.9737 8.97852C17.6698 7.49192 16.5081 6.33016 15.0215 6.02632C14.9928 6.02046 14.9928 5.97954 15.0215 5.97368C16.5081 5.66984 17.6698 4.50808 17.9737 3.02148Z"/></svg>
                                        Visit
                                    </span>
                                    <!-- Trip title -->
                                    <div id="prev-trip-title" style="font-size:12px; color:#555; margin-bottom:5px; font-weight:600;">
                                        <span style="color:#e67e22;">Perú 2024</span>
                                    </div>
                                    <!-- Date — always visible -->
                                    <div style="font-size:11px; color:#888; margin-bottom:6px;">12 mar. 2024</div>
                                    <!-- Description -->
                                    <div id="prev-description" style="font-size:12px; color:#555; line-height:1.5; margin-bottom:8px;">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor.</div>
                                    <!-- Links -->
                                    <div id="prev-links" style="display:flex; gap:5px; margin-bottom:8px;">
                                        <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:6px;background:#f1f3f5;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                        </span>
                                        <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:6px;background:#f1f3f5;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#3498db" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                                        </span>
                                        <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:6px;background:#f1f3f5;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                        </span>
                                    </div>
                                    <!-- Coordinates -->
                                    <div id="prev-coords" style="font-size:10px; color:#aaa; font-family:monospace;">-13.163141, -72.544963</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /grid -->

                <style>
                    @media (max-width: 900px) {
                        #tooltip-preview-grid { grid-template-columns: 1fr !important; }
                    }
                </style>
            </div>
        </div>

        <div class="admin-card" style="margin-top: 24px;">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><?= __('settings.trip_navigation_settings', 'Map Navigation') ?></h3>
            </div>
            <div class="admin-card-body">
                <p class="form-hint" style="margin-bottom: 20px;"><?= __('settings.trip_navigation_settings_desc', 'Configure the zoom level and animation when navigating to a point of interest on the map.') ?></p>

                <div class="form-group" style="max-width: 480px;">
                    <label style="font-weight: 600; font-size: 14px; display: block; margin-bottom: 4px;" for="trip_poi_fly_zoom">
                        <?= __('settings.trip_poi_fly_zoom', 'Zoom level') ?>
                    </label>
                    <div class="form-hint" style="margin-bottom: 10px;"><?= __('settings.trip_poi_fly_zoom_desc', 'Zoom level applied when the map flies to a point of interest (1 = world view, 20 = maximum detail).') ?></div>
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <input type="range" id="trip_poi_fly_zoom" name="trip_poi_fly_zoom"
                               min="1" max="20" step="1"
                               value="<?= (int)($currentSettings['trip_poi_fly_zoom'] ?? 11) ?>"
                               style="flex: 1;"
                               oninput="document.getElementById('fly_zoom_val').textContent = this.value">
                        <span id="fly_zoom_val" style="font-size: 16px; font-weight: 700; min-width: 28px; text-align: center;">
                            <?= (int)($currentSettings['trip_poi_fly_zoom'] ?? 11) ?>
                        </span>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 20px; max-width: 320px;">
                    <label style="font-weight: 600; font-size: 14px; display: block; margin-bottom: 4px;" for="trip_poi_fly_speed">
                        <?= __('settings.trip_poi_fly_speed', 'Animation') ?>
                    </label>
                    <div class="form-hint" style="margin-bottom: 10px;"><?= __('settings.trip_poi_fly_speed_desc', 'Speed of the fly-to animation when navigating to a point.') ?></div>
                    <select id="trip_poi_fly_speed" name="trip_poi_fly_speed" class="form-select">
                        <?php
                        $currentSpeed = $currentSettings['trip_poi_fly_speed'] ?? 'normal';
                        $speeds = ['none' => __('settings.trip_poi_fly_speed_none', 'None (instant)'), 'slow' => __('settings.trip_poi_fly_speed_slow', 'Slow'), 'normal' => __('settings.trip_poi_fly_speed_normal', 'Normal'), 'fast' => __('settings.trip_poi_fly_speed_fast', 'Fast')];
                        foreach ($speeds as $val => $label):
                        ?>
                            <option value="<?= $val ?>" <?= $currentSpeed === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                
                <div class="form-group" style="margin-top: 24px;">
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="requires_pass" name="requires_pass" value="1"
                               <?= ($currentSettings['requires_pass'] ?? false) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= __('settings.requires_pass') ?? 'Require password for public access' ?></span>
                    </label>
                    <div class="form-hint" style="margin-left: 44px;"><?= __('settings.requires_pass_description') ?? 'Require a password to access the public map page' ?></div>
                </div>
                
                <div class="form-group" style="margin-top: 24px;">
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="show_footer_note" name="show_footer_note" value="1"
                               <?= ($currentSettings['show_footer_note'] ?? true) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= __('settings.show_footer_note') ?? 'Show footer note' ?></span>
                    </label>
                    <div class="form-hint" style="margin-left: 44px;"><?= __('settings.show_footer_note_description') ?? 'Display the footer note on the public map page' ?></div>
                </div>
                
                <div class="form-group" style="margin-top: 16px;">
                    <label for="footer_note_text" class="form-label"><?= __('settings.footer_note_text') ?? 'Footer text' ?></label>
                    <textarea class="form-control" id="footer_note_text" name="footer_note_text" rows="2"
                              style="font-family: monospace; font-size: 12px;"
                              placeholder="<?= __('settings.footer_note_placeholder') ?? 'Your custom footer text...' ?>"><?= htmlspecialchars($currentSettings['footer_note_text'] ?? '') ?></textarea>
                    <div class="form-hint"><?= __('settings.footer_note_text_description') ?? 'Supports HTML. Leave empty for default (with GitHub link).' ?></div>
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
    
    <!-- Routes Tab -->
    <div class="tab-content" id="tab-routes">
        <div class="admin-card" style="margin-bottom: 24px;">
            <div class="admin-card-header">
                <h3 class="admin-card-title"><?= __('settings.routing_service_configuration') ?? 'Routing Service Configuration' ?></h3>
            </div>
            <div class="admin-card-body">
                <p class="form-hint" style="margin-bottom: 20px;"><?= __('settings.routing_service_description') ?? 'Configure an external routing service to automatically generate routes between two points. When enabled, a button will appear in the map editor to create routes by specifying origin, destination and transport type.' ?></p>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="routing_service_enabled" name="routing_service_enabled" value="1"
                               <?= ($currentSettings['routing_service_enabled'] ?? false) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= __('settings.routing_service_enabled') ?? 'Enable Routing Service' ?></span>
                    </label>
                    <div class="form-hint" style="margin-left: 44px;"><?= __('settings.routing_service_enabled_desc') ?? 'When enabled, a button to create automated routes will appear in the map editor.' ?></div>
                </div>

                <div id="routing-service-options" style="<?= ($currentSettings['routing_service_enabled'] ?? false) ? '' : 'opacity: 0.5; pointer-events: none;' ?>">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="routing_service_type" class="form-label"><?= __('settings.routing_service_type') ?? 'Service Provider' ?></label>
                        <?php $currentServiceType = $currentSettings['routing_service_type'] ?? 'brouter_online'; ?>
                        <select class="form-control form-select" id="routing_service_type" name="routing_service_type">
                            <option value="brouter_online" <?= $currentServiceType === 'brouter_online' ? 'selected' : '' ?>>
                                <?= __('settings.routing_brouter_online') ?? 'BRouter (brouter.de - Free)' ?>
                            </option>
                            <option value="brouter_custom" <?= $currentServiceType === 'brouter_custom' ? 'selected' : '' ?>>
                                <?= __('settings.routing_brouter_custom') ?? 'BRouter (Self-hosted / Docker)' ?>
                            </option>
                            <option value="google_maps" <?= $currentServiceType === 'google_maps' ? 'selected' : '' ?>>
                                <?= __('settings.routing_google_maps') ?? 'Google Maps Directions API' ?>
                            </option>
                        </select>
                    </div>

                    <!-- BRouter custom URL -->
                    <div id="routing-brouter-custom-options" class="form-group" style="margin-bottom: 20px; <?= $currentServiceType === 'brouter_custom' ? '' : 'display: none;' ?>">
                        <label for="routing_brouter_url" class="form-label"><?= __('settings.routing_brouter_url') ?? 'BRouter Server URL' ?></label>
                        <input type="url" class="form-control" id="routing_brouter_url" name="routing_brouter_url"
                               value="<?= htmlspecialchars($currentSettings['routing_brouter_url'] ?? 'https://brouter.de/brouter') ?>"
                               placeholder="https://brouter.de/brouter">
                        <div class="form-hint"><?= __('settings.routing_brouter_url_desc') ?? 'Base URL of your BRouter instance. Default: https://brouter.de/brouter' ?></div>
                    </div>

                    <!-- Google Maps API Key -->
                    <div id="routing-google-options" class="form-group" style="margin-bottom: 20px; <?= $currentServiceType === 'google_maps' ? '' : 'display: none;' ?>">
                        <label for="routing_google_api_key" class="form-label"><?= __('settings.routing_google_api_key') ?? 'Google Maps API Key' ?></label>
                        <input type="password" class="form-control" id="routing_google_api_key" name="routing_google_api_key"
                               value="<?= htmlspecialchars($currentSettings['routing_google_api_key'] ?? '') ?>"
                               placeholder="AIza...">
                        <div class="form-hint"><?= __('settings.routing_google_api_key_desc') ?? 'API key with Directions API enabled. Get one at console.cloud.google.com' ?></div>
                    </div>

                    <div class="alert alert-info alert-permanent mb-0">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <span><?= __('settings.routing_note') ?? 'BRouter (brouter.de) is free and does not require an API key. For self-hosted instances, install BRouter via Docker. Google Maps requires a valid API key with billing enabled. Routes for Plane, Ship and Aerial are generated as great-circle arcs without an external service.' ?></span>
                    </div>
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
function activateTab(tabName) {
    // Remove active from all tabs
    document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    // Find and activate the target tab
    const tabButton = document.querySelector(`.tab-link[data-tab="${tabName}"]`);
    const tabContent = document.getElementById('tab-' + tabName);
    
    if (tabButton && tabContent) {
        tabButton.classList.add('active');
        tabContent.classList.add('active');
        document.getElementById('active_tab').value = tabName;
    }
}

// Handle tab clicks
document.querySelectorAll('.tab-link').forEach(tab => {
    tab.addEventListener('click', function() {
        const tabName = this.dataset.tab;
        activateTab(tabName);
        // Update URL hash without scrolling
        history.replaceState(null, null, '#' + tabName);
    });
});

// On page load, check for hash and activate that tab
(function() {
    const hash = window.location.hash.replace('#', '');
    if (hash && document.querySelector(`.tab-link[data-tab="${hash}"]`)) {
        activateTab(hash);
    }
})();

// Sync color inputs
document.querySelectorAll('input[type="color"]').forEach(colorInput => {
    const textInput = document.getElementById(colorInput.id + '_text');
    if (textInput) {
    colorInput.addEventListener('input', function() {
        textInput.value = this.value.toUpperCase();
    });
    }
});

// Renderer card selection
document.querySelectorAll('.renderer-card').forEach(card => {
    card.addEventListener('click', function() {
        const renderer = this.dataset.renderer;
        
        // Update hidden input
        document.getElementById('map_renderer').value = renderer;
        
        // Update active state on cards
        document.querySelectorAll('.renderer-card').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
    });
});

// Tooltip preview
(function() {
    const map = {
        'trip_tooltip_show_image':       'prev-image',
        'trip_tooltip_show_trip_title':  'prev-trip-title',
        'trip_tooltip_show_description': 'prev-description',
        'trip_tooltip_show_links':       'prev-links',
        'trip_tooltip_show_coordinates': 'prev-coords'
    };

    function syncPreview() {
        Object.entries(map).forEach(([toggleId, previewId]) => {
            const toggle  = document.getElementById(toggleId);
            const preview = document.getElementById(previewId);
            if (!toggle || !preview) return;
            preview.style.display = toggle.checked ? '' : 'none';
        });
    }

    document.querySelectorAll('.tooltip-toggle').forEach(el => {
        el.addEventListener('change', syncPreview);
    });

    syncPreview();
})();

// Map style card selection
document.querySelectorAll('.map-style-card').forEach(card => {
    card.addEventListener('click', function() {
        const style = this.dataset.style;
        
        // Update hidden input
        document.getElementById('map_style').value = style;
        
        // Update active state on cards
        document.querySelectorAll('.map-style-card').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
    });
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

// Routing service settings toggle
(function() {
    const enabledCheckbox = document.getElementById('routing_service_enabled');
    const optionsPanel = document.getElementById('routing-service-options');
    const serviceTypeSelect = document.getElementById('routing_service_type');
    const brouterCustomOptions = document.getElementById('routing-brouter-custom-options');
    const googleOptions = document.getElementById('routing-google-options');

    if (enabledCheckbox && optionsPanel) {
        enabledCheckbox.addEventListener('change', function() {
            optionsPanel.style.opacity = this.checked ? '1' : '0.5';
            optionsPanel.style.pointerEvents = this.checked ? 'auto' : 'none';
        });
    }

    if (serviceTypeSelect) {
        serviceTypeSelect.addEventListener('change', function() {
            if (brouterCustomOptions) brouterCustomOptions.style.display = this.value === 'brouter_custom' ? '' : 'none';
            if (googleOptions) googleOptions.style.display = this.value === 'google_maps' ? '' : 'none';
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
