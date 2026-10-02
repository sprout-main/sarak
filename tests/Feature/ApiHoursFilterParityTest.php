<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Place;
use App\Models\PlaceHour;
use App\Services\HoursService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class ApiHoursFilterParityTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_sql_hours_filter_matches_php_hours_service_semantics(): void
    {
        $categoryId = Category::where('slug', 'grocery')->value('id');
        $day = (int) now('Asia/Manila')->dayOfWeek;

        $today = fn (array $overrides = []) => array_merge([
            'place_id' => 0,
            'day' => $day,
            'opens' => '07:00:00',
            'closes' => '22:00:00',
            'is_closed' => 0,
            'note' => null,
        ], $overrides);

        $noHours = Place::factory()->create(['category_id' => $categoryId]);

        $typical = Place::factory()->create(['category_id' => $categoryId]);
        PlaceHour::create($today(['place_id' => $typical->id]));

        $closedToday = Place::factory()->create(['category_id' => $categoryId]);
        PlaceHour::create($today(['place_id' => $closedToday->id, 'is_closed' => 1]));

        $equalTimes = Place::factory()->create(['category_id' => $categoryId]);
        PlaceHour::create($today(['place_id' => $equalTimes->id, 'opens' => '09:00:00', 'closes' => '09:00:00']));

        $nullOpens = Place::factory()->create(['category_id' => $categoryId]);
        PlaceHour::create($today(['place_id' => $nullOpens->id, 'opens' => null, 'closes' => '22:00:00']));

        $overnight = Place::factory()->create(['category_id' => $categoryId]);
        PlaceHour::create($today(['place_id' => $overnight->id, 'opens' => '03:00:00', 'closes' => '05:00:00']));

        $otherDayOnly = Place::factory()->create(['category_id' => $categoryId]);
        PlaceHour::create($today(['place_id' => $otherDayOnly->id, 'day' => ($day + 1) % 7]));

        $todayWithSeconds = Place::factory()->create(['category_id' => $categoryId]);
        PlaceHour::create($today(['place_id' => $todayWithSeconds->id, 'opens' => '07:30:45', 'closes' => '21:45:50']));

        $roundTheClock = Place::factory()->create(['category_id' => $categoryId]);
        PlaceHour::create($today(['place_id' => $roundTheClock->id, 'opens' => '00:21:11', 'closes' => '23:59:59']));

        $fullDay = Place::factory()->create(['category_id' => $categoryId]);
        PlaceHour::create($today(['place_id' => $fullDay->id, 'opens' => '00:00:00', 'closes' => '23:59:59', 'is_closed' => 0]));

        $closedFullDay = Place::factory()->create(['category_id' => $categoryId]);
        PlaceHour::create($today(['place_id' => $closedFullDay->id, 'opens' => '00:00:00', 'closes' => '23:59:59', 'is_closed' => 1]));

        $closedRoundTheClock = Place::factory()->create(['category_id' => $categoryId]);
        PlaceHour::create($today(['place_id' => $closedRoundTheClock->id, 'opens' => '00:21:11', 'closes' => '23:59:59', 'is_closed' => 1]));
        PlaceHour::create([
            'place_id' => $closedRoundTheClock->id,
            'day' => ($day + 1) % 7,
            'opens' => '00:00:00',
            'closes' => '23:59:59',
            'is_closed' => 0,
            'note' => null,
        ]);

        $service = app(HoursService::class);
        $all = Place::with('hours')->approved()->get();

        $expectedOpenNow = $all->filter(fn (Place $p) => $service->openNow($p) === true)->pluck('id')->all();
        $expectedOpen24h = $all->filter(fn (Place $p) => $service->is24h($p))->pluck('id')->all();
        $expectedUnknown = $all->filter(fn (Place $p) => ! $service->hasHours($p))->pluck('id')->all();

        $this->assertSame(
            $this->sorted($expectedOpenNow),
            $this->sorted($this->idsFor('/api/places?hours=open_now')),
            'SQL open_now must equal HoursService::openNow(true).'
        );

        $this->assertSame(
            $this->sorted($expectedOpen24h),
            $this->sorted($this->idsFor('/api/places?hours=open_24h')),
            'SQL open_24h must equal HoursService::is24h().'
        );

        $this->assertSame(
            $this->sorted($expectedUnknown),
            $this->sorted($this->idsFor('/api/places?hours=unknown')),
            'SQL unknown must equal !HoursService::hasHours().'
        );

        $this->assertNotSame([], $expectedOpenNow);
        $this->assertNotSame([], $expectedOpen24h);
        $this->assertContains($noHours->id, $expectedUnknown);
        $this->assertNotContains($closedToday->id, $expectedOpenNow);
        $this->assertNotContains($closedFullDay->id, $expectedOpenNow);
        $this->assertContains($closedFullDay->id, $expectedOpen24h);
        $this->assertContains($roundTheClock->id, $expectedOpenNow);
        $this->assertNotContains($roundTheClock->id, $expectedOpen24h);
    }

    public function test_hours_bbox_endpoint_stays_fast_on_3000_places(): void
    {
        $categoryId = Category::where('slug', 'grocery')->value('id');

        collect([1000, 1000, 1000])->each(function (int $batch) use ($categoryId) {
            Place::factory()
                ->count($batch)
                ->state(fn () => [
                    'category_id' => $categoryId,
                    'lat' => fake()->randomFloat(6, 17.55, 17.70),
                    'lng' => fake()->randomFloat(6, 120.55, 120.70),
                ])
                ->create();
        });

        Place::all()->each(function (Place $place) {
            PlaceHour::create([
                'place_id' => $place->id,
                'day' => 0,
                'opens' => '00:00:00',
                'closes' => '23:59:59',
                'is_closed' => 0,
                'note' => null,
            ]);
        });

        $this->assertSame(3000, Place::count());

        $start = microtime(true);
        $this->getJson('/api/places?bbox=17.50,120.50,17.75,120.75&hours=open_24h')->assertOk();
        $elapsedMs = (microtime(true) - $start) * 1000;

        fwrite(STDERR, sprintf("\nAPI hours=open_24h bbox over 3000 places: %.1f ms\n", $elapsedMs));

        $this->assertLessThan(2500, $elapsedMs);
    }

    private function idsFor(string $uri): array
    {
        $ids = [];
        $page = 1;

        for (;;) {
            $response = $this->getJson($uri . '&per_page=100&page=' . $page)->assertOk();
            $rows = $response->json('data');

            foreach ($rows as $row) {
                $ids[] = $row['id'];
            }

            $meta = $response->json('meta');

            if ($page >= (int) ($meta['last_page'] ?? 0) || count($rows) < 100) {
                break;
            }

            $page++;
        }

        return $ids;
    }

    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }
}