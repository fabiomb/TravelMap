<?php
/**
 * Dashboard - Panel de Administración
 * 
 * Página principal del panel de control con resumen de estadísticas
 */

require_once __DIR__ . '/../includes/header.php';

// Obtener estadísticas básicas
try {
    $db = getDB();
    
    // Contar viajes
    $stmt = $db->query('SELECT COUNT(*) as total FROM trips');
    $total_trips = $stmt->fetch()['total'];
    
    // Contar viajes públicos
    $stmt = $db->query('SELECT COUNT(*) as total FROM trips WHERE status = "public"');
    $public_trips = $stmt->fetch()['total'];
    
    // Contar puntos de interés
    $stmt = $db->query('SELECT COUNT(*) as total FROM points_of_interest');
    $total_points = $stmt->fetch()['total'];
    
    // Contar usuarios
    $stmt = $db->query('SELECT COUNT(*) as total FROM users');
    $total_users = $stmt->fetch()['total'];
    
    // Obtener actividad reciente (últimos 5 viajes y puntos)
    $stmt = $db->query('
        SELECT "trip" as type, title, created_at, color_hex as color
        FROM trips 
        ORDER BY created_at DESC 
        LIMIT 3
    ');
    $recent_trips = $stmt->fetchAll();
    
    $stmt = $db->query('
        SELECT "point" as type, p.title, p.created_at, t.color_hex as color
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
    
} catch (PDOException $e) {
    $total_trips = $public_trips = $total_points = $total_users = 0;
    $recent_activity = [];
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
                                <?php if ($item['type'] === 'trip'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/>
                                    </svg>
                                <?php else: ?>
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
                                    <?= $item['type'] === 'trip' ? __('navigation.trips') : __('navigation.points') ?>
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
