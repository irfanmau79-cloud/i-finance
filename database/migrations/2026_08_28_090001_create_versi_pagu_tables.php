<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat pagu berversi: "DPA Murni", "DPA Pergeseran 1", "DPA Perubahan",
 * dan seterusnya.
 *
 * KEPUTUSAN ARSITEKTUR - yang berversi HANYA NOMINAL PAGU, bukan identitas
 * mata anggaran. Baris master_anggaran tetap unik per (kode_sub_kegiatan +
 * kode_rekening + tagging_id) dan TIDAK diduplikasi per versi, karena
 * npd.master_anggaran_id, spm.master_anggaran_id, spm_detail, dan
 * pengembalian_detail semuanya menunjuk ke baris itu. Kalau tiap versi
 * membuat baris master_anggaran baru, seluruh dokumen lama akan menggantung
 * di baris versi lama dan realisasi pecah - lihat MODEL REALISASI di
 * CLAUDE.md.
 *
 * master_anggaran.pagu tetap ada dan berperan sebagai CERMIN pagu versi
 * yang sedang berstatus aktif. Semua konsumen lama (AnggaranRealisasiService,
 * dashboard, validasi sisa_tersedia pada NPD/SPM) membacanya seperti biasa
 * tanpa perlu tahu soal versi. VersiPagu::aktifkan() yang menulis ulang
 * cermin itu dalam satu transaksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versi_pagu', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->string('nama', 150);
            $table->text('keterangan')->nullable();

            // draft = hasil import, belum berlaku. aktif = pagu yang sedang
            // dipakai seluruh aplikasi (maksimum SATU per tahun).
            // arsip = pernah aktif, kini digantikan versi lain.
            $table->string('status', 20)->default('draft');

            $table->decimal('total_pagu', 18, 2)->default(0);
            $table->unsignedInteger('jumlah_baris')->default(0);

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diaktifkan_at')->nullable();
            $table->foreignId('diaktifkan_oleh_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['tahun', 'nama'], 'versi_pagu_tahun_nama_unique');
            $table->index(['tahun', 'status'], 'versi_pagu_tahun_status_index');
        });

        Schema::create('versi_pagu_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('versi_pagu_id')->constrained('versi_pagu')->cascadeOnDelete();
            $table->foreignId('master_anggaran_id')->constrained('master_anggaran')->cascadeOnDelete();

            $table->decimal('pagu', 18, 2);
            $table->boolean('aktif')->default(true);

            $table->timestamps();

            $table->unique(['versi_pagu_id', 'master_anggaran_id'], 'versi_pagu_detail_identitas_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versi_pagu_detail');
        Schema::dropIfExists('versi_pagu');
    }
};
