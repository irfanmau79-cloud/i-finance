<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pejabat_opd', function (Blueprint $table) {
            $table->unsignedTinyInteger('aktif_unik')
                ->nullable()
                ->virtualAs('CASE WHEN aktif = 1 THEN 1 ELSE NULL END');
            $table->unique('aktif_unik', 'pejabat_opd_satu_aktif_unique');
        });

        Schema::table('kpa', function (Blueprint $table) {
            $table->unsignedBigInteger('kpa_pegawai_aktif_unik')
                ->nullable()
                ->virtualAs('CASE WHEN aktif = 1 THEN kpa_pegawai_id ELSE NULL END');
            $table->unique('kpa_pegawai_aktif_unik', 'kpa_pegawai_satu_aktif_unique');
        });
    }

    public function down(): void
    {
        Schema::table('kpa', function (Blueprint $table) {
            $table->dropUnique('kpa_pegawai_satu_aktif_unique');
            $table->dropColumn('kpa_pegawai_aktif_unik');
        });

        Schema::table('pejabat_opd', function (Blueprint $table) {
            $table->dropUnique('pejabat_opd_satu_aktif_unique');
            $table->dropColumn('aktif_unik');
        });
    }
};
