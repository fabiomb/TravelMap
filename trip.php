<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/public_access.php';
require_once __DIR__ . '/src/models/Trip.php';
require_once __DIR__ . '/src/models/Route.php';
require_once __DIR__ . '/src/models/Point.php';
require_once __DIR__ . '/src/models/TripTag.php';
require_once __DIR__ . '/src/models/Link.php';
require_once __DIR__ . '/src/models/PoiImage.php';
require_once __DIR__ . '/src/helpers/FileHelper.php';
require_once __DIR__ . '/src/helpers/DateTimeHelper.php';
require_once __DIR__ . '/src/models/Settings.php';

// Verificar acceso al sitio público (con contraseña si es requerido)
$accessInfo = check_public_access();

// Validar ID del viaje
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("HTTP/1.0 404 Not Found");
    die("Trip ID missing or invalid");
}

$tripId = (int)$_GET['id'];

// Verificar que el viaje sea accesible para este usuario
if (!is_trip_accessible($tripId, $accessInfo)) {
    // Si no tiene acceso, mostrar página de login
    if (!$accessInfo['access']) {
        show_public_login_page(SITE_TITLE, $version, $accessInfo['error'] ?? null);
    } else {
        // Tiene acceso al sitio pero no a este viaje específico
        header("HTTP/1.0 403 Forbidden");
        die("You do not have access to this trip");
    }
    exit;
}

// Check feature flag
$_settingsModel = new Settings(getDB());
if (!$_settingsModel->get('trip_page_enabled', true)) {
    header("HTTP/1.0 404 Not Found");
    die("Page not found");
}

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDB();

$tripModel    = new Trip();
$routeModel   = new Route();
$pointModel   = new Point();
$tripTagModel = new TripTag();
$linkModel = new Link();
$poiImageModel = new PoiImage();

$trip = $tripModel->getById($tripId);

if (!$trip) {
    header("HTTP/1.0 404 Not Found");
    die("Trip not found");
}

// Los viajes privados solo son visibles para el administrador logueado
if (!empty($trip['is_private']) && empty($accessInfo['is_admin'])) {
    header("HTTP/1.0 403 Forbidden");
    die("You do not have access to this trip");
}

// Obtener datos
$routes = $routeModel->getByTripId($tripId);
$tags = $tripTagModel->getByTripId($tripId);
$points = $pointModel->getAll($tripId);

// Determinar si mostrar rutas en timeline: propiedad del viaje tiene prioridad, luego config global
$_settingsForTimeline = new Settings($db);
$tripShowRoutes = $trip['show_routes_in_timeline'];
if ($tripShowRoutes === null) {
    $tripShowRoutes = (bool) $_settingsForTimeline->get('trip_timeline_show_routes', false);
} else {
    $tripShowRoutes = (bool) $tripShowRoutes;
}

// Calcular estadísticas y procesar rutas
$totalDistance = 0;
$processedRoutes = [];
$timelineItems = [];

foreach ($routes as $route) {
    $dist = (int) ($route['distance_meters'] ?? 0);
    $totalDistance += $dist;

    $routeLinks = Link::toApiArray($linkModel->getByRouteId((int) $route['id']));

    $processedRoute = [
        'id' => (int) $route['id'],
        'transport_type' => $route['transport_type'],
        'color' => $route['color'],
        'geojson' => json_decode($route['geojson_data'], true),
        'name' => $route['name'] ?? null,
        'description' => $route['description'] ?? null,
        'start_datetime' => $route['start_datetime'] ?? null,
        'end_datetime' => $route['end_datetime'] ?? null,
        'links' => $routeLinks,
    ];
    $processedRoutes[] = $processedRoute;

    // Solo agregar al timeline si está habilitado y tiene fecha de inicio
    if ($tripShowRoutes && !empty($route['start_datetime'])) {
        $timelineItems[] = [
            'type' => 'route',
            'item' => $processedRoute,
            'sort_date' => strtotime($route['start_datetime']),
            'has_date' => true,
        ];
    }
}

// Resolver imágenes de galería del viaje en una sola consulta
$tripImages = $poiImageModel->getByTripId($tripId);

