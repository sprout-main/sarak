<?php

namespace Tests\Feature;

use App\Enums\QueueAction;
use App\Models\BoundaryArea;
use App\Models\ModQueue;
use App\Models\Place;
use App\Services\BarangayResolver;
use App\Services\ModerationService;
use App\Services\PlaceService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BoundaryFixtures;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class GeoSnappingTest extends TestCase
{
    use CoreCatalog, BoundaryFixtures, RefreshDatabase;

    private BoundaryArea $palao;

    private BoundaryArea $mira;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);

        $this->palao = $this->polygon('Palao', $this->boxRing(17.58, 120.60, 17.605, 120.62));
        $this->mira = $this->polygon('Mira', $this->boxRing(17.605, 120.62, 17.63, 120.64));
    }

    public function test_resolver_finds_containing_barangay_and_null_outside(): void
    {
        $resolver = app(BarangayResolver::class);

        $this->assertSame($this->palao->id, $resolver->resolveId(17.59, 120.61));
        $this->assertSame('Palao', $resolver->resolve(17.592, 120.615)?->name);
        $this->assertSame($this->mira->id, $resolver->resolveId(17.61, 120.63));
        $this->assertNull($resolver->resolveId(17.57, 120.61));
        $this->assertNull($resolver->resolveId(17.61, 120.65));
        $this->assertNull($resolver->resolveId(0.0, 0.0));
    }

    public function test_apply_location_auto_assigns_barangay_when_not_explicit(): void
    {
        $place = Place::factory()->create(['lat' => 17.60, 'lng' => 120.62]);

        $this->assertNull($place->barangay_id);

        app(PlaceService::class)->applyLocation($place, 17.59, 120.61);

        $place->refresh();
        $this->assertSame($this->palao->id, $place->barangay_id);

        app(PlaceService::class)->applyLocation($place, 17.61, 120.63);
        $place->refresh();
        $this->assertSame($this->mira->id, $place->barangay_id);
    }

    public function test_apply_location_keeps_explicit_barangay(): void
    {
        $place = Place::factory()->create(['lat' => 17.59, 'lng' => 120.61]);

        app(PlaceService::class)->applyLocation($place, 17.59, 120.61, $this->mira->id);

        $place->refresh();
        $this->assertSame($this->mira->id, $place->barangay_id);
    }

    public function test_create_from_queue_resolves_barangay_when_missing(): void
    {
        $place = app(PlaceService::class)->createFromQueue([
            'name' => 'Palao Corner Store',
            'category_id' => $this->category('grocery')->id,
            'lat' => 17.595,
            'lng' => 120.615,
            'source' => 'manual',
        ]);

        $this->assertSame($this->palao->id, $place->barangay_id);

        $explicit = app(PlaceService::class)->createFromQueue([
            'name' => 'Explicitly Mira Store',
            'category_id' => $this->category('grocery')->id,
            'lat' => 17.59,
            'lng' => 120.61,
            'barangay_id' => $this->mira->id,
            'source' => 'manual',
        ]);

        $this->assertSame($this->mira->id, $explicit->barangay_id);
    }

    public function test_move_approval_re_snaps_barangay(): void
    {
        $place = Place::factory()->create(['lat' => 17.59, 'lng' => 120.61, 'barangay_id' => $this->palao->id]);
        $moderation = app(ModerationService::class);
        $admin = $this->adminUser();

        $moderation->submit(QueueAction::Move, [
            'place_id' => $place->id,
            'lat' => 17.612,
            'lng' => 120.632,
            'barangay_id' => null,
        ], $admin);

        $queue = ModQueue::where('action', 'move')->firstOrFail();
        $moderation->approve($queue, $admin);

        $place->refresh();
        $this->assertSame($this->mira->id, $place->barangay_id);
    }

    public function test_update_approval_re_snaps_barangay_on_coordinate_change(): void
    {
        $place = Place::factory()->create(['lat' => 17.59, 'lng' => 120.61, 'barangay_id' => $this->palao->id]);
        $moderation = app(ModerationService::class);
        $admin = $this->adminUser();

        $moderation->submit(QueueAction::Update, [
            'place_id' => $place->id,
            'lat' => 17.612,
            'lng' => 120.632,
            'phone' => '0917 000 1111',
        ], $admin);

        $queue = ModQueue::where('action', 'update')->firstOrFail();
        $moderation->approve($queue, $admin);

        $place->refresh();
        $this->assertSame($this->mira->id, $place->barangay_id);
    }

    public function test_public_geo_barangay_endpoint_resolves_a_point(): void
    {
        $this->getJson('/api/geo/barangay?lat=17.592&lng=120.615')
            ->assertOk()
            ->assertJsonPath('data.id', $this->palao->id)
            ->assertJsonPath('data.name', 'Palao');

        $this->getJson('/api/geo/barangay?lat=17.59&lng=120.59')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->getJson('/api/geo/barangay?lat=200&lng=120.615')->assertUnprocessable();
    }
}