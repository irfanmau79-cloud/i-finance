<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penugasan KPA + PPTK per SUB KEGIATAN (bukan per baris master_anggaran
     * — satu sub kegiatan bisa punya banyak baris master_anggaran beda kode
     * rekening/tagging, jadi disimpan berdasarkan kode_sub_kegiatan supaya
     * satu setup berlaku untuk semuanya). kode_sub_kegiatan menyimpan nilai
     * yang sama persis dengan master_anggaran.sub_kegiatan. Unik per sub
     * kegiatan — set ulang berarti update, bukan baris baru.
     */
    public function up(): void
    {
        Schema::create('pelimpahan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_sub_kegiatan', 255)->unique();
            $table->foreignId('kpa_id')->constrained('kpa')->restrictOnDelete();
            $table->foreignId('pptk_pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelimpahan');
    }
};