// Procesar puntos para JS y timeline
$processedPoints = [];
foreach ($points as $point) {
    $thumbnail_url = null;
    if (!empty($point['image_path'])) {
        $thumb_path = FileHelper::getThumbnailPath($point['image_path']);
        $thumbnail_url = $thumb_path ? BASE_URL . '/' . $thumb_path : null;
    }
    
    $links = Link::toApiArray($linkModel->getByPoiId((int) $point['id']));

    $processedPoint = [
        'id' => (int) $point['id'],
        'title' => $point['title'],
        'description' => $point['description'],
        'type' => $point['type'] ?? 'visit',
        'latitude' => (float) $point['latitude'],
        'longitude' => (float) $point['longitude'],
        'image_url' => !empty($point['image_path']) ? BASE_URL . '/' . $point['image_path'] : null,
        'thumbnail_url' => $thumbnail_url,
        'visit_date' => $point['visit_date'],
        'links' => $links,
        'images' => PoiImage::toApiArray($tripImages[(int) $point['id']] ?? []),
    ];
    $processedPoints[] = $processedPoint;
    
    // Agregar a timeline
    $timelineItems[] = [
        'type' => 'point',
        'item' => $processedPoint,
        'sort_date' => strtotime($point['visit_date'] ?? '1970-01-01 00:00:00'),
        'has_date' => !empty($point['visit_date']),
    ];
}

// Ordenar timeline: primero los que tienen fecha (por fecha), luego los que no tienen fecha
usort($timelineItems, function($a, $b) {
    // Si ambos tienen fecha o ambos no tienen, ordenar por fecha
    if ($a['has_date'] === $b['has_date']) {
        return $a['sort_date'] - $b['sort_date'];
    }
    // Los que tienen fecha van primero
    return $a['has_date'] ? -1 : 1;
});

$tripDataForJS = [
    'id' => (int) $trip['id'],
    'title' => $trip['title'],
    'color' => $trip['color_hex'],
    'routes' => $processedRoutes,
    'points' => $processedPoints
];

// Configuración de renderizado de mapa
$settingsModel = new Settings($db);
$mapRenderer = $settingsModel->get('map_renderer', 'maplibre');
$mapStyle = $settingsModel->get('map_style', 'voyager');
$tripTooltipConfig = [
    'showImage'       => (bool) $settingsModel->get('trip_tooltip_show_image', true),
    'showTripTitle'   => (bool) $settingsModel->get('trip_tooltip_show_trip_title', false),
    'showDescription' => (bool) $settingsModel->get('trip_tooltip_show_description', true),
    'showLinks'       => (bool) $settingsModel->get('trip_tooltip_show_links', true),
    'showCoordinates' => (bool) $settingsModel->get('trip_tooltip_show_coordinates', true),
    'flyZoom'         => (int) $settingsModel->get('trip_poi_fly_zoom', 11),
    'flySpeed'        => $settingsModel->get('trip_poi_fly_speed', 'normal'),
];

// Icons definition (matching public_map.js)
$statsIcons = [
    'routes' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="19" r="3"/><path d="M12 5H8.5C6.567 5 5 6.567 5 8.5C5 10.433 6.567 12 8.5 12H15.5C17.433 12 19 13.567 19 15.5C19 17.433 17.433 19 15.5 19H12"/></svg>',
    'points' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M7 18C5.17107 18.4117 4 19.0443 4 19.7537C4 20.9943 7.58172 22 12 22C16.4183 22 20 20.9943 20 19.7537C20 19.0443 18.8289 18.4117 17 18"/><path d="M14.5 9C14.5 10.3807 13.3807 11.5 12 11.5C10.6193 11.5 9.5 10.3807 9.5 9C9.5 7.61929 10.6193 6.5 12 6.5C13.3807 6.5 14.5 7.61929 14.5 9Z"/><path d="M13.2574 17.4936C12.9201 17.8184 12.4693 18 12.0002 18C11.531 18 11.0802 17.8184 10.7429 17.4936C7.6543 14.5008 3.51519 11.1575 5.53371 6.30373C6.6251 3.67932 9.24494 2 12.0002 2C14.7554 2 17.3752 3.67933 18.4666 6.30373C20.4826 11.1514 16.3536 14.5111 13.2574 17.4936Z"/></svg>'
];


?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($trip['title']) ?> - <?= htmlspecialchars(SITE_TITLE) ?></title>
    
    <?php if (!empty(SITE_FAVICON)): ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars(SITE_FAVICON) ?>">
    <?php endif; ?>

    <?php if ($mapRenderer === 'leaflet'): ?>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/css/leaflet.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/plugins/MarkerCluster.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/plugins/MarkerCluster.Default.css">
    <?php else: ?>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/maplibre/maplibre-gl.css">
    <?php endif; ?>

    <!-- Base Styles -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/public_map.css?v=<?php echo $version; ?>">
    <!-- Trip Page Styles -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/trip.css?v=<?php echo $version; ?>">

    <style>
        :root {
            --trip-color: <?= htmlspecialchars($trip['color_hex']) ?>;
        }
    </style>
