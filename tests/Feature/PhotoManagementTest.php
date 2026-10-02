<?php

namespace Tests\Feature;

use App\Enums\QueueAction;
use App\Enums\QueueStatus;
use App\Models\ModQueue;
use App\Models\Place;
use App\Models\PlacePhoto;
use App\Models\User;
use App\Services\PhotoService;
use App\Services\PhotoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

/**
 * Covers the photo row plus file lifecycle.
 *
 * place_photos.place_id cascades at the MySQL level, so a deleted place takes
 * its rows with it and no Eloquent event fires. These tests exist to prove the
 * files are removed explicitly at every site that used to leak them.
 */
class PhotoManagementTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function place(): Place
    {
        return Place::factory()->create();
    }

    private function seedPhoto(Place $place, ?User $uploader = null, string $status = 'approved'): PlacePhoto
    {
        $path = 'photos/'.$place->id.'/'.uniqid('bg_', true).'.jpg';
        Storage::disk('public')->put($path, 'binary');

        return PlacePhoto::create([
            'place_id' => $place->id,
            'path' => $path,
            'uploader_id' => $uploader?->id,
            'is_cover' => false,
            'status' => $status,
        ]);
    }

    public function test_admin_can_delete_a_photo_row_and_its_file(): void
    {
        $place = $this->place();
        $photo = $this->seedPhoto($place, $this->adminUser());
        $path = $photo->path;

        $this->actingAs($this->adminUser())
            ->deleteJson("/api/admin/places/{$place->id}/photos/{$photo->id}")
            ->assertOk();

        $this->assertDatabaseMissing('place_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseHas('audit_log', [
            'model' => 'place_photo',
            'model_id' => $photo->id,
            'action' => 'delete',
        ]);
    }

    public function test_deleting_the_last_photo_removes_the_empty_place_directory(): void
    {
        $place = $this->place();
        $photo = $this->seedPhoto($place, $this->adminUser());

        $this->actingAs($this->adminUser())
            ->deleteJson("/api/admin/places/{$place->id}/photos/{$photo->id}")
            ->assertOk();

        Storage::disk('public')->assertMissing('photos/'.$place->id);
    }

    public function test_admin_set_cover_demotes_the_previous_cover(): void
    {
        $place = $this->place();
        $first = $this->seedPhoto($place);
        $second = $this->seedPhoto($place);

        $this->actingAs($this->adminUser())
            ->patchJson("/api/admin/places/{$place->id}/photos/{$second->id}/cover")
            ->assertOk()
            ->assertJsonPath('data.is_cover', true);

        $this->assertFalse((bool) $first->refresh()->is_cover);
        $this->assertTrue((bool) $second->refresh()->is_cover);

        $this->assertSame(1, PlacePhoto::where('place_id', $place->id)->where('is_cover', true)->count());
    }

    public function test_deleting_the_cover_promotes_another_photo(): void
    {
        $place = $this->place();
        $cover = $this->seedPhoto($place);
        $other = $this->seedPhoto($place);

        app(PhotoService::class)->setCover($cover);

        $this->actingAs($this->adminUser())
            ->deleteJson("/api/admin/places/{$place->id}/photos/{$cover->id}")
            ->assertOk();

        $this->assertTrue((bool) $other->refresh()->is_cover);
    }

    public function test_user_cannot_delete_a_photo_they_did_not_upload(): void
    {
        $place = $this->place();
        $owner = $this->residentUser();
        $photo = $this->seedPhoto($place, $owner, 'pending');

        $this->actingAs($this->residentUser())
            ->deleteJson("/api/places/{$place->id}/photos/{$photo->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('place_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertExists($photo->path);
    }

    public function test_user_cannot_delete_their_own_approved_photo(): void
    {
        $place = $this->place();
        $owner = $this->residentUser();
        $photo = $this->seedPhoto($place, $owner, 'approved');

        $this->actingAs($owner)
            ->deleteJson("/api/places/{$place->id}/photos/{$photo->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('place_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertExists($photo->path);
    }

    public function test_user_can_withdraw_their_own_pending_photo(): void
    {
        $place = $this->place();
        $owner = $this->residentUser();
        $photo = $this->seedPhoto($place, $owner, 'pending');

        $this->actingAs($owner)
            ->deleteJson("/api/places/{$place->id}/photos/{$photo->id}")
            ->assertOk();

        $this->assertDatabaseMissing('place_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($photo->path);
    }

    public function test_withdrawing_a_pending_photo_also_removes_its_queue_item(): void
    {
        $place = $this->place();
        $owner = $this->residentUser();
        $photo = $this->seedPhoto($place, $owner, 'pending');

        ModQueue::create([
            'action' => QueueAction::Photo->value,
            'place_id' => $place->id,
            'payload' => ['photo_path' => $photo->path, 'place_id' => $place->id],
            'requested_by' => $owner->id,
            'status' => QueueStatus::Pending->value,
        ]);

        $this->actingAs($owner)
            ->deleteJson("/api/places/{$place->id}/photos/{$photo->id}")
            ->assertOk();

        $this->assertSame(0, ModQueue::where('action', QueueAction::Photo->value)->count());
    }

    public function test_user_cannot_set_cover_on_someone_elses_photo(): void
    {
        $place = $this->place();
        $photo = $this->seedPhoto($place, $this->residentUser(), 'pending');

        $this->actingAs($this->residentUser())
            ->patchJson("/api/places/{$place->id}/photos/{$photo->id}/cover")
            ->assertForbidden();
    }

    public function test_user_can_flag_their_own_pending_photo_as_cover(): void
    {
        $place = $this->place();
        $owner = $this->residentUser();
        $photo = $this->seedPhoto($place, $owner, 'pending');

        $this->actingAs($owner)
            ->patchJson("/api/places/{$place->id}/photos/{$photo->id}/cover")
            ->assertOk();

        $this->assertTrue((bool) $photo->refresh()->is_cover);
    }

    public function test_admin_upload_publishes_immediately_with_no_queue_item(): void
    {
        $place = $this->place();

        $response = $this->actingAs($this->adminUser())
            ->postJson("/api/admin/places/{$place->id}/photos", [
                'photo' => UploadedFile::fake()->image('front.jpg', 400, 400),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved');

        $path = $response->json('data.path');

        Storage::disk('public')->assertExists($path);
        $this->assertSame(0, ModQueue::where('action', QueueAction::Photo->value)->count());
        $this->assertSame(1, $place->photos()->where('status', 'approved')->count());
    }

    public function test_contributor_upload_queues_and_stays_pending(): void
    {
        $place = $this->place();

        $response = $this->actingAs($this->residentUser())
            ->postJson("/api/places/{$place->id}/photos", [
                'photo' => UploadedFile::fake()->image('side.jpg', 400, 400),
            ])
            ->assertCreated()
            ->assertJsonPath('data.message', 'Photo submitted for review');

        Storage::disk('public')->assertExists($response->json('data.photo_id') ? 'photos/'.$place->id : 'nope');

        $this->assertSame(1, ModQueue::where('action', QueueAction::Photo->value)->count());
        $this->assertSame(1, $place->photos()->where('status', 'pending')->count());
    }

    public function test_approving_a_queued_photo_does_not_displace_an_existing_cover(): void
    {
        $place = $this->place();
        $existingCover = $this->seedPhoto($place);
        app(PhotoService::class)->setCover($existingCover);

        $pending = $this->seedPhoto($place, null, 'pending');

        $queue = ModQueue::create([
            'action' => QueueAction::Photo->value,
            'place_id' => $place->id,
            'payload' => ['photo_path' => $pending->path, 'place_id' => $place->id],
            'requested_by' => $this->adminUser()->id,
            'status' => QueueStatus::Pending->value,
        ]);

        $this->actingAs($this->adminUser())
            ->postJson("/api/admin/queue/{$queue->id}/approve")
            ->assertOk();

        $this->assertTrue((bool) $existingCover->refresh()->is_cover);
        $this->assertFalse((bool) $pending->refresh()->is_cover);
        $this->assertSame(1, PlacePhoto::where('place_id', $place->id)->where('is_cover', true)->count());
    }

    public function test_rejecting_a_photo_submission_removes_the_file_and_row(): void
    {
        $place = $this->place();
        $photo = $this->seedPhoto($place, $this->residentUser(), 'pending');
        $path = $photo->path;

        $queue = ModQueue::create([
            'action' => QueueAction::Photo->value,
            'place_id' => $place->id,
            'payload' => ['photo_path' => $path, 'place_id' => $place->id],
            'requested_by' => $this->residentUser()->id,
            'status' => QueueStatus::Pending->value,
        ]);

        $this->actingAs($this->adminUser())
            ->postJson("/api/admin/queue/{$queue->id}/reject", ['note' => 'Not a shop front'])
            ->assertOk();

        $this->assertDatabaseMissing('place_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_discarding_a_photo_queue_item_removes_the_file_and_row(): void
    {
        $place = $this->place();
        $photo = $this->seedPhoto($place, $this->residentUser(), 'pending');
        $path = $photo->path;

        $queue = ModQueue::create([
            'action' => QueueAction::Photo->value,
            'place_id' => $place->id,
            'payload' => ['photo_path' => $path, 'place_id' => $place->id],
            'requested_by' => $this->residentUser()->id,
            'status' => QueueStatus::Pending->value,
        ]);

        $this->actingAs($this->adminUser())
            ->deleteJson("/api/admin/queue/{$queue->id}")
            ->assertOk();

        $this->assertDatabaseMissing('place_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_deleting_a_place_removes_its_photo_files(): void
    {
        $place = $this->place();
        $a = $this->seedPhoto($place);
        $b = $this->seedPhoto($place);
        $paths = [$a->path, $b->path];

        $this->actingAs($this->adminUser())
            ->deleteJson("/api/admin/places/{$place->id}")
            ->assertOk();

        $this->assertDatabaseMissing('places', ['id' => $place->id]);

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        Storage::disk('public')->assertMissing('photos/'.$place->id);
    }

    /**
     * @dataProvider unsafePaths
     */
    public function test_photo_storage_refuses_paths_outside_photos(string $path): void
    {
        $storage = app(PhotoStorage::class);

        $this->assertNull($storage->normalize($path));
        $this->assertFalse($storage->forget($path));
    }

    public static function unsafePaths(): array
    {
        return [
            'parent traversal' => ['photos/1/../../../.env'],
            'leading traversal' => ['../.env'],
            'absolute unix' => ['/etc/passwd'],
            'windows drive' => ['C:/Windows/win.ini'],
            'outside photos' => ['avatars/1/pic.png'],
            'empty' => [''],
            'root only' => ['/'],
            'null byte' => ["photos/1/a.jpg\0.png"],
        ];
    }

    public function test_photo_storage_accepts_normalised_photo_paths(): void
    {
        $storage = app(PhotoStorage::class);

        $this->assertSame('photos/12/bg_a.jpg', $storage->normalize('photos\\12\\bg_a.jpg'));
        $this->assertSame('photos/12/bg_a.jpg', $storage->normalize('./photos/12/bg_a.jpg'));
        $this->assertSame('photos/12/bg_a.jpg', $storage->normalize('photos/./12/bg_a.jpg'));

        // A leading slash is treated as web-root absolute rather than being
        // silently reinterpreted, because place_photos.path values are written
        // relative to the disk root.
        $this->assertNull($storage->normalize('/photos/12/bg_a.jpg'));

        // The bare root is not a photo and must not delete the tree.
        $this->assertNull($storage->normalize('photos'));
    }
}
