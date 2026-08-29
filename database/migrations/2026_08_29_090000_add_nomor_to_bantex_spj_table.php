<?php

use App\Models\BantexSpj;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor Penyimpanan bantex/box: dua digit, unik, dan menjadi identitas yang
 * dipakai di label lokasi ("07 - PDTT Irban II"). Kolom lama `keterangan`
 * DIBIARKAN - isinya masih dipakai sebagai catatan bebas dan tidak ada
 * gunanya menghapus data yang sudah terlanjur diisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bantex_spj', function (Blueprint $table) {
            $table->char('nomor', 2)->nullable()->after('id');
        });

        // Bantex yang sudah ada diberi nomor urut mengikuti namanya supaya
        // tidak ada baris tanpa nomor saat kolomnya mulai ditampilkan.
        $urut = 0;
        BantexSpj::query()->orderBy('nama')->get()->each(function (BantexSpj $bantex) use (&$urut) {
            $urut++;
            $bantex->forceFill(['nomor' => str_pad((string) $urut, 2, '0', STR_PAD_LEFT)])->saveQuietly();
        });

        Schema::table('bantex_spj', function (Blueprint $table) {
            $table->unique('nomor');
        });
    }

    public function down(): void
    {
        Schema::table('bantex_spj', function (Blueprint $table) {
            $table->dropUnique(['nomor']);
            $table->dropColumn('nomor');
        });
    }
};
