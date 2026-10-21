<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    #[Test]
    public function login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('SkinCare POS');
        $response->assertSee('Sign In to POS');
    }

    #[Test]
    public function unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/pos')->assertRedirect('/login');
        $this->get('/products')->assertRedirect('/login');
        $this->get('/users')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
    }

    #[Test]
    public function users_can_authenticate_with_valid_credentials(): void
    {
        $user = User::where('role', 'admin')->first();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Admin@SkinCare2026',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');
    }

    #[Test]
    public function users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::where('role', 'admin')->first();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'WrongPassword123',
        ]);

        $this->assertGuest();
    }

    #[Test]
    public function deactivated_users_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'role' => 'cashier',
            'status' => 'inactive',
            'password' => Hash::make('Secret123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors(['email']);
    }

    #[Test]
    public function user_deactivated_mid_session_is_denied_access(): void
    {
        $user = User::factory()->create([
            'role' => 'cashier',
            'status' => 'active',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->actingAs($user);
        $this->get('/pos')->assertStatus(200);

        // Deactivate user in database
        $user->update(['status' => 'inactive']);

        // Next request must force logout and redirect to login
        $response = $this->get('/pos');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    #[Test]
    public function cashier_can_access_pos_but_forbidden_from_admin_and_management_routes(): void
    {
        $cashier = User::where('role', 'cashier')->first();
        $this->actingAs($cashier);

        // Permitted
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/pos')->assertStatus(200);

        // Forbidden on server
        $this->get('/products')->assertStatus(403);
        $this->get('/inventory')->assertStatus(403);
        $this->get('/reports')->assertStatus(403);
        $this->get('/users')->assertStatus(403);
        $this->get('/settings')->assertStatus(403);
    }

    #[Test]
    public function manager_can_access_operations_and_reports_but_forbidden_from_admin(): void
    {
        $manager = User::where('role', 'manager')->first();
        $this->actingAs($manager);

        // Permitted
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/pos')->assertStatus(200);
        $this->get('/products')->assertStatus(200);
        $this->get('/inventory')->assertStatus(200);
        $this->get('/reports')->assertStatus(200);

        // Forbidden on server
        $this->get('/users')->assertStatus(403);
        $this->get('/settings')->assertStatus(403);
    }

    #[Test]
    public function administrator_has_full_access_to_all_modules(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin);

        $this->get('/dashboard')->assertStatus(200);
        $this->get('/pos')->assertStatus(200);
        $this->get('/products')->assertStatus(200);
        $this->get('/inventory')->assertStatus(200);
        $this->get('/reports')->assertStatus(200);
        $this->get('/users')->assertStatus(200);
        $this->get('/settings')->assertStatus(200);
    }

    #[Test]
    public function authenticated_user_can_update_profile_details(): void
    {
        $user = User::where('role', 'cashier')->first();
        $this->actingAs($user);

        $response = $this->patch('/profile', [
            'name' => 'Updated Cashier Name',
            'email' => 'cashier.updated@skincare.com',
        ]);

        $response->assertRedirect('/profile');
        $user->refresh();
        $this->assertEquals('Updated Cashier Name', $user->name);
        $this->assertEquals('cashier.updated@skincare.com', $user->email);
    }

    #[Test]
    public function authenticated_user_can_change_password_with_valid_current_password(): void
    {
        $user = User::factory()->create([
            'role' => 'cashier',
            'status' => 'active',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($user);

        $response = $this->put('/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertRedirect('/profile');
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    #[Test]
    public function admin_can_create_and_toggle_activation_status_of_other_users(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin);

        // 1. Create a new user
        $response = $this->post('/users', [
            'name' => 'New Staff Member',
            'email' => 'newstaff@skincare.com',
            'role' => 'cashier',
            'status' => 'active',
            'password' => 'StaffPassword123!',
        ]);

        $response->assertRedirect('/users');
        $createdUser = User::where('email', 'newstaff@skincare.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertEquals('active', $createdUser->status);

        // 2. Toggle status to deactivate
        $toggleResponse = $this->patch("/users/{$createdUser->id}/toggle-status");
        $toggleResponse->assertRedirect('/users');
        $createdUser->refresh();
        $this->assertEquals('inactive', $createdUser->status);
    }

    #[Test]
    public function admin_cannot_deactivate_their_own_account(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin);

        $response = $this->patch("/users/{$admin->id}/toggle-status");
        $response->assertRedirect('/users');
        $response->assertSessionHasErrors(['error']);

        $admin->refresh();
        $this->assertEquals('active', $admin->status);
    }

    #[Test]
    public function users_can_log_out(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }
}
