<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('npd', function (Blueprint $table) {
            $table->id();

            $table->enum('jenis', ['bj', 'pd', 'tr', 'ns', 'kd']);

            $table->foreignId('master_anggaran_id')->constrained('master_anggaran')->restrictOnDelete();
            $table->foreignId('surat_perintah_id')->nullable()->constrained('surat_perintah')->nullOnDelete();

            $table->enum('keu', ['1', '2']);
            $table->integer('nomor_urut')->nullable();
            $table->unsignedTinyInteger('bulan');
            $table->year('tahun');
            $table->string('nomor_lengkap', 100)->nullable();
            $table->date('tanggal_npd');

            $table->decimal('nominal', 18, 2);
            $table->string('terbilang', 500);

            $table->enum('status', [
                'Draft NPD - PPTK',
                'Draft NPD - BPP',
                'Verifikasi - Verifikator',
                'Dikembalikan',
                'NPD Disetujui - BPP',
                'Selesai',
            ]);
            $table->text('catatan')->nullable();

            // Metadata khusus per jenis (mis. pd: berangkat_dari, tujuan, uraian_sp).
            // Angka yang perlu dihitung/dijumlah TIDAK boleh ditaruh di sini.
            $table->json('detail_json')->nullable();

            $table->string('link_pdf_npd', 500)->nullable();
            $table->string('link_pdf_lampiran', 500)->nullable();

            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['keu', 'bulan', 'tahun', 'nomor_urut']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npd');
    }
};
