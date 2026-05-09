<?php
/**
 * MCP Tools: Location
 * search_location
 */

final class LocationTools
{
    public static function register(Dispatcher $d): void
    {
        $d->register('search_location',
            'Searches coordinates for a place by name using OpenStreetMap Nominatim. ' .
            'Returns up to 5 candidates ordered by relevance, each with lat/lng and full name. ' .
            'If the results array is empty (very specific place, name in another language, etc.) ' .
            'use your WebSearch tool to find the coordinates on the internet and then call ' .
            'this tool with a more precise name, or pass the coordinates directly to create_poi.',
        [
            'type'       => 'object',
            'required'   => ['query'],
            'properties' => [
                'query' => [
                    'type'        => 'string',
                    'minLength'   => 2,
                    'maxLength'   => 200,
                    'description' => 'Name of the place, address or point of interest to search for.',
                ],
                'limit' => [
                    'type'    => 'integer',
                    'minimum' => 1,
                    'maximum' => 10,
                    'description' => 'Maximum number of results. Default 5.',
                ],
            ],
            'additionalProperties' => false,
        ], [self::class, 'searchLocation']);
    }

    public static function searchLocation(array $p): array
    {
        $query = trim($p['query'] ?? '');
        if (strlen($query) < 2) {
            throw new ToolException('Search query must be at least 2 characters', 'INVALID_INPUT', -32602);
        }

        $limit   = min((int)($p['limit'] ?? 5), 10);
        $results = Geocoder::forwardLookup($query, $limit);

        $response = [
            'query'   => $query,
            'results' => $results,
            'count'   => count($results),
            'source'  => 'nominatim',
        ];

        if (empty($results)) {
            $response['hint'] = 'No results found in Nominatim. Try a more specific name or use WebSearch to get the coordinates.';
        }

        return $response;
    }
}
