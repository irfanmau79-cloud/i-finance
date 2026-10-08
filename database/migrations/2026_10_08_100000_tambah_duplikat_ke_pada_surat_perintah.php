<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu Surat Perintah bisa dibayarkan lewat BEBERAPA NPD Perjalanan Dinas,
     * padahal satu baris SP hanya bisa ditaut ke satu NPD (statusnya mengikuti
     * NPD itu). Jalan keluarnya: baris SP diduplikat di halaman Data SP, dan
     * tiap duplikat ditaut ke NPD-nya sendiri.
     *
     * Duplikat menyimpan nomor_sp yang SAMA dengan aslinya - nomor itulah
     * yang tercetak di NPD dan tampil di Monitoring SP. Yang membedakannya
     * hanya kolom ini: 0 = SP asli, n = duplikat ke-n. Keterangan
     * "(Duplikat-n)" dirakit dari sini dan hanya ditampilkan di Data SP -
     * lihat SuratPerintah::nomorBerlabel().
     */
    public function up(): void
    {
        Schema::table('surat_perintah', function (Blueprint $table) {
            $table->unsignedSmallInteger('duplikat_ke')->default(0)->after('nomor_sp');
            $table->index(['nomor_sp', 'duplikat_ke'], 'surat_perintah_nomor_duplikat_index');
        });
    }

    public function down(): void
    {
        Schema::table('surat_perintah', function (Blueprint $table) {
            $table->dropIndex('surat_perintah_nomor_duplikat_index');
            $table->dropColumn('duplikat_ke');
        });
    }
};
