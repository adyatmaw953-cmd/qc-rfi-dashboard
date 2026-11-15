<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ModuleAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTestDatabase();
        $this->withoutVite();
    }

    public function test_sidebar_and_dashboard_cards_match_each_role(): void
    {
        $expectedModules = [
            'admin' => ['rfi', 'qc-finding', 'ncr', 'mdr-checklist'],
            'production' => ['rfi', 'qc-finding'],
            'qc_qa' => ['rfi', 'qc-finding', 'ncr', 'mdr-checklist'],
            'warehouse' => ['rfi', 'qc-finding'],
            'ppic' => ['rfi', 'qc-finding'],
        ];

        foreach ($expectedModules as $role => $modulePaths) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

            $response->assertSee('Navigasi utama')
                ->assertSee('Navigasi mobile')
                ->assertSee('aria-current="page"', false)
                ->assertSee('brand-carousel-track')
                ->assertSee('PT. MUDALAYA ENERGY<br>INDONESIA', false)
                ->assertSee('PT. MUTIARA GLOBAL<br>INDUSTRY', false)
                ->assertSee('animation: brand-carousel-slide 6s ease-in-out infinite', false)
                ->assertSee('transform: translateY(-2.5em)', false)
                ->assertSee('Dashboard')
                ->assertSee($user->name);

            foreach (['rfi', 'qc-finding', 'ncr', 'mdr-checklist'] as $path) {
                $link = 'href="'.url("/{$path}").'"';

                if (in_array($path, $modulePaths, true)) {
                    $response->assertSee($link, false);
                } else {
                    $response->assertDontSee($link, false);
                }
            }

            $this->post(route('logout'));
        }
    }

    public function test_rfi_categories_are_limited_to_authorized_roles_and_admin_sees_all(): void
    {
        $allowedCategories = [
            'admin' => ['A', 'B', 'C', 'D', 'E'],
            'qc_qa' => ['A', 'B', 'C', 'D', 'E'],
            'production' => ['B', 'C', 'D'],
            'warehouse' => ['A'],
            'ppic' => ['E'],
        ];

        foreach ($allowedCategories as $role => $categories) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get(route('rfi.index'))->assertOk();

            foreach (['A', 'B', 'C', 'D', 'E'] as $category) {
                $link = 'href="'.route('rfi.category', ['kategori' => $category]).'"';

                if (in_array($category, $categories, true)) {
                    $response->assertSee($link, false);
                    $this->get(route('rfi.category', ['kategori' => $category]))->assertOk();
                } else {
                    $response->assertDontSee($link, false);
                    $this->get(route('rfi.category', ['kategori' => $category]))->assertForbidden();
                }
            }

            $this->post(route('logout'));
        }
    }

    public function test_production_cannot_open_ncr(): void
    {
        $production = User::factory()->create(['role' => 'production']);

        $this->actingAs($production)->get(route('ncr.index'))->assertForbidden();
    }

    public function test_unknown_role_cannot_open_role_protected_pages(): void
    {
        $unknown = User::factory()->create(['role' => 'unknown']);

        $this->actingAs($unknown)->get(route('dashboard'))->assertForbidden();
    }

    public function test_all_new_module_pages_render_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['rfi.index', 'qc-finding.index', 'ncr.index', 'mdr-checklist.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_sidebar_shows_initials_avatar_and_role_badge_without_profile_photo(): void
    {
        $admin = User::factory()->create([
            'name' => 'Administrator User',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        $response->assertSee('Administrator User')
            ->assertSee('Super Admin')
            ->assertSee('AU', false)
            ->assertSee('h-10 w-10 min-w-10 shrink-0 aspect-square', false)
            ->assertSee('rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-[#26501F]', false);
    }

    public function test_all_new_pages_render_for_authorized_roles(): void
    {
        $cases = [
            ['path' => '/dashboard', 'role' => 'admin'],
            ['path' => '/rfi', 'role' => 'production'],
            ['path' => '/rfi/A', 'role' => 'warehouse'],
            ['path' => '/qc-finding', 'role' => 'production'],
            ['path' => '/ncr', 'role' => 'qc_qa'],
            ['path' => '/mdr-checklist', 'role' => 'qc_qa'],
        ];

        foreach ($cases as $case) {
            $user = User::factory()->create(['role' => $case['role']]);
            $this->actingAs($user)
                ->get($case['path'])
                ->assertOk();
        }
    }

    public function test_legacy_routes_return_404_not_found(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $legacyPaths = [
            '/produksi',
            '/qc',
            '/mdr',
            '/material-incoming',
            '/admin',
        ];

        foreach ($legacyPaths as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertNotFound();
        }
    }
}
