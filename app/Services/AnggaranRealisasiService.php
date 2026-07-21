<?php

namespace App\Services;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\RakBulanan;
use App\Models\Spm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnggaranRealisasiService
{
    public const BULAN = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    /**
     * Pilihan filter berasal dari master aktif. Kode Rekening menyempit
     * mengikuti Sub Kegiatan yang dipilih.
     */
    public function pilihanFilter(?string $subKegiatanKunci = null): array
    {
        $masters = MasterAnggaran::query()
            ->where('aktif', true)
            ->with('tagging:id,nama')
            ->orderBy('sub_kegiatan_normal')
            ->orderBy('kode_rekening')
            ->get(['id', 'sub_kegiatan', 'sub_kegiatan_kunci', 'kode_rekening', 'tagging_id']);

        $kodeScope = $subKegiatanKunci
            ? $masters->where('sub_kegiatan_kunci', $subKegiatanKunci)
            : $masters;

        return [
            'sub_kegiatan' => $masters
                ->unique('sub_kegiatan_kunci')
                ->map(fn (MasterAnggaran $item) => [
                    'value' => $item->sub_kegiatan_kunci,
                    'label' => $item->subKegiatanNormal(),
                ])->values(),
            'kode_rekening' => $kodeScope->pluck('kode_rekening')->unique()->sort()->values(),
            'tagging' => $masters->whereNotNull('tagging_id')
                ->unique('tagging_id')
                ->map(fn (MasterAnggaran $item) => [
                    'value' => (string) $item->tagging_id,
                    'label' => $item->tagging?->nama ?? 'Tagging tidak tersedia',
                ])
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
            'tanpa_tagging' => $masters->contains(fn (MasterAnggaran $item) => $item->tagging_id === null),
        ];
    }

    /** @param array{sub_kegiatan: string, kode_rekening: string, tagging: string, q: string} $filters */
    public function rincian(array $filters): array
    {
        $query = $this->masterQuery($filters)
            ->with('tagging:id,nama')
            ->withSum([
                'npd as dana_terikat_npd_total' => fn (Builder $query) => $query->where('status', 'not like', '%batal%'),
            ], 'nominal')
            ->withSum([
                'npd as realisasi_npd_total' => fn (Builder $query) => $query->where('status', 'Selesai'),
            ], 'nominal')
            ->withSum([
                'spm as realisasi_ls_total' => fn (Builder $query) => $query->where('jenis_spm', 'ls'),
            ], 'nominal');

        $masters = $query
            ->orderBy('sub_kegiatan_normal')
            ->orderBy('kode_rekening')
            ->orderBy('tagging_id')
            ->get();

        $tree = $masters
            ->groupBy('sub_kegiatan_kunci')
            ->map(function (Collection $subItems) {
                $rekening = $subItems->groupBy('kode_rekening')->map(function (Collection $rekeningItems, string $kode) {
                    $tagging = $rekeningItems->map(fn (MasterAnggaran $master) => [
                        'id' => $master->id,
                        'nama' => $master->tagging?->nama ?? 'Tanpa Tagging',
                        'angka' => $master->ringkasanRealisasi(),
                    ])->values();

                    return [
                        'kode' => $kode,
                        'uraian' => $rekeningItems->first()->uraian_rekening,
                        'tagging' => $tagging,
                        'angka' => $this->agregasiRingkasan($tagging->pluck('angka')),
                    ];
                })->values();

                $first = $subItems->first();

                return [
                    'kunci' => $first->sub_kegiatan_kunci,
                    'nama' => $first->subKegiatanNormal(),
                    'program' => $first->programNormal(),
                    'kegiatan' => $first->kegiatanNormal(),
                    'rekening' => $rekening,
                    'angka' => $this->agregasiRingkasan($rekening->pluck('angka')),
                ];
            })->values();

        return ['tree' => $tree, 'total' => $this->agregasiRingkasan($tree->pluck('angka'))];
    }

    /**
     * Agregasi analisis dari transaksi Laravel dan RAK resmi.
     *
     * @param  array{sub_kegiatan?: string, kode_rekening?: string}  $filters
     */
    public function analisis(array $filters, int $tahun, int $bulanAcuan): array
    {
        $filters = [
            'sub_kegiatan' => (string) ($filters['sub_kegiatan'] ?? ''),
            'kode_rekening' => (string) ($filters['kode_rekening'] ?? ''),
        ];
        $bulanAcuan = max(1, min(12, $bulanAcuan));

        $masterQuery = $this->masterQuery($filters);
        $pagu = (float) (clone $masterQuery)->sum('pagu');
        $jumlahMaster = (clone $masterQuery)->count();
        $pasangan = (clone $masterQuery)
            ->select('sub_kegiatan_kunci', 'kode_rekening')
            ->distinct()
            ->get();

        $realisasiBulanan = array_fill(0, 12, 0.0);
        Npd::query()
            ->where('status', 'Selesai')
            ->whereYear('tanggal_npd', $tahun)
            ->whereHas('masterAnggaran', fn (Builder $query) => $this->terapkanFilterMaster($query, $filters))
            ->get(['tanggal_npd', 'nominal'])
            ->each(function (Npd $npd) use (&$realisasiBulanan) {
                $realisasiBulanan[$npd->tanggal_npd->month - 1] += (float) $npd->nominal;
            });

        Spm::query()
            ->where('jenis_spm', 'ls')
            ->whereYear('tanggal_dokumen', $tahun)
            ->whereHas('masterAnggaran', fn (Builder $query) => $this->terapkanFilterMaster($query, $filters))
            ->get(['tanggal_dokumen', 'nominal'])
            ->each(function (Spm $spm) use (&$realisasiBulanan) {
                $realisasiBulanan[$spm->tanggal_dokumen->month - 1] += (float) $spm->nominal;
            });

        $rakRows = $this->rakQuery($filters, $tahun)->get(['bulan', 'target']);
        $jumlahPasangan = $pasangan->count();
        $targetBulanan = [];
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $rowsBulan = $rakRows->where('bulan', $bulan);
            $targetBulanan[] = $jumlahPasangan > 0 && $rowsBulan->count() === $jumlahPasangan
                ? (float) $rowsBulan->sum('target')
                : null;
        }

        $realisasiKumulatif = $this->kumulatif($realisasiBulanan);
        $targetKumulatif = $this->kumulatifNullable($targetBulanan);
        $rakTersedia = $rakRows->isNotEmpty();
        $rakLengkapSdAcuan = $jumlahPasangan > 0
            && $rakRows->where('bulan', '<=', $bulanAcuan)->count() === $jumlahPasangan * $bulanAcuan;
        $rakLengkapTahun = $jumlahPasangan > 0 && $rakRows->count() === $jumlahPasangan * 12;

        $realisasiAktual = (float) array_sum($realisasiBulanan);
        $realisasiSdAcuan = $realisasiKumulatif[$bulanAcuan - 1];
        $rakSdAcuan = $rakLengkapSdAcuan ? $targetKumulatif[$bulanAcuan - 1] : null;
        $deviasiRupiah = $rakSdAcuan !== null ? $realisasiSdAcuan - $rakSdAcuan : null;
        $deviasiPersen = $deviasiRupiah !== null
            ? MasterAnggaran::hitungPersentaseRealisasi($deviasiRupiah, $pagu)
            : null;

        return [
            'tahun' => $tahun,
            'bulan_acuan' => $bulanAcuan,
            'bulan_acuan_label' => self::BULAN[$bulanAcuan - 1],
            'bulan' => self::BULAN,
            'jumlah_master' => $jumlahMaster,
            'pagu' => $pagu,
            'realisasi_aktual' => $realisasiAktual,
            'capaian_tahun' => MasterAnggaran::hitungPersentaseRealisasi($realisasiAktual, $pagu),
            'realisasi_sd_bulan' => $realisasiSdAcuan,
            'rak_sd_bulan' => $rakSdAcuan,
            'deviasi_rupiah' => $deviasiRupiah,
            'deviasi_persen' => $deviasiPersen,
            'realisasi_bulanan' => $realisasiBulanan,
            'realisasi_kumulatif' => $realisasiKumulatif,
            'target_bulanan' => $targetBulanan,
            'target_kumulatif' => $targetKumulatif,
            'rak_tersedia' => $rakTersedia,
            'rak_lengkap_sd_bulan' => $rakLengkapSdAcuan,
            'rak_lengkap_tahun' => $rakLengkapTahun,
            'kosong' => $jumlahMaster === 0 && $realisasiAktual === 0.0,
            'pesan_rak' => $this->pesanRak(
                $rakTersedia,
                $rakLengkapSdAcuan,
                $rakLengkapTahun,
                $tahun,
                self::BULAN[$bulanAcuan - 1],
            ),
        ];
    }

    /**
     * Snapshot Dashboard: KPI/komposisi dari ringkasan yang sama dengan
     * Rincian, serta target/deviasi dari RAK yang sama dengan Analisis.
     *
     * @param  array{sub_kegiatan?: string, kode_rekening?: string}  $filters
     */
    public function dashboard(
        array $filters,
        int $tahun,
        int $bulanAcuan,
        string $sort = 'nama',
        string $direction = 'asc',
    ): array {
        $filters = [
            'sub_kegiatan' => (string) ($filters['sub_kegiatan'] ?? ''),
            'kode_rekening' => (string) ($filters['kode_rekening'] ?? ''),
        ];
        $bulanAcuan = max(1, min(12, $bulanAcuan));
        $rincian = $this->rincian($filters + ['tagging' => '', 'q' => '']);
        $analisis = $this->analisis($filters, $tahun, $bulanAcuan);

        $masters = $this->masterQuery($filters)
            ->get(['id', 'sub_kegiatan_kunci', 'kode_rekening']);
        $subByMaster = $masters->pluck('sub_kegiatan_kunci', 'id');
        $realisasiSdBulan = $masters->pluck('sub_kegiatan_kunci')->unique()->mapWithKeys(fn (string $sub) => [$sub => 0.0]);

        Npd::query()
            ->whereIn('master_anggaran_id', $masters->pluck('id'))
            ->where('status', 'Selesai')
            ->whereYear('tanggal_npd', $tahun)
            ->whereMonth('tanggal_npd', '<=', $bulanAcuan)
            ->get(['master_anggaran_id', 'nominal'])
            ->each(function (Npd $npd) use ($subByMaster, $realisasiSdBulan) {
                $sub = $subByMaster->get($npd->master_anggaran_id);
                if ($sub !== null) {
                    $realisasiSdBulan->put($sub, (float) $realisasiSdBulan->get($sub, 0.0) + (float) $npd->nominal);
                }
            });

        Spm::query()
            ->whereIn('master_anggaran_id', $masters->pluck('id'))
            ->where('jenis_spm', 'ls')
            ->whereYear('tanggal_dokumen', $tahun)
            ->whereMonth('tanggal_dokumen', '<=', $bulanAcuan)
            ->get(['master_anggaran_id', 'nominal'])
            ->each(function (Spm $spm) use ($subByMaster, $realisasiSdBulan) {
                $sub = $subByMaster->get($spm->master_anggaran_id);
                if ($sub !== null) {
                    $realisasiSdBulan->put($sub, (float) $realisasiSdBulan->get($sub, 0.0) + (float) $spm->nominal);
                }
            });

        $pasanganPerSub = $masters
            ->unique(fn (MasterAnggaran $master) => $master->sub_kegiatan_kunci.'|'.$master->kode_rekening)
            ->groupBy('sub_kegiatan_kunci');
        $rakPerSub = $this->rakQuery($filters, $tahun)
            ->where('bulan', '<=', $bulanAcuan)
            ->get(['sub_kegiatan_kunci', 'bulan', 'target'])
            ->groupBy('sub_kegiatan_kunci');

        $rows = $rincian['tree']->map(function (array $sub) use (
            $bulanAcuan,
            $pasanganPerSub,
            $rakPerSub,
            $realisasiSdBulan,
        ) {
            $jumlahPasangan = $pasanganPerSub->get($sub['kunci'], collect())->count();
            $rakRows = $rakPerSub->get($sub['kunci'], collect());
            $rakLengkap = $jumlahPasangan > 0 && $rakRows->count() === $jumlahPasangan * $bulanAcuan;
            $targetRak = $rakLengkap ? (float) $rakRows->sum('target') : null;
            $realisasiBerjalan = (float) $realisasiSdBulan->get($sub['kunci'], 0.0);
            $deviasi = $targetRak !== null ? $realisasiBerjalan - $targetRak : null;

            return $sub + [
                'target_rak' => $targetRak,
                'realisasi_sd_bulan' => $realisasiBerjalan,
                'deviasi_rupiah' => $deviasi,
                'deviasi_persen' => $deviasi !== null
                    ? MasterAnggaran::hitungPersentaseRealisasi($deviasi, $sub['angka']['pagu'])
                    : null,
            ];
        });

        $allowedSorts = [
            'nama', 'pagu', 'dana_terikat_npd', 'realisasi_aktual',
            'sisa_tersedia', 'persentase_realisasi', 'target_rak', 'deviasi_rupiah',
        ];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'nama';
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $rows = $rows->sort(function (array $a, array $b) use ($sort, $direction) {
            $aValue = $sort === 'nama' ? $a['nama'] : ($a[$sort] ?? $a['angka'][$sort] ?? null);
            $bValue = $sort === 'nama' ? $b['nama'] : ($b[$sort] ?? $b['angka'][$sort] ?? null);

            if ($aValue === null || $bValue === null) {
                return $aValue === $bValue ? 0 : ($aValue === null ? 1 : -1);
            }
            $comparison = is_string($aValue)
                ? strnatcasecmp($aValue, $bValue)
                : ($aValue <=> $bValue);

            return $direction === 'desc' ? -$comparison : $comparison;
        })->values();

        return [
            'tahun' => $tahun,
            'bulan_acuan' => $bulanAcuan,
            'bulan_acuan_label' => self::BULAN[$bulanAcuan - 1],
            'total' => $rincian['total'],
            'dana_terikat_belum_selesai' => $rincian['total']['dana_terikat_belum_selesai'],
            'realisasi_sd_bulan' => $analisis['realisasi_sd_bulan'],
            'target_rak_sd_bulan' => $analisis['rak_sd_bulan'],
            'persentase_target_rak' => $analisis['rak_sd_bulan'] !== null && $analisis['rak_sd_bulan'] > 0
                ? ($analisis['realisasi_sd_bulan'] / $analisis['rak_sd_bulan']) * 100
                : null,
            'rak_tersedia' => $analisis['rak_tersedia'],
            'pesan_rak' => $analisis['pesan_rak'],
            'kosong' => $rows->isEmpty(),
            'rows' => $rows,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    private function masterQuery(array $filters): Builder
    {
        $query = MasterAnggaran::query()->where('aktif', true);
        $this->terapkanFilterMaster($query, $filters);

        if (($filters['tagging'] ?? '') !== '') {
            ($filters['tagging'] === 'tanpa')
                ? $query->whereNull('tagging_id')
                : $query->where('tagging_id', $filters['tagging']);
        }

        if (($filters['q'] ?? '') !== '') {
            $cari = '%'.$filters['q'].'%';
            $query->where(function (Builder $query) use ($cari) {
                $query->where('program', 'like', $cari)
                    ->orWhere('kegiatan', 'like', $cari)
                    ->orWhere('sub_kegiatan', 'like', $cari)
                    ->orWhere('kode_rekening', 'like', $cari)
                    ->orWhere('uraian_rekening', 'like', $cari)
                    ->orWhereHas('tagging', fn (Builder $query) => $query->where('nama', 'like', $cari));
            });
        }

        return $query;
    }

    private function terapkanFilterMaster(Builder $query, array $filters): Builder
    {
        return $query
            ->when(($filters['sub_kegiatan'] ?? '') !== '', fn (Builder $query) => $query
                ->where('sub_kegiatan_kunci', $filters['sub_kegiatan']))
            ->when(($filters['kode_rekening'] ?? '') !== '', fn (Builder $query) => $query
                ->where('kode_rekening', $filters['kode_rekening']));
    }

    private function rakQuery(array $filters, int $tahun): Builder
    {
        return RakBulanan::query()
            ->where('tahun', $tahun)
            ->whereExists(function ($query) use ($filters) {
                $query->selectRaw('1')
                    ->from('master_anggaran')
                    ->whereColumn('master_anggaran.sub_kegiatan_kunci', 'rak_bulanan.sub_kegiatan_kunci')
                    ->whereColumn('master_anggaran.kode_rekening', 'rak_bulanan.kode_rekening')
                    ->where('master_anggaran.aktif', true)
                    ->when(($filters['sub_kegiatan'] ?? '') !== '', fn ($query) => $query
                        ->where('master_anggaran.sub_kegiatan_kunci', $filters['sub_kegiatan']))
                    ->when(($filters['kode_rekening'] ?? '') !== '', fn ($query) => $query
                        ->where('master_anggaran.kode_rekening', $filters['kode_rekening']));
            });
    }

    private function agregasiRingkasan(Collection $items): array
    {
        $pagu = (float) $items->sum('pagu');
        $realisasi = (float) $items->sum('realisasi_aktual');

        return [
            'pagu' => $pagu,
            'dana_terikat_npd' => (float) $items->sum('dana_terikat_npd'),
            'dana_terikat_belum_selesai' => (float) $items->sum('dana_terikat_belum_selesai'),
            'realisasi_npd' => (float) $items->sum('realisasi_npd'),
            'realisasi_ls' => (float) $items->sum('realisasi_ls'),
            'realisasi_aktual' => $realisasi,
            'sisa_tersedia' => (float) $items->sum('sisa_tersedia'),
            'persentase_realisasi' => MasterAnggaran::hitungPersentaseRealisasi($realisasi, $pagu),
        ];
    }

    private function kumulatif(array $values): array
    {
        $total = 0.0;

        return array_map(function ($value) use (&$total) {
            $total += (float) $value;

            return $total;
        }, $values);
    }

    private function kumulatifNullable(array $values): array
    {
        $total = 0.0;
        $lengkap = true;

        return array_map(function ($value) use (&$total, &$lengkap) {
            if ($value === null) {
                $lengkap = false;

                return null;
            }
            $total += (float) $value;

            return $lengkap ? $total : null;
        }, $values);
    }

    private function pesanRak(bool $tersedia, bool $lengkapSdAcuan, bool $lengkapTahun, int $tahun, string $bulan): ?string
    {
        if (! $tersedia) {
            return "RAK resmi Tahun Anggaran {$tahun} belum tersedia. Realisasi ditampilkan tanpa garis target; aplikasi tidak menggunakan perkiraan pagu/12.";
        }
        if (! $lengkapSdAcuan) {
            return "RAK resmi sampai {$bulan} {$tahun} belum lengkap. Deviasi tidak dihitung dan target yang belum lengkap tidak diperkirakan.";
        }
        if (! $lengkapTahun) {
            return "RAK resmi setelah {$bulan} {$tahun} belum lengkap. Deviasi sampai bulan berjalan tetap memakai RAK resmi yang sudah lengkap.";
        }

        return null;
    }
}
