<?php
/**
 * MCP Tools: Trips
 * list_trips, search_trips, get_trip, create_trip
 */

final class TripTools
{
    public static function register(Dispatcher $d): void
    {
        $d->register('list_trips', 'Lists stored trips. Use search_trips for text search.', [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['draft', 'published', 'planned'], 'description' => '"draft": not visible to the public. "published": published and visible. "planned": future planned trip.'],
                'limit'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
                'order'  => ['type' => 'string', 'enum' => ['recent', 'oldest', 'start_date_desc', 'start_date_asc', 'title'], 'description' => '"recent"/"oldest": by creation date. "start_date_desc"/"start_date_asc": by trip start date. "title": alphabetical order.'],
            ],
            'additionalProperties' => false,
        ], [self::class, 'listTrips']);

        $d->register('search_trips', 'Searches trips by free text in title/description, tag or date range.', [
            'type' => 'object',
            'properties' => [
                'query'     => ['type' => 'string', 'maxLength' => 200],
                'tag'       => ['type' => 'string', 'maxLength' => 60],
                'date_from' => ['type' => 'string', 'description' => 'Minimum date in YYYY-MM-DD format. Filters trips ending on or after this date.'],
                'date_to'   => ['type' => 'string', 'description' => 'Maximum date in YYYY-MM-DD format. Filters trips starting on or before this date.'],
                'status'    => ['type' => 'string', 'enum' => ['draft', 'published', 'planned'], 'description' => '"draft": draft. "published": published. "planned": planned.'],
                'limit'     => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'additionalProperties' => false,
        ], [self::class, 'searchTrips']);

        $d->register('get_trip', 'Retrieves a full trip with its routes, POIs and tags.', [
            'type'       => 'object',
            'required'   => ['id'],
            'properties' => [
                'id'              => ['type' => 'integer', 'minimum' => 1],
                'include_geojson' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ], [self::class, 'getTrip']);

        $d->register('update_trip',
            'Updates the data of an existing trip. Only the provided fields are modified. ' .
            'For tags and links supply the full array (replaces existing ones).',
        [
            'type'       => 'object',
            'required'   => ['id'],
            'properties' => [
                'id'          => ['type' => 'integer', 'minimum' => 1],
                'title'       => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                'description' => ['type' => 'string', 'maxLength' => 5000],
                'start_date'  => ['type' => 'string', 'description' => 'Start date in YYYY-MM-DD format. Example: "2024-07-15".'],
                'end_date'    => ['type' => 'string', 'description' => 'End date in YYYY-MM-DD format. Example: "2024-08-03".'],
                'color_hex'   => ['type' => 'string', 'pattern' => '^#[0-9A-Fa-f]{6}$', 'description' => 'Trip color as a CSS hex string. Example: "#3388ff".'],
                'status'      => ['type' => 'string', 'enum' => ['draft', 'published', 'planned'], 'description' => '"draft": draft, not visible. "published": published and visible. "planned": future planned trip.'],
                'show_routes_in_timeline' => ['type' => ['boolean', 'null'], 'description' => 'Whether routes are shown in the trip timeline. null = inherits the global site setting.'],
                'tags'        => ['type' => 'array', 'maxItems' => 20, 'items' => ['type' => 'string', 'maxLength' => 60]],
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
        ], [self::class, 'updateTrip']);

        $d->register('create_trip', 'Creates a new trip. Returns the created id.', [
            'type'       => 'object',
            'required'   => ['title'],
            'properties' => [
                'title'       => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                'description' => ['type' => 'string', 'maxLength' => 5000],
                'start_date'  => ['type' => 'string', 'description' => 'Start date in YYYY-MM-DD format. Example: "2024-07-15".'],
                'end_date'    => ['type' => 'string', 'description' => 'End date in YYYY-MM-DD format. Example: "2024-08-03".'],
                'color_hex'   => ['type' => 'string', 'pattern' => '^#[0-9A-Fa-f]{6}$', 'description' => 'Trip color as a CSS hex string. Example: "#3388ff". Default: "#3388ff".'],
                'status'      => ['type' => 'string', 'enum' => ['draft', 'published', 'planned'], 'description' => '"draft": draft, not visible. "published": published and visible. "planned": future planned trip.'],
                'show_routes_in_timeline' => ['type' => ['boolean', 'null'], 'description' => 'Whether routes are shown in the trip timeline. null = inherits the global site setting.'],
                'tags'        => ['type' => 'array', 'maxItems' => 20, 'items' => ['type' => 'string', 'maxLength' => 60]],
            ],
            'additionalProperties' => false,
        ], [self::class, 'createTrip']);
    }

    // ──────────────────────────────────────────────────────────────────────────

    public static function listTrips(array $p): array
    {
        $orderMap = [
            'recent'          => 'created_at DESC',
            'oldest'          => 'created_at ASC',
            'start_date_desc' => 'start_date DESC',
            'start_date_asc'  => 'start_date ASC',
            'title'           => 'title ASC',
        ];
        $orderBy = $orderMap[$p['order'] ?? 'recent'] ?? 'created_at DESC';
        $status  = $p['status'] ?? null;
        $limit   = min((int)($p['limit'] ?? 50), 200);

        $tripModel = new Trip();
        $rows      = $tripModel->getAll($orderBy, $status, $limit);

        $trips = self::buildSummaries($rows, $tripModel);

        return ['trips' => $trips, 'count' => count($trips)];
    }

    public static function searchTrips(array $p): array
    {
        $tripModel = new Trip();
        $rows = $tripModel->search(
            $p['query']     ?? null,
            $p['tag']       ?? null,
            $p['date_from'] ?? null,
            $p['date_to']   ?? null,
            $p['status']    ?? null,
            (int)($p['limit'] ?? 25)
        );

        $trips = self::buildSummaries($rows, $tripModel);

        return ['trips' => $trips, 'count' => count($trips)];
    }

    public static function getTrip(array $p): array
    {
        $tripModel = new Trip();
        $trip      = $tripModel->getById((int)$p['id']);

        if (!$trip) {
            throw new ToolException("Trip with id={$p['id']} not found", 'TRIP_NOT_FOUND');
        }

        $routeModel = new Route();
        $pointModel = new Point();
        $tagModel   = new TripTag();
        $linkModel  = new Link();

        $routes = $routeModel->getByTripId($trip['id']);
        $pois   = $pointModel->getAll($trip['id']);
        $tags   = $tagModel->getByTripId($trip['id']);
        $links  = $linkModel->getByEntity('trip', (int)$trip['id']);

        $includeGeojson = (bool)($p['include_geojson'] ?? false);

        $routesOut = [];
        foreach ($routes as $r) {
            $routeLinks = $linkModel->getByEntity('route', (int)$r['id']);
            $out = [
                'id'             => (int)$r['id'],
                'name'           => $r['name'],
                'transport_type' => $r['transport_type'],
                'distance_meters'=> (int)$r['distance_meters'],
                'distance_km'    => round((int)$r['distance_meters'] / 1000, 2),
                'is_round_trip'  => (bool)$r['is_round_trip'],
                'start_datetime' => $r['start_datetime'] ?? null,
                'end_datetime'   => $r['end_datetime'] ?? null,
                'color'          => $r['color'],
                'description'    => $r['description'],
                'links'          => self::formatLinks($routeLinks),
            ];
            if ($includeGeojson) {
                $out['geojson_data'] = $r['geojson_data'];
            }
            $routesOut[] = $out;
        }

        $poisOut = [];
        foreach ($pois as $poi) {
            $poiLinks = $linkModel->getByEntity('poi', (int)$poi['id']);
            $poisOut[] = [
                'id'         => (int)$poi['id'],
                'title'      => $poi['title'],
                'type'       => $poi['type'],
                'latitude'   => (float)$poi['latitude'],
                'longitude'  => (float)$poi['longitude'],
                'visit_date' => $poi['visit_date'],
                'image_path' => $poi['image_path'],
                'description'=> $poi['description'],
                'links'      => self::formatLinks($poiLinks),
            ];
        }

        return [
            'trip' => [
                'id'                      => (int)$trip['id'],
                'title'                   => $trip['title'],
                'description'             => $trip['description'],
                'start_date'              => $trip['start_date'],
                'end_date'                => $trip['end_date'],
                'status'                  => $trip['status'],
                'color_hex'               => $trip['color_hex'],
                'show_routes_in_timeline' => isset($trip['show_routes_in_timeline'])
                    ? (is_null($trip['show_routes_in_timeline']) ? null : (bool)$trip['show_routes_in_timeline'])
                    : null,
                'tags'                    => $tags,
                'links'                   => self::formatLinks($links),
                'routes'                  => $routesOut,
                'pois'                    => $poisOut,
            ],
        ];
    }

    public static function createTrip(array $p): array
    {
        $tripModel = new Trip();

        $showRoutes = array_key_exists('show_routes_in_timeline', $p)
            ? (is_null($p['show_routes_in_timeline']) ? null : (int)(bool)$p['show_routes_in_timeline'])
            : null;

        $data = [
            'title'                   => trim($p['title']),
            'description'             => isset($p['description']) ? trim($p['description']) : null,
            'start_date'              => $p['start_date']  ?? null,
            'end_date'                => $p['end_date']    ?? null,
            'color_hex'               => $p['color_hex']   ?? '#3388ff',
            'status'                  => $p['status']      ?? 'draft',
            'show_routes_in_timeline' => $showRoutes,
        ];

        $errors = $tripModel->validate($data);
        if (!empty($errors)) {
            throw new ToolException('Invalid trip data', 'INVALID_INPUT', -32602, ['fieldErrors' => $errors]);
        }

        $id = $tripModel->create($data);
        if (!$id) {
            throw new ToolException('Could not create the trip in the database', 'DB_ERROR');
        }

        $tags = $p['tags'] ?? [];
        if (!empty($tags)) {
            $tagModel = new TripTag();
            $tagModel->sync((int)$id, $tags);
        }

        McpLogger::info("create_trip OK", ['id' => $id, 'title' => $data['title']]);

        return [
            'id'      => (int)$id,
            'title'   => $data['title'],
            'status'  => $data['status'],
            'admin_url' => '/admin/trip_form.php?id=' . $id,
        ];
    }

    public static function updateTrip(array $p): array
    {
        $id = (int)$p['id'];
        $tripModel = new Trip();
        $trip = $tripModel->getById($id);
        if (!$trip) {
            throw new ToolException("Trip with id={$id} not found", 'TRIP_NOT_FOUND');
        }

        $updatableFields = ['title', 'description', 'start_date', 'end_date', 'color_hex', 'status', 'show_routes_in_timeline'];
        $data = [];
        foreach ($updatableFields as $field) {
            if (!array_key_exists($field, $p)) {
                continue;
            }
            if ($field === 'show_routes_in_timeline') {
                $data[$field] = is_null($p[$field]) ? null : (int)(bool)$p[$field];
            } else {
                $data[$field] = $p[$field];
            }
        }

        if (!empty($data)) {
            $errors = $tripModel->validate(array_merge([
                'title'     => $trip['title'],
                'color_hex' => $trip['color_hex'],
                'status'    => $trip['status'],
            ], $data));
            if (!empty($errors)) {
                throw new ToolException('Invalid trip data', 'INVALID_INPUT', -32602, ['fieldErrors' => $errors]);
            }
            if (!$tripModel->update($id, $data)) {
                throw new ToolException('Could not update the trip', 'DB_ERROR');
            }
        }

        if (array_key_exists('tags', $p)) {
            (new TripTag())->sync($id, $p['tags']);
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
            $linkModel->replaceForEntity('trip', $id, $links);
        }

        $updated = $tripModel->getById($id);
        McpLogger::info('update_trip OK', ['id' => $id]);

        return [
            'id'        => $id,
            'title'     => $updated['title'],
            'status'    => $updated['status'],
            'admin_url' => '/admin/trip_form.php?id=' . $id,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function formatLinks(array $rows): array
    {
        return array_map(function ($r) {
            return [
                'url'        => $r['url'],
                'label'      => $r['label'],
                'link_type'  => $r['link_type'],
            ];
        }, $rows);
    }

    /**
     * Builds summaries for a list of trip rows using batch queries to avoid N+1.
     */
    private static function buildSummaries(array $rows, Trip $tripModel): array
    {
        if (empty($rows)) return [];
        $ids    = array_column($rows, 'id');
        $counts = $tripModel->getBatchCounts($ids);
        return array_map(
            fn($row) => self::tripSummary($row, $counts['route_counts'], $counts['poi_counts']),
            $rows
        );
    }

    private static function tripSummary(array $row, array $routeCounts, array $poiCounts): array
    {
        $id = (int)$row['id'];
        return [
            'id'                      => $id,
            'title'                   => $row['title'],
            'description'             => $row['description'],
            'start_date'              => $row['start_date'],
            'end_date'                => $row['end_date'],
            'status'                  => $row['status'],
            'color_hex'               => $row['color_hex'],
            'show_routes_in_timeline' => isset($row['show_routes_in_timeline'])
                ? (is_null($row['show_routes_in_timeline']) ? null : (bool)$row['show_routes_in_timeline'])
                : null,
            'route_count'             => (int)($routeCounts[$id] ?? 0),
            'poi_count'               => (int)($poiCounts[$id] ?? 0),
        ];
    }
}
