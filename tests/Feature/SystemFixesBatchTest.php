<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class SystemFixesBatchTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_guest_cannot_submit_place_report_feedback(): void
    {
        $place = Place::factory()->create([
            'name' => 'Bangued Heritage Cafe',
            'category_id' => $this->category('coffee_shop')->id,
        ]);

        $response = $this->postJson("/api/places/{$place->id}/reports", [
            'issue_type' => 'wrong_info',
            'details' => 'Operating hours have changed from 8am to 9am.',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_report_captures_user_id(): void
    {
        $user = $this->residentUser();
        $place = Place::factory()->create([
            'name' => 'Bangued Heritage Bakery',
            'category_id' => $this->category('coffee_shop')->id,
        ]);

        $response = $this->actingAs($user)->postJson("/api/places/{$place->id}/reports", [
            'issue_type' => 'closed_permanently',
            'details' => 'Store front is closed down.',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('place_reports', [
            'place_id' => $place->id,
            'issue_type' => 'closed_permanently',
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_view_and_moderate_reports(): void
    {
        $admin = $this->adminUser();
        $place = Place::factory()->create([
            'name' => 'Bangued Hardware Supplies',
            'category_id' => $this->category('coffee_shop')->id,
        ]);

        $report = PlaceReport::create([
            'place_id' => $place->id,
            'issue_type' => 'duplicate',
            'details' => 'Duplicate listing of another store.',
            'status' => 'pending',
        ]);

        // View reports in admin API
        $response = $this->actingAs($admin)->getJson('/api/admin/reports?status=pending');
        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $report->id)
            ->assertJsonPath('data.0.issue_type', 'duplicate');

        // Update status to resolved
        $patchResponse = $this->actingAs($admin)->patchJson("/api/admin/reports/{$report->id}/status", [
            'status' => 'resolved',
            'admin_notes' => 'Merged into primary place listing.',
        ]);

        $patchResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.admin_notes', 'Merged into primary place listing.');

        $this->assertDatabaseHas('place_reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'reviewed_by' => $admin->id,
            'admin_notes' => 'Merged into primary place listing.',
        ]);

        // Delete report
        $deleteResponse = $this->actingAs($admin)->deleteJson("/api/admin/reports/{$report->id}");
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('place_reports', [
            'id' => $report->id,
        ]);
    }

    public function test_forgot_password_screen_contains_back_to_login_link(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertSee('Back to login');
        $response->assertSee(route('login'));
    }

    public function test_register_screen_uses_username_instead_of_full_name(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Username');
        $response->assertDontSee('Full Name');
    }

    public function test_admin_users_view_has_username_header_and_reports_route(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get('/admin/users');
        $response->assertStatus(200);
        $response->assertSee('Username');

        $reportsResponse = $this->actingAs($admin)->get('/admin/reports');
        $reportsResponse->assertStatus(200);
        $reportsResponse->assertSee('Feedback &amp; Reports', false);
    }
}
