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
        Schema::create('ncrs', function (Blueprint $table) {
            $table->id();
            $table->string('ncr_number')->unique();
            $table->date('finding_date');
            $table->string('department');
            $table->json('related_departments')->nullable();
            $table->string('area_finding');
            $table->string('level');
            $table->string('type');
            $table->string('project_code')->nullable()->index();
            $table->string('customer')->nullable();
            $table->string('responsible_pic');
            $table->text('problem_description');
            $table->text('root_cause');
            $table->string('root_cause_category');
            $table->text('corrective_action')->nullable();
            $table->string('pic_ca')->nullable();
            $table->date('ca_closed_date')->nullable();
            $table->text('preventive_action')->nullable();
            $table->string('pic_pa')->nullable();
            $table->date('pa_closed_date')->nullable();
            $table->string('status')->default('Open');
            $table->unsignedBigInteger('nc_cost')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ncrs');
    }
};
