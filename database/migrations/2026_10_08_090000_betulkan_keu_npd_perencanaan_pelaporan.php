<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kegiatan 6.01.01.1.01 (perencanaan & pelaporan kinerja) dibukukan di
     * Keu.2, bukan Keu.1 seperti sisa program 6.01.01 - lihat
     * MasterAnggaran::tentukanKeu(), adopsi _keuByProgram() "i-finance gas".
     *
     * NPD yang dibuat sebelum aturan itu diadopsi tersimpan dengan keu = 1.
     * Kolom keu dibetulkan supaya nomor default dan tampilan KEU-nya benar.
     * nomor_lengkap TIDAK disentuh: itu nomor yang sudah ditetapkan
     * Verifikator / tercetak di dokumen.
     *
     * Baris yang masih punya nomor_urut dilewati: kolom itu ikut indeks unik
     * lama (keu, tahun, nomor_urut), jadi memindahkan Keu-nya bisa bentrok.
     * Sejak penomoran manual, nomor_urut selalu NULL.
     */
    public function up(): void
    {
        $subKegiatan = DB::table('master_anggaran')
            ->where(fn ($q) => $q
                ->where('kode_sub_kegiatan', '6.01.01.1.01')
                ->orWhere('kode_sub_kegiatan', 'like', '6.01.01.1.01.%'))
            ->select('id');

        DB::table('npd')
            ->whereIn('master_anggaran_id', $subKegiatan)
            ->where('keu', '<>', '2')
            ->whereNull('nomor_urut')
            ->update(['keu' => '2']);
    }

    /** Pembetulan data - tidak ada keadaan lama yang perlu dikembalikan. */
    public function down(): void
    {
        //
    }
};
