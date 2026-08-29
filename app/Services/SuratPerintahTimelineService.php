<?php

namespace App\Services;

use App\Models\Npd;
use App\Models\NpdHistoriStatus;
use App\Models\SuratPerintah;
use Illuminate\Support\Collection;

/**
 * Timeline progres Surat Perintah untuk halaman Monitoring SP.
 *
 * Port dari _susunTimelineSP()/getTimelineSPBatch() di CodeSuratPerintah.gs.
 * MURNI BACA: hanya membaca SP, NPD tertaut, dan histori statusnya - tidak
 * pernah menulis apa pun.
 *
 * Tujuh titik progres, sama urutan dan sumber waktunya dengan GAS:
 *
 *   SP Diterima                  <- tanggal SP masuk (created_at)
 *   NPD Dibuat                   <- NPD tertaut dibuat
 *   Diperiksa BPP                <- aksi 'ajukan_bpp'   (masuk meja BPP)
 *   Verifikasi                   <- aksi 'teruskan'     (masuk antrean verifikator)
 *   Revisi                       <- aksi 'kembali_pptk' TERAKHIR
 *   Persetujuan NPD & Proses IBC <- aksi 'verifikasi'   (verifikator menyetujui)
 *   Selesai                      <- aksi 'selesai'
 *
 * Dua kehalusan yang sengaja dipertahankan dari GAS:
 * 1. Titik pertama berlabel "SPJ Diterima" (bukan "SP Diterima") bila
 *    pengajuannya HANYA transport - termasuk semua SP Reimburse.
 * 2. Titik "Revisi" ditandai tercapai dengan catatan "Tanpa revisi" bila
 *    dokumen sudah lewat verifikator tanpa pernah dikembalikan, supaya
 *    garis waktunya tidak terlihat mandek di tengah.
 */
class SuratPerintahTimelineService
{
    /** Aksi histori yang dipakai timeline, dipetakan ke labelnya. */
    private const AKSI_AJUKAN_BPP = 'ajukan_bpp';

    private const AKSI_TERUSKAN = 'teruskan';

    private const AKSI_KEMBALI_PPTK = 'kembali_pptk';

    private const AKSI_VERIFIKASI = 'verifikasi';

    private const AKSI_SELESAI = 'selesai';

    /**
     * Timeline untuk BANYAK SP sekaligus. NPD dan histori dibaca masing-masing
     * SEKALI lalu disusun di memori - halaman Monitoring SP menampilkan
     * puluhan baris, jadi versi per-baris akan menghasilkan ratusan query.
     *
     * @param  Collection<int, SuratPerintah>  $daftarSp
     * @return array<int, array<string, mixed>> dikunci id surat perintah
     */
    public function untukBanyak(Collection $daftarSp): array
    {
        if ($daftarSp->isEmpty()) {
            return [];
        }

        $npdPerSp = $this->npdTerbaruPerSp($daftarSp->pluck('id')->all());

        $histori = NpdHistoriStatus::query()
            ->whereIn('npd_id', collect($npdPerSp)->pluck('id')->all() ?: [0])
            ->orderBy('nomor_urut')
            ->get()
            ->groupBy('npd_id');

        $hasil = [];

        foreach ($daftarSp as $sp) {
            $npd = $npdPerSp[$sp->id] ?? null;
            $hasil[$sp->id] = $this->susun($sp, $npd, $npd ? ($histori[$npd->id] ?? collect()) : collect());
        }

        return $hasil;
    }

    /** @return array<string, mixed> */
    public function untukSatu(SuratPerintah $sp): array
    {
        return $this->untukBanyak(collect([$sp]))[$sp->id];
    }

