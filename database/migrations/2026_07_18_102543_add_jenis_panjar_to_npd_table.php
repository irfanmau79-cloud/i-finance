<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            // Penanda "Panjar" / "Tanpa Panjar" dari sistem lama (GAS). Tidak
            // memengaruhi perhitungan nominal, hanya dicetak di dokumen NPD.
            $table->enum('jenis_panjar', ['Panjar', 'Tanpa Panjar'])->nullable()->after('tanggal_npd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->dropColumn('jenis_panjar');
        });
    }
};
