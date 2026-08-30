<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sisa anggaran yang diketik sendiri oleh pembuat NPD. HANYA dipakai
     * untuk kolom "SISA ANGGARAN" pada PDF NPD — tidak pernah ikut dalam
     * perhitungan mana pun di sistem (pagu, dana terikat, realisasi, dan
     * sisa tersedia tetap dihitung dari transaksi lewat MasterAnggaran,
     * lihat AnggaranRealisasiService).
     *
     * Kolomnya nullable dan tetap ada walau nanti input manualnya dikunci
     * kembali (config anggaran.sisa_manual_npd): dokumen yang sudah
     * terlanjur dicetak dengan angka manual harus tetap bisa dicetak ulang
     * dengan angka yang sama.
     */
    public function up(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->decimal('sisa_anggaran_manual', 18, 2)->nullable()->after('nominal');
        });
    }

    public function down(): void
    {
        Schema::table('npd', function (Blueprint $table) {
            $table->dropColumn('sisa_anggaran_manual');
        });
    }
};
