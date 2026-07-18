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
        Schema::create('surat_perintah', function (Blueprint $table) {
            $table->id();

            $table->string('nomor_sp', 100);
            $table->date('tanggal_sp');
            $table->enum('unit_kerja', [
                'Inspektur Pembantu I',
                'Inspektur Pembantu II',
                'Inspektur Pembantu III',
                'Inspektur Pembantu IV',
                'Inspektur Pembantu Investigasi',
                'Sekretariat',
                'Subbagian Tata Usaha',
            ]);
            $table->string('lokasi', 100);
            $table->string('nama_pengirim', 100);
            $table->string('tujuan_transfer', 150);
            $table->boolean('irban_dibayar')->default(false);
            $table->string('rincian_tgl_bayar', 255);
            $table->text('keterangan');
            $table->string('file_url', 500);
            $table->enum('status_sp', ['Baru', 'Revisi'])->default('Baru');

            // Terisi menyusul dari proses NPD, bukan input user.
            $table->enum('status', [
                'Draft NPD - PPTK',
                'Draft NPD - BPP',
                'Verifikasi - Verifikator',
                'Dikembalikan',
                'NPD Disetujui - BPP',
                'Selesai',
            ])->nullable();
            $table->string('pengajuan')->nullable();
            $table->text('catatan')->nullable();
            $table->boolean('dipantau')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_perintah');
    }
};
