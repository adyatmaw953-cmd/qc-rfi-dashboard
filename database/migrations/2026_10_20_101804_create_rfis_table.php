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
        Schema::create('rfis', function (Blueprint $table) {
            $table->id();
            $table->string('rfi_no')->nullable()->unique();
            $table->foreignId('project_id')->constrained('projects');
            $table->char('category', 1);
            $table->json('details');
            $table->date('date_inspect');
            $table->date('scheduled_at')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('open');
            $table->string('result')->nullable();
            $table->text('qc_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamps();
            $table->index(['category', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfis');
    }
};
