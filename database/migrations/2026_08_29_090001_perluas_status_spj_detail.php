<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status SPJ bertambah dari dua menjadi empat: Lengkap, Belum Lengkap,
 * Dikembalikan, Tidak Ditemukan. Tipenya diubah dari enum menjadi string
 * supaya penambahan status berikutnya tidak perlu mengubah skema lagi -
 * daftar nilainya ditegakkan di SpjDetail::STATUS dan aturan validasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spj_detail', function (Blueprint $table) {
            $table->string('status', 20)->default('belum_lengkap')->change();
        });
    }

    public function down(): void
    {
        Schema::table('spj_detail', function (Blueprint $table) {
            $table->enum('status', ['lengkap', 'belum_lengkap'])->default('belum_lengkap')->change();
        });
    }
};
