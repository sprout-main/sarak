<?php

namespace Tests\Feature;

use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_geojson_export_contains_only_approved(): void
    {
        $approved = Place::factory()->create([
            'name' => 'Sanchez Grocery',
            'category_id' => $this->category('grocery')->id,
            'lat' => 17.5905,
            'lng' => 120.6080,
        ]);

        Place::factory()->unapproved()->create(['name' => 'Hidden Pending Place']);

        $response = $this->get('/api/export.geojson');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/geo+json');

        $collection = $response->json();

        $this->assertSame('FeatureCollection', $collection['type']);
        $this->assertCount(1, $collection['features']);
        $this->assertSame('urn:ogc:def:crs:OGC:1.3:CRS84', $collection['crs']['properties']['name']);

        $feature = $collection['features'][0];

        $this->assertSame('Point', $feature['geometry']['type']);
        $this->assertSame([120.608, 17.5905], $feature['geometry']['coordinates']);
        $this->assertSame('Sanchez Grocery', $feature['properties']['name']);
        $this->assertSame('grocery', $feature['properties']['category']);
        $this->assertSame($approved->slug, $feature['properties']['slug']);
        $this->assertSame($approved->verified_tier, $feature['properties']['verified_tier']);

        $this->assertStringContainsString('sarak-' . date('Y-m-d') . '.geojson', $response->headers->get('Content-Disposition'));
    }

    public function test_csv_export_rows_match_approved(): void
    {
        $approvedCount = 1;

        Place::factory()->create(['name' => 'Rizal Supermart']);
        Place::factory()->unapproved()->create(['name' => 'Hidden CSV Place']);

        $response = $this->get('/api/export.csv');

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));

        $rows = array_values(array_filter(
            explode("\n", trim($response->streamedContent())),
            fn (string $line) => $line !== ''
        ));

        $parsed = array_map($this->parseCsvLine(...), $rows);

        $this->assertSame('name', $parsed[0][0]);
        $this->assertContains('name', $parsed[0]);
        $this->assertCount($approvedCount + 1, $rows);
        $this->assertSame('Rizal Supermart', $parsed[1][0]);

        $this->assertStringContainsString('sarak-' . date('Y-m-d') . '.csv', $response->headers->get('Content-Disposition'));
    }

    private function parseCsvLine(string $line): array
    {
        return str_getcsv($line);
    }
}