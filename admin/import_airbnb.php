<?php
/**
 * Importar Estadías desde Airbnb CSV
 * 
 * Permite importar puntos de interés (tipo: stay) desde un CSV exportado de Airbnb
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/Trip.php';
require_once __DIR__ . '/../src/models/Point.php';

$conn = getDB();
$tripModel = new Trip();
$pointModel = new Point();

$trips = $tripModel->getAll('start_date DESC');
$message = '';
$messageType = '';
$importResults = [];

/**
 * Parse Airbnb date format and extract the first date
 */
function parseAirbnbDate($dateString) {
    $dateString = trim($dateString);
    $normalized = preg_replace('/[\s\x{2009}\x{00A0}]+/u', ' ', $dateString);
    $normalized = preg_replace('/[\x{2013}\x{2014}\x{2212}\x{2010}\x{2011}–—−-]+/u', '-', $normalized);
    
    if (!preg_match('/(\d{4})$/', $normalized, $yearMatch)) {
        return null;
    }
    $year = $yearMatch[1];
    
    if (preg_match('/^([A-Za-z]+)\s+(\d{1,2})\s*-/', $normalized, $matches)) {
        $month = $matches[1];
        $day = $matches[2];
        $dateStr = "$month $day, $year";
        $timestamp = strtotime($dateStr);
        
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
    }
    
    return null;
}

/**
 * Find a trip that contains the given date
 */
function findTripByDate($date, $trips) {
    if (!$date) return null;
    
    $visitDate = strtotime($date);
    
    foreach ($trips as $trip) {
        if (empty($trip['start_date']) || empty($trip['end_date'])) {
            continue;
        }
        
        $start = strtotime($trip['start_date']);
        $end = strtotime($trip['end_date']);
        
        if ($visitDate >= $start && $visitDate <= $end) {
            return $trip;
        }
    }
    
    return null;
}

/**
 * Geocode a city name using Nominatim via our proxy
 */
function geocodeCity($cityName) {
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'format' => 'json',
        'q' => $cityName,
        'limit' => 1,
        'addressdetails' => 1
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TravelMap/1.0 (PHP Airbnb Importer)');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: es,en'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false || $httpCode !== 200) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (empty($data)) {
        return null;
    }
    
    return [
        'lat' => (float)$data[0]['lat'],
        'lon' => (float)$data[0]['lon'],
        'display_name' => $data[0]['display_name'] ?? $cityName
    ];
}

// Handle clear action via GET
if (isset($_GET['clear'])) {
    unset($_SESSION['airbnb_preview']);
    unset($_SESSION['airbnb_csv']);
    header('Location: import_airbnb.php');
    exit;
}

