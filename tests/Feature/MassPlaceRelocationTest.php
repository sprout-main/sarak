<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class MassPlaceRelocationTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_head_admin_can_mass_relocate_multiple_places_in_batch(): void
    {
        $headAdmin = User::factory()->create([
            'email' => 'admin@bangued.ph',
            'role' => UserRole::Admin,
        ]);

        $placeA = Place::factory()->create([
            'name' => 'Bangued Town Plaza Kiosk',
            'category_id' => $this->category('coffee_shop')->id,
            'lat' => 17.595000,
            'lng' => 120.618000,
        ]);

        $placeB = Place::factory()->create([
            'name' => 'Abra Provincial Pharmacy',
            'category_id' => $this->category('pharmacy')->id,
            'lat' => 17.596000,
            'lng' => 120.619000,
        ]);

        $payload = [
            'moves' => [
                [
                    'id' => $placeA->id,
                    'lat' => 17.595500,
                    'lng' => 120.618500,
                ],
                [
                    'id' => $placeB->id,
                    'lat' => 17.596800,
                    'lng' => 120.619800,
                ],
            ],
        ];

        $response = $this->actingAs($headAdmin)->postJson('/api/admin/places/mass-relocate', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'updated_count' => 2,
            ],
        ]);

        $placeA->refresh();
        $placeB->refresh();

        $this->assertEqualsWithDelta(17.595500, (float) $placeA->lat, 0.00001);
        $this->assertEqualsWithDelta(120.618500, (float) $placeA->lng, 0.00001);
        $this->assertEqualsWithDelta(17.596800, (float) $placeB->lat, 0.00001);
        $this->assertEqualsWithDelta(120.619800, (float) $placeB->lng, 0.00001);

        $this->assertDatabaseHas('audit_log', [
            'model' => 'place',
            'model_id' => $placeA->id,
            'action' => 'mass_move',
            'actor_id' => $headAdmin->id,
        ]);

        $this->assertDatabaseHas('audit_log', [
            'model' => 'place',
            'model_id' => $placeB->id,
            'action' => 'mass_move',
            'actor_id' => $headAdmin->id,
        ]);
    }

    public function test_regular_admin_cannot_mass_relocate_places(): void
    {
        $regularAdmin = User::factory()->create([
            'email' => 'mod@bangued.ph',
            'role' => UserRole::Admin,
        ]);

        $place = Place::factory()->create([
            'category_id' => $this->category('grocery')->id,
            'lat' => 17.595000,
            'lng' => 120.618000,
        ]);

        $payload = [
            'moves' => [
                [
                    'id' => $place->id,
                    'lat' => 17.595200,
                    'lng' => 120.618200,
                ],
            ],
        ];

        $response = $this->actingAs($regularAdmin)->postJson('/api/admin/places/mass-relocate', $payload);

        $response->assertStatus(403);

        $place->refresh();
        $this->assertEqualsWithDelta(17.595000, (float) $place->lat, 0.00001);
    }

    public function test_resident_cannot_mass_relocate_places(): void
    {
        $resident = $this->residentUser();

        $place = Place::factory()->create([
            'category_id' => $this->category('grocery')->id,
            'lat' => 17.595000,
            'lng' => 120.618000,
        ]);

        $payload = [
            'moves' => [
                [
                    'id' => $place->id,
                    'lat' => 17.595200,
                    'lng' => 120.618200,
                ],
            ],
        ];

        $response = $this->actingAs($resident)->postJson('/api/admin/places/mass-relocate', $payload);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_mass_relocate_places(): void
    {
        $payload = [
            'moves' => [
                [
                    'id' => 1,
                    'lat' => 17.595200,
                    'lng' => 120.618200,
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/places/mass-relocate', $payload);

        $response->assertStatus(401);
    }

    public function test_mass_relocate_validates_moves_payload(): void
    {
        $headAdmin = User::factory()->create([
            'email' => 'admin@bangued.ph',
            'role' => UserRole::Admin,
        ]);

        $response = $this->actingAs($headAdmin)->postJson('/api/admin/places/mass-relocate', [
            'moves' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['moves']);

        $responseInvalidCoords = $this->actingAs($headAdmin)->postJson('/api/admin/places/mass-relocate', [
            'moves' => [
                [
                    'id' => 999999,
                    'lat' => 120.0, // Invalid latitude (> 90)
                    'lng' => 200.0, // Invalid longitude (> 180)
                ],
            ],
        ]);

        $responseInvalidCoords->assertStatus(422);
        $responseInvalidCoords->assertJsonValidationErrors(['moves.0.id', 'moves.0.lat', 'moves.0.lng']);
    }

    public function test_map_ui_removes_mass_drag_controls_from_map_view(): void
    {
        $guestResponse = $this->get('/');
        $guestResponse->assertStatus(200);
        $guestResponse->assertSee('window.SarakIsHeadAdmin = false', false);
        $guestResponse->assertDontSee('Mass Drag Pins');

        $regularAdmin = User::factory()->create([
            'email' => 'regular@bangued.ph',
            'role' => UserRole::Admin,
        ]);

        $adminResponse = $this->actingAs($regularAdmin)->get('/');
        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('window.SarakIsHeadAdmin = false', false);
        $adminResponse->assertDontSee('Mass Drag Pins');

        $adminPlacesResponse = $this->actingAs($regularAdmin)->get('/admin/places');
        $adminPlacesResponse->assertStatus(200);
        $adminPlacesResponse->assertDontSee('Mass Relocate Pins');

        $headAdmin = User::factory()->create([
            'email' => 'admin@bangued.ph',
            'role' => UserRole::Admin,
        ]);

        $headAdminResponse = $this->actingAs($headAdmin)->get('/');
        $headAdminResponse->assertStatus(200);
        $headAdminResponse->assertSee('window.SarakIsHeadAdmin = true', false);
        $headAdminResponse->assertDontSee('Mass Drag Pins');
        $headAdminResponse->assertDontSee('Relocated Pins');

        $headAdminPlacesResponse = $this->actingAs($headAdmin)->get('/admin/places');
        $headAdminPlacesResponse->assertStatus(200);
        $headAdminPlacesResponse->assertDontSee('Mass Relocate Pins');
        $headAdminPlacesResponse->assertDontSee('/?mass_drag=1');
    }
}

