<?php
/**
 * Helper: BRouterClient
 *
 * Llama a la API REST de BRouter (brouter.de) y devuelve datos de ruta
 * listos para usar con Route::create() / create_route MCP tool.
 *
 * API BRouter: GET /brouter?lonlats=lon,lat|...|lon,lat&profile=PROFILE&alternativeidx=0&format=geojson
 * Devuelve un FeatureCollection con un Feature LineString + propiedades de stats.
 *
 * Tipos de transporte soportados: car, bike, walk, train, bus
 * No soportados (no terrestres): plane, ship, aerial
 */

final class BRouterClient
{
    /** URL pública del API. Se puede sobreescribir con la constante BROUTER_API_URL. */
    private const DEFAULT_API_URL = 'https://brouter.de/brouter';

    /** Timeout HTTP en segundos. */
    private const HTTP_TIMEOUT = 30;

    /** Máximo de waypoints intermedios permitidos. */
    private const MAX_VIA_POINTS = 8;

    /**
     * Mapa transport_type → perfil de BRouter.
     *
     * Perfiles disponibles en brouter.de:
     *   car-fast, car-eco, trekking, fastbike, MTB, foot-way, rail, bus-local
     */
    private const TRANSPORT_PROFILES = [
        'car'   => 'car-fast',
        'bike'  => 'trekking',
        'walk'  => 'foot-way',
        'train' => 'rail',
        'bus'   => 'bus-local',
    ];

