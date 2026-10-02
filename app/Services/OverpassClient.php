<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OverpassClient
{
    public array $endpoints;

    public int $maxAttempts;

    public int $baseDelaySeconds;

    public function __construct()
    {
        $this->endpoints = array_values(array_filter(array_map(
            'strval',
            (array) config('atlas.overpass_endpoints', [
                'https://overpass-api.de/api/interpreter',
                'https://overpass.kumi.systems/api/interpreter',
                'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
            ])
        )));
        $this->maxAttempts = max(1, (int) config('atlas.overpass_max_attempts', 4));
        $this->baseDelaySeconds = max(0, (int) config('atlas.overpass_base_delay', 1));
    }

    public function queryWithRetry(string $overpassQuery, int $timeoutSec = 60): array
    {
        $last = null;
        $attempts = max(1, $this->maxAttempts);

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $endpoint = $this->endpoints[$attempt % max(1, count($this->endpoints))];

            try {
                return $this->query($overpassQuery, $timeoutSec, $endpoint);
            } catch (Throwable $exception) {
                $last = $exception;
                $this->backoff($attempt);
            }
        }

        throw $last ?? new RuntimeException('Overpass query failed after all retries.');
    }

    public function backoff(int $attempt): void
    {
        $seconds = min($this->baseDelaySeconds * (2 ** $attempt), 30.0);
        if ($seconds > 0) {
            sleep((int) $seconds);
        }
    }

    public function query(
        string $overpassQuery,
        int $timeoutSec = 60,
        string $endpoint = 'https://overpass-api.de/api/interpreter',
    ): array {
        $response = Http::withHeaders([
            'User-Agent' => 'Sarak/1.0 (community infrastructure map; contact: admin@bangued.ph)',
            'Accept' => 'application/json, text/plain, */*',
        ])
            ->timeout($timeoutSec)
            ->asForm()
            ->post($endpoint, ['data' => $overpassQuery]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Overpass endpoint %s returned HTTP %d: %s',
                $endpoint,
                $response->status(),
                mb_substr((string) $response->body(), 0, 200),
            ));
        }

        $json = $response->json();
        if (!is_array($json) || !isset($json['elements']) || !is_array($json['elements'])) {
            throw new RuntimeException('Overpass response contained no elements array.');
        }

        return $json['elements'];
    }

    public function banguedPOIsQuery(): string
    {
        return <<<'OVERPASS'
[out:json][timeout:60];
(
  node["amenity"~"^(cafe|hospital|pharmacy|fast_food|marketplace|clinic)$"](17.55,120.58,17.65,120.66);
  way["amenity"~"^(cafe|hospital|pharmacy|fast_food|marketplace|clinic)$"](17.55,120.58,17.65,120.66);
  node["shop"~"^(supermarket|convenience|grocery|coffee)$"](17.55,120.58,17.65,120.66);
  way["shop"~"^(supermarket|convenience|grocery|coffee)$"](17.55,120.58,17.65,120.66);
);
out center tags;
OVERPASS;
    }

    public function osmElementToPayload(array $el): ?array
    {
        $tags = is_array($el['tags'] ?? null) ? $el['tags'] : [];

        if (isset($el['lat'], $el['lon'])) {
            $lat = (float) $el['lat'];
            $lng = (float) $el['lon'];
        } elseif (isset($el['center']['lat'], $el['center']['lon'])) {
            $lat = (float) $el['center']['lat'];
            $lng = (float) $el['center']['lon'];
        } else {
            $lat = null;
            $lng = null;
        }

        $name = is_string($tags['name'] ?? null) ? trim($tags['name']) : '';
        if ($name === '' && is_string($tags['name:en'] ?? null)) {
            $name = trim($tags['name:en']);
        }

        if ($lat === null || $lng === null || $name === '') {
            return null;
        }

        $categoryRaw = $tags['amenity'] ?? $tags['shop'] ?? null;
        $categorySlug = is_string($categoryRaw) ? ImportService::bestMatchCategorySlug($categoryRaw) : null;
        if ($categorySlug === null) {
            return null;
        }

        $id = $tags['id'] ?? $el['id'] ?? '';

        return [
            'name' => $name,
            'lat' => $lat,
            'lng' => $lng,
            'category_slug' => $categorySlug,
            'address' => $this->buildAddress($tags),
            'phone' => $this->firstTagValue($tags, ['phone', 'contact:phone', 'phone:mobile', 'contact:mobile']),
            'website' => $this->firstTagValue($tags, ['website', 'contact:website']),
            'source' => 'overpass',
            'source_ref' => 'overpass:' . $id,
        ];
    }

    private function buildAddress(array $tags): ?string
    {
        if (is_string($tags['addr:full'] ?? null) && trim($tags['addr:full']) !== '') {
            return trim($tags['addr:full']);
        }

        $parts = array_values(array_filter([
            $tags['addr:housenumber'] ?? null,
            $tags['addr:street'] ?? null,
            $tags['addr:city'] ?? null,
            $tags['addr:province'] ?? null,
        ], fn ($value) => is_string($value) && trim($value) !== ''));

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    private function firstTagValue(array $tags, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (is_string($tags[$key] ?? null) && trim($tags[$key]) !== '') {
                return trim($tags[$key]);
            }
        }

        return null;
    }
}