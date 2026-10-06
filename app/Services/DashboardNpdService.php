<?php

namespace App\Services;

use App\Models\Npd;
use App\Models\Pegawai;
use App\Models\Spm;
use Illuminate\Support\Collection;

/**
 * Dashboard Nota Pencairan Dana: pemantauan pencairan lewat NPD.
 *
 * Seperti dashboard lain, seluruh angkanya DIHITUNG dari transaksi tiap kali
 * halaman dibuka - tidak ada yang disimpan.
 *
 * Dua kelompok rincian ditentukan KODE REKENING mata anggarannya, bukan jenis
 * formulirnya: semua NPD pada rekening Perjalanan Dinas Biasa/Dalam Kota
 * (termasuk NPD Transport dan Kontribusi Diklat mode perjalanan) masuk
 * "Perjalanan Dinas"; selebihnya - Barang/Jasa, Kontribusi, Narasumber -
 * masuk "Barang/Jasa".
 */
class DashboardNpdService
{
    public const KELOMPOK = [
        'bj' => 'NPD Barang/Jasa',
        'pd' => 'NPD Perjalanan Dinas',
    ];

    /** Unit Kerja untuk penerima yang tidak ada di Data Pegawai. */
    public const PIHAK_KETIGA = 'Pihak Ketiga';

    /**
     * @param  array{status: string, bulan: string, unit: string, cari: string}  $filters
     */
    public function ringkasan(array $filters, int $tahun): array
    {
        $pegawai = Pegawai::query()->get(['id', 'nama', 'bidang']);
        $bidangPerId = $pegawai->pluck('bidang', 'id');
        $bidangPerNama = $pegawai->mapWithKeys(fn (Pegawai $p) => [$this->kunciNama($p->nama) => $p->bidang]);

        $semua = Npd::query()
            ->with(['masterAnggaran', 'penerima', 'tim', 'narasumber', 'peserta', 'suratPerintah:id,nomor_sp'])
            ->where('tahun', $tahun)
            ->where('status', '!=', 'Dibatalkan')
            ->orderByDesc('tanggal_npd')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Npd $npd) => $this->baris($npd, $bidangPerId, $bidangPerNama));

        // Kartu NPD Selesai / Dalam Proses mengikuti saringan lain (bulan,
        // unit, kata kunci) tetapi TIDAK saringan status - kalau ikut,
        // menekan satu kartu akan menolkan kartu satunya.
        $tanpaStatus = $this->saring($semua, ['status' => ''] + $filters);
        $rows = $this->saring($semua, $filters);

        $selesai = $tanpaStatus->where('selesai', true);
        $proses = $tanpaStatus->where('selesai', false);

        return [
            'kpi' => [
                'selesai' => ['jumlah' => $selesai->count(), 'nominal' => (float) $selesai->sum('nominal')],
                'proses' => ['jumlah' => $proses->count(), 'nominal' => (float) $proses->sum('nominal')],
                'sisa_up' => $this->sisaUangPersediaan($tahun),
            ],
            'kelompok' => collect(self::KELOMPOK)->map(function (string $label, string $kunci) use ($rows) {
                $isi = $rows->where('kelompok', $kunci)->values();

                return [
                    'kunci' => $kunci,
                    'label' => $label,
                    'jumlah' => $isi->count(),
                    'nominal' => (float) $isi->sum('nominal'),
                    'rows' => $isi->all(),
                ];
            })->values()->all(),
            'pilihan_unit' => $semua->pluck('unit_kerja')->unique()->sort()->values()->all(),
            'pilihan_bulan' => $semua->pluck('bulan')->unique()->sort()->values()->all(),
            'kosong' => $rows->isEmpty(),
        ];
    }

    /**
     * Sisa Uang Persediaan (IBC) = jumlah SP2D UP/GU/TU dikurangi NPD yang
     * sudah Selesai, pada tahun anggaran ini.
     *
     * Rumus dari kantor (Irfan, Oktober 2026). Ini posisi kas persediaan,
     * BUKAN sisa pagu: SP2D UP/GU/TU mengisi kas dan tidak mengurangi pagu,
     * sedangkan NPD Selesai adalah uang yang sudah keluar dari kas itu.
     *
     * @return array{sp2d: float, npd_selesai: float, sisa: float}
     */
    public function sisaUangPersediaan(int $tahun): array
    {
        $sp2d = (float) Spm::query()->where('jenis_spm', 'up_gu')->whereYear('tanggal_dokumen', $tahun)->sum('nominal');
        $npdSelesai = (float) Npd::query()->where('tahun', $tahun)->where('status', 'Selesai')->sum('nominal');

        return ['sp2d' => $sp2d, 'npd_selesai' => $npdSelesai, 'sisa' => $sp2d - $npdSelesai];
    }

    private function saring(Collection $rows, array $filters): Collection
    {
        return $rows
            ->when(($filters['status'] ?? '') === 'selesai', fn (Collection $r) => $r->where('selesai', true))
            ->when(($filters['status'] ?? '') === 'proses', fn (Collection $r) => $r->where('selesai', false))
            ->when($filters['bulan'] ?? '', fn (Collection $r, $v) => $r->where('bulan', (int) $v))
            ->when($filters['unit'] ?? '', fn (Collection $r, $v) => $r->where('unit_kerja', $v))
            ->when($filters['cari'] ?? '', function (Collection $r, $v) {
                $cari = mb_strtolower((string) $v);

                return $r->filter(fn (array $row) => str_contains(mb_strtolower(implode(' ', [
                    $row['nomor'], $row['penerima'], $row['unit_kerja'], $row['uraian'], $row['status'],
                ])), $cari));
            })
            ->values();
    }

    private function baris(Npd $npd, Collection $bidangPerId, Collection $bidangPerNama): array
    {
        return [
            'id' => $npd->id,
            'kelompok' => SpjDashboardService::adalahPerjalananDinas($npd) ? 'pd' : 'bj',
            'jenis' => Npd::JENIS_LABEL[$npd->jenis] ?? strtoupper((string) $npd->jenis),
            'nomor' => $npd->nomorDokumen(),
            'tanggal' => $npd->tanggal_npd->format('d-m-Y'),
            'bulan' => (int) $npd->tanggal_npd->month,
            'penerima' => $npd->ringkasanPenerima(),
            'unit_kerja' => $this->unitKerja($npd, $bidangPerId, $bidangPerNama),
            'nominal' => (float) $npd->nominal,
            'status' => $npd->status,
            'badge' => Npd::STATUS_BADGE_CLASS[$npd->status] ?? 'st-npd',
            'selesai' => $npd->status === 'Selesai',
            'uraian' => $npd->uraianRingkas(),
        ];
    }

    /**
     * Unit Kerja = unit (bidang) penerima utama di Data Pegawai. Penerima
     * yang bukan pegawai - rekanan, narasumber luar - ditulis "Pihak Ketiga".
     *
     * Penerima utama mengikuti Npd::ringkasanPenerima(): baris penerima
     * pertama untuk Barang/Jasa, anggota bertanda penerima untuk perjalanan,
     * narasumber pertama, dan peserta terpilih untuk Kontribusi Diklat.
     */
    private function unitKerja(Npd $npd, Collection $bidangPerId, Collection $bidangPerNama): string
    {
        $utama = match ($npd->jenis) {
            'pd', 'tr' => $npd->tim->firstWhere('is_penerima', true) ?? $npd->tim->first(),
            'ns' => $npd->narasumber->first(),
            'kd' => $npd->peserta->get((int) ($npd->detail_json['penerima_index'] ?? 0)) ?? $npd->peserta->first(),
            default => $npd->penerima->first(),
        };

        if ($utama === null) {
            return self::PIHAK_KETIGA;
        }

        $bidang = ($utama->pegawai_id ? $bidangPerId->get($utama->pegawai_id) : null)
            // Unit saat perjalanan dilakukan, bila tercatat di anggota tim.
            ?: ($utama->bidang_snapshot ?? null)
            ?: $bidangPerNama->get($this->kunciNama($utama->nama));

        return filled($bidang) ? (string) $bidang : self::PIHAK_KETIGA;
    }

    private function kunciNama(?string $nama): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $nama)));
    }
}
