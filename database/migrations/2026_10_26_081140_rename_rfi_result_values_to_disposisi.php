<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename old RFI result enum values to the new disposisi values.
     *
     * Old → New
     *   ok                → accepted
     *   accepted_with_note → use_as_is
     *
     * Affects both rfis.result and qc_findings.result columns.
     */
    public function up(): void
    {
        $map = [
            'ok' => 'accepted',
            'accepted_with_note' => 'use_as_is',
        ];

        foreach ($map as $old => $new) {
            DB::table('rfis')->where('result', $old)->update(['result' => $new]);
            DB::table('qc_findings')->where('result', $old)->update(['result' => $new]);
        }
    }

    public function down(): void
    {
        $map = [
            'accepted' => 'ok',
            'use_as_is' => 'accepted_with_note',
        ];

        foreach ($map as $old => $new) {
            DB::table('rfis')->where('result', $old)->update(['result' => $new]);
            DB::table('qc_findings')->where('result', $old)->update(['result' => $new]);
        }
    }
};
