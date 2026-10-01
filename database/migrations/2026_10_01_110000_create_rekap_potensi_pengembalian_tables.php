<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekap Potensi Pengembalian (adopsi GAS #95 & #96).
 *
 * Rekap per pegawai atas kelebihan pembayaran yang masih harus dikembalikan.
 * Di GAS datanya tinggal di satu tab spreadsheet yang diisi manual; di sini
 * ia masuk lewat Manajemen Data dengan pola preview/dry-run yang sama dengan
 * jenis data lain, supaya berkas yang salah tidak langsung menimpa data.
 *
 * Kolom SISA sengaja TIDAK disimpan: nilainya selalu potensi - setoran, dan
 * menyimpan angka ketiga yang bisa bertentangan dengan dua angka sumbernya
 * hanya mengundang rekap yang tidak konsisten dengan dirinya sendiri.
 * Baris dikenali dari NIP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_potensi_pengembalian', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 30)->unique();
            $table->string('nama', 255);
            $table->string('jabatan', 255)->nullable();
            $table->decimal('potensi', 18, 2)->default(0);
            $table->decimal('setoran', 18, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('rekap_potensi_imports', function (Blueprint $table) {
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

        Schema::create('rekap_potensi_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('rekap_potensi_imports')->cascadeOnDelete();
            $table->unsignedInteger('nomor_baris');
            $table->enum('aksi', ['baru', 'update', 'ditolak']);
            $table->text('alasan')->nullable();

            $table->string('nip', 30)->nullable();
            $table->string('nama', 255)->nullable();
            $table->string('jabatan', 255)->nullable();
            $table->decimal('potensi', 18, 2)->default(0);
            $table->decimal('setoran', 18, 2)->default(0);
            $table->text('keterangan')->nullable();

            $table->foreignId('rekap_id')->nullable()->constrained('rekap_potensi_pengembalian')->nullOnDelete();

            $table->timestamps();
            $table->index(['import_id', 'nomor_baris']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_potensi_import_rows');
        Schema::dropIfExists('rekap_potensi_imports');
        Schema::dropIfExists('rekap_potensi_pengembalian');
    }
};
