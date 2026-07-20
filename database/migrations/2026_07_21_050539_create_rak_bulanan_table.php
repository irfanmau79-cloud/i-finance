<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RAK (Rencana Anggaran Kas) Bulanan - sumber data resmi untuk grafik
     * target bulanan (fitur berikutnya). Sumber acuan lama: sheet Google
     * Sheets "Realisasi RAK" (gas-lama/Code.gs: SHEET_RAK), yang menyimpan
     * SATU BARIS PER SUB KEGIATAN dengan 12 kolom (R:AC) berisi nilai
     * KUMULATIF Jan-Des.
     *
     * Desain di sini SENGAJA berbeda dari sumber lama pada dua hal, keduanya
     * didokumentasikan karena eksplisit diminta:
     *
     * 1. Baris per bulan (bukan baris per sub kegiatan + 12 kolom) - jauh
     *    lebih mudah divalidasi (satu kolom `target`, satu aturan non-negatif)
     *    dan di-query (SUM/AVG per bulan atau rentang bulan tanpa UNPIVOT).
     * 2. `target` di sini adalah nilai BULANAN (bukan kumulatif) - representasi
     *    paling wajar untuk "rencana kas bulan ini", tidak mewajibkan urutan
     *    bulan tidak-menurun seperti kumulatif, dan nilai kumulatif tetap bisa
     *    diturunkan kapan saja lewat SUM(target) WHERE bulan <= N (lihat
     *    MasterAnggaran::targetRakKumulatifSampai()). Tidak ada informasi yang
     *    hilang - hanya dinormalisasi.
     *
     * Terhubung ke master_anggaran_id (bukan sekadar teks sub_kegiatan+kode
     * seperti sheet lama) supaya baris dengan tagging/sumber dana berbeda
     * pada sub kegiatan+kode rekening yang sama tidak ambigu.
     *
     * TIDAK ADA kolom "realisasi" di sini (beda dari kolom AD sheet lama) -
     * realisasi aktual sudah bisa dihitung akurat & real-time dari NPD+SPM
     * lewat MasterAnggaran::realisasiAktual(), jadi tidak perlu disimpan
     * dobel seperti di spreadsheet lama yang butuh cache manual.
     */
    public function up(): void
    {
        Schema::create('rak_bulanan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('master_anggaran_id')->constrained('master_anggaran')->restrictOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->decimal('target', 18, 2);

            $table->timestamps();

            $table->unique(['master_anggaran_id', 'tahun', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rak_bulanan');
    }
};
