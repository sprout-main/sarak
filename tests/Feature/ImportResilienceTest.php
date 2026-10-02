<?php

namespace Tests\Feature;

use App\Models\ImportRun;
use App\Services\OverpassClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class ImportResilienceTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_overpass_client_retries_and_rotates_mirror_on_failure(): void
    {
        $tries = 0;
        Http::fake(function () use (&$tries) {
            $tries++;

            return $tries === 1
                ? Http::response('service unavailable', 502)
                : Http::response(['elements' => [['id' => 1]]], 200);
        });

        $client = app(OverpassClient::class);
        $client->maxAttempts = 3;
        $client->baseDelaySeconds = 0;

        $elements = $client->queryWithRetry('auto');

        $this->assertSame([['id' => 1]], $elements);
        $this->assertSame(2, $tries);
    }

    public function test_overpass_client_gives_up_after_max_attempts(): void
    {
        Http::fake([
            '*' => Http::response('boom', 503),
        ]);

        $this->expectExceptionMessageMatches('/HTTP 503/');

        $client = app(OverpassClient::class);
        $client->maxAttempts = 2;
        $client->baseDelaySeconds = 0;

        $client->queryWithRetry('auto');
    }

    public function test_overpass_job_chunks_elements_and_reports_progress(): void
    {
        Http::fake([
            '*' => Http::response([
                'elements' => [
                    $this->osmNode(1, 'Rizal Pharmacy', 'pharmacy', ['addr:street' => 'Rizal St']),
                    $this->osmNode(2, 'Mira Grocery', 'grocery', ['addr:street' => 'Mirabra St']),
                    $this->osmNode(3, 'Quirino Fast Food', 'fast_food', ['addr:street' => 'Quirino Ave']),
                ],
            ], 200),
        ]);

        config(['atlas.import_chunk_size' => 1]);

        $run = ImportRun::create(['source' => 'overpass', 'status' => 'running', 'started_at' => now()]);

        dispatch(new \App\Jobs\ImportOverpassJob($run->id, 'auto'));

        $run->refresh();

        $this->assertSame('done', $run->status);
        $this->assertSame(3, $run->batch_summary['attempted']);
        $this->assertSame(3, $run->batch_summary['queued']);
        $this->assertSame(0, $run->batch_summary['skipped_invalid']);
        $this->assertSame(3, $run->batch_summary['chunks_processed']);
        $this->assertSame(3, $run->batch_summary['chunks_total']);

        $this->assertDatabaseCount('mod_queue', 3);
    }

    private function osmNode(int $id, string $name, string $amenity, array $extra = []): array
    {
        $tags = ['name' => $name, 'amenity' => $amenity];
        foreach ($extra as $key => $value) {
            $tags[$key] = $value;
        }

        return [
            'id' => $id,
            'type' => 'node',
            'lat' => 17.590 + ($id * 0.003),
            'lon' => 120.605 + ($id * 0.004),
            'tags' => $tags,
        ];
    }
}