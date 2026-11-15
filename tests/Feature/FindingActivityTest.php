<?php

namespace Tests\Feature;

use App\Models\FindingActivity;
use App\Models\Project;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use App\Services\FindingActivityPresenter;
use App\Services\FindingActivityRecorder;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class FindingActivityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateTestDatabase();
        $this->withoutVite();
    }

    // ─── Action plan ────────────────────────────────────────────────────────

    public function test_update_action_plan_records_activity_first_save(): void
    {
        $finding = $this->makeFinding('B');
        $production = User::factory()->create(['role' => 'production']);

        $this->actingAs($production)
            ->post(route('qc-finding.action-plan.update', ['finding' => $finding]), [
                'action_plan' => 'Plan pertama',
            ])
            ->assertRedirect();

        $activity = FindingActivity::where('qc_finding_id', $finding->id)
            ->where('type', FindingActivity::TYPE_ACTION_PLAN_SAVED)
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($production->id, $activity->actor_id);
        $this->assertSame($production->name, $activity->actor_name);
        $this->assertSame('production', $activity->actor_role);
        $this->assertTrue($activity->meta['first']);
    }

    public function test_update_action_plan_records_activity_subsequent_save(): void
    {
        $finding = $this->makeFinding('B', actionPlan: 'Plan awal');
        $production = User::factory()->create(['role' => 'production']);

        $this->actingAs($production)
            ->post(route('qc-finding.action-plan.update', ['finding' => $finding]), [
                'action_plan' => 'Plan baru',
            ])
            ->assertRedirect();

        $activity = FindingActivity::where('qc_finding_id', $finding->id)
            ->where('type', FindingActivity::TYPE_ACTION_PLAN_SAVED)
            ->first();

        $this->assertNotNull($activity);
        $this->assertFalse($activity->meta['first']);
    }

    // ─── Due date ───────────────────────────────────────────────────────────

    public function test_update_due_date_records_activity_only_when_changed(): void
    {
        $finding = $this->makeFinding('B');
        $finding->update(['due_date' => '2026-11-15']);
        $qc = User::factory()->create(['role' => 'qc_qa']);

        $this->actingAs($qc)
            ->post(route('qc-finding.due-date.update', ['finding' => $finding]), [
                'due_date' => '2026-11-20',
            ])
            ->assertRedirect();

        $this->assertSame(1, FindingActivity::where('qc_finding_id', $finding->id)
            ->where('type', FindingActivity::TYPE_DUE_DATE_CHANGED)->count());

        $activity = FindingActivity::where('qc_finding_id', $finding->id)
            ->where('type', FindingActivity::TYPE_DUE_DATE_CHANGED)->first();
        $this->assertSame('2026-11-15', $activity->meta['from']);
        $this->assertSame('2026-11-20', $activity->meta['to']);

        // Same value → no new record
        $this->actingAs($qc)
            ->post(route('qc-finding.due-date.update', ['finding' => $finding]), [
                'due_date' => '2026-11-20',
            ]);

        $this->assertSame(1, FindingActivity::where('qc_finding_id', $finding->id)
            ->where('type', FindingActivity::TYPE_DUE_DATE_CHANGED)->count());
    }

    // ─── Re-inspect submitted ───────────────────────────────────────────────

    public function test_store_submission_records_reinspect_submitted_activity(): void
    {
        Storage::fake('local');
        $finding = $this->makeFinding('B', actionPlan: 'Plan OK');
        $production = User::factory()->create(['role' => 'production']);

        $this->actingAs($production)
            ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                'note' => 'Sudah diperbaiki',
                'evidence' => [UploadedFile::fake()->create('ev.pdf', 10, 'application/pdf')],
            ])
            ->assertRedirect();

        $activity = FindingActivity::where('qc_finding_id', $finding->id)
            ->where('type', FindingActivity::TYPE_REINSPECT_SUBMITTED)->first();

        $this->assertNotNull($activity);
        $this->assertSame($production->id, $activity->actor_id);
        $this->assertSame(1, $activity->meta['round']);
        $this->assertNotNull($activity->meta['submission_id']);
    }

    // ─── Reinspect verified ─────────────────────────────────────────────────

    public function test_verify_submission_ok_records_reinspect_verified_ok(): void
    {
        Storage::fake('local');
        $finding = $this->makeFindingWaitingVerification();
        $qc = User::factory()->create(['role' => 'qc_qa']);

        $this->actingAs($qc)
            ->post(route('qc-finding.verify', ['finding' => $finding]), [
                'result' => 'ok',
                'verification_note' => 'Sempurna.',
            ])
            ->assertRedirect();

        $activity = FindingActivity::where('qc_finding_id', $finding->id)
            ->where('type', FindingActivity::TYPE_REINSPECT_VERIFIED)->first();

        $this->assertNotNull($activity);
        $this->assertSame('ok', $activity->meta['result']);
        $this->assertSame('Sempurna.', $activity->note);
        $this->assertSame($qc->id, $activity->actor_id);
    }

    public function test_verify_submission_not_ok_records_reinspect_verified_not_ok_and_due_date_changed(): void
    {
        Storage::fake('local');
        $finding = $this->makeFindingWaitingVerification('2026-11-10');
        $qc = User::factory()->create(['role' => 'qc_qa']);

        $this->actingAs($qc)
            ->post(route('qc-finding.verify', ['finding' => $finding]), [
                'result' => 'not_ok',
                'verification_note' => 'Belum sesuai.',
            ])
            ->assertRedirect();

        $verified = FindingActivity::where('qc_finding_id', $finding->id)
            ->where('type', FindingActivity::TYPE_REINSPECT_VERIFIED)->first();
        $this->assertNotNull($verified);
        $this->assertSame('not_ok', $verified->meta['result']);
        $this->assertSame('Belum sesuai.', $verified->note);

        $dueDateActivity = FindingActivity::where('qc_finding_id', $finding->id)
            ->where('type', FindingActivity::TYPE_DUE_DATE_CHANGED)->first();
        $this->assertNotNull($dueDateActivity);
        $this->assertSame('2026-11-10', $dueDateActivity->meta['from']);
    }

    // ─── Append-only model ──────────────────────────────────────────────────

    public function test_finding_activity_cannot_be_updated(): void
    {
        $finding = $this->makeFinding('B');
        $activity = FindingActivityRecorder::record(
            finding: $finding,
            type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
            actor: null,
        );

        $this->expectException(LogicException::class);
        $activity->update(['note' => 'tampering']);
    }

    public function test_finding_activity_cannot_be_deleted(): void
    {
        $finding = $this->makeFinding('B');
        $activity = FindingActivityRecorder::record(
            finding: $finding,
            type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
            actor: null,
        );

        $this->expectException(LogicException::class);
        $activity->delete();
    }

    // ─── latestActivity ordering ────────────────────────────────────────────

    public function test_latest_activity_returns_the_most_recent(): void
    {
        $finding = $this->makeFinding('B');

        FindingActivityRecorder::record(
            finding: $finding,
            type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
            actor: null,
            at: Carbon::parse('2026-11-10 08:00:00', 'UTC'),
        );
        FindingActivityRecorder::record(
            finding: $finding,
            type: FindingActivity::TYPE_DUE_DATE_CHANGED,
            actor: null,
            meta: ['from' => '2026-11-10', 'to' => '2026-11-15'],
            at: Carbon::parse('2026-11-11 08:00:00', 'UTC'),
        );

        $latest = $finding->latestActivity()->first();
        $this->assertNotNull($latest);
        $this->assertSame(FindingActivity::TYPE_DUE_DATE_CHANGED, $latest->type);
    }

    // ─── Presenter ──────────────────────────────────────────────────────────

    public function test_presenter_action_plan_saved_first(): void
    {
        $finding = $this->makeFinding('B');
        $user = User::factory()->create(['name' => 'Budi', 'role' => 'production']);

        $activity = FindingActivityRecorder::record(
            finding: $finding,
            type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
            actor: $user,
            meta: ['first' => true],
        );

        $presented = FindingActivityPresenter::present($activity);

        $this->assertSame('Budi', $presented['actor']);
        $this->assertSame('menyimpan action plan', $presented['verb']);
        $this->assertSame('neutral', $presented['tone']);
        $this->assertSame('pencil', $presented['icon']);
    }

    public function test_presenter_reinspect_verified_ok(): void
    {
        $finding = $this->makeFinding('B');
        $activity = FindingActivityRecorder::record(
            finding: $finding,
            type: FindingActivity::TYPE_REINSPECT_VERIFIED,
            actor: null,
            meta: ['round' => 2, 'submission_id' => 5, 'result' => 'ok'],
            note: 'Bagus.',
        );

        $presented = FindingActivityPresenter::present($activity);

        $this->assertSame('ok', $presented['tone']);
        $this->assertSame('check', $presented['icon']);
        $this->assertContains('OK', $presented['chips']);
        $this->assertSame('Bagus.', $presented['note']);
        $this->assertStringContainsString('putaran 2', $presented['object']);
    }

    public function test_presenter_reinspect_verified_not_ok(): void
    {
        $finding = $this->makeFinding('B');
        $activity = FindingActivityRecorder::record(
            finding: $finding,
            type: FindingActivity::TYPE_REINSPECT_VERIFIED,
            actor: null,
            meta: ['round' => 1, 'submission_id' => 1, 'result' => 'not_ok'],
        );

        $presented = FindingActivityPresenter::present($activity);

        $this->assertSame('warn', $presented['tone']);
        $this->assertContains('Belum OK', $presented['chips']);
    }

    public function test_presenter_due_date_changed_formats_dates(): void
    {
        $finding = $this->makeFinding('B');
        $activity = FindingActivityRecorder::record(
            finding: $finding,
            type: FindingActivity::TYPE_DUE_DATE_CHANGED,
            actor: null,
            meta: ['from' => '2026-11-12', 'to' => '2026-11-18'],
        );

        $presented = FindingActivityPresenter::present($activity);

        $this->assertStringContainsString('12 Nov', $presented['object']);
        $this->assertStringContainsString('18 Nov', $presented['object']);
        $this->assertSame('neutral', $presented['tone']);
    }

    public function test_presenter_relative_time_baru_saja(): void
    {
        $result = FindingActivityPresenter::relativeTime(Carbon::now('UTC'));
        $this->assertSame('Baru saja', $result);
    }

    public function test_presenter_relative_time_hours(): void
    {
        $dt = Carbon::now('UTC')->subHours(3)->subMinutes(10);
        $result = FindingActivityPresenter::relativeTime($dt);
        $this->assertSame('3 jam lalu', $result);
    }

    public function test_presenter_relative_time_days(): void
    {
        $dt = Carbon::now('UTC')->subDays(2);
        $result = FindingActivityPresenter::relativeTime($dt);
        $this->assertSame('2 hari lalu', $result);
    }

    public function test_presenter_relative_time_over_30_days_shows_absolute(): void
    {
        $dt = Carbon::parse('2026-10-01 10:00:00', 'UTC');
        $result = FindingActivityPresenter::relativeTime($dt);
        // Should return absolute date string (not relative)
        $this->assertStringContainsString('2026', $result);
        $this->assertStringNotContainsString('lalu', $result);
    }

    public function test_presenter_unknown_actor_fallback(): void
    {
        $finding = $this->makeFinding('B');
        $activity = FindingActivityRecorder::record(
            finding: $finding,
            type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
            actor: null,
            meta: ['first' => false],
        );

        $presented = FindingActivityPresenter::present($activity);
        $this->assertSame('Sistem', $presented['actor']);
    }

    // ─── Backfill command ───────────────────────────────────────────────────

    public function test_backfill_command_is_idempotent(): void
    {
        $finding = $this->makeFinding('B', actionPlan: 'Plan lama');

        $this->artisan('findings:backfill-activities')->assertSuccessful();

        $count = FindingActivity::where('qc_finding_id', $finding->id)->count();
        $this->assertGreaterThan(0, $count);

        // Run again — should skip (idempotent)
        $this->artisan('findings:backfill-activities')->assertSuccessful();

        $this->assertSame($count, FindingActivity::where('qc_finding_id', $finding->id)->count());
    }

    public function test_backfill_dry_run_writes_nothing(): void
    {
        $finding = $this->makeFinding('B');

        $this->artisan('findings:backfill-activities --dry-run')->assertSuccessful();

        $this->assertSame(0, FindingActivity::where('qc_finding_id', $finding->id)->count());
    }

    // ─── RFI review records activities ──────────────────────────────────────

    public function test_rfi_review_nc_records_rfi_submitted_and_finding_created(): void
    {
        $project = Project::factory()->create();
        $submitter = User::factory()->create(['role' => 'production', 'name' => 'Ali Submitter']);
        $reviewer = User::factory()->create(['role' => 'qc_qa', 'name' => 'Dani QC']);

        $rfi = Rfi::factory()->for($project)->create([
            'category' => 'B',
            'status' => 'open',
            'result' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'created_by' => $submitter->id,
            'submitted_at' => Carbon::now('UTC')->subHour(),
            'rfi_no' => 'RFI-B-TEST-001',
        ]);

        $this->actingAs($reviewer)
            ->post(route('rfi.review', ['kategori' => 'B', 'rfi' => $rfi]), [
                'result' => 'repair',
                'qc_note' => 'Las tidak sesuai',
            ])
            ->assertRedirect();

        $finding = QcFinding::where('rfi_id', $rfi->id)->first();
        $this->assertNotNull($finding);

        $rfiSubmittedActivity = FindingActivity::where('qc_finding_id', $finding->id)
            ->where('type', FindingActivity::TYPE_RFI_SUBMITTED)->first();
        $this->assertNotNull($rfiSubmittedActivity);
        $this->assertSame($submitter->id, $rfiSubmittedActivity->actor_id);
        $this->assertSame('RFI-B-TEST-001', $rfiSubmittedActivity->meta['rfi_no']);

        $findingCreatedActivity = FindingActivity::where('qc_finding_id', $finding->id)
            ->where('type', FindingActivity::TYPE_FINDING_CREATED)->first();
        $this->assertNotNull($findingCreatedActivity);
        $this->assertSame($reviewer->id, $findingCreatedActivity->actor_id);
        $this->assertSame('repair', $findingCreatedActivity->meta['disposition']);
    }

    // ─── TAHAP RA-2 View & Controller Tests ─────────────────────────────────

    public function test_activity_panel_displays_for_selected_finding_with_latest_first_and_terbaru_chip(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa', 'name' => 'Dani QC']);
        $prod = User::factory()->create(['role' => 'production', 'name' => 'Rina Prod']);
        $finding = $this->makeFinding('B');

        FindingActivityRecorder::record(
            finding: $finding,
            type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
            actor: $prod,
            meta: ['first' => true],
            at: Carbon::now('UTC')->subHours(3),
        );
        FindingActivityRecorder::record(
            finding: $finding,
            type: FindingActivity::TYPE_REINSPECT_SUBMITTED,
            actor: $prod,
            meta: ['round' => 1, 'submission_id' => 1],
            at: Carbon::now('UTC')->subHours(1),
        );

        $response = $this->actingAs($qc)
            ->get(route('qc-finding.project', ['project' => $finding->rfi->project_id]));

        $response->assertOk()
            ->assertSee('Riwayat aktivitas')
            ->assertSee($finding->rfi->rfi_no)
            ->assertSee('Rina Prod')
            ->assertSee('Terbaru')
            ->assertSee('Putaran 1');
    }

    public function test_buka_query_param_switches_selected_finding(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $finding1 = $this->makeFindingForProject('B', $project, 'RFI-B-0001');
        $finding2 = $this->makeFindingForProject('B', $project, 'RFI-B-0002');

        FindingActivityRecorder::record(
            finding: $finding1,
            type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
            actor: $qc,
            meta: ['first' => true],
        );
        FindingActivityRecorder::record(
            finding: $finding2,
            type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
            actor: $qc,
            meta: ['first' => true],
        );

        // When requesting ?buka=finding2->id
        $response = $this->actingAs($qc)
            ->get(route('qc-finding.project', [
                'project' => $project->id,
                'buka' => $finding2->id,
            ]));

        $response->assertOk();
        // Finding 2 is in panel header
        $response->assertSee('Temuan <b class="font-semibold text-brand">RFI-B-0002</b>', false);
        // Finding 1 has "Buka detail" button
        $response->assertSee('buka='.$finding1->id, false);
    }

    public function test_aktivitas_terakhir_appears_on_compact_card_and_not_on_open_card(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $prod = User::factory()->create(['role' => 'production', 'name' => 'Budi']);
        $project = Project::factory()->create();

        $openFinding = $this->makeFindingForProject('B', $project, 'RFI-B-OPEN');
        $compactFinding = $this->makeFindingForProject('B', $project, 'RFI-B-COMPACT');

        FindingActivityRecorder::record(
            finding: $openFinding,
            type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
            actor: $prod,
            meta: ['first' => true],
        );
        FindingActivityRecorder::record(
            finding: $compactFinding,
            type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
            actor: $prod,
            meta: ['first' => true],
        );

        $response = $this->actingAs($qc)
            ->get(route('qc-finding.project', [
                'project' => $project->id,
                'buka' => $openFinding->id,
            ]));

        $response->assertOk();
        // Compact finding shows "Aktivitas terakhir"
        $response->assertSee('Aktivitas terakhir:');
        $response->assertSee('Budi menyimpan action plan');
    }

    public function test_eager_loading_query_count_does_not_grow_with_findings(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        for ($i = 1; $i <= 3; $i++) {
            $f = $this->makeFindingForProject('B', $project, "RFI-B-COUNT-{$i}");
            FindingActivityRecorder::record(
                finding: $f,
                type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
                actor: $qc,
                meta: ['first' => true],
            );
        }

        DB::enableQueryLog();
        $this->actingAs($qc)->get(route('qc-finding.project', ['project' => $project->id]))->assertOk();
        $queryCount3 = count(DB::getQueryLog());

        // Add 3 more findings
        for ($i = 4; $i <= 6; $i++) {
            $f = $this->makeFindingForProject('B', $project, "RFI-B-COUNT-{$i}");
            FindingActivityRecorder::record(
                finding: $f,
                type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
                actor: $qc,
                meta: ['first' => true],
            );
        }

        DB::flushQueryLog();
        $this->actingAs($qc)->get(route('qc-finding.project', ['project' => $project->id]))->assertOk();
        $queryCount6 = count(DB::getQueryLog());

        // Query count should be identical (no N+1 queries per finding)
        $this->assertSame($queryCount3, $queryCount6);
    }

    public function test_role_access_filtering_for_project_findings(): void
    {
        $project = Project::factory()->create();
        $this->makeFindingForProject('A', $project, 'RFI-A-MAT');
        $this->makeFindingForProject('E', $project, 'RFI-E-SUBCON');
        $this->makeFindingForProject('B', $project, 'RFI-B-PROD');

        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $ppic = User::factory()->create(['role' => 'ppic']);

        // Warehouse only sees category A
        $resWh = $this->actingAs($warehouse)->get(route('qc-finding.project', ['project' => $project->id]))->assertOk();
        $resWh->assertSee('RFI-A-MAT');
        $resWh->assertDontSee('RFI-E-SUBCON');
        $resWh->assertDontSee('RFI-B-PROD');

        // PPIC only sees category E
        $resPpic = $this->actingAs($ppic)->get(route('qc-finding.project', ['project' => $project->id]))->assertOk();
        $resPpic->assertSee('RFI-E-SUBCON');
        $resPpic->assertDontSee('RFI-A-MAT');
        $resPpic->assertDontSee('RFI-B-PROD');
    }

    public function test_finding_without_activities_renders_empty_state_without_error(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();
        $finding = $this->makeFindingForProject('B', $project, 'RFI-B-EMPTY');

        $response = $this->actingAs($qc)
            ->get(route('qc-finding.project', ['project' => $project->id, 'buka' => $finding->id]));

        $response->assertOk()
            ->assertSee('Riwayat aktivitas')
            ->assertSee('Belum ada aktivitas untuk temuan ini.');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function makeFindingForProject(string $category, Project $project, string $rfiNo): QcFinding
    {
        $submitter = User::factory()->create(['role' => 'production']);
        $rfi = Rfi::factory()->for($project)->create([
            'category' => $category,
            'created_by' => $submitter->id,
            'submitted_at' => Carbon::now('UTC')->subHours(2),
            'rfi_no' => $rfiNo,
        ]);

        return QcFinding::factory()->for($rfi)->create([
            'status' => 'open',
        ]);
    }

    private function makeFinding(string $category, ?string $actionPlan = null): QcFinding
    {
        $submitter = User::factory()->create(['role' => 'production', 'name' => 'Submitter']);
        $project = Project::factory()->create();
        $rfi = Rfi::factory()->for($project)->create([
            'category' => $category,
            'created_by' => $submitter->id,
            'submitted_at' => Carbon::now('UTC')->subHours(2),
            'rfi_no' => sprintf('RFI-%s-%04d', $category, rand(1, 9999)),
        ]);

        return QcFinding::factory()->for($rfi)->create([
            'action_plan' => $actionPlan,
            'status' => 'open',
        ]);
    }

    private function makeFindingWaitingVerification(?string $dueDate = null): QcFinding
    {
        Storage::fake('local');
        $finding = $this->makeFinding('B', actionPlan: 'Plan OK');
        if ($dueDate) {
            $finding->update(['due_date' => $dueDate]);
        }

        $production = User::factory()->create(['role' => 'production']);

        $this->actingAs($production)
            ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                'note' => 'Submit',
                'evidence' => [UploadedFile::fake()->create('ev.pdf', 10, 'application/pdf')],
            ]);

        return $finding->fresh();
    }
}
