<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AddressEnricher
{
    public int $minIntervalSeconds = 1;

    public int $ttlSeconds = 2592000;

    private ?float $inProcessLastCall = null;

    public function __construct(
        private readonly NominatimClient $client,
    ) {
        $this->minIntervalSeconds = max(0, (int) config('atlas.nominatim_min_interval', 1));
    }

    public function enabled(): bool
    {
        return (bool) config('atlas.enrich_addresses', true);
    }

    public function enrich(array $payload): array
    {
        if (trim((string) ($payload['address'] ?? '')) !== '') {
            return $payload;
        }

        if (! isset($payload['lat'], $payload['lng'])) {
            return $payload;
        }

        $lat = round((float) $payload['lat'], 5);
        $lng = round((float) $payload['lng'], 5);

        if ($lat === 0.0 && $lng === 0.0) {
            return $payload;
        }

        $cacheKey = 'atlas.nominatim.reverse.' . $lat . ',' . $lng;
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            $payload['address'] = $cached;

            return $payload;
        }

        $this->throttle();

        try {
            $result = $this->client->reverse($lat, $lng);
        } catch (Throwable $exception) {
            Log::warning('AddressEnricher: reverse geocode failed', [
                'lat' => $lat,
                'lng' => $lng,
                'message' => $exception->getMessage(),
            ]);

            return $payload;
        }

        if (is_array($result) && isset($result['display_name']) && is_string($result['display_name']) && $result['display_name'] !== '') {
            Cache::put($cacheKey, $result['display_name'], $this->ttlSeconds);
            $payload['address'] = $result['display_name'];
        }

        return $payload;
    }

    public function enrichBatch(array $payloads): array
    {
        return array_map(fn (array $payload) => $this->enrich($payload), $payloads);
    }

    private function throttle(): void
    {
        $stored = (float) Cache::get('atlas.nominatim.last_call', 0.0);
        $wait = ($stored + $this->minIntervalSeconds) - microtime(true);

        if ($this->inProcessLastCall !== null) {
            $wait = max($wait, ($this->inProcessLastCall + $this->minIntervalSeconds) - microtime(true));
        }

        if ($wait > 0) {
            usleep((int) ($wait * 1000000));
        }

        $now = microtime(true);
        Cache::put('atlas.nominatim.last_call', $now, 3600);
        $this->inProcessLastCall = $now;
    }
}