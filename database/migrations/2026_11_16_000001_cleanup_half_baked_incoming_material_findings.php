<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $findings = DB::table('qc_findings')
            ->join('rfis', 'rfis.id', '=', 'qc_findings.rfi_id')
            ->where('rfis.category', 'A')
            ->where('rfis.status', 'nc')
            ->where('rfis.result', 'repair')
            ->where('qc_findings.finding_list', 'Incoming Material Finding')
            ->where('qc_findings.status', 'open')
            ->where(function ($query) {
                $query->whereNull('qc_findings.action_plan')
                    ->orWhere('qc_findings.action_plan', '');
            })
            ->select('qc_findings.id as finding_id', 'rfis.id as rfi_id')
            ->get();

        foreach ($findings as $row) {
            $hasSubmissions = DB::table('finding_submissions')
                ->where('qc_finding_id', $row->finding_id)
                ->exists();

            if ($hasSubmissions) {
                continue;
            }

            $hasOtherHistories = DB::table('finding_histories')
                ->where('qc_finding_id', $row->finding_id)
                ->exists();

            if ($hasOtherHistories) {
                continue;
            }

            $hasOtherActivities = DB::table('finding_activities')
                ->where('qc_finding_id', $row->finding_id)
                ->whereNotIn('type', ['rfi_submitted', 'finding_created'])
                ->exists();

            if ($hasOtherActivities) {
                continue;
            }

            DB::table('rfis')
                ->where('id', $row->rfi_id)
                ->update([
                    'status' => 'open',
                    'result' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]);

            DB::table('finding_activities')->where('qc_finding_id', $row->finding_id)->delete();
            DB::table('finding_histories')->where('qc_finding_id', $row->finding_id)->delete();
            DB::table('qc_findings')->where('id', $row->finding_id)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way data cleanup migration
    }
};
