<?php

namespace Tests\Feature;

use App\Enums\QueueAction;
use App\Models\BoundaryArea;
use App\Models\ImportRun;
use App\Models\ModQueue;
use App\Models\Place;
use App\Services\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class AdminReadEndpointsTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_admin_queue_index(): void
    {
        $place = Place::factory()->create(['name' => 'Old Name Corner Store']);
        $admin = $this->adminUser();
        $moderation = app(ModerationService::class);

        $create = $moderation->submit(QueueAction::Create, [
            'name' => 'Brand New Store',
            'lat' => 17.6000,
            'lng' => 120.6100,
        ], $admin);

        $update = $moderation->submit(QueueAction::Update, [
            'place_id' => $place->id,
            'name' => 'New Name Corner Store',
            'phone' => '0917 555 1234',
        ], $admin);

        $move = $moderation->submit(QueueAction::Move, [
            'place_id' => $place->id,
            'lat' => 17.6099,
            'lng' => 120.6199,
        ], $admin);

        $moderator = $this->moderatorUser();

        $response = $this->actingAs($moderator)
            ->getJson('/api/admin/queue')
            ->assertOk();

        $data = collect($response->json('data'));

        $this->assertSame('pending', $data->firstWhere('id', $create->id)['status']);
        $this->assertNull($data->firstWhere('id', $create->id)['place']);

        $updateRow = $data->firstWhere('id', $update->id);
        $this->assertSame('Old Name Corner Store', $updateRow['diff']['before']['name']);
        $this->assertSame('New Name Corner Store', $updateRow['diff']['after']['name']);

        $moveRow = $data->firstWhere('id', $move->id);
        $this->assertSame((int) $place->id, $moveRow['place']['id']);
        $this->assertEquals(17.6099, $moveRow['diff']['after']['lat']);
        $this->assertEquals(120.6199, $moveRow['diff']['after']['lng']);
    }

    public function test_admin_places_index(): void
    {
        $approved = Place::factory()->create(['name' => 'Studio Kai']);
        $second = Place::factory()->create(['name' => 'Studio Migs']);
        Place::factory()->unapproved()->create(['name' => 'Pending Depot']);

        $response = $this->actingAs($this->moderatorUser())
            ->getJson('/api/admin/places?status=approved')
            ->assertOk();

        $places = $response->json('data');

        $this->assertCount(2, $places);
        $this->assertSame([$approved->id, $second->id], collect($places)->pluck('id')->sort()->values()->all());

        $response->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'category', 'barangay', 'lat', 'lng', 'status', 'source', 'verified_tier', 'updated_at']],
            'meta' => ['total', 'per_page', 'current_page', 'last_page'],
        ]);
    }

    public function test_admin_import_index(): void
    {
        ImportRun::create(['source' => 'overpass', 'status' => 'done', 'started_at' => now(), 'finished_at' => now(), 'batch_summary' => ['attempted' => 3, 'queued' => 2]]);
        $latest = ImportRun::create(['source' => 'official_list', 'status' => 'running', 'started_at' => now(), 'finished_at' => null, 'batch_summary' => null]);

        $response = $this->actingAs($this->adminUser())
            ->getJson('/api/admin/imports')
            ->assertOk();

        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertSame($latest->id, $data[0]['id']);

        $response->assertJsonStructure([
            'data' => [[
                'id',
                'source',
                'batch_summary',
                'status',
                'started_at',
                'finished_at',
            ]],
        ]);
    }

    public function test_admin_boundaries_index(): void
    {
        $area = BoundaryArea::create([
            'name' => 'Palao',
            'type' => 'barangay',
            'boundary_source' => 'PSGC 2023',
            'psgc' => '1400101001',
            'imported_at' => now(),
        ]);

        Place::factory()->count(2)->create(['barangay_id' => $area->id]);

        $this->actingAs($this->adminUser())
            ->getJson('/api/admin/boundaries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Palao')
            ->assertJsonPath('data.0.place_count', 2)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'type', 'psgc', 'boundary_source', 'imported_at', 'place_count']],
            ]);
    }

    public function test_admin_analytics_coverage_and_pending(): void
    {
        $coffee = $this->category('coffee_shop');
        $grocery = $this->category('grocery');
        $palao = BoundaryArea::create(['name' => 'Palao', 'type' => 'barangay', 'psgc' => '1400101001']);
        $mira = BoundaryArea::create(['name' => 'Mira', 'type' => 'barangay', 'psgc' => '1400101002']);

        Place::factory()->count(2)->create(['category_id' => $coffee->id, 'barangay_id' => $palao->id]);
        Place::factory()->create(['category_id' => $grocery->id, 'barangay_id' => $mira->id]);

        $admin = $this->adminUser();

        app(ModerationService::class)->submit(QueueAction::Create, [
            'category_id' => $coffee->id,
            'name' => 'Awaiting Kapehan',
            'lat' => 17.6000,
            'lng' => 120.6100,
        ], $admin);

        $coverage = $this->actingAs($admin)
            ->getJson('/api/admin/analytics/coverage')
            ->assertOk()
            ->json();

        $coffeeRow = collect($coverage['matrix'])->firstWhere('category.slug', 'coffee_shop');
        $coffeeBarangayCounts = collect($coffeeRow['barangays'])->keyBy('id');

        $this->assertSame(2, $coffeeBarangayCounts[$palao->id]['count']);
        $this->assertSame(0, $coffeeBarangayCounts[$mira->id]['count']);

        $coffeeTotal = collect($coverage['totals'])->firstWhere('category_id', $coffee->id);
        $this->assertSame(2, $coffeeTotal['approved']);
        $this->assertSame(1, $coffeeTotal['pending']);

        $emptySpot = collect($coverage['empty_spots'])->first(fn (array $spot) => $spot['category_id'] === $coffee->id && $spot['barangay_id'] === $mira->id);
        $this->assertSame('Mira', $emptySpot['barangay_name']);

        app(ModerationService::class)->submit(QueueAction::Update, ['place_id' => $palao->id, 'name' => 'x'], $admin);
        app(ModerationService::class)->submit(QueueAction::Move, ['place_id' => $palao->id, 'lat' => 17.61, 'lng' => 120.62], $admin);
        app(ModerationService::class)->submit(QueueAction::Photo, ['place_id' => $palao->id, 'photo_path' => 'photos/x.jpg'], $admin);
        app(ModerationService::class)->submit(QueueAction::Delete, ['place_id' => $palao->id], $admin);

        $pending = $this->actingAs($admin)
            ->getJson('/api/admin/analytics/pending')
            ->assertOk()
            ->json();

        $this->assertSame(5, $pending['total']);
        $this->assertSame(['create' => 1, 'update' => 1, 'move' => 1, 'photo' => 1, 'delete' => 1], $pending['by_action']);
        $this->assertSame(5, ModQueue::where('status', 'pending')->count());
    }

    public function test_role_guards(): void
    {
        $this->getJson('/api/admin/queue')->assertUnauthorized();
        $this->getJson('/api/admin/analytics/pending')->assertUnauthorized();

        $user = $this->residentUser();

        $this->actingAs($user)
            ->getJson('/api/admin/queue')
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson('/api/admin/analytics/coverage')
            ->assertForbidden();
    }
}