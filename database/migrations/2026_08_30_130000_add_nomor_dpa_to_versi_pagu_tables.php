<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor DPA menempel pada Tahapan Pagu, bukan pada program.
 *
 * Sebelumnya No. DPA di cetakan NPD diambil dari data_tambahan (per program,
 * warisan GAS) — tabel yang tidak pernah terisi sejak migrasi, sehingga
 * kolom "No. DPA" pada PDF NPD selalu kosong. Satu dokumen DPA punya satu
 * nomor dan satu tahapan (DPA Murni, DPA Pergeseran 1, ...), jadi nomornya
 * disimpan di baris tahapannya. Yang tercetak selalu nomor milik tahapan
 * yang sedang BERLAKU — lihat App\Helpers\PejabatResolver.
 *
 * Kolom kembarannya di master_anggaran_imports menampung nomor itu selama
 * berkasnya masih berstatus staging, sebelum tahapannya benar-benar dibuat
 * saat konfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL tidak membatalkan DDL saat migrasi gagal di tengah jalan, jadi
        // tiap kolom diperiksa dulu supaya migrasi ini aman diulang.
        if (! Schema::hasColumn('versi_pagu', 'nomor_dpa')) {
            Schema::table('versi_pagu', function (Blueprint $table) {
                $table->string('nomor_dpa', 100)->nullable()->after('nama');
            });
        }

        if (! Schema::hasColumn('master_anggaran_imports', 'versi_nomor_dpa')) {
            Schema::table('master_anggaran_imports', function (Blueprint $table) {
                $table->string('versi_nomor_dpa', 100)->nullable()->after('versi_nama');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('versi_pagu', 'nomor_dpa')) {
            Schema::table('versi_pagu', function (Blueprint $table) {
                $table->dropColumn('nomor_dpa');
            });
        }

        if (Schema::hasColumn('master_anggaran_imports', 'versi_nomor_dpa')) {
            Schema::table('master_anggaran_imports', function (Blueprint $table) {
                $table->dropColumn('versi_nomor_dpa');
            });
        }
    }
};
