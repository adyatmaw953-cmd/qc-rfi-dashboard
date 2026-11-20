<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use App\Notifications\RfiSubmitted;
use App\Services\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTestDatabase();
        $this->withoutVite();
    }

    public function test_non_admin_cannot_access_user_management_and_receives_403(): void
    {
        $nonAdminRoles = ['production', 'qc_qa', 'warehouse', 'ppic'];
        $targetUser = User::factory()->create(['role' => 'production']);

        foreach ($nonAdminRoles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('users.index'))->assertStatus(403);
            $this->actingAs($user)->get(route('users.create'))->assertStatus(403);
            $this->actingAs($user)->post(route('users.store'), [
                'name' => 'Test',
                'email' => 'test@example.com',
                'role' => 'qc_qa',
                'password' => 'secret1234',
            ])->assertStatus(403);
            $this->actingAs($user)->get(route('users.edit', $targetUser))->assertStatus(403);
            $this->actingAs($user)->put(route('users.update', $targetUser), [
                'name' => 'Updated',
                'email' => $targetUser->email,
                'role' => 'qc_qa',
            ])->assertStatus(403);
            $this->actingAs($user)->patch(route('users.toggle-status', $targetUser))->assertStatus(403);

            // User management menu does NOT appear in sidebar for non-admin
            $dashboard = $this->actingAs($user)->get(route('dashboard'));
            $dashboard->assertDontSee(route('users.index'), false);
        }

        // Admin can access and sees the menu in sidebar
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $adminDashboard = $this->actingAs($admin)->get(route('dashboard'));
        $adminDashboard->assertSee(route('users.index'), false);
        $adminDashboard->assertSee('Kelola Akun');
    }

    public function test_admin_can_view_user_list_with_search_and_filters_and_pagination(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Utama',
            'email' => 'admin.utama@example.com',
            'role' => 'admin',
        ]);

        $budi = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'role' => 'production',
            'is_active' => true,
        ]);

        $citra = User::factory()->create([
            'name' => 'Citra Dewi',
            'email' => 'citra@example.com',
            'role' => 'qc_qa',
            'is_active' => false,
        ]);

        // Default list shows all
        $response = $this->actingAs($admin)->get(route('users.index'));
        $response->assertOk();
        $response->assertSee('Admin Utama')
            ->assertSee('Budi Santoso')
            ->assertSee('Citra Dewi')
            ->assertSee('Super Admin')
            ->assertSee('Production')
            ->assertSee('QC / QA')
            ->assertSee('Aktif')
            ->assertSee('Nonaktif');

        // Search by name
        $searchResponse = $this->actingAs($admin)->get(route('users.index', ['search' => 'Budi']));
        $searchResponse->assertSee('Budi Santoso')
            ->assertDontSee('Citra Dewi');

        // Search by email
        $searchEmailResponse = $this->actingAs($admin)->get(route('users.index', ['search' => 'citra@example.com']));
        $searchEmailResponse->assertSee('Citra Dewi')
            ->assertDontSee('Budi Santoso');

        // Filter by role
        $roleResponse = $this->actingAs($admin)->get(route('users.index', ['role' => 'qc_qa']));
        $roleResponse->assertSee('Citra Dewi')
            ->assertDontSee('Budi Santoso');

        // Filter by status active
        $activeResponse = $this->actingAs($admin)->get(route('users.index', ['status' => 'active']));
        $activeResponse->assertSee('Budi Santoso')
            ->assertDontSee('Citra Dewi');

        // Filter by status inactive
        $inactiveResponse = $this->actingAs($admin)->get(route('users.index', ['status' => 'inactive']));
        $inactiveResponse->assertSee('Citra Dewi')
            ->assertDontSee('Budi Santoso');

        // Empty state when no matches
        $emptyResponse = $this->actingAs($admin)->get(route('users.index', ['search' => 'ZZZ999NONEXISTENT']));
        $emptyResponse->assertSee('Tidak ada akun yang cocok');
        $emptyResponse->assertSee('Reset filter');

        // Pagination 15 items per page
        User::factory()->count(20)->create();
        $pageResponse = $this->actingAs($admin)->get(route('users.index'));
        $pageResponse->assertSee('Menampilkan', false);
    }

    public function test_admin_can_create_user_and_new_user_can_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Operator Baru',
            'email' => 'operator.baru@example.com',
            'role' => 'production',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Operator Baru',
            'email' => 'operator.baru@example.com',
            'role' => 'production',
            'is_active' => true,
        ]);

        $newUser = User::where('email', 'operator.baru@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $newUser->password));

        // New user can login
        $this->post(route('logout'));
        $loginResponse = $this->post(route('login.attempt'), [
            'email' => 'operator.baru@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($newUser);
    }

    public function test_create_user_validates_unique_email_and_valid_role_and_min_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['email' => 'existing@example.com']);

        // Duplicate email
        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Baru',
            'email' => 'existing@example.com',
            'role' => 'production',
            'password' => 'secret1234',
        ])->assertSessionHasErrors(['email']);

        // Invalid role
        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Baru',
            'email' => 'valid@example.com',
            'role' => 'superman',
            'password' => 'secret1234',
        ])->assertSessionHasErrors(['role']);

        // Password less than 8 chars
        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Baru',
            'email' => 'valid@example.com',
            'role' => 'production',
            'password' => '1234567',
        ])->assertSessionHasErrors(['password']);
    }

    public function test_admin_can_edit_user_role_and_reset_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'name' => 'Akun Lama',
            'email' => 'lama@example.com',
            'role' => 'production',
            'password' => 'oldpassword123',
        ]);

        // Edit role without changing password
        $response = $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Akun Diperbarui',
            'email' => 'lama@example.com',
            'role' => 'warehouse',
            'password' => '',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Akun Diperbarui', $user->name);
        $this->assertSame('warehouse', $user->role);
        $this->assertTrue(Hash::check('oldpassword123', $user->password));

        // Edit with password reset
        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Akun Diperbarui',
            'email' => 'lama@example.com',
            'role' => 'warehouse',
            'password' => 'newpassword456',
        ])->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword456', $user->password));
    }

    public function test_admin_cannot_demote_own_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'production',
        ]);

        $response->assertSessionHasErrors(['role']);
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_admin_cannot_demote_the_last_active_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $secondAdmin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // When 2 active admins exist, second admin can be demoted
        $this->actingAs($admin)->put(route('users.update', $secondAdmin), [
            'name' => $secondAdmin->name,
            'email' => $secondAdmin->email,
            'role' => 'qc_qa',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('qc_qa', $secondAdmin->fresh()->role);

        // Now only $admin is left as active admin. Trying to demote must fail.
        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'qc_qa',
        ])->assertSessionHasErrors(['role']);

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->patch(route('users.toggle-status', $admin));

        $response->assertSessionHasErrors(['status']);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_the_last_active_admin(): void
    {
        $admin1 = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $admin2 = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // Can deactivate admin2 when admin1 is still active
        $this->actingAs($admin1)->patch(route('users.toggle-status', $admin2))
            ->assertSessionHas('success');
        $this->assertFalse($admin2->fresh()->is_active);

        // Now only admin1 is active. Trying to deactivate admin1 must fail
        $this->actingAs($admin1)->patch(route('users.toggle-status', $admin1))
            ->assertSessionHasErrors(['status']);
        $this->assertTrue($admin1->fresh()->is_active);

        // Reactivating admin2 succeeds
        $this->actingAs($admin1)->patch(route('users.toggle-status', $admin2))
            ->assertSessionHas('success');
        $this->assertTrue($admin2->fresh()->is_active);
    }

    public function test_inactive_account_cannot_login_and_gets_generic_error(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'role' => 'production',
            'password' => 'secret1234',
            'is_active' => false,
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => 'inactive@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_inactive_user_session_is_rejected_on_subsequent_request_and_redirected_to_login(): void
    {
        $user = User::factory()->create([
            'role' => 'production',
            'is_active' => true,
        ]);

        // User is logged in
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        // Admin deactivates user
        $user->update(['is_active' => false]);

        // User makes subsequent request
        $subsequentResponse = $this->actingAs($user)->get(route('dashboard'));
        $subsequentResponse->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_account_does_not_receive_daily_reminders(): void
    {
        $now = Carbon::parse('2026-11-04 08:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        $activeProduction = User::factory()->create(['role' => 'production', 'is_active' => true]);
        $inactiveProduction = User::factory()->create(['role' => 'production', 'is_active' => false]);

        $project = Project::factory()->create();
        $rfi = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B']);

        QcFinding::factory()->create([
            'rfi_id' => $rfi->id,
            'status' => 'open',
            'due_date' => '2026-11-04',
        ]);

        $this->artisan('reminders:send-daily')->assertSuccessful();

        $this->assertSame(1, $activeProduction->notifications()->count());
        $this->assertSame(0, $inactiveProduction->notifications()->count());
    }

    public function test_inactive_account_does_not_receive_notifications(): void
    {
        Notification::fake();

        $actor = User::factory()->create(['role' => 'production']);
        $activeQc = User::factory()->create(['role' => 'qc_qa', 'is_active' => true]);
        $inactiveQc = User::factory()->create(['role' => 'qc_qa', 'is_active' => false]);

        $project = Project::factory()->create();
        $rfi = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'created_by' => $actor->id,
        ]);

        $dispatcher = app(NotificationDispatcher::class);
        $dispatcher->rfiSubmitted($rfi, $actor);

        Notification::assertSentTo($activeQc, RfiSubmitted::class);
        Notification::assertNotSentTo($inactiveQc, RfiSubmitted::class);
    }

    public function test_change_password_feature_for_authenticated_users(): void
    {
        $user = User::factory()->create([
            'role' => 'production',
            'password' => 'oldpassword123',
        ]);

        // Render page
        $this->actingAs($user)->get(route('password.edit'))->assertOk()
            ->assertSee('Ganti Kata Sandi')
            ->assertSee('Kata Sandi Saat Ini')
            ->assertSee('Kata Sandi Baru')
            ->assertSee('Konfirmasi Kata Sandi Baru');

        // Wrong old password
        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertSessionHasErrors(['current_password']);

        // Password confirmation mismatch
        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'oldpassword123',
            'password' => 'newpassword123',
            'password_confirmation' => 'mismatch123',
        ])->assertSessionHasErrors(['password']);

        // Password less than 8 characters
        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'oldpassword123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['password']);

        // Successful password change
        $response = $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'oldpassword123',
            'password' => 'newsecretpass88',
            'password_confirmation' => 'newsecretpass88',
        ]);

        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('newsecretpass88', $user->fresh()->password));

        // Can login with new password
        $this->post(route('logout'));
        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'newsecretpass88',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_existing_accounts_remain_active_after_migration(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->fresh()->is_active);
    }
}
