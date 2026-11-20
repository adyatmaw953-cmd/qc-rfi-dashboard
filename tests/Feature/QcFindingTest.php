<?php

namespace Tests\Feature;

use App\Enums\RfiResult;
use App\Models\FindingActivity;
use App\Models\Project;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QcFindingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTestDatabase();
        $this->withoutVite();
    }

    public function test_non_ok_review_creates_one_finding_with_the_review_result_and_note(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $rfi = Rfi::factory()->create([
            'category' => 'C',
            'status' => 'open',
            'result' => null,
            'qc_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
        $this->travelTo(Carbon::parse('2026-10-20 11:00:00', 'UTC'));

        $url = route('rfi.review', ['kategori' => 'C', 'rfi' => $rfi]);
        $this->actingAs($qc)
            ->post($url, [
                'result' => RfiResult::Reject->value,
                'qc_note' => 'Dimensi tidak sesuai gambar.',
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'C']));

        $finding = QcFinding::query()->where('rfi_id', $rfi->id)->firstOrFail();
        $this->assertSame(RfiResult::Reject, $finding->result);
        $this->assertSame('Dimensi tidak sesuai gambar.', $finding->finding_list);
        $this->assertSame('2026-10-20', $finding->date_of_finding->toDateString());
        $this->assertSame('open', $finding->status);

        $this->post($url, [
            'result' => RfiResult::Repair->value,
            'qc_note' => 'Catatan pengganti.',
        ])->assertSessionHasErrors('result');

        $this->assertDatabaseCount('qc_findings', 1);
        $this->assertSame($rfi->id, $rfi->fresh()->qcFinding->rfi_id);
    }

    public function test_ok_review_does_not_create_a_finding(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $rfi = Rfi::factory()->create([
            'category' => 'B',
            'status' => 'open',
            'result' => null,
            'qc_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $this->actingAs($qc)
            ->post(route('rfi.review', ['kategori' => 'B', 'rfi' => $rfi]), [
                'result' => RfiResult::Accepted->value,
            ])
            ->assertRedirect(route('rfi.category', ['kategori' => 'B']));

        $this->assertDatabaseCount('qc_findings', 0);
        $this->assertSame('close', $rfi->fresh()->status);
    }

    public function test_migration_backfills_existing_nc_rfis(): void
    {
        Schema::dropIfExists('qc_findings');
        $rfi = Rfi::factory()->create([
            'category' => 'E',
            'status' => 'nc',
            'result' => RfiResult::UseAsIs,
            'qc_note' => 'Vendor perlu melengkapi dokumen.',
            'reviewed_at' => Carbon::parse('2026-10-19 12:30:00', 'UTC'),
        ]);
        $rfi->update(['rfi_no' => sprintf('RFI-E-%04d', $rfi->id)]);

        $migration = require database_path('migrations/2026_10_20_111258_create_qc_findings_table.php');
        $migration->up();

        $this->assertDatabaseHas('qc_findings', [
            'rfi_id' => $rfi->id,
            'result' => RfiResult::UseAsIs->value,
            'finding_list' => 'Vendor perlu melengkapi dokumen.',
            'date_of_finding' => '2026-10-19',
        ]);
    }

    /**
     * Visibility uses findings.view_categories:
     * admin/qc_qa/production: A-E; warehouse: A; ppic: E.
     */
    public function test_finding_visibility_is_limited_by_view_categories_and_hidden_projects_return_404(): void
    {
        $project = Project::factory()->create([
            'project_name' => 'All Category Project',
            'customer' => 'Customer One',
        ]);
        $findings = [];

        foreach (['A', 'B', 'C', 'D', 'E'] as $category) {
            $findings[$category] = $this->makeFinding($category, project: $project);
        }

        $visibleCategories = config('mudalaya.findings.view_categories');

        foreach ($visibleCategories as $role => $categories) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)
                ->get(route('qc-finding.project', ['project' => $project]))
                ->assertOk()
                ->assertSee('All Category Project')
                ->assertSee('Customer One');

            if (in_array($role, ['production', 'admin', 'ppic'], true)) {
                $response->assertSee('Simpan action plan');
            } elseif ($role === 'qc_qa') {
                $response->assertDontSee('Simpan action plan')
                    ->assertSee('Simpan due date');
            } else {
                // warehouse: category A only, handled via incoming material action
                $response->assertDontSee('Simpan action plan')
                    ->assertDontSee('Kirim Re-Inspect');
            }

            foreach ($findings as $category => $finding) {
                if (in_array($category, $categories, true)) {
                    $response->assertSee($finding->rfi->rfi_no);
                } else {
                    $response->assertDontSee($finding->rfi->rfi_no);
                }
            }

            $this->post(route('logout'));
        }

        // Project with only cat B findings -> ppic (sees only E) gets 404
        $hiddenProject = Project::factory()->create(['project_name' => 'Only Cat B Project']);
        $this->makeFinding('B', project: $hiddenProject);
        $ppic = User::factory()->create(['role' => 'ppic']);

        $this->actingAs($ppic)
            ->get(route('qc-finding.project', ['project' => $hiddenProject]))
            ->assertNotFound();
    }

    public function test_nc_rfi_links_to_its_qc_finding(): void
    {
        $finding = $this->makeFinding('A');
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $findingUrl = route('qc-finding.project', [
            'project' => $finding->rfi->project_id,
        ])."#finding-{$finding->id}";

        $this->actingAs($warehouse)
            ->get(route('rfi.category', ['kategori' => 'A']))
            ->assertOk()
            ->assertSee('Lihat temuan')
            ->assertSee($findingUrl, false);
    }

    /**
     * Action plan restricted to findings.action_plan_roles per category (B, C, D = production; E = ppic) and admin.
     * Category A has no action plan roles.
     * Action plan cannot be updated when status is waiting_verification or closed.
     */
    public function test_action_plan_is_limited_to_action_plan_roles_and_admin(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 00:00:00', 'UTC'));
        $project = Project::factory()->create();
        $findings = [];

        foreach (['A', 'B', 'C', 'D', 'E'] as $category) {
            $findings[$category] = $this->makeFinding($category, project: $project);
        }

        $production = User::factory()->create(['role' => 'production']);
        $ppic = User::factory()->create(['role' => 'ppic']);
        $admin = User::factory()->create(['role' => 'admin']);

        // Production can fill action plan for B, C, D
        foreach (['B', 'C', 'D'] as $cat) {
            $this->actingAs($production)
                ->post(
                    route('qc-finding.action-plan.update', ['finding' => $findings[$cat]]),
                    ['action_plan' => "production action for {$cat}"]
                )
                ->assertRedirect(route('qc-finding.project', ['project' => $project]));

            $this->assertDatabaseHas('qc_findings', [
                'id' => $findings[$cat]->id,
                'action_plan' => "production action for {$cat}",
                'action_plan_by' => $production->id,
                'action_plan_at' => '2026-10-20 00:00:00',
            ]);
        }

        // Production cannot fill action plan for A and E
        foreach (['A', 'E'] as $cat) {
            $this->actingAs($production)
                ->post(
                    route('qc-finding.action-plan.update', ['finding' => $findings[$cat]]),
                    ['action_plan' => "production action for {$cat}"]
                )
                ->assertForbidden();
        }

        // PPIC can fill action plan for E
        $this->actingAs($ppic)
            ->post(
                route('qc-finding.action-plan.update', ['finding' => $findings['E']]),
                ['action_plan' => 'ppic action for E']
            )
            ->assertRedirect(route('qc-finding.project', ['project' => $project]));

        $this->assertDatabaseHas('qc_findings', [
            'id' => $findings['E']->id,
            'action_plan' => 'ppic action for E',
            'action_plan_by' => $ppic->id,
            'action_plan_at' => '2026-10-20 00:00:00',
        ]);

        // PPIC cannot fill action plan for A, B, C, D
        foreach (['A', 'B', 'C', 'D'] as $cat) {
            $this->actingAs($ppic)
                ->post(
                    route('qc-finding.action-plan.update', ['finding' => $findings[$cat]]),
                    ['action_plan' => 'Should be blocked']
                )
                ->assertForbidden();
        }

        // Warehouse and qc_qa cannot fill action plan for any category
        foreach (['warehouse', 'qc_qa'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            foreach ($findings as $finding) {
                $this->actingAs($user)
                    ->post(
                        route('qc-finding.action-plan.update', ['finding' => $finding]),
                        ['action_plan' => 'Should be blocked']
                    )
                    ->assertForbidden();
            }
        }

        // Admin can fill action plan
        $this->actingAs($admin)
            ->post(
                route('qc-finding.action-plan.update', ['finding' => $findings['B']]),
                ['action_plan' => 'admin action plan']
            )
            ->assertRedirect();

        // Action plan rejected when status is waiting_verification
        $waitingFinding = $this->makeFinding('B', status: 'waiting_verification', project: $project);
        $this->actingAs($production)
            ->post(route('qc-finding.action-plan.update', ['finding' => $waitingFinding]), [
                'action_plan' => 'Tidak boleh saat waiting_verification.',
            ])
            ->assertForbidden();

        // Closed finding blocks even production
        $closedFinding = $this->makeFinding('B', status: 'closed', project: $project);

        $this->actingAs($production)
            ->post(route('qc-finding.action-plan.update', ['finding' => $closedFinding]), [
                'action_plan' => 'Tidak boleh disimpan.',
            ])
            ->assertForbidden();

        $this->assertNull($closedFinding->fresh()->action_plan);
    }

    public function test_project_view_computes_can_edit_action_plan_and_can_submit_per_category(): void
    {
        $project = Project::factory()->create();
        $findingB = $this->makeFinding('B', status: 'open', project: $project);
        $findingE = $this->makeFinding('E', status: 'open', project: $project);
        $waitingB = $this->makeFinding('B', status: 'waiting_verification', project: $project);

        $production = User::factory()->create(['role' => 'production']);
        $ppic = User::factory()->create(['role' => 'ppic']);

        // Production on project page
        $resProd = $this->actingAs($production)->get(route('qc-finding.project', ['project' => $project]));
        $resProd->assertOk();
        $findingsProd = $resProd->viewData('findings')->keyBy('id');

        $this->assertTrue($findingsProd[$findingB->id]->can_edit_action_plan);
        $this->assertTrue($findingsProd[$findingB->id]->can_submit);
        $this->assertFalse($findingsProd[$findingE->id]->can_edit_action_plan);
        $this->assertFalse($findingsProd[$findingE->id]->can_submit);
        $this->assertFalse($findingsProd[$waitingB->id]->can_edit_action_plan);

        // PPIC on project page (PPIC only has view_categories E)
        $resPpic = $this->actingAs($ppic)->get(route('qc-finding.project', ['project' => $project]));
        $resPpic->assertOk();
        $findingsPpic = $resPpic->viewData('findings')->keyBy('id');

        $this->assertTrue($findingsPpic[$findingE->id]->can_edit_action_plan);
        $this->assertTrue($findingsPpic[$findingE->id]->can_submit);
        $this->assertFalse(isset($findingsPpic[$findingB->id]));
    }

    public function test_only_qc_and_admin_can_update_due_date(): void
    {
        $finding = $this->makeFinding('B');
        $route = route('qc-finding.due-date.update', ['finding' => $finding]);
        $production = User::factory()->create(['role' => 'production']);

        $this->actingAs($production)
            ->post($route, ['due_date' => '2026-10-30'])
            ->assertForbidden();

        $qc = User::factory()->create(['role' => 'qc_qa']);
        $this->actingAs($qc)
            ->post($route, ['due_date' => '2026-10-30'])
            ->assertRedirect(route('qc-finding.project', ['project' => $finding->rfi->project_id]));

        $this->assertSame('2026-10-30', $finding->fresh()->due_date->toDateString());

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->post($route, ['due_date' => ''])
            ->assertRedirect(route('qc-finding.project', ['project' => $finding->rfi->project_id]));

        $this->assertNull($finding->fresh()->due_date);
    }

    /**
     * production now sees all categories A-E (findings.view_categories).
     * open_findings_count and nearest_due_date reflect this expanded visibility.
     */
    public function test_project_open_total_and_nearest_due_date_use_only_visible_categories(): void
    {
        $project = Project::factory()->create([
            'project_name' => 'Summary Project',
            'customer' => 'Summary Customer',
        ]);
        $this->makeFinding('A', dueDate: '2026-10-22', project: $project);
        $this->makeFinding('B', dueDate: '2026-10-30', project: $project);
        $this->makeFinding('C', status: 'waiting_verification', dueDate: '2026-10-25', project: $project);
        $this->makeFinding('D', status: 'closed', dueDate: '2026-10-21', project: $project);

        // production sees A-E: open_findings_count=3 (A,B,C not closed), nearest=2026-10-22
        $production = User::factory()->create(['role' => 'production']);

        $this->actingAs($production)
            ->get(route('qc-finding.index'))
            ->assertOk()
            ->assertSee('Summary Project')
            ->assertSee('Summary Customer')
            ->assertViewHas('projects', function ($projects) use ($project): bool {
                $summary = $projects->getCollection()->firstWhere('id', $project->id);

                return $summary !== null
                    && (int) $summary->open_findings_count === 3
                    && Carbon::parse($summary->nearest_due_date)->toDateString() === '2026-10-22';
            });

        // A project with only cat-E findings is hidden from warehouse (warehouse sees only A)
        $hiddenProject = Project::factory()->create(['project_name' => 'Only E Project']);
        $this->makeFinding('E', dueDate: '2026-10-23', project: $hiddenProject);

        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $this->actingAs($warehouse)
            ->get(route('qc-finding.index'))
            ->assertSee('Summary Project')
            ->assertDontSee('Only E Project')
            ->assertViewHas('projects', function ($projects) use ($project): bool {
                $summary = $projects->getCollection()->firstWhere('id', $project->id);

                // warehouse sees only cat A: open_findings_count=1, nearest=2026-10-22
                return $summary !== null
                    && (int) $summary->open_findings_count === 1
                    && Carbon::parse($summary->nearest_due_date)->toDateString() === '2026-10-22';
            });
    }

    /**
     * RFI no is a link only when the viewing role has RFI access to that category.
     * production has RFI access B,C,D only -> cat A and E shown as plain text (no href).
     */
    public function test_rfi_no_link_is_hidden_for_roles_without_rfi_category_access(): void
    {
        $project = Project::factory()->create();
        $findingA = $this->makeFinding('A', project: $project);
        $findingE = $this->makeFinding('E', project: $project);
        $findingB = $this->makeFinding('B', project: $project);

        $production = User::factory()->create(['role' => 'production']);

        $response = $this->actingAs($production)
            ->get(route('qc-finding.project', ['project' => $project]))
            ->assertOk();

        // Cat B -> production has RFI access -> link appears
        $response->assertSee(
            'href="'.route('rfi.category', ['kategori' => 'B', 'status' => 'nc']).'"',
            false
        );

        // Cat A and E -> production has NO RFI access -> no href link
        $response->assertDontSee(
            'href="'.route('rfi.category', ['kategori' => 'A', 'status' => 'nc']).'"',
            false
        );
        $response->assertDontSee(
            'href="'.route('rfi.category', ['kategori' => 'E', 'status' => 'nc']).'"',
            false
        );

        // But RFI numbers are still visible (production sees all A-E findings)
        $response->assertSee($findingA->rfi->rfi_no)
            ->assertSee($findingE->rfi->rfi_no)
            ->assertSee($findingB->rfi->rfi_no);
    }

    public function test_project_filter_by_terlambat_and_verifikasi(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00', 'Asia/Jakarta'));
        $user = User::factory()->create(['role' => 'admin']);

        $projectOverdue = Project::factory()->create(['project_name' => 'Project Overdue']);
        $this->makeFinding('A', status: 'open', dueDate: '2026-10-20', project: $projectOverdue);

        $projectWaiting = Project::factory()->create(['project_name' => 'Project Waiting']);
        $this->makeFinding('A', status: 'waiting_verification', dueDate: '2026-10-30', project: $projectWaiting);

        // Filter terlambat
        $this->actingAs($user)
            ->get(route('qc-finding.index', ['pf' => 'terlambat']))
            ->assertOk()
            ->assertSee('Project Overdue')
            ->assertDontSee('Project Waiting');

        // Filter verifikasi
        $this->actingAs($user)
            ->get(route('qc-finding.index', ['pf' => 'verifikasi']))
            ->assertOk()
            ->assertSee('Project Waiting')
            ->assertDontSee('Project Overdue');
    }

    public function test_project_search_by_name_or_code(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $projectAlpha = Project::factory()->create(['project_name' => 'Alpha Heavy Crane', 'project_code' => 'PRJ-ALP']);
        $this->makeFinding('B', project: $projectAlpha);

        $projectBeta = Project::factory()->create(['project_name' => 'Beta Pipeline', 'project_code' => 'PRJ-BET']);
        $this->makeFinding('B', project: $projectBeta);

        $this->actingAs($user)
            ->get(route('qc-finding.index', ['pq' => 'Heavy Crane']))
            ->assertOk()
            ->assertSee('Alpha Heavy Crane')
            ->assertDontSee('Beta Pipeline');
    }

    public function test_qc_finding_tab_on_dashboard_renders_statistics_and_project_cards(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00', 'Asia/Jakarta'));
        $user = User::factory()->create(['role' => 'admin']);
        $project = Project::factory()->create(['project_name' => 'Main Project']);
        $this->makeFinding('A', status: 'open', dueDate: '2026-10-20', project: $project);

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'qc-finding']))
            ->assertOk()
            ->assertSee('Temuan Open')
            ->assertSee('Menunggu Verifikasi')
            ->assertSee('Terlambat')
            ->assertSee('Closed')
            ->assertSee('Main Project');
    }

    public function test_finding_buka_parameter_and_stepper_stages(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $project = Project::factory()->create(['project_name' => 'Stepper Project']);
        $finding = $this->makeFinding('A', status: 'closed', project: $project);

        $this->actingAs($user)
            ->get(route('qc-finding.project', ['project' => $project, 'buka' => $finding->id]))
            ->assertOk()
            ->assertSee('id="temuan-'.$finding->id.'"', false)
            ->assertSee('Tahapan temuan');
    }

    public function test_qc_finding_module_and_dashboard_tab_render_identical_overview_for_all_roles(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00', 'Asia/Jakarta'));

        // Setup multiple projects with different findings across categories A, B, C, D, E
        $project1 = Project::factory()->create(['project_name' => 'Project A Overdue', 'project_code' => 'PRJ-A']);
        $this->makeFinding('A', status: 'open', dueDate: '2026-10-20', project: $project1);

        $project2 = Project::factory()->create(['project_name' => 'Project B Waiting', 'project_code' => 'PRJ-B']);
        $this->makeFinding('B', status: 'waiting_verification', dueDate: '2026-10-28', project: $project2);

        $project3 = Project::factory()->create(['project_name' => 'Project C Closed', 'project_code' => 'PRJ-C']);
        $this->makeFinding('C', status: 'closed', dueDate: '2026-10-15', project: $project3);

        $project4 = Project::factory()->create(['project_name' => 'Project E Overdue', 'project_code' => 'PRJ-E']);
        $this->makeFinding('E', status: 'open', dueDate: '2026-10-18', project: $project4);

        $roles = ['admin', 'qc_qa', 'production'];

        foreach ($roles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $resModule = $this->actingAs($user)->get(route('qc-finding.index'))->assertOk();
            $resDashboard = $this->get(route('dashboard', ['tab' => 'qc-finding']))->assertOk();

            // Extract overview data from both responses
            $overviewModule = $resModule->viewData('overview');
            $overviewDashboard = $resDashboard->viewData('qcDashboard');

            $this->assertNotNull($overviewModule);
            $this->assertNotNull($overviewDashboard);

            // 1. Assert identical 4 cards stats
            $this->assertSame($overviewModule['stats'], $overviewDashboard['stats']);

            // 2. Assert identical project order and IDs
            $moduleProjectIds = $overviewModule['projects']->getCollection()->pluck('id')->all();
            $dashboardProjectIds = $overviewDashboard['projects']->getCollection()->pluck('id')->all();
            $this->assertSame($moduleProjectIds, $dashboardProjectIds);

            // 3. Assert identical urgent findings
            $moduleUrgentIds = $overviewModule['urgentFindings']->pluck('id')->all();
            $dashboardUrgentIds = $overviewDashboard['urgentFindings']->pluck('id')->all();
            $this->assertSame($moduleUrgentIds, $dashboardUrgentIds);

            $this->post(route('logout'));
        }
    }

    public function test_new_finding_from_non_ok_review_has_automatic_due_date_tomorrow_asia_jakarta(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-03 14:00:00', 'Asia/Jakarta'));
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $rfi = Rfi::factory()->create([
            'category' => 'C',
            'status' => 'open',
            'result' => null,
        ]);

        $this->actingAs($qc)
            ->post(route('rfi.review', ['kategori' => 'C', 'rfi' => $rfi]), [
                'result' => RfiResult::Repair->value,
                'qc_note' => 'Perbaiki pengelasan.',
            ])
            ->assertRedirect();

        $finding = QcFinding::query()->where('rfi_id', $rfi->id)->firstOrFail();
        $this->assertSame('2026-11-03', $finding->date_of_finding->toDateString());
        $this->assertSame('2026-11-04', $finding->due_date->toDateString());

        Carbon::setTestNow(null);
    }

    public function test_terlambat_chip_appears_only_for_open_findings_past_due_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00:00', 'Asia/Jakarta'));
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        // 1. Overdue finding (due yesterday, open) -> Should see "Terlambat"
        $overdueRfi = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'C']);
        $overdueFinding = QcFinding::factory()->create([
            'rfi_id' => $overdueRfi->id,
            'status' => 'open',
            'due_date' => '2026-11-04',
            'date_of_finding' => '2026-11-03',
        ]);

        $response = $this->actingAs($qc)
            ->get(route('qc-finding.project', ['project' => $project]));
        $response->assertOk();
        $response->assertSee('Terlambat');

        // 2. Overdue finding but closed -> Should NOT see "Terlambat"
        $overdueFinding->update(['status' => 'closed', 'date_of_close' => '2026-11-05']);
        $responseClosed = $this->actingAs($qc)
            ->get(route('qc-finding.project', ['project' => $project]));
        $responseClosed->assertDontSee('Terlambat');

        // 3. Not overdue finding (due tomorrow, open) -> Should NOT see "Terlambat"
        $overdueFinding->update(['status' => 'open', 'due_date' => '2026-11-06']);
        $responseFuture = $this->actingAs($qc)
            ->get(route('qc-finding.project', ['project' => $project]));
        $responseFuture->assertDontSee('Terlambat');

        Carbon::setTestNow(null);
    }

    public function test_migration_fills_default_due_date_only_on_open_findings_with_null_due_date(): void
    {
        $project = Project::factory()->create();
        $rfi1 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'C']);
        $rfi2 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'C']);
        $rfi3 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'C']);

        // 1. Open finding with null due_date -> should be filled with date_of_finding + 1 day
        $openNull = QcFinding::factory()->create([
            'rfi_id' => $rfi1->id,
            'status' => 'open',
            'date_of_finding' => '2026-11-01',
            'due_date' => null,
        ]);

        // 2. Closed finding with null due_date -> should NOT be changed
        $closedNull = QcFinding::factory()->create([
            'rfi_id' => $rfi2->id,
            'status' => 'closed',
            'date_of_finding' => '2026-11-01',
            'due_date' => null,
            'date_of_close' => '2026-11-02',
        ]);

        // 3. Open finding with existing due_date -> should NOT be changed
        $openWithDue = QcFinding::factory()->create([
            'rfi_id' => $rfi3->id,
            'status' => 'open',
            'date_of_finding' => '2026-11-01',
            'due_date' => '2026-11-15',
        ]);

        $migration = require database_path('migrations/2026_11_03_055546_fill_default_due_date_on_open_qc_findings.php');
        $migration->up();

        $this->assertSame('2026-11-02', $openNull->fresh()->due_date->toDateString());
        $this->assertNull($closedNull->fresh()->due_date);
        $this->assertSame('2026-11-15', $openWithDue->fresh()->due_date->toDateString());
    }

    public function test_due_date_status_logic_and_model_scopes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Jakarta'));
        $project = Project::factory()->create();

        $rfi1 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B']);
        $rfi2 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B']);
        $rfi3 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B']);
        $rfi4 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B']);

        $dueToday = QcFinding::factory()->create([
            'rfi_id' => $rfi1->id,
            'status' => 'open',
            'due_date' => '2026-11-04',
        ]);

        $overdue = QcFinding::factory()->create([
            'rfi_id' => $rfi2->id,
            'status' => 'open',
            'due_date' => '2026-11-03',
        ]);

        $future = QcFinding::factory()->create([
            'rfi_id' => $rfi3->id,
            'status' => 'open',
            'due_date' => '2026-11-05',
        ]);

        $closedOverdue = QcFinding::factory()->create([
            'rfi_id' => $rfi4->id,
            'status' => 'closed',
            'due_date' => '2026-11-01',
            'date_of_close' => '2026-11-02',
        ]);

        $this->assertSame('due_today', $dueToday->dueDateStatus());
        $this->assertTrue($dueToday->isDueToday());
        $this->assertFalse($dueToday->isOverdue());

        $this->assertSame('overdue', $overdue->dueDateStatus());
        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($overdue->isDueToday());

        $this->assertNull($future->dueDateStatus());
        $this->assertFalse($future->isDueToday());
        $this->assertFalse($future->isOverdue());

        $this->assertNull($closedOverdue->dueDateStatus());
        $this->assertFalse($closedOverdue->isDueToday());
        $this->assertFalse($closedOverdue->isOverdue());

        $this->assertSame(1, QcFinding::query()->dueToday()->count());
        $this->assertSame(1, QcFinding::query()->overdue()->count());
        $this->assertSame(2, QcFinding::query()->urgentDue()->count());

        Carbon::setTestNow(null);
    }

    public function test_project_finding_displays_chips_for_due_today_and_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Jakarta'));
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfiToday = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'C']);
        QcFinding::factory()->create([
            'rfi_id' => $rfiToday->id,
            'status' => 'open',
            'due_date' => '2026-11-04',
        ]);

        $response = $this->actingAs($qc)->get(route('qc-finding.project', ['project' => $project]));
        $response->assertOk();
        $response->assertSee('Jatuh tempo hari ini');

        Carbon::setTestNow(null);
    }

    public function test_process_chip_and_details_appear_according_to_category(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        // 1. Category A with jenis_material
        $rfiA = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'details' => ['jenis_material' => 'Plat Baja SS400'],
        ]);
        QcFinding::factory()->create(['rfi_id' => $rfiA->id, 'finding_list' => 'Material cacat permukaan']);

        // 2. Category C with inspect_item & test_type
        $rfiC = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'C',
            'details' => ['inspect_item' => 'Testing', 'test_type' => 'Hydro'],
        ]);
        QcFinding::factory()->create(['rfi_id' => $rfiC->id, 'finding_list' => 'Tekanan uji bocor']);

        // 3. Category E with vendor
        $rfiE = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'E',
            'details' => ['vendor' => 'PT Mitra Logam'],
        ]);
        QcFinding::factory()->create(['rfi_id' => $rfiE->id, 'finding_list' => 'Dimensi dari vendor tidak pas']);

        // 4. Category B without detail
        $rfiB = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'details' => [],
        ]);
        QcFinding::factory()->create(['rfi_id' => $rfiB->id, 'finding_list' => 'Sudut potong salah']);

        $res = $this->actingAs($qc)->get(route('qc-finding.project', ['project' => $project]))->assertOk();

        $res->assertSee('Proses: Incoming Material · Plat Baja SS400');
        $res->assertSee('Proses: Fabrication · Testing (Hydro)');
        $res->assertSee('Proses: Vendor · PT Mitra Logam');
        $res->assertSee('Proses: Preparation');
    }

    public function test_process_filter_only_displays_findings_for_selected_category_and_combines_with_status(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfiA = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'A']);
        QcFinding::factory()->create(['rfi_id' => $rfiA->id, 'finding_list' => 'Finding Material A', 'status' => 'open']);

        $rfiC1 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'C']);
        QcFinding::factory()->create(['rfi_id' => $rfiC1->id, 'finding_list' => 'Finding Fab C Open', 'status' => 'open']);

        $rfiC2 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'C']);
        QcFinding::factory()->create(['rfi_id' => $rfiC2->id, 'finding_list' => 'Finding Fab C Closed', 'status' => 'closed']);

        // Filter proses C
        $res = $this->actingAs($qc)
            ->get(route('qc-finding.project', ['project' => $project, 'proses' => 'C']))
            ->assertOk();

        $res->assertSee('Finding Fab C Open');
        $res->assertSee('Finding Fab C Closed');
        $res->assertDontSee('Finding Material A');

        // Combined with status open
        $resCombined = $this->actingAs($qc)
            ->get(route('qc-finding.project', ['project' => $project, 'proses' => 'C', 'status' => 'open']))
            ->assertOk();

        $resCombined->assertSee('Finding Fab C Open');
        $resCombined->assertDontSee('Finding Fab C Closed');
        $resCombined->assertDontSee('Finding Material A');
    }

    public function test_role_without_category_access_does_not_see_it_in_process_filter(): void
    {
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $project = Project::factory()->create();

        $rfiA = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'A']);
        QcFinding::factory()->create(['rfi_id' => $rfiA->id, 'finding_list' => 'Finding Warehouse']);

        $res = $this->actingAs($warehouse)
            ->get(route('qc-finding.project', ['project' => $project]))
            ->assertOk();

        // Warehouse only has access to category A (Incoming Material)
        $res->assertSee('value="A"', false);
        $res->assertSee('Incoming Material');
        $res->assertDontSee('value="B"', false);
        $res->assertDontSee('value="C"', false);
        $res->assertDontSee('value="D"', false);
        $res->assertDontSee('value="E"', false);
    }

    private function makeFinding(
        string $category,
        string $status = 'open',
        ?string $dueDate = null,
        ?Project $project = null
    ): QcFinding {
        $project ??= Project::factory()->create();
        $creator = User::factory()->create(['role' => 'qc_qa']);
        $rfi = Rfi::factory()->for($project)->create([
            'category' => $category,
            'created_by' => $creator->id,
            'rfi_no' => null,
        ]);
        $rfi->update(['rfi_no' => sprintf('RFI-%s-%04d', $category, $rfi->id)]);

        return QcFinding::factory()->for($rfi)->create([
            'status' => $status,
            'due_date' => $dueDate,
        ]);
    }

    public function test_qc_finding_project_displays_incoming_form_for_valid_rfi_baru(): void
    {
        $project = Project::factory()->create();
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $admin = User::factory()->create(['role' => 'admin']);
        $warehouse = User::factory()->create(['role' => 'warehouse']);

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-9901',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['material' => 'Baut M16', 'vendor' => 'V1'],
            'created_by' => $warehouse->id,
        ]);

        // QC sees form
        $resQc = $this->actingAs($qc)->get(route('qc-finding.project', [
            'project' => $project->id,
            'rfi_baru' => $rfi->id,
        ]));
        $resQc->assertOk()
            ->assertSee('Tindakan QC — Incoming Material')
            ->assertSee('RFI-A-9901')
            ->assertSee(route('qc-finding.incoming.store', ['rfi' => $rfi->id]))
            ->assertDontSee('Tindakan QC Incoming Material Tidak Dapat Ditampilkan');

        // Admin also sees form
        $resAdmin = $this->actingAs($admin)->get(route('qc-finding.project', [
            'project' => $project->id,
            'rfi_baru' => $rfi->id,
        ]));
        $resAdmin->assertOk()
            ->assertSee('Tindakan QC — Incoming Material');

        // No finding in DB yet
        $this->assertDatabaseMissing('qc_findings', ['rfi_id' => $rfi->id]);
    }

    public function test_production_and_warehouse_cannot_access_or_submit_incoming_form(): void
    {
        $project = Project::factory()->create();
        $production = User::factory()->create(['role' => 'production']);
        $warehouse = User::factory()->create(['role' => 'warehouse']);

        // We must have at least one finding in project so warehouse/production doesn't hit 404 for empty findings
        $this->makeFinding('A', 'open', null, $project);

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-9902',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['material' => 'Baut M16'],
            'created_by' => $warehouse->id,
        ]);

        // Warehouse gets error message and no form
        $resWarehouse = $this->actingAs($warehouse)->get(route('qc-finding.project', [
            'project' => $project->id,
            'rfi_baru' => $rfi->id,
        ]));
        $resWarehouse->assertOk()
            ->assertSee('Tindakan QC Incoming Material Tidak Dapat Ditampilkan')
            ->assertDontSee(route('qc-finding.incoming.store', ['rfi' => $rfi->id]));

        // Warehouse POST is forbidden
        $this->actingAs($warehouse)->post(route('qc-finding.incoming.store', ['rfi' => $rfi->id]), [
            'result' => 'use_as_is',
            'material' => 'Baut M16',
            'reason_of_finding' => 'Karat ringan',
        ])->assertForbidden();

        // Production POST is forbidden
        $this->actingAs($production)->post(route('qc-finding.incoming.store', ['rfi' => $rfi->id]), [
            'result' => 'use_as_is',
            'material' => 'Baut M16',
            'reason_of_finding' => 'Karat ringan',
        ])->assertForbidden();
    }

    public function test_qc_finding_project_with_invalid_rfi_baru_shows_error_message(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $qc = User::factory()->create(['role' => 'qc_qa']);

        // Non-Category A RFI
        $rfiB = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'rfi_no' => 'RFI-B-9903',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['line' => 'Line 1'],
            'created_by' => $qc->id,
        ]);

        // Create 1 finding so project has findings
        $this->makeFinding('A', 'open', null, $project);

        $res = $this->actingAs($qc)->get(route('qc-finding.project', [
            'project' => $project->id,
            'rfi_baru' => $rfiB->id,
        ]));
        $res->assertOk()
            ->assertSee('Tindakan QC Incoming Material Tidak Dapat Ditampilkan')
            ->assertDontSee(route('qc-finding.incoming.store', ['rfi' => $rfiB->id]));

        // RFI belonging to other project
        $rfiOtherProj = Rfi::query()->create([
            'project_id' => $otherProject->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-9904',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['material' => 'Baut'],
            'created_by' => $qc->id,
        ]);

        $res2 = $this->actingAs($qc)->get(route('qc-finding.project', [
            'project' => $project->id,
            'rfi_baru' => $rfiOtherProj->id,
        ]));
        $res2->assertOk()
            ->assertSee('Tindakan QC Incoming Material Tidak Dapat Ditampilkan');
    }

    public function test_store_incoming_material_use_as_is_sends_single_notification_to_creator(): void
    {
        $project = Project::factory()->create();
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $warehouse = User::factory()->create(['role' => 'warehouse']);

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-9905',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['material' => 'Pipa CS 4 inch'],
            'created_by' => $warehouse->id,
            'submitted_at' => Carbon::now('UTC')->subHour(),
        ]);

        $response = $this->actingAs($qc)->post(route('qc-finding.incoming.store', ['rfi' => $rfi->id]), [
            'items' => [
                [
                    'materials' => ['Pipa CS 4 inch'],
                    'reason' => 'Ada goresan halus di permukaan',
                ],
            ],
            'result' => 'use_as_is',
        ]);

        $response->assertRedirect(route('qc-finding.project', ['project' => $project->id]));

        $finding = QcFinding::query()->where('rfi_id', $rfi->id)->firstOrFail();
        $this->assertSame('closed', $finding->status);
        $this->assertSame(RfiResult::UseAsIs, $finding->result);
        $this->assertSame('Ada goresan halus di permukaan', $finding->finding_list);
        $this->assertSame('Pipa CS 4 inch', $finding->action_plan);
        $this->assertNotNull($finding->date_of_close);

        $rfi->refresh();
        $this->assertSame('close', $rfi->status);
        $this->assertSame(RfiResult::UseAsIs, $rfi->result);
        $this->assertSame('Ada goresan halus di permukaan', $rfi->qc_note);

        // Warehouse receives exactly one notification
        $this->assertSame(1, $warehouse->notifications()->count());
        $notification = $warehouse->notifications()->first();
        $this->assertSame('finding.warehouse_use_as_is', $notification->data['type']);
        $this->assertStringContainsString('Use as is', $notification->data['title']);

        // Actor (QC) does not receive
        $this->assertSame(0, $qc->notifications()->count());

        // Assert activities recorded
        $this->assertDatabaseHas('finding_activities', [
            'qc_finding_id' => $finding->id,
            'type' => FindingActivity::TYPE_RFI_SUBMITTED,
        ]);
        $this->assertDatabaseHas('finding_activities', [
            'qc_finding_id' => $finding->id,
            'type' => FindingActivity::TYPE_FINDING_CREATED,
        ]);
        $this->assertDatabaseHas('finding_activities', [
            'qc_finding_id' => $finding->id,
            'type' => FindingActivity::TYPE_ACTION_PLAN_SAVED,
        ]);
        $this->assertDatabaseHas('finding_histories', [
            'qc_finding_id' => $finding->id,
            'event' => 'closed_incoming',
        ]);
    }

    public function test_store_incoming_material_reject_sends_notification_to_warehouse(): void
    {
        $project = Project::factory()->create();
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $warehouse = User::factory()->create(['role' => 'warehouse']);

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-9906',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['material' => 'Flange 2 inch'],
            'created_by' => $warehouse->id,
            'submitted_at' => Carbon::now('UTC')->subHour(),
        ]);

        $response = $this->actingAs($qc)->post(route('qc-finding.incoming.store', ['rfi' => $rfi->id]), [
            'material' => 'Flange 2 inch',
            'reason_of_finding' => 'Dimensi tidak presisi',
            'result' => 'reject',
        ]);

        $response->assertRedirect(route('qc-finding.project', ['project' => $project->id]));

        $finding = QcFinding::query()->where('rfi_id', $rfi->id)->firstOrFail();
        $this->assertSame('closed', $finding->status);
        $this->assertSame(RfiResult::Reject, $finding->result);

        $rfi->refresh();
        $this->assertSame('nc', $rfi->status);
        $this->assertSame(RfiResult::Reject, $rfi->result);

        // Warehouse receives warehouseFindingRejected notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $warehouse->id,
        ]);
    }

    public function test_store_incoming_material_validation_failure_creates_no_data(): void
    {
        $project = Project::factory()->create();
        $qc = User::factory()->create(['role' => 'qc_qa']);

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-9907',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['material' => 'Test Material'],
            'created_by' => $qc->id,
        ]);

        $response = $this->actingAs($qc)->post(route('qc-finding.incoming.store', ['rfi' => $rfi->id]), [
            'items' => [],
            'material' => '',
            'reason_of_finding' => '',
            'result' => 'use_as_is',
        ]);

        $response->assertSessionHasErrors('material');
        $this->assertDatabaseMissing('qc_findings', ['rfi_id' => $rfi->id]);
        $this->assertSame('open', $rfi->fresh()->status);
    }

    public function test_store_incoming_material_second_submission_is_rejected(): void
    {
        $project = Project::factory()->create();
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $warehouse = User::factory()->create(['role' => 'warehouse']);

        $rfi = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-9908',
            'status' => 'open',
            'date_inspect' => '2026-10-01',
            'details' => ['material' => 'Test Material'],
            'created_by' => $warehouse->id,
        ]);

        $payload = [
            'material' => 'Fitting Elbow',
            'reason_of_finding' => 'Sudut tidak sesuai',
            'result' => 'use_as_is',
        ];

        // First submit succeeds
        $this->actingAs($qc)->post(route('qc-finding.incoming.store', ['rfi' => $rfi->id]), $payload)
            ->assertRedirect();

        $finding = QcFinding::query()->where('rfi_id', $rfi->id)->firstOrFail();
        $this->assertSame('closed', $finding->status);
        $this->assertSame(RfiResult::UseAsIs, $finding->result);
        $notifCount = $warehouse->notifications()->count();
        $this->assertSame(1, $notifCount);

        // Second submit to store endpoint fails with 400
        $this->actingAs($qc)->post(route('qc-finding.incoming.store', ['rfi' => $rfi->id]), [
            'material' => 'Fitting Elbow Ganti',
            'reason_of_finding' => 'Alasan berbeda',
            'result' => 'reject',
        ])->assertStatus(400);

        // Submit to incoming-action on closed finding also fails with 400
        $this->actingAs($qc)->post(route('qc-finding.incoming-action', ['finding' => $finding]), [
            'material' => 'Fitting Elbow Ganti Lagi',
            'reason_of_finding' => 'Alasan ketiga',
            'result' => 'reject',
        ])->assertStatus(400);

        // Finding result and notification count are unchanged
        $finding->refresh();
        $rfi->refresh();
        $this->assertSame('closed', $finding->status);
        $this->assertSame(RfiResult::UseAsIs, $finding->result);
        $this->assertSame('close', $rfi->status);
        $this->assertSame(RfiResult::UseAsIs, $rfi->result);
        $this->assertSame($notifCount, $warehouse->notifications()->count());
    }

    public function test_migration_cleanup_only_half_baked_incoming_material_findings(): void
    {
        $project = Project::factory()->create();
        $qc = User::factory()->create(['role' => 'qc_qa']);

        // 1. Half-baked finding
        $rfiHalfBaked = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-CLEAN-1',
            'status' => 'nc',
            'result' => 'repair',
            'reviewed_by' => $qc->id,
            'reviewed_at' => Carbon::now('UTC'),
            'date_inspect' => '2026-10-01',
            'details' => ['material' => 'Test Half Baked'],
            'created_by' => $qc->id,
        ]);
        $findingHalfBaked = QcFinding::query()->create([
            'rfi_id' => $rfiHalfBaked->id,
            'result' => RfiResult::Repair,
            'finding_list' => 'Incoming Material Finding',
            'date_of_finding' => '2026-10-01',
            'status' => 'open',
            'action_plan' => null,
        ]);
        DB::table('finding_activities')->insert([
            'qc_finding_id' => $findingHalfBaked->id,
            'type' => 'rfi_submitted',
            'occurred_at' => Carbon::now('UTC'),
            'created_at' => Carbon::now('UTC'),
        ]);
        DB::table('finding_activities')->insert([
            'qc_finding_id' => $findingHalfBaked->id,
            'type' => 'finding_created',
            'occurred_at' => Carbon::now('UTC'),
            'created_at' => Carbon::now('UTC'),
        ]);

        // 2. Legitimate closed finding (must NOT be touched)
        $rfiClosed = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-KEEP-1',
            'status' => 'close',
            'result' => 'use_as_is',
            'reviewed_by' => $qc->id,
            'reviewed_at' => Carbon::now('UTC'),
            'date_inspect' => '2026-10-01',
            'details' => ['material' => 'Pipa Baja'],
            'created_by' => $qc->id,
        ]);
        $findingClosed = QcFinding::query()->create([
            'rfi_id' => $rfiClosed->id,
            'result' => RfiResult::UseAsIs,
            'finding_list' => 'Incoming Material Finding',
            'action_plan' => 'Pipa Baja',
            'date_of_finding' => '2026-10-01',
            'status' => 'closed',
        ]);

        // 3. Finding with action plan (must NOT be touched)
        $rfiWithPlan = Rfi::query()->create([
            'project_id' => $project->id,
            'category' => 'A',
            'rfi_no' => 'RFI-A-KEEP-2',
            'status' => 'nc',
            'result' => 'repair',
            'reviewed_by' => $qc->id,
            'reviewed_at' => Carbon::now('UTC'),
            'date_inspect' => '2026-10-01',
            'details' => ['material' => 'Test With Plan'],
            'created_by' => $qc->id,
        ]);
        $findingWithPlan = QcFinding::query()->create([
            'rfi_id' => $rfiWithPlan->id,
            'result' => RfiResult::Repair,
            'finding_list' => 'Incoming Material Finding',
            'action_plan' => 'Sedang dikoordinasikan dengan vendor',
            'date_of_finding' => '2026-10-01',
            'status' => 'open',
        ]);

        // Run the cleanup migration up()
        $migration = require database_path('migrations/2026_11_16_000001_cleanup_half_baked_incoming_material_findings.php');
        $migration->up();

        // Half-baked finding is deleted and RFI reverted to open
        $rfiHalfBaked->refresh();
        $this->assertSame('open', $rfiHalfBaked->status);
        $this->assertNull($rfiHalfBaked->result);
        $this->assertNull($rfiHalfBaked->reviewed_by);
        $this->assertNull($rfiHalfBaked->reviewed_at);
        $this->assertDatabaseMissing('qc_findings', ['id' => $findingHalfBaked->id]);
        $this->assertDatabaseMissing('finding_activities', ['qc_finding_id' => $findingHalfBaked->id]);

        // Other findings are untouched
        $this->assertDatabaseHas('qc_findings', ['id' => $findingClosed->id]);
        $this->assertDatabaseHas('qc_findings', ['id' => $findingWithPlan->id]);
        $this->assertSame('close', $rfiClosed->fresh()->status);
        $this->assertSame('nc', $rfiWithPlan->fresh()->status);
    }
}
