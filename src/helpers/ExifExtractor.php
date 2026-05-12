<?php
/**
 * Helper: ExifExtractor
 *
 * Extrae datos GPS y fecha de imágenes JPEG con datos EXIF.
 * Incluye fallback de fecha por regex en nombre de archivo e interpolación
 * de coordenadas entre fotos contiguas.
 *
 * Extraído de api/import_exif_upload.php para reutilización en MCP y tests.
 */

final class ExifExtractor
{
    /**
     * Lee GPS + fecha de un archivo de imagen.
     * Incluye fallback de fecha por regex en el nombre de archivo.
     *
     * @param string $filePath       Ruta absoluta al archivo.
     * @param string $originalFilename Nombre original (para regex de fecha en nombre).
     * @return array {
     *   has_gps: bool,
     *   has_date: bool,
     *   latitude: float|null,
     *   longitude: float|null,
     *   date: string|null,      // formato: 'Y-m-d\TH:i'
     *   timestamp: int|null,    // Unix timestamp
     *   gps_estimated: bool,    // true si fue interpolado
     *   date_source: string|null // 'exif'|'filename_pattern'
     * }
     */
    public static function readFromFile(string $filePath, string $originalFilename = ''): array
    {
        $result = [
            'has_gps'       => false,
            'has_date'      => false,
            'latitude'      => null,
            'longitude'     => null,
            'date'          => null,
            'timestamp'     => null,
            'gps_estimated' => false,
            'date_source'   => null,
        ];

        if (!function_exists('exif_read_data')) {
            return $result;
        }

        $exif = @exif_read_data($filePath, 0, true);
        if (!$exif) {
            $exif = [];
        }

        // ── Fecha ─────────────────────────────────────────────────────────────
        $dateStr = $exif['EXIF']['DateTimeOriginal']
            ?? $exif['EXIF']['DateTimeDigitized']
            ?? $exif['IFD0']['DateTime']
            ?? null;

        if ($dateStr) {
            $dt = DateTime::createFromFormat('Y:m:d H:i:s', trim($dateStr));
            if ($dt) {
                $result['has_date']   = true;
                $result['date']       = $dt->format('Y-m-d\TH:i');
                $result['timestamp']  = $dt->getTimestamp();
                $result['date_source'] = 'exif';
            }
        }

        // ── Fallback: fecha desde nombre de archivo ───────────────────────────
        $filename = !empty($originalFilename) ? $originalFilename : basename($filePath);
        if (!$result['has_date'] || preg_match('/(\d{4})(\d{2})(\d{2})[_-]?(\d{2})(\d{2})(\d{2})/', $filename)) {
            $matched = false;
            $year = $month = $day = $hour = $minute = $second = null;

            // Patrón 1: IMG_20250329_143025 o IMG-20250329-143025
            if (!$matched && preg_match('/(\d{4})(\d{2})(\d{2})[_-](\d{2})(\d{2})(\d{2})/', $filename, $m)) {
                $year = $m[1]; $month = $m[2]; $day = $m[3];
                $hour = $m[4]; $minute = $m[5]; $second = $m[6];
                $matched = true;
            }
            // Patrón 2: IMG20250329143025 (sin separadores)
            if (!$matched && preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $filename, $m)) {
                $year = $m[1]; $month = $m[2]; $day = $m[3];
                $hour = $m[4]; $minute = $m[5]; $second = $m[6];
                $matched = true;
            }
            // Patrón 3: 2025-03-29_14-30-25
            if (!$matched && preg_match('/(\d{4})[-_](\d{2})[-_](\d{2})[-_T](\d{2})[-_:](\d{2})[-_:](\d{2})/', $filename, $m)) {
                $year = $m[1]; $month = $m[2]; $day = $m[3];
                $hour = $m[4]; $minute = $m[5]; $second = $m[6];
                $matched = true;
            }

            if ($matched) {
                $monthInt = (int)$month;
                $dayInt   = (int)$day;
                $hourInt  = (int)$hour;
                $minInt   = (int)$minute;
                $secInt   = (int)$second;

                if ($monthInt >= 1 && $monthInt <= 12 &&
                    $dayInt   >= 1 && $dayInt   <= 31 &&
                    $hourInt  >= 0 && $hourInt  <= 23 &&
                    $minInt   >= 0 && $minInt   <= 59 &&
                    $secInt   >= 0 && $secInt   <= 59) {

                    $dts = "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}";
                    $dt  = DateTime::createFromFormat('Y-m-d H:i:s', $dts);
                    if ($dt && $dt->format('Y-m-d H:i:s') === $dts) {
                        // Solo sobrescribir si no había fecha EXIF
                        if (!$result['has_date']) {
                            $result['has_date']    = true;
                            $result['date']        = $dt->format('Y-m-d\TH:i');
                            $result['timestamp']   = $dt->getTimestamp();
                            $result['date_source'] = 'filename_pattern';
                        }
                    }
                }
            }
        }

