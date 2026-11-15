<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom unit_no dengan default sementara agar aman untuk data yang ada
        Schema::table('mdrs', function (Blueprint $table) {
            $table->string('unit_no', 50)->default('Unit 1')->after('project_id');
        });

        // 2. Isi MDR yang sudah ada dengan "Unit 1"
        DB::table('mdrs')->update(['unit_no' => 'Unit 1']);

        // 3. Tambah unique gabungan (project_id, unit_no) DULU
        Schema::table('mdrs', function (Blueprint $table) {
            $table->unique(['project_id', 'unit_no'], 'mdrs_project_id_unit_no_unique');
        });

        // 4. Baru hapus unique lama pada project_id
        //    MySQL butuh index untuk foreign key, jadi pastikan index project_id
        //    tidak dihapus sebelum ada indeks pengganti (unique gabungan sudah mencakup project_id)
        Schema::table('mdrs', function (Blueprint $table) {
            $table->dropUnique('mdrs_project_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('mdrs', function (Blueprint $table) {
            // Kembalikan unique project_id (hanya jika belum ada — aman untuk rollback)
            $table->unique('project_id', 'mdrs_project_id_unique');
            $table->dropUnique('mdrs_project_id_unit_no_unique');
            $table->dropColumn('unit_no');
        });
    }
};
