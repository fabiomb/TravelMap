<?php
/**
 * Helper: BRouterParser
 *
 * Parsea archivos CSV exportados por BRouter (brouter.de) y genera GeoJSON.
 * Extraído de admin/import_brouter.php para reutilización en MCP y tests.
 *
 * BRouter exporta lon/lat como enteros × 1.000.000 (microgrados).
 * Ej: 23944734 → 23.944734°
 *
 * Columnas esperadas: Longitude, Latitude, Elevation, Distance (metros acum.),
 * CostPerKm, ElevCost, TurnCost, NodeCost, InitialCost,
 * WayTags, NodeTags, Time (segundos acum.), Energy
 */

defined('MAX_CSV_BYTES')   || define('MAX_CSV_BYTES',   5 * 1024 * 1024); // 5 MB
defined('MAX_WAYPOINTS')   || define('MAX_WAYPOINTS',   50_000);
defined('MAX_DIST_METERS') || define('MAX_DIST_METERS', 4_294_967_295);   // int UNSIGNED max

final class BRouterParser
{
    /**
     * Parsea un CSV de BRouter desde ruta de archivo.
     *
     * @param string $filePath Ruta absoluta al archivo CSV.
     * @return array {
     *   success: bool, error?: string,
     *   coordinates: array,     // downsampled para vista previa (≤500 puntos)
     *   waypoints_count: int,
     *   distance_km: float,
     *   distance_meters: int,
     *   duration_min: int,
     *   rail_type: string,
     *   geojson_data: string,   // GeoJSON completo para almacenar en DB
     *   start: {lon,lat},
     *   end: {lon,lat},
     *   bbox: {minLon,maxLon,minLat,maxLat}
     * }
     */
    public static function parseFromFile(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['success' => false, 'error' => 'No se pudo abrir el archivo CSV.'];
        }

        // Detectar separador: BRouter usa TAB, aceptar coma como fallback
        $firstLine = fgets($handle);
        rewind($handle);
        $separator = ($firstLine !== false && substr_count($firstLine, "\t") > 0) ? "\t" : ',';

        $header         = null;
        $coordinates    = [];
        $totalDistanceM = 0;
        $totalTimeSec   = 0;
        $railTypes      = [];

        while (($row = fgetcsv($handle, 4096, $separator)) !== false) {
            if ($header === null) {
                $header = array_map('trim', $row);
                if (!in_array('Longitude', $header, true) || !in_array('Latitude', $header, true)) {
                    fclose($handle);
                    return ['success' => false, 'error' => 'El CSV no tiene el formato de BRouter (faltan columnas Longitude/Latitude).'];
                }
                continue;
            }

            if (count($coordinates) >= MAX_WAYPOINTS) {
                break;
            }

            $data = self::parseRow($header, $row);
            if ($data) {
                $coordinates[]  = $data['coord'];
                $totalDistanceM = max($totalDistanceM, $data['distance']);
                $totalTimeSec   = max($totalTimeSec,   $data['time']);
                if ($data['rail_type']) {
                    $railTypes[] = $data['rail_type'];
                }
            }
        }
        fclose($handle);

        if (count($coordinates) < 2) {
            return ['success' => false, 'error' => 'El CSV no contiene suficientes coordenadas (mínimo 2 puntos válidos).'];
        }

        $railTypeCounts = array_count_values($railTypes);
        arsort($railTypeCounts);
        $dominantRail = array_key_first($railTypeCounts) ?? 'unknown';

        $geojsonData = json_encode([
            'type'       => 'Feature',
            'properties' => [
                'transport'    => 'brouter',
                'rail_subtype' => $dominantRail,
                'distance_km'  => round($totalDistanceM / 1000, 2),
            ],
            'geometry' => [
                'type'        => 'LineString',
                'coordinates' => $coordinates, // [lon, lat] — orden GeoJSON estándar
            ],
        ], JSON_UNESCAPED_UNICODE);

        if ($geojsonData === false) {
            return ['success' => false, 'error' => 'Error al construir el GeoJSON. El archivo puede contener caracteres inválidos.'];
        }

        $distMeters = max(0, min($totalDistanceM, MAX_DIST_METERS));

        return [
            'success'         => true,
            'coordinates'     => self::downsampleCoordinates($coordinates, 500),
            'waypoints_count' => count($coordinates),
            'distance_km'     => round($totalDistanceM / 1000, 2),
            'distance_meters' => $distMeters,
            'duration_min'    => round($totalTimeSec / 60),
            'rail_type'       => $dominantRail,
            'geojson_data'    => $geojsonData,
            'start'           => ['lon' => $coordinates[0][0],        'lat' => $coordinates[0][1]],
            'end'             => ['lon' => end($coordinates)[0],       'lat' => end($coordinates)[1]],
            'bbox'            => self::getBoundingBox($coordinates),
        ];
    }

    /**
     * Parsea un CSV de BRouter desde string en memoria.
     * Escribe a un archivo temporal y llama a parseFromFile.
     *
     * @param string $csv Contenido CSV.
     * @return array Igual que parseFromFile.
     */
    public static function parseFromString(string $csv): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'brouter_str_');
        if ($tmpFile === false) {
            return ['success' => false, 'error' => 'No se pudo crear archivo temporal.'];
        }

        try {
            if (file_put_contents($tmpFile, $csv) === false) {
                return ['success' => false, 'error' => 'No se pudo escribir el archivo temporal.'];
            }
            return self::parseFromFile($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ──────────────────────────────────────────────────────────────────────────

    private static function parseRow(array $header, array $row): ?array
    {
        if (count($header) !== count($row)) return null;
        $data = array_combine($header, $row);
        if (!$data) return null;

        $lon = isset($data['Longitude']) ? (float)$data['Longitude'] / 1e6 : null;
        $lat = isset($data['Latitude'])  ? (float)$data['Latitude']  / 1e6 : null;

        if ($lon === null || $lat === null) return null;
        if (abs($lon) < 0.001 && abs($lat) < 0.001) return null;
        if ($lon < -180.0 || $lon > 180.0 || $lat < -90.0 || $lat > 90.0) return null;

        $distance = isset($data['Distance']) ? max(0, (int)$data['Distance']) : 0;
        $time     = isset($data['Time'])     ? max(0, (int)$data['Time'])     : 0;
        $wayTags  = $data['WayTags'] ?? '';

        $railType = null;
        if (preg_match('/railway=(\w+)/', $wayTags, $m)) {
            $railType = $m[1];
        }

        return [
            'coord'     => [$lon, $lat],
            'distance'  => $distance,
            'time'      => $time,
            'rail_type' => $railType,
        ];
    }

    private static function downsampleCoordinates(array $coords, int $maxPoints): array
    {
        $total = count($coords);
        if ($total <= $maxPoints) return $coords;
        $step   = $total / $maxPoints;
        $result = [];
        for ($i = 0; $i < $maxPoints; $i++) {
            $result[] = $coords[(int)($i * $step)];
        }
        $last = end($coords);
        if ($result[count($result) - 1] !== $last) {
            $result[] = $last;
        }
        return $result;
    }

    private static function getBoundingBox(array $coords): array
    {
        $lons = array_column($coords, 0);
        $lats = array_column($coords, 1);
        return [
            'minLon' => min($lons), 'maxLon' => max($lons),
            'minLat' => min($lats), 'maxLat' => max($lats),
        ];
    }
}
