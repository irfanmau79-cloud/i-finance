<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch import Pegawai dengan alur preview/dry-run, pola sama seperti
 * Master Anggaran (lihat create_master_anggaran_import_rows_table) - file
 * di-parse ke staging dulu, TIDAK menyentuh tabel pegawai sampai user
 * menekan Konfirmasi Simpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai_imports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_file', 255);
            $table->enum('status', ['staged', 'committed'])->default('staged');

            $table->unsignedInteger('total_baris')->default(0);
            $table->unsignedInteger('jumlah_baru')->default(0);
            $table->unsignedInteger('jumlah_update')->default(0);
            $table->unsignedInteger('jumlah_ditolak')->default(0);

            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });

        Schema::create('pegawai_import_rows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_id')->constrained('pegawai_imports')->cascadeOnDelete();
            $table->unsignedInteger('nomor_baris');
            $table->enum('aksi', ['baru', 'update', 'ditolak']);
            $table->text('alasan')->nullable();

            $table->string('nama', 255)->nullable();
            $table->string('nip', 30)->nullable();
            $table->string('jabatan', 255)->nullable();
            $table->string('golongan', 20)->nullable();
            $table->string('pangkat', 100)->nullable();
            $table->string('bidang', 100)->nullable();
            $table->string('rekening', 100)->nullable();
            $table->boolean('aktif')->default(true);

            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();

            $table->timestamps();

            $table->index(['import_id', 'nomor_baris']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawai_import_rows');
        Schema::dropIfExists('pegawai_imports');
    }
};
