<?php

namespace App\Services;

use App\Models\FindingHistory;
use App\Models\QcFinding;
use App\Models\User;
use Carbon\Carbon;

class FindingHistoryRecorder
{
    /**
     * Record an activity history event for a QC finding.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(
        QcFinding $finding,
        string $event,
        User|int|null $actor = null,
        ?string $old = null,
        ?string $new = null,
        array $meta = []
    ): FindingHistory {
        $actorId = $actor instanceof User ? $actor->id : $actor;

        return $finding->histories()->create([
            'event' => $event,
            'actor_id' => $actorId,
            'old_value' => $old,
            'new_value' => $new,
            'meta' => $meta !== [] ? $meta : null,
            'created_at' => Carbon::now('UTC'),
        ]);
    }
}
