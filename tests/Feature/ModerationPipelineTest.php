<?php

namespace Tests\Feature;

use App\Enums\QueueAction;
use App\Models\AuditLog;
use App\Models\ModQueue;
use App\Models\Place;
use App\Models\PlacePhoto;
use App\Services\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class ModerationPipelineTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_user_submission_lands_in_queue_not_places(): void
    {
        $user = $this->residentUser();
        $category = $this->category('grocery');

        $response = $this->actingAs($user)->postJson('/api/places', [
            'category_id' => $category->id,
            'name' => 'New Corner Grocery',
            'description' => 'Small neighborhood store',
            'address' => 'Brant St, Zone 1',
            'lat' => 17.6000,
            'lng' => 120.6100,
            'phone' => '0917 000 0000',
        ]);

        $response->assertCreated()->assertJsonPath('data.message', 'Submitted for review');

        $queue = ModQueue::findOrFail($response->json('data.queue_id'));

        $this->assertSame('create', $queue->action);
        $this->assertSame($user->id, (int) $queue->requested_by);
        $this->assertSame('pending', $queue->status);
        $this->assertSame('New Corner Grocery', $queue->payload['name']);
        $this->assertSame('manual', $queue->payload['source']);
        $this->assertDatabaseCount('places', 0);
    }

    public function test_admin_submission_bypasses_moderation_queue_and_publishes_directly(): void
    {
        $admin = $this->adminUser();
        $category = $this->category('grocery');

        $response = $this->actingAs($admin)->postJson('/api/places', [
            'category_id' => $category->id,
            'name' => 'Direct Admin Grocery',
            'description' => 'Admin added supermarket',
            'address' => 'Brant St, Zone 1',
            'lat' => 17.6000,
            'lng' => 120.6100,
            'phone' => '0917 000 0000',
        ]);

        $response->assertCreated();
        $placeId = $response->json('data.place_id');
        $this->assertNotNull($placeId);

        $this->assertDatabaseCount('mod_queue', 0);
        $this->assertDatabaseHas('places', [
            'id' => $placeId,
            'name' => 'Direct Admin Grocery',
            'status' => 'approved',
            'verified_tier' => 'verified',
            'submitted_by' => $admin->id,
            'approved_by' => $admin->id,
        ]);
    }

    public function test_moderator_can_approve_create_which_publishes_place_and_audits(): void
    {
        $user = $this->residentUser();
        $category = $this->category('pharmacy');

        $submission = $this->actingAs($user)->postJson('/api/places', [
            'category_id' => $category->id,
            'name' => 'Luna Pharmacy',
            'address' => 'Rizal St',
            'lat' => 17.6000,
            'lng' => 120.6100,
        ])->assertCreated()->json('data');

        $moderator = $this->moderatorUser();

        $approval = $this->actingAs($moderator)
            ->postJson('/api/admin/queue/' . $submission['queue_id'] . '/approve')
            ->assertOk();

        $this->assertSame('Approved', $approval->json('data.message'));

        $place = Place::firstOrFail();

        $this->assertSame('approved', $place->status);
        $this->assertSame($moderator->id, (int) $place->approved_by);
        $this->assertNotNull($place->approved_at);

        $queue = ModQueue::findOrFail($submission['queue_id']);
        $this->assertSame('approved', $queue->status);
        $this->assertSame($moderator->id, (int) $queue->reviewed_by);

        $this->assertDatabaseHas('audit_log', [
            'model' => 'place',
            'model_id' => $place->id,
            'action' => 'create',
            'actor_id' => $moderator->id,
        ]);

        $this->getJson('/api/places')
            ->assertOk()
            ->assertJsonFragment(['id' => $place->id]);
    }

    public function test_reject_requires_reason_and_does_not_publish(): void
    {
        $user = $this->residentUser();
        $category = $this->category('coffee_shop');

        $create = $this->actingAs($user)->postJson('/api/places', [
            'category_id' => $category->id,
            'name' => 'Kape Bangued',
            'lat' => 17.6000,
            'lng' => 120.6100,
        ])->assertCreated()->json('data');

        $moderator = $this->moderatorUser();

        $this->actingAs($moderator)
            ->postJson('/api/admin/queue/' . $create['queue_id'] . '/reject', [
                'note' => 'Name is misspelled and no address given.',
            ])
            ->assertOk()
            ->assertJsonPath('data.message', 'Rejected');

        $this->assertDatabaseCount('places', 0);

        $queue = ModQueue::findOrFail($create['queue_id']);
        $this->assertSame('rejected', $queue->status);
        $this->assertSame('Name is misspelled and no address given.', $queue->reviewer_note);

        $second = $this->actingAs($user)->postJson('/api/places', [
            'category_id' => $category->id,
            'name' => 'Tablac Coffee Bar',
            'lat' => 17.6100,
            'lng' => 120.6200,
        ])->assertCreated()->json('data');

        $this->actingAs($moderator)
            ->postJson('/api/admin/queue/' . $second['queue_id'] . '/reject')
            ->assertUnprocessable();
    }

    public function test_admin_can_approve_move_updates_location_and_audits_move(): void
    {
        $place = Place::factory()->create(['lat' => 17.600000, 'lng' => 120.610000]);
        $admin = $this->adminUser();

        app(ModerationService::class)->submit(
            QueueAction::Move,
            ['place_id' => $place->id, 'lat' => 17.609900, 'lng' => 120.619900, 'barangay_id' => null],
            $admin
        );

        $queue = ModQueue::where('action', 'move')->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/admin/queue/' . $queue->id . '/approve')
            ->assertOk();

        $place->refresh();

        $this->assertEquals(17.6099, (float) $place->lat);
        $this->assertEquals(120.6199, (float) $place->lng);

        $geom = DB::selectOne('SELECT ST_AsText(geom) AS g FROM places WHERE id = ?', [$place->id]);
        $this->assertStringContainsString('POINT(120.6199 17.6099)', (string) $geom->g);

        $moveLog = AuditLog::where(['model' => 'place', 'model_id' => $place->id, 'action' => 'move'])->firstOrFail();
        $this->assertEquals(17.600000, $moveLog->diff['before']['lat']);
        $this->assertEquals(120.610000, $moveLog->diff['before']['lng']);
        $this->assertEquals(17.6099, $moveLog->diff['after']['lat']);

        $this->assertEquals('approved', $queue->fresh()->status);

        $user = $this->residentUser();
        $this->actingAs($user)
            ->patchJson('/api/admin/places/' . $place->id . '/location', ['lat' => 17.59, 'lng' => 120.60])
            ->assertForbidden();
    }

    public function test_merge_deactivates_duplicate(): void
    {
        $source = Place::factory()->create(['name' => 'Dizon Pharmacy']);
        $target = Place::factory()->create(['name' => 'Dizon Drug Mart']);

        PlacePhoto::create([
            'place_id' => $source->id,
            'path' => 'photos/' . $source->id . '/duplicate.jpg',
            'uploader_id' => null,
            'is_cover' => false,
            'status' => 'approved',
        ]);

        $admin = $this->adminUser();

        app(ModerationService::class)->submit(
            QueueAction::Create,
            ['place_id' => $source->id, 'name' => $source->name],
            $admin
        );

        $queue = ModQueue::where('status', 'pending')->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/admin/queue/' . $queue->id . '/merge', ['into_place_id' => $target->id])
            ->assertOk()
            ->assertJsonPath('data.place_id', $target->id);

        $this->assertNull(Place::find($source->id));
        $this->assertNotNull(Place::withTrashed()->find($source->id));
        $this->assertSame('approved', $queue->fresh()->status);

        $photo = PlacePhoto::where('path', 'photos/' . $source->id . '/duplicate.jpg')->firstOrFail();
        $this->assertSame($target->id, (int) $photo->place_id);

        $this->assertDatabaseHas('audit_log', ['model' => 'place', 'model_id' => $target->id, 'action' => 'merge']);

        $this->getJson('/api/places')
            ->assertOk()
            ->assertJsonFragment(['id' => $target->id]);
    }

    public function test_moderation_invariant_nothing_unapproved_mutates_public_read(): void
    {
        $base = Place::factory()->create([
            'name' => 'Invariant Base Supply',
            'lat' => 17.600000,
            'lng' => 120.610000,
        ]);

        $before = [
            'index' => $this->getJson('/api/places')->assertOk()->json(),
            'base' => $this->getJson('/api/places/' . $base->id)->assertOk()->json(),
        ];

        $resident = $this->residentUser();
        $category = $this->category('hospital');

        $this->actingAs($resident)->postJson('/api/places', [
            'category_id' => $category->id,
            'name' => 'Invariant New Clinic',
            'address' => 'Village Rd, Palao',
            'lat' => 17.6050,
            'lng' => 120.6150,
        ])->assertCreated();

        $admin = $this->adminUser();

        app(ModerationService::class)->submit(
            QueueAction::Move,
            ['place_id' => $base->id, 'lat' => 17.609900, 'lng' => 120.619900],
            $admin
        );

        $during = [
            'index' => $this->getJson('/api/places')->assertOk()->json(),
            'base' => $this->getJson('/api/places/' . $base->id)->assertOk()->json(),
        ];

        $this->assertSame($before, $during, 'Pending rows must never mutate the public read output.');

        $moderator = $this->moderatorUser();

        foreach (ModQueue::where('status', 'pending')->orderBy('id')->get() as $queue) {
            $this->actingAs($moderator)
                ->postJson('/api/admin/queue/' . $queue->id . '/approve')
                ->assertOk();
        }

        $afterIndex = $this->getJson('/api/places')->assertOk()->json();
        $afterDetail = $this->getJson('/api/places/' . $base->id)->assertOk()->json();

        $this->assertNotSame($during['index'], $afterIndex);
        $this->assertNotSame($during['base'], $afterDetail);

        $published = Place::where('name', 'Invariant New Clinic')->firstOrFail();

        $indexIds = collect($afterIndex['data'])->pluck('id')->all();
        $this->assertContains($published->id, $indexIds);
        $this->assertSame('approved', $published->status);

        $this->assertEquals(17.6099, $afterDetail['data']['lat']);
        $this->assertEquals(120.6199, $afterDetail['data']['lng']);
        $this->assertEquals(17.600000, $before['base']['data']['lat']);
    }

    public function test_admin_can_delete_queue_item(): void
    {
        $user = $this->residentUser();
        $admin = $this->adminUser();
        $category = $this->category('grocery');

        $submission = $this->actingAs($user)->postJson('/api/places', [
            'category_id' => $category->id,
            'name' => 'Discard Me Grocery',
            'address' => 'Zone 4',
            'lat' => 17.6000,
            'lng' => 120.6100,
        ])->assertCreated()->json('data');

        $queueId = $submission['queue_id'];
        $this->assertDatabaseHas('mod_queue', ['id' => $queueId]);

        $this->actingAs($admin)
            ->deleteJson('/api/admin/queue/' . $queueId)
            ->assertOk()
            ->assertJsonPath('data.message', 'Queue item deleted successfully');

        $this->assertDatabaseMissing('mod_queue', ['id' => $queueId]);
    }
}