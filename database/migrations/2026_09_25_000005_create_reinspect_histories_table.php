<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reinspect_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('irf_id')->constrained('irfs')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('irf_no');
            $table->string('previous_irf_no')->nullable();
            $table->string('previous_qc_result')->nullable();
            $table->string('previous_drawing_ref')->nullable();
            $table->text('previous_note')->nullable();
            $table->text('response_to_finding');
            $table->string('status');
            $table->string('drawing_ref')->nullable();
            $table->text('note')->nullable();
            $table->string('qc_result')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['irf_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reinspect_histories');
    }
};
