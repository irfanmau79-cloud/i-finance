<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nomor NPD kini diketik PENUH oleh Verifikator, tidak lagi disusun sistem
     * dari nomor urut + template. Karena itu yang wajib unik bukan lagi
     * (keu, tahun, nomor_urut) melainkan nomor_lengkap itu sendiri.
     *
     * Indeks unik lama dibiarkan: kolom nomor_urut tetap ada untuk data lama,
     * dan barisnya bernilai NULL untuk NPD baru sehingga indeks itu tidak
     * pernah lagi menghalangi (NULL tidak saling bentrok pada indeks unik).
     *
     * Indeks ini yang menjaga dari race condition - dua Verifikator yang
     * menekan Verifikasi bersamaan dengan nomor sama akan ditolak peladen,
     * bukan sekadar oleh pengecekan sebelum simpan.
     */
    public function up(): void
    {
        $duplikat = DB::table('npd')
            ->whereNotNull('nomor_lengkap')
            ->select('nomor_lengkap', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('nomor_lengkap')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplikat) {
            throw new RuntimeException(
                "Nomor NPD duplikat ditemukan: {$duplikat->nomor_lengkap} ({$duplikat->jumlah} dokumen). Rapikan data sebelum migrasi."
            );
        }

        Schema::table('npd', function (Blueprint $table) {
            $table->unique('nomor_lengkap', 'npd_nomor_lengkap_unique');
        });
    }

    public function down(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->dropUnique('npd_nomor_lengkap_unique');
        });
    }
};
