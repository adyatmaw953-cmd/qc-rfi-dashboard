<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('irfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('produk')->nullable();
            $table->string('irf_no')->unique();
            $table->string('status');
            $table->date('date_inspect');
            $table->string('inspect_item');
            $table->string('drawing_ref')->nullable();
            $table->text('note')->nullable();
            $table->string('qc_result')->nullable();
            $table->text('response_to_finding')->nullable();
            $table->boolean('is_new')->default(false);
            $table->timestamp('notification_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irfs');
    }
};
