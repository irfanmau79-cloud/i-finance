<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Tambah Jenis BBM": satu anggota bisa mengisi BBM dengan lebih dari satu
 * tarif per liter (mis. Pertamax dan Dexlite dalam satu perjalanan).
 *
 * Jenis BBM PERTAMA tetap di bbm_nominal/bbm_tarif; kolom ini hanya memuat
 * tambahannya, sebagai daftar {nominal, tarif}. Null = tidak ada tambahan,
 * jadi NPD yang sudah ada tidak berubah - lihat
 * App\Helpers\NpdPerjalananHitung::rincianBbm().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('npd_tim', function (Blueprint $table) {
            $table->json('bbm_lain')->nullable()->after('bbm_tarif');
        });
    }

    public function down(): void
    {
        Schema::table('npd_tim', function (Blueprint $table) {
            $table->dropColumn('bbm_lain');
        });
    }
};
