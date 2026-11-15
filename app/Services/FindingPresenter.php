<?php

namespace App\Services;

use App\Models\QcFinding;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class FindingPresenter
{
    /**
     * Compute stepper state for a finding.
     *
     * Stages:
     *  1. Action plan     — done if action_plan filled
     *  2. Re-Inspect      — done if any submission exists
     *  3. Verifikasi QC   — done if latest submission has verification_result
     *  4. Closed          — done if status === 'closed'
     *
     * @return array{
     *     current: int,
     *     stages: list<array{label: string, done: bool, isCurrent: bool, number: int}>,
     * }
     */
    public static function getStepperState(QcFinding $finding): array
    {
        $hasActionPlan = filled($finding->action_plan);
        $hasSubmission = $finding->submissions->isNotEmpty();
        $latestSubmission = $finding->submissions->last();
        $hasVerification = $latestSubmission !== null && $latestSubmission->verification_result !== null;
        $isClosed = $finding->status === 'closed';

        $doneFlags = [
            $hasActionPlan,
            $hasSubmission,
            $hasVerification,
            $isClosed,
        ];

        $labels = ['Action plan', 'Re-Inspect', 'Verifikasi QC', 'Closed'];

        // Current = first not-done stage (1-indexed)
        $current = 4;

        foreach ($doneFlags as $i => $done) {
            if (! $done) {
                $current = $i + 1;
                break;
            }
        }

        // If all done, current = 4 (last)
        if (array_product(array_map(fn (bool $v): int => (int) $v, $doneFlags)) === 1) {
            $current = 4;
        }

        $stages = [];

        foreach ($labels as $i => $label) {
            $stageNumber = $i + 1;
            $stages[] = [
                'number' => $stageNumber,
                'label' => $label,
                'done' => $doneFlags[$i],
                'isCurrent' => $stageNumber === $current && ! $isClosed,
            ];
        }

        return [
            'current' => $current,
            'stages' => $stages,
        ];
    }

    /**
     * Build unified activity timeline for a finding, newest first.
     *
     * @return Collection<int, array{
     *     timestamp: CarbonInterface,
     *     actor: string,
     *     description: string,
     *     event: string,
     * }>
     */
    public static function getActivityTimeline(QcFinding $finding): Collection
    {
        $timeline = collect();

        // (a) RFI-derived events
        $rfi = $finding->rfi;

        if ($rfi && $rfi->submitted_at) {
            $creator = $rfi->createdBy?->name ?? 'Pembuat RFI';
            $timeline->push([
                'timestamp' => $rfi->submitted_at,
                'actor' => $creator,
                'description' => "RFI dikirim oleh {$creator}",
                'event' => 'rfi_submitted',
            ]);
        }

        if ($rfi && $rfi->reviewed_at) {
            $reviewer = $rfi->reviewedBy?->name ?: 'QC';
            $timeline->push([
                'timestamp' => $rfi->reviewed_at,
                'actor' => $reviewer,
                'description' => 'QC mereview, hasil NC',
                'event' => 'rfi_reviewed',
            ]);
        }

        if ($finding->created_at) {
            $reviewer = $rfi?->reviewedBy?->name ?: 'QC';
            $timeline->push([
                'timestamp' => $finding->created_at,
                'actor' => $reviewer,
                'description' => 'Temuan dibuat',
                'event' => 'finding_created',
            ]);
        }

        // (b) finding_histories events
        foreach ($finding->histories as $history) {
            $actorName = $history->actor?->name ?? 'Sistem';
            $meta = is_array($history->meta) ? $history->meta : [];

            $description = match ($history->event) {
                'action_plan_updated' => "{$actorName} memperbarui action plan",
                'due_date_changed' => (function () use ($history, $actorName): string {
                    $old = $history->old_value ? Carbon::parse($history->old_value)->format('d M') : '—';
                    $new = $history->new_value ? Carbon::parse($history->new_value)->format('d M') : '—';

                    return "{$actorName} mengubah due date {$old} -> {$new}";
                })(),
                'reinspect_submitted' => (function () use ($meta, $actorName): string {
                    $round = $meta['round_no'] ?? null;

                    return $round ? "{$actorName} mengirim Re-Inspect putaran {$round}" : "{$actorName} mengirim Re-Inspect";
                })(),
                'verified_ok' => (function () use ($meta, $actorName): string {
                    $round = $meta['round_no'] ?? null;
                    $suffix = $round ? " putaran {$round}" : '';

                    return "{$actorName} memverifikasi Re-Inspect{$suffix}: OK";
                })(),
                'verified_rejected' => (function () use ($history, $meta, $actorName): string {
                    $round = $meta['round_no'] ?? null;
                    $dueNew = $history->new_value ?? $meta['due_date'] ?? null;
                    $dueFormatted = $dueNew ? Carbon::parse($dueNew)->format('d M') : null;
                    $suffix = $round ? " putaran {$round}" : '';
                    $dueSuffix = $dueFormatted ? " (due date baru {$dueFormatted})" : '';

                    return "{$actorName} memverifikasi Re-Inspect{$suffix}: Belum OK{$dueSuffix}";
                })(),
                'closed_incoming' => (function () use ($meta, $actorName): string {
                    $result = $meta['result'] ?? '';
                    $resultLabel = $result === 'use_as_is' ? 'Use as is' : ($result === 'reject' ? 'Reject' : $result);

                    return "{$actorName} menyelesaikan tindakan Incoming Material ({$resultLabel})";
                })(),
                default => "{$actorName} melakukan {$history->event}",
            };

            $timeline->push([
                'timestamp' => $history->created_at,
                'actor' => $actorName,
                'description' => $description,
                'event' => $history->event,
            ]);
        }

        return $timeline->sortByDesc(fn (array $item): int => $item['timestamp']->getTimestamp())->values();
    }
}
