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
                'type'    => ['type' => 'string', 'enum' => ['stay', 'visit', 'food']],
                'limit'   => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'additionalProperties' => false,
        ], [self::class, 'searchPois']);

        $d->register('create_poi',
            'Creates a point of interest. ' .
            'To attach a photo, first upload it via POST /mcp/upload.php (multipart/form-data, field "photo"). ' .
            'That endpoint requires a Bearer token — always ask the user for their Bearer token before attempting any upload. ' .
            'Pass the returned photo_token here. ' .
            'Set use_exif to true only when the user explicitly confirms that coordinates and visit_date ' .
            'should be extracted from the photo EXIF (e.g. the photo was personally taken at this location). ' .
            'Never auto-fill from EXIF for planned/future trips or reference/decorative images.',
        [
            'type'       => 'object',
            'required'   => ['trip_id', 'type'],
            'properties' => [
                'trip_id'         => ['type' => 'integer', 'minimum' => 1],
                'title'           => ['type' => 'string', 'maxLength' => 200],
                'type'            => ['type' => 'string', 'enum' => ['stay', 'visit', 'food'], 'description' => '"stay": accommodation (hotel, hostel). "visit": tourist spot or attraction. "food": restaurant, bar, café.'],
                'latitude'        => ['type' => 'number', 'minimum' => -90,  'maximum' => 90],
                'longitude'       => ['type' => 'number', 'minimum' => -180, 'maximum' => 180],
                'description'     => ['type' => 'string', 'maxLength' => 5000],
                'icon'            => ['type' => 'string', 'maxLength' => 64, 'description' => 'Icon name. Defaults to "default" if omitted. Suggested values by type: stay→"hotel", visit→"camera", food→"restaurant".'],
                'visit_date'      => ['type' => 'string', 'description' => 'Date and time of the visit. Accepted formats: "YYYY-MM-DD HH:MM:SS", "YYYY-MM-DD HH:MM", "YYYY-MM-DDTHH:MM", "YYYY-MM-DD". Include the time if known.'],
                'photo_token'     => ['type' => 'string', 'pattern' => '^photo_[a-f0-9]{32}$', 'description' => 'Token returned by POST /mcp/upload.php. Requires Bearer token — ask the user before uploading.'],
                'use_exif'        => ['type' => 'boolean', 'description' => 'If true, auto-fill missing latitude/longitude and visit_date from the photo GPS EXIF. Only set to true when the user explicitly confirms the photo was taken at this location. Default: false.'],
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
            'To attach a photo, first upload it via POST /mcp/upload.php (multipart/form-data, field "photo"). ' .
            'That endpoint requires a Bearer token — always ask the user for their Bearer token before attempting any upload. ' .
            'Pass the returned photo_token here. ' .
            'Set use_exif to true only when the user explicitly confirms that coordinates and visit_date ' .
            'should be extracted from the photo EXIF (e.g. the photo was personally taken at this location). ' .
            'Never auto-fill from EXIF for planned/future trips or reference/decorative images.',
        [
            'type'       => 'object',
            'required'   => ['id'],
            'properties' => [
                'id'             => ['type' => 'integer', 'minimum' => 1],
                'title'          => ['type' => 'string', 'maxLength' => 200],
                'type'           => ['type' => 'string', 'enum' => ['stay', 'visit', 'food'], 'description' => '"stay": accommodation (hotel, hostel). "visit": tourist spot or attraction. "food": restaurant, bar, café.'],
                'latitude'       => ['type' => 'number', 'minimum' => -90,  'maximum' => 90],
                'longitude'      => ['type' => 'number', 'minimum' => -180, 'maximum' => 180],
                'description'    => ['type' => 'string', 'maxLength' => 5000],
                'icon'           => ['type' => 'string', 'maxLength' => 64, 'description' => 'Icon name. Defaults to "default" if omitted. Suggested values by type: stay→"hotel", visit→"camera", food→"restaurant".'],
                'visit_date'     => ['type' => 'string', 'description' => 'Date and time of the visit. Accepted formats: "YYYY-MM-DD HH:MM:SS", "YYYY-MM-DD HH:MM", "YYYY-MM-DDTHH:MM", "YYYY-MM-DD". Include the time if known.'],
                'photo_token'    => ['type' => 'string', 'pattern' => '^photo_[a-f0-9]{32}$', 'description' => 'Token returned by POST /mcp/upload.php. Requires Bearer token — ask the user before uploading.'],
                'use_exif'       => ['type' => 'boolean', 'description' => 'If true, auto-fill missing latitude/longitude and visit_date from the photo GPS EXIF. Only set to true when the user explicitly confirms the photo was taken at this location. Default: false.'],
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

        $d->register('inspect_uploaded_photo',
            'Inspects a photo previously uploaded to /mcp/upload.php and returns EXIF-derived metadata ' .
            '(GPS coordinates, capture date, MIME type, file size). ' .
            'The upload endpoint requires a Bearer token — always ask the user for their Bearer token before attempting any upload.',
        [
            'type'       => 'object',
            'required'   => ['photo_token'],
            'properties' => [
                'photo_token' => ['type' => 'string', 'pattern' => '^photo_[a-f0-9]{32}$'],
            ],
            'additionalProperties' => false,
        ], [self::class, 'inspectUploadedPhoto']);

        $d->register('cleanup_uploaded_photo',
            'Deletes a temporary uploaded photo that will not be used.',
        [
            'type'       => 'object',
            'required'   => ['photo_token'],
            'properties' => [
                'photo_token' => ['type' => 'string', 'pattern' => '^photo_[a-f0-9]{32}$'],
            ],
            'additionalProperties' => false,
        ], [self::class, 'cleanupUploadedPhoto']);

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
        $photoToken    = $p['photo_token'] ?? null;
        $photo         = $photoToken ? McpUploadedFiles::getPhoto($photoToken) : null;
        if ($photoToken && $photo === null) {
            throw new ToolException('photo_token inválido o expirado', 'PHOTO_NOT_FOUND', -32602);
        }
        $exifData = $photo['exif'] ?? null;

        // ── Auto-fill from EXIF (only when user explicitly requested it) ─────────
        $latitude  = isset($p['latitude'])  ? (float)$p['latitude']  : null;
        $longitude = isset($p['longitude']) ? (float)$p['longitude'] : null;
        $visitDate = $p['visit_date'] ?? null;

        if ($exifData && !empty($p['use_exif'])) {
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
            'image_path' => null,
            'visit_date' => self::normalizeVisitDate($visitDate),
        ];

        $pointModel = new Point();
        $errors     = $pointModel->validate($data);
        if (!empty($errors)) {
            throw new ToolException('Invalid POI data', 'INVALID_INPUT', -32602, ['fieldErrors' => $errors]);
        }

        if ($photoToken) {
            $uploadResult = McpUploadedFiles::consumePhoto($photoToken);
            if (!$uploadResult['success']) {
                throw new ToolException($uploadResult['error'] ?? 'Error saving the photo', 'UPLOAD_FAILED');
            }
            $imagePath = $uploadResult['path'];
            $thumbnailPath = $uploadResult['thumbnail_path'];
            $data['image_path'] = $imagePath;
        }

        $id = $pointModel->create($data);
        if (!$id) {
            if ($imagePath) {
                FileHelper::deleteFile($imagePath);
            }
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

        $imagePath     = $current['image_path'];
        $thumbnailPath = null;
        $autoFilled    = [];
        $photoToken    = $p['photo_token'] ?? null;
        $photo         = $photoToken ? McpUploadedFiles::getPhoto($photoToken) : null;
        if ($photoToken && $photo === null) {
            throw new ToolException('photo_token inválido o expirado', 'PHOTO_NOT_FOUND', -32602);
        }
        $exifData = $photo['exif'] ?? null;

        // ── Auto-fill coords/date from EXIF (only when user explicitly requested it) ──
        $latitude  = isset($p['latitude'])  ? (float)$p['latitude']  : null;
        $longitude = isset($p['longitude']) ? (float)$p['longitude'] : null;
        $visitDate = array_key_exists('visit_date', $p) ? $p['visit_date'] : null;

        if ($exifData && !empty($p['use_exif'])) {
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

        $data = [
            'trip_id'     => (int)$current['trip_id'],
            'title'       => array_key_exists('title', $p)       ? trim($p['title'])        : $current['title'],
            'type'        => $p['type']        ?? $current['type'],
            'latitude'    => $latitude  !== null ? $latitude  : (float)$current['latitude'],
            'longitude'   => $longitude !== null ? $longitude : (float)$current['longitude'],
            'description' => array_key_exists('description', $p) ? $p['description']        : $current['description'],
            'icon'        => $p['icon']        ?? $current['icon'],
            'image_path'  => $imagePath,
            'visit_date'  => $visitDate !== null ? self::normalizeVisitDate($visitDate) : $current['visit_date'],
        ];

        $validationErrors = $pointModel->validate($data, true);
        if (!empty($validationErrors)) {
            throw new ToolException('Validation failed', 'INVALID_INPUT', -32602, ['fieldErrors' => $validationErrors]);
        }

        $newImagePath = null;
        if ($photoToken) {
            $uploadResult = McpUploadedFiles::consumePhoto($photoToken);
            if (!$uploadResult['success']) {
                throw new ToolException($uploadResult['error'] ?? 'Error saving the photo', 'UPLOAD_FAILED');
            }
            $newImagePath = $uploadResult['path'];
            $thumbnailPath = $uploadResult['thumbnail_path'];
            $data['image_path'] = $newImagePath;
        }

        if (!$pointModel->update($id, $data)) {
            if ($newImagePath) {
                FileHelper::deleteFile($newImagePath);
            }
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
            'id'             => $id,
            'title'          => $updated['title'],
            'type'           => $updated['type'],
            'latitude'       => (float)$updated['latitude'],
            'longitude'      => (float)$updated['longitude'],
            'image_path'     => $updated['image_path'],
            'thumbnail_path' => $thumbnailPath,
            'auto_filled'    => $autoFilled ?: null,
            'links'          => array_map(fn($l) => ['url' => $l['url'], 'label' => $l['label'], 'link_type' => $l['link_type']], $updLinks),
            'admin_url'      => '/admin/point_form.php?id=' . $id,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────

    public static function inspectUploadedPhoto(array $p): array
    {
        $photo = McpUploadedFiles::getPhoto($p['photo_token']);
        if ($photo === null) {
            throw new ToolException('photo_token inválido o expirado', 'PHOTO_NOT_FOUND', -32602);
        }

        $info = McpUploadedFiles::publicPhotoInfo($photo);
        if ($info['has_gps']) {
            $info['suggested_place'] = self::suggestPlace((float)$info['latitude'], (float)$info['longitude']);
        }

        return $info;
    }

    public static function cleanupUploadedPhoto(array $p): array
    {
        return [
            'photo_token' => $p['photo_token'],
            'deleted' => McpUploadedFiles::deletePhoto($p['photo_token']),
        ];
    }

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

    private static function suggestPlace(float $latitude, float $longitude): ?array
    {
        try {
            return Geocoder::reverseLookup($latitude, $longitude);
        } catch (Exception $e) {
            McpLogger::error('Geocoder failed in POI photo flow: ' . $e->getMessage());
            return null;
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
