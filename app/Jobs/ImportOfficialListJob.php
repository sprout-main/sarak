<?php

namespace App\Jobs;

use App\Enums\Source;
use App\Jobs\Concerns\ManagesImportRun;
use App\Models\ImportRun;
use App\Services\AddressEnricher;
use App\Services\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ImportOfficialListJob implements ShouldQueue
{
    use ManagesImportRun, Queueable;

    public function __construct(
        public int $runId,
        public string $payload = '',
    ) {
    }

    public function handle(ImportService $imports, AddressEnricher $enricher): void
    {
        $run = ImportRun::find($this->runId);
        if ($run === null) {
            Log::error('ImportOfficialListJob: import run not found', ['run_id' => $this->runId]);

            return;
        }

        $this->ensureStarted($run);

        try {
            $raw = trim($this->payload);
            if ($raw === '') {
                throw new RuntimeException('Official list import received an empty payload.');
            }

            if (preg_match('#^https?://#i', $raw) === 1) {
                $raw = $this->fetchWithRetry($raw);
            }

            $payloads = $this->parse($raw);
            if ($enricher->enabled() && $payloads !== []) {
                $payloads = $enricher->enrichBatch($payloads);
            }

            $this->importInChunks($run, $imports, $payloads, Source::OfficialList->value);
        } catch (Throwable $exception) {
            $this->failRun($run, $exception);
        }
    }

    private function fetchWithRetry(string $url): string
    {
        $attempts = max(1, (int) config('atlas.overpass_max_attempts', 4));
        $baseDelay = max(0, (int) config('atlas.overpass_base_delay', 1));
        $last = null;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            try {
                $response = Http::timeout(60)->get($url);
                if ($response->failed()) {
                    throw new RuntimeException('Official list URL returned HTTP ' . $response->status());
                }

                return (string) $response->body();
            } catch (Throwable $exception) {
                $last = $exception;
                $seconds = min($baseDelay * (2 ** $attempt), 30.0);
                if ($attempt < $attempts - 1 && $seconds > 0) {
                    sleep((int) $seconds);
                }
            }
        }

        throw $last ?? new RuntimeException('Official list URL fetch failed after ' . $attempts . ' attempts.');
    }

    private function parse(string $raw): array
    {
        $trimmed = ltrim($raw);
        if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
            return $this->parseGeoJson($trimmed);
        }

        return $this->parseCsv($trimmed);
    }

    private function parseGeoJson(string $raw): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Official list GeoJSON could not be parsed.');
        }

        $features = [];
        if (($data['type'] ?? null) === 'FeatureCollection') {
            $features = is_array($data['features'] ?? null) ? $data['features'] : [];
        } elseif (($data['type'] ?? null) === 'Feature') {
            $features = [$data];
        } else {
            throw new RuntimeException('Official list GeoJSON must be a FeatureCollection or a Feature.');
        }

        $payloads = [];
        foreach ($features as $feature) {
            if (!is_array($feature)) {
                continue;
            }

            $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $coords = $feature['geometry']['coordinates'] ?? null;
            $name = is_string($props['name'] ?? null) ? trim($props['name']) : '';

            $lat = is_numeric($props['lat'] ?? null)
                ? (float) $props['lat']
                : (isset($coords[1]) && is_numeric($coords[1]) ? (float) $coords[1] : null);
            $lng = is_numeric($props['lng'] ?? null)
                ? (float) $props['lng']
                : (isset($coords[0]) && is_numeric($coords[0]) ? (float) $coords[0] : null);

            $category = is_string($props['category'] ?? null)
                ? $props['category']
                : (is_string($props['category_slug'] ?? null) ? $props['category_slug'] : null);

            if ($name === '' || $lat === null || $lng === null) {
                continue;
            }

            $payloads[] = [
                'name' => $name,
                'lat' => $lat,
                'lng' => $lng,
                'category_slug' => $category !== null ? ImportService::bestMatchCategorySlug($category) : null,
                'address' => is_string($props['address'] ?? null) ? $props['address'] : null,
                'phone' => is_string($props['phone'] ?? null) ? $props['phone'] : null,
                'website' => is_string($props['website'] ?? null) ? $props['website'] : null,
            ];
        }

        return $payloads;
    }

    private function parseCsv(string $csv): array
    {
        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', $csv),
            fn ($line) => is_string($line) && trim($line) !== '',
        ));

        if ($lines === []) {
            return [];
        }

        $delimiter = $this->delimiterOf((string) $lines[0]);
        $columnMap = $this->columnMap(str_getcsv((string) $lines[0], $delimiter));
        $hasHeader = count($columnMap) >= 2;

        if (!$hasHeader) {
            $columnMap = [
                'name' => 0,
                'address' => 1,
                'lat' => 2,
                'lng' => 3,
                'category' => 4,
                'phone' => 5,
                'website' => 6,
            ];
        }

        $payloads = [];
        $start = $hasHeader ? 1 : 0;

        for ($i = $start, $iMax = count($lines); $i < $iMax; $i++) {
            $cells = str_getcsv((string) $lines[$i], $delimiter);
            $cell = function (string $key) use ($cells, $columnMap): string {
                $index = $columnMap[$key] ?? null;

                return $index !== null && isset($cells[$index]) ? trim($cells[$index]) : '';
            };

            $name = $cell('name');
            $latRaw = $cell('lat');
            $lngRaw = $cell('lng');
            $category = $cell('category');

            if ($name === '' || $latRaw === '' || $lngRaw === '' || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
                continue;
            }

            $lat = (float) $latRaw;
            $lng = (float) $lngRaw;
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }

            $address = $cell('address');
            $phone = $cell('phone');
            $website = $cell('website');

            $payloads[] = [
                'name' => $name,
                'lat' => $lat,
                'lng' => $lng,
                'category_slug' => $category !== '' ? ImportService::bestMatchCategorySlug($category) : null,
                'address' => $address !== '' ? $address : null,
                'phone' => $phone !== '' ? $phone : null,
                'website' => $website !== '' ? $website : null,
            ];
        }

        return $payloads;
    }

    private function delimiterOf(string $line): string
    {
        $candidates = [',', ';', "\t"];
        $delimiter = ',';
        $maxCount = -1;

        foreach ($candidates as $candidate) {
            $count = substr_count($line, $candidate);
            if ($count > $maxCount) {
                $maxCount = $count;
                $delimiter = $candidate;
            }
        }

        return $delimiter;
    }

    private function columnMap(array $headerCells): array
    {
        $lookup = [
            'name' => ['name', 'business', 'establishment', 'place'],
            'address' => ['address', 'addr'],
            'lat' => ['lat', 'latitude'],
            'lng' => ['lng', 'lon', 'long', 'longitude'],
            'category' => ['category', 'type', 'kind'],
            'phone' => ['phone', 'telephone', 'tel', 'contact'],
            'website' => ['website', 'web', 'url'],
        ];

        $map = [];
        foreach ($headerCells as $index => $cell) {
            $normalized = preg_replace('/[^a-z]/', '', strtolower(trim((string) $cell)));
            foreach ($lookup as $column => $aliases) {
                foreach ($aliases as $alias) {
                    if ($normalized === $alias) {
                        $map[$column] = $index;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }
}