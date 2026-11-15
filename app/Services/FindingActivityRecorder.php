<?php

namespace App\Services;

use App\Models\FindingActivity;
use App\Models\QcFinding;
use App\Models\User;
use Carbon\Carbon;

class FindingActivityRecorder
{
    /**
     * Record a finding activity inside an existing DB transaction.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(
        QcFinding $finding,
        string $type,
        ?User $actor,
        array $meta = [],
        ?string $note = null,
        ?Carbon $at = null,
    ): FindingActivity {
        return FindingActivity::create([
            'qc_finding_id' => $finding->id,
            'type' => $type,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_role' => $actor?->role,
            'meta' => $meta !== [] ? $meta : null,
            'note' => $note,
            'occurred_at' => $at ?? Carbon::now('UTC'),
        ]);
    }
}
