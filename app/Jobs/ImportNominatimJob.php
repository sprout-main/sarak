<?php

namespace App\Jobs;

use App\Enums\Source;
use App\Jobs\Concerns\ManagesImportRun;
use App\Models\ImportRun;
use App\Services\ImportService;
use App\Services\NominatimClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ImportNominatimJob implements ShouldQueue
{
    use ManagesImportRun, Queueable;

    public function __construct(
        public int $runId,
        public string $payload = '',
    ) {
    }

    public function handle(NominatimClient $client, ImportService $imports): void
    {
        $run = ImportRun::find($this->runId);
        if ($run === null) {
            Log::error('ImportNominatimJob: import run not found', ['run_id' => $this->runId]);

            return;
        }

        $this->ensureStarted($run);

        try {
            $terms = array_values(array_filter(array_map(
                'trim',
                preg_split('/\r\n|\r|\n/', $this->payload),
            ), fn ($term) => $term !== '' && !str_starts_with($term, '#')));

            $payloads = [];
            $misses = 0;

            foreach ($terms as $term) {
                $result = $client->geocode($term);
                if ($result === null) {
                    $misses++;

                    continue;
                }

                $payload = $this->payloadFromResult($term, $result);
                if ($payload !== null) {
                    $payloads[] = $payload;
                }
            }

            if ($misses > 0) {
                Log::warning('ImportNominatimJob: some terms did not geocode', [
                    'run_id' => $this->runId,
                    'misses' => $misses,
                ]);
            }

            $this->importInChunks($run, $imports, $payloads, Source::Nominatim->value);
        } catch (Throwable $exception) {
            $this->failRun($run, $exception);
        }
    }

    private function payloadFromResult(string $term, array $result): ?array
    {
        $displayName = is_string($result['display_name'] ?? null) ? $result['display_name'] : null;
        $name = is_string($result['name'] ?? null) && trim($result['name']) !== ''
            ? trim($result['name'])
            : ($displayName !== null ? trim(Str::before($displayName, ',')) : trim($term));
        if ($name === '') {
            $name = trim($term);
        }

        if (!isset($result['lat'], $result['lon'])) {
            return null;
        }

        $lat = (float) $result['lat'];
        $lng = (float) $result['lon'];

        $categorySlug = null;
        foreach (['class', 'type'] as $kind) {
            if (is_string($result[$kind] ?? null) && $result[$kind] !== '') {
                $categorySlug = ImportService::bestMatchCategorySlug($result[$kind]);
                if ($categorySlug !== null) {
                    break;
                }
            }
        }

        return [
            'name' => $name,
            'lat' => $lat,
            'lng' => $lng,
            'address' => $displayName,
            'category_slug' => $categorySlug,
            'source' => 'nominatim',
            'source_ref' => isset($result['osm_id']) ? 'nominatim:osm_id:' . $result['osm_id'] : null,
        ];
    }
}