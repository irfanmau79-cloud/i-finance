<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "Diterima PPTK" adalah status awal SP sendiri (sebelum tertaut NPD
     * mana pun), terpisah dari status workflow NPD yang di-mirror lewat
     * Npd::mirrorStatusKeSuratPerintah(). Kolom ini sebelumnya cuma berisi
     * status workflow NPD, jadi SP yang baru dibuat tidak punya nilai awal.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE surat_perintah MODIFY status ENUM(
            'Diterima PPTK',
            'Draft NPD - PPTK',
            'Draft NPD - BPP',
            'Verifikasi - Verifikator',
            'Dikembalikan',
            'NPD Disetujui - BPP',
            'Selesai'
        ) NULL");

        // Data lama tanpa status (belum pernah tertaut NPD) dianggap "Diterima PPTK".
        DB::table('surat_perintah')
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '');
            })
            ->update(['status' => 'Diterima PPTK']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('surat_perintah')->where('status', 'Diterima PPTK')->update(['status' => null]);

        DB::statement("ALTER TABLE surat_perintah MODIFY status ENUM(
            'Draft NPD - PPTK',
            'Draft NPD - BPP',
            'Verifikasi - Verifikator',
            'Dikembalikan',
            'NPD Disetujui - BPP',
            'Selesai'
        ) NULL");
    }
};
