<?php

namespace App\Helpers;

use App\Models\DataTambahan;
use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\PejabatOpd;
use App\Models\Pelimpahan;

/**
 * Sumber tunggal KPA/BPP/PPTK (dan PA/Bendahara Pengeluaran level OPD)
 * untuk tanda tangan dokumen NPD — dipakai SEMUA jenis NPD (bj, pd, tr,
 * ns, kd) lewat method terpusat di sini, supaya konsisten.
 *
 * Sumber utama: Pelimpahan per sub kegiatan (KPA -> BPP ikut otomatis,
 * PPTK dari pelimpahan). Kalau sub kegiatan itu belum ada pelimpahannya,
 * fallback ke data_tambahan lama (by program) — port dari resolvePegawai()
 * lama di NpdController — supaya NPD tetap bisa dicetak, bukan gagal.
 */
class PejabatResolver
{
    public static function untukNpd(Npd $npd): array
    {
        return self::untukSubKegiatan($npd->masterAnggaran->program, $npd->masterAnggaran->sub_kegiatan);
    }

    /**
     * $subKegiatan dinormalisasi (whitespace) sebelum dicocokkan ke
     * pelimpahan.kode_sub_kegiatan — lihat MasterAnggaran::normalisasiTeks().
     * Baris master_anggaran yang "sama" sub kegiatannya kadang punya varian
     * whitespace berbeda hasil impor; tanpa normalisasi, pelimpahan yang
     * sudah diset bisa gagal cocok untuk sebagian baris.
     */
    public static function untukSubKegiatan(?string $program, ?string $subKegiatan): array
    {
        $pejabatOpd = PejabatOpd::aktif();
        $pa = self::dariPegawai($pejabatOpd?->paPegawai);
        $bendaharaPengeluaran = self::dariPegawai($pejabatOpd?->bendaharaPengeluaranPegawai);

        $subKegiatanNormal = $subKegiatan !== null ? MasterAnggaran::normalisasiTeks($subKegiatan) : null;

        $pelimpahan = $subKegiatanNormal
            ? Pelimpahan::with(['kpa.kpaPegawai', 'kpa.bppPegawai', 'pptkPegawai'])
                ->where('kode_sub_kegiatan', $subKegiatanNormal)
                ->first()
            : null;

        if ($pelimpahan) {
            return [
                'kpa' => self::dariPegawai($pelimpahan->kpa->kpaPegawai),
                'bpp' => self::dariPegawai($pelimpahan->kpa->bppPegawai),
                'pptk' => self::dariPegawai($pelimpahan->pptkPegawai),
                'pa' => $pa,
                'bendahara_pengeluaran' => $bendaharaPengeluaran,
                'peringatan' => null,
            ];
        }

        // Belum ada pelimpahan untuk sub kegiatan ini — fallback ke data_tambahan
        // lama (by program), sama persis dengan resolvePegawai() lama, supaya
        // NPD tetap bisa dicetak alih-alih gagal.
        $dataTambahan = DataTambahan::untukProgram($program);

        return [
            'kpa' => self::dariNamaBebas($dataTambahan?->kpa),
            'bpp' => self::dariNamaBebas($dataTambahan?->bpp),
            'pptk' => self::dariNamaBebas($dataTambahan?->pptk),
            'pa' => $pa,
            'bendahara_pengeluaran' => $bendaharaPengeluaran,
            'peringatan' => 'Pelimpahan belum diset untuk sub kegiatan ini — memakai data lama (Data Tambahan) sebagai cadangan.',
        ];
    }

    /** True kalau sub kegiatan ini sudah punya pelimpahan. Dipakai untuk tampilkan peringatan di halaman detail NPD tanpa perlu resolve penuh. */
    public static function sudahDiset(?string $subKegiatan): bool
    {
        return $subKegiatan !== null
            && Pelimpahan::where('kode_sub_kegiatan', MasterAnggaran::normalisasiTeks($subKegiatan))->exists();
    }

    private static function dariPegawai(?Pegawai $pegawai): object
    {
        return (object) [
            'nama' => $pegawai->nama ?? '',
            'pangkat' => $pegawai->pangkat ?? '',
            'nip' => $pegawai->nip ?? '',
        ];
    }

    /** Port 1:1 dari resolvePegawai() lama di NpdController: nama bebas -> data pegawai (kalau ketemu), fallback nama apa adanya. */
    private static function dariNamaBebas(?string $nama): object
    {
        $match = Pegawai::cariByNama($nama);

        return (object) [
            'nama' => $match->nama ?? trim((string) $nama),
            'pangkat' => $match->pangkat ?? '',
            'nip' => $match->nip ?? '',
        ];
    }
}
