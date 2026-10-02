<?php

namespace App\Jobs;

use App\Enums\Source;
use App\Jobs\Concerns\ManagesImportRun;
use App\Models\ImportRun;
use App\Services\AddressEnricher;
use App\Services\ImportService;
use App\Services\OverpassClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportOverpassJob implements ShouldQueue
{
    use ManagesImportRun, Queueable;

    public function __construct(
        public int $runId,
        public string $payload = '',
    ) {
    }

    public function handle(OverpassClient $client, ImportService $imports, AddressEnricher $enricher): void
    {
        $run = ImportRun::find($this->runId);
        if ($run === null) {
            Log::error('ImportOverpassJob: import run not found', ['run_id' => $this->runId]);

            return;
        }

        $this->ensureStarted($run);

        try {
            $query = trim($this->payload);
            if ($query === '' || strtolower($query) === 'auto') {
                $query = $client->banguedPOIsQuery();
            }

            $elements = $client->queryWithRetry($query);
            $payloads = array_values(array_filter(
                array_map(fn (array $el) => $client->osmElementToPayload($el), $elements),
                fn ($payload) => $payload !== null,
            ));

            if ($enricher->enabled()) {
                $payloads = $enricher->enrichBatch($payloads);
            }

            $this->importInChunks($run, $imports, $payloads, Source::Overpass->value);
        } catch (Throwable $exception) {
            $this->failRun($run, $exception);
        }
    }
}