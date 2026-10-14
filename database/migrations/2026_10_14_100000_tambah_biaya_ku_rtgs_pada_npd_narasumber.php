<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya KU/RTGS per narasumber - potongan transfer, sama seperti n.biaya di
 * _hitungNara() gas-lama/CodeNarasumber.gs: netto = bruto - PPh 21 - biaya.
 * Nominal NPD tetap total bruto. NPD lama bernilai 0, jadi cetakannya tidak
 * berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('npd_narasumber', function (Blueprint $table) {
            $table->decimal('biaya_ku_rtgs', 18, 2)->default(0)->after('pph21');
        });
    }

    public function down(): void
    {
        Schema::table('npd_narasumber', function (Blueprint $table) {
            $table->dropColumn('biaya_ku_rtgs');
        });
    }
};