</head>
<body class="trip-page">

    <div class="trip-container">
        <!-- Left Column: Details -->
        <div class="trip-details">
            <header class="trip-header">
                <a href="<?= BASE_URL ?>" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/>
                    </svg>
                    <?= __('common.back_to_map') ?>
                </a>

                <div class="trip-header-main">
                    <h1 class="trip-title-large"><?= htmlspecialchars($trip['title']) ?></h1>
                    <?php if ($totalDistance > 0): ?>
                    <div class="trip-distance-badge" data-meters="<?= $totalDistance ?>" title="<?= __('map.total_distance') ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                        <?= number_format($totalDistance / 1000, 0) ?> km
                    </div>
                    <?php endif; ?>
                </div>

                <div class="trip-meta-row">
                    <?php if ($trip['start_date']): ?>
                    <span class="trip-date">
                        <?= date('d M Y', strtotime($trip['start_date'])) ?>
                        <?php if ($trip['end_date']): ?>
                            – <?= date('d M Y', strtotime($trip['end_date'])) ?>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>

                    <span class="trip-counts">
                        <span title="<?= __('map.routes') ?>"><?= $statsIcons['routes'] ?> <?= count($routes) ?></span>
                        <span title="<?= __('map.points') ?>"><?= $statsIcons['points'] ?> <?= count($points) ?></span>
                    </span>
                </div>

                <?php if (!empty($tags)): ?>
                <div class="trip-tags">
                    <?php foreach ($tags as $tag): ?>
                        <span class="trip-tag"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </header>

            <div class="trip-body">
                <?php if (!empty($trip['description'])): ?>
                <div class="trip-description">
                    <?= nl2br(htmlspecialchars($trip['description'])) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($timelineItems)): ?>
                <div class="trip-timeline">
                    <div class="timeline-points">
                        <?php foreach ($timelineItems as $timelineItem): ?>
                            <?php if ($timelineItem['type'] === 'route'): ?>
                                <?php $route = $timelineItem['item']; ?>
                                <div class="timeline-point timeline-route" data-route-id="<?= $route['id'] ?>">
                                    <div class="point-marker" style="background-color: <?= htmlspecialchars($route['color']) ?>;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                                        </svg>
                                    </div>
                                    <div class="point-content">
                                        <div class="point-header">
                                            <?php if (!empty($route['name'])): ?>
                                                <h3 class="point-title"><?= htmlspecialchars($route['name']) ?></h3>
                                            <?php else: ?>
                                                <h3 class="point-title text-muted"><?= __('map.route') ?> #<?= $route['id'] ?></h3>
                                            <?php endif; ?>
                                            <?php if (!empty($route['start_datetime'])): ?>
                                                <span class="point-date"><?= formatRouteDatetime($route['start_datetime'], $route['end_datetime'] ?? null) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($route['description'])): ?>
                                        <div class="route-description"><?= nl2br(htmlspecialchars($route['description'])) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($route['links'])): ?>
                                        <div class="point-links">
                                            <?php foreach ($route['links'] as $link): ?>
                                            <a href="<?= htmlspecialchars($link['url']) ?>"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="point-link-btn"
                                               title="<?= htmlspecialchars($link['label']) ?>">
                                                <?= Link::getSvg($link['type'], 14) ?>
                                            </a>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php $point = $timelineItem['item']; ?>
                                <div class="timeline-point" data-id="<?= $point['id'] ?>" data-lat="<?= $point['latitude'] ?>" data-lng="<?= $point['longitude'] ?>">
                                    <div class="point-marker"></div>
                                    <div class="point-content">
                                        <div class="point-header">
                                            <h3 class="point-title"><?= htmlspecialchars($point['title']) ?></h3>
                                            <?php if ($point['visit_date']): ?>
                                                <span class="point-date"><?= date('d M Y', strtotime($point['visit_date'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($point['links'])): ?>
                                        <div class="point-links">
                                            <?php foreach ($point['links'] as $link): ?>
                                            <a href="<?= htmlspecialchars($link['url']) ?>"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="point-link-btn"
                                               title="<?= htmlspecialchars($link['label']) ?>">
                                                <?= Link::getSvg($link['type'], 14) ?>
                                            </a>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Drag handle -->
        <div class="trip-resizer" id="tripResizer" title="Arrastrar para redimensionar"></div>

        <!-- Right Column: Visuals -->
        <div class="trip-visuals">
            <div id="tripMap" class="trip-map"></div>
            
            <?php
            $pointsWithImages = array_filter($processedPoints, function($p) { return !empty($p['image_url']); });
            // Sort by visit_date (oldest first on left, newest last on right)
            usort($pointsWithImages, function($a, $b) {
                $dateA = strtotime($a['visit_date'] ?? '1970-01-01 00:00:00');
                $dateB = strtotime($b['visit_date'] ?? '1970-01-01 00:00:00');
                return $dateA - $dateB;
            });
            ?>
            <div class="trip-media<?= empty($pointsWithImages) ? ' is-empty' : '' ?>" id="tripMedia">
                <div class="media-resizer" id="mediaResizer" title="<?= __('trips.resize_photos') ?>"></div>

                <div class="media-header">
                    <button type="button"
                            class="media-toggle"
                            id="mediaToggle"
                            aria-expanded="true"
                            aria-controls="mediaBody"
                            data-label-collapse="<?= __('trips.collapse_photos') ?>"
                            data-label-expand="<?= __('trips.expand_photos') ?>"
                            title="<?= __('trips.collapse_photos') ?>">
                        <svg class="media-toggle-icon" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/>
                        </svg>
                        <span class="media-header-title"><?= __('trips.photos') ?></span>
                        <span class="media-header-count"><?= count($pointsWithImages) ?></span>
                    </button>
                </div>

                <div class="media-body" id="mediaBody">
                    <?php if (!empty($pointsWithImages)): ?>
                    <button class="carousel-nav prev" onclick="scrollCarousel(-1)">&#10094;</button>
                    <div class="media-carousel">
                        <!-- Simple horizontal scroll carousel -->
                        <?php foreach ($pointsWithImages as $p): ?>
                            <div class="media-item"
                                 data-point-id="<?= (int)$p['id'] ?>"
                                 data-img="<?= htmlspecialchars($p['image_url']) ?>"
                                 data-title="<?= htmlspecialchars($p['title']) ?>"
                                 data-desc="<?= htmlspecialchars($p['description'] ?? '') ?>"
                                 onclick="viewImageFromData(this)">
                                <div class="media-photo">
                                    <img src="<?= htmlspecialchars($p['thumbnail_url'] ?? $p['image_url']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
                                </div>
                                <span class="media-caption"><?= htmlspecialchars($p['title']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-nav next" onclick="scrollCarousel(1)">&#10095;</button>
                    <?php else: ?>
                    <div class="no-media">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <circle cx="8.5" cy="10" r="1.5"/>
                            <path d="m21 15-4.5-4.5L7 20"/>
                        </svg>
                        <p class="no-media-title"><?= __('trips.no_photos_title') ?></p>
                        <p class="no-media-hint"><?= __('trips.no_photos') ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <div id="imageLightbox" class="lightbox" style="display: none;" onclick="if(event.target===this)closeLightbox()">
        <button class="lightbox-close" onclick="closeLightbox()" aria-label="<?= __('common.close') ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
            </svg>
        </button>
        <button class="lightbox-prev" onclick="changeImage(-1)">&#10094;</button>
        <button class="lightbox-next" onclick="changeImage(1)">&#10095;</button>
        <div class="lightbox-content">
            <img id="lightboxImage" src="" alt="">
            <div class="lightbox-footer">
                <h4 id="lightboxTitle"></h4>
                <div id="lightboxDesc" class="lightbox-description"></div>
            </div>
        </div>
        <span class="lightbox-hint"><?= __('map.click_anywhere_to_close') ?></span>
    </div>

    <!-- Pass Data to JS -->
    <script>
        const TRIP_DATA = <?= json_encode($tripDataForJS) ?>;
        const MAP_RENDERER = '<?= $mapRenderer ?>';
        const MAP_STYLE = '<?= $mapStyle ?>';
        const ASSETS_URL = '<?= ASSETS_URL ?>';
        const TRIP_TOOLTIP_CONFIG = <?= json_encode($tripTooltipConfig) ?>;
    </script>

    <script src="<?= ASSETS_URL ?>/vendor/jquery/jquery-3.7.1.min.js"></script>

    <?php if ($mapRenderer === 'leaflet'): ?>
    <script src="<?= ASSETS_URL ?>/vendor/leaflet/js/leaflet.js"></script>
    <script src="<?= ASSETS_URL ?>/vendor/leaflet/plugins/leaflet.markercluster.js"></script>
    <?php else: ?>
    <script src="<?= ASSETS_URL ?>/vendor/maplibre/maplibre-gl.js"></script>
    <script src="<?= ASSETS_URL ?>/vendor/supercluster/supercluster.min.js"></script>
    <script src="<?= ASSETS_URL ?>/vendor/deckgl/deck.gl.min.js"></script>
    <?php endif; ?>

    <script>const PHP_TRANSLATIONS = <?= $lang->getTranslationsAsJson() ?>;</script>
    <script src="<?= ASSETS_URL ?>/js/i18n.js?v=<?php echo $version; ?>"></script>
    <script>i18n.init();</script>

    <script src="<?= ASSETS_URL ?>/js/map-config.js?v=<?php echo $version; ?>"></script>
    <script src="<?= ASSETS_URL ?>/js/map-renderer.js?v=<?php echo $version; ?>"></script>
    <script src="<?= ASSETS_URL ?>/js/unit_manager.js?v=<?php echo $version; ?>"></script>
    <script src="<?= ASSETS_URL ?>/js/trip_single.js?v=<?php echo $version; ?>"></script>

</body>
</html>
