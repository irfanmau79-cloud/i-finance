<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BBM kini diisi sebagai Total Nominal (rupiah); liternya diturunkan dari
 * nominal dibagi tarif per liter.
 *
 * Kolomnya nullable DENGAN SENGAJA: null menandai NPD lama yang BBM-nya
 * masih dihitung liter x tarif. Baris lama tidak diisi ulang, supaya dokumen
 * yang sudah ditandatangani tetap tercetak persis sama - lihat
 * App\Helpers\NpdPerjalananHitung::bbm().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('npd_tim', function (Blueprint $table) {
            $table->decimal('bbm_nominal', 15, 2)->nullable()->after('rekening');
        });
    }

    public function down(): void
    {
        Schema::table('npd_tim', function (Blueprint $table) {
            $table->dropColumn('bbm_nominal');
        });
    }
};
