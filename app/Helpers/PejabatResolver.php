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
        $dataTambahan = DataTambahan::untukProgram($program);

        $programKunci = MasterAnggaran::normalisasiKunci((string) $program);
        $subKegiatanKunci = MasterAnggaran::normalisasiKunci((string) $subKegiatan);

        $pelimpahan = $programKunci !== '' && $subKegiatanKunci !== ''
            ? Pelimpahan::with(['kpa.kpaPegawai', 'kpa.bppPegawai', 'pptkPegawai', 'kpaPptk'])
                ->aktif()
                ->where('program_kunci', $programKunci)
                ->where('sub_kegiatan_kunci', $subKegiatanKunci)
                ->first()
            : null;

        $pelimpahanValid = $pelimpahan
            && $pejabatOpd?->paPegawai?->aktif
            && $pejabatOpd->bendaharaPengeluaranPegawai?->aktif
            && $pelimpahan->kpa?->aktif
            && $pelimpahan->kpa->kpaPegawai?->aktif
            && $pelimpahan->kpa->bppPegawai?->aktif
            && $pelimpahan->pptkPegawai?->aktif
            && $pelimpahan->kpaPptk?->aktif
            && $pelimpahan->kpaPptk->kpa_id === $pelimpahan->kpa_id
            && $pelimpahan->kpaPptk->pptk_pegawai_id === $pelimpahan->pptk_pegawai_id;

        if ($pelimpahanValid) {
            return [
                'kpa' => self::dariPegawai($pelimpahan->kpa->kpaPegawai),
                'bpp' => self::dariPegawai($pelimpahan->kpa->bppPegawai),
                'pptk' => self::dariPegawai($pelimpahan->pptkPegawai),
                'pa' => $pa,
                'bendahara_pengeluaran' => $bendaharaPengeluaran,
                'no_dpa' => $dataTambahan?->no_dpa ?? '',
                'peringatan' => null,
                'fallback_digunakan' => false,
                'sumber' => 'pelimpahan',
            ];
        }

        // Belum ada pelimpahan untuk sub kegiatan ini — fallback ke data_tambahan
        // lama (by program), sama persis dengan resolvePegawai() lama, supaya
        // NPD tetap bisa dicetak alih-alih gagal.
        $peringatan = $pelimpahan
            ? 'Pelimpahan sub kegiatan belum valid atau pejabatnya tidak aktif — memakai data lama (Data Tambahan) sebagai cadangan.'
            : 'Pelimpahan belum diset untuk sub kegiatan ini — memakai data lama (Data Tambahan) sebagai cadangan.';

        return [
            'kpa' => self::dariNamaBebas($dataTambahan?->kpa),
            'bpp' => self::dariNamaBebas($dataTambahan?->bpp),
            'pptk' => self::dariNamaBebas($dataTambahan?->pptk),
            'pa' => $pa,
            'bendahara_pengeluaran' => $bendaharaPengeluaran,
            'no_dpa' => $dataTambahan?->no_dpa ?? '',
            'peringatan' => $peringatan,
            'fallback_digunakan' => true,
            'sumber' => 'data_tambahan',
        ];
    }

    /** True kalau sub kegiatan ini sudah punya pelimpahan. Dipakai untuk tampilkan peringatan di halaman detail NPD tanpa perlu resolve penuh. */
    public static function sudahDiset(?string $subKegiatan, ?string $program = null): bool
    {
        return $subKegiatan !== null && $program !== null
            && Pelimpahan::aktif()
                ->where('program_kunci', MasterAnggaran::normalisasiKunci($program))
                ->where('sub_kegiatan_kunci', MasterAnggaran::normalisasiKunci($subKegiatan))
                ->whereHas('kpa', fn ($query) => $query->where('aktif', true))
                ->whereHas('kpa.kpaPegawai', fn ($query) => $query->where('aktif', true))
                ->whereHas('kpa.bppPegawai', fn ($query) => $query->where('aktif', true))
                ->whereHas('pptkPegawai', fn ($query) => $query->where('aktif', true))
                ->whereHas('kpaPptk', fn ($query) => $query->where('aktif', true))
                ->exists();
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
