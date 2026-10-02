<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class SpatialBoundingPerformanceTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_scope_in_bounds_filters_correctly(): void
    {
        $cat = $this->category('grocery');

        $inside = Place::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Bangued Town Center Store',
            'lat' => 17.5960,
            'lng' => 120.6180,
        ]);

        $outside = Place::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Far Perimeter Mountain Hub',
            'lat' => 17.5000,
            'lng' => 120.5000,
        ]);

        $results = Place::approved()
            ->inBounds(17.5800, 120.6000, 17.6200, 120.6300)
            ->get();

        $ids = $results->pluck('id')->all();
        $this->assertContains($inside->id, $ids);
        $this->assertNotContains($outside->id, $ids);
    }

    public function test_api_places_supports_in_bounds_parameter(): void
    {
        $cat = $this->category('coffee_shop');

        $inside = Place::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Plaza Cafe',
            'lat' => 17.5980,
            'lng' => 120.6190,
        ]);

        $outside = Place::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Out of Town Rest Stop',
            'lat' => 17.6900,
            'lng' => 120.6900,
        ]);

        $response = $this->getJson('/api/places?in_bounds=17.5800,120.6000,17.6200,120.6300')
            ->assertOk();

        $data = $response->json('data');
        $ids = collect($data)->pluck('id')->all();

        $this->assertContains($inside->id, $ids);
        $this->assertNotContains($outside->id, $ids);
    }

    public function test_etag_header_and_304_not_modified_support(): void
    {
        $cat = $this->category('pharmacy');
        Place::factory()->create([
            'category_id' => $cat->id,
            'lat' => 17.5970,
            'lng' => 120.6170,
        ]);

        $response1 = $this->getJson('/api/places?in_bounds=17.5800,120.6000,17.6200,120.6300')
            ->assertOk();

        $etag = $response1->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $response2 = $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/places?in_bounds=17.5800,120.6000,17.6200,120.6300');

        $this->assertSame(304, $response2->getStatusCode());
    }

    public function test_tile_proxy_caches_tiles_to_disk(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://a.basemaps.cartocdn.com/*' => \Illuminate\Support\Facades\Http::response('fake-png-binary-data', 200, ['Content-Type' => 'image/png']),
        ]);

        // Non-existent style or invalid bounds returns 404
        $this->get('/tiles/invalid_style/14/13680/7452.png')->assertNotFound();

        // Valid tile coordinates
        $response = $this->get('/tiles/dark/14/13680/7452.png');
        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));

        // Assert cached file exists on disk
        $cachePath = storage_path('app/tiles/dark/14/13680/7452.png');
        $this->assertFileExists($cachePath);

        // Second request served directly from disk
        $response2 = $this->get('/tiles/dark/14/13680/7452.png');
        $response2->assertOk();

        // Clean up test file
        if (file_exists($cachePath)) {
            @unlink($cachePath);
        }
    }
}
