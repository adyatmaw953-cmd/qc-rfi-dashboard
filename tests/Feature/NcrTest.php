<?php

namespace Tests\Feature;

use App\Models\Ncr;
use App\Models\Project;
use App\Models\User;
use App\Services\NcrDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NcrTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create([
            'project_code' => 'PRJ-NCR-01',
            'project_name' => 'NCR Test Project',
            'customer' => 'Customer A',
        ]);
    }

    public function test_qc_and_admin_can_access_ncr_pages_and_see_dashboard_widget(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $qc = User::factory()->create(['role' => 'qc_qa']);

        foreach ([$admin, $qc] as $user) {
            $this->actingAs($user)->get(route('ncr.index'))->assertOk();
            $this->actingAs($user)->get(route('ncr.create'))->assertOk();

            // Tab Ringkasan displays NCR summary card & chart
            $dashboardRes = $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $dashboardRes->assertSee('NCR')
                ->assertSee('Total');

            // Tab NCR displays full Dashboard NCR widgets and Total NCR
            $ncrTabRes = $this->actingAs($user)->get(route('dashboard', ['tab' => 'ncr']))->assertOk();
            $ncrTabRes->assertSee('Dashboard NCR')
                ->assertSee('Total NCR');
        }
    }

    public function test_production_warehouse_ppic_cannot_access_ncr_pages_and_cannot_see_ncr_on_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ncr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'type' => 'Project',
            'project_code' => $this->project->project_code,
            'problem_description' => 'UnikDeskripsiNCRSecret',
        ]);

        $roles = ['production', 'warehouse', 'ppic'];

        foreach ($roles as $role) {
            $user = User::factory()->create(['role' => $role]);

            // All NCR endpoints are forbidden
            $this->actingAs($user)->get(route('ncr.index'))->assertForbidden();
            $this->actingAs($user)->get(route('ncr.create'))->assertForbidden();
            $this->actingAs($user)->post(route('ncr.store'), [])->assertForbidden();
            $this->actingAs($user)->get(route('ncr.show', $ncr))->assertForbidden();
            $this->actingAs($user)->get(route('ncr.edit', $ncr))->assertForbidden();
            $this->actingAs($user)->put(route('ncr.update', $ncr), [])->assertForbidden();
            $this->actingAs($user)->delete(route('ncr.destroy', $ncr))->assertForbidden();

            // Main dashboard should NOT contain NCR elements
            $dashboardRes = $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $dashboardRes->assertDontSee('NCR')
                ->assertDontSee('Dashboard NCR')
                ->assertDontSee('Tren Jumlah NCR per Bulan')
                ->assertDontSee('chart-ncr-monthly-summary')
                ->assertDontSee('UnikDeskripsiNCRSecret');

            // Accessing ?tab=ncr or ?ncr_year must not expose NCR data
            $ncrTabRes = $this->actingAs($user)->get(route('dashboard', ['tab' => 'ncr', 'ncr_year' => 2026]))->assertOk();
            $ncrTabRes->assertDontSee('Dashboard NCR')
                ->assertDontSee('Total NCR')
                ->assertDontSee('chart-ncr-monthly-summary')
                ->assertDontSee('NCR');
        }
    }

    public function test_admin_and_qc_can_soft_delete_ncr_while_other_roles_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $qc = User::factory()->create(['role' => 'qc_qa']);

        $ncrForAdmin = Ncr::factory()->create([
            'created_by' => $admin->id,
            'type' => 'Project',
            'project_code' => $this->project->project_code,
        ]);

        $ncrForQc = Ncr::factory()->create([
            'created_by' => $admin->id,
            'type' => 'Project',
            'project_code' => $this->project->project_code,
        ]);

        // Unauthorized roles cannot delete
        foreach (['production', 'warehouse', 'ppic'] as $role) {
            $otherUser = User::factory()->create(['role' => $role]);
            $this->actingAs($otherUser)->delete(route('ncr.destroy', $ncrForAdmin))->assertForbidden();
            $this->assertDatabaseHas('ncrs', ['id' => $ncrForAdmin->id, 'deleted_at' => null]);
        }

        // Admin can delete
        $this->actingAs($admin)->delete(route('ncr.destroy', $ncrForAdmin))
            ->assertRedirect(route('ncr.index'));
        $this->assertSoftDeleted('ncrs', ['id' => $ncrForAdmin->id]);

        // QC can delete
        $this->actingAs($qc)->delete(route('ncr.destroy', $ncrForQc))
            ->assertRedirect(route('ncr.index'));
        $this->assertSoftDeleted('ncrs', ['id' => $ncrForQc->id]);
    }

    public function test_ncr_dashboard_service_not_called_for_unauthorized_roles(): void
    {
        $serviceSpy = $this->spy(NcrDashboardService::class);

        foreach (['production', 'warehouse', 'ppic'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('dashboard', ['tab' => 'ncr', 'ncr_year' => 2026]))->assertOk();
        }

        $serviceSpy->shouldNotReceive('getDashboardData');
    }

    public function test_validation_project_type_requires_project_code_and_internal_prohibits_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Type Project without project_code => validation error
        $res1 = $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2026-03-01',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Project',
            'project_code' => '',
            'responsible_pic' => 'Budi',
            'problem_description' => 'Masalah pengelasan',
            'root_cause' => 'Human error',
            'root_cause_category' => ['Man'],
            'status' => 'Open',
        ]);
        $res1->assertSessionHasErrors('project_code');

        // Type Internal with project_code => validation error
        $res2 = $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2026-03-01',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Internal',
            'project_code' => $this->project->project_code,
            'responsible_pic' => 'Budi',
            'problem_description' => 'Masalah pengelasan',
            'root_cause' => 'Human error',
            'root_cause_category' => ['Man'],
            'status' => 'Open',
        ]);
        $res2->assertSessionHasErrors('project_code');

        // Type Internal with empty project_code => success
        $res3 = $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2026-03-01',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Internal',
            'project_code' => '',
            'responsible_pic' => 'Budi',
            'problem_description' => 'Masalah internal office',
            'root_cause' => 'Kabel rusak',
            'root_cause_category' => ['Machine'],
            'status' => 'Open',
        ]);
        $res3->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ncrs', [
            'type' => 'Internal',
            'project_code' => null,
            'responsible_pic' => 'Budi',
        ]);
    }

    public function test_validation_date_rules_and_status_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 1. ca_closed_date before finding_date => error
        $res1 = $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2026-04-10',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Internal',
            'responsible_pic' => 'Joko',
            'problem_description' => 'Deskripsi',
            'root_cause' => 'Penyebab',
            'root_cause_category' => ['Method'],
            'ca_closed_date' => '2026-04-05',
            'status' => 'Open',
        ]);
        $res1->assertSessionHasErrors('ca_closed_date');

        // 2. Status 'CA Closed' requires ca_closed_date
        $res2 = $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2026-04-10',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Internal',
            'responsible_pic' => 'Joko',
            'problem_description' => 'Deskripsi',
            'root_cause' => 'Penyebab',
            'root_cause_category' => ['Method'],
            'status' => 'CA Closed',
        ]);
        $res2->assertSessionHasErrors('ca_closed_date');

        // 3. Status 'Closed' requires ca_closed_date
        $res3 = $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2026-04-10',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Internal',
            'responsible_pic' => 'Joko',
            'problem_description' => 'Deskripsi',
            'root_cause' => 'Penyebab',
            'root_cause_category' => ['Method'],
            'status' => 'Closed',
        ]);
        $res3->assertSessionHasErrors('ca_closed_date');

        // 4. Status 'Closed' with preventive_action requires pa_closed_date
        $res4 = $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2026-04-10',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Internal',
            'responsible_pic' => 'Joko',
            'problem_description' => 'Deskripsi',
            'root_cause' => 'Penyebab',
            'root_cause_category' => ['Method'],
            'ca_closed_date' => '2026-04-12',
            'preventive_action' => 'Training operator',
            'status' => 'Closed',
        ]);
        $res4->assertSessionHasErrors('pa_closed_date');

        // 5. Valid Closed with CA and PA dates
        $res5 = $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2026-04-10',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Internal',
            'responsible_pic' => 'Joko',
            'problem_description' => 'Deskripsi',
            'root_cause' => 'Penyebab',
            'root_cause_category' => ['Method'],
            'ca_closed_date' => '2026-04-12',
            'preventive_action' => 'Training operator',
            'pa_closed_date' => '2026-04-15',
            'status' => 'Closed',
        ]);
        $res5->assertSessionHasNoErrors();
    }

    public function test_ncr_number_increments_and_resets_per_year(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // First NCR in 2026
        $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2026-01-10',
            'department' => 'Engineering',
            'area_finding' => 'Process Engineering',
            'level' => 'Minor',
            'type' => 'Internal',
            'responsible_pic' => 'PIC 1',
            'problem_description' => 'Prob 1',
            'root_cause' => 'Cause 1',
            'root_cause_category' => ['Method'],
            'status' => 'Open',
        ])->assertSessionHasNoErrors();

        // Second NCR in 2026
        $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2026-02-15',
            'department' => 'Subcon',
            'area_finding' => 'Process Fabrikasi',
            'level' => 'Major',
            'type' => 'Internal',
            'responsible_pic' => 'PIC 2',
            'problem_description' => 'Prob 2',
            'root_cause' => 'Cause 2',
            'root_cause_category' => ['Man'],
            'status' => 'Open',
        ])->assertSessionHasNoErrors();

        // First NCR in 2027
        $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2027-01-05',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Near Miss',
            'type' => 'Internal',
            'responsible_pic' => 'PIC 3',
            'problem_description' => 'Prob 3',
            'root_cause' => 'Cause 3',
            'root_cause_category' => ['Machine'],
            'status' => 'Open',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ncrs', ['ncr_number' => 'NCR-2026-001']);
        $this->assertDatabaseHas('ncrs', ['ncr_number' => 'NCR-2026-002']);
        $this->assertDatabaseHas('ncrs', ['ncr_number' => 'NCR-2027-001']);
    }

    public function test_time_leads_is_null_when_ca_closed_date_empty_and_computed_when_filled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $ncrOpen = Ncr::factory()->create([
            'created_by' => $admin->id,
            'finding_date' => '2026-05-01',
            'ca_closed_date' => null,
            'status' => 'Open',
        ]);

        $this->assertNull($ncrOpen->time_leads);

        $ncrClosed = Ncr::factory()->create([
            'created_by' => $admin->id,
            'finding_date' => '2026-05-01',
            'ca_closed_date' => '2026-05-11',
            'status' => 'Closed',
        ]);

        $this->assertSame(10, $ncrClosed->time_leads);
    }

    public function test_nc_cost_input_sanitizes_formatted_numbers_to_integer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2026-06-01',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Internal',
            'responsible_pic' => 'PIC Cost',
            'problem_description' => 'Deskripsi cost',
            'root_cause' => 'Penyebab cost',
            'root_cause_category' => ['Material'],
            'nc_cost' => '83.922.729',
            'status' => 'Open',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ncrs', [
            'responsible_pic' => 'PIC Cost',
            'nc_cost' => 83922729,
        ]);
    }

    public function test_ncr_dashboard_service_aggregations_and_tie_break(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create fixtures for year 2026:
        // 4 NCRs total in 2026
        // 1 Open, 1 CA Closed (Open = 2), 2 Closed
        // 2 Major, 1 Minor, 1 Near Miss
        // Departments: 2 Engineering, 1 Subcon, 1 Produksi (High Dept: Engineering (2))
        // Root Cause: 2 Man, 2 Method (High Root Cause tie: "Man / Method (2)")
        // Costs: 10.000.000 in Feb, 20.000.000 in Feb, 5.000.000 in May, 0 in May => Total: 35.000.000
        Ncr::factory()->create([
            'created_by' => $admin->id,
            'finding_date' => '2026-02-01',
            'department' => 'Engineering',
            'root_cause_category' => ['Man'],
            'level' => 'Major',
            'status' => 'Open',
            'nc_cost' => 10000000,
        ]);

        Ncr::factory()->create([
            'created_by' => $admin->id,
            'finding_date' => '2026-02-15',
            'department' => 'Engineering',
            'root_cause_category' => ['Method'],
            'level' => 'Major',
            'status' => 'CA Closed',
            'ca_closed_date' => '2026-02-20',
            'nc_cost' => 20000000,
        ]);

        Ncr::factory()->create([
            'created_by' => $admin->id,
            'finding_date' => '2026-05-10',
            'department' => 'Subcon',
            'root_cause_category' => ['Man'],
            'level' => 'Minor',
            'status' => 'Closed',
            'ca_closed_date' => '2026-05-15',
            'nc_cost' => 5000000,
        ]);

        Ncr::factory()->create([
            'created_by' => $admin->id,
            'finding_date' => '2026-05-20',
            'department' => 'Produksi',
            'root_cause_category' => ['Method'],
            'level' => 'Near Miss',
            'status' => 'Closed',
            'ca_closed_date' => '2026-05-25',
            'nc_cost' => 0,
        ]);

        // NCR in another year (2025) should NOT be counted in 2026 dashboard
        Ncr::factory()->create([
            'created_by' => $admin->id,
            'finding_date' => '2025-11-01',
            'department' => 'HRGA',
            'root_cause_category' => ['Machine'],
            'level' => 'Major',
            'status' => 'Open',
            'nc_cost' => 50000000,
        ]);

        $service = new NcrDashboardService;
        $data = $service->getDashboardData(2026);

        // Check cards
        $this->assertSame(4, $data['cards']['totalNcr']);
        $this->assertSame(2, $data['cards']['openNcr']); // 1 Open + 1 CA Closed
        $this->assertSame(2, $data['cards']['closedNcr']);
        $this->assertSame(35000000, $data['cards']['totalNcCost']);
        $this->assertSame('Rp 35.000.000', $data['cards']['totalNcCostFormatted']);
        $this->assertSame(2, $data['cards']['totalMajorNcr']);
        $this->assertSame('Engineering (2)', $data['cards']['highDepartment']);
        $this->assertSame('Man / Method (2)', $data['cards']['highRootCauseCategory']);

        // Check 12 months (Januari = 0, Februari = 2, Mei = 2, others = 0)
        $this->assertCount(12, $data['charts']['monthlyTrend']['data']);
        $this->assertSame(0, $data['charts']['monthlyTrend']['data'][0]); // Januari
        $this->assertSame(2, $data['charts']['monthlyTrend']['data'][1]); // Februari
        $this->assertSame(0, $data['charts']['monthlyTrend']['data'][2]); // Maret
        $this->assertSame(0, $data['charts']['monthlyTrend']['data'][3]); // April
        $this->assertSame(2, $data['charts']['monthlyTrend']['data'][4]); // Mei
        $this->assertSame(0, $data['charts']['monthlyTrend']['data'][11]); // Desember

        // Check monthly cost trend
        $this->assertSame(30000000, $data['charts']['monthlyCostTrend']['data'][1]); // Februari
        $this->assertSame(5000000, $data['charts']['monthlyCostTrend']['data'][4]); // Mei
    }

    public function test_updating_status_from_open_directly_to_closed_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ncr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'finding_date' => '2026-04-10',
            'status' => 'Open',
            'type' => 'Internal',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'responsible_pic' => 'Joko',
            'problem_description' => 'Deskripsi',
            'root_cause' => 'Penyebab',
            'root_cause_category' => ['Method'],
        ]);

        $res = $this->actingAs($admin)->put(route('ncr.update', $ncr), [
            'finding_date' => '2026-04-10',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Internal',
            'responsible_pic' => 'Joko',
            'problem_description' => 'Deskripsi',
            'root_cause' => 'Penyebab',
            'root_cause_category' => ['Method'],
            'ca_closed_date' => '2026-04-12',
            'preventive_action' => 'Training operator',
            'pa_closed_date' => '2026-04-15',
            'status' => 'Closed',
        ]);

        $res->assertSessionHasErrors('status');
        $this->assertSame('Open', $ncr->fresh()->status);
    }

    public function test_updating_status_from_open_to_ca_closed_then_to_closed_is_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ncr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'finding_date' => '2026-04-10',
            'status' => 'Open',
            'type' => 'Internal',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'responsible_pic' => 'Joko',
            'problem_description' => 'Deskripsi',
            'root_cause' => 'Penyebab',
            'root_cause_category' => ['Method'],
        ]);

        // 1. Open -> CA Closed
        $res1 = $this->actingAs($admin)->put(route('ncr.update', $ncr), [
            'finding_date' => '2026-04-10',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Internal',
            'responsible_pic' => 'Joko',
            'problem_description' => 'Deskripsi',
            'root_cause' => 'Penyebab',
            'root_cause_category' => ['Method'],
            'ca_closed_date' => '2026-04-12',
            'status' => 'CA Closed',
        ]);
        $res1->assertSessionHasNoErrors();
        $this->assertSame('CA Closed', $ncr->fresh()->status);

        // 2. CA Closed -> Closed
        $res2 = $this->actingAs($admin)->put(route('ncr.update', $ncr), [
            'finding_date' => '2026-04-10',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Internal',
            'responsible_pic' => 'Joko',
            'problem_description' => 'Deskripsi',
            'root_cause' => 'Penyebab',
            'root_cause_category' => ['Method'],
            'ca_closed_date' => '2026-04-12',
            'preventive_action' => 'Training operator',
            'pa_closed_date' => '2026-04-15',
            'status' => 'Closed',
        ]);
        $res2->assertSessionHasNoErrors();
        $this->assertSame('Closed', $ncr->fresh()->status);
    }

    public function test_editing_closed_ncr_is_forbidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $qc = User::factory()->create(['role' => 'qc_qa']);

        $ncr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'finding_date' => '2026-04-10',
            'status' => 'Closed',
            'ca_closed_date' => '2026-04-12',
            'pa_closed_date' => '2026-04-15',
            'preventive_action' => 'Training',
        ]);

        foreach ([$admin, $qc] as $user) {
            $this->actingAs($user)->get(route('ncr.edit', $ncr))->assertForbidden();
            $this->actingAs($user)->put(route('ncr.update', $ncr), [
                'status' => 'Closed',
            ])->assertForbidden();
        }
    }

    public function test_admin_can_still_delete_closed_ncr(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ncr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'finding_date' => '2026-04-10',
            'status' => 'Closed',
            'ca_closed_date' => '2026-04-12',
            'pa_closed_date' => '2026-04-15',
            'preventive_action' => 'Training',
        ]);

        $res = $this->actingAs($admin)->delete(route('ncr.destroy', $ncr));
        $res->assertRedirect();
        $this->assertSoftDeleted('ncrs', ['id' => $ncr->id]);
    }

    public function test_index_returns_status_counts_for_all_statuses(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Ncr::factory()->create(['created_by' => $admin->id, 'status' => 'Open', 'type' => 'Internal']);
        Ncr::factory()->create(['created_by' => $admin->id, 'status' => 'Open', 'type' => 'Internal']);
        Ncr::factory()->create(['created_by' => $admin->id, 'status' => 'CA Closed', 'type' => 'Internal', 'ca_closed_date' => '2026-01-10']);
        Ncr::factory()->create(['created_by' => $admin->id, 'status' => 'Closed', 'type' => 'Internal', 'ca_closed_date' => '2026-01-10', 'pa_closed_date' => '2026-01-15', 'preventive_action' => 'Training']);

        $res = $this->actingAs($admin)->get(route('ncr.index'));
        $res->assertOk();

        $view = $res->original->getData();
        $counts = $view['statusCounts'];

        $this->assertSame(4, $counts['Semua']);
        $this->assertSame(2, $counts['Open']);
        $this->assertSame(1, $counts['CA Closed']);
        $this->assertSame(1, $counts['Closed']);
    }

    public function test_status_counts_respect_other_filters_but_not_status_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Ncr::factory()->create(['created_by' => $admin->id, 'status' => 'Open', 'type' => 'Internal', 'level' => 'Major']);
        Ncr::factory()->create(['created_by' => $admin->id, 'status' => 'Closed', 'type' => 'Internal', 'level' => 'Major', 'ca_closed_date' => '2026-01-10', 'pa_closed_date' => '2026-01-15', 'preventive_action' => 'Training']);
        Ncr::factory()->create(['created_by' => $admin->id, 'status' => 'Open', 'type' => 'Internal', 'level' => 'Minor']);

        // Filter by level=Major; status filter is empty — counts should only cover Major NCRs
        $res = $this->actingAs($admin)->get(route('ncr.index', ['level' => 'Major']));
        $res->assertOk();

        $counts = $res->original->getData()['statusCounts'];
        $this->assertSame(2, $counts['Semua']);
        $this->assertSame(1, $counts['Open']);
        $this->assertSame(1, $counts['Closed']);
        $this->assertSame(0, $counts['CA Closed']);
    }

    public function test_search_combined_with_status_filter_and_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create 2 Open NCRs with distinct responsible_pic
        Ncr::factory()->create(['created_by' => $admin->id, 'status' => 'Open', 'type' => 'Internal', 'responsible_pic' => 'Agus Setiawan']);
        Ncr::factory()->create(['created_by' => $admin->id, 'status' => 'Open', 'type' => 'Internal', 'responsible_pic' => 'Budi Santoso']);
        Ncr::factory()->create(['created_by' => $admin->id, 'status' => 'CA Closed', 'type' => 'Internal', 'responsible_pic' => 'Agus Setiawan', 'ca_closed_date' => '2026-01-10']);

        $res = $this->actingAs($admin)->get(route('ncr.index', [
            'status' => 'Open',
            'search' => 'Agus',
        ]));
        $res->assertOk();

        $ncrs = $res->original->getData()['ncrs'];
        $this->assertCount(1, $ncrs);
        $this->assertSame('Open', $ncrs->first()->status);
        $this->assertStringContainsString('Agus', $ncrs->first()->responsible_pic);
    }

    public function test_empty_state_shows_correct_message_with_and_without_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // No NCRs at all => "Belum ada NCR" message
        $res = $this->actingAs($admin)->get(route('ncr.index'));
        $res->assertOk()->assertSee('Belum ada NCR');

        // With a filter that yields no results => "Tidak ada NCR yang cocok"
        $res2 = $this->actingAs($admin)->get(route('ncr.index', ['search' => 'xyzabcnotexist']));
        $res2->assertOk()->assertSee('Tidak ada NCR yang cocok');
    }

    public function test_edit_action_visible_only_for_allowed_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $qc = User::factory()->create(['role' => 'qc_qa']);

        $ncr = Ncr::factory()->create(['created_by' => $admin->id, 'status' => 'Open', 'type' => 'Internal']);

        // Admin sees Edit link
        $this->actingAs($admin)->get(route('ncr.index'))->assertSee('Edit');

        // QC sees Edit link
        $this->actingAs($qc)->get(route('ncr.index'))->assertSee('Edit');
    }

    public function test_delete_action_visible_for_admin_and_qc(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $qc = User::factory()->create(['role' => 'qc_qa']);

        Ncr::factory()->create(['created_by' => $admin->id, 'status' => 'Open', 'type' => 'Internal']);

        // Admin sees Hapus button
        $this->actingAs($admin)->get(route('ncr.index'))->assertSee('Hapus');

        // QC also sees Hapus button
        $this->actingAs($qc)->get(route('ncr.index'))->assertSee('Hapus');
    }

    public function test_show_renders_empty_fields_as_belum_diisi(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $ncr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'status' => 'Open',
            'type' => 'Internal',
            'project_code' => null,
            'customer' => null,
            'corrective_action' => null,
            'pic_ca' => null,
            'ca_closed_date' => null,
            'preventive_action' => null,
            'pic_pa' => null,
            'pa_closed_date' => null,
            'related_departments' => null,
        ]);

        $res = $this->actingAs($admin)->get(route('ncr.show', $ncr));
        $res->assertOk();
        $res->assertSee('Belum diisi');
        $res->assertSee('— (Internal)');
    }

    public function test_show_stepper_renders_correctly_for_each_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 1. Open
        $openNcr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'status' => 'Open',
            'finding_date' => '2026-05-10',
            'type' => 'Internal',
        ]);
        $resOpen = $this->actingAs($admin)->get(route('ncr.show', $openNcr));
        $resOpen->assertOk()
            ->assertSee('Dibuka 10 Mei 2026')
            ->assertSee('Menunggu tindakan koreksi')
            ->assertSee('Menunggu tindakan pencegahan')
            ->assertSee('Sekarang');

        // 2. CA Closed
        $caNcr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'status' => 'CA Closed',
            'finding_date' => '2026-05-10',
            'ca_closed_date' => '2026-05-15',
            'type' => 'Internal',
        ]);
        $resCa = $this->actingAs($admin)->get(route('ncr.show', $caNcr));
        $resCa->assertOk()
            ->assertSee('Dibuka 10 Mei 2026')
            ->assertSee('Koreksi selesai 15 Mei 2026')
            ->assertSee('Menunggu tindakan pencegahan');

        // 3. Closed
        $closedNcr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'status' => 'Closed',
            'finding_date' => '2026-05-10',
            'ca_closed_date' => '2026-05-15',
            'pa_closed_date' => '2026-05-20',
            'preventive_action' => 'Training operator',
            'type' => 'Internal',
        ]);
        $resClosed = $this->actingAs($admin)->get(route('ncr.show', $closedNcr));
        $resClosed->assertOk()
            ->assertSee('Dibuka 10 Mei 2026')
            ->assertSee('Koreksi selesai 15 Mei 2026')
            ->assertSee('20 Mei 2026');
    }

    public function test_show_edit_and_delete_buttons_respect_policy_per_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $qc = User::factory()->create(['role' => 'qc_qa']);

        $openNcr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'status' => 'Open',
            'type' => 'Internal',
        ]);

        $closedNcr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'status' => 'Closed',
            'ca_closed_date' => '2026-04-12',
            'pa_closed_date' => '2026-04-15',
            'preventive_action' => 'Training',
            'type' => 'Internal',
        ]);

        // Admin on Open: sees Edit NCR and Hapus
        $this->actingAs($admin)->get(route('ncr.show', $openNcr))
            ->assertSee('Edit NCR')
            ->assertSee('Hapus');

        // QC on Open: sees Edit NCR and Hapus
        $this->actingAs($qc)->get(route('ncr.show', $openNcr))
            ->assertSee('Edit NCR')
            ->assertSee('Hapus');

        // Admin on Closed: cannot edit, but can delete
        $this->actingAs($admin)->get(route('ncr.show', $closedNcr))
            ->assertDontSee('Edit NCR')
            ->assertSee('Hapus');

        // QC on Closed: cannot edit, but can delete
        $this->actingAs($qc)->get(route('ncr.show', $closedNcr))
            ->assertDontSee('Edit NCR')
            ->assertSee('Hapus');
    }

    public function test_create_and_edit_forms_render_sections_and_anchors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ncr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'status' => 'Open',
            'type' => 'Project',
            'project_code' => $this->project->project_code,
            'responsible_pic' => 'Siti Aminah',
        ]);

        // Create form
        $createRes = $this->actingAs($admin)->get(route('ncr.create'));
        $createRes->assertOk()
            ->assertSee('Buat NCR')
            ->assertSee('PT Mudalaya Indo')
            ->assertSee('#s1')
            ->assertSee('#s2')
            ->assertSee('#s3')
            ->assertSee('#s4')
            ->assertSee('#s5')
            ->assertSee('Simpan NCR');

        // Edit form
        $editRes = $this->actingAs($admin)->get(route('ncr.edit', $ncr));
        $editRes->assertOk()
            ->assertSee('Edit NCR')
            ->assertSee('Perbarui NCR')
            ->assertSee('Siti Aminah')
            ->assertSee($ncr->ncr_number);
    }

    public function test_form_displays_old_input_and_validation_errors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Post invalid data and follow redirect
        $res = $this->actingAs($admin)
            ->from(route('ncr.create'))
            ->followingRedirects()
            ->post(route('ncr.store'), [
                'finding_date' => '',
                'responsible_pic' => 'Budi Santoso',
                'type' => 'Internal',
            ]);

        $res->assertOk()
            ->assertSee('Periksa kembali isian berikut:')
            ->assertSee('role="alert"', false)
            ->assertSee('Budi Santoso');
    }

    public function test_form_project_vs_internal_type_rendering(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create with Project type default
        $resDefault = $this->actingAs($admin)->get(route('ncr.create'));
        $resDefault->assertOk()
            ->assertSee('Terisi otomatis dari project, tetap bisa diubah');

        // Edit an Internal NCR
        $internalNcr = Ncr::factory()->create([
            'created_by' => $admin->id,
            'status' => 'Open',
            'type' => 'Internal',
            'project_code' => null,
        ]);
        $resInternal = $this->actingAs($admin)->get(route('ncr.edit', $internalNcr));
        $resInternal->assertOk()
            ->assertSee('display: none;', false)
            ->assertSee('Tipe Internal tidak memakai project');
    }

    public function test_create_and_edit_save_records_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Store Project NCR
        $createData = [
            'finding_date' => '2026-06-01',
            'department' => 'Produksi',
            'area_finding' => 'Process Produksi',
            'level' => 'Major',
            'type' => 'Project',
            'project_code' => $this->project->project_code,
            'customer' => 'Customer A',
            'responsible_pic' => 'Ahmad Subarjo',
            'problem_description' => 'Keretakan pada plat baja',
            'root_cause_category' => ['Material'],
            'root_cause' => 'Bahan baku dari vendor mengalami cacat mikroskopis',
            'corrective_action' => 'Ganti plat baru',
            'pic_ca' => 'Bambang',
            'status' => 'Open',
            'nc_cost' => '1.500.000',
        ];

        $storeRes = $this->actingAs($admin)->post(route('ncr.store'), $createData);
        $storeRes->assertRedirect();

        $created = Ncr::where('responsible_pic', 'Ahmad Subarjo')->first();
        $this->assertNotNull($created);
        $this->assertSame(1500000, $created->nc_cost);
        $this->assertSame('Project', $created->type);

        // Update NCR
        $updateRes = $this->actingAs($admin)->put(route('ncr.update', $created), array_merge($createData, [
            'responsible_pic' => 'Ahmad Subarjo Updated',
            'status' => 'CA Closed',
            'ca_closed_date' => '2026-06-05',
        ]));
        $updateRes->assertRedirect(route('ncr.show', $created));

        $this->assertSame('Ahmad Subarjo Updated', $created->fresh()->responsible_pic);
        $this->assertSame('CA Closed', $created->fresh()->status);
    }

    public function test_form_accepts_manual_area_finding_and_multiple_root_causes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $res = $this->actingAs($admin)->post(route('ncr.store'), [
            'finding_date' => '2026-06-01',
            'department' => 'Produksi',
            'area_finding' => 'Area Custom Manual 123',
            'level' => 'Minor',
            'type' => 'Internal',
            'responsible_pic' => 'PIC Manual Area',
            'problem_description' => 'Deskripsi masalah',
            'root_cause' => 'Penyebab multi 4M',
            'root_cause_category' => ['Man', 'Method', 'Machine'],
            'status' => 'Open',
        ]);

        $res->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ncrs', [
            'responsible_pic' => 'PIC Manual Area',
            'area_finding' => 'Area Custom Manual 123',
        ]);

        $ncr = Ncr::where('responsible_pic', 'PIC Manual Area')->first();
        $this->assertSame(['Man', 'Method', 'Machine'], $ncr->root_cause_category);

        // Verify detail page displays all categories
        $showRes = $this->actingAs($admin)->get(route('ncr.show', $ncr));
        $showRes->assertOk()
            ->assertSee('Area Custom Manual 123')
            ->assertSee('Man')
            ->assertSee('Method')
            ->assertSee('Machine');
    }

    public function test_ncr_form_renders_status_ncr_below_nc_cost(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('ncr.create'));
        $response->assertOk();

        $content = $response->getContent();
        $ncCostPos = strpos($content, 'id="nc_cost"');
        $statusPos = strpos($content, 'Status NCR');

        $this->assertNotFalse($ncCostPos);
        $this->assertNotFalse($statusPos);
        $this->assertTrue($statusPos > $ncCostPos, 'Status NCR should be positioned after NC Cost');
    }
}
