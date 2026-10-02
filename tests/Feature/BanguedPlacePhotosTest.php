<?php

namespace Tests\Feature;

use App\Models\Place;
use Database\Seeders\BanguedPhotosSeeder;
use Database\Seeders\BoundarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class BanguedPlacePhotosTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Without this the seeder copies every source image into the real
        // storage/app/public/photos tree. RefreshDatabase rolls the rows back
        // but not the files, which is how the suite accumulated 577 MB of
        // orphans after a single run.
        Storage::fake('public');

        $this->seed(BoundarySeeder::class);
        $this->seed(BanguedPhotosSeeder::class);
    }

    public function test_api_places_index_returns_cover_photo_and_photo_count(): void
    {
        $response = $this->getJson('/api/places');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data);

        // Find a place that has photos, e.g. Chowking Bangued or Jollibee
        $placesWithPhotos = collect($data)->filter(fn ($p) => ($p['photo_count'] ?? 0) > 0);
        $this->assertGreaterThanOrEqual(1, $placesWithPhotos->count());

        $firstWithPhoto = $placesWithPhotos->first();
        $this->assertNotEmpty($firstWithPhoto['cover_photo']);
        $this->assertStringStartsWith('photos/', $firstWithPhoto['cover_photo']);
    }

    public function test_api_place_detail_returns_photos_array(): void
    {
        $place = Place::has('photos')->first();
        $this->assertNotNull($place);

        $response = $this->getJson("/api/places/{$place->id}");
        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayHasKey('photos', $data);
        $this->assertNotEmpty($data['photos']);

        $firstPhoto = $data['photos'][0];
        $this->assertArrayHasKey('id', $firstPhoto);
        $this->assertArrayHasKey('path', $firstPhoto);
        $this->assertArrayHasKey('is_cover', $firstPhoto);
        $this->assertSame('approved', $firstPhoto['status']);
    }

    public function test_newly_inserted_bangued_places_exist_with_proper_categories(): void
    {
        $expectedNames = [
            'Cafe Merci' => 'coffee_shop',
            'Daily Dose Coffee Shop' => 'coffee_shop',
            'Depresso Cafe' => 'coffee_shop',
            'Igop Cafe' => 'coffee_shop',
            'Kapepiliin' => 'coffee_shop',
            'Ladrillo Cafe' => 'coffee_shop',
            'Jollibee - Zone 7 Branch' => 'fast_food',
            "Max's Restaurant Bangued" => 'fast_food',
            "Shakey's Pizza Parlor Bangued" => 'fast_food',
            'Aquino General Merchandise' => 'grocery',
            'Miguel Chike Gen. Mdse.' => 'grocery',
            'NCT Department Store & Supermarket' => 'grocery',
            "HMC - Medical Specialist's Clinic" => 'hospital',
            'St. James Hospital / Valera Medical Hospital' => 'hospital',
            'St. Joseph Drug - McKinley Branch' => 'pharmacy',
            'St. Joseph Drug - Zone 5 Branch' => 'pharmacy',
        ];

        foreach ($expectedNames as $name => $categorySlug) {
            $place = Place::where('name', $name)->first();
            $this->assertNotNull($place, "Place {$name} should exist in database");
            $this->assertSame($categorySlug, $place->category->slug);
            $this->assertSame('approved', $place->status);
            $this->assertGreaterThan(0, $place->photos()->count(), "Place {$name} should have photos attached");
        }
    }
}
