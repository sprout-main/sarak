<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BoundaryArea;
use App\Models\Place;
use App\Models\PlaceReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class SystemRefinementsTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_mariadb_connection_and_safe_collation_configured(): void
    {
        $default = config('database.default');
        $this->assertEquals('mariadb', $default);

        $collation = config('database.connections.mariadb.collation');
        $this->assertEquals('utf8mb4_unicode_ci', $collation);
    }

    public function test_user_controller_role_and_delete_audit_logging(): void
    {
        $headAdmin = User::factory()->create([
            'email' => 'admin@bangued.ph',
            'role' => 'admin',
        ]);
        $admin = $this->adminUser();
        $targetUser = User::factory()->create([
            'role' => 'user',
        ]);

        // Role update (authorized by Head Admin)
        $res = $this->actingAs($headAdmin)->patchJson("/api/admin/users/{$targetUser->id}/role", [
            'role' => 'admin',
        ]);
        $res->assertStatus(200);

        $this->assertDatabaseHas('audit_log', [
            'model' => 'user',
            'model_id' => $targetUser->id,
            'action' => 'role_change',
            'actor_id' => $headAdmin->id,
        ]);

        // User delete
        $delRes = $this->actingAs($admin)->deleteJson("/api/admin/users/{$targetUser->id}");
        $delRes->assertStatus(200);

        $this->assertDatabaseHas('audit_log', [
            'model' => 'user',
            'model_id' => $targetUser->id,
            'action' => 'delete',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_report_admin_controller_audit_logging(): void
    {
        $admin = $this->adminUser();
        $place = Place::factory()->create([
            'category_id' => $this->category('grocery')->id,
        ]);

        $report = PlaceReport::create([
            'place_id' => $place->id,
            'issue_type' => 'closed',
            'details' => 'Permanently closed location',
            'status' => 'pending',
        ]);

        // Update status
        $res = $this->actingAs($admin)->patchJson("/api/admin/reports/{$report->id}/status", [
            'status' => 'resolved',
        ]);
        $res->assertStatus(200);

        $this->assertDatabaseHas('audit_log', [
            'model' => 'place_report',
            'model_id' => $report->id,
            'action' => 'status_update',
            'actor_id' => $admin->id,
        ]);

        // Delete report
        $delRes = $this->actingAs($admin)->deleteJson("/api/admin/reports/{$report->id}");
        $delRes->assertStatus(200);

        $this->assertDatabaseHas('audit_log', [
            'model' => 'place_report',
            'model_id' => $report->id,
            'action' => 'delete',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_data_quality_fix_barangay_audit_logging(): void
    {
        $admin = $this->adminUser();

        $res = $this->actingAs($admin)->postJson('/api/admin/quality/barangay/fix', [
            'limit' => 10,
        ]);

        $res->assertStatus(200);

        $this->assertDatabaseHas('audit_log', [
            'model' => 'boundary_area',
            'model_id' => 0,
            'action' => 'batch_fix',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_admin_place_update_status_and_location(): void
    {
        $admin = $this->adminUser();
        $place = Place::factory()->create([
            'category_id' => $this->category('grocery')->id,
            'status' => 'pending',
            'lat' => 17.595000,
            'lng' => 120.615000,
        ]);

        // Admin updates status directly
        $res = $this->actingAs($admin)->patchJson("/api/admin/places/{$place->id}", [
            'status' => 'approved',
        ]);
        $res->assertStatus(200);
        $this->assertEquals('approved', $place->fresh()->status);

        // Admin updates location
        $locRes = $this->actingAs($admin)->patchJson("/api/admin/places/{$place->id}/location", [
            'lat' => 17.598800,
            'lng' => 120.619900,
        ]);
        $locRes->assertStatus(200);
        $fresh = $place->fresh();
        $this->assertEquals(17.598800, (float) $fresh->lat);
        $this->assertEquals(120.619900, (float) $fresh->lng);
    }

    public function test_drop_pin_place_submission_flow(): void
    {
        $user = $this->residentUser();
        $category = $this->category('coffee_shop');
        $barangay = BoundaryArea::first() ?? BoundaryArea::create(['name' => 'Zone 5', 'type' => 'barangay']);

        // Resident submission goes to moderation queue
        $res = $this->actingAs($user)->postJson('/api/places', [
            'name' => 'Kape Ti Abra Pin Drop',
            'category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'lat' => 17.596400,
            'lng' => 120.618200,
            'address' => 'Corner Street',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('mod_queue', [
            'action' => 'create',
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);

        // Admin submission publishes directly to places
        $admin = $this->adminUser();
        $adminRes = $this->actingAs($admin)->postJson('/api/places', [
            'name' => 'Bangued Admin Pinned Spot',
            'category_id' => $category->id,
            'barangay_id' => $barangay->id,
            'lat' => 17.597100,
            'lng' => 120.618500,
            'address' => 'Town Center',
        ]);

        $adminRes->assertStatus(201);
        $this->assertDatabaseHas('places', [
            'name' => 'Bangued Admin Pinned Spot',
            'barangay_id' => $barangay->id,
            'status' => 'approved',
        ]);
    }
}
