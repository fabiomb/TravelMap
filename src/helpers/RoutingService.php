<?php
/**
 * RoutingService
 * 
 * Provides automated route generation between two points using external services.
 * Supports BRouter (online/self-hosted) and Google Maps Directions API.
 */

class RoutingService
{
    private $settings;

    // BRouter profile mapping per transport type
    private static $brouterProfiles = [
        'car'   => 'car-fast',
        'bike'  => 'trekking',
        'walk'  => 'hiking',
        'bus'   => 'car-fast',
        'train' => 'rail',
        'plane' => null, // Great-circle arc, not routed
        'ship'  => null, // Great-circle arc, not routed
        'aerial' => null  // Great-circle arc, not routed
    ];

    // Google Maps travel mode mapping
    private static $googleModes = [
        'car'   => 'driving',
        'bike'  => 'bicycling',
        'walk'  => 'walking',
        'bus'   => 'transit',
        'train' => 'transit',
        'plane' => null,
        'ship'  => null,
        'aerial' => null
    ];

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Check if routing service is enabled and configured.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('routing_service_enabled', false);
    }

    /**
     * Get the configured service type.
     */
    public function getServiceType(): string
    {
        return $this->settings->get('routing_service_type', 'brouter_online');
    }

    /**
     * Get a route between two points.
     *
     * @param float $fromLat Origin latitude
     * @param float $fromLng Origin longitude
     * @param float $toLat   Destination latitude
     * @param float $toLng   Destination longitude
     * @param string $transport Transport type (plane, car, bike, etc.)
     * @return array{geojson: array, distance_meters: int} GeoJSON Feature + distance
     * @throws Exception on failure
     */
    public function getRoute(float $fromLat, float $fromLng, float $toLat, float $toLng, string $transport, array $via = []): array
    {
        if (!$this->isEnabled()) {
            throw new Exception('Routing service is not enabled');
        }

        // For plane/ship/aerial, generate a great-circle arc (no external service needed)
        if (in_array($transport, ['plane', 'ship', 'aerial'])) {
            error_log("[RoutingService] service=great_circle_arc transport=$transport");
            $arc = $this->generateGreatCircleArc($fromLat, $fromLng, $toLat, $toLng);
            $arc['service_type'] = 'great_circle_arc';
            return $arc;
        }

        $serviceType = $this->getServiceType();
        $viaCount = count($via);
        error_log("[RoutingService] service=$serviceType transport=$transport via={$viaCount} from=$fromLat,$fromLng to=$toLat,$toLng");

        switch ($serviceType) {
            case 'brouter_online':
            case 'brouter_custom':
                return $this->routeViaBRouter($fromLat, $fromLng, $toLat, $toLng, $transport, $serviceType, $via);

            case 'google_maps':
                return $this->routeViaGoogleMaps($fromLat, $fromLng, $toLat, $toLng, $transport, $via);

            default:
                throw new Exception("Unknown routing service type: $serviceType");
        }
    }

    /**
     * Route via BRouter API.
     */
    private function routeViaBRouter(float $fromLat, float $fromLng, float $toLat, float $toLng, string $transport, string $serviceType, array $via = []): array
    {
        $profile = self::$brouterProfiles[$transport] ?? 'car-fast';

        if ($serviceType === 'brouter_custom') {
            $baseUrl = rtrim($this->settings->get('routing_brouter_url', 'https://brouter.de/brouter'), '/');
        } else {
            $baseUrl = 'https://brouter.de/brouter';
        }

        // BRouter API: /brouter?lonlats=lon1,lat1|...|lon2,lat2&profile=...&alternativeidx=0&format=geojson
        $lonlats = sprintf('%s,%s', $fromLng, $fromLat);
        foreach ($via as $wp) {
            $lonlats .= sprintf('|%s,%s', (float)($wp['lon'] ?? $wp['lng'] ?? 0), (float)($wp['lat'] ?? 0));
        }
        $lonlats .= sprintf('|%s,%s', $toLng, $toLat);
        $url = $baseUrl . '?' . http_build_query([
            'lonlats'        => $lonlats,
            'profile'        => $profile,
            'alternativeidx' => 0,
            'format'         => 'geojson'
        ]);

        $response = $this->httpGet($url);
        $data = json_decode($response, true);

        if (!$data || !isset($data['features'][0])) {
            throw new Exception('Invalid response from BRouter service');
        }

        $feature = $data['features'][0];
        $coordinates = $feature['geometry']['coordinates'] ?? [];
        $distance = $this->calculateDistanceFromCoords($coordinates);

        // BRouter may include distance in properties
        if (isset($feature['properties']['track-length'])) {
            $distance = (int) round($feature['properties']['track-length']);
        }

        $geojson = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $coordinates
            ],
            'properties' => $feature['properties'] ?? []
        ];

        error_log("[RoutingService] brouter OK profile=$profile distance={$distance}m");

        return [
            'geojson'         => $geojson,
            'distance_meters' => $distance,
            'service_type'    => $serviceType,
        ];
    }

    /**
     * Route via Google Maps Directions API.
     */
    private function routeViaGoogleMaps(float $fromLat, float $fromLng, float $toLat, float $toLng, string $transport, array $via = []): array
    {
        $apiKey = $this->settings->get('routing_google_api_key', '');
        if (empty($apiKey)) {
            throw new Exception('Google Maps API key is not configured');
        }

        $mode = self::$googleModes[$transport] ?? 'driving';

        $params = [
            'origin'      => "$fromLat,$fromLng",
            'destination' => "$toLat,$toLng",
            'mode'        => $mode,
            'key'         => $apiKey,
        ];

        if (!empty($via)) {
            $waypoints = array_map(fn($wp) => ($wp['lat'] ?? 0) . ',' . ($wp['lon'] ?? $wp['lng'] ?? 0), $via);
            $params['waypoints'] = implode('|', $waypoints);
        }

        $url = 'https://maps.googleapis.com/maps/api/directions/json?' . http_build_query($params);

        $response = $this->httpGet($url);
        $data = json_decode($response, true);

        if (!$data || ($data['status'] ?? '') !== 'OK' || empty($data['routes'])) {
            $errorMsg = $data['error_message'] ?? ($data['status'] ?? 'Unknown error');
            throw new Exception('Google Maps API error: ' . $errorMsg);
        }

        $route = $data['routes'][0];
        $overviewPolyline = $route['overview_polyline']['points'] ?? '';
        $coordinates = $this->decodeGooglePolyline($overviewPolyline);

        // Get total distance and duration from legs
        $distance = 0;
        $durationSeconds = 0;
        foreach ($route['legs'] as $leg) {
            $distance        += $leg['distance']['value'] ?? 0;
            $durationSeconds += $leg['duration']['value'] ?? 0;
        }

        $geojson = [
            'type' => 'Feature',
            'geometry' => [
                'type'        => 'LineString',
                'coordinates' => $coordinates,
            ],
            'properties' => [
                'source' => 'google_maps',
                'mode'   => $mode,
            ],
        ];

        error_log("[RoutingService] google_maps OK mode=$mode distance={$distance}m duration={$durationSeconds}s");

        return [
            'geojson'          => $geojson,
            'distance_meters'  => $distance,
            'duration_seconds' => $durationSeconds,
            'service_type'     => 'google_maps',
        ];
    }

    /**
     * Generate a great-circle arc for plane/ship/aerial routes.
     */
    private function generateGreatCircleArc(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $numPoints = 100;
        $coordinates = [];

        $lat1 = deg2rad($fromLat);
        $lng1 = deg2rad($fromLng);
        $lat2 = deg2rad($toLat);
        $lng2 = deg2rad($toLng);

        $d = 2 * asin(sqrt(
            pow(sin(($lat1 - $lat2) / 2), 2) +
            cos($lat1) * cos($lat2) * pow(sin(($lng1 - $lng2) / 2), 2)
        ));

        if ($d < 1e-10) {
            // Same point, return single coordinate
            return [
                'geojson' => [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[$fromLng, $fromLat], [$toLng, $toLat]]
                    ],
                    'properties' => []
                ],
                'distance_meters' => 0
            ];
        }

        for ($i = 0; $i <= $numPoints; $i++) {
            $f = $i / $numPoints;
            $A = sin((1 - $f) * $d) / sin($d);
            $B = sin($f * $d) / sin($d);

            $x = $A * cos($lat1) * cos($lng1) + $B * cos($lat2) * cos($lng2);
            $y = $A * cos($lat1) * sin($lng1) + $B * cos($lat2) * sin($lng2);
            $z = $A * sin($lat1) + $B * sin($lat2);

            $lat = rad2deg(atan2($z, sqrt($x * $x + $y * $y)));
            $lng = rad2deg(atan2($y, $x));

            $coordinates[] = [round($lng, 6), round($lat, 6)];
        }

        $distanceMeters = (int) round($d * 6371000); // Earth radius in meters

        return [
            'geojson' => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $coordinates
                ],
                'properties' => []
            ],
            'distance_meters' => $distanceMeters
        ];
    }

    /**
     * Decode Google's encoded polyline format.
     */
    private function decodeGooglePolyline(string $encoded): array
    {
        $points = [];
        $index = 0;
        $len = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $len) {
            // Decode latitude
            $shift = 0;
            $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            // Decode longitude
            $shift = 0;
            $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            // GeoJSON uses [lng, lat]
            $points[] = [round($lng / 1e5, 6), round($lat / 1e5, 6)];
        }

        return $points;
    }

    /**
     * Calculate distance from an array of [lng, lat] coordinates using Haversine.
     */
    private function calculateDistanceFromCoords(array $coordinates): int
    {
        $totalDistance = 0;
        $R = 6371000; // Earth radius in meters

        for ($i = 1; $i < count($coordinates); $i++) {
            $lat1 = deg2rad($coordinates[$i - 1][1]);
            $lng1 = deg2rad($coordinates[$i - 1][0]);
            $lat2 = deg2rad($coordinates[$i][1]);
            $lng2 = deg2rad($coordinates[$i][0]);

            $dlat = $lat2 - $lat1;
            $dlng = $lng2 - $lng1;

            $a = pow(sin($dlat / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($dlng / 2), 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

            $totalDistance += $R * $c;
        }

        return (int) round($totalDistance);
    }

    /**
     * HTTP GET request with cURL.
     */
    private function httpGet(string $url): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'TravelMap/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("HTTP request failed: $error");
        }

        if ($httpCode >= 400) {
            throw new Exception("HTTP error $httpCode from routing service");
        }

        return $response;
    }
}
