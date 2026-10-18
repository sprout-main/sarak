<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CoreCatalog;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use CoreCatalog, RefreshDatabase;

    public function test_non_admin_cannot_access_accounts_management(): void
    {
        $resident = $this->residentUser();

        $this->actingAs($resident)
            ->get('/admin/users')
            ->assertForbidden();

        $this->actingAs($resident)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_admin_can_view_accounts_page_and_query_users(): void
    {
        $admin = $this->adminUser();
        $user1 = User::factory()->create(['name' => 'Alice Walker', 'email' => 'alice@example.com', 'role' => 'user']);
        $user2 = User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.com', 'role' => 'user']);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Accounts');

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/users?q=Alice')
            ->assertOk();

        $response->assertJsonCount(1, 'data');
        $this->assertSame('Alice Walker', $response->json('data.0.name'));
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $admin = $this->adminUser();
        User::factory()->create(['name' => 'Regular User', 'role' => 'user']);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/users?role=admin')
            ->assertOk();

        $this->assertTrue(collect($response->json('data'))->every(fn ($u) => $u['role'] === 'admin'));
    }

    public function test_only_head_admin_can_update_user_role(): void
    {
        $deputyAdmin = $this->adminUser();
        $target = User::factory()->create(['role' => 'user']);

        // Deputy admin is blocked (403 Forbidden)
        $this->actingAs($deputyAdmin)
            ->patchJson('/api/admin/users/' . $target->id . '/role', [
                'role' => 'admin',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only the head administrator can promote or demote user accounts.');

        // Head Admin has full authority
        $headAdmin = User::factory()->create([
            'email' => 'admin@bangued.ph',
            'role' => 'admin',
        ]);

        $this->actingAs($headAdmin)
            ->patchJson('/api/admin/users/' . $target->id . '/role', [
                'role' => 'admin',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->assertSame('admin', $target->fresh()->role->value ?? $target->fresh()->role);
    }

    public function test_administrators_cannot_edit_their_own_role(): void
    {
        $rootAdmin = User::factory()->create([
            'email' => 'admin@bangued.ph',
            'role' => 'admin',
        ]);

        // admin@bangued.ph cannot edit own role
        $this->actingAs($rootAdmin)
            ->patchJson('/api/admin/users/' . $rootAdmin->id . '/role', [
                'role' => 'user',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Administrators cannot edit their own role.');

        $this->assertSame('admin', $rootAdmin->fresh()->role->value ?? $rootAdmin->fresh()->role);

        // Any other admin also CANNOT edit their own role
        $otherAdmin = User::factory()->create([
            'email' => 'otheradmin@example.com',
            'role' => 'admin',
        ]);

        $this->actingAs($otherAdmin)
            ->patchJson('/api/admin/users/' . $otherAdmin->id . '/role', [
                'role' => 'user',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Administrators cannot edit their own role.');

        $this->assertSame('admin', $otherAdmin->fresh()->role->value ?? $otherAdmin->fresh()->role);
    }

    public function test_head_admin_role_cannot_be_altered_or_deleted_by_other_admins(): void
    {
        $rootAdmin = User::factory()->create([
            'email' => 'admin@bangued.ph',
            'role' => 'admin',
        ]);

        $otherAdmin = User::factory()->create([
            'email' => 'deputy@example.com',
            'role' => 'admin',
        ]);

        // otherAdmin attempts to demote rootAdmin
        $this->actingAs($otherAdmin)
            ->patchJson('/api/admin/users/' . $rootAdmin->id . '/role', [
                'role' => 'user',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The head administrator role cannot be altered.');

        // otherAdmin attempts to delete rootAdmin
        $this->actingAs($otherAdmin)
            ->deleteJson('/api/admin/users/' . $rootAdmin->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'The head administrator account cannot be deleted.');

        // rootAdmin CAN update otherAdmin's role
        $this->actingAs($rootAdmin)
            ->patchJson('/api/admin/users/' . $otherAdmin->id . '/role', [
                'role' => 'user',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'user');
    }

    public function test_admin_can_delete_user_account(): void
    {
        $admin = $this->adminUser();
        $target = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)
            ->deleteJson('/api/admin/users/' . $target->id)
            ->assertOk()
            ->assertJsonPath('data.message', 'User account deleted successfully.');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->deleteJson('/api/admin/users/' . $admin->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot delete your own account from administration.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
