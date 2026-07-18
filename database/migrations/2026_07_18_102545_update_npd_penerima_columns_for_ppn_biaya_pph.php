<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Kolom 'biaya' sebelumnya dipakai keliru untuk menyimpan netto (bruto-pph).
        // Direname jadi 'biaya_ku_rtgs' karena maksud aslinya memang biaya transfer
        // KU/RTGS (lihat Code.gs _rowsLampiran) — bukan kolom baru yang tak terkait.
        Schema::table('npd_penerima', function (Blueprint $table) {
            $table->renameColumn('biaya', 'biaya_ku_rtgs');
        });

        Schema::table('npd_penerima', function (Blueprint $table) {
            $table->decimal('ppn', 18, 2)->default(0)->after('bruto');
        });

        // Selamatkan nilai pph lama (kolom tunggal) sebagai satu baris di tabel anak,
        // sebelum kolomnya di-drop, supaya data yang sudah terlanjur diinput tidak hilang.
        DB::table('npd_penerima')->where('pph', '>', 0)->orderBy('id')->get()->each(function ($row) {
            DB::table('npd_penerima_pph')->insert([
                'npd_penerima_id' => $row->id,
                'jenis' => 'PPh',
                'nilai' => $row->pph,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // Nilai PPh pindah ke tabel anak npd_penerima_pph (bisa multi-jenis per penerima).
        Schema::table('npd_penerima', function (Blueprint $table) {
            $table->dropColumn('pph');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('npd_penerima', function (Blueprint $table) {
            $table->decimal('pph', 18, 2)->default(0)->after('ppn');
        });

        Schema::table('npd_penerima', function (Blueprint $table) {
            $table->dropColumn('ppn');
        });

        Schema::table('npd_penerima', function (Blueprint $table) {
            $table->renameColumn('biaya_ku_rtgs', 'biaya');
        });
    }
};
