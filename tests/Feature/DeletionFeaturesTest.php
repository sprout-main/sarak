<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ModQueue;
use App\Models\Place;
use App\Models\PlaceHour;
use App\Models\PlacePhoto;
use App\Models\PlaceReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class DeletionFeaturesTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_admin_can_delete_place_with_photo_file_cleanup_and_audit(): void
    {
        Storage::fake('public');

        $admin = $this->adminUser();
        $place = Place::factory()->create([
            'category_id' => $this->category('grocery')->id,
        ]);

        PlaceHour::create([
            'place_id' => $place->id,
            'day' => 1,
            'opens' => '08:00',
            'closes' => '17:00',
        ]);

        $file = UploadedFile::fake()->image('test_place_photo.jpg');
        $photoPath = $file->store("photos/{$place->id}", 'public');

        PlacePhoto::create([
            'place_id' => $place->id,
            'path' => $photoPath,
            'uploader_id' => $admin->id,
            'is_cover' => true,
            'status' => 'approved',
        ]);

        Storage::disk('public')->assertExists($photoPath);

        $response = $this->actingAs($admin)->deleteJson("/api/admin/places/{$place->id}");
        $response->assertOk();

        // Database assertions
        $this->assertDatabaseMissing('places', ['id' => $place->id]);
        $this->assertDatabaseMissing('place_hours', ['place_id' => $place->id]);
        $this->assertDatabaseMissing('place_photos', ['place_id' => $place->id]);

        // File system assertion
        Storage::disk('public')->assertMissing($photoPath);

        // Audit log assertion
        $this->assertDatabaseHas('audit_log', [
            'model' => 'place',
            'model_id' => $place->id,
            'action' => 'delete',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_admin_can_discard_queue_item_with_photo_file_cleanup(): void
    {
        Storage::fake('public');

        $admin = $this->adminUser();
        $user = $this->residentUser();
        $place = Place::factory()->create([
            'category_id' => $this->category('grocery')->id,
        ]);

        $file = UploadedFile::fake()->image('queued_photo.jpg');
        $photoPath = $file->store("photos/{$place->id}", 'public');

        PlacePhoto::create([
            'place_id' => $place->id,
            'path' => $photoPath,
            'uploader_id' => $user->id,
            'is_cover' => false,
            'status' => 'pending',
        ]);

        $queue = ModQueue::create([
            'action' => 'photo',
            'place_id' => $place->id,
            'payload' => ['photo_path' => $photoPath],
            'requested_by' => $user->id,
            'status' => 'pending',
        ]);

        Storage::disk('public')->assertExists($photoPath);

        $response = $this->actingAs($admin)->deleteJson("/api/admin/queue/{$queue->id}");
        $response->assertOk();

        // Queue item and photo row should be deleted
        $this->assertDatabaseMissing('mod_queue', ['id' => $queue->id]);
        $this->assertDatabaseMissing('place_photos', ['path' => $photoPath]);

        // Photo file unlinked
        Storage::disk('public')->assertMissing($photoPath);

        // Audit log assertion
        $this->assertDatabaseHas('audit_log', [
            'model' => 'mod_queue',
            'model_id' => $queue->id,
            'action' => 'discard',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_admin_can_delete_user_and_prevents_self_delete(): void
    {
        $admin = $this->adminUser();
        $targetUser = $this->residentUser();

        // Admin cannot delete self
        $this->actingAs($admin)->deleteJson("/api/admin/users/{$admin->id}")
            ->assertStatus(422);

        // Admin can delete target user
        $this->actingAs($admin)->deleteJson("/api/admin/users/{$targetUser->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);

        $this->assertDatabaseHas('audit_log', [
            'model' => 'user',
            'model_id' => $targetUser->id,
            'action' => 'delete',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_user_can_delete_own_profile(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password-123'),
        ]);

        // Wrong password fails
        $this->actingAs($user)->delete('/profile', [
            'password' => 'wrong-password',
        ])->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // Correct password succeeds
        $this->actingAs($user)->delete('/profile', [
            'password' => 'secret-password-123',
        ])->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_can_delete_place_report(): void
    {
        $admin = $this->adminUser();
        $place = Place::factory()->create([
            'category_id' => $this->category('grocery')->id,
        ]);

        $report = PlaceReport::create([
            'place_id' => $place->id,
            'issue_type' => 'duplicate',
            'details' => 'Duplicate listing',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/admin/reports/{$report->id}");
        $response->assertOk();

        $this->assertDatabaseMissing('place_reports', ['id' => $report->id]);

        $this->assertDatabaseHas('audit_log', [
            'model' => 'place_report',
            'model_id' => $report->id,
            'action' => 'delete',
            'actor_id' => $admin->id,
        ]);
    }
}
