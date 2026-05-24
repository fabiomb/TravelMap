<?php
/**
 * Helper: Geocoder
 *
 * Geocodificación inversa (coordenadas → nombre de ciudad/lugar) usando
 * Nominatim de OpenStreetMap, con cache en la tabla geocode_cache de la DB
 * y rate-limit de 1 request/segundo compartido con api/reverse_geocode.php.
 *
 * Extraído de api/reverse_geocode.php para reutilización en MCP y tests.
 */

final class Geocoder
{
    /**
     * Convierte coordenadas a nombre de ciudad/lugar.
     *
     * Consulta primero la cache en DB. Si no hay hit, llama a Nominatim
     * (respetando el rate-limit de 1 req/s) y guarda el resultado en cache.
     *
     * @param float $lat Latitud (−90 a 90).
     * @param float $lng Longitud (−180 a 180).
     * @return array|null {city, display_name, country, source: 'cache'|'nominatim'}
     *                    o null si no se puede determinar la ubicación.
     */
    public static function reverseLookup(float $lat, float $lng): ?array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        $db = self::getDb();
        if ($db === null) {
            return null;
        }

        // ── Cache ─────────────────────────────────────────────────────────────
        try {
            $stmt = $db->prepare('
                SELECT city, display_name, country FROM geocode_cache
                WHERE ABS(latitude - ?) < 0.000001 AND ABS(longitude - ?) < 0.000001
                LIMIT 1
            ');
            $stmt->execute([$lat, $lng]);
            $cached = $stmt->fetch();
            if ($cached) {
                return [
                    'city'         => $cached['city'],
                    'display_name' => $cached['display_name'] ?? $cached['city'],
                    'country'      => $cached['country'],
                    'source'       => 'cache',
                ];
            }
        } catch (PDOException $e) {
            error_log('[Geocoder] Cache lookup failed: ' . $e->getMessage());
        }

        // ── Rate-limit: máximo 1 request/segundo (compartido con reverse_geocode.php) ─
        $rateFile = sys_get_temp_dir() . '/nominatim_last_request.txt';
        $lastReq  = @file_get_contents($rateFile);
        if ($lastReq) {
            $elapsed = microtime(true) - (float)$lastReq;
            if ($elapsed < 1.0) {
                usleep((int)((1.0 - $elapsed) * 1_000_000));
            }
        }
        file_put_contents($rateFile, microtime(true));

        // ── Nominatim ─────────────────────────────────────────────────────────
        $url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
            'lat'            => $lat,
            'lon'            => $lng,
            'format'         => 'json',
            'addressdetails' => 1,
            'zoom'           => 10,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,            $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,        15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT,      'TravelMap/1.0 (PHP MCP) Contact: admin@travelmap.local');
        curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Accept: application/json', 'Accept-Language: es,en']);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("[Geocoder] Nominatim request failed (HTTP $httpCode) for lat=$lat, lng=$lng");
            return null;
        }

        $data = json_decode($response, true);
        if ($data === null || isset($data['error'])) {
            return null;
        }

        $address = $data['address'] ?? [];
        $city = $address['city']
            ?? $address['town']
            ?? $address['village']
            ?? $address['municipality']
            ?? $address['county']
            ?? $address['state']
            ?? null;

        if (!$city) {
            return null;
        }

        // ── Guardar en cache ──────────────────────────────────────────────────
        try {
            $ins = $db->prepare('
                INSERT INTO geocode_cache (latitude, longitude, city, display_name, country)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    city = VALUES(city),
                    display_name = VALUES(display_name),
                    country = VALUES(country),
                    created_at = CURRENT_TIMESTAMP
            ');
            $ins->execute([
                $lat,
                $lng,
                $city,
                $data['display_name'] ?? $city,
                $address['country'] ?? null,
            ]);
        } catch (PDOException $e) {
            error_log('[Geocoder] Cache insert failed: ' . $e->getMessage());
        }

        return [
            'city'         => $city,
            'display_name' => $data['display_name'] ?? $city,
            'country'      => $address['country'] ?? null,
            'source'       => 'nominatim',
        ];
    }

    /**
     * Geocodificación directa (texto → coordenadas) usando Nominatim.
     *
     * @param string $query Nombre del lugar a buscar.
     * @param int    $limit Máximo de resultados (1–10).
     * @return array Lista de candidatos: [{lat, lng, display_name, type, importance}]
     *               Vacío si no hay resultados o falla la llamada.
     */
    public static function forwardLookup(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }
        $limit = max(1, min(10, $limit));

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q'              => $query,
            'format'         => 'json',
            'limit'          => $limit,
            'addressdetails' => 1,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,            $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,        10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT,      'TravelMap/1.0 (PHP MCP) Contact: admin@travelmap.local');
        curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Accept: application/json', 'Accept-Language: es,en']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("[Geocoder] forwardLookup failed (HTTP $httpCode) for query='$query'");
            return [];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return [];
        }

        $results = [];
        foreach ($data as $item) {
            $results[] = [
                'lat'          => (float)$item['lat'],
                'lng'          => (float)$item['lon'],
                'display_name' => $item['display_name'] ?? '',
                'type'         => $item['type'] ?? $item['class'] ?? '',
                'importance'   => round((float)($item['importance'] ?? 0), 4),
            ];
        }
        return $results;
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function getDb(): ?PDO
    {
        try {
            return getDB();
        } catch (Exception $e) {
            error_log('[Geocoder] DB connection failed: ' . $e->getMessage());
            return null;
        }
    }
}