    /**
     * NPD tertaut TERBARU untuk tiap SP. Satu SP bisa punya beberapa NPD
     * (mis. NPD Perjalanan Dinas lalu NPD Transport turunannya); GAS memakai
     * yang paling baru, jadi diikuti di sini.
     *
     * @param  array<int, int>  $suratPerintahIds
     * @return array<int, Npd> dikunci surat_perintah_id
     */
    private function npdTerbaruPerSp(array $suratPerintahIds): array
    {
        return Npd::query()
            ->whereIn('surat_perintah_id', $suratPerintahIds)
            ->orderBy('surat_perintah_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'surat_perintah_id', 'nomor_lengkap', 'status', 'created_at'])
            ->groupBy('surat_perintah_id')
            ->map(fn (Collection $npd) => $npd->first())
            ->all();
    }

    /**
     * @param  Collection<int, NpdHistoriStatus>  $histori
     * @return array<string, mixed>
     */
    private function susun(SuratPerintah $sp, ?Npd $npd, Collection $histori): array
    {
        $hanyaTransport = $this->hanyaTransport($sp);

        // Aksi yang sama bisa terjadi berkali-kali (dokumen bolak-balik
        // revisi): simpan yang PERTAMA dan TERAKHIR, lalu tiap titik memilih
        // sendiri mana yang dipakai.
        $pertama = [];
        $terakhir = [];

        foreach ($histori as $baris) {
            $pertama[$baris->aksi] ??= $baris;
            $terakhir[$baris->aksi] = $baris;
        }

        $titik = [];
        $tambah = function (string $label, ?NpdHistoriStatus $baris, string $peran, ?string $waktu = null) use (&$titik) {
            $ts = $waktu ?? $baris?->created_at?->format('d/m/Y H:i');
            $titik[] = [
                'label' => $label,
                'ts' => $ts,
                'tercapai' => $ts !== null,
                'peran' => $peran,
                'catatan' => '',
            ];
        };

        $tambah(
            $hanyaTransport ? 'SPJ Diterima' : 'SP Diterima',
            null,
            'PPTK',
            $sp->created_at?->format('d/m/Y H:i')
        );

        $tambah('NPD Dibuat', null, 'PPTK', $npd?->created_at?->format('d/m/Y H:i'));
        $tambah('Diperiksa BPP', $pertama[self::AKSI_AJUKAN_BPP] ?? null, 'BPP');

        $keVerifikator = $pertama[self::AKSI_TERUSKAN] ?? null;
        $tambah('Verifikasi', $keVerifikator, 'Verifikator');

        $revisi = $terakhir[self::AKSI_KEMBALI_PPTK] ?? null;

        if ($revisi) {
            $tambah('Revisi', $revisi, 'PPTK');
        } elseif ($keVerifikator) {
            // Sudah lewat verifikator tanpa pernah dikembalikan.
            $titik[] = ['label' => 'Revisi', 'ts' => null, 'tercapai' => true, 'peran' => '', 'catatan' => 'Tanpa revisi'];
        } else {
            $tambah('Revisi', null, 'PPTK');
        }

        $tambah('Persetujuan NPD & Proses IBC', $pertama[self::AKSI_VERIFIKASI] ?? null, 'BPP');
        $tambah('Selesai', $pertama[self::AKSI_SELESAI] ?? null, 'BPP');

        return [
            'hanya_transport' => $hanyaTransport,
            'ada_npd' => $npd !== null,
            'nomor_npd' => $npd?->nomor_lengkap ?? '',
            'npd_id' => $npd?->id,
            'titik' => $titik,
        ];
    }

    /**
     * SP Reimburse selalu dianggap hanya transport. Selain itu, ditentukan
     * dari kolom Pengajuan: dianggap hanya transport bila SELURUH komponen
     * yang dipilih mengandung kata "transport".
     */
    private function hanyaTransport(SuratPerintah $sp): bool
    {
        if ($sp->isReimburse()) {
            return true;
        }

        $komponen = $sp->pengajuanArray();

        return $komponen !== [] && collect($komponen)
            ->every(fn (string $item) => str_contains(mb_strtolower($item), 'transport'));
    }
}
