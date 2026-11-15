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
        Schema::table('mdrs', function (Blueprint $table) {
            $table->string('owner_serial_no', 100)->nullable()->after('project_id');
            $table->string('manufacture_serial_no', 100)->nullable()->after('owner_serial_no');
            $table->string('unit_no', 50)->nullable()->default('Unit 1')->change();
        });

        Schema::table('mdr_report_statuses', function (Blueprint $table) {
            $table->string('report_title')->nullable()->after('report_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mdr_report_statuses', function (Blueprint $table) {
            $table->dropColumn('report_title');
        });

        Schema::table('mdrs', function (Blueprint $table) {
            $table->dropColumn(['owner_serial_no', 'manufacture_serial_no']);
        });
    }
};
