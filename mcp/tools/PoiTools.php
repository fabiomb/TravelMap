<?php
/**
 * MCP Tools: POIs
 * search_pois, create_poi, update_poi
 */

final class PoiTools
{
    public static function register(Dispatcher $d): void
    {
        $d->register('search_pois', 'Searches POIs by free text, trip or type.', [
            'type' => 'object',
            'properties' => [
                'query'   => ['type' => 'string', 'maxLength' => 200],
                'trip_id' => ['type' => 'integer', 'minimum' => 1],
                'type'    => ['type' => 'string', 'enum' => ['stay', 'visit', 'food', 'waypoint']],
                'limit'   => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'additionalProperties' => false,
        ], [self::class, 'searchPois']);

        $d->register('create_poi',
            'Creates a point of interest. If you attach a photo with GPS EXIF, coordinates and ' .
            'date are auto-filled if not provided. The output includes suggested_place ' .
            '(city suggested by Nominatim) to help you choose the title.',
        [
            'type'       => 'object',
            'required'   => ['trip_id', 'type'],
            'properties' => [
                'trip_id'         => ['type' => 'integer', 'minimum' => 1],
                'title'           => ['type' => 'string', 'maxLength' => 200],
                'type'            => ['type' => 'string', 'enum' => ['stay', 'visit', 'food', 'waypoint'], 'description' => '"stay": accommodation (hotel, hostel). "visit": tourist spot or attraction. "food": restaurant, bar, café. "waypoint": waypoint or generic reference.'],
                'latitude'        => ['type' => 'number', 'minimum' => -90,  'maximum' => 90],
                'longitude'       => ['type' => 'number', 'minimum' => -180, 'maximum' => 180],
                'description'     => ['type' => 'string', 'maxLength' => 5000],
                'icon'            => ['type' => 'string', 'maxLength' => 64, 'description' => 'Icon name. Defaults to "default" if omitted. Suggested values by type: stay→"hotel", visit→"camera", food→"restaurant".'],
                'visit_date'      => ['type' => 'string', 'description' => 'Date and time of the visit. Accepted formats: "YYYY-MM-DD HH:MM:SS", "YYYY-MM-DD HH:MM", "YYYY-MM-DDTHH:MM", "YYYY-MM-DD". Include the time if known.'],
                'photo_base64'    => ['type' => 'string', 'maxLength' => 14000000],
                'photo_filename'  => ['type' => 'string', 'maxLength' => 255],
                'links' => [
                    'type' => 'array',
                    'maxItems' => 10,
                    'items' => [
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
        ], [self::class, 'createPoi']);

        $d->register('update_poi',
            'Updates the data of an existing POI. Only the provided fields are modified. ' .
            'To update links supply the full array (replaces existing ones). ' .
            'Photo change is not supported; create a new POI for that.',
        [
            'type'       => 'object',
            'required'   => ['id'],
            'properties' => [
                'id'          => ['type' => 'integer', 'minimum' => 1],
                'title'       => ['type' => 'string', 'maxLength' => 200],
                'type'        => ['type' => 'string', 'enum' => ['stay', 'visit', 'food', 'waypoint'], 'description' => '"stay": accommodation (hotel, hostel). "visit": tourist spot or attraction. "food": restaurant, bar, café. "waypoint": waypoint or generic reference.'],
                'latitude'    => ['type' => 'number', 'minimum' => -90,  'maximum' => 90],
                'longitude'   => ['type' => 'number', 'minimum' => -180, 'maximum' => 180],
                'description' => ['type' => 'string', 'maxLength' => 5000],
                'icon'        => ['type' => 'string', 'maxLength' => 64, 'description' => 'Icon name. Defaults to "default" if omitted. Suggested values by type: stay→"hotel", visit→"camera", food→"restaurant".'],
                'visit_date'  => ['type' => 'string', 'description' => 'Date and time of the visit. Accepted formats: "YYYY-MM-DD HH:MM:SS", "YYYY-MM-DD HH:MM", "YYYY-MM-DDTHH:MM", "YYYY-MM-DD". Include the time if known.'],
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
        ], [self::class, 'updatePoi']);

    }

    // ──────────────────────────────────────────────────────────────────────────

    public static function searchPois(array $p): array
    {
        $pointModel = new Point();
        $rows = $pointModel->search(
            $p['query']   ?? null,
            isset($p['trip_id']) ? (int)$p['trip_id'] : null,
            $p['type']    ?? null,
            (int)($p['limit'] ?? 25)
        );

        return ['pois' => array_map([self::class, 'poiSummary'], $rows), 'count' => count($rows)];
    }

    public static function createPoi(array $p): array
    {
        $tripId = (int)$p['trip_id'];
        self::assertTripExists($tripId);

        $imagePath     = null;
        $thumbnailPath = null;
        $autoFilled    = [];
        $exifData      = null;

        // ── Process photo ──────────────────────────────────────────────────────
        if (!empty($p['photo_base64'])) {
            if (empty($p['photo_filename'])) {
                throw new ToolException('photo_filename is required when photo_base64 is provided', 'INVALID_INPUT', -32602);
            }
            $uploadResult = FileHelper::saveImageFromBase64(
                $p['photo_base64'],
                $p['photo_filename']
            );
            if (!$uploadResult['success']) {
                throw new ToolException($uploadResult['error'] ?? 'Error saving the photo', 'UPLOAD_FAILED');
            }
            $imagePath     = $uploadResult['path'];
            $thumbnailPath = $uploadResult['thumbnail_path'];

            // Extract EXIF from the saved image
            $fullPath = ROOT_PATH . '/' . $imagePath;
            $exifData = ExifExtractor::readFromFile($fullPath, $p['photo_filename']);

            McpLogger::info('create_poi: photo uploaded', [
                'path'    => $imagePath,
                'size_kb' => round(strlen($p['photo_base64']) * 0.75 / 1024),
            ]);

        }

        // ── Auto-fill from EXIF ────────────────────────────────────────────────
        $latitude  = isset($p['latitude'])  ? (float)$p['latitude']  : null;
        $longitude = isset($p['longitude']) ? (float)$p['longitude'] : null;
        $visitDate = $p['visit_date'] ?? null;

        if ($exifData) {
            if ($latitude === null && $exifData['has_gps']) {
                $latitude  = $exifData['latitude'];
                $longitude = $exifData['longitude'];
                $autoFilled['latitude']  = $latitude;
                $autoFilled['longitude'] = $longitude;
            }
            if ($visitDate === null && $exifData['has_date']) {
                $visitDate = $exifData['date'];
                $autoFilled['visit_date'] = $visitDate;
            }
        }

        if ($latitude === null || $longitude === null) {
            throw new ToolException(
                'Could not determine coordinates. Provide latitude/longitude or a photo with GPS EXIF.',
                'COORDINATES_REQUIRED', -32602
            );
        }

        // ── Suggested place via Nominatim ──────────────────────────────────────
        $suggestedPlace = null;
        try {
            $suggestedPlace = Geocoder::reverseLookup($latitude, $longitude);
        } catch (Exception $e) {
            // silent — non-critical
            McpLogger::error('Geocoder failed in create_poi: ' . $e->getMessage());
        }

        // ── Create POI ─────────────────────────────────────────────────────────
        $title = isset($p['title']) && $p['title'] !== '' ? trim($p['title']) : 'Untitled POI';

        $data = [
            'trip_id'    => $tripId,
            'title'      => $title,
            'type'       => $p['type'],
            'latitude'   => $latitude,
            'longitude'  => $longitude,
            'description'=> $p['description'] ?? null,
            'icon'       => $p['icon']        ?? 'default',
            'image_path' => $imagePath,
            'visit_date' => self::normalizeVisitDate($visitDate),
        ];

        $pointModel = new Point();
        $errors     = $pointModel->validate($data);
        if (!empty($errors)) {
            throw new ToolException('Invalid POI data', 'INVALID_INPUT', -32602, ['fieldErrors' => $errors]);
        }

        $id = $pointModel->create($data);
        if (!$id) {
            throw new ToolException('Could not create the POI in the database', 'DB_ERROR');
        }

        if (!empty($p['links'])) {
            $linkModel = new Link();
            $links = array_map(function ($l) {
                return [
                    'link_type' => $l['link_type'] ?? 'other',
                    'url'       => $l['url'],
                    'label'     => $l['label'] ?? null,
                ];
            }, $p['links']);
            $linkModel->replaceForPoi((int)$id, $links);
        }

        McpLogger::info('create_poi OK', [
            'id'      => $id,
            'trip_id' => $tripId,
            'title'   => $title,
            'lat'     => $latitude,
            'lng'     => $longitude,
        ]);

        return [
            'id'              => (int)$id,
            'title'           => $title,
            'trip_id'         => $tripId,
            'latitude'        => $latitude,
            'longitude'       => $longitude,
            'image_path'      => $imagePath,
            'thumbnail_path'  => $thumbnailPath,
            'auto_filled'     => $autoFilled ?: null,
            'suggested_place' => $suggestedPlace,
            'admin_url'       => '/admin/point_form.php?id=' . $id,
        ];
    }

    public static function updatePoi(array $p): array
    {
        $id = (int)$p['id'];
        $pointModel = new Point();
        $current = $pointModel->getById($id);
        if (!$current) {
            throw new ToolException("POI with id={$id} not found", 'POI_NOT_FOUND');
        }

        $data = [
            'trip_id'     => (int)$current['trip_id'],
            'title'       => array_key_exists('title', $p)       ? trim($p['title'])        : $current['title'],
            'type'        => $p['type']        ?? $current['type'],
            'latitude'    => isset($p['latitude'])  ? (float)$p['latitude']  : (float)$current['latitude'],
            'longitude'   => isset($p['longitude']) ? (float)$p['longitude'] : (float)$current['longitude'],
            'description' => array_key_exists('description', $p) ? $p['description']        : $current['description'],
            'icon'        => $p['icon']        ?? $current['icon'],
            'image_path'  => $current['image_path'],
            'visit_date'  => array_key_exists('visit_date', $p)  ? self::normalizeVisitDate($p['visit_date']) : $current['visit_date'],
        ];

        $validationErrors = $pointModel->validate($data, true);
        if (!empty($validationErrors)) {
            throw new ToolException('Validation failed', 'INVALID_INPUT', -32602, ['fieldErrors' => $validationErrors]);
        }

        if (!$pointModel->update($id, $data)) {
            throw new ToolException('Could not update the POI', 'DB_ERROR');
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
            $linkModel->replaceForPoi($id, $links);
        }

        $updated  = $pointModel->getById($id);
        $updLinks = (new Link())->getByEntity('poi', $id);
        McpLogger::info('update_poi OK', ['id' => $id]);

        return [
            'id'        => $id,
            'title'     => $updated['title'],
            'type'      => $updated['type'],
            'latitude'  => (float)$updated['latitude'],
            'longitude' => (float)$updated['longitude'],
            'links'     => array_map(fn($l) => ['url' => $l['url'], 'label' => $l['label'], 'link_type' => $l['link_type']], $updLinks),
            'admin_url' => '/admin/point_form.php?id=' . $id,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function normalizeVisitDate(?string $date): ?string
    {
        if ($date === null || $date === '') return null;
        $formats = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $date);
            if ($dt !== false) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        return $date;
    }

    private static function assertTripExists(int $tripId): void
    {
        $tripModel = new Trip();
        if (!$tripModel->getById($tripId)) {
            throw new ToolException("Trip with id={$tripId} not found", 'TRIP_NOT_FOUND');
        }
    }

    private static function poiSummary(array $poi, array $links = []): array
    {
        $out = [
            'id'         => (int)$poi['id'],
            'trip_id'    => (int)$poi['trip_id'],
            'title'      => $poi['title'],
            'type'       => $poi['type'],
            'latitude'   => (float)$poi['latitude'],
            'longitude'  => (float)$poi['longitude'],
            'visit_date' => $poi['visit_date'],
            'image_path' => $poi['image_path'],
        ];
        if (!empty($links)) {
            $out['links'] = array_map(fn($l) => ['url' => $l['url'], 'label' => $l['label'], 'link_type' => $l['link_type']], $links);
        }
        return $out;
    }
}
