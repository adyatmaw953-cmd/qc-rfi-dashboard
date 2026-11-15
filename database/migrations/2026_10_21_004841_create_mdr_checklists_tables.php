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
        Schema::create('mdrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mdr_report_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mdr_id')->constrained()->cascadeOnDelete();
            $table->string('report_key');
            $table->string('status')->default('open');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['mdr_id', 'report_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mdr_report_statuses');
        Schema::dropIfExists('mdrs');
    }
};
