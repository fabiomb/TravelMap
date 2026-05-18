<?php
/**
 * MCP Tools: Routes
 * plan_route, commit_route, create_route, update_route
 */

final class RouteTools
{
    private const ALLOWED_TRANSPORT = ['plane', 'car', 'bike', 'walk', 'ship', 'train', 'bus', 'aerial'];

    public static function register(Dispatcher $d): void
    {
        $d->register('plan_route',
            'Calculates a land route between two points using BRouter (brouter.de). ' .
            'Saves the result to a server-side temp file and returns ONLY lightweight metadata ' .
            '(distance, duration, temp_path). Use commit_route with that temp_path to persist the route to the DB. ' .
            'Supported types: car, bike, walk, train, bus. ' .
            'NOT supported: plane, ship, aerial (non-land segments).',
        [
            'type'       => 'object',
            'required'   => ['from_lat', 'from_lon', 'to_lat', 'to_lon', 'transport_type'],
            'properties' => [
                'from_lat'       => ['type' => 'number', 'minimum' => -90,  'maximum' => 90,  'description' => 'Latitude of the origin point.'],
                'from_lon'       => ['type' => 'number', 'minimum' => -180, 'maximum' => 180, 'description' => 'Longitude of the origin point.'],
                'to_lat'         => ['type' => 'number', 'minimum' => -90,  'maximum' => 90,  'description' => 'Latitude of the destination point.'],
                'to_lon'         => ['type' => 'number', 'minimum' => -180, 'maximum' => 180, 'description' => 'Longitude of the destination point.'],
                'transport_type' => ['type' => 'string', 'enum' => ['car', 'bike', 'walk', 'train', 'bus']],
                'via' => [
                    'type'        => 'array',
                    'maxItems'    => 8,
                    'description' => 'Optional intermediate waypoints the route must pass through.',
                    'items' => [
                        'type'       => 'object',
                        'required'   => ['lat', 'lon'],
                        'properties' => [
                            'lat' => ['type' => 'number', 'minimum' => -90,  'maximum' => 90],
                            'lon' => ['type' => 'number', 'minimum' => -180, 'maximum' => 180],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'additionalProperties' => false,
        ], [self::class, 'planRoute']);

        $d->register('create_route',
            'Creates a route for a trip from a small structured coordinate list. ' .
            'For detailed land routes use plan_route then commit_route so geometry stays server-side. ' .
            'For plane, ship or aerial segments, provide two coordinates: origin and destination.',
        [
            'type'       => 'object',
            'required'   => ['trip_id', 'transport_type', 'coordinates'],
            'properties' => [
                'trip_id'            => ['type' => 'integer', 'minimum' => 1],
                'transport_type'     => ['type' => 'string', 'enum' => self::ALLOWED_TRANSPORT],
                'name'               => ['type' => 'string', 'maxLength' => 200],
                'description'        => ['type' => 'string', 'maxLength' => 5000],
                'is_round_trip'      => ['type' => 'boolean'],
                'color'              => ['type' => 'string', 'pattern' => '^#[0-9A-Fa-f]{6}$', 'description' => 'Route color as a CSS hex string. Example: "#e63946". Default: "#3388ff".'],
                'start_datetime'     => ['type' => 'string', 'description' => 'Start date and time. Format "YYYY-MM-DD HH:MM:SS". Also accepts date only "YYYY-MM-DD". Example: "2024-07-15 09:30:00".'],
                'end_datetime'       => ['type' => 'string', 'description' => 'End date and time. Format "YYYY-MM-DD HH:MM:SS". Also accepts date only "YYYY-MM-DD". Example: "2024-07-15 18:00:00".'],
                'coordinates' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'maxItems' => 500,
                    'description' => 'Coordinate list in order. Each item is {lat, lon}.',
                    'items' => [
                        'type' => 'object',
                        'required' => ['lat', 'lon'],
                        'properties' => [
                            'lat' => ['type' => 'number', 'minimum' => -90, 'maximum' => 90],
                            'lon' => ['type' => 'number', 'minimum' => -180, 'maximum' => 180],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'links' => [
                    'type'     => 'array',
                    'maxItems' => 10,
                    'items'    => [
                        'type'       => 'object',
                        'required'   => ['url'],
                        'properties' => [
                            'url'       => ['type' => 'string', 'maxLength' => 500],
                            'label'     => ['type' => 'string', 'maxLength' => 100],
                            'link_type' => ['type' => 'string', 'maxLength' => 40, 'description' => 'Link type. Values: "website", "google_maps", "instagram", "facebook", "twitter", "tripadvisor", "booking", "airbnb", "youtube", "wikipedia", "google_photos", "other" (default).'],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'additionalProperties' => false,
        ], [self::class, 'createRoute']);

        $d->register('commit_route',
            'Persists to the DB a route previously calculated with plan_route. ' .
            'Reads the GeoJSON from the server-side temp file (temp_path returned by plan_route) ' .
            'and creates the route directly without passing coordinates through the context. ' .
            'The temp file is automatically deleted after the commit.',
        [
            'type'       => 'object',
            'required'   => ['trip_id', 'temp_path', 'transport_type'],
            'properties' => [
                'trip_id'        => ['type' => 'integer', 'minimum' => 1],
                'temp_path'      => ['type' => 'string', 'description' => 'The temp_path value returned by plan_route.'],
                'transport_type' => ['type' => 'string', 'enum' => self::ALLOWED_TRANSPORT],
                'name'           => ['type' => 'string', 'maxLength' => 200],
                'description'    => ['type' => 'string', 'maxLength' => 5000],
                'is_round_trip'  => ['type' => 'boolean'],
                'color'          => ['type' => 'string', 'pattern' => '^#[0-9A-Fa-f]{6}$', 'description' => 'Route color as a CSS hex string. Example: "#e63946". Default: "#3388ff".'],
                'start_datetime' => ['type' => 'string', 'description' => 'Start date and time. Format "YYYY-MM-DD HH:MM:SS". Also accepts date only "YYYY-MM-DD". Example: "2024-07-15 09:30:00".'],
                'end_datetime'   => ['type' => 'string', 'description' => 'End date and time. Format "YYYY-MM-DD HH:MM:SS". Also accepts date only "YYYY-MM-DD". Example: "2024-07-15 18:00:00".'],
                'links' => [
                    'type'     => 'array',
                    'maxItems' => 10,
                    'items'    => [
                        'type'       => 'object',
                        'required'   => ['url'],
                        'properties' => [
                            'url'       => ['type' => 'string', 'maxLength' => 500],
                            'label'     => ['type' => 'string', 'maxLength' => 100],
                            'link_type' => ['type' => 'string', 'maxLength' => 40, 'description' => 'Link type. Values: "website", "google_maps", "instagram", "facebook", "twitter", "tripadvisor", "booking", "airbnb", "youtube", "wikipedia", "google_photos", "other" (default).'],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'additionalProperties' => false,
        ], [self::class, 'commitRoute']);

        $d->register('update_route',
            'Updates the metadata of an existing route. Only the provided fields are modified. ' .
            'Route geometry (geojson) cannot be changed through this tool. ' .
            'To update links supply the full array (replaces existing ones).',
        [
            'type'       => 'object',
            'required'   => ['id'],
            'properties' => [
                'id'             => ['type' => 'integer', 'minimum' => 1],
                'name'           => ['type' => 'string', 'maxLength' => 200],
                'description'    => ['type' => 'string', 'maxLength' => 5000],
                'transport_type' => ['type' => 'string', 'enum' => self::ALLOWED_TRANSPORT],
                'color'          => ['type' => 'string', 'pattern' => '^#[0-9A-Fa-f]{6}$', 'description' => 'Route color as a CSS hex string. Example: "#e63946". Default: "#3388ff".'],
                'is_round_trip'  => ['type' => 'boolean'],
                'start_datetime' => ['type' => 'string', 'description' => 'Start date and time. Format "YYYY-MM-DD HH:MM:SS". Also accepts date only "YYYY-MM-DD". Example: "2024-07-15 09:30:00".'],
                'end_datetime'   => ['type' => 'string', 'description' => 'End date and time. Format "YYYY-MM-DD HH:MM:SS". Also accepts date only "YYYY-MM-DD". Example: "2024-07-15 18:00:00".'],
                'links' => [
                    'type'     => 'array',
                    'maxItems' => 10,
                    'items'    => [
                        'type'       => 'object',
                        'required'   => ['url'],
                        'properties' => [
                            'url'       => ['type' => 'string', 'maxLength' => 500],
                            'label'     => ['type' => 'string', 'maxLength' => 100],
                            'link_type' => ['type' => 'string', 'maxLength' => 40, 'description' => 'Link type. Values: "website", "google_maps", "instagram", "facebook", "twitter", "tripadvisor", "booking", "airbnb", "youtube", "wikipedia", "google_photos", "other" (default).'],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'additionalProperties' => false,
        ], [self::class, 'updateRoute']);
    }

    // ──────────────────────────────────────────────────────────────────────────

    public static function createRoute(array $p): array
    {
        $tripId = (int)$p['trip_id'];
        self::assertTripExists($tripId);

        $normalized = self::normalizeGeoJsonLineString(self::geoJsonFromCoordinates($p['coordinates']));
        $geojsonData = $normalized['geojson_data'];
        $waypointsCount = $normalized['waypoints_count'];

        $data = [
            'trip_id'        => $tripId,
            'transport_type' => $p['transport_type'],
            'geojson_data'   => $geojsonData,
            'is_round_trip'  => isset($p['is_round_trip']) ? (int)(bool)$p['is_round_trip'] : 0,
            'name'           => $p['name']           ?? null,
            'description'    => $p['description']    ?? null,
            'color'          => $p['color']          ?? '#3388ff',
            'start_datetime' => $p['start_datetime'] ?? null,
            'end_datetime'   => $p['end_datetime']   ?? null,
        ];

        $routeModel = new Route();
        $id = $routeModel->create($data);
        if (!$id) {
            throw new ToolException('Could not create the route in the database', 'DB_ERROR');
        }

        // Read distance calculated by the model (Haversine)
        $created = $routeModel->getById((int)$id);
        $distanceMeters = $created ? (int)$created['distance_meters'] : 0;

        if (!empty($p['links'])) {
            $linkModel = new Link();
            $links = array_map(function ($l) {
                return [
                    'link_type' => $l['link_type'] ?? 'other',
                    'url'       => $l['url'],
                    'label'     => $l['label'] ?? null,
                ];
            }, $p['links']);
            $linkModel->replaceForRoute((int)$id, $links);
        }

        McpLogger::info('create_route OK', [
            'id'       => $id,
            'trip_id'  => $tripId,
            'transport'=> $data['transport_type'],
            'dist_m'   => $distanceMeters,
            'waypoints'=> $waypointsCount,
        ]);

        return [
            'id'              => (int)$id,
            'trip_id'         => $tripId,
            'transport_type'  => $data['transport_type'],
            'distance_meters' => $distanceMeters,
            'distance_km'     => round($distanceMeters / 1000, 2),
            'waypoints_count' => $waypointsCount,
        ];
    }

    public static function updateRoute(array $p): array
    {
        $id = (int)$p['id'];
        $routeModel = new Route();
        $current = $routeModel->getById($id);
        if (!$current) {
            throw new ToolException("Route with id={$id} not found", 'ROUTE_NOT_FOUND');
        }

        // Merge current fields with provided ones; geometry is never changed
        $data = [
            'transport_type' => $p['transport_type'] ?? $current['transport_type'],
            'name'           => array_key_exists('name', $p)           ? $p['name']           : $current['name'],
            'description'    => array_key_exists('description', $p)    ? $p['description']    : $current['description'],
            'color'          => $p['color']          ?? $current['color'],
            'is_round_trip'  => array_key_exists('is_round_trip', $p)  ? (int)(bool)$p['is_round_trip'] : (int)$current['is_round_trip'],
            'start_datetime' => array_key_exists('start_datetime', $p) ? $p['start_datetime'] : $current['start_datetime'],
            'end_datetime'   => array_key_exists('end_datetime', $p)   ? $p['end_datetime']   : $current['end_datetime'],
            'geojson_data'   => $current['geojson_data'],
            'image_path'     => $current['image_path'],
        ];

        if (!$routeModel->update($id, $data)) {
            throw new ToolException('Could not update the route', 'DB_ERROR');
        }

        if (array_key_exists('links', $p)) {
            $linkModel = new Link();
            $links = array_map(function ($l) {
                return [
                    'link_type' => $l['link_type'] ?? 'other',
                    'url'       => $l['url'],
                    'label'     => $l['label'] ?? null,
                ];
            }, $p['links']);
            $linkModel->replaceForRoute($id, $links);
        }

        $updated = $routeModel->getById($id);
        $updLinks = (new Link())->getByEntity('route', $id);
        McpLogger::info('update_route OK', ['id' => $id]);

        return [
            'id'              => $id,
            'name'            => $updated['name'],
            'transport_type'  => $updated['transport_type'],
            'distance_meters' => (int)$updated['distance_meters'],
            'distance_km'     => round((int)$updated['distance_meters'] / 1000, 2),
            'links'           => array_map(fn($l) => ['url' => $l['url'], 'label' => $l['label'], 'link_type' => $l['link_type']], $updLinks),
            'admin_url'       => '/admin/route_form.php?id=' . $id,
        ];
    }

    public static function planRoute(array $p): array
    {
        $via = [];
        foreach ($p['via'] ?? [] as $wp) {
            $via[] = ['lat' => (float)$wp['lat'], 'lon' => (float)$wp['lon']];
        }

        $result = BRouterClient::planRoute(
            fromLat:       (float)$p['from_lat'],
            fromLon:       (float)$p['from_lon'],
            toLat:         (float)$p['to_lat'],
            toLon:         (float)$p['to_lon'],
            via:           $via,
            transportType: $p['transport_type']
        );

        if (!$result['success']) {
            throw new ToolException($result['error'], 'BROUTER_ERROR');
        }

        // Save GeoJSON to disk — does not pass through the LLM context
        $tempDir = ROOT_PATH . '/uploads/mcp_temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0750, true);
        }
        $tempFilename = 'route_' . uniqid('', true) . '.geojson';
        $tempAbsPath  = $tempDir . '/' . $tempFilename;
        $tempRelPath  = 'uploads/mcp_temp/' . $tempFilename;

        if (file_put_contents($tempAbsPath, $result['geojson_data']) === false) {
            throw new ToolException('Could not save the route temp file', 'SERVER_ERROR');
        }

        McpLogger::info('plan_route OK', [
            'transport' => $p['transport_type'],
            'profile'   => $result['profile'],
            'dist_km'   => $result['distance_km'],
            'duration'  => $result['duration_min'],
            'waypoints' => $result['waypoints_count'],
            'temp'      => $tempRelPath,
        ]);

        return [
            'temp_path'       => $tempRelPath,
            'distance_km'     => $result['distance_km'],
            'distance_meters' => $result['distance_meters'],
            'duration_min'    => $result['duration_min'],
            'waypoints_count' => $result['waypoints_count'],
            'profile'         => $result['profile'],
            'transport_type'  => $p['transport_type'],
            'start'           => $result['start'],
            'end'             => $result['end'],
            'bbox'            => $result['bbox'],
            'hint'            => 'Use commit_route with trip_id and temp_path to persist the route to the DB.',
        ];
    }

    public static function commitRoute(array $p): array
    {
        $tripId      = (int)$p['trip_id'];
        $tempRelPath = $p['temp_path'];

        // Validate that temp_path is inside mcp_temp (prevent path traversal)
        // Normalize separators for Windows compatibility (realpath returns backslashes)
        $tempDir  = str_replace('\\', '/', realpath(ROOT_PATH . '/uploads/mcp_temp'));
        $absPath  = str_replace('\\', '/', realpath(ROOT_PATH . '/' . $tempRelPath));
        if ($absPath === false || $tempDir === false || !str_starts_with($absPath, $tempDir . '/')) {
            throw new ToolException('Invalid temp_path or outside the allowed directory', 'INVALID_PATH', -32602);
        }
        if (!file_exists($absPath)) {
            throw new ToolException('Temp file not found. Run plan_route again.', 'TEMP_NOT_FOUND');
        }

        $geojsonData = file_get_contents($absPath);
        if ($geojsonData === false) {
            throw new ToolException('Could not read the temp file', 'READ_ERROR');
        }
        $geojsonData = self::normalizeGeoJsonLineString($geojsonData)['geojson_data'];

        self::assertTripExists($tripId);

        $data = [
            'trip_id'        => $tripId,
            'transport_type' => $p['transport_type'],
            'geojson_data'   => $geojsonData,
            'is_round_trip'  => isset($p['is_round_trip']) ? (int)(bool)$p['is_round_trip'] : 0,
            'name'           => $p['name']           ?? null,
            'description'    => $p['description']    ?? null,
            'color'          => $p['color']          ?? null,
            'start_datetime' => $p['start_datetime'] ?? null,
            'end_datetime'   => $p['end_datetime']   ?? null,
        ];

        $routeModel = new Route();
        $id = $routeModel->create($data);

        @unlink($absPath);

        if (!$id) {
            throw new ToolException('Could not create the route in the database', 'DB_ERROR');
        }

        if (!empty($p['links'])) {
            $linkModel = new Link();
            $links = array_map(fn($l) => [
                'link_type' => $l['link_type'] ?? 'other',
                'url'       => $l['url'],
                'label'     => $l['label'] ?? null,
            ], $p['links']);
            $linkModel->replaceForRoute((int)$id, $links);
        }

        $created = $routeModel->getById((int)$id);
        $distanceMeters = $created ? (int)$created['distance_meters'] : 0;

        McpLogger::info('commit_route OK', ['id' => $id, 'trip_id' => $tripId, 'dist_m' => $distanceMeters]);

        return [
            'id'              => (int)$id,
            'trip_id'         => $tripId,
            'name'            => $created['name'] ?? null,
            'transport_type'  => $data['transport_type'],
            'distance_meters' => $distanceMeters,
            'distance_km'     => round($distanceMeters / 1000, 2),
            'admin_url'       => '/admin/route_form.php?id=' . $id,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function geoJsonFromCoordinates(array $coordinates): string
    {
        $coords = [];
        foreach ($coordinates as $i => $coord) {
            if (!isset($coord['lat'], $coord['lon'])) {
                throw new ToolException("Coordinate {$i} must include lat and lon", 'INVALID_INPUT', -32602);
            }
            $coords[] = [(float)$coord['lon'], (float)$coord['lat']];
        }

        $json = json_encode([
            'type' => 'Feature',
            'properties' => new stdClass(),
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => $coords,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new ToolException('Could not serialize route coordinates', 'INVALID_INPUT', -32602);
        }

        return $json;
    }

    private static function normalizeGeoJsonLineString(string $geojson): array
    {
        $decoded = json_decode($geojson, true);
        if (!is_array($decoded)) {
            throw new ToolException('geojson_data is not valid JSON', 'INVALID_INPUT', -32602);
        }

        $type = $decoded['type'] ?? '';
        if ($type === 'FeatureCollection') {
            if (empty($decoded['features']) || !is_array($decoded['features'])) {
                throw new ToolException('FeatureCollection must contain at least one Feature', 'INVALID_INPUT', -32602);
            }
            $decoded = $decoded['features'][0];
        } elseif ($type !== 'Feature') {
            throw new ToolException('geojson_data must be a GeoJSON Feature or FeatureCollection', 'INVALID_INPUT', -32602);
        }

        if (($decoded['type'] ?? '') !== 'Feature') {
            throw new ToolException('GeoJSON item must be a Feature', 'INVALID_INPUT', -32602);
        }

        $geometry = $decoded['geometry'] ?? null;
        if (!is_array($geometry) || ($geometry['type'] ?? '') !== 'LineString') {
            throw new ToolException('GeoJSON geometry must be a LineString', 'INVALID_INPUT', -32602);
        }

        $coords = $geometry['coordinates'] ?? null;
        if (!is_array($coords) || count($coords) < 2) {
            throw new ToolException('GeoJSON LineString must contain at least two coordinates', 'INVALID_INPUT', -32602);
        }

        $normalizedCoords = [];
        foreach ($coords as $i => $coord) {
            if (!is_array($coord) || count($coord) < 2 || !is_numeric($coord[0]) || !is_numeric($coord[1])) {
                throw new ToolException("Invalid coordinate at index {$i}", 'INVALID_INPUT', -32602);
            }
            $lon = (float)$coord[0];
            $lat = (float)$coord[1];
            if (!is_finite($lon) || !is_finite($lat) || $lon < -180 || $lon > 180 || $lat < -90 || $lat > 90) {
                throw new ToolException("Coordinate out of range at index {$i}", 'INVALID_INPUT', -32602);
            }
            $normalizedCoords[] = [$lon, $lat];
        }

        $decoded['geometry']['coordinates'] = $normalizedCoords;
        if (!isset($decoded['properties']) || !is_array($decoded['properties'])) {
            $decoded['properties'] = new stdClass();
        }

        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new ToolException('Could not serialize normalized GeoJSON', 'INVALID_INPUT', -32602);
        }

        return [
            'geojson_data' => $encoded,
            'waypoints_count' => count($normalizedCoords),
        ];
    }

    private static function assertTripExists(int $tripId): void
    {
        $tripModel = new Trip();
        if (!$tripModel->getById($tripId)) {
            throw new ToolException("Trip with id={$tripId} not found", 'TRIP_NOT_FOUND');
        }
    }
}
