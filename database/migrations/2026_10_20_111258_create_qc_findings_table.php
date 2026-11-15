<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('qc_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfi_id')->unique()->constrained('rfis');
            $table->string('result');
            $table->text('finding_list');
            $table->date('date_of_finding');
            $table->date('due_date')->nullable();
            $table->string('status')->default('open');
            $table->text('action_plan')->nullable();
            $table->foreignId('action_plan_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('action_plan_at')->nullable();
            $table->date('date_of_close')->nullable();
            $table->timestamps();
        });

        $validFindingResults = ['accepted_with_note', 'repair', 'reject', 'use_as_is'];

        DB::table('rfis')
            ->where('status', 'nc')
            ->orderBy('id')
            ->chunkById(200, function ($rfis) use ($validFindingResults): void {
                foreach ($rfis as $rfi) {
                    if (
                        ! in_array($rfi->result, $validFindingResults, true)
                        || $rfi->qc_note === null
                        || $rfi->reviewed_at === null
                    ) {
                        throw new RuntimeException(
                            "RFI {$rfi->id} cannot be migrated to a QC finding because its QC review data is incomplete."
                        );
                    }

                    DB::table('qc_findings')->insert([
                        'rfi_id' => $rfi->id,
                        'result' => $rfi->result,
                        'finding_list' => $rfi->qc_note,
                        'date_of_finding' => substr($rfi->reviewed_at, 0, 10),
                        'status' => 'open',
                        'created_at' => $rfi->reviewed_at,
                        'updated_at' => $rfi->reviewed_at,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qc_findings');
    }
};
