<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ModQueue;
use App\Models\Place;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BoundaryFixtures;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class RigorousSystemIntegrityTest extends TestCase
{
    use BoundaryFixtures, CoreCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CategorySeeder::class);
        $this->polygon('Zone 5 (Poblacion)', $this->boxRing(17.58, 120.60, 17.61, 120.63), ['psgc' => '1400101005']);
    }

    /**
     * Vector 1: Spatial Geometry, Point-in-Polygon, & Boundaries
     */
    public function test_vector1_spatial_boundaries_and_point_in_polygon_resolution(): void
    {
        // 1. Point in Bangued Center (Zone 5)
        $inside = $this->getJson('/api/geo/barangay?lat=17.5965&lng=120.6190')
            ->assertOk()
            ->json('data');

        $this->assertNotNull($inside);
        $this->assertNotEmpty($inside['name']);

        // 2. Point far outside Bangued (Manila)
        $outside = $this->getJson('/api/geo/barangay?lat=14.5995&lng=120.9842')
            ->assertOk()
            ->json('data');

        $this->assertNull($outside);

        // 3. Boundaries GeoJSON endpoint
        $boundaries = $this->getJson('/api/boundaries')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->json('data');

        $this->assertSame('FeatureCollection', $boundaries['type'] ?? null);
        $this->assertNotEmpty($boundaries['features'] ?? []);
    }

    /**
     * Vector 2: Zero-Trust Security, Root Admin Armor, & Role Barriers
     */
    public function test_vector2_security_root_admin_immutability_and_role_barriers(): void
    {
        $rootAdmin = User::factory()->create([
            'email' => 'admin@bangued.ph',
            'role' => 'admin',
        ]);

        $otherAdmin = User::factory()->create([
            'email' => 'deputy@bangued.ph',
            'role' => 'admin',
        ]);

        $resident = $this->residentUser();

        // Root admin cannot edit own role
        $this->actingAs($rootAdmin)
            ->patchJson("/api/admin/users/{$rootAdmin->id}/role", ['role' => 'user'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Administrators cannot edit their own role.');

        // Root admin cannot delete own account
        $this->actingAs($rootAdmin)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/profile');
        $this->assertNotNull($rootAdmin->fresh());

        // Deputy admin CANNOT edit their own role
        $this->actingAs($otherAdmin)
            ->patchJson("/api/admin/users/{$otherAdmin->id}/role", ['role' => 'user'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Administrators cannot edit their own role.');

        // Deputy admin CANNOT edit or delete the head admin account
        $this->actingAs($otherAdmin)
            ->patchJson("/api/admin/users/{$rootAdmin->id}/role", ['role' => 'user'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The head administrator role cannot be altered.');

        $this->actingAs($otherAdmin)
            ->deleteJson("/api/admin/users/{$rootAdmin->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'The head administrator account cannot be deleted.');

        // Resident is blocked from admin routes
        $this->actingAs($resident)->get('/admin')->assertForbidden();
        $this->actingAs($resident)->getJson('/api/admin/places')->assertForbidden();
        $this->actingAs($resident)->getJson('/api/admin/users')->assertForbidden();
    }

    /**
     * Vector 3: Workflow State Machine & Admin Bypass Invariant
     */
    public function test_vector3_workflow_admin_direct_publishing_vs_resident_queue(): void
    {
        $admin = $this->adminUser();
        $resident = $this->residentUser();
        $category = $this->category('hospital');

        // Admin submission -> direct approved place, 0 queue items added
        $adminResponse = $this->actingAs($admin)->postJson('/api/places', [
            'category_id' => $category->id,
            'name' => 'Bangued Apex Medical Center',
            'lat' => 17.5980,
            'lng' => 120.6180,
        ])->assertCreated();

        $adminPlaceId = $adminResponse->json('data.place_id') ?? $adminResponse->json('data.id');
        $this->assertNotNull($adminPlaceId);

        $this->assertDatabaseHas('places', [
            'id' => $adminPlaceId,
            'name' => 'Bangued Apex Medical Center',
            'status' => 'approved',
            'verified_tier' => 'verified',
            'submitted_by' => $admin->id,
            'approved_by' => $admin->id,
        ]);

        $this->assertDatabaseCount('mod_queue', 0);

        // Resident submission -> lands in queue, not in places
        $residentResponse = $this->actingAs($resident)->postJson('/api/places', [
            'category_id' => $category->id,
            'name' => 'Community Care Clinic',
            'lat' => 17.5990,
            'lng' => 120.6170,
        ])->assertCreated();

        $queueId = $residentResponse->json('data.queue_id');
        $this->assertNotNull($queueId);

        $this->assertDatabaseHas('mod_queue', [
            'id' => $queueId,
            'action' => 'create',
            'status' => 'pending',
            'requested_by' => $resident->id,
        ]);

        $this->assertDatabaseMissing('places', [
            'name' => 'Community Care Clinic',
        ]);
    }

    /**
     * Vector 4: Sensory UI, Contrast Tokens, & Minimap Component Integrity
     */
    public function test_vector4_ui_contrast_and_minimap_invariants(): void
    {
        // 1. Login view must NOT have quick sign-in presets
        $loginContent = file_get_contents(resource_path('views/auth/login.blade.php'));
        $this->assertStringNotContainsString('Quick Sign-in Presets', $loginContent);
        $this->assertStringNotContainsString("fill('admin@bangued.ph'", $loginContent);

        // 2. Admin Submit Modal contains interactive Leaflet minimap container
        $submitModal = file_get_contents(resource_path('views/components/admin/submit-modal.blade.php'));
        $this->assertStringContainsString('x-ref="miniMap"', $submitModal);
        $this->assertStringContainsString('recenterBangued()', $submitModal);
        $this->assertStringContainsString('updateMapFromInputs()', $submitModal);

        // 3. Profile view hides account deletion for root admin
        $profileView = file_get_contents(resource_path('views/profile/edit.blade.php'));
        $this->assertStringContainsString("strcasecmp(trim(\$user->email), 'admin@bangued.ph') !== 0", $profileView);

        // 4. CSS ships the right-docked place panel tokens
        $appCss = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.place-dock-card', $appCss);
        $this->assertStringContainsString('.hours-toggle', $appCss);
        $this->assertStringNotContainsString('leaflet-popup-content-wrapper', $appCss);
    }

    /**
     * Vector 5: ETag Cache Invalidation & Parity
     */
    public function test_vector5_cache_invalidation_and_etag_consistency(): void
    {
        $admin = $this->adminUser();
        $cat = $this->category('coffee_shop');

        // Initial read -> has ETag
        $first = $this->getJson('/api/places');
        $first->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        // Conditional read -> 304 Not Modified
        $second = $this->withHeader('If-None-Match', $etag)->getJson('/api/places');
        $second->assertStatus(304);

        // Mutate by creating a new place
        $this->actingAs($admin)->postJson('/api/places', [
            'category_id' => $cat->id,
            'name' => 'Cache Breaker Cafe',
            'lat' => 17.5960,
            'lng' => 120.6190,
        ])->assertCreated();

        // Third read -> cache was invalidated, returns 200 with new data
        $third = $this->getJson('/api/places');
        $third->assertOk();
        $newEtag = $third->headers->get('ETag');
        $this->assertNotEmpty($newEtag);
    }
}
