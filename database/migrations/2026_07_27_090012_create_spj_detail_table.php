<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override/pelengkap untuk baris "Tabel Detail SPJ" di Inventarisasi SPJ -
 * SATU baris per NPD (bukan per jenis dokumen seperti arsip_spj). Semua
 * kolom override nullable: null berarti "pakai nilai default hasil hitung
 * dari data NPD" (lihat InventarisasiSpjService::detailSpj()); diisi berarti
 * Bendahara Pengeluaran/BPP sudah menimpanya secara manual. Tombol Restore
 * mengosongkan kolom override ini kembali ke null - bukan menghapus baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spj_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('npd_id')->unique()->constrained('npd')->cascadeOnDelete();

            $table->unsignedTinyInteger('bulan')->nullable();
            $table->string('nomor_sp', 100)->nullable();
            $table->decimal('nominal', 18, 2)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('bidang', 100)->nullable();
            $table->text('uraian')->nullable();
            $table->string('lokasi', 100)->nullable();

            // Tidak punya nilai "default hasil hitung" - murni status kerja manual
            // BP/BPP, karena itu tidak ikut dikosongkan oleh tombol Restore.
            $table->enum('status', ['lengkap', 'belum_lengkap'])->default('belum_lengkap');
            $table->text('catatan')->nullable();

            $table->foreignId('diedit_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diedit_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spj_detail');
    }
};