// Process import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'preview') {
        $csvData = $_POST['csv_data'] ?? '';
        
        if (empty(trim($csvData))) {
            $message = 'Por favor, pega el contenido del CSV';
            $messageType = 'warning';
        } else {
            $lines = explode("\n", trim($csvData));
            $header = str_getcsv(array_shift($lines), ',', '"', '');
            
            $previewData = [];
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                
                $row = str_getcsv($line, ',', '"', '');
                if (count($row) >= 2) {
                    $destination = $row[0] ?? '';
                    $dates = $row[1] ?? '';
                    $firstDate = parseAirbnbDate($dates);
                    $matchingTrip = findTripByDate($firstDate, $trips);
                    
                    $previewData[] = [
                        'destination' => $destination,
                        'dates' => $dates,
                        'first_date' => $firstDate,
                        'first_date_formatted' => $firstDate ? date('d/m/Y', strtotime($firstDate)) : 'No detectada',
                        'trip_id' => $matchingTrip ? $matchingTrip['id'] : null,
                        'trip_title' => $matchingTrip ? $matchingTrip['title'] : null
                    ];
                }
            }
            
            $_SESSION['airbnb_preview'] = $previewData;
            $_SESSION['airbnb_csv'] = $csvData;
        }
        
    } elseif ($_POST['action'] === 'prepare_import') {
        header('Content-Type: application/json');
        
        $fallbackTripId = $_POST['trip_id'] ?? null;
        $createNewTrip = isset($_POST['create_new_trip']) && $_POST['create_new_trip'] === '1';
        $newTripTitle = $_POST['new_trip_title'] ?? 'Airbnb Trips';
        $useAutoDetect = isset($_POST['use_auto_detect']) && $_POST['use_auto_detect'] === '1';
        $selectedIndices = json_decode($_POST['selected_indices'] ?? '[]', true);
        
        if ($createNewTrip) {
            $fallbackTripId = $tripModel->create([
                'title' => $newTripTitle,
                'description' => 'Estadías importadas desde Airbnb',
                'color_hex' => '#FF5A5F',
                'status' => 'draft'
            ]);
            
            if (!$fallbackTripId) {
                echo json_encode(['error' => 'Error al crear el viaje']);
                exit;
            }
        }
        
        if (!isset($_SESSION['airbnb_preview'])) {
            echo json_encode(['error' => 'No hay datos de preview']);
            exit;
        }
        
        $previewData = $_SESSION['airbnb_preview'];
        $itemsToImport = [];
        
        foreach ($selectedIndices as $index) {
            if (!isset($previewData[$index])) continue;
            
            $row = $previewData[$index];
            $rowTripId = null;
            
            if ($useAutoDetect && !empty($row['trip_id'])) {
                $rowTripId = $row['trip_id'];
            } elseif ($fallbackTripId) {
                $rowTripId = $fallbackTripId;
            }
            
            if (!$rowTripId) continue;
            
            $itemsToImport[] = [
                'destination' => $row['destination'],
                'dates' => $row['dates'],
                'first_date' => $row['first_date'],
                'trip_id' => $rowTripId
            ];
        }
        
        unset($_SESSION['airbnb_preview']);
        unset($_SESSION['airbnb_csv']);
        
        echo json_encode([
            'success' => true,
            'items' => $itemsToImport,
            'total' => count($itemsToImport)
        ]);
        exit;
    }
}

$previewData = $_SESSION['airbnb_preview'] ?? null;
$csvData = $_SESSION['airbnb_csv'] ?? '';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#FF5A5F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 18.7753C10.3443 16.7754 9 15.5355 9 13.5C9 11.4645 10.5033 10 12.0033 10C13.5033 10 15 11.4645 15 13.5C15 15.5355 13.6557 16.7754 12 18.7753ZM12 18.7753C10 21.3198 6.02071 21.4621 4.34969 20.302C2.67867 19.1419 2.65485 16.7398 3.75428 14.1954C4.85371 11.651 6.31925 8.5977 9.25143 4.52665C10.2123 3.45799 10.8973 3 11.9967 3M12 18.7753C14 21.3198 17.9793 21.4621 19.6503 20.302C21.3213 19.1419 21.3451 16.7398 20.2457 14.1954C19.1463 11.651 17.6807 8.5977 14.7486 4.52665C13.7877 3.45799 13.1027 3 12.0033 3" />
            </svg>
            <?= __('navigation.import_airbnb') ?>
        </h1>
        <p class="page-subtitle"><?= __('import.airbnb_description') ?? 'Import your stays from Airbnb CSV export. Creates stay-type points of interest.' ?></p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
<?php endif; ?>

<?php if ($previewData === null): ?>
    <!-- Step 1: Paste CSV Data -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    <?= __('import.step') ?? 'Step' ?> 1: <?= __('import.paste_csv') ?? 'Paste CSV Data' ?>
                </h3>
            </div>
            <div class="admin-card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="preview">
                    
                    <div class="form-group">
                        <label for="csv_data" class="form-label">
                            <?= __('import.csv_content') ?? 'CSV Content' ?>
                            <small class="text-muted">(<?= __('import.including_header') ?? 'including Destination,Dates header' ?>)</small>
                        </label>
                        <textarea 
                            class="form-control" 
                            id="csv_data" 
                            name="csv_data" 
                            rows="10"
                            style="font-family: monospace; font-size: 12px;"
                            placeholder='Destination,Dates
