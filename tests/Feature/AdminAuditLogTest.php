<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Place;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_guest_cannot_access_audit_logs(): void
    {
        $this->getJson('/api/admin/audit-logs')->assertUnauthorized();
        $this->get('/admin/audit-logs')->assertRedirect('/login');
    }

    public function test_standard_user_cannot_access_audit_logs(): void
    {
        $resident = $this->residentUser();

        $this->actingAs($resident)
            ->getJson('/api/admin/audit-logs')
            ->assertForbidden();

        $this->actingAs($resident)
            ->get('/admin/audit-logs')
            ->assertForbidden();
    }

    public function test_admin_can_access_audit_log_page(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->get('/admin/audit-logs')
            ->assertOk();

        $response->assertSee('Audit Logs');
        $response->assertSee('adminAuditLogs');
    }

    public function test_admin_can_list_audit_logs_with_pagination(): void
    {
        $admin = $this->adminUser();
        $auditService = app(AuditService::class);

        for ($i = 1; $i <= 30; $i++) {
            $auditService->record('place', $i, 'create', $admin->id, ['name' => "Place {$i}"]);
        }

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/audit-logs?per_page=10&page=1')
            ->assertOk();

        $json = $response->json();
        $this->assertCount(10, $json['data']);
        $this->assertSame(30, $json['meta']['total']);
        $this->assertSame(3, $json['meta']['last_page']);
        $this->assertSame(1, $json['meta']['current_page']);
        $this->assertSame($admin->name, $json['data'][0]['actor']['name']);
        $this->assertContains('place', $json['filters']['models']);
        $this->assertContains('create', $json['filters']['actions']);
    }

    public function test_admin_can_filter_audit_logs_by_model_and_action(): void
    {
        $admin = $this->adminUser();
        $auditService = app(AuditService::class);

        $auditService->record('place', 101, 'create', $admin->id, ['name' => 'Cafe Alpha']);
        $auditService->record('place', 101, 'update', $admin->id, ['name' => 'Cafe Alpha Updated']);
        $auditService->record('user', 5, 'role_change', $admin->id, ['before' => 'user', 'after' => 'admin']);

        // Filter by model = user
        $responseUser = $this->actingAs($admin)
            ->getJson('/api/admin/audit-logs?model=user')
            ->assertOk();

        $userData = $responseUser->json('data');
        $this->assertCount(1, $userData);
        $this->assertSame('user', $userData[0]['model']);
        $this->assertSame('role_change', $userData[0]['action']);

        // Filter by action = update
        $responseUpdate = $this->actingAs($admin)
            ->getJson('/api/admin/audit-logs?action=update')
            ->assertOk();

        $updateData = $responseUpdate->json('data');
        $this->assertCount(1, $updateData);
        $this->assertSame('update', $updateData[0]['action']);
        $this->assertSame('Cafe Alpha Updated', $updateData[0]['diff']['name']);
    }

    public function test_admin_can_search_audit_logs(): void
    {
        $admin = $this->adminUser();
        $auditService = app(AuditService::class);

        $auditService->record('place', 201, 'delete', $admin->id, ['name' => 'Mysterious Secret Spot']);
        $auditService->record('place', 202, 'create', $admin->id, ['name' => 'Regular Mart']);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/audit-logs?q=Mysterious')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(201, (int) $data[0]['model_id']);
        $this->assertSame('delete', $data[0]['action']);
    }
}
