<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pejabat level OPD (1 baris aktif): Pengguna Anggaran (PA, 1 orang —
     * kepala OPD) dan Bendahara Pengeluaran (1 orang, milik PA).
     *
     * PENTING: "Bendahara Pengeluaran" di sini adalah JABATAN untuk tanda
     * tangan dokumen, level OPD. Ini BEDA dari "BPP" (Bendahara Pengeluaran
     * Pembantu, level KPA, lihat tabel kpa) dan BEDA dari role login
     * 'superadmin' (akses sistem) — jangan disamakan satu sama lain.
     */
    public function up(): void
    {
        Schema::create('pejabat_opd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pa_pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->foreignId('bendahara_pengeluaran_pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pejabat_opd');
    }
};
