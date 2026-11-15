<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTestDatabase();
    }

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('Masuk')
            ->assertSee('Senang bertemu lagi.')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false)
            ->assertDontSee('name="role"', false)
            ->assertDontSee('admin_code');
    }

    public function test_user_can_authenticate_with_email_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'qc@example.test',
            'role' => 'qc_qa',
            'password' => 'secret123',
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => 'qc@example.test',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_production_login_redirects_to_dashboard(): void
    {
        User::factory()->create([
            'email' => 'production@example.test',
            'role' => 'production',
            'password' => 'secret123',
        ]);

        $this->post(route('login.attempt'), [
            'email' => 'production@example.test',
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_admin_login_redirects_to_dashboard(): void
    {
        User::factory()->create([
            'email' => 'admin@example.test',
            'role' => 'admin',
            'password' => 'secret123',
        ]);

        $this->post(route('login.attempt'), [
            'email' => 'admin@example.test',
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_warehouse_and_ppic_login_redirect_to_dashboard(): void
    {
        foreach (['warehouse', 'ppic'] as $role) {
            $email = "{$role}@example.test";
            User::factory()->create([
                'email' => $email,
                'role' => $role,
                'password' => 'secret123',
            ]);

            $this->post(route('login.attempt'), [
                'email' => $email,
                'password' => 'secret123',
            ])->assertRedirect(route('dashboard'));

            $this->post(route('logout'));
        }
    }

    public function test_unknown_role_login_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'unknown@example.test',
            'role' => 'unknown',
            'password' => 'secret123',
        ]);

        $this->post(route('login.attempt'), [
            'email' => 'unknown@example.test',
            'password' => 'secret123',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected_and_displays_error(): void
    {
        User::factory()->create([
            'email' => 'user@example.test',
            'role' => 'production',
            'password' => 'secret123',
        ]);

        $response = $this->from(route('login'))
            ->post(route('login.attempt'), [
                'email' => 'user@example.test',
                'password' => 'wrong-password',
            ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $followUp = $this->get(route('login'));
        $followUp->assertOk();
        $followUp->assertSee('field-error-msg', false);
    }

    public function test_unknown_email_is_rejected_and_displays_error(): void
    {
        $response = $this->from(route('login'))
            ->post(route('login.attempt'), [
                'email' => 'missing@example.test',
                'password' => 'secret123',
            ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $followUp = $this->get(route('login'));
        $followUp->assertOk();
        $followUp->assertSee('field-error-msg', false);
    }

    public function test_login_route_throttles_repeated_attempts(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $response = $this->post(route('login.attempt'), [
                'email' => 'missing@example.test',
                'password' => 'wrong-password',
            ]);
        }

        $response->assertTooManyRequests();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create(['role' => 'qc_qa']);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
