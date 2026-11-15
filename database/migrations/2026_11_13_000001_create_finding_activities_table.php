<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finding_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_finding_id')->constrained('qc_findings')->cascadeOnDelete();
            $table->string('type', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 120)->nullable();
            $table->string('actor_role', 30)->nullable();
            $table->json('meta')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['qc_finding_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finding_activities');
    }
};
