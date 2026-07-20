<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KPA (Kuasa Pengguna Anggaran) — bisa banyak, tiap KPA punya TEPAT 1
     * BPP (Bendahara Pengeluaran Pembantu). BEDA dari role login 'bpp'
     * (akses sistem) — ini jabatan untuk tanda tangan dokumen.
     */
    public function up(): void
    {
        Schema::create('kpa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpa_pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('bpp_pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->string('nama_jabatan', 150)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpa');
    }
};
