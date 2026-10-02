<?php

namespace Tests\Feature;

use App\Models\BoundaryArea;
use App\Models\Category;
use App\Models\Place;
use App\Models\PlaceHour;
use App\Models\PlacePhoto;
use Tests\Concerns\BoundaryFixtures;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApiPlaceReadTest extends TestCase
{
    use BoundaryFixtures, CoreCatalog, RefreshDatabase;

    public function test_public_categories_include_approved_counts(): void
    {
        $category = $this->category('coffee_shop');

        Place::factory()->count(3)->create(['category_id' => $category->id]);
        Place::factory()->unapproved()->create(['category_id' => $category->id]);

        $response = $this->getJson('/api/categories');

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('slug', $category->slug);

        $this->assertSame(3, $row['place_count']);
    }

    public function test_public_boundaries_return_geojson_features(): void
    {
        $area = $this->polygon('Palao', [
            [17.589, 120.614],
            [17.589, 120.624],
            [17.594, 120.624],
            [17.594, 120.614],
        ], [
            'psgc' => '1400101001',
            'boundary_source' => 'test',
        ]);

        BoundaryArea::create([
            'name' => 'No Geometry',
            'type' => 'barangay',
        ]);

        $response = $this->getJson('/api/boundaries')->assertOk();
        $features = $response->json('data.features');

        $this->assertSame('FeatureCollection', $response->json('data.type'));
        $this->assertCount(1, $features);
        $this->assertSame('Polygon', $features[0]['geometry']['type']);
        $this->assertSame($area->id, $features[0]['properties']['id']);
        $this->assertSame('Palao', $features[0]['properties']['name']);
    }

    public function test_places_index_defaults_to_approved_only(): void
    {
        $approved = Place::factory()->create();
        $pending = Place::factory()->unapproved()->create();
        $rejected = Place::factory()->create(['status' => 'rejected']);

        $response = $this->getJson('/api/places');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$approved->id], $ids);
        $this->assertNotContains($pending->id, $ids);
        $this->assertNotContains($rejected->id, $ids);
    }

    public function test_places_index_filters_bbox(): void
    {
        $insideA = Place::factory()->create(['lat' => 17.590, 'lng' => 120.600]);
        $insideB = Place::factory()->create(['lat' => 17.600, 'lng' => 120.610]);
        $outside = Place::factory()->create(['lat' => 17.700, 'lng' => 120.700]);

        $response = $this->getJson('/api/places?bbox=17.55,120.58,17.65,120.66');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$insideA->id, $insideB->id], collect($ids)->sort()->values()->all());
        $this->assertNotContains($outside->id, $ids);
    }

    public function test_places_index_filters_category_and_search_q(): void
    {
        $coffee = $this->category('coffee_shop');
        $grocery = $this->category('grocery');

        $first = Place::factory()->create(['category_id' => $coffee->id, 'name' => 'Immaculate Brew of Bangued']);
        $second = Place::factory()->create(['category_id' => $coffee->id, 'name' => 'Zion Cafe', 'address' => 'Mirabra Lane 4, Bangued']);
        Place::factory()->create(['category_id' => $grocery->id, 'name' => 'Quirino Supermart']);

        $categoryFiltered = $this->getJson('/api/places?categories=coffee_shop')
            ->assertOk()
            ->json('data');

        $categoryIds = collect($categoryFiltered)->pluck('id')->all();
        $this->assertSame([$first->id, $second->id], collect($categoryIds)->sort()->values()->all());

        $nameSearch = $this->getJson('/api/places?q=Immaculate')->assertOk()->json('data');
        $this->assertSame([$first->id], collect($nameSearch)->pluck('id')->all());

        $addressSearch = $this->getJson('/api/places?q=Mirabra')->assertOk()->json('data');
        $this->assertSame([$second->id], collect($addressSearch)->pluck('id')->all());
    }

    public function test_places_index_radius_sorts_by_distance(): void
    {
        $nearA = Place::factory()->create(['lat' => 17.5999, 'lng' => 120.6100]);
        $nearB = Place::factory()->create(['lat' => 17.6002, 'lng' => 120.6100]);
        Place::factory()->create(['lat' => 17.7000, 'lng' => 120.7000]);

        $response = $this->getJson('/api/places?lat=17.6000&lng=120.6100&radius=500');

        $response->assertOk();

        $data = $response->json('data');

        $this->assertSame(2, count($data));

        $ids = collect($data)->pluck('id')->all();

        $this->assertContains($nearA->id, $ids);
        $this->assertContains($nearB->id, $ids);

        $distances = array_column($data, 'distance_m');
        $this->assertLessThan($distances[1], $distances[0]);
        $this->assertNotNull($distances[0]);
    }

    public function test_places_show_returns_full_profile_and_404_for_pending(): void
    {
        $place = Place::factory()->create([
            'name' => 'Bangued General Hospital',
            'category_id' => $this->category('hospital')->id,
        ]);

        foreach (range(0, 6) as $day) {
            PlaceHour::create([
                'place_id' => $place->id,
                'day' => $day,
                'opens' => $day === 0 ? null : '07:00:00',
                'closes' => $day === 0 ? null : '19:00:00',
                'is_closed' => $day === 0,
                'note' => null,
            ]);
        }

        PlacePhoto::create([
            'place_id' => $place->id,
            'path' => 'photos/' . $place->id . '/cover.jpg',
            'uploader_id' => null,
            'is_cover' => true,
            'status' => 'approved',
        ]);

        $detail = $this->getJson('/api/places/' . $place->id);

        $detail->assertOk();
        $this->assertCount(7, $detail->json('data.hours'));
        $this->assertCount(1, $detail->json('data.photos'));
        $this->assertSame('hospital', $detail->json('data.category.slug'));
        $this->assertSame($place->id, $detail->json('data.id'));

        $pending = Place::factory()->unapproved()->create();

        $this->getJson('/api/places/' . $pending->id)->assertNotFound();
    }

    public function test_places_index_hours_open_now_hits(): void
    {
        $open = Place::factory()->create(['name' => 'Daylight Grocers']);

        $this->tieHoursToNow($open->id, 60);

        $this->getJson('/api/places?hours=open_now')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/places?hours=open_now')
            ->assertOk()
            ->assertJsonPath('data.0.id', $open->id);
    }

    public function test_places_index_hours_open_24h_and_unknown(): void
    {
        $day = (int) now('Asia/Manila')->dayOfWeek;

        $twentyFour = Place::factory()->create(['name' => '24hr Care Pharmacy']);
        PlaceHour::create([
            'place_id' => $twentyFour->id,
            'day' => $day,
            'opens' => '00:00:00',
            'closes' => '23:59:00',
            'is_closed' => false,
            'note' => null,
        ]);

        $unknown = Place::factory()->create(['name' => 'No Hours Stall', 'description' => 'Closed for now']);

        $this->getJson('/api/places?hours=open_24h')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $twentyFour->id);

        $this->getJson('/api/places?hours=unknown')
            ->assertOk()
            ->assertJsonPath('data.0.id', $unknown->id);
    }

    private function tieHoursToNow(int $placeId, int $marginMinutes): void
    {
        $day = (int) now('Asia/Manila')->dayOfWeek;
        $minuteOfDay = ((int) now('Asia/Manila')->format('G')) * 60 + (int) now('Asia/Manila')->format('i');

        PlaceHour::create([
            'place_id' => $placeId,
            'day' => $day,
            'opens' => $this->minutesToHm($minuteOfDay - $marginMinutes),
            'closes' => $this->minutesToHm($minuteOfDay + $marginMinutes),
            'is_closed' => false,
            'note' => null,
        ]);
    }

    private function minutesToHm(int $minutes): string
    {
        $clamped = min(1439, max(0, $minutes));

        return sprintf('%02d:%02d', intdiv($clamped, 60), $clamped % 60);
    }
}