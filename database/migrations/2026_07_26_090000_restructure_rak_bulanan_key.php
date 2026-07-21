<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prompt 12A: RAK Bulanan seharusnya berhenti di Kode Rekening, TANPA
     * Tagging - satu (Tahun, Sub Kegiatan, Kode Rekening) hanya boleh punya
     * satu rangkaian Jan-Des. Desain awal (create_rak_bulanan_table) memakai
     * master_anggaran_id sebagai identitas RAK, tapi master_anggaran sendiri
     * unique pada (sub_kegiatan, kode_rekening, tagging_id) - sehingga Sub
     * Kegiatan+Kode Rekening yang sama dengan Tagging berbeda menghasilkan
     * RAK terpisah. Audit basis data langsung (2026-07-21) mengonfirmasi:
     * 639 baris master_anggaran, 100% punya tagging_id, 92 kombinasi
     * (sub_kegiatan, kode_rekening) dengan lebih dari satu tagging - persis
     * kasus yang harus dicegah di RAK. rak_bulanan sendiri masih 0 baris
     * saat migrasi ini ditulis, jadi tidak ada data yang perlu dinormalisasi.
     *
     * RAK dilepas dari master_anggaran_id, dikunci langsung ke
     * (tahun, sub_kegiatan_kunci, kode_rekening, bulan). Pagu untuk konteks
     * tampilan dihitung terpisah sebagai SUM(master_anggaran.pagu) lintas
     * tagging - lihat RakBulanan::paguGabungan().
     */
    public function up(): void
    {
        Schema::table('rak_bulanan', function (Blueprint $table) {
            $table->dropUnique(['master_anggaran_id', 'tahun', 'bulan']);
            $table->dropForeign(['master_anggaran_id']);
            $table->dropColumn('master_anggaran_id');

            $table->string('sub_kegiatan', 255)->after('id');
            $table->string('sub_kegiatan_kunci', 255)->after('sub_kegiatan');
            $table->string('kode_rekening', 50)->after('sub_kegiatan_kunci');

            $table->unique(['tahun', 'sub_kegiatan_kunci', 'kode_rekening', 'bulan'], 'rak_bulanan_identitas_unique');
            $table->index(['sub_kegiatan_kunci', 'kode_rekening']);
        });
    }

    public function down(): void
    {
        Schema::table('rak_bulanan', function (Blueprint $table) {
            $table->dropUnique('rak_bulanan_identitas_unique');
            $table->dropIndex(['sub_kegiatan_kunci', 'kode_rekening']);
            $table->dropColumn(['sub_kegiatan', 'sub_kegiatan_kunci', 'kode_rekening']);

            $table->foreignId('master_anggaran_id')->after('id')->constrained('master_anggaran')->restrictOnDelete();
            $table->unique(['master_anggaran_id', 'tahun', 'bulan']);
        });
    }
};
