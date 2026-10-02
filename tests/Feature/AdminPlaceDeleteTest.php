<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Place;
use App\Models\PlaceHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class AdminPlaceDeleteTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_admin_can_delete_place_permanently(): void
    {
        $admin = $this->adminUser();
        $place = Place::factory()->create([
            'name' => 'Bangued Heritage Cafe',
            'category_id' => $this->category('coffee_shop')->id,
        ]);

        PlaceHour::create([
            'place_id' => $place->id,
            'day' => 1,
            'opens' => '08:00',
            'closes' => '17:00',
            'is_closed' => false,
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/admin/places/{$place->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $place->id,
            ],
        ]);

        $this->assertDatabaseMissing('places', [
            'id' => $place->id,
        ]);

        $this->assertDatabaseMissing('place_hours', [
            'place_id' => $place->id,
        ]);

        $this->assertDatabaseHas('audit_log', [
            'model' => 'place',
            'model_id' => $place->id,
            'action' => 'delete',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_resident_cannot_delete_place(): void
    {
        $resident = $this->residentUser();
        $place = Place::factory()->create([
            'name' => 'Bangued Quick Bites',
            'category_id' => $this->category('fast_food')->id,
        ]);

        $response = $this->actingAs($resident)->deleteJson("/api/admin/places/{$place->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('places', [
            'id' => $place->id,
        ]);
    }

    public function test_guest_cannot_delete_place(): void
    {
        $place = Place::factory()->create([
            'name' => 'Bangued General Hospital',
            'category_id' => $this->category('hospital')->id,
        ]);

        $response = $this->deleteJson("/api/admin/places/{$place->id}");

        $response->assertStatus(401);
        $this->assertDatabaseHas('places', [
            'id' => $place->id,
        ]);
    }
}
