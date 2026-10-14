<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Input Manual" pada isian nama detail pembayaran NPD: nama diketik sendiri
 * dan SENGAJA tidak ditautkan ke Data Pegawai.
 *
 * Penandanya perlu disimpan karena pegawai_id yang kosong saja tidak cukup:
 * baris tanpa pegawai_id selama ini tetap dihitung di Dashboard Perjalanan
 * Dinas dan Riwayat Perjalanan Dinas pegawai lewat kecocokan NIP/nama. Baris
 * bertanda ini dikeluarkan dari keduanya. Baris lama tetap false, jadi
 * angka yang sudah ada tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('npd_tim', function (Blueprint $table) {
            $table->boolean('input_manual')->default(false)->after('pegawai_id');
        });

        Schema::table('npd_peserta', function (Blueprint $table) {
            $table->boolean('input_manual')->default(false)->after('pegawai_id');
        });
    }

    public function down(): void
    {
        Schema::table('npd_tim', function (Blueprint $table) {
            $table->dropColumn('input_manual');
        });

        Schema::table('npd_peserta', function (Blueprint $table) {
            $table->dropColumn('input_manual');
        });
    }
};
