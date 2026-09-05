<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monitoring PKPT (Program Kerja Pengawasan Tahunan).
 *
 * Padanan sheet "Monitoring PKPT" di GAS (CodePKPT.gs). Kolom di sini
 * mengikuti kolom yang benar-benar dipakai sheet itu - A No, B Area,
 * C Jenis, D Tujuan, E Ruang Lingkup, O Jumlah Tim, P Estimasi, Q Realisasi,
 * R Pelaksanaan, S Jumlah Laporan, W Rencana Pelaksanaan, X Unit Kerja,
 * Y Terlaksana - ditambah `tahun`, yang di GAS tersirat dari "sheet tahun
 * ini" tapi di sini harus eksplisit karena satu basis data menampung
 * beberapa tahun anggaran sekaligus.
 *
 * Datanya masuk lewat Manajemen Data > Data PKPT (import preview/dry-run),
 * bukan diketik di aplikasi: PKPT disusun di luar sistem ini dan yang
 * dibutuhkan aplikasi hanya memantaunya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpt', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('tahun')->index();
            // Nomor PKPT ditulis apa adanya ("1", "1-IRB1", ...) - penomoran
            // di dokumen aslinya tidak selalu murni angka.
            $table->string('nomor', 50)->nullable();
            $table->string('unit_kerja', 100)->nullable()->index();
            $table->string('area', 255)->nullable();
            $table->string('jenis_kegiatan', 255)->nullable();
            $table->text('tujuan')->nullable();
            $table->text('ruang_lingkup')->nullable();
            $table->string('jumlah_tim', 50)->nullable();
            $table->decimal('estimasi_anggaran', 18, 2)->default(0);
            $table->decimal('realisasi', 18, 2)->default(0);
            $table->string('pelaksanaan', 255)->nullable();
            $table->string('jumlah_laporan', 50)->nullable();
            $table->string('rencana_pelaksanaan', 255)->nullable();
            $table->boolean('terlaksana')->default(false);

            $table->timestamps();

            $table->index(['tahun', 'unit_kerja']);
        });

        Schema::create('pkpt_imports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_file', 255);
            $table->enum('status', ['staged', 'committed'])->default('staged');
            $table->unsignedSmallInteger('tahun');

            $table->unsignedInteger('total_baris')->default(0);
            $table->unsignedInteger('jumlah_baru')->default(0);
            $table->unsignedInteger('jumlah_update')->default(0);
            $table->unsignedInteger('jumlah_ditolak')->default(0);

            // datetime NULL, bukan timestamp: lihat StagingKedaluwarsa soal
            // aturan implisit DEFAULT CURRENT_TIMESTAMP di MariaDB.
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('committed_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });

        Schema::create('pkpt_import_rows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_id')->constrained('pkpt_imports')->cascadeOnDelete();
            $table->unsignedInteger('nomor_baris');
            $table->enum('aksi', ['baru', 'update', 'ditolak']);
            $table->text('alasan')->nullable();

            $table->string('nomor', 50)->nullable();
            $table->string('unit_kerja', 100)->nullable();
            $table->string('area', 255)->nullable();
            $table->string('jenis_kegiatan', 255)->nullable();
            $table->text('tujuan')->nullable();
            $table->text('ruang_lingkup')->nullable();
            $table->string('jumlah_tim', 50)->nullable();
            $table->decimal('estimasi_anggaran', 18, 2)->default(0);
            $table->decimal('realisasi', 18, 2)->default(0);
            $table->string('pelaksanaan', 255)->nullable();
            $table->string('jumlah_laporan', 50)->nullable();
            $table->string('rencana_pelaksanaan', 255)->nullable();
            $table->boolean('terlaksana')->default(false);

            $table->foreignId('pkpt_id')->nullable()->constrained('pkpt')->nullOnDelete();

            $table->timestamps();

            $table->index(['import_id', 'nomor_baris']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpt_import_rows');
        Schema::dropIfExists('pkpt_imports');
        Schema::dropIfExists('pkpt');
    }
};