    /** Tipos no enrutables por BRouter (trayectos no terrestres). */
    private const UNSUPPORTED_TYPES = ['plane', 'ship', 'aerial'];

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Calcula una ruta entre dos puntos usando BRouter.
     *
     * @param float  $fromLat       Latitud de origen (−90..90).
     * @param float  $fromLon       Longitud de origen (−180..180).
     * @param float  $toLat         Latitud de destino.
     * @param float  $toLon         Longitud de destino.
     * @param array  $via           Waypoints intermedios: [[lat, lon], ...]. Máx. 8.
     * @param string $transportType Tipo de transporte: car|bike|walk|train|bus.
     *
     * @return array {
     *   success: bool,
     *   error?: string,
     *   geojson_data: string,     // listo para create_route → geojson_data
     *   distance_meters: int,
     *   distance_km: float,
     *   duration_min: int,
     *   waypoints_count: int,
     *   profile: string,          // perfil BRouter utilizado
     *   start: {lat, lon},
     *   end: {lat, lon},
     *   bbox: {minLat, maxLat, minLon, maxLon},
     * }
     */
    public static function planRoute(
        float  $fromLat,
        float  $fromLon,
        float  $toLat,
        float  $toLon,
        array  $via           = [],
        string $transportType = 'car'
    ): array {
        // ── Validar tipo de transporte ─────────────────────────────────────────
        if (in_array($transportType, self::UNSUPPORTED_TYPES, true)) {
            return [
                'success' => false,
                'error'   => "El tipo '{$transportType}' no es enrutable por BRouter (solo aplica a: car, bike, walk, train, bus).",
            ];
        }

        $profile = self::TRANSPORT_PROFILES[$transportType] ?? null;
        if ($profile === null) {
            return [
                'success' => false,
                'error'   => "Tipo de transporte desconocido: '{$transportType}'. Usar car, bike, walk, train o bus.",
            ];
        }

        // ── Validar coordenadas ────────────────────────────────────────────────
        $coordError = self::validateCoords($fromLat, $fromLon, 'origen')
                   ?? self::validateCoords($toLat,   $toLon,   'destino');
        if ($coordError) {
            return ['success' => false, 'error' => $coordError];
        }

        if (count($via) > self::MAX_VIA_POINTS) {
            return [
                'success' => false,
                'error'   => 'Máximo ' . self::MAX_VIA_POINTS . ' waypoints intermedios permitidos.',
            ];
        }

        // ── Construir string de coordenadas (formato BRouter: lon,lat|lon,lat) ─
        $points = [self::formatLonLat($fromLon, $fromLat)];
        foreach ($via as $i => $wp) {
            if (!isset($wp['lat'], $wp['lon']) && !isset($wp[0], $wp[1])) {
                return ['success' => false, 'error' => "Waypoint #{$i} inválido. Debe tener lat y lon."];
            }
            $wpLat = (float)($wp['lat'] ?? $wp[0]);
            $wpLon = (float)($wp['lon'] ?? $wp[1]);
            $viaErr = self::validateCoords($wpLat, $wpLon, "waypoint #{$i}");
            if ($viaErr) {
                return ['success' => false, 'error' => $viaErr];
            }
            $points[] = self::formatLonLat($wpLon, $wpLat);
        }
        $points[] = self::formatLonLat($toLon, $toLat);

        // ── Llamar a BRouter ───────────────────────────────────────────────────
        $apiUrl = defined('BROUTER_API_URL') ? BROUTER_API_URL : self::DEFAULT_API_URL;
        $url    = $apiUrl . '?' . http_build_query([
            'lonlats'        => implode('|', $points),
            'profile'        => $profile,
            'alternativeidx' => 0,
            'format'         => 'geojson',
        ], '', '&', PHP_QUERY_RFC3986);

        $http = self::httpGet($url);
        if (!$http['success']) {
            return ['success' => false, 'error' => $http['error']];
        }

        return self::parseGeoJson($http['body'], $transportType, $profile);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ──────────────────────────────────────────────────────────────────────────

    private static function validateCoords(float $lat, float $lon, string $label): ?string
    {
        if ($lat < -90 || $lat > 90) {
            return "Latitud de {$label} fuera de rango (−90..90): {$lat}";
        }
        if ($lon < -180 || $lon > 180) {
            return "Longitud de {$label} fuera de rango (−180..180): {$lon}";
        }
        return null;
    }

    /** Formatea lon,lat con 6 decimales para la URL de BRouter. */
    private static function formatLonLat(float $lon, float $lat): string
    {
        return number_format($lon, 6, '.', '') . ',' . number_format($lat, 6, '.', '');
    }

    /**
     * Realiza una petición HTTP GET con timeout.
     * Usa file_get_contents con stream context (sin dependencias externas).
     */
    private static function httpGet(string $url): array
    {
        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => self::HTTP_TIMEOUT,
                'ignore_errors'   => true,   // para leer el cuerpo en errores HTTP
                'user_agent'      => 'TravelMap-MCP/1.0 (+https://github.com/tucho235/TravelMap)',
                'follow_location' => 1,
                'max_redirects'   => 3,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);

        if ($body === false) {
            return [
                'success' => false,
                'error'   => 'No se pudo conectar con BRouter (' . $url . '). Verificá la conexión a internet.',
            ];
        }

        // Extraer código de estado HTTP
        $statusCode = 200;
        if (!empty($http_response_header)) {
            foreach ($http_response_header as $hdr) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', $hdr, $m)) {
                    $statusCode = (int)$m[1];
                }
            }
        }

        // BRouter devuelve HTTP 500 con mensaje de texto en el body cuando no encuentra ruta
        if ($statusCode >= 400) {
            $errDetail = trim($body);
            // Los errores de BRouter suelen ser cortos y legibles
            if (strlen($errDetail) > 300 || str_starts_with($errDetail, '<')) {
                $errDetail = "HTTP {$statusCode} desde BRouter.";
            }
            return ['success' => false, 'error' => "BRouter: {$errDetail}"];
        }

        return ['success' => true, 'body' => $body];
    }

    /**
     * Parsea la respuesta GeoJSON de BRouter y construye el array de resultado.
     */
    private static function parseGeoJson(string $body, string $transportType, string $profile): array
    {
        $data = json_decode($body, true);

        if ($data === null || !isset($data['type'])) {
            return ['success' => false, 'error' => 'Respuesta de BRouter no es JSON válido.'];
        }
        if ($data['type'] !== 'FeatureCollection') {
            return ['success' => false, 'error' => "Respuesta inesperada de BRouter (tipo: {$data['type']})."];
        }

        $feature = $data['features'][0] ?? null;
        if (!$feature || ($feature['geometry']['type'] ?? '') !== 'LineString') {
            return ['success' => false, 'error' => 'BRouter no devolvió una ruta LineString válida.'];
        }

        $rawCoords = $feature['geometry']['coordinates'] ?? [];
        if (count($rawCoords) < 2) {
            return ['success' => false, 'error' => 'La ruta devuelta tiene menos de 2 puntos.'];
        }

        // Eliminar coordenada de elevación (3er elemento) si está presente
        $coords = array_map(fn($c) => [(float)$c[0], (float)$c[1]], $rawCoords);

        // Stats desde las propiedades de BRouter
        $props          = $feature['properties'] ?? [];
        $distanceMeters = (int)($props['track-length'] ?? 0);
        $totalTimeSec   = (int)($props['total-time']   ?? 0);

        // Si BRouter no devuelve distancia, calcularla con Haversine
        if ($distanceMeters === 0 && class_exists('Route')) {
            $tmpGeo         = json_encode(['geometry' => ['coordinates' => $coords]]);
            $distanceMeters = Route::calculateDistance($tmpGeo);
        }

        // Construir GeoJSON en el formato que espera Route::create() / create_route
        $geojsonData = json_encode([
            'type'       => 'Feature',
            'properties' => [
                'transport'   => $transportType,
                'distance_km' => round($distanceMeters / 1000, 2),
                'source'      => 'brouter',
                'profile'     => $profile,
            ],
            'geometry' => [
                'type'        => 'LineString',
                'coordinates' => $coords,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($geojsonData === false) {
            return ['success' => false, 'error' => 'Error al serializar el GeoJSON de la ruta.'];
        }

        $lastCoord = $coords[count($coords) - 1];
        $lons      = array_column($coords, 0);
        $lats      = array_column($coords, 1);

        return [
            'success'         => true,
            'geojson_data'    => $geojsonData,
            'distance_meters' => $distanceMeters,
            'distance_km'     => round($distanceMeters / 1000, 2),
            'duration_min'    => (int)round($totalTimeSec / 60),
            'waypoints_count' => count($coords),
            'profile'         => $profile,
            'start'           => ['lat' => $coords[0][1],   'lon' => $coords[0][0]],
            'end'             => ['lat' => $lastCoord[1],   'lon' => $lastCoord[0]],
            'bbox'            => [
                'minLat' => min($lats), 'maxLat' => max($lats),
                'minLon' => min($lons), 'maxLon' => max($lons),
            ],
        ];
    }

    /**
     * Devuelve la lista de perfiles disponibles por tipo de transporte.
     * Útil para debug y tests.
     */
    public static function getSupportedTypes(): array
    {
        return array_keys(self::TRANSPORT_PROFILES);
    }
}
