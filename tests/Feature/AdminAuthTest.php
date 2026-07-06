<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_admin_home_redirects_to_admin_login(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('admin.login'));
    }

    public function test_guest_admin_login_page_renders(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Admin Login');
    }

    public function test_active_admin_can_login_to_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password_hash' => Hash::make('secret-password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_non_admin_user_cannot_login_to_admin_dashboard(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password_hash' => Hash::make('secret-password'),
            'role' => 'user',
            'status' => 'active',
        ]);

        $this->post('/admin/login', [
            'email' => 'user@example.com',
            'password' => 'secret-password',
        ])
            ->assertSessionHasErrors('email')
            ->assertRedirect();

        $this->assertGuest();
    }
}
