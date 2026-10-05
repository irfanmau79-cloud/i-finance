<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jumlah Liter BBM tidak lagi dibatasi dua angka di belakang koma.
 *
 * Liter sengaja diketik panjang (mis. 10,123456) supaya liter x tarif
 * dibulatkan tepat ke nominal BBM yang dituju. Dengan decimal(10,2) angka
 * itu terpotong saat disimpan, sehingga nominal yang dihitung ulang di
 * server dan di PDF bisa meleset dari yang terlihat di formulir.
 *
 * Sepuluh desimal = praktis tanpa batas untuk keperluan ini; nilai lama
 * (dua desimal) tetap sama persis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('npd_tim', function (Blueprint $table) {
            $table->decimal('bbm_liter', 20, 10)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('npd_tim', function (Blueprint $table) {
            $table->decimal('bbm_liter', 10, 2)->default(0)->change();
        });
    }
};
