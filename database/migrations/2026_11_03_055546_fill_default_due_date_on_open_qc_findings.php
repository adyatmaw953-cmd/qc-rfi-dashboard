<?php

use Carbon\Carbon;
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
            ->where('status', '!=', 'closed')
            ->whereNull('due_date')
            ->get(['id', 'date_of_finding']);

        foreach ($findings as $finding) {
            if ($finding->date_of_finding) {
                $dueDate = Carbon::parse($finding->date_of_finding, 'Asia/Jakarta')->addDay()->toDateString();
                DB::table('qc_findings')
                    ->where('id', $finding->id)
                    ->update(['due_date' => $dueDate]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data migration, cannot safely determine which due_dates were originally null
    }
};
