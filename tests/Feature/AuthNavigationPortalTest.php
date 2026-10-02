<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthNavigationPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CategorySeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);
    }

    public function test_guest_can_render_landing_map_with_navigation_links(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Log in');
        $response->assertSee('Register');
        $response->assertSee('Bangued');
    }

    public function test_admin_authenticates_and_redirects_to_admin_portal(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@bangued.ph',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_resident_authenticates_and_redirects_to_user_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'resident@bangued.ph',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    public function test_resident_is_forbidden_from_admin_portal(): void
    {
        $resident = User::where('email', 'resident@bangued.ph')->first();

        $response = $this->actingAs($resident)->get('/admin');
        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login_from_portals(): void
    {
        $responseAdmin = $this->get('/admin');
        $responseAdmin->assertRedirect(route('login'));

        $responseDashboard = $this->get('/dashboard');
        $responseDashboard->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_render_rich_dashboard(): void
    {
        $resident = User::where('email', 'resident@bangued.ph')->first();

        $response = $this->actingAs($resident)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Community Portal');
        $response->assertSee('Sarak Map Portal');
        $response->assertSee('Add a Place');
        $response->assertSee('Open Map');
    }

    public function test_new_user_can_register_and_access_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@bangued.ph',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::where('email', 'juan@bangued.ph')->first();
        $this->assertNotNull($user);
        $this->assertEquals(UserRole::User, $user->role);
    }

    public function test_user_can_logout_and_returns_to_landing_map(): void
    {
        $resident = User::where('email', 'resident@bangued.ph')->first();

        $response = $this->actingAs($resident)->post('/logout');
        $response->assertRedirect('/');
        $this->assertGuest();
    }
}
