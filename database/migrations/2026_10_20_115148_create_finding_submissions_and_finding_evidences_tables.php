<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('finding_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_finding_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_no');
            $table->text('note');
            $table->foreignId('submitted_by')->constrained('users');
            $table->timestamp('submitted_at');
            $table->string('verification_result')->nullable();
            $table->text('verification_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['qc_finding_id', 'round_no']);
        });

        Schema::create('finding_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_submission_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path')->unique();
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finding_evidences');
        Schema::dropIfExists('finding_submissions');
    }
};
