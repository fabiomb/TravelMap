<?php
// Cargar configuración para las constantes
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/version.php';
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(SITE_TITLE) ?></title>
    <meta name="description" content="<?= htmlspecialchars(SITE_DESCRIPTION) ?>">
    
    <?php if (!empty(SITE_FAVICON)): ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars(SITE_FAVICON) ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?= htmlspecialchars(SITE_FAVICON) ?>">
    <?php endif; ?>
    
    <!-- Bootstrap 5 CSS -->
    <link href="<?= ASSETS_URL ?>/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/css/leaflet.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/plugins/MarkerCluster.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/vendor/leaflet/plugins/MarkerCluster.Default.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/public_map.css?v=<?php echo $version; ?>">
    
    <?php 
    // Insertar código de analytics u otros scripts personalizados
    if (!empty(SITE_ANALYTICS_CODE)): 
        echo SITE_ANALYTICS_CODE . "\n";
    endif; 
    ?>
</head>
<body>
    <!-- Mapa a pantalla completa -->
    <div id="map"></div>

    <!-- Botón para abrir el panel lateral -->
    <button class="btn btn-primary floating-menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#tripsPanel" aria-controls="tripsPanel">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
        </svg>
        <span><?= __('map.my_trips') ?></span>
    </button>

    <!-- Floating controls panel -->
    <div class="floating-controls card shadow-sm">
        <div class="card-body p-2">
            <div class="form-check form-switch mb-1">
                <input class="form-check-input" type="checkbox" id="toggleRoutes" checked>
                <label class="form-check-label small" for="toggleRoutes">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                        <circle cx="18" cy="5" r="3"/><circle cx="6" cy="19" r="3"/>
                        <path d="M12 5H8.5C6.567 5 5 6.567 5 8.5C5 10.433 6.567 12 8.5 12H15.5C17.433 12 19 13.567 19 15.5C19 17.433 17.433 19 15.5 19H12"/>
                    </svg>
                    <?= __('map.routes') ?>
                </label>
            </div>
            <div class="form-check form-switch mb-1">
                <input class="form-check-input" type="checkbox" id="togglePoints" checked>
                <label class="form-check-label small" for="togglePoints">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" class="me-1">
                        <path d="M7 18C5.17107 18.4117 4 19.0443 4 19.7537C4 20.9943 7.58172 22 12 22C16.4183 22 20 20.9943 20 19.7537C20 19.0443 18.8289 18.4117 17 18"/>
                        <path d="M14.5 9C14.5 10.3807 13.3807 11.5 12 11.5C10.6193 11.5 9.5 10.3807 9.5 9C9.5 7.61929 10.6193 6.5 12 6.5C13.3807 6.5 14.5 7.61929 14.5 9Z"/>
                        <path d="M13.2574 17.4936C12.9201 17.8184 12.4693 18 12.0002 18C11.531 18 11.0802 17.8184 10.7429 17.4936C7.6543 14.5008 3.51519 11.1575 5.53371 6.30373C6.6251 3.67932 9.24494 2 12.0002 2C14.7554 2 17.3752 3.67933 18.4666 6.30373C20.4826 11.1514 16.3536 14.5111 13.2574 17.4936Z"/>
                    </svg>
                    <?= __('map.points') ?>
                </label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="toggleFlightRoutes">
                <label class="form-check-label small" for="toggleFlightRoutes">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                        <path d="M15.8667 3.7804C16.7931 3.03188 17.8307 2.98644 18.9644 3.00233C19.5508 3.01055 19.844 3.01467 20.0792 3.10588C20.4524 3.2506 20.7494 3.54764 20.8941 3.92081C20.9853 4.15601 20.9894 4.4492 20.9977 5.03557C21.0136 6.16926 20.9681 7.20686 20.2196 8.13326C19.5893 8.91337 18.5059 9.32101 17.9846 10.1821C17.5866 10.8395 17.772 11.5203 17.943 12.2209L19.2228 17.4662C19.4779 18.5115 19.2838 19.1815 18.5529 19.9124C18.164 20.3013 17.8405 20.2816 17.5251 19.779L13.6627 13.6249L11.8181 15.0911C11.1493 15.6228 10.8149 15.8886 10.6392 16.2627C10.2276 17.1388 10.4889 18.4547 10.5022 19.4046C10.5096 19.9296 10.0559 20.9644 9.41391 20.9993C9.01756 21.0209 8.88283 20.5468 8.75481 20.2558L7.52234 17.4544C7.2276 16.7845 7.21552 16.7724 6.54556 16.4777L3.74415 15.2452C3.45318 15.1172 2.97914 14.9824 3.00071 14.5861C3.03565 13.9441 4.07036 13.4904 4.59536 13.4978C5.54532 13.5111 6.86122 13.7724 7.73734 13.3608C8.11142 13.1851 8.37724 12.8507 8.90888 12.1819L10.3751 10.3373L4.22103 6.47489C3.71845 6.15946 3.69872 5.83597 4.08755 5.44715C4.8185 4.7162 5.48851 4.52214 6.53377 4.77718L11.7791 6.05703C12.4797 6.22798 13.1605 6.41343 13.8179 6.0154C14.679 5.49411 15.0866 4.41074 15.8667 3.7804Z"/>
                    </svg>
                    <?= __('map.flights') ?>
                </label>
            </div>
        </div>
    </div>

    <!-- Panel lateral con filtros (Offcanvas) -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="tripsPanel" aria-labelledby="tripsPanelLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="tripsPanelLabel">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="me-2">
                    <path d="M5.25345 4.19584L4.02558 4.90813C3.03739 5.48137 2.54329 5.768 2.27164 6.24483C2 6.72165 2 7.30233 2 8.46368V16.6283C2 18.1542 2 18.9172 2.34226 19.3418C2.57001 19.6244 2.88916 19.8143 3.242 19.8773C3.77226 19.9719 4.42148 19.5953 5.71987 18.8421C6.60156 18.3306 7.45011 17.7994 8.50487 17.9435C8.98466 18.009 9.44231 18.2366 10.3576 18.6917L14.1715 20.588C14.9964 20.9982 15.004 21 15.9214 21H18C19.8856 21 20.8284 21 21.4142 20.4013C22 19.8026 22 18.8389 22 16.9117V10.1715C22 8.24423 22 7.2806 21.4142 6.68188C20.8284 6.08316 19.8856 6.08316 18 6.08316H15.9214C15.004 6.08316 14.9964 6.08139 14.1715 5.6712L10.8399 4.01463C9.44884 3.32297 8.75332 2.97714 8.01238 3.00117C7.27143 3.02521 6.59877 3.41542 5.25345 4.19584Z"/>
                    <path d="M8 3L8 17.5"/><path d="M15 6.5L15 20.5"/>
                </svg>
                <?= __('map.my_trips') ?>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <!-- Buscador de lugares -->
            <div class="mb-4">
                <h6 class="text-muted text-uppercase small mb-3"><?= __('map.search_place') ?></h6>
                <div class="input-group mb-2">
                    <input type="text" 
                           class="form-control form-control-sm" 
                           id="publicPlaceSearch" 
                           placeholder="<?= __('map.search_placeholder') ?>"
                           autocomplete="off">
                    <button class="btn btn-sm btn-search" type="button" id="publicSearchBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                        </svg>
                    </button>
                </div>
                <div id="publicSearchResults" class="list-group" style="display: none; max-height: 250px; overflow-y: auto;"></div>
            </div>

            <hr>

            <!-- Lista de viajes -->
            <div>
                <div class="trips-header">
                    <h6 class="text-muted text-uppercase small mb-0"><?= __('map.trips_section') ?></h6>
                    <div class="trips-filters">
                        <button type="button" class="filter-btn active" id="filterAll" title="<?= __('map.show_all_trips') ?>"><?= __('map.filter_all') ?></button>
                        <button type="button" class="filter-btn" id="filterPast" title="<?= __('map.show_past_trips') ?>"><?= __('map.filter_past') ?></button>
                        <button type="button" class="filter-btn" id="filterNone" title="<?= __('map.hide_all_trips') ?>"><?= __('map.filter_none') ?></button>
                    </div>
                </div>
                <div id="tripsList">
                    <!-- Se llenará dinámicamente con JavaScript -->
                    <div class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden"><?= __('map.loading') ?></span>
                        </div>
                        <p class="small mt-2"><?= __('map.loading_trips') ?></p>
                    </div>
                </div>
            </div>

            <!-- Footer del panel -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <!-- Language Toggle -->
                    <div class="btn-group btn-group-sm" role="group" aria-label="Language">
                        <input type="radio" class="btn-check" name="langToggle" id="langEn" value="en" <?= current_lang() === 'en' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary" for="langEn">EN</label>
                        <input type="radio" class="btn-check" name="langToggle" id="langEs" value="es" <?= current_lang() === 'es' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary" for="langEs">ES</label>
                    </div>
                    
                    <small class="text-muted">
                        <a href="admin/" class="text-decoration-none"><?= __('app.admin_panel') ?></a>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Leyenda flotante -->
    <div class="legend-card card shadow-sm">
        <div class="card-body p-2">
            <h6 class="small mb-2 fw-bold"><?= __('map.transport_legend') ?></h6>
            <div id="legendItems">
                <!-- Se llenará dinámicamente con JavaScript -->
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="<?= ASSETS_URL ?>/vendor/jquery/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap 5 JS -->
    <script src="<?= ASSETS_URL ?>/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- Leaflet JS -->
    <script src="<?= ASSETS_URL ?>/vendor/leaflet/js/leaflet.js"></script>
    <script src="<?= ASSETS_URL ?>/vendor/leaflet/plugins/leaflet.markercluster.js"></script>
    <script src="<?= ASSETS_URL ?>/vendor/leaflet/plugins/leaflet.curve.js"></script>
    
    <!-- API URL Config -->
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const API_URL = '<?= BASE_URL ?>/api/get_all_data.php';
        
        // Traducciones PHP disponibles para JavaScript
        const PHP_TRANSLATIONS = <?= $lang->getTranslationsAsJson() ?>;
        
        // Console art - Invite to collaborate
        console.log(`%c
:::::::::::''  ''::'      '::::::  \`:::::::::::::'.:::::::::::::::
:::::::::' :. :  :         ::::::  :::::::::::.:::':::::::::::::::
::::::::::  :   :::.       :::::::::::::..::::'     :::: : :::::::
::::::::    :':  "::'     '"::::::::::::: :'           '' ':::::::
:'        : '   :  ::    .::::::::'    '                        .:
:               :  .:: .::. ::::'                              :::
:. .,.        :::  ':::::::::::.: '                      .:...::::
:::::::.      '     .::::::: '''                         :: :::::.
::::::::            ':::::::::  '',            '    '   .:::::::::
::::::::.        :::::::::::: '':,:   '    :         ''' :::::::::
::::::::::      ::::::::::::'                        :::::::::::::
: .::::::::.   .:''::::::::    '         ::   :   '::.::::::::::::
:::::::::::::::. '  '::::::.  '  '     :::::.:.:.:.:.:::::::::::::
:::::::::::::::: :     ':::::::::   ' ,:::::::::: : :.:'::::::::::
::::::::::::::::: '     :::::::::   . :'::::::::::::::' ':::::::::
::::::::::::::::::''   :::::::::: :' : ,:::::::::::'      ':::::::
:::::::::::::::::'   .::::::::::::  ::::::::::::::::       :::::::
:::::::::::::::::. .::::::::::::::::::::::::::::::::::::.'::::::::
:::::::::::::::::' :::::::::::::::::::::::::::::::::::::::::::::::
::::::::::::::::::.:::::::::::::::::::::::::::::::::::::::::::::::
`, 'color: rgb(64, 96, 144); font-family: monospace; font-size: 10px;');
        console.log('%c✈️ TravelMap', 'color:rgb(64, 96, 144); font-size: 20px; font-weight: bold;');
        console.log('%cWant to contribute? Check out our GitHub! → https://github.com/fabiomb/TravelMap', 'color: #64748b; font-size: 12px;');
        console.log('%cPRs, issues, and stars are welcome! ⭐', 'color: #64748b; font-size: 12px;');
    </script>
    
    <!-- i18n JS -->
    <script src="<?= ASSETS_URL ?>/js/i18n.js?v=<?php echo $version; ?>"></script>
    
    <!-- Inicializar i18n -->
    <script>
        // Inicializar sistema de traducciones
        i18n.init(function() {
            console.log('Language system initialized:', i18n.getCurrentLanguage());
        });
        
        // Event handler para el toggle de idioma
        $(document).ready(function() {
            $('input[name="langToggle"]').on('change', function() {
                const newLang = $(this).val();
                console.log('Language changed to:', newLang);
                
                // Guardar en localStorage y cookie
                i18n.setLanguage(newLang, function() {
                    // Recargar página para aplicar nuevo idioma
                    window.location.reload();
                });
            });
        });
    </script>
    
    <!-- Public Map JS -->
    <script src="<?= ASSETS_URL ?>/js/public_map.js?v=<?php echo $version; ?>"></script>
    
    <!-- Lightbox para imágenes -->
    <div id="imageLightbox" class="lightbox" style="display: none;">
        <button class="lightbox-close" onclick="closeLightbox()" aria-label="<?= __('common.close') ?? 'Close' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
            </svg>
        </button>
        <div class="lightbox-content">
            <img id="lightboxImage" src="" alt="">
        </div>
        <span class="lightbox-hint"><?= __('map.click_anywhere_to_close') ?? 'Click anywhere to close' ?></span>
    </div>
    
    <!-- Footer -->
    <footer class="map-footer">
        <span>TravelMap - <?= __('app.footer_created_by') ?> Fabio Baccaglioni</span>
        <a href="https://github.com/fabiomb/TravelMap" target="_blank" rel="noopener noreferrer" class="github-link" title="GitHub">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8"/>
            </svg>
        </a>
    </footer>
</body>
</html>
