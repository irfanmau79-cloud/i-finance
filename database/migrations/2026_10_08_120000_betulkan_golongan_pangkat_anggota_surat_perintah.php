<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Formulir Input SP dulu memecah isian gabungan "III/a / Penata Muda"
     * pada garis miring PERTAMA, sehingga anggota SP tersimpan sebagai
     * golongan "III" dengan pangkat "a / Penata Muda". Pemecahnya sudah
     * dibetulkan (pecahGolPangkat di surat-perintah/_form); migrasi ini
     * merapikan baris yang sudah telanjur tersimpan salah.
     *
     * Hanya baris yang polanya PERSIS seperti itu yang disentuh: golongan
     * berupa angka Romawi I-IV tanpa huruf, dan pangkat diawali satu huruf
     * a-e lalu garis miring. Baris lain dibiarkan apa adanya.
     */
    public function up(): void
    {
        DB::table('surat_perintah_anggota')
            ->whereIn('golongan', ['I', 'II', 'III', 'IV'])
            ->whereNotNull('pangkat')
            ->orderBy('id')
            ->get(['id', 'golongan', 'pangkat'])
            ->each(function (object $baris) {
                if (! preg_match('/^([a-eA-E])\s*\/\s*(.*)$/', trim((string) $baris->pangkat), $cocok)) {
                    return;
                }

                DB::table('surat_perintah_anggota')->where('id', $baris->id)->update([
                    'golongan' => $baris->golongan.'/'.strtolower($cocok[1]),
                    'pangkat' => trim($cocok[2]),
                ]);
            });
    }

    /** Pembetulan data - bentuk rusaknya tidak perlu dikembalikan. */
    public function down(): void
    {
        //
    }
};
