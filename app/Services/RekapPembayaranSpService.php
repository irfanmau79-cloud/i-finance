<?php

namespace App\Services;

use App\Models\Npd;
use App\Models\NpdPeserta;
use App\Models\NpdTim;
use App\Models\SuratPerintah;
use Illuminate\Support\Collection;

/**
 * Rekapitulasi Pembayaran SP: per Surat Perintah, komponen mana (Uang Harian,
 * Akomodasi, Transport) yang SUDAH dibuatkan NPD-nya.
 *
 * Tidak ada yang disimpan - semuanya dibaca dari NPD yang tertaut, sama
 * seperti realisasi anggaran:
 *
 *   - Satu baris per NOMOR SP. SP asli dan duplikatnya (lihat
 *     SuratPerintah::isDuplikat) digabung, karena duplikat justru dibuat
 *     supaya satu SP bisa dibayar lewat beberapa NPD - mis. Uang Harian di
 *     NPD pertama dan Transport di NPD berikutnya.
 *   - Sebuah komponen dianggap dibayar bila NPD-nya memuat NILAI untuk
 *     komponen itu (bukan sekadar SP-nya punya NPD): NPD yang hanya berisi
 *     uang harian tidak mencentang Transport.
 *   - NPD berstatus Dibatalkan tidak dihitung. NPD yang masih berjalan
 *     (Draft s.d. Disetujui) mencentang sebagai PROSES; yang sudah Selesai
 *     mencentang sebagai SELESAI, dan SELESAI menang bila keduanya ada.
 */
class RekapPembayaranSpService
{
    public const UANG_HARIAN = 'uang_harian';

    public const AKOMODASI = 'akomodasi';

    public const TRANSPORT = 'transport';

    /** Kunci komponen => judul kolom, urut seperti di tabel. */
    public const KOMPONEN = [
        self::UANG_HARIAN => 'Uang Harian',
        self::AKOMODASI => 'Akomodasi',
        self::TRANSPORT => 'Transport',
    ];

    /** NPD-nya sudah dibuat tetapi belum Selesai. */
    public const PROSES = 'proses';

    public const SELESAI = 'selesai';

    /**
     * @return Collection<int, array{
     *     nomor_sp: string, unit_kerja: string, koordinator: string, keterangan: string,
     *     jumlah_npd: int,
     *     pembayaran: array<string, array{status: ?string, npd: array<int, string>}>
     * }>
     */
    public function baris(): Collection
    {
        $suratPerintah = SuratPerintah::query()
            ->orderByDesc('tanggal_sp')
            ->orderByDesc('id')
            ->get(['id', 'nomor_sp', 'duplikat_ke', 'unit_kerja', 'tujuan_transfer', 'keterangan']);

        $npdPerSp = Npd::query()
            ->whereIn('surat_perintah_id', $suratPerintah->pluck('id'))
            ->where('status', '!=', 'Dibatalkan')
            ->with(['tim.paket', 'peserta'])
            ->orderBy('id')
            ->get()
            ->groupBy('surat_perintah_id');

        return $suratPerintah
            ->groupBy('nomor_sp')
            ->map(function (Collection $kelompok) use ($npdPerSp) {
                // Identitas baris diambil dari SP aslinya (duplikat_ke terkecil).
                $utama = $kelompok->sortBy('duplikat_ke')->first();
                $npd = $kelompok->flatMap(fn (SuratPerintah $sp) => $npdPerSp->get($sp->id, collect()))->values();

                return [
                    'nomor_sp' => (string) $utama->nomor_sp,
                    'unit_kerja' => (string) $utama->unit_kerja,
                    'koordinator' => (string) $utama->tujuan_transfer,
                    'keterangan' => (string) $utama->keterangan,
                    'jumlah_npd' => $npd->count(),
                    'pembayaran' => $this->pembayaran($npd),
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, Npd>  $npd
     * @return array<string, array{status: ?string, npd: array<int, string>}>
     */
    private function pembayaran(Collection $npd): array
    {
        $hasil = [];

        foreach (array_keys(self::KOMPONEN) as $komponen) {
            $memuat = $npd->filter(fn (Npd $satu) => $this->nilaiKomponen($satu)[$komponen] > 0);

            $hasil[$komponen] = [
                'status' => match (true) {
                    $memuat->isEmpty() => null,
                    $memuat->contains(fn (Npd $satu) => $satu->status === 'Selesai') => self::SELESAI,
                    default => self::PROSES,
                },
                // Untuk keterangan saat kotaknya disorot.
                'npd' => $memuat->map(fn (Npd $satu) => $satu->nomorCetak().' ('.$satu->status.')')->values()->all(),
            ];
        }

        return $hasil;
    }

    /**
     * Nilai tiap komponen pada satu NPD.
     *
     * Perjalanan Dinas ('pd') dan Transport lama ('tr') menyimpan rinciannya
     * per anggota tim; Kontribusi Diklat mode Perjalanan Dinas ('kd')
     * menyimpannya per peserta. Jenis lain tidak membayar perjalanan.
     *
     * @return array<string, float>
     */
    private function nilaiKomponen(Npd $npd): array
    {
        if (in_array($npd->jenis, ['pd', 'tr'], true)) {
            $hitung = $npd->tim->map(fn (NpdTim $anggota) => $anggota->hitung());

            return [
                self::UANG_HARIAN => (float) $hitung->sum('jml_harian'),
                self::AKOMODASI => (float) $hitung->sum('jml_akom'),
                self::TRANSPORT => (float) $hitung->sum('jml_transport'),
            ];
        }

        if ($npd->jenis === 'kd' && $npd->mode_kd === 'perjalanan') {
            return [
                self::UANG_HARIAN => (float) $npd->peserta->sum(fn (NpdPeserta $p) => $p->jumlah_harian),
                self::AKOMODASI => (float) $npd->peserta->sum(fn (NpdPeserta $p) => $p->jumlah_akomodasi),
                self::TRANSPORT => (float) $npd->peserta->sum(fn (NpdPeserta $p) => (float) $p->transport),
            ];
        }

        return [self::UANG_HARIAN => 0.0, self::AKOMODASI => 0.0, self::TRANSPORT => 0.0];
    }
}
