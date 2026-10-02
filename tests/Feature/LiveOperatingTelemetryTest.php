<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceHour;
use App\Services\HoursService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class LiveOperatingTelemetryTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_24h_telemetry_returns_24_7_service(): void
    {
        $cat = $this->category('hospital');
        $place = Place::factory()->create(['category_id' => $cat->id]);

        PlaceHour::create([
            'place_id' => $place->id,
            'day' => 1,
            'opens' => '00:00:00',
            'closes' => '23:59:00',
            'is_closed' => false,
        ]);

        $service = app(HoursService::class);
        $telemetry = $service->telemetry($place);

        $this->assertSame('open_24h', $telemetry['status']);
        $this->assertSame('24/7 Service', $telemetry['countdown']);
        $this->assertSame('open_24h', $telemetry['badge']);
    }

    public function test_closing_soon_telemetry_identifies_imminent_closure(): void
    {
        // Wednesday (day 3) at 17:15
        Carbon::setTestNow(Carbon::parse('2026-09-23 17:15:00', 'Asia/Manila'));

        $cat = $this->category('coffee_shop');
        $place = Place::factory()->create(['category_id' => $cat->id]);

        PlaceHour::create([
            'place_id' => $place->id,
            'day' => 3,
            'opens' => '08:00:00',
            'closes' => '18:00:00',
            'is_closed' => false,
        ]);

        $service = app(HoursService::class);
        $telemetry = $service->telemetry($place);

        $this->assertSame('closing_soon', $telemetry['status']);
        $this->assertSame(45, $telemetry['minutes_remaining']);
        $this->assertSame('Closes in 45 mins', $telemetry['countdown']);
    }

    public function test_open_normal_telemetry_displays_closing_time(): void
    {
        // Wednesday (day 3) at 10:00
        Carbon::setTestNow(Carbon::parse('2026-09-23 10:00:00', 'Asia/Manila'));

        $cat = $this->category('grocery');
        $place = Place::factory()->create(['category_id' => $cat->id]);

        PlaceHour::create([
            'place_id' => $place->id,
            'day' => 3,
            'opens' => '08:00:00',
            'closes' => '20:00:00',
            'is_closed' => false,
        ]);

        $service = app(HoursService::class);
        $telemetry = $service->telemetry($place);

        $this->assertSame('open', $telemetry['status']);
        $this->assertSame('Closes at 8:00 PM', $telemetry['countdown']);
    }

    public function test_closed_place_telemetry_displays_tomorrow_opening(): void
    {
        // Wednesday (day 3) at 21:00 (closed for the day)
        Carbon::setTestNow(Carbon::parse('2026-09-23 21:00:00', 'Asia/Manila'));

        $cat = $this->category('pharmacy');
        $place = Place::factory()->create(['category_id' => $cat->id]);

        // Closed Wednesday at 18:00, opens Thursday (day 4) at 08:00
        PlaceHour::create([
            'place_id' => $place->id,
            'day' => 3,
            'opens' => '08:00:00',
            'closes' => '18:00:00',
            'is_closed' => false,
        ]);
        PlaceHour::create([
            'place_id' => $place->id,
            'day' => 4,
            'opens' => '08:00:00',
            'closes' => '18:00:00',
            'is_closed' => false,
        ]);

        $service = app(HoursService::class);
        $telemetry = $service->telemetry($place);

        $this->assertSame('closed', $telemetry['status']);
        $this->assertSame('Opens at 8:00 AM tomorrow', $telemetry['countdown']);
    }

    public function test_api_places_includes_telemetry_in_resource(): void
    {
        $cat = $this->category('coffee_shop');
        Place::factory()->create(['category_id' => $cat->id]);

        $response = $this->getJson('/api/places')->assertOk();

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'telemetry' => [
                        'status',
                        'label',
                        'countdown',
                        'badge',
                        'minutes_remaining',
                    ],
                ],
            ],
        ]);
    }
}