"Alicante","Nov 14 – 18, 2025"
"Skopje","Sep 19 – 24, 2025"
...'
                            required
                        ><?= htmlspecialchars($csvData) ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <?= __('import.preview') ?? 'Preview' ?>
                    </button>
                </form>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <?= __('import.how_to_get_csv') ?? 'How to get the CSV' ?>
                </h3>
            </div>
            <div class="admin-card-body">
                <ol style="font-size: 13px; color: var(--admin-text-muted); padding-left: 20px; margin: 0 0 16px 0;">
                    <li style="margin-bottom: 8px;">
                        <?= __('import.go_to') ?? 'Go to' ?> 
                        <a href="https://www.airbnb.com/users/profile/past-trips" target="_blank" style="color: var(--admin-info);">
                            airbnb.com/trips
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px;">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </a>
                    </li>
                    <li style="margin-bottom: 8px;"><?= __('import.open_console') ?? 'Open browser console (F12 → Console)' ?></li>
                    <li style="margin-bottom: 8px;">
                        <?= __('import.paste_script') ?? 'Paste the extraction script:' ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="copy-script-btn" style="margin-left: 8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px;">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            Copy
                        </button>
                    </li>
                    <li style="margin-bottom: 8px;"><?= __('import.click_pink_button') ?? 'Click the pink button that appears' ?></li>
                    <li><?= __('import.paste_here') ?? 'Paste the CSV here' ?></li>
                </ol>
                
                <details>
                    <summary style="font-size: 12px; color: var(--admin-text-muted); cursor: pointer;"><?= __('import.view_script') ?? 'View extraction script' ?></summary>
                    <pre id="extraction-script" style="background: var(--admin-primary); color: white; padding: 12px; border-radius: var(--radius-md); margin-top: 8px; font-size: 10px; overflow-x: auto; max-height: 150px;">(function() {
  const btn = document.createElement('button');
  btn.textContent = '📋 Copy Trips CSV';
  btn.style.cssText = `position:fixed;top:20px;right:20px;z-index:99999;padding:12px 24px;background:#FF385C;color:white;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,0.2);`;
  document.body.appendChild(btn);

  btn.addEventListener('click', () => {
    const destM = '.t19lfnmc.atm_9s_1ulexfb.t13cgbdw.atm_c8_1h3mmnw.atm_fr_b3emyl.atm_7l_hfv0h6.atm_cs_tj69uu.atm_g3_f6fqlb.atm_ti_150s2i2.dir.dir-ltr';
    const dateM = '.s1ejsf9d.atm_9s_1ulexfb.atm_fr_b3emyl.s1p4un0a.atm_gi_idpfg4.atm_c8_1gcojkr.atm_g3_exct8b.atm_cs_1dh25pa.atm_7l_xeyu1p.dir.dir-ltr';
    const destD = '.t1q95j6x.atm_c8_cvmmj6.atm_g3_1obqfcl.atm_fr_frkw1s.atm_cs_ml5b3k.atm_ti_150s2i2.atm_lo_1gqzj4n.dir.dir-ltr';
    const dateD = '.scurj2v.atm_lo_1l7b3ar.atm_c8_1gcojkr.atm_g3_exct8b.atm_cs_1dh25pa.atm_7l_xeyu1p.dir.dir-ltr';

    let dests = document.querySelectorAll(destM);
    let dates = document.querySelectorAll(dateM);
    if (dests.length === 0) { dests = document.querySelectorAll(destD); dates = document.querySelectorAll(dateD); }

    const trips = [];
    for (let i = 0; i < Math.min(dests.length, dates.length); i++) {
      const dest = dests[i]?.textContent?.trim() || '';
      const dt = dates[i]?.textContent?.trim() || '';
      if (dest || dt) trips.push({ destination: dest, dates: dt });
    }

    let csv = 'Destination,Dates\n';
    trips.forEach(t => csv += `"${t.destination.replace(/"/g,'""')}","${t.dates.replace(/"/g,'""')}"\n`);

    navigator.clipboard.writeText(csv).then(() => {
      btn.textContent = `✅ ${trips.length} copied!`;
      btn.style.background = '#00A699';
      setTimeout(() => { btn.textContent = '📋 Copy Trips CSV'; btn.style.background = '#FF385C'; }, 2000);
    });
  });
})();</pre>
                </details>
            </div>
        </div>
    </div>
    
    <script>
    document.getElementById('copy-script-btn')?.addEventListener('click', function() {
        const script = document.getElementById('extraction-script').textContent;
        navigator.clipboard.writeText(script).then(() => {
            this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px;"><polyline points="20 6 9 17 4 12"></polyline></svg> Copied!';
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-success');
            setTimeout(() => {
                this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copy';
                this.classList.remove('btn-success');
                this.classList.add('btn-outline-primary');
            }, 2000);
        });
    });
    </script>
    
<?php else: ?>
    <!-- Step 2: Preview and Import -->
    <form method="POST">
        <input type="hidden" name="action" value="import">
        
        <div class="admin-card">
            <div class="admin-card-header" style="background: var(--admin-success); color: white; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
                <h3 class="admin-card-title" style="color: white;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: white;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <?= __('import.step') ?? 'Step' ?> 2: <?= __('import.select_trip_confirm') ?? 'Select Trip & Confirm' ?>
                </h3>
            </div>
            <div class="admin-card-body">
                <?php
                $autoDetectedCount = count(array_filter($previewData, fn($r) => !empty($r['trip_id'])));
                ?>
                
                <?php if ($autoDetectedCount > 0): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span><strong><?= $autoDetectedCount ?> of <?= count($previewData) ?></strong> <?= __('import.stays_auto_matched') ?? 'stays have auto-detected trips by date.' ?></span>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="use_auto_detect" value="1" id="use_auto_detect" checked>
                        <span class="form-check-label" style="font-weight: 600;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#FF5A5F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px; margin-right: 4px;">
                                <path d="M12 18.7753C10.3443 16.7754 9 15.5355 9 13.5C9 11.4645 10.5033 10 12.0033 10C13.5033 10 15 11.4645 15 13.5C15 15.5355 13.6557 16.7754 12 18.7753Z"/>
                            </svg>
                            <?= __('import.use_auto_detected') ?? 'Use auto-detected trips by date' ?>
                        </span>
                    </label>
                </div>
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;" id="fallback-trip-section">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600;">
                            <?= $autoDetectedCount > 0 ? __('import.fallback_trip') ?? 'Fallback Trip' : __('import.assign_to_trip') ?? 'Assign to Trip' ?>
                        </label>
                        <select class="form-control form-select" name="trip_id" id="trip_id">
                            <option value="">-- <?= __('import.select_trip') ?? 'Select Trip' ?> --</option>
                            <?php foreach ($trips as $trip): ?>
                                <option value="<?= $trip['id'] ?>">
                                    <?= htmlspecialchars($trip['title']) ?>
                                    <?php if ($trip['start_date']): ?>
                                        (<?= date('d/m/Y', strtotime($trip['start_date'])) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600;"><?= __('import.or_create_new') ?? 'Or Create New Trip' ?></label>
                        <div style="display: flex; gap: 8px;">
                            <label class="form-check" style="flex-shrink: 0; padding-top: 10px;">
                                <input type="checkbox" class="form-check-input" name="create_new_trip" value="1" id="create_new_trip">
                            </label>
                            <input type="text" class="form-control" name="new_trip_title" id="new_trip_title" 
                                   value="Airbnb Stays" placeholder="New trip name" disabled>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning" style="margin-bottom: 24px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?= __('import.geocoding_note') ?? 'Geocoding uses OpenStreetMap with 1 request/second limit. Import may take a few minutes.' ?></span>
                </div>
                
                <h4 style="font-size: 15px; font-weight: 600; margin-bottom: 16px;">
                    <?= __('import.preview') ?>: <?= count($previewData) ?> <?= __('import.stays_found') ?? 'stays found' ?>
                </h4>
                
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input class="form-check-input" type="checkbox" id="select_all" checked>
                                </th>
                                <th><?= __('import.destination') ?? 'Destination' ?></th>
                                <th><?= __('import.original_dates') ?? 'Original Dates' ?></th>
                                <th><?= __('import.visit_date') ?? 'Visit Date' ?></th>
                                <th><?= __('navigation.trips') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewData as $index => $row): ?>
                                <tr data-index="<?= $index ?>" 
                                    data-destination="<?= htmlspecialchars($row['destination']) ?>"
                                    data-dates="<?= htmlspecialchars($row['dates']) ?>"
                                    data-first-date="<?= htmlspecialchars($row['first_date'] ?? '') ?>"
                                    data-auto-trip-id="<?= htmlspecialchars($row['trip_id'] ?? '') ?>">
                                    <td>
                                        <input class="form-check-input row-check" type="checkbox" 
                                               name="import_<?= $index ?>" value="1" checked>
                                    </td>
                                    <td>
                                        <span style="display: flex; align-items: center; gap: 6px;">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#FF5A5F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px;">
                                                <path d="M12 18.7753C10.3443 16.7754 9 15.5355 9 13.5C9 11.4645 10.5033 10 12.0033 10C13.5033 10 15 11.4645 15 13.5C15 15.5355 13.6557 16.7754 12 18.7753Z"/>
                                            </svg>
                                            <span class="cell-title"><?= htmlspecialchars($row['destination']) ?></span>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted" style="font-size: 12px;"><?= htmlspecialchars($row['dates']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($row['first_date']): ?>
                                            <span class="badge badge-success"><?= $row['first_date_formatted'] ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-warning"><?= __('import.not_detected') ?? 'Not detected' ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['trip_id'])): ?>
                                            <span class="badge badge-info"><?= htmlspecialchars($row['trip_title']) ?></span>
                                        <?php else: ?>
                                            <select class="form-control form-select row-trip-select" style="font-size: 12px; padding: 4px 8px; min-width: 140px;">
                                                <option value="">-- <?= __('common.select') ?? 'Select' ?> --</option>
                                                <?php foreach ($trips as $trip): ?>
                                                    <option value="<?= $trip['id'] ?>">
                                                        <?= htmlspecialchars($trip['title']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="admin-card-footer" style="display: flex; justify-content: space-between; align-items: center;">
                <a href="?clear=1" class="btn btn-secondary" id="btn-back">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                    <?= __('import.start_over') ?? 'Start Over' ?>
                </a>
                <button type="button" class="btn btn-success" id="btn-import">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <?= __('import.import_selected') ?? 'Import Selected Stays' ?>
                </button>
            </div>
            
            <!-- Progress section (hidden by default) -->
            <div class="admin-card-body" id="progress-section" style="display: none;">
                <h5 style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#FF5A5F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px;">
                        <path d="M12 18.7753C10.3443 16.7754 9 15.5355 9 13.5C9 11.4645 10.5033 10 12.0033 10C13.5033 10 15 11.4645 15 13.5C15 15.5355 13.6557 16.7754 12 18.7753Z"/>
                    </svg>
                    <?= __('import.importing_stays') ?? 'Importing stays...' ?>
                </h5>
                <div style="height: 24px; background: var(--admin-bg-alt); border-radius: var(--radius-md); margin-bottom: 16px; overflow: hidden;">
                    <div id="import-progress" style="height: 100%; background: var(--admin-success); width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: 600;">
                        0%
                    </div>
                </div>
                <p style="margin-bottom: 8px;"><strong><?= __('import.processing') ?? 'Processing' ?>:</strong> <span id="current-destination">-</span></p>
                <p style="font-size: 12px; color: var(--admin-text-muted); margin-bottom: 16px;">
                    <span id="import-stats">0 / 0</span> <?= __('import.processed') ?? 'processed' ?> 
                    (<span id="success-count" style="color: var(--admin-success);">0</span> <?= __('import.new') ?? 'new' ?>, 
                    <span id="skip-count" style="color: var(--admin-warning);">0</span> <?= __('import.duplicates') ?? 'duplicates' ?>,
                    <span id="fail-count" style="color: var(--admin-danger);">0</span> <?= __('import.failed') ?? 'failed' ?>)
                </p>
                <div id="import-log" style="border: 1px solid var(--admin-border); border-radius: var(--radius-md); padding: 12px; background: var(--admin-bg); max-height: 150px; overflow-y: auto; font-size: 12px;"></div>
            </div>
        </div>
    </form>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const createNewTripCheckbox = document.getElementById('create_new_trip');
    const newTripTitleInput = document.getElementById('new_trip_title');
    const tripIdSelect = document.getElementById('trip_id');
    
    if (createNewTripCheckbox) {
        createNewTripCheckbox.addEventListener('change', function() {
            newTripTitleInput.disabled = !this.checked;
            if (this.checked) {
                tripIdSelect.value = '';
                tripIdSelect.disabled = true;
            } else {
                tripIdSelect.disabled = false;
            }
        });
    }
    
    const selectAll = document.getElementById('select_all');
    const rowChecks = document.querySelectorAll('.row-check');
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rowChecks.forEach(check => { check.checked = this.checked; });
        });
        
        rowChecks.forEach(check => {
            check.addEventListener('change', function() {
                const allChecked = Array.from(rowChecks).every(c => c.checked);
                const someChecked = Array.from(rowChecks).some(c => c.checked);
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            });
        });
    }
    
    const btnImport = document.getElementById('btn-import');
    const progressSection = document.getElementById('progress-section');
    const progressBar = document.getElementById('import-progress');
    const currentDest = document.getElementById('current-destination');
    const importStats = document.getElementById('import-stats');
    const successCount = document.getElementById('success-count');
    const skipCount = document.getElementById('skip-count');
    const failCount = document.getElementById('fail-count');
    const importLog = document.getElementById('import-log');
    
    if (btnImport) {
        btnImport.addEventListener('click', async function() {
            const items = [];
            const fallbackTripId = tripIdSelect ? tripIdSelect.value : '';
            const useAutoDetect = document.getElementById('use_auto_detect');
            const useAuto = useAutoDetect && useAutoDetect.checked;
            
            document.querySelectorAll('tbody tr').forEach(row => {
                const checkbox = row.querySelector('.row-check');
                if (!checkbox || !checkbox.checked) return;
                
                const destination = row.dataset.destination;
                const dates = row.dataset.dates;
                const firstDate = row.dataset.firstDate || null;
                const autoTripId = row.dataset.autoTripId;
                
                let tripId = null;
                if (useAuto && autoTripId) {
                    tripId = autoTripId;
                } else {
                    const rowSelect = row.querySelector('.row-trip-select');
                    if (rowSelect && rowSelect.value) {
                        tripId = rowSelect.value;
                    } else if (fallbackTripId) {
                        tripId = fallbackTripId;
                    }
                }
                
                if (tripId) {
                    items.push({ destination, dates, first_date: firstDate, trip_id: tripId });
                }
            });
            
            if (items.length === 0) {
                alert('<?= __('import.no_stays_to_import') ?? 'No stays to import. Select a trip for each row or use fallback trip.' ?>');
                return;
            }
            
            document.querySelector('.admin-table-wrapper').style.display = 'none';
            document.querySelector('.admin-card-footer').style.display = 'none';
            const fallbackSection = document.getElementById('fallback-trip-section');
            if (fallbackSection) fallbackSection.style.display = 'none';
            progressSection.style.display = 'block';
            
            const total = items.length;
            let success = 0;
            let failed = 0;
            let skipped = 0;
            
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                currentDest.textContent = item.destination;
                
                try {
                    const response = await fetch('<?= BASE_URL ?>/api/import_airbnb_point.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(item)
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        if (result.skipped) {
                            skipped++;
                            addLog(`⊘ ${item.destination}: ${result.message}`, 'warning');
                        } else {
                            success++;
                            addLog(`✓ ${item.destination}`, 'success');
                        }
                    } else {
                        failed++;
                        addLog(`✗ ${item.destination}: ${result.message}`, 'danger');
                    }
                } catch (e) {
                    failed++;
                    addLog(`✗ ${item.destination}: Network error`, 'danger');
                }
                
                const pct = Math.round(((i + 1) / total) * 100);
                progressBar.style.width = pct + '%';
                progressBar.textContent = pct + '%';
                importStats.textContent = `${i + 1} / ${total}`;
                successCount.textContent = success;
                skipCount.textContent = skipped;
                failCount.textContent = failed;
                
                if (i < items.length - 1) {
                    await sleep(1200);
                }
            }
            
            currentDest.textContent = '<?= __('import.completed') ?? 'Completed!' ?>';
            addLog(`📊 Summary: ${success} imported, ${skipped} duplicates, ${failed} failed`, 'info');
            
            const doneHtml = `
                <div style="margin-top: 16px; display: flex; gap: 12px;">
                    <a href="points.php" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;">
                            <path d="M14.5 9C14.5 10.3807 13.3807 11.5 12 11.5"/>
                        </svg>
                        <?= __('navigation.points') ?>
                    </a>
                    <a href="import_airbnb.php" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                        </svg>
                        <?= __('import.import_more') ?? 'Import More' ?>
                    </a>
                </div>
            `;
            progressSection.insertAdjacentHTML('beforeend', doneHtml);
        });
    }
    
    function addLog(message, type) {
        const line = document.createElement('div');
        line.style.color = type === 'success' ? 'var(--admin-success)' : type === 'danger' ? 'var(--admin-danger)' : type === 'warning' ? 'var(--admin-warning)' : 'var(--admin-info)';
        line.textContent = message;
        importLog.appendChild(line);
        importLog.scrollTop = importLog.scrollHeight;
    }
    
    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