        // ── GPS ───────────────────────────────────────────────────────────────
        if (!empty($exif['GPS'])) {
            $gps = $exif['GPS'];
            if (!empty($gps['GPSLatitude'])  && is_array($gps['GPSLatitude']) &&
                !empty($gps['GPSLongitude']) && is_array($gps['GPSLongitude']) &&
                !empty($gps['GPSLatitudeRef']) &&
                !empty($gps['GPSLongitudeRef'])) {

                $lat = self::gpsToDecimal($gps['GPSLatitude'],  $gps['GPSLatitudeRef']);
                $lng = self::gpsToDecimal($gps['GPSLongitude'], $gps['GPSLongitudeRef']);

                if ($lat !== null && $lng !== null && ($lat != 0 || $lng != 0)) {
                    $result['has_gps']   = true;
                    $result['latitude']  = $lat;
                    $result['longitude'] = $lng;
                }
            }
        }

        return $result;
    }

    /**
     * Interpola coordenadas GPS faltantes entre fotos con GPS según timestamp.
     * Modifica el array por referencia. Las fotos modificadas quedan con
     * 'gps_estimated' = true y 'gps_source' = 'interpolated'|'copied_from_neighbor'.
     *
     * Cada elemento de $images debe ser un array con las claves devueltas
     * por readFromFile (has_gps, has_date, timestamp, latitude, longitude, …).
     *
     * @param array &$images Array de metadatos de imágenes.
     */
    public static function interpolateMissingGps(array &$images): void
    {
        $needsEstimate = [];
        $withGps       = [];

        foreach ($images as $idx => &$img) {
            if ($img['has_gps'] && $img['timestamp'] !== null) {
                $withGps[$idx] = $img;
            } elseif ($img['has_date'] && $img['timestamp'] !== null) {
                $needsEstimate[$idx] = $img;
            }
        }
        unset($img);

        if (count($withGps) < 1 || count($needsEstimate) === 0) {
            return;
        }

        foreach ($needsEstimate as $idx => &$targetImg) {
            $targetTime = $targetImg['timestamp'];
            $before = null;
            $after  = null;

            foreach ($withGps as $gpsIdx => $gpsImg) {
                $timeDiff = $gpsImg['timestamp'] - $targetTime;
                if ($timeDiff > 0) {
                    if ($after === null || $gpsImg['timestamp'] < $images[$after]['timestamp']) {
                        $after = $gpsIdx;
                    }
                } elseif ($timeDiff < 0) {
                    if ($before === null || $gpsImg['timestamp'] > $images[$before]['timestamp']) {
                        $before = $gpsIdx;
                    }
                } else {
                    $before = $gpsIdx;
                    break;
                }
            }

            if ($before !== null && $after !== null) {
                $beforeTime  = $images[$before]['timestamp'];
                $afterTime   = $images[$after]['timestamp'];
                $timeBetween = $afterTime - $beforeTime;
                $progress    = $timeBetween > 0 ? ($targetTime - $beforeTime) / $timeBetween : 0;
                $progress    = max(0.0, min(1.0, $progress));

                $images[$idx]['latitude']    = round($images[$before]['latitude']  + ($images[$after]['latitude']  - $images[$before]['latitude'])  * $progress, 7);
                $images[$idx]['longitude']   = round($images[$before]['longitude'] + ($images[$after]['longitude'] - $images[$before]['longitude']) * $progress, 7);
                $images[$idx]['has_gps']     = true;
                $images[$idx]['gps_estimated'] = true;
                $images[$idx]['gps_source']  = 'interpolated';
            } elseif ($before !== null) {
                $images[$idx]['latitude']    = $images[$before]['latitude'];
                $images[$idx]['longitude']   = $images[$before]['longitude'];
                $images[$idx]['has_gps']     = true;
                $images[$idx]['gps_estimated'] = true;
                $images[$idx]['gps_source']  = 'copied_from_neighbor';
            } elseif ($after !== null) {
                $images[$idx]['latitude']    = $images[$after]['latitude'];
                $images[$idx]['longitude']   = $images[$after]['longitude'];
                $images[$idx]['has_gps']     = true;
                $images[$idx]['gps_estimated'] = true;
                $images[$idx]['gps_source']  = 'copied_from_neighbor';
            }
        }
        unset($targetImg);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers privados
    // ──────────────────────────────────────────────────────────────────────────

    private static function rationalToFloat(string $rational): float
    {
        if (strpos($rational, '/') !== false) {
            [$num, $den] = explode('/', $rational, 2);
            return (float)$den != 0 ? (float)$num / (float)$den : 0.0;
        }
        return (float)$rational;
    }

    private static function gpsToDecimal(array $coords, string $hemisphere): ?float
    {
        if (count($coords) < 3) return null;
        $degrees = self::rationalToFloat($coords[0]);
        $minutes = self::rationalToFloat($coords[1]);
        $seconds = self::rationalToFloat($coords[2]);
        $decimal = $degrees + ($minutes / 60.0) + ($seconds / 3600.0);
        if (in_array(strtoupper(trim($hemisphere)), ['S', 'W'], true)) {
            $decimal = -$decimal;
        }
        return round($decimal, 7);
    }
}
