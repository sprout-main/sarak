<?php

namespace Tests\Feature;

use App\Models\ImportRun;
use App\Models\ModQueue;
use App\Models\Place;
use App\Services\ImportService;
use App\Services\OverpassClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class ImportPipelineTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_import_service_queues_candidates_and_skips_duplicates(): void
    {
        Place::factory()->create(['name' => 'Dizon Pharmacy', 'lat' => 17.6000, 'lng' => 120.6100]);

        $run = ImportRun::create(['source' => 'overpass', 'status' => 'running', 'started_at' => now()]);

        $elements = [
            ['name' => 'Dizon Pharmacy', 'lat' => 17.6000, 'lng' => 120.6100, 'category_slug' => 'pharmacy', 'address' => 'North Shore Rd', 'source_ref' => 'overpass:111'],
            ['name' => 'Botika Ramos', 'lat' => 17.6050, 'lng' => 120.6150, 'category_slug' => 'pharmacy', 'address' => 'Mirabra St', 'source_ref' => 'overpass:222'],
            ['name' => 'Bangued Kapehan Central', 'lat' => 17.6100, 'lng' => 120.6200, 'category_slug' => 'coffee_shop', 'source_ref' => 'overpass:333'],
        ];

        $summary = app(ImportService::class)->runImport($run->id, $elements, 'overpass');

        $this->assertSame(['attempted' => 3, 'queued' => 2, 'skipped_duplicates' => 1, 'skipped_invalid' => 0], $summary);

        $run->refresh();
        $this->assertSame('done', $run->status);

        $this->assertDatabaseCount('mod_queue', 2);
        $this->assertDatabaseCount('places', 1);

        $queued = ModQueue::pluck('payload');

        $this->assertContains('Botika Ramos', $queued->pluck('name')->all());
        $this->assertContains('Bangued Kapehan Central', $queued->pluck('name')->all());
        $this->assertNotContains('Dizon Pharmacy', $queued->pluck('name')->all());

        foreach (ModQueue::all() as $queue) {
            $this->assertSame('create', $queue->action);
            $this->assertSame('pending', $queue->status);
            $this->assertSame('overpass', $queue->payload['source']);
            $this->assertNotNull($queue->payload['source_ref']);
            $this->assertNotNull($queue->payload['fetched_at']);
        }
    }

    public function test_overpass_candidate_mapping(): void
    {
        $client = app(OverpassClient::class);

        $node = $client->osmElementToPayload([
            'id' => 123,
            'type' => 'node',
            'lat' => 17.6005,
            'lon' => 120.6110,
            'tags' => ['name' => 'Luna Pharmacy', 'amenity' => 'pharmacy', 'phone' => '0917 888 0000'],
        ]);

        $this->assertNotNull($node);
        $this->assertSame('Luna Pharmacy', $node['name']);
        $this->assertSame('pharmacy', $node['category_slug']);
        $this->assertEquals(17.6005, $node['lat']);
        $this->assertEquals(120.6110, $node['lng']);
        $this->assertSame('overpass:123', $node['source_ref']);
        $this->assertSame('0917 888 0000', $node['phone']);

        $way = $client->osmElementToPayload([
            'id' => 456,
            'type' => 'way',
            'center' => ['lat' => 17.6050, 'lon' => 120.6150],
            'tags' => ['name' => 'Bangued CES', 'amenity' => 'cafe'],
        ]);

        $this->assertNotNull($way);
        $this->assertSame('Bangued CES', $way['name']);
        $this->assertSame('coffee_shop', $way['category_slug']);
        $this->assertEquals(17.6050, $way['lat']);
        $this->assertEquals(120.6150, $way['lng']);

        $nameless = $client->osmElementToPayload([
            'id' => 789,
            'type' => 'node',
            'lat' => 17.6000,
            'lon' => 120.6100,
            'tags' => ['amenity' => 'pharmacy'],
        ]);

        $this->assertNull($nameless);

        $uncategorized = $client->osmElementToPayload([
            'id' => 101,
            'type' => 'node',
            'lat' => 17.6000,
            'lon' => 120.6100,
            'tags' => ['name' => 'Cinema House', 'amenity' => 'cinema'],
        ]);

        $this->assertNull($uncategorized);
    }
}