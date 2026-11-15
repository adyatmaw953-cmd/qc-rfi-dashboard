<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\QcFinding;
use App\Models\Rfi;
use App\Models\User;
use Tests\TestCase;

class RecurringFindingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateTestDatabase();
        $this->withoutVite();
    }

    /** @test */
    public function test_process_finding_number_increments_chronologically_within_same_category(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        // Three category B findings – create in reverse chronological order to verify sorting
        $f3 = $this->makeFinding('B', project: $project, dateOfFinding: '2026-11-03');
        $f1 = $this->makeFinding('B', project: $project, dateOfFinding: '2026-11-01');
        $f2 = $this->makeFinding('B', project: $project, dateOfFinding: '2026-11-02');

        $response = $this->actingAs($qc)
            ->get(route('qc-finding.project', $project))
            ->assertOk();

        $findings = $response->viewData('allFindings');

        $byId = $findings->keyBy('id');

        $this->assertSame(1, $byId[$f1->id]->process_finding_number);
        $this->assertSame(2, $byId[$f2->id]->process_finding_number);
        $this->assertSame(3, $byId[$f3->id]->process_finding_number);

        $this->assertCount(0, $byId[$f1->id]->previous_process_findings);
        $this->assertCount(1, $byId[$f2->id]->previous_process_findings);
        $this->assertCount(2, $byId[$f3->id]->previous_process_findings);
    }

    /** @test */
    public function test_different_categories_do_not_share_numbering(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $b1 = $this->makeFinding('B', project: $project, dateOfFinding: '2026-11-01');
        $e1 = $this->makeFinding('E', project: $project, dateOfFinding: '2026-11-02');
        $b2 = $this->makeFinding('B', project: $project, dateOfFinding: '2026-11-03');

        $response = $this->actingAs($qc)
            ->get(route('qc-finding.project', $project))
            ->assertOk();

        $findings = $response->viewData('allFindings')->keyBy('id');

        $this->assertSame(1, $findings[$b1->id]->process_finding_number);
        $this->assertSame(2, $findings[$b2->id]->process_finding_number);
        $this->assertSame(1, $findings[$e1->id]->process_finding_number, 'Category E should start its own counter at 1');
        $this->assertCount(0, $findings[$e1->id]->previous_process_findings);
    }

    /** @test */
    public function test_first_finding_in_process_has_no_badge_text_in_view(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);
        $project = Project::factory()->create();

        $this->makeFinding('B', project: $project, dateOfFinding: '2026-11-01');
        $this->makeFinding('B', project: $project, dateOfFinding: '2026-11-02');

        $response = $this->actingAs($qc)
            ->get(route('qc-finding.project', $project))
            ->assertOk();

        // Badge should appear for 2nd finding but NOT for the 1st
        $response->assertSee('Temuan ke-2 di proses ini');
        $response->assertDontSee('Temuan ke-1 di proses ini');
    }

    /** @test */
    public function test_findings_from_different_projects_are_isolated(): void
    {
        $qc = User::factory()->create(['role' => 'qc_qa']);

        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        // Both projects have one B finding – each should be numbered 1
        $fa = $this->makeFinding('B', project: $projectA, dateOfFinding: '2026-11-01');
        $fb = $this->makeFinding('B', project: $projectB, dateOfFinding: '2026-11-01');

        $responseA = $this->actingAs($qc)
            ->get(route('qc-finding.project', $projectA))
            ->assertOk();

        $findingA = $responseA->viewData('allFindings')->firstWhere('id', $fa->id);
        $this->assertSame(1, $findingA->process_finding_number);
        $this->assertCount(0, $findingA->previous_process_findings);

        $responseB = $this->actingAs($qc)
            ->get(route('qc-finding.project', $projectB))
            ->assertOk();

        $findingB = $responseB->viewData('allFindings')->firstWhere('id', $fb->id);
        $this->assertSame(1, $findingB->process_finding_number);
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    private function makeFinding(
        string $category,
        string $status = 'open',
        ?string $dateOfFinding = null,
        ?Project $project = null,
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
            'date_of_finding' => $dateOfFinding ?? now()->toDateString(),
        ]);
    }
}
