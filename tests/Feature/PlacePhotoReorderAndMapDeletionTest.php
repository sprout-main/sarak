<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlacePhoto;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class PlacePhotoReorderAndMapDeletionTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        Storage::fake('public');
    }

    private function createPhoto(Place $place, int $order = 0, bool $isCover = false): PlacePhoto
    {
        $path = 'photos/'.$place->id.'/'.uniqid('photo_', true).'.jpg';
        Storage::disk('public')->put($path, 'fake_image_content');

        return PlacePhoto::create([
            'place_id' => $place->id,
            'path' => $path,
            'uploader_id' => null,
            'is_cover' => $isCover,
            'sort_order' => $order,
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_reorder_place_photos(): void
    {
        $admin = $this->adminUser();
        $place = Place::factory()->create();

        $photo1 = $this->createPhoto($place, 0, false);
        $photo2 = $this->createPhoto($place, 1, false);
        $photo3 = $this->createPhoto($place, 2, false);

        // Reorder photo3 to first, photo1 to second, photo2 to third
        $response = $this->actingAs($admin)->patchJson("/api/admin/places/{$place->id}/photos/reorder", [
            'photo_ids' => [$photo3->id, $photo1->id, $photo2->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Photos reordered successfully.')
            ->assertJsonCount(3, 'data.photo_ids');

        $this->assertDatabaseHas('place_photos', ['id' => $photo3->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('place_photos', ['id' => $photo1->id, 'sort_order' => 2]);
        $this->assertDatabaseHas('place_photos', ['id' => $photo2->id, 'sort_order' => 3]);

        // PlaceAdminController index endpoint returns photos sorted with sort_order
        $adminIndex = $this->actingAs($admin)->getJson("/api/admin/places/{$place->id}/photos");
        $adminIndex->assertOk();
        $this->assertSame($photo3->id, $adminIndex->json('data.0.id'));
        $this->assertSame(1, $adminIndex->json('data.0.sort_order'));
        $this->assertSame($photo1->id, $adminIndex->json('data.1.id'));
        $this->assertSame(2, $adminIndex->json('data.1.sort_order'));
        $this->assertSame($photo2->id, $adminIndex->json('data.2.id'));
        $this->assertSame(3, $adminIndex->json('data.2.sort_order'));
    }

    public function test_non_admin_cannot_reorder_photos(): void
    {
        $resident = $this->residentUser();
        $place = Place::factory()->create();
        $photo = $this->createPhoto($place, 0);

        $this->actingAs($resident)->patchJson("/api/admin/places/{$place->id}/photos/reorder", [
            'photo_ids' => [$photo->id],
        ])->assertForbidden();

        $guestRes = $this->patchJson("/api/admin/places/{$place->id}/photos/reorder", [
            'photo_ids' => [$photo->id],
        ]);
        $this->assertTrue(in_array($guestRes->status(), [401, 403]));
    }

    public function test_reorder_validates_photo_ids(): void
    {
        $admin = $this->adminUser();
        $place = Place::factory()->create();
        $otherPlace = Place::factory()->create();

        $photoOther = $this->createPhoto($otherPlace, 0);

        // Attempting to supply a photo belonging to another place should fail validation
        $this->actingAs($admin)->patchJson("/api/admin/places/{$place->id}/photos/reorder", [
            'photo_ids' => [$photoOther->id],
        ])->assertStatus(422);

        // Empty array fails validation
        $this->actingAs($admin)->patchJson("/api/admin/places/{$place->id}/photos/reorder", [
            'photo_ids' => [],
        ])->assertStatus(422);
    }

    public function test_cover_photo_always_precedes_non_cover_photos_regardless_of_sort_order(): void
    {
        $place = Place::factory()->create();

        $photoA = $this->createPhoto($place, 0, false);
        $photoCover = $this->createPhoto($place, 5, true);
        $photoB = $this->createPhoto($place, 1, false);

        $freshPhotos = $place->fresh()->photos;
        $this->assertSame($photoCover->id, $freshPhotos->first()->id);
    }

    public function test_admin_can_delete_photo_via_admin_api_endpoint(): void
    {
        $admin = $this->adminUser();
        $place = Place::factory()->create();
        $photo = $this->createPhoto($place, 0, false);

        Storage::disk('public')->assertExists($photo->path);

        $response = $this->actingAs($admin)->deleteJson("/api/admin/places/{$place->id}/photos/{$photo->id}");
        $response->assertOk();

        $this->assertDatabaseMissing('place_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing($photo->path);
    }

    public function test_karma_is_only_awarded_upon_place_submission_and_approval(): void
    {
        $contributor = $this->residentUser();
        $admin = $this->adminUser();

        $this->assertSame(0, $contributor->karma_points);

        // 1. Submitting place gives 0 karma initially
        $response = $this->actingAs($contributor)->postJson('/api/places', [
            'name' => 'Abra Heritage Center',
            'category_id' => $this->category('grocery')->id,
            'lat' => 17.599,
            'lng' => 120.620,
        ])->assertStatus(201);

        $queueId = $response->json('data.queue_id');
        $this->assertNotNull($queueId);

        $contributor->refresh();
        $this->assertSame(0, $contributor->karma_points);

        // 2. Approving submission awards exactly 20 karma
        $this->actingAs($admin)->postJson("/api/admin/queue/{$queueId}/approve")->assertOk();

        $contributor->refresh();
        $this->assertSame(20, $contributor->karma_points);

        // 3. Place created directly by admin without pending state awards 0 karma
        $this->actingAs($admin)->postJson('/api/admin/places', [
            'name' => 'Municipal Plaza',
            'category_id' => $this->category('grocery')->id,
            'lat' => 17.600,
            'lng' => 120.621,
            'status' => 'approved',
        ])->assertStatus(201);

        $contributor->refresh();
        $this->assertSame(20, $contributor->karma_points);
    }
}
