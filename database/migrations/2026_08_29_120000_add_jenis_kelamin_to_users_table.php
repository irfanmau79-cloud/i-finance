<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis kelamin dipakai untuk menyapa pengguna dengan benar di bilah atas:
 * "Pak" untuk laki-laki, "Bu" untuk perempuan. Nullable karena akun lama
 * belum mengisinya - selama kosong, sapaannya tetap "Pak/Bu".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('jenis_kelamin', 1)->nullable()->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('jenis_kelamin');
        });
    }
};
