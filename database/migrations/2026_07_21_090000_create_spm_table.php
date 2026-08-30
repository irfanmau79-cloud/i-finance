<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fondasi SPM (Surat Perintah Membayar) — tabel TERPISAH dari npd.
     * Dua jalur belanja:
     *   - up_gu: BPP bayar transaksi lewat NPD, lalu kas diisi ulang lewat SPM
     *     UP/GU yang dicairkan ke Bendahara Pengeluaran (BP) — bukan ke BPP.
     *     Bukan realisasi (cuma mengisi ulang kas), makanya
     *     master_anggaran_id-nya NULL.
     *   - ls: dicairkan langsung di BPKAD ke pihak ketiga, tanpa NPD,
     *     langsung mengurangi pagu. master_anggaran_id WAJIB diisi.
     * Lihat App\Models\Spm::buatLs()/buatUpGu() untuk aturan ini ditegakkan.
     */
    public function up(): void
    {
        Schema::create('spm', function (Blueprint $table) {
            $table->id();

            $table->enum('jenis_spm', ['up_gu', 'ls']);
            $table->date('tanggal_dokumen');
            $table->string('nomor_dokumen', 100);

            // Khusus UP/GU (pencairan).
            $table->date('tanggal_sp2d')->nullable();
            $table->string('nomor_sp2d', 100)->nullable();

            // WAJIB untuk ls, NULL untuk up_gu.
            $table->foreignId('master_anggaran_id')->nullable()->constrained('master_anggaran')->restrictOnDelete();

            $table->decimal('nominal', 18, 2);
            $table->decimal('ppn', 18, 2)->default(0);
            $table->decimal('pph1', 18, 2)->default(0);
            $table->string('jenis_pph1', 50)->nullable();
            $table->decimal('pph2', 18, 2)->default(0);
            $table->string('jenis_pph2', 50)->nullable();

            $table->string('penerima', 255)->nullable();
            $table->text('uraian')->nullable();

            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('nomor_dokumen');
            $table->index('jenis_spm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spm');
    }
};
