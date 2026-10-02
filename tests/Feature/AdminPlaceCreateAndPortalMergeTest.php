<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ModQueue;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class AdminPlaceCreateAndPortalMergeTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_admin_accessing_dashboard_redirects_to_admin_console(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_resident_accessing_dashboard_renders_community_portal(): void
    {
        $resident = $this->residentUser();

        $response = $this->actingAs($resident)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Community Portal');
    }

    public function test_admin_console_dashboard_renders_without_contributor_rankings(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Admin Console');
        $response->assertDontSee('Civic Vanguard: Top Contributors');
        $response->assertSee('Submit Place');
    }

    public function test_admin_can_directly_create_and_publish_place(): void
    {
        $admin = $this->adminUser();
        $cat = $this->category('coffee_shop');

        $payload = [
            'name' => 'Bangued Apex Roastery',
            'category_id' => $cat->id,
            'address' => 'MacKee St, Zone 4',
            'lat' => 17.5970,
            'lng' => 120.6185,
            'phone' => '09171234567',
            'website' => 'https://apexroastery.ph',
            'description' => 'Specialty coffee and workspace.',
        ];

        $response = $this->actingAs($admin)->postJson('/api/admin/places', $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Bangued Apex Roastery');

        $placeId = $response->json('data.id');

        $this->assertDatabaseHas('places', [
            'id' => $placeId,
            'name' => 'Bangued Apex Roastery',
            'status' => 'approved',
            'verified_tier' => 'verified',
            'submitted_by' => $admin->id,
            'approved_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_log', [
            'model' => 'place',
            'model_id' => $placeId,
            'action' => 'create',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_resident_submission_enqueues_to_mod_queue_while_admin_bypasses_it(): void
    {
        $resident = $this->residentUser();
        $cat = $this->category('pharmacy');

        $payload = [
            'name' => 'Queued Abra Pharmacy',
            'category_id' => $cat->id,
            'lat' => 17.5950,
            'lng' => 120.6170,
        ];

        $response = $this->actingAs($resident)->postJson('/api/places', $payload);

        $response->assertStatus(201);
        $queueId = $response->json('data.queue_id');

        $this->assertDatabaseHas('mod_queue', [
            'id' => $queueId,
            'action' => 'create',
            'status' => 'pending',
            'requested_by' => $resident->id,
        ]);

        $this->assertDatabaseMissing('places', [
            'name' => 'Queued Abra Pharmacy',
        ]);
    }

    public function test_resident_cannot_access_admin_places_store(): void
    {
        $resident = $this->residentUser();
        $cat = $this->category('grocery');

        $response = $this->actingAs($resident)->postJson('/api/admin/places', [
            'name' => 'Unauthorized Mart',
            'category_id' => $cat->id,
            'lat' => 17.5960,
            'lng' => 120.6190,
        ]);

        $response->assertStatus(403);
    }
}
