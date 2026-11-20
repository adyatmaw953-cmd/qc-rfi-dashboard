<?php

namespace Tests\Feature;

use App\Enums\RfiResult;
use App\Models\FindingSubmission;
use App\Models\Project;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FindingSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTestDatabase();
        $this->withoutVite();
    }

    public function test_submission_requires_action_plan_note_and_at_least_one_evidence_file(): void
    {
        Storage::fake('local');
        $finding = $this->makeFinding('B');
        $production = User::factory()->create(['role' => 'production']);
        $url = route('qc-finding.submissions.store', ['finding' => $finding]);
        $validFile = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $this->actingAs($production)
            ->post($url, [
                'note' => 'Bukti pemenuhan.',
                'evidence' => [$validFile],
            ])
            ->assertSessionHasErrors('action_plan');

        $finding->update(['action_plan' => 'Ganti material yang tidak sesuai.']);

        $this->post($url, [
            'note' => '',
            'evidence' => [],
        ])->assertSessionHasErrors(['note', 'evidence']);

        $this->assertDatabaseCount('finding_submissions', 0);
        $this->assertDatabaseCount('finding_evidences', 0);
    }

    public function test_submitter_can_upload_private_evidence_and_duplicate_submission_is_rejected(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00', 'UTC'));
        $finding = $this->makeFinding('B', actionPlan: 'Ganti material');
        $production = User::factory()->create(['role' => 'production']);
        $url = route('qc-finding.submissions.store', ['finding' => $finding]);

        $this->actingAs($production)
            ->post($url, [
                'note' => 'Material pengganti sudah dipasang.',
                'evidence' => [
                    UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
                ],
            ])
            ->assertRedirect(route('qc-finding.project', ['project' => $finding->rfi->project_id]));

        $submission = FindingSubmission::query()->with('evidences')->firstOrFail();
        $this->assertSame(1, $submission->round_no);
        $this->assertSame($production->id, $submission->submitted_by);
        $this->assertSame('2026-10-20 10:00:00', $submission->submitted_at->toDateTimeString());
        $this->assertSame('waiting_verification', $finding->fresh()->status);
        $this->assertCount(2, $submission->evidences);

        foreach ($submission->evidences as $evidence) {
            Storage::disk('local')->assertExists($evidence->path);
            $this->assertStringStartsWith("evidence/{$finding->id}/", $evidence->path);
            $this->assertNotSame($evidence->original_name, basename($evidence->path));
        }

        $this->post($url, [
            'note' => 'Klik ganda.',
            'evidence' => [UploadedFile::fake()->create('again.pdf', 100, 'application/pdf')],
        ])->assertSessionHasErrors('submission');

        $this->assertDatabaseCount('finding_submissions', 1);
        $this->assertDatabaseCount('finding_evidences', 2);
    }

    public function test_submission_accepts_webp_files(): void
    {
        Storage::fake('local');
        $finding = $this->makeFinding('B', actionPlan: 'Perbaiki dimensi flange');
        $production = User::factory()->create(['role' => 'production']);

        $response = $this->actingAs($production)
            ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                'note' => 'Bukti foto webp.',
                'evidence' => [
                    UploadedFile::fake()->create('proof.webp', 200, 'image/webp'),
                ],
            ]);

        $response->assertRedirect(route('qc-finding.project', ['project' => $finding->rfi->project_id]));
        $this->assertDatabaseCount('finding_submissions', 1);
        $this->assertDatabaseCount('finding_evidences', 1);

        $evidence = $finding->submissions()->first()->evidences()->first();
        $this->assertSame('proof.webp', $evidence->original_name);
    }

    public function test_submission_rejects_unsupported_file_types(): void
    {
        $finding = $this->makeFinding('B', actionPlan: 'Perbaiki dimensi');
        $production = User::factory()->create(['role' => 'production']);

        $this->actingAs($production)
            ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                'note' => 'Bukti pekerjaan.',
                'evidence' => [UploadedFile::fake()->create('photo.heic', 10, 'image/heic')],
            ])
            ->assertSessionHasErrors([
                'evidence.0' => 'Format file tidak didukung. Gunakan format JPG, JPEG, PNG, WEBP, atau PDF.',
            ]);

        $this->assertDatabaseCount('finding_submissions', 0);
    }

    public function test_submission_rejects_files_over_ten_megabytes(): void
    {
        $finding = $this->makeFinding('C', actionPlan: 'Ulangi test');
        $production = User::factory()->create(['role' => 'production']);

        $this->actingAs($production)
            ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                'note' => 'Hasil pengujian ulang.',
                'evidence' => [UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')],
            ])
            ->assertSessionHasErrors([
                'evidence.0' => 'Ukuran file evidence maksimal 10 MB per file.',
            ]);

        $this->assertDatabaseCount('finding_submissions', 0);
    }

    public function test_submission_rejects_more_than_five_files(): void
    {
        $finding = $this->makeFinding('D', actionPlan: 'Ulangi finishing');
        $production = User::factory()->create(['role' => 'production']);
        $files = [];

        for ($index = 1; $index <= 6; $index++) {
            $files[] = UploadedFile::fake()->create("evidence-{$index}.pdf", 100, 'application/pdf');
        }

        $this->actingAs($production)
            ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                'note' => 'Finishing sudah diperbaiki.',
                'evidence' => $files,
            ])
            ->assertSessionHasErrors([
                'evidence' => 'Maksimal 5 file bukti yang dapat diunggah.',
            ]);

        $this->assertDatabaseCount('finding_submissions', 0);
    }

    public function test_post_too_large_exception_redirects_back_with_evidence_message(): void
    {
        $finding = $this->makeFinding('B', actionPlan: 'Perbaiki pengelasan');
        $production = User::factory()->create(['role' => 'production']);
        $postUrl = route('qc-finding.submissions.store', ['finding' => $finding]);
        $projectUrl = route('qc-finding.project', ['project' => $finding->rfi->project_id]);

        $postMaxSize = ini_get('post_max_size') ?: '10M';

        $response = $this->actingAs($production)
            ->from($projectUrl)
            ->call('POST', $postUrl, [], [], [], [
                'CONTENT_LENGTH' => 1024 * 1024 * 1024,
            ]);

        $expectedMsg = "Total ukuran file melebihi batas server ({$postMaxSize}). Kurangi jumlah atau ukuran foto.";

        $response->assertRedirect($projectUrl);
        $response->assertSessionHasErrors([
            'evidence' => $expectedMsg,
        ]);
    }

    /**
     * Submission roles per category: B, C, D = production; E = ppic; admin always allowed.
     * Category A has no submission roles.
     */
    public function test_only_submission_roles_can_send_reinspect(): void
    {
        Storage::fake('local');
        $production = User::factory()->create(['role' => 'production']);
        $ppic = User::factory()->create(['role' => 'ppic']);
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $admin = User::factory()->create(['role' => 'admin']);

        // Production can submit for B, C, D
        foreach (['B', 'C', 'D'] as $cat) {
            $finding = $this->makeFinding($cat, actionPlan: "Action plan for {$cat}");
            $this->actingAs($production)
                ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                    'note' => 'Sudah diperbaiki.',
                    'evidence' => [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')],
                ])
                ->assertRedirect(route('qc-finding.project', ['project' => $finding->rfi->project_id]));
        }

        // Production CANNOT submit for A and E
        foreach (['A', 'E'] as $cat) {
            $finding = $this->makeFinding($cat, actionPlan: "Action plan for {$cat}");
            $this->actingAs($production)
                ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                    'note' => 'Sudah diperbaiki.',
                    'evidence' => [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')],
                ])
                ->assertForbidden();
        }

        // PPIC can submit for E
        $findingE = $this->makeFinding('E', actionPlan: 'Vendor lengkapi dokumen');
        $this->actingAs($ppic)
            ->post(route('qc-finding.submissions.store', ['finding' => $findingE]), [
                'note' => 'Dokumen sudah dilengkapi.',
                'evidence' => [UploadedFile::fake()->create('proof_e.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect(route('qc-finding.project', ['project' => $findingE->rfi->project_id]));

        // PPIC CANNOT submit for A, B, C, D
        foreach (['A', 'B', 'C', 'D'] as $cat) {
            $finding = $this->makeFinding($cat, actionPlan: "Action plan for {$cat}");
            $this->actingAs($ppic)
                ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                    'note' => 'Sudah diperbaiki.',
                    'evidence' => [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')],
                ])
                ->assertForbidden();
        }

        // Warehouse cannot submit for any category
        foreach (['A', 'B', 'E'] as $cat) {
            $finding = $this->makeFinding($cat, actionPlan: "Action plan for {$cat}");
            $this->actingAs($warehouse)
                ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                    'note' => 'Sudah diperbaiki.',
                    'evidence' => [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')],
                ])
                ->assertForbidden();
        }

        // Admin can submit
        $findingAdmin = $this->makeFinding('B', actionPlan: 'Action plan admin');
        $this->actingAs($admin)
            ->post(route('qc-finding.submissions.store', ['finding' => $findingAdmin]), [
                'note' => 'Sudah diperbaiki admin.',
                'evidence' => [UploadedFile::fake()->create('proof_admin.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();
    }

    public function test_qc_ok_verification_closes_finding_and_related_rfi_without_changing_rfi_result(): void
    {
        $finding = $this->makeFinding('E', actionPlan: 'Vendor melengkapi dokumen');
        $production = User::factory()->create(['role' => 'production']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        Storage::fake('local');
        $submission = $this->submit($finding, $production);
        $url = route('qc-finding.verify', ['finding' => $finding]);
        $this->travelTo(Carbon::parse('2026-10-20 11:30:00', 'UTC'));

        $this->actingAs($qc)
            ->post($url, ['result' => 'ok'])
            ->assertRedirect(route('qc-finding.project', ['project' => $finding->rfi->project_id]));

        $finding->refresh();
        $this->assertSame('closed', $finding->status);
        $this->assertSame('2026-10-20', $finding->date_of_close->toDateString());
        $this->assertSame('close', $finding->rfi->fresh()->status);
        $this->assertSame(RfiResult::Repair, $finding->rfi->fresh()->result);
        $this->assertSame('ok', $submission->fresh()->verification_result);
        $this->assertSame($qc->id, $submission->fresh()->verified_by);
        $this->assertSame('2026-10-20 11:30:00', $submission->fresh()->verified_at->toDateTimeString());

        $this->post($url, ['result' => 'ok'])
            ->assertSessionHasErrors('result');
    }

    public function test_qc_not_ok_requires_a_note_reopens_finding_and_allows_a_new_round(): void
    {
        Storage::fake('local');
        $finding = $this->makeFinding('B', actionPlan: 'Perbaiki persiapan');
        $production = User::factory()->create(['role' => 'production']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $firstSubmission = $this->submit($finding, $production);
        $verifyUrl = route('qc-finding.verify', ['finding' => $finding]);

        $this->actingAs($qc)
            ->post($verifyUrl, ['result' => 'not_ok'])
            ->assertSessionHasErrors('verification_note');

        $this->post($verifyUrl, [
            'result' => 'not_ok',
            'verification_note' => 'Bukti foto belum memperlihatkan seluruh bagian.',
        ])->assertRedirect(route('qc-finding.project', ['project' => $finding->rfi->project_id]));

        $this->assertSame('open', $finding->fresh()->status);
        $this->assertSame(RfiResult::Repair, $finding->fresh()->result);
        $this->assertSame('not_ok', $firstSubmission->fresh()->verification_result);
        $this->assertSame(
            'Bukti foto belum memperlihatkan seluruh bagian.',
            $firstSubmission->fresh()->verification_note
        );

        $this->actingAs($production)
            ->post(route('qc-finding.submissions.store', ['finding' => $finding]), [
                'note' => 'Foto tambahan memperlihatkan seluruh bagian.',
                'evidence' => [UploadedFile::fake()->create('additional.pdf', 100, 'application/pdf')],
            ])->assertRedirect(route('qc-finding.project', ['project' => $finding->rfi->project_id]));

        $secondSubmission = $finding->submissions()->reorder()->orderByDesc('round_no')->firstOrFail();
        $this->assertSame(2, $secondSubmission->round_no);
        $this->assertSame('waiting_verification', $finding->fresh()->status);

        $this->actingAs($qc)
            ->get(route('qc-finding.project', ['project' => $finding->rfi->project_id]))
            ->assertSee('Putaran 1')
            ->assertSee('Putaran 2')
            ->assertSee('Bukti foto belum memperlihatkan seluruh bagian.')
            ->assertSee('Foto tambahan memperlihatkan seluruh bagian.')
            ->assertSee('proof.pdf')
            ->assertSee('additional.pdf')
            ->assertSee('1 evidence')
            ->assertSee('Menunggu verifikasi');
    }

    public function test_production_cannot_verify_a_submission(): void
    {
        $finding = $this->makeFinding('B', actionPlan: 'Perbaiki persiapan');
        $production = User::factory()->create(['role' => 'production']);
        $this->submit($finding, $production);

        $this->actingAs($production)
            ->post(route('qc-finding.verify', ['finding' => $finding]), [
                'result' => 'ok',
            ])
            ->assertForbidden();

        $this->assertSame('waiting_verification', $finding->fresh()->status);
        $this->assertNull($finding->submissions()->firstOrFail()->verification_result);
    }

    /**
     * Download access uses findings.view_categories.
     * production sees all A-E -> allowed to download cat A and E evidence.
     * ppic sees only E -> cannot download cat A evidence.
     */
    public function test_only_roles_with_view_category_access_can_download_evidence(): void
    {
        Storage::fake('local');
        $production = User::factory()->create(['role' => 'production']);

        // Cat A evidence: production CAN download (production sees A-E)
        $findingA = $this->makeFinding('A', actionPlan: 'Perbaiki penerimaan');
        $submissionA = $this->submit($findingA, $production);
        $evidenceA = $submissionA->evidences()->firstOrFail();
        $downloadUrlA = route('qc-finding.evidence.download', ['evidence' => $evidenceA]);

        $this->actingAs($production)->get($downloadUrlA)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'attachment; filename=proof.pdf');

        // Cat E evidence: production CAN download
        $findingE = $this->makeFinding('E', actionPlan: 'Vendor lengkapi dokumen');
        $submissionE = $this->submit($findingE, $production);
        $evidenceE = $submissionE->evidences()->firstOrFail();

        $this->actingAs($production)
            ->get(route('qc-finding.evidence.download', ['evidence' => $evidenceE]))
            ->assertOk();

        // ppic sees only E -> cannot download cat A evidence
        $ppic = User::factory()->create(['role' => 'ppic']);
        $this->actingAs($ppic)->get($downloadUrlA)->assertForbidden();

        // warehouse sees only A -> cannot download cat E evidence
        $warehouse = User::factory()->create(['role' => 'warehouse']);
        $this->actingAs($warehouse)
            ->get(route('qc-finding.evidence.download', ['evidence' => $evidenceE]))
            ->assertForbidden();

        // warehouse CAN download cat A evidence
        $this->actingAs($warehouse)->get($downloadUrlA)->assertOk();
    }

    private function makeFinding(string $category, ?string $actionPlan = null): QcFinding
    {
        $creator = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();
        $rfi = Rfi::factory()->for($project)->create([
            'category' => $category,
            'created_by' => $creator->id,
            'rfi_no' => null,
        ]);
        $rfi->update(['rfi_no' => sprintf('RFI-%s-%04d', $category, $rfi->id)]);

        return QcFinding::factory()->for($rfi)->create([
            'action_plan' => $actionPlan,
            'status' => 'open',
            'result' => RfiResult::Repair,
        ]);
    }

    public function test_qc_verification_not_ok_recalculates_due_date_to_tomorrow_asia_jakarta(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 15:30:00', 'Asia/Jakarta'));
        $finding = $this->makeFinding('B', actionPlan: 'Perbaiki persiapan');
        $finding->update(['due_date' => '2026-11-01']); // previous due date
        $production = User::factory()->create(['role' => 'production']);
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $this->submit($finding, $production);

        $verifyUrl = route('qc-finding.verify', ['finding' => $finding]);

        $this->actingAs($qc)
            ->post($verifyUrl, [
                'result' => 'not_ok',
                'verification_note' => 'Pekerjaan masih belum rapi.',
            ])
            ->assertRedirect();

        $fresh = $finding->fresh();
        $this->assertSame('open', $fresh->status);
        $this->assertSame('2026-11-11', $fresh->due_date->toDateString());

        Carbon::setTestNow(null);
    }

    public function test_production_reinspect_resets_due_date_to_tomorrow_asia_jakarta_without_sending_due_date_notification(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-11-10 14:00:00', 'Asia/Jakarta'));
        $finding = $this->makeFinding('B', actionPlan: 'Perbaikan');
        $finding->update(['due_date' => '2026-11-05']);
        $production = User::factory()->create(['role' => 'production']);

        $url = route('qc-finding.submissions.store', ['finding' => $finding]);

        $this->actingAs($production)
            ->post($url, [
                'note' => 'Reinspect diajukan.',
                'evidence' => [
                    UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $fresh = $finding->fresh();
        $this->assertSame('waiting_verification', $fresh->status);
        $this->assertSame('2026-11-11', $fresh->due_date->toDateString());

        $this->assertDatabaseHas('finding_histories', [
            'qc_finding_id' => $finding->id,
            'event' => 'due_date_changed',
            'new_value' => '2026-11-11',
        ]);
        $this->assertDatabaseHas('finding_activities', [
            'qc_finding_id' => $finding->id,
            'type' => 'due_date_changed',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'data->type' => 'finding.due_date_changed',
        ]);

        Carbon::setTestNow(null);
    }

    private function submit(QcFinding $finding, User $submitter): FindingSubmission
    {
        $submission = $finding->submissions()->create([
            'round_no' => ($finding->submissions()->max('round_no') ?? 0) + 1,
            'note' => 'Bukti pekerjaan sudah diperbaiki.',
            'submitted_by' => $submitter->id,
            'submitted_at' => Carbon::now('UTC'),
        ]);

        $path = "evidence/{$finding->id}/proof.pdf";
        Storage::disk('local')->put($path, 'evidence content');
        $submission->evidences()->create([
            'original_name' => 'proof.pdf',
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => 16,
        ]);
        $finding->update(['status' => 'waiting_verification']);

        return $submission->fresh();
    }
}
