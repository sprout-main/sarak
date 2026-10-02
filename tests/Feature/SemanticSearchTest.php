<?php

namespace Tests\Feature;

use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class SemanticSearchTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_semantic_search_matches_ilocano_medicine_intent(): void
    {
        $pharmacyCat = $this->category('pharmacy');
        $coffeeCat = $this->category('coffee_shop');

        $pharmacy = Place::factory()->create([
            'category_id' => $pharmacyCat->id,
            'name' => 'Farmacia Bangued',
            'description' => 'Prescription and over the counter medicines available.',
        ]);

        $cafe = Place::factory()->create([
            'category_id' => $coffeeCat->id,
            'name' => 'Kape Ti Abra',
            'description' => 'Artisan brewed coffee and pastries.',
        ]);

        $response = $this->getJson('/api/search/semantic?q=agas')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'category',
                        'barangay',
                        'address',
                        'lat',
                        'lng',
                        'distance_m',
                        'telemetry',
                        'score',
                        'match_reasons',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame($pharmacy->id, $data[0]['id']);
        $this->assertContains('Matches category intent for Pharmacies', $data[0]['match_reasons']);
    }

    public function test_semantic_search_matches_power_and_coffee_intent(): void
    {
        $coffeeCat = $this->category('coffee_shop');

        $cafeWithGen = Place::factory()->create([
            'category_id' => $coffeeCat->id,
            'name' => 'North Star Study Lounge',
            'description' => 'Quiet workspace equipped with standby generator and fiber internet.',
        ]);

        $response = $this->getJson('/api/search/semantic?q=kapehan with generator')
            ->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame($cafeWithGen->id, $data[0]['id']);

        $reasons = $data[0]['match_reasons'];
        $this->assertTrue(
            collect($reasons)->contains(fn ($r) => str_contains($r, 'generator'))
        );
    }

    public function test_semantic_search_matches_dining_food_intent(): void
    {
        $foodCat = $this->category('fast_food');

        $burgerJoint = Place::factory()->create([
            'category_id' => $foodCat->id,
            'name' => 'Bangued Bites',
            'description' => 'Juicy burgers and fries for quick takeout.',
        ]);

        $response = $this->getJson('/api/search/semantic?q=mangan burger')
            ->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame($burgerJoint->id, $data[0]['id']);
    }

    public function test_semantic_search_validates_minimum_query_length(): void
    {
        $this->getJson('/api/search/semantic?q=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    public function test_semantic_search_supports_distance_sorting(): void
    {
        $cat = $this->category('grocery');

        $nearPlace = Place::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Near Market Mart',
            'lat' => 17.5960,
            'lng' => 120.6180,
        ]);

        $farPlace = Place::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Far Market Branch',
            'lat' => 17.6500,
            'lng' => 120.6800,
        ]);

        $response = $this->getJson('/api/search/semantic?q=tindahan&lat=17.5960&lng=120.6180')
            ->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame($nearPlace->id, $data[0]['id']);
        $this->assertNotNull($data[0]['distance_m']);
    }
}
