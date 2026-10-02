<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NominatimClient
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org';

    private const USER_AGENT = 'sarak/1.0';

    public function geocode(string $query, array $opts = []): ?array
    {
        $params = array_merge([
            'format' => 'jsonv2',
            'q' => $query,
            'limit' => 1,
            'addressdetails' => 1,
        ], $opts);

        $response = $this->client()->get(self::BASE_URL . '/search', $params);

        if ($response->failed()) {
            throw new RuntimeException('Nominatim search failed with HTTP ' . $response->status());
        }

        $results = $response->json();
        if (!is_array($results) || $results === []) {
            return null;
        }

        return $results[0];
    }

    public function reverse(float $lat, float $lng): ?array
    {
        $response = $this->client()->get(self::BASE_URL . '/reverse', [
            'format' => 'jsonv2',
            'lat' => $lat,
            'lon' => $lng,
            'addressdetails' => 1,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Nominatim reverse failed with HTTP ' . $response->status());
        }

        $data = $response->json();
        if (!is_array($data) || isset($data['error'])) {
            return null;
        }

        return $data;
    }

    public function snapAddress(array $placeFields): array
    {
        $address = isset($placeFields['address']) ? trim((string) $placeFields['address']) : '';
        if ($address !== '') {
            return $placeFields;
        }

        $name = isset($placeFields['name']) ? trim((string) $placeFields['name']) : '';
        $town = isset($placeFields['town']) && trim((string) $placeFields['town']) !== ''
            ? trim((string) $placeFields['town'])
            : 'Bangued, Abra';
        $query = trim($name !== '' ? $name . ', ' . $town : $town, " \t\n\r\0\x0B,");

        if ($query === '') {
            return $placeFields;
        }

        $result = $this->geocode($query);
        if ($result === null) {
            return $placeFields;
        }

        $placeFields['address'] = $result['display_name']
            ?? $result['address']['town']
            ?? $result['address']['city']
            ?? $result['address']['state']
            ?? null;

        return $placeFields;
    }

    private function client(): PendingRequest
    {
        return Http::timeout(30)->withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'application/json',
        ]);
    }
}