<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class DashboardTabsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTestDatabase();
        $this->withoutVite();
    }

    public function test_dashboard_renders_tabs_correctly_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Default tab: Ringkasan
        $resDefault = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $resDefault->assertSee('Ringkasan')
            ->assertSee('QC Finding')
            ->assertSee('NCR')
            ->assertSee('Perlu tindakan Anda')
            ->assertSee('Ringkasan modul')
            ->assertSee('Grafik utama')
            ->assertSee('aria-current="page"', false);

        // Tab QC Finding
        $resQc = $this->actingAs($admin)->get(route('dashboard', ['tab' => 'qc-finding']))->assertOk();
        $resQc->assertSee('Temuan Open')
            ->assertSee('Menunggu Verifikasi')
            ->assertSee('Terlambat')
            ->assertSee('Closed')
            ->assertSee('Temuan per Project')
            ->assertSee('Perlu Perhatian');

        // Tab NCR
        $resNcr = $this->actingAs($admin)->get(route('dashboard', ['tab' => 'ncr']))->assertOk();
        $resNcr->assertSee('Dashboard NCR')
            ->assertSee('Total NCR')
            ->assertSee('Total Major');
    }

    public function test_action_cards_counts_for_qc_and_production(): void
    {
        $project = Project::factory()->create(['project_code' => 'PRJ-TEST']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $production = User::factory()->create(['role' => 'production']);

        $now = Carbon::now('Asia/Jakarta');

        // 1. RFI waiting review
        $rfi = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'status' => 'open',
            'submitted_at' => $now->copy()->subDays(2),
        ]);

        // 2. Finding waiting verification
        $rfiFinding1 = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'status' => 'nc',
        ]);
        $finding = QcFinding::factory()->create([
            'rfi_id' => $rfiFinding1->id,
            'status' => 'waiting_verification',
            'date_of_finding' => $now->copy()->subDays(4)->toDateString(),
            'due_date' => $now->copy()->addDays(2)->toDateString(),
        ]);

        // 3. Finding overdue
        $rfiFinding2 = Rfi::factory()->create([
            'project_id' => $project->id,
            'category' => 'B',
            'status' => 'nc',
        ]);
        QcFinding::factory()->create([
            'rfi_id' => $rfiFinding2->id,
            'status' => 'open',
            'date_of_finding' => $now->copy()->subDays(10)->toDateString(),
            'due_date' => $now->copy()->subDays(3)->toDateString(),
        ]);

        // QC should see: RFI review (1), Re-Inspect verify (1), Overdue (1)
        $qcRes = $this->actingAs($qc)->get(route('dashboard'))->assertOk();
        $qcRes->assertSee('RFI menunggu review')
            ->assertSee('Re-Inspect menunggu verifikasi')
            ->assertSee('Temuan lewat due date');

        // Production cannot review RFI or verify Re-Inspect, but CAN see Overdue findings on allowed categories
        $prodRes = $this->actingAs($production)->get(route('dashboard'))->assertOk();
        $prodRes->assertDontSee('Review sekarang')
            ->assertDontSee('Re-Inspect menunggu verifikasi')
            ->assertSee('Temuan lewat due date');
    }

    public function test_empty_state_when_no_action_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $res = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $res->assertSee('Tidak ada yang perlu ditindak sekarang.');
    }

    public function test_action_card_shows_due_today_and_overdue_breakdown(): void
    {
        $now = Carbon::parse('2026-11-04 10:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $rfi1 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B', 'status' => 'nc']);
        $rfi2 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B', 'status' => 'nc']);
        $rfi3 = Rfi::factory()->create(['project_id' => $project->id, 'category' => 'B', 'status' => 'nc']);

        // 2 due today
        QcFinding::factory()->create([
            'rfi_id' => $rfi1->id,
            'status' => 'open',
            'due_date' => $now->toDateString(),
        ]);
        QcFinding::factory()->create([
            'rfi_id' => $rfi2->id,
            'status' => 'open',
            'due_date' => $now->toDateString(),
        ]);

        // 1 overdue
        QcFinding::factory()->create([
            'rfi_id' => $rfi3->id,
            'status' => 'open',
            'due_date' => $now->copy()->subDay()->toDateString(),
        ]);

        $res = $this->actingAs($qc)->get(route('dashboard'))->assertOk();
        $res->assertSee('Temuan lewat due date');
        $res->assertSee('2 temuan jatuh tempo hari ini, 1 terlambat.');
        $res->assertSee(route('qc-finding.index', ['pf' => 'terlambat']));

        Carbon::setTestNow(null);
    }
}
