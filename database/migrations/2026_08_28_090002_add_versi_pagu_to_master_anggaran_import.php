<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging import Pagu menyesuaikan dua perubahan sekaligus:
 *
 * 1. Kolom kode dipisah dari nama (ikut master_anggaran).
 * 2. Setiap batch import wajib diberi NAMA VERSI ("DPA Murni", "DPA
 *    Pergeseran 1", ...). Versi dibuat saat Konfirmasi Simpan dan berstatus
 *    draft - baru berlaku setelah diaktifkan terpisah.
 *
 * Kolom aksi diubah dari enum menjadi string karena bertambah nilai
 * 'dinolkan': mata anggaran yang ada di versi berlaku tapi TIDAK dicantumkan
 * di file versi baru. File DPA diperlakukan sebagai dokumen utuh, jadi baris
 * yang hilang berarti tidak dianggarkan lagi (pagu 0), bukan diwarisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_anggaran_imports', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun')->nullable()->after('nama_file');
            $table->string('versi_nama', 150)->nullable()->after('tahun');
            $table->text('versi_keterangan')->nullable()->after('versi_nama');
            $table->foreignId('versi_pagu_id')->nullable()->after('versi_keterangan')
                ->constrained('versi_pagu')->nullOnDelete();
            $table->unsignedInteger('jumlah_dinolkan')->default(0)->after('jumlah_ditolak');
        });

        Schema::table('master_anggaran_import_rows', function (Blueprint $table) {
            $table->string('kode_program', 50)->nullable()->after('alasan');
            $table->string('kode_kegiatan', 50)->nullable()->after('program');
            $table->string('kode_sub_kegiatan', 50)->nullable()->after('kegiatan');
            $table->string('rekening', 255)->nullable()->after('kode_rekening');
        });

        Schema::table('master_anggaran_import_rows', function (Blueprint $table) {
            $table->string('aksi', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('master_anggaran_import_rows', function (Blueprint $table) {
            $table->dropColumn(['kode_program', 'kode_kegiatan', 'kode_sub_kegiatan', 'rekening']);
        });

        Schema::table('master_anggaran_imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('versi_pagu_id');
            $table->dropColumn(['tahun', 'versi_nama', 'versi_keterangan', 'jumlah_dinolkan']);
        });
    }
};
