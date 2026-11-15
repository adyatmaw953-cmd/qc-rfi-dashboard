<?php

namespace Tests\Feature;

use App\Enums\RfiResult;
use App\Models\FindingHistory;
use App\Models\Project;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FindingHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTestDatabase();
        $this->withoutVite();
    }

    public function test_update_action_plan_records_history(): void
    {
        $finding = $this->makeFinding('B', actionPlan: 'Plan awal');
        $production = User::factory()->create(['role' => 'production']);

        $this->actingAs($production)
            ->post(route('qc-finding.action-plan.update', ['finding' => $finding]), [
                'action_plan' => 'Plan perbaikan baru',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('finding_histories', [
            'qc_finding_id' => $finding->id,
            'event' => 'action_plan_updated',
            'actor_id' => $production->id,
            'old_value' => 'Plan awal',
            'new_value' => 'Plan perbaikan baru',
        ]);
    }

    public function test_update_due_date_records_history_only_when_changed(): void
    {
        $finding = $this->makeFinding('B');
        $finding->update(['due_date' => '2026-11-15']);
        $qc = User::factory()->create(['role' => 'qc_qa']);

        // Update with changed date -> records history
        $this->actingAs($qc)
            ->post(route('qc-finding.due-date.update', ['finding' => $finding]), [
                'due_date' => '2026-11-20',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('finding_histories', [
            'qc_finding_id' => $finding->id,
            'event' => 'due_date_changed',
            'actor_id' => $qc->id,
            'old_value' => '2026-11-15',
            'new_value' => '2026-11-20',
        ]);

        $this->assertSame(1, FindingHistory::where('event', 'due_date_changed')->count());

        // Update with same date -> does not record history
        $this->post(route('qc-finding.due-date.update', ['finding' => $finding]), [
            'due_date' => '2026-11-20',
        ])->assertRedirect();

        $this->assertSame(1, FindingHistory::where('event', 'due_date_changed')->count());
    }

    public function test_store_submission_records_history(): void
    {
        Storage::fake('local');
        $finding = $this->makeFinding('B', actionPlan: 'Rencana kerja');
        $production = User::factory()->create(['role' => 'production']);

        $this->actingAs($production)
            ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                'note' => 'Sudah diperbaiki.',
                'evidence' => [UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf')],
            ])
            ->assertRedirect();

        $history = FindingHistory::where('qc_finding_id', $finding->id)
            ->where('event', 'reinspect_submitted')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame($production->id, $history->actor_id);
        $this->assertSame(['round_no' => 1], $history->meta);
    }

    public function test_verify_submission_records_verified_rejected_and_ok_histories(): void
    {
        Storage::fake('local');
        $finding = $this->makeFinding('B', actionPlan: 'Rencana kerja');
        $production = User::factory()->create(['role' => 'production']);
        $qc = User::factory()->create(['role' => 'qc_qa']);

        // Submit round 1
        $this->actingAs($production)
            ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                'note' => 'Perbaikan putaran 1',
                'evidence' => [UploadedFile::fake()->create('proof1.pdf', 50, 'application/pdf')],
            ]);

        // QC verifies rejected (not_ok)
        $this->actingAs($qc)
            ->post(route('qc-finding.verify', ['finding' => $finding]), [
                'result' => 'not_ok',
                'verification_note' => 'Kurang rapi.',
            ])
            ->assertRedirect();

        $rejectedHistory = FindingHistory::where('qc_finding_id', $finding->id)
            ->where('event', 'verified_rejected')
            ->first();

        $this->assertNotNull($rejectedHistory);
        $this->assertSame($qc->id, $rejectedHistory->actor_id);
        $this->assertSame(1, $rejectedHistory->meta['round_no']);
        $this->assertSame('Kurang rapi.', $rejectedHistory->meta['verification_note']);
        $this->assertNotNull($rejectedHistory->new_value);

        // Submit round 2
        $this->actingAs($production)
            ->post(route('qc-finding.submissions.store', ['finding' => $finding->fresh()]), [
                'note' => 'Perbaikan putaran 2',
                'evidence' => [UploadedFile::fake()->create('proof2.pdf', 50, 'application/pdf')],
            ]);

        // QC verifies OK
        $this->actingAs($qc)
            ->post(route('qc-finding.verify', ['finding' => $finding->fresh()]), [
                'result' => 'ok',
                'verification_note' => 'Sudah sesuai.',
            ])
            ->assertRedirect();

        $okHistory = FindingHistory::where('qc_finding_id', $finding->id)
            ->where('event', 'verified_ok')
            ->first();

        $this->assertNotNull($okHistory);
        $this->assertSame($qc->id, $okHistory->actor_id);
        $this->assertSame(2, $okHistory->meta['round_no']);
        $this->assertSame('Sudah sesuai.', $okHistory->meta['verification_note']);
    }

    public function test_action_incoming_material_records_closed_incoming_history(): void
    {
        $finding = $this->makeFinding('A');
        $qc = User::factory()->create(['role' => 'qc_qa']);

        $this->actingAs($qc)
            ->post(route('qc-finding.incoming-action', ['finding' => $finding]), [
                'result' => 'use_as_is',
                'items' => [
                    [
                        'materials' => ['Plat Baja A36'],
                        'reason' => 'Dimensi toleransi masih diterima.',
                    ],
                ],
            ])
            ->assertRedirect();

        $history = FindingHistory::where('qc_finding_id', $finding->id)
            ->where('event', 'closed_incoming')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame($qc->id, $history->actor_id);
        $this->assertSame(['result' => 'use_as_is'], $history->meta);
    }

    public function test_project_page_displays_activity_timeline_and_submission_badges(): void
    {
        Storage::fake('local');
        $finding = $this->makeFinding('B', actionPlan: 'Action plan test');
        $production = User::factory()->create(['role' => 'production', 'name' => 'Budi Operator']);
        $qc = User::factory()->create(['role' => 'qc_qa', 'name' => 'Rina QC']);

        // Set RFI submitted and reviewed info
        $finding->rfi->update([
            'submitted_at' => Carbon::now('UTC')->subHours(5),
            'reviewed_at' => Carbon::now('UTC')->subHours(4),
            'reviewed_by' => $qc->id,
        ]);

        // 1 Submission rejected
        $this->actingAs($production)
            ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                'note' => 'Submit bukti',
                'evidence' => [UploadedFile::fake()->create('ev.pdf', 20, 'application/pdf')],
            ]);

        $this->actingAs($qc)
            ->post(route('qc-finding.verify', ['finding' => $finding]), [
                'result' => 'not_ok',
                'verification_note' => 'Perbaiki lagi',
            ]);

        // Check project view
        $response = $this->actingAs($qc)
            ->get(route('qc-finding.project', ['project' => $finding->rfi->project_id]));

        $response->assertOk()
            ->assertSee('Re-inspect 1x')
            ->assertSee('Not OK 1x')
            ->assertSee('History Finding')
            ->assertSee('Riwayat aktivitas')
            ->assertSee('Riwayat Re-Inspect');
    }

    private function makeFinding(string $category, ?string $actionPlan = null): QcFinding
    {
        $creator = User::factory()->create(['role' => 'qc_qa', 'name' => 'Creator RFI']);
        $project = Project::factory()->create();
        $rfi = Rfi::factory()->for($project)->create([
            'category' => $category,
            'created_by' => $creator->id,
            'rfi_no' => null,
            'submitted_at' => Carbon::now('UTC')->subHours(2),
        ]);
        $rfi->update(['rfi_no' => sprintf('RFI-%s-%04d', $category, $rfi->id)]);

        return QcFinding::factory()->for($rfi)->create([
            'action_plan' => $actionPlan,
            'status' => 'open',
            'result' => RfiResult::Repair,
        ]);
    }
}
