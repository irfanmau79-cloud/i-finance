<?php

namespace App\Services;

use App\Models\Pkpt;
use App\Support\BidangOrganisasi;
use Illuminate\Support\Collection;

/**
 * Agregat halaman Monitoring PKPT. Port dari getMonitoringPKPT() di
 * CodePKPT.gs - MURNI BACA, tidak pernah menulis apa pun.
 *
 * Tiga hal yang sengaja dipertahankan sama persis dengan GAS:
 *  1. Baris tanpa Area DAN tanpa Jenis Kegiatan dilewati - di sheet aslinya
 *     itu baris kosong penyekat, bukan kegiatan.
 *  2. Urutan tabel: unit Irban I..IV lalu Investigasi, baru nomor urut.
 *  3. Opsi filter Periode diurutkan Januari..Desember, bukan alfabetis
 *     ("April" tidak boleh mendahului "Januari").
 */
class PkptService
{
    private const BULAN = [
        'januari', 'februari', 'maret', 'april', 'mei', 'juni',
        'juli', 'agustus', 'september', 'oktober', 'november', 'desember',
    ];

    public const STATUS_TERLAKSANA = 'Terlaksana';

    public const STATUS_BELUM = 'Belum terlaksana';

    /**
     * @return array{
     *   kartu: array<string, float|int>,
     *   perUnit: array<int, array<string, mixed>>,
     *   rows: array<int, array<string, mixed>>,
     *   filterOpts: array{area: array<int, string>, unit: array<int, string>, periode: array<int, string>}
     * }
     */
    public function ringkasan(?int $tahun = null): array
    {
        $rows = $this->barisTerurut($tahun);

        return [
            'kartu' => $this->kartu($rows),
            'perUnit' => $this->perUnit($rows),
            'rows' => $rows->all(),
            'filterOpts' => $this->opsiFilter($rows),
        ];
    }

    /**
     * Baris untuk export Manajemen Data - urutan sama persis dengan tabel di
     * layar, sehingga berkas unduhan bisa dibandingkan berdampingan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ringkasanUntukExport(?int $tahun = null): array
    {
        return $this->barisTerurut($tahun)->all();
    }

    /**
     * Seluruh kegiatan tahun tsb, sudah dipetakan & diurutkan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function barisTerurut(?int $tahun = null): Collection
    {
        $tahun ??= (int) config('anggaran.tahun_aktif');

        return Pkpt::query()
            ->tahun($tahun)
            ->get()
            ->filter(fn (Pkpt $p) => trim((string) $p->area) !== '' || trim((string) $p->jenis_kegiatan) !== '')
            ->map(fn (Pkpt $p) => [
                'id' => $p->id,
                'nomor' => (string) ($p->nomor ?? ''),
                'unit' => (string) ($p->unit_kerja ?? ''),
                'unit_singkat' => $p->unitSingkat(),
                'area' => (string) ($p->area ?? ''),
                'jenis' => (string) ($p->jenis_kegiatan ?? ''),
                'estimasi' => (float) $p->estimasi_anggaran,
                'realisasi' => (float) $p->realisasi,
                'rencana' => (string) ($p->rencana_pelaksanaan ?? ''),
                'pelaksanaan' => (string) ($p->pelaksanaan ?? ''),
                'jumlah_tim' => (string) ($p->jumlah_tim ?? ''),
                'jumlah_laporan' => (string) ($p->jumlah_laporan ?? ''),
                'tujuan' => (string) ($p->tujuan ?? ''),
                'ruang_lingkup' => (string) ($p->ruang_lingkup ?? ''),
                'terlaksana' => (bool) $p->terlaksana,
                'status' => $p->terlaksana ? self::STATUS_TERLAKSANA : self::STATUS_BELUM,
            ])
            ->sort(function (array $a, array $b) {
                $urut = BidangOrganisasi::urutanPkpt($a['unit']) <=> BidangOrganisasi::urutanPkpt($b['unit']);

                return $urut !== 0 ? $urut : $this->angka($a['nomor']) <=> $this->angka($b['nomor']);
            })
            ->values();
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    private function kartu(Collection $rows): array
    {
        $total = $rows->count();
        $terlaksana = $rows->where('terlaksana', true)->count();
        $estimasi = (float) $rows->sum('estimasi');
        $realisasi = (float) $rows->sum('realisasi');

        return [
            'total_kegiatan' => $total,
            'terlaksana' => $terlaksana,
            'belum' => $total - $terlaksana,
            'persen' => $this->persen($terlaksana, $total),
            'total_estimasi' => $estimasi,
            'total_realisasi' => $realisasi,
            'belum_terealisasi' => $estimasi - $realisasi,
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    private function perUnit(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (array $r) => $r['unit'] !== '' ? $r['unit'] : '(Tanpa Unit)')
            ->map(fn (Collection $grup, string $unit) => [
                'unit' => $unit,
                'unit_singkat' => BidangOrganisasi::singkat($unit),
                'total' => $grup->count(),
                'terlaksana' => $grup->where('terlaksana', true)->count(),
                'persen' => $this->persen($grup->where('terlaksana', true)->count(), $grup->count()),
            ])
            ->sortBy(fn (array $u) => [BidangOrganisasi::urutanPkpt($u['unit']), $u['unit']])
            ->values()
            ->all();
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    private function opsiFilter(Collection $rows): array
    {
        $area = $rows->pluck('area')->filter()->unique()->values();
        $area = $area->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))->values();

        $unit = $rows->pluck('unit')->filter()->unique()
            ->sortBy(fn (string $u) => [BidangOrganisasi::urutanPkpt($u), $u])
            ->values();

        $periode = $rows->pluck('rencana')->filter()->unique()
            ->sort(fn (string $a, string $b) => [$this->indeksBulan($a), $a] <=> [$this->indeksBulan($b), $b])
            ->values();

        return ['area' => $area->all(), 'unit' => $unit->all(), 'periode' => $periode->all()];
    }

    /** Persen 1 desimal, 0 bila pembaginya nol. */
    private function persen(int $bagian, int $total): float
    {
        return $total > 0 ? round($bagian / $total * 100, 1) : 0.0;
    }

    /** Nomor PKPT tidak selalu murni angka ("1-IRB1"); ambil angka depannya. */
    private function angka(string $nilai): float
    {
        return (float) preg_replace('/[^0-9.\-]/', '', $nilai);
    }

    /** Indeks bulan dalam teks periode; 99 kalau tidak menyebut bulan sama sekali. */
    private function indeksBulan(string $teks): int
    {
        $teks = mb_strtolower($teks);

        foreach (self::BULAN as $i => $bulan) {
            if (str_contains($teks, $bulan)) {
                return $i;
            }
        }

        return 99;
    }
}
