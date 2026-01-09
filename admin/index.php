<?php
/**
 * Dashboard - Panel de Administración
 * 
 * Página principal del panel de control con resumen de estadísticas
 */

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../src/models/Route.php';
require_once __DIR__ . '/../src/models/Settings.php';

// Obtener estadísticas básicas
try {
    $db = getDB();
    
    // Contar viajes
    $stmt = $db->query('SELECT COUNT(*) as total FROM trips');
    $total_trips = $stmt->fetch()['total'];
    
    // Contar viajes publicados
    $stmt = $db->query('SELECT COUNT(*) as total FROM trips WHERE status = "published"');
    $public_trips = $stmt->fetch()['total'];
    
    // Contar puntos de interés
    $stmt = $db->query('SELECT COUNT(*) as total FROM points_of_interest');
    $total_points = $stmt->fetch()['total'];
    
    // Contar usuarios
    $stmt = $db->query('SELECT COUNT(*) as total FROM users');
    $total_users = $stmt->fetch()['total'];
    
    // Obtener actividad reciente (últimos 5 viajes y puntos)
    $stmt = $db->query('
        SELECT "trip" as item_type, title, created_at, color_hex as color
        FROM trips 
        ORDER BY created_at DESC 
        LIMIT 3
    ');
    $recent_trips = $stmt->fetchAll();
    
    $stmt = $db->query('
        SELECT "point" as item_type, p.title, p.created_at, t.color_hex as color, p.type as point_type
        FROM points_of_interest p
        LEFT JOIN trips t ON p.trip_id = t.id
        ORDER BY p.created_at DESC 
        LIMIT 3
    ');
    $recent_points = $stmt->fetchAll();
    
    // Combinar y ordenar por fecha
    $recent_activity = array_merge($recent_trips, $recent_points);
    usort($recent_activity, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    $recent_activity = array_slice($recent_activity, 0, 5);
    
    // Obtener estadísticas de viaje
    $routeModel = new Route();
    $settingsModel = new Settings($db);
    $travel_stats_base = $routeModel->getStatistics();
    $preferredUnit = $settingsModel->get('distance_unit', 'km');
    
    $travel_stats = [
        'plane' => ['value' => 0, 'unit' => 'mi', 'count' => 0, 'label' => __('admin.travel_stats_air') ?? 'Air Travel', 'color' => 'blue', 'accent' => '#3b82f6'],
        'ship' => ['value' => 0, 'unit' => 'nm', 'count' => 0, 'label' => __('admin.travel_stats_sea') ?? 'Sea Travel', 'color' => 'cyan', 'accent' => '#06b6d4'],
        'land' => ['value' => 0, 'unit' => $preferredUnit, 'count' => 0, 'label' => __('admin.travel_stats_land') ?? 'Land Travel', 'color' => 'green', 'accent' => '#10b981']
    ];
    
    $typeToCategory = [
        'plane' => 'plane',
        'ship' => 'ship',
        'car' => 'land', 'bike' => 'land', 'train' => 'land', 'walk' => 'land', 'bus' => 'land', 'aerial' => 'land'
    ];
    
    foreach ($travel_stats_base as $stat) {
        $cat = $typeToCategory[$stat['transport_type']] ?? 'land';
        $meters = (float)$stat['total_meters'];
        
        $conv = 0;
        if ($cat === 'plane') $conv = $meters / 1609.344;
        elseif ($cat === 'ship') $conv = $meters / 1852;
        else $conv = ($preferredUnit === 'mi') ? $meters / 1609.344 : $meters / 1000;
        
        $travel_stats[$cat]['value'] += $conv;
        $travel_stats[$cat]['count'] += (int)$stat['route_count'];
    }
    
} catch (PDOException $e) {
    $total_trips = $public_trips = $total_points = $total_users = 0;
    $recent_activity = [];
    $travel_stats = [];
}
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?= __('admin.dashboard') ?></h1>
        <p class="page-subtitle"><?= __('admin.welcome') ?>, <?= htmlspecialchars($username) ?>!</p>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card" style="--accent: #3b82f6;">
        <div class="stat-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-label"><?= __('admin.total_trips') ?></div>
            <div class="stat-value"><?= $total_trips ?></div>
        </div>
    </div>
    
    <div class="stat-card" style="--accent: #10b981;">
        <div class="stat-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-label"><?= __('admin.public_trips') ?></div>
            <div class="stat-value"><?= $public_trips ?></div>
        </div>
    </div>
    
    <div class="stat-card" style="--accent: #f59e0b;">
        <div class="stat-icon amber">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.5 9C14.5 10.3807 13.3807 11.5 12 11.5C10.6193 11.5 9.5 10.3807 9.5 9C9.5 7.61929 10.6193 6.5 12 6.5C13.3807 6.5 14.5 7.61929 14.5 9Z"/>
                <path d="M13.2574 17.4936C12.9201 17.8184 12.4693 18 12.0002 18C11.531 18 11.0802 17.8184 10.7429 17.4936C7.6543 14.5008 3.51519 11.1575 5.53371 6.30373C6.6251 3.67932 9.24494 2 12.0002 2C14.7554 2 17.3752 3.67933 18.4666 6.30373C20.4826 11.1514 16.3536 14.5111 13.2574 17.4936Z"/>
                <path d="M7 18C5.17107 18.4117 4 19.0443 4 19.7537C4 20.9943 7.58172 22 12 22C16.4183 22 20 20.9943 20 19.7537C20 19.0443 18.8289 18.4117 17 18"/>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-label"><?= __('admin.total_points') ?></div>
            <div class="stat-value"><?= $total_points ?></div>
        </div>
    </div>
    
    <div class="stat-card" style="--accent: #06b6d4;">
        <div class="stat-icon cyan">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-label"><?= __('admin.total_users') ?></div>
            <div class="stat-value"><?= $total_users ?></div>
        </div>
    </div>
</div>

<!-- Travel Statistics -->
<div class="admin-card mt-4 mb-4">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
            </svg>
            <?= __('admin.travel_stats') ?? 'Travel Statistics' ?>
        </h3>
    </div>
    <div class="admin-card-body">
        <div id="travel-stats-container" class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <?php 
            $has_stats = false;
            $stat_icons = [
                'plane' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/></svg>',
                'ship' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 21.1932C2.68524 22.2443 3.57104 22.2443 4.27299 21.1932C6.52985 17.7408 8.67954 23.6764 10.273 21.2321C12.703 17.5694 14.4508 23.9218 16.273 21.1932C18.6492 17.5582 20.1295 23.5776 22 21.5842"/><path d="M3.57228 17L2.07481 12.6457C1.80373 11.8574 2.30283 11 3.03273 11H20.8582C23.9522 11 19.9943 17 17.9966 17"/><path d="M18 11L15.201 7.50122C14.4419 6.55236 13.2926 6 12.0775 6H8C6.89543 6 6 6.89543 6 8V11"/><path d="M10 6V3C10 2.44772 9.55228 2 9 2H8"/></svg>',
                'land' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="19" r="3"></circle><path d="M12 5H8.5C6.567 5 5 6.567 5 8.5C5 10.433 6.567 12 8.5 12H15.5C17.433 12 19 13.567 19 15.5C19 17.433 17.433 19 15.5 19H12"></path></svg>'
            ];

            foreach ($travel_stats as $cat => $data): 
                if ($data['count'] === 0 && $cat !== 'plane') continue;
                $has_stats = true;
            ?>
                <div class="stat-card" style="--accent: <?= $data['accent'] ?>;">
                    <div class="stat-icon <?= $data['color'] ?>">
                        <?= $stat_icons[$cat] ?>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label"><?= $data['label'] ?></div>
                        <div class="stat-value" style="font-size: 1.5rem;">
                            <?= number_format($data['value'], 0) ?> 
                            <small style="font-size: 0.8rem; opacity: 0.7;"><?= $data['unit'] ?></small>
                        </div>
                        <div class="stat-meta" style="font-size: 0.75rem; color: #64748b;">
                            <?= $data['count'] ?> <?= __('admin.routes') ?? 'routes' ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$has_stats): ?>
                <p class="text-muted text-center w-100 p-4"><?= __('messages.no_data') ?? 'No travel routes found yet.' ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Quick Actions -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
                <?= __('admin.quick_actions') ?>
            </h3>
        </div>
        <div class="admin-card-body">
            <div class="quick-actions">
                <a href="trip_form.php" class="quick-action">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/>
                    </svg>
                    <span class="quick-action-label"><?= __('trips.new_trip') ?></span>
                </a>
                <a href="point_form.php" class="quick-action">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.5 9C14.5 10.3807 13.3807 11.5 12 11.5C10.6193 11.5 9.5 10.3807 9.5 9C9.5 7.61929 10.6193 6.5 12 6.5C13.3807 6.5 14.5 7.61929 14.5 9Z"/>
                        <path d="M13.2574 17.4936C12.9201 17.8184 12.4693 18 12.0002 18C11.531 18 11.0802 17.8184 10.7429 17.4936C7.6543 14.5008 3.51519 11.1575 5.53371 6.30373C6.6251 3.67932 9.24494 2 12.0002 2C14.7554 2 17.3752 3.67933 18.4666 6.30373C20.4826 11.1514 16.3536 14.5111 13.2574 17.4936Z"/>
                        <path d="M7 18C5.17107 18.4117 4 19.0443 4 19.7537C4 20.9943 7.58172 22 12 22C16.4183 22 20 20.9943 20 19.7537C20 19.0443 18.8289 18.4117 17 18"/>
                    </svg>
                    <span class="quick-action-label"><?= __('points.new_point') ?? 'New Point' ?></span>
                </a>
                <a href="import_flights.php" class="quick-action">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.0002 12H6.00024V19C6.00024 20.4142 6.00024 21.1213 6.43958 21.5607C6.87892 22 7.58603 22 9.00024 22H10.0002V12Z" />
                        <path d="M18.0002 15H10.0002V22H18.0002C19.4145 22 20.1216 22 20.5609 21.5607C21.0002 21.1213 21.0002 20.4142 21.0002 19V18C21.0002 16.5858 21.0002 15.8787 20.5609 15.4393C20.1216 15 19.4145 15 18.0002 15Z" />
                        <path d="M21 6L20 7M16.5 7H20M20 7L17 10H16M20 7V10.5" />
                        <path d="M12.2686 10.1181C11.9025 11.0296 11.7195 11.4854 11.3388 11.7427C10.9582 12 10.4671 12 9.4848 12H6.51178C5.5295 12 5.03836 12 4.65773 11.7427C4.27711 11.4854 4.09405 11.0296 3.72794 10.1181L3.57717 9.74278C3.07804 8.50009 2.82847 7.87874 3.12717 7.43937C3.42587 7 4.09785 7 5.44182 7H10.5548C11.8987 7 12.5707 7 12.8694 7.43937C13.1681 7.87874 12.9185 8.50009 12.4194 9.74278L12.2686 10.1181Z" />
                        <path d="M9.99616 7H6.00407C5.18904 5.73219 4.8491 5.09829 5.06258 4.59641C5.34685 4.13381 6.15056 4 7.61989 4H8.38063C9.84995 4 10.6537 4.13381 10.9379 4.59641C11.1514 5.09829 10.8112 5.73219 9.99616 7Z" />
                        <path d="M8 4V2" />
                    </svg>
                    <span class="quick-action-label"><?= __('navigation.import_flights') ?></span>
                </a>
                <a href="import_airbnb.php" class="quick-action">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 18.7753C10.3443 16.7754 9 15.5355 9 13.5C9 11.4645 10.5033 10 12.0033 10C13.5033 10 15 11.4645 15 13.5C15 15.5355 13.6557 16.7754 12 18.7753ZM12 18.7753C10 21.3198 6.02071 21.4621 4.34969 20.302C2.67867 19.1419 2.65485 16.7398 3.75428 14.1954C4.85371 11.651 6.31925 8.5977 9.25143 4.52665C10.2123 3.45799 10.8973 3 11.9967 3M12 18.7753C14 21.3198 17.9793 21.4621 19.6503 20.302C21.3213 19.1419 21.3451 16.7398 20.2457 14.1954C19.1463 11.651 17.6807 8.5977 14.7486 4.52665C13.7877 3.45799 13.1027 3 12.0033 3" />
                    </svg>
                    <span class="quick-action-label"><?= __('navigation.import_airbnb') ?></span>
                </a>
                <a href="<?= BASE_URL ?>/" target="_blank" class="quick-action">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5.25345 4.19584L4.02558 4.90813C3.03739 5.48137 2.54329 5.768 2.27164 6.24483C2 6.72165 2 7.30233 2 8.46368V16.6283C2 18.1542 2 18.9172 2.34226 19.3418C2.57001 19.6244 2.88916 19.8143 3.242 19.8773C3.77226 19.9719 4.42148 19.5953 5.71987 18.8421C6.60156 18.3306 7.45011 17.7994 8.50487 17.9435C8.98466 18.009 9.44231 18.2366 10.3576 18.6917L14.1715 20.588C14.9964 20.9982 15.004 21 15.9214 21H18C19.8856 21 20.8284 21 21.4142 20.4013C22 19.8026 22 18.8389 22 16.9117V10.1715C22 8.24423 22 7.2806 21.4142 6.68188C20.8284 6.08316 19.8856 6.08316 18 6.08316H15.9214C15.004 6.08316 14.9964 6.08139 14.1715 5.6712L10.8399 4.01463C9.44884 3.32297 8.75332 2.97714 8.01238 3.00117C7.27143 3.02521 6.59877 3.41542 5.25345 4.19584Z"/>
                        <path d="M8 3L8 17.5"/><path d="M15 6.5L15 20.5"/>
                    </svg>
                    <span class="quick-action-label"><?= __('admin.view_public_map') ?></span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <?= __('admin.recent_activity') ?>
            </h3>
        </div>
        <div class="admin-card-body">
            <?php if (empty($recent_activity)): ?>
                <p class="text-muted" style="text-align: center; padding: 20px 0;">
                    <?= __('messages.no_data') ?? 'No recent activity' ?>
                </p>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recent_activity as $item): 
                        $iconColor = $item['color'] ?? '#64748b';
                    ?>
                        <li class="activity-item">
                            <div class="activity-icon" style="color: <?= htmlspecialchars($iconColor) ?>; background: <?= htmlspecialchars($iconColor) ?>15;">
                                <?php if ($item['item_type'] === 'trip'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/>
                                    </svg>
                                <?php elseif (($item['point_type'] ?? '') === 'stay'): ?>
                                    <!-- Hotel/Stay icon (same as map) -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 4V20C3 20.9428 3 21.4142 3.29289 21.7071C3.58579 22 4.05719 22 5 22H19C19.9428 22 20.4142 22 20.7071 21.7071C21 21.4142 21 20.9428 21 20V4"/>
                                        <path d="M10.5 8V9.5M10.5 11V9.5M13.5 8V9.5M13.5 11V9.5M10.5 9.5H13.5"/>
                                        <path d="M14 22L14 17.9999C14 16.8954 13.1046 15.9999 12 15.9999C10.8954 15.9999 10 16.8954 10 17.9999V22"/>
                                        <path d="M2 4H8C8.6399 2.82727 10.1897 2 12 2C13.8103 2 15.3601 2.82727 16 4H22"/>
                                        <path d="M6 8H7M6 12H7M6 16H7"/>
                                        <path d="M17 8H18M17 12H18M17 16H18"/>
                                    </svg>
                                <?php elseif (($item['point_type'] ?? '') === 'food'): ?>
                                    <!-- Restaurant/Food icon (same as map) -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                                        <path d="M21 17C18.2386 17 16 14.7614 16 12C16 9.23858 18.2386 7 21 7"/>
                                        <path d="M21 21C16.0294 21 12 16.9706 12 12C12 7.02944 16.0294 3 21 3"/>
                                        <path d="M6 3L6 8M6 21L6 11"/>
                                        <path d="M3.5 8H8.5"/>
                                        <path d="M9 3L9 7.35224C9 12.216 3 12.2159 3 7.35207L3 3"/>
                                    </svg>
                                <?php elseif (($item['point_type'] ?? '') === 'visit'): ?>
                                    <!-- Camera/Visit icon (same as map) -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                        <path d="M8.31253 4.7812L7.6885 4.36517V4.36517L8.31253 4.7812ZM7.5 6V6.75C7.75076 6.75 7.98494 6.62467 8.12404 6.41603L7.5 6ZM2.17224 8.83886L1.45453 8.62115L2.17224 8.83886ZM4.83886 6.17224L4.62115 5.45453H4.62115L4.83886 6.17224ZM3.46243 20.092L3.93822 19.5123L3.93822 19.5123L3.46243 20.092ZM2.90796 19.5376L3.48772 19.0618L3.48772 19.0618L2.90796 19.5376ZM21.092 19.5376L20.5123 19.0618L20.5123 19.0618L21.092 19.5376ZM20.5376 20.092L20.0618 19.5123L20.0618 19.5123L20.5376 20.092ZM14.0195 3.89791C14.3847 4.09336 14.8392 3.95575 15.0346 3.59054C15.2301 3.22534 15.0924 2.77084 14.7272 2.57539L14.0195 3.89791ZM22.5455 8.62115C22.4252 8.22477 22.0064 8.00092 21.61 8.12116C21.2137 8.2414 20.9898 8.6602 21.1101 9.05658L22.5455 8.62115ZM21.25 11.5V13.5H22.75V11.5H21.25ZM14.5 20.25H9.5V21.75H14.5V20.25ZM2.75 13.5V11.5H1.25V13.5H2.75ZM12.3593 2.25H11.6407V3.75H12.3593V2.25ZM7.6885 4.36517L6.87596 5.58397L8.12404 6.41603L8.93657 5.19722L7.6885 4.36517ZM11.6407 2.25C11.1305 2.25 10.6969 2.24925 10.3369 2.28282C9.96142 2.31783 9.61234 2.39366 9.27276 2.57539L9.98055 3.89791C10.0831 3.84299 10.2171 3.80049 10.4762 3.77634C10.7506 3.75075 11.1031 3.75 11.6407 3.75V2.25ZM8.93657 5.19722C9.23482 4.74985 9.43093 4.45704 9.60448 4.24286C9.76825 4.04074 9.87794 3.95282 9.98055 3.89791L9.27276 2.57539C8.93318 2.75713 8.67645 3.00553 8.43904 3.29853C8.2114 3.57947 7.97154 3.94062 7.6885 4.36517L8.93657 5.19722ZM2.75 11.5C2.75 10.0499 2.75814 9.49107 2.88994 9.05657L1.45453 8.62115C1.24186 9.32224 1.25 10.159 1.25 11.5H2.75ZM7.5 5.25C6.159 5.25 5.32224 5.24186 4.62115 5.45453L5.05657 6.88994C5.49107 6.75814 6.04987 6.75 7.5 6.75V5.25ZM2.88994 9.05657C3.20503 8.01787 4.01787 7.20503 5.05657 6.88994L4.62115 5.45453C3.10304 5.91505 1.91505 7.10304 1.45453 8.62115L2.88994 9.05657ZM9.5 20.25C7.83789 20.25 6.65724 20.2488 5.75133 20.1417C4.86197 20.0366 4.33563 19.8384 3.93822 19.5123L2.98663 20.6718C3.69558 21.2536 4.54428 21.5095 5.57525 21.6313C6.58966 21.7512 7.87463 21.75 9.5 21.75V20.25ZM1.25 13.5C1.25 15.1254 1.24877 16.4103 1.36868 17.4248C1.49054 18.4557 1.74638 19.3044 2.3282 20.0134L3.48772 19.0618C3.16158 18.6644 2.96343 18.138 2.85831 17.2487C2.75123 16.3428 2.75 15.1621 2.75 13.5H1.25ZM3.93822 19.5123C3.77366 19.3772 3.62277 19.2263 3.48772 19.0618L2.3282 20.0134C2.52558 20.2539 2.74612 20.4744 2.98663 20.6718L3.93822 19.5123ZM21.25 13.5C21.25 15.1621 21.2488 16.3428 21.1417 17.2487C21.0366 18.138 20.8384 18.6644 20.5123 19.0618L21.6718 20.0134C22.2536 19.3044 22.5095 18.4557 22.6313 17.4248C22.7512 16.4103 22.75 15.1254 22.75 13.5H21.25ZM14.5 21.75C16.1254 21.75 17.4103 21.7512 18.4248 21.6313C19.4557 21.5095 20.3044 21.2536 21.0134 20.6718L20.0618 19.5123C19.6644 19.8384 19.138 20.0366 18.2487 20.1417C17.3428 20.2488 16.1621 20.25 14.5 20.25V21.75ZM20.5123 19.0618C20.3772 19.2263 20.2263 19.3772 20.0618 19.5123L21.0134 20.6718C21.2539 20.4744 21.4744 20.2539 21.6718 20.0134L20.5123 19.0618ZM12.3593 3.75C12.8969 3.75 13.2494 3.75075 13.5238 3.77634C13.7829 3.80049 13.9169 3.84299 14.0195 3.89791L14.7272 2.57539C14.3877 2.39366 14.0386 2.31783 13.6631 2.28282C13.3031 2.24925 12.8695 2.25 12.3593 2.25V3.75ZM22.75 11.5C22.75 10.159 22.7581 9.32224 22.5455 8.62115L21.1101 9.05658C21.2419 9.49107 21.25 10.0499 21.25 11.5H22.75Z"/>
                                        <path d="M16 13C16 15.2091 14.2091 17 12 17C9.79086 17 8 15.2091 8 13C8 10.7909 9.79086 9 12 9C14.2091 9 16 10.7909 16 13Z" stroke="currentColor" stroke-width="1.25" fill="none"/>
                                        <path d="M17.9737 3.02148C17.9795 2.99284 18.0205 2.99284 18.0263 3.02148C18.3302 4.50808 19.4919 5.66984 20.9785 5.97368C21.0072 5.97954 21.0072 6.02046 20.9785 6.02632C19.4919 6.33016 18.3302 7.49192 18.0263 8.97852C18.0205 9.00716 17.9795 9.00716 17.9737 8.97852C17.6698 7.49192 16.5081 6.33016 15.0215 6.02632C14.9928 6.02046 14.9928 5.97954 15.0215 5.97368C16.5081 5.66984 17.6698 4.50808 17.9737 3.02148Z"/>
                                    </svg>
                                <?php else: ?>
                                    <!-- Default point icon (other) -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14.5 9C14.5 10.3807 13.3807 11.5 12 11.5C10.6193 11.5 9.5 10.3807 9.5 9C9.5 7.61929 10.6193 6.5 12 6.5C13.3807 6.5 14.5 7.61929 14.5 9Z"/>
                                        <path d="M13.2574 17.4936C12.9201 17.8184 12.4693 18 12.0002 18C11.531 18 11.0802 17.8184 10.7429 17.4936C7.6543 14.5008 3.51519 11.1575 5.53371 6.30373C6.6251 3.67932 9.24494 2 12.0002 2C14.7554 2 17.3752 3.67933 18.4666 6.30373C20.4826 11.1514 16.3536 14.5111 13.2574 17.4936Z"/>
                                        <path d="M7 18C5.17107 18.4117 4 19.0443 4 19.7537C4 20.9943 7.58172 22 12 22C16.4183 22 20 20.9943 20 19.7537C20 19.0443 18.8289 18.4117 17 18"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?= htmlspecialchars($item['title']) ?>
                                </div>
                                <div class="activity-meta">
                                    <?php if ($item['item_type'] === 'trip'): ?>
                                        <?= __('navigation.trips') ?>
                                    <?php else: ?>
                                        <?= __('points.types.' . ($item['point_type'] ?? 'other')) ?? __('navigation.points') ?>
                                    <?php endif; ?>
                                    · <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
