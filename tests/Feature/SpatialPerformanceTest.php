<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class SpatialPerformanceTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_bbox_query_on_5k_places_stays_interactive(): void
    {
        $categoryId = Category::where('slug', 'grocery')->value('id');

        foreach ([1000, 1000, 1000, 1000, 1000] as $batch) {
            Place::factory()
                ->count($batch)
                ->state(fn () => [
                    'category_id' => $categoryId,
                    'lat' => fake()->randomFloat(6, 17.55, 17.70),
                    'lng' => fake()->randomFloat(6, 120.55, 120.70),
                ])
                ->create();
        }

        $this->assertSame(5000, Place::count());

        $start = microtime(true);

        $rows = Place::approved()
            ->withinBbox(17.50, 120.50, 17.75, 120.75)
            ->get();

        $elapsedMs = (microtime(true) - $start) * 1000;

        fwrite(STDERR, sprintf("\nSpatial bbox over 5000 places: %.1f ms for %d rows\n", $elapsedMs, $rows->count()));

        $this->assertLessThan(3000, $elapsedMs);
        $this->assertSame(5000, $rows->count());
    }
}