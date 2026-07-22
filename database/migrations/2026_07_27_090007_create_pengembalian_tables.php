<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengembalian adalah pengurang terhadap REALISASI AKTUAL (NPD Selesai
     * atau SPM LS), BUKAN pengubah data transaksi mentah - SP2D/NPD/SPM
     * tetap tercatat apa adanya. dokumen_id merujuk ke npd.id atau spm.id
     * tergantung dokumen_tipe (tidak bisa FK tunggal karena dua tabel
     * sumber berbeda - divalidasi di level aplikasi lewat
     * Pengembalian::nominalAsliPerMataAnggaran()).
     */
    public function up(): void
    {
        Schema::create('pengembalian', function (Blueprint $table) {
            $table->id();

            $table->enum('dokumen_tipe', ['npd', 'spm_ls']);
            $table->unsignedBigInteger('dokumen_id');

            $table->date('tanggal_pengembalian');

            // Selalu disk private ('local'), nama file diacak - lihat
            // PengembalianController. Nullable saat draft, wajib sebelum disetujui.
            $table->string('dokumen_pendukung', 500)->nullable();

            $table->text('keterangan')->nullable();

            $table->enum('status', ['draft', 'disetujui'])->default('draft');

            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_at')->nullable();

            $table->timestamps();

            $table->index(['dokumen_tipe', 'dokumen_id']);
            $table->index('status');
        });

        // Breakdown per mata anggaran dalam satu pengembalian. Diimplementasikan
        // sebagai LIST (bukan kolom tunggal) walau struktur NPD/SPM LS saat ini
        // (satu master_anggaran_id per NPD, per baris spm_detail) biasanya
        // menghasilkan 1 baris per dokumen - supaya tetap benar bila struktur
        // dokumen sumber berubah di masa depan.
        Schema::create('pengembalian_detail', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pengembalian_id')->constrained('pengembalian')->cascadeOnDelete();
            $table->foreignId('master_anggaran_id')->constrained('master_anggaran')->restrictOnDelete();
            $table->decimal('nominal', 18, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengembalian_detail');
        Schema::dropIfExists('pengembalian');
    }
};
