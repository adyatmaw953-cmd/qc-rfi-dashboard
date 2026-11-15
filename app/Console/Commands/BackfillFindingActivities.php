<?php

namespace App\Console\Commands;

use App\Models\FindingActivity;
use App\Models\QcFinding;
use App\Models\User;
use App\Services\FindingActivityRecorder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillFindingActivities extends Command
{
    protected $signature = 'findings:backfill-activities {--dry-run : Simulate backfill without saving}';

    protected $description = 'Backfill finding_activities from existing QC finding data (idempotent).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $skipped = 0;
        $processed = 0;

        QcFinding::query()
            ->with(['rfi.createdBy', 'rfi.reviewedBy', 'submissions'])
            ->doesntHave('activities')
            ->orderBy('id')
            ->chunkById(100, function ($findings) use ($dryRun, &$skipped, &$processed): void {
                foreach ($findings as $finding) {
                    if ($dryRun) {
                        $this->line("[dry-run] Would backfill finding #{$finding->id}");
                        $processed++;

                        continue;
                    }

                    DB::transaction(function () use ($finding): void {
                        $rfi = $finding->rfi;

                        // 1. rfi_submitted
                        if ($rfi && $rfi->submitted_at) {
                            FindingActivityRecorder::record(
                                finding: $finding,
                                type: FindingActivity::TYPE_RFI_SUBMITTED,
                                actor: $rfi->createdBy ?? null,
                                meta: array_filter(['rfi_no' => $rfi->rfi_no]),
                                at: Carbon::instance($rfi->submitted_at),
                            );
                        }

                        // 2. finding_created
                        $reviewer = $rfi?->reviewedBy ?? null;
                        FindingActivityRecorder::record(
                            finding: $finding,
                            type: FindingActivity::TYPE_FINDING_CREATED,
                            actor: $reviewer,
                            meta: array_filter([
                                'disposition' => $rfi?->result?->value,
                                'rfi_no' => $rfi?->rfi_no,
                            ]),
                            at: Carbon::instance($finding->created_at),
                        );

                        // 3. action_plan_saved (if set)
                        if (filled($finding->action_plan)) {
                            $apActor = $finding->action_plan_by ? User::find($finding->action_plan_by) : null;
                            FindingActivityRecorder::record(
                                finding: $finding,
                                type: FindingActivity::TYPE_ACTION_PLAN_SAVED,
                                actor: $apActor,
                                meta: ['first' => true],
                                at: $finding->action_plan_at
                                    ? Carbon::instance($finding->action_plan_at)
                                    : Carbon::instance($finding->created_at),
                            );
                        }

                        // 4. submissions: reinspect_submitted + reinspect_verified
                        $submissions = $finding->submissions->sortBy('round_no');

                        foreach ($submissions as $submission) {
                            $submitter = $submission->submitted_by ? User::find($submission->submitted_by) : null;
                            FindingActivityRecorder::record(
                                finding: $finding,
                                type: FindingActivity::TYPE_REINSPECT_SUBMITTED,
                                actor: $submitter,
                                meta: ['round' => $submission->round_no, 'submission_id' => $submission->id],
                                at: $submission->submitted_at
                                    ? Carbon::instance($submission->submitted_at)
                                    : Carbon::instance($finding->created_at),
                            );

                            if ($submission->verification_result !== null && $submission->verified_at) {
                                $verifier = $submission->verified_by ? User::find($submission->verified_by) : null;
                                FindingActivityRecorder::record(
                                    finding: $finding,
                                    type: FindingActivity::TYPE_REINSPECT_VERIFIED,
                                    actor: $verifier,
                                    meta: [
                                        'round' => $submission->round_no,
                                        'submission_id' => $submission->id,
                                        'result' => $submission->verification_result === 'ok' ? 'ok' : 'not_ok',
                                    ],
                                    note: $submission->verification_note,
                                    at: Carbon::instance($submission->verified_at),
                                );
                            }
                        }
                    });

                    $processed++;
                    $this->line("Backfilled finding #{$finding->id}");
                }
            });

        $this->info("Done. Processed: {$processed}, Skipped (already had activities): {$skipped}.");

        return self::SUCCESS;
    }
}
