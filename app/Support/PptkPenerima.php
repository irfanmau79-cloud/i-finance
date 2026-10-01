<?php

namespace App\Support;

use App\Helpers\PejabatResolver;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;

/**
 * Mode "PPTK Sebagai Penerima" (adopsi GAS #88 & #89).
 *
 * Ada keadaan di mana dana tidak ditransfer ke penerima sebenarnya,
 * melainkan ke PPTK yang kemudian menyelesaikan pembayarannya. Mode ini
 * hanya MENGALIHKAN tujuan transfer; nominal dan isi dokumen lainnya tidak
 * berubah.
 *
 * Nama PPTK TIDAK diambil dari isian formulir seperti di GAS, melainkan
 * diresolusi ulang di server dari pelimpahan sub kegiatan lewat
 * PejabatResolver - sumber yang sama dengan blok tanda tangan PPTK di
 * dokumen. Dengan begitu nama penerima dan nama penanda tangan tidak mungkin
 * berbeda, dan nama PPTK tidak bisa dikarang lewat payload.
 *
 * Nomor rekening dicari di Data Pegawai berdasarkan nama itu. Kalau PPTK
 * belum punya rekening di sana, formulir menyediakan isian manual - sama
 * seperti GAS #89, karena memblokir petugas di tengah pembuatan NPD hanya
 * karena satu kolom master yang kosong justru menghambat pekerjaan.
 */
class PptkPenerima
{
    /** Apakah NPD ini memakai mode PPTK sebagai penerima? */
    public static function aktif(Npd $npd): bool
    {
        return (bool) ($npd->detail_json['pptk_penerima'] ?? false);
    }

    /** Nama PPTK sub kegiatan ini, dari sumber yang sama dengan tanda tangannya. */
    public static function nama(MasterAnggaran $masterAnggaran, ?int $tahun = null): string
    {
        return trim((string) PejabatResolver::untukSubKegiatan(
            $masterAnggaran->program_lengkap,
            $masterAnggaran->sub_kegiatan_lengkap,
            $tahun,
        )['pptk']->nama);
    }

    /**
     * Nomor rekening PPTK: dari Data Pegawai bila ada, kalau tidak dari
     * isian manual pada formulir.
     */
    public static function rekening(string $nama, ?string $manual = null): string
    {
        $dariMaster = trim((string) (Pegawai::cariByNama($nama)?->rekening ?? ''));

        return $dariMaster !== '' ? $dariMaster : trim((string) $manual);
    }

    /**
     * Penerima transfer yang dipakai NPD & Lampiran saat mode ini menyala.
     *
     * @return object{nama: string, rekening: string}
     */
    public static function untukNpd(Npd $npd): object
    {
        $nama = self::nama($npd->masterAnggaran, $npd->tahun);

        return (object) [
            'nama' => $nama,
            'rekening' => self::rekening($nama, $npd->detail_json['pptk_rekening'] ?? null),
        ];
    }
}
