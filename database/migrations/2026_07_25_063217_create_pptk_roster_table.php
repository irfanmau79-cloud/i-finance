<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar pegawai berstatus PPTK di OPD ini — TIDAK terikat ke KPA
     * tertentu di sini (beda dari kpa_pptk). Keterkaitan ke KPA baru
     * ditentukan otomatis saat sebuah Sub Kegiatan di-assign lewat
     * Pelimpahan::tetapkanBaris(), yang mencari/membuat baris kpa_pptk
     * yang sesuai di belakang layar.
     */
    public function up(): void
    {
        Schema::create('pptk_roster', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamp('dinonaktifkan_at')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('pegawai_aktif_unik')->nullable()
                ->virtualAs('CASE WHEN aktif = 1 THEN pegawai_id ELSE NULL END');
            $table->unique('pegawai_aktif_unik', 'pptk_roster_satu_aktif_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pptk_roster');
    }
};
