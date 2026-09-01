<?php

namespace App\Services;

use App\Models\MasterAnggaran;
use App\Models\Npd;
use App\Models\PengembalianDetail;
use App\Models\RakBulanan;
use App\Models\Spm;
use App\Models\SpmDetail;
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
            // kode_sub_kegiatan & rekening ikut dimuat karena label dropdown
            // memakai bentuk gabungan "{kode} {nama}" (subKegiatanNormal(),
            // rekening_lengkap) - tanpa keduanya labelnya kehilangan kode.
            ->get(['id', 'kode_sub_kegiatan', 'sub_kegiatan', 'sub_kegiatan_kunci', 'kode_rekening', 'rekening', 'kode_rekening_bersih', 'tagging_id']);

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
            'kode_rekening' => $kodeScope->pluck('kode_rekening_bersih')->unique()->sort()->values(),
            /**
             * Sama seperti 'kode_rekening' di atas (nilainya kode_rekening_bersih,
             * tetap pendek untuk filter), tapi berlabel kode+uraian gabungan
             * untuk dropdown yang perlu menampilkan uraian rekening (Dashboard
             * Realisasi Anggaran). Kunci TAMBAHAN, sengaja tidak menggantikan
             * 'kode_rekening' supaya Rincian Realisasi & Analisis Tren yang
             * sudah memakai bentuk lama (daftar string polos) tidak ikut berubah.
             */
            'kode_rekening_berlabel' => $kodeScope
                ->groupBy('kode_rekening_bersih')
                ->map(fn (Collection $items, string $kode) => ['value' => $kode, 'label' => $items->first()->rekening_lengkap])
                ->sortBy('value', SORT_NATURAL)
                ->values(),
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
            ->withSum('spmDetail as realisasi_ls_total', 'nominal')
            ->withSum([
                'pengembalianDetail as pengembalian_disetujui_npd_total' => fn (Builder $query) => $query
                    ->whereHas('pengembalian', fn (Builder $q) => $q->where('status', 'disetujui')->where('dokumen_tipe', 'npd')),
            ], 'nominal')
            ->withSum([
                'pengembalianDetail as pengembalian_disetujui_ls_total' => fn (Builder $query) => $query
                    ->whereHas('pengembalian', fn (Builder $q) => $q->where('status', 'disetujui')->where('dokumen_tipe', 'spm_ls')),
            ], 'nominal');

        $masters = $query
            ->orderBy('sub_kegiatan_normal')
            ->orderBy('kode_rekening_bersih')
            ->orderBy('tagging_id')
            ->get();

        // Susunan sama dengan GAS (lihat pivot di CodeDashboard.gs):
        // Program > Sub Kegiatan > Kode Rekening > Tagging. Level Kegiatan
        // sengaja DILEWATI supaya ringkas - namanya tetap dibawa sebagai
        // keterangan pada baris Sub Kegiatan.
        $tree = $masters
            ->groupBy('program_kunci')
            ->map(function (Collection $programItems) {
                $sub = $programItems
                    ->groupBy('sub_kegiatan_kunci')
                    ->map(function (Collection $subItems) {
                        $rekening = $subItems->groupBy('kode_rekening_bersih')
                            ->map(function (Collection $rekeningItems, string $kode) {
                                $tagging = $rekeningItems->map(fn (MasterAnggaran $master) => [
                                    'id' => $master->id,
                                    'nama' => $master->tagging?->nama ?? 'Tanpa Tagging',
                                    'angka' => $master->ringkasanRealisasi(),
                                ]);

                                return [
                                    'kode' => $kode,
                                    'uraian' => $rekeningItems->first()->uraian_rekening,
                                    'tagging' => $this->urutNama($tagging),
                                    'angka' => $this->agregasiRingkasan($tagging->pluck('angka')),
                                ];
                            });

                        $rekening = $this->urutNama($rekening, 'kode');
                        $first = $subItems->first();

                        return [
                            'kunci' => $first->sub_kegiatan_kunci,
                            'nama' => $first->subKegiatanNormal(),
                            'program' => $first->programNormal(),
                            'kegiatan' => $first->kegiatanNormal(),
                            'rekening' => $rekening,
                            'angka' => $this->agregasiRingkasan($rekening->pluck('angka')),
                        ];
                    });

                $sub = $this->urutNama($sub);
                $first = $programItems->first();

                return [
                    'kunci' => $first->program_kunci,
                    'nama' => $first->programNormal(),
                    'sub' => $sub,
                    'angka' => $this->agregasiRingkasan($sub->pluck('angka')),
                ];
            });

        $tree = $this->urutNama($tree);

        return ['tree' => $tree, 'total' => $this->agregasiRingkasan($tree->pluck('angka'))];
    }

    /**
     * Realisasi anggaran pada RENTANG TANGGAL tertentu, dirinci sampai Tagging.
     *
     * Berbeda dari rincian() yang menjumlahkan SELURUH transaksi tanpa batas
     * waktu dan meringkas Program > Sub Kegiatan > Kode Rekening > Tagging, di
     * sini level Kegiatan ikut tampil sebagai level tersendiri dan seluruh
     * angka realisasinya dibatasi rentang tanggal.
     *
     * TANGGAL YANG DIPAKAI mengikuti kesepakatan yang sudah berlaku di
     * MasterAnggaran::sisaAnggaranSebelum():
     *
     *   - NPD          -> tanggal_npd
     *   - SPM LS       -> spm.tanggal_dokumen
     *   - Pengembalian -> tanggal_pengembalian
     *
     * Pengembalian ikut dibatasi rentang yang sama karena realisasi di
     * aplikasi ini selalu NETO: realisasiNpd()/realisasiLs() mengurangkan
     * pengembalian yang sudah disetujui. Kalau pengembalian tidak ikut
     * dibatasi, laporan satu bulan bisa dikurangi pengembalian dari bulan lain.
     *
     * Kolom pagu adalah pagu TAHUNAN mata anggaran, bukan angka periode -
     * pagu tidak punya dimensi waktu. Persentasenya karena itu berarti
     * "berapa persen pagu setahun yang terserap pada periode ini".
     *
     * @return array{tree: Collection<int, array<string, mixed>>, total: array<string, float>}
     */
    public function realisasiPeriode(string $dari, string $sampai): array
    {
        // whereDate, BUKAN whereBetween: kolom tanggalnya bertipe date di
        // MySQL tetapi tersimpan sebagai '2026-08-31 00:00:00' di SQLite,
        // sehingga perbandingan string biasa membuang tanggal batas atas.
        // whereDate membungkus kolomnya jadi tanggal di kedua driver.
        $masters = MasterAnggaran::query()
            ->where('aktif', true)
            ->with('tagging:id,nama')
            ->withSum([
                'npd as realisasi_npd_bruto' => fn (Builder $query) => $query
                    ->where('status', 'Selesai')
                    ->whereDate('tanggal_npd', '>=', $dari)
                    ->whereDate('tanggal_npd', '<=', $sampai),
            ], 'nominal')
            ->withSum([
                'spmDetail as realisasi_ls_bruto' => fn (Builder $query) => $query
                    ->whereHas('spm', fn (Builder $spm) => $spm
                        ->whereDate('tanggal_dokumen', '>=', $dari)
                        ->whereDate('tanggal_dokumen', '<=', $sampai)),
            ], 'nominal')
            ->withSum([
                'pengembalianDetail as pengembalian_npd' => fn (Builder $query) => $query
                    ->whereHas('pengembalian', fn (Builder $p) => $p
                        ->where('status', 'disetujui')
                        ->where('dokumen_tipe', 'npd')
                        ->whereDate('tanggal_pengembalian', '>=', $dari)
                        ->whereDate('tanggal_pengembalian', '<=', $sampai)),
            ], 'nominal')
            ->withSum([
                'pengembalianDetail as pengembalian_ls' => fn (Builder $query) => $query
                    ->whereHas('pengembalian', fn (Builder $p) => $p
                        ->where('status', 'disetujui')
                        ->where('dokumen_tipe', 'spm_ls')
                        ->whereDate('tanggal_pengembalian', '>=', $dari)
                        ->whereDate('tanggal_pengembalian', '<=', $sampai)),
            ], 'nominal')
            ->orderBy('sub_kegiatan_normal')
            ->orderBy('kode_rekening_bersih')
            ->orderBy('tagging_id')
            ->get();

        $tree = $masters
            ->groupBy('program_kunci')
            ->map(function (Collection $programItems) {
                $kegiatan = $programItems
                    ->groupBy('kegiatan_normal')
                    ->map(function (Collection $kegiatanItems) {
                        $sub = $kegiatanItems
                            ->groupBy('sub_kegiatan_kunci')
                            ->map(function (Collection $subItems) {
                                $rekening = $subItems
                                    ->groupBy('kode_rekening_bersih')
                                    ->map(function (Collection $rekeningItems, string $kode) {
                                        $tagging = $rekeningItems->map(fn (MasterAnggaran $master) => [
                                            'nama' => $master->tagging?->nama ?? 'Tanpa Tagging',
                                            'angka' => $this->angkaPeriode($master),
                                        ]);

                                        return [
                                            'nama' => $kode,
                                            'uraian' => $rekeningItems->first()->uraian_rekening,
                                            'tagging' => $this->urutNama($tagging),
                                            'angka' => $this->agregasiPeriode($tagging->pluck('angka')),
                                        ];
                                    });

                                $rekening = $this->urutNama($rekening);

                                return [
                                    'nama' => $subItems->first()->subKegiatanNormal(),
                                    'rekening' => $rekening,
                                    'angka' => $this->agregasiPeriode($rekening->pluck('angka')),
                                ];
                            });

                        $sub = $this->urutNama($sub);

                        return [
                            'nama' => $kegiatanItems->first()->kegiatanNormal(),
                            'sub' => $sub,
                            'angka' => $this->agregasiPeriode($sub->pluck('angka')),
                        ];
                    });

                $kegiatan = $this->urutNama($kegiatan);

                return [
                    'nama' => $programItems->first()->programNormal(),
                    'kegiatan' => $kegiatan,
                    'angka' => $this->agregasiPeriode($kegiatan->pluck('angka')),
                ];
            });

        $tree = $this->urutNama($tree);

        return ['tree' => $tree, 'total' => $this->agregasiPeriode($tree->pluck('angka'))];
    }

    /** Angka satu mata anggaran pada rentang tanggal - selalu NETO pengembalian. */
    private function angkaPeriode(MasterAnggaran $master): array
    {
        $npd = (float) ($master->realisasi_npd_bruto ?? 0) - (float) ($master->pengembalian_npd ?? 0);
        $ls = (float) ($master->realisasi_ls_bruto ?? 0) - (float) ($master->pengembalian_ls ?? 0);
        $pagu = $master->nilaiPagu();

        return [
            'pagu' => $pagu,
            'realisasi_npd' => $npd,
            'realisasi_ls' => $ls,
            'realisasi_aktual' => $npd + $ls,
            'persentase_realisasi' => MasterAnggaran::hitungPersentaseRealisasi($npd + $ls, $pagu),
        ];
    }

    /** @param  Collection<int, array<string, float>>  $items */
    private function agregasiPeriode(Collection $items): array
    {
        $pagu = (float) $items->sum('pagu');
        $realisasi = (float) $items->sum('realisasi_aktual');

        return [
            'pagu' => $pagu,
            'realisasi_npd' => (float) $items->sum('realisasi_npd'),
            'realisasi_ls' => (float) $items->sum('realisasi_ls'),
            'realisasi_aktual' => $realisasi,
            'persentase_realisasi' => MasterAnggaran::hitungPersentaseRealisasi($realisasi, $pagu),
        ];
    }

    /**
     * Urut alami berdasarkan nama/kode - padanan urutKode di GAS
     * (localeCompare 'id' dengan numeric:true), sehingga "5.1.2" berada
     * sebelum "5.1.10" alih-alih sesudahnya.
     *
     * @param  Collection<int|string, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function urutNama(Collection $items, string $kunci = 'nama'): Collection
    {
        return $items
            ->sort(fn (array $a, array $b) => strnatcasecmp((string) $a[$kunci], (string) $b[$kunci]))
            ->values();
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

        SpmDetail::query()
            ->whereHas('spm', fn (Builder $query) => $query->whereYear('tanggal_dokumen', $tahun))
            ->whereHas('masterAnggaran', fn (Builder $query) => $this->terapkanFilterMaster($query, $filters))
            ->with('spm:id,tanggal_dokumen')
            ->get(['id', 'spm_id', 'master_anggaran_id', 'nominal'])
            ->each(function (SpmDetail $detail) use (&$realisasiBulanan) {
                $realisasiBulanan[$detail->spm->tanggal_dokumen->month - 1] += (float) $detail->nominal;
            });

        // Pengembalian disetujui mengurangi realisasi pada BULAN pengembalian
        // itu sendiri (tanggal_pengembalian), bukan menulis ulang bulan
        // dokumen sumbernya - konsisten dengan prinsip append-only.
        PengembalianDetail::query()
            ->whereHas('pengembalian', fn (Builder $query) => $query->where('status', 'disetujui')->whereYear('tanggal_pengembalian', $tahun))
            ->whereHas('masterAnggaran', fn (Builder $query) => $this->terapkanFilterMaster($query, $filters))
            ->with('pengembalian:id,tanggal_pengembalian')
            ->get(['id', 'pengembalian_id', 'master_anggaran_id', 'nominal'])
            ->each(function (PengembalianDetail $detail) use (&$realisasiBulanan) {
                $realisasiBulanan[$detail->pengembalian->tanggal_pengembalian->month - 1] -= (float) $detail->nominal;
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

        SpmDetail::query()
            ->whereIn('master_anggaran_id', $masters->pluck('id'))
            ->whereHas('spm', fn (Builder $query) => $query
                ->whereYear('tanggal_dokumen', $tahun)
                ->whereMonth('tanggal_dokumen', '<=', $bulanAcuan))
            ->get(['master_anggaran_id', 'nominal'])
            ->each(function (SpmDetail $detail) use ($subByMaster, $realisasiSdBulan) {
                $sub = $subByMaster->get($detail->master_anggaran_id);
                if ($sub !== null) {
                    $realisasiSdBulan->put($sub, (float) $realisasiSdBulan->get($sub, 0.0) + (float) $detail->nominal);
                }
            });

        PengembalianDetail::query()
            ->whereIn('master_anggaran_id', $masters->pluck('id'))
            ->whereHas('pengembalian', fn (Builder $query) => $query
                ->where('status', 'disetujui')
                ->whereYear('tanggal_pengembalian', $tahun)
                ->whereMonth('tanggal_pengembalian', '<=', $bulanAcuan))
            ->get(['master_anggaran_id', 'nominal'])
            ->each(function (PengembalianDetail $detail) use ($subByMaster, $realisasiSdBulan) {
                $sub = $subByMaster->get($detail->master_anggaran_id);
                if ($sub !== null) {
                    $realisasiSdBulan->put($sub, (float) $realisasiSdBulan->get($sub, 0.0) - (float) $detail->nominal);
                }
            });

        $pasanganPerSub = $masters
            ->unique(fn (MasterAnggaran $master) => $master->sub_kegiatan_kunci.'|'.$master->kode_rekening)
            ->groupBy('sub_kegiatan_kunci');
        $rakPerSub = $this->rakQuery($filters, $tahun)
            ->where('bulan', '<=', $bulanAcuan)
            ->get(['sub_kegiatan_kunci', 'bulan', 'target'])
            ->groupBy('sub_kegiatan_kunci');

        // rincian() kini bertingkat Program dulu (mengikuti pivot GAS);
        // Analisis & Tren bekerja per Sub Kegiatan, jadi diratakan dulu.
        $rows = $rincian['tree']
            ->flatMap(fn (array $program) => $program['sub'])
            ->map(function (array $sub) use (
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

        // Realisasi SP2D (kartu Dashboard): SPM LS pada scope filter + total
        // nasional SPM UP/GU/TU. UP/GU/TU TIDAK memiliki keterkaitan ke mata
        // anggaran sama sekali (spm.master_anggaran_id sudah dihapus saat
        // restrukturisasi SPM LS jadi header+detail, dan UP/GU tidak pernah
        // membuat baris spm_detail - lihat Spm::buatUpGu()), jadi nilainya
        // SELALU total nasional penuh, tidak menyempit walau filter Sub
        // Kegiatan/Kode Rekening aktif - lihat 'filter_aktif' untuk
        // menampilkan catatan bahwa persentase saat filter aktif hanya
        // indikatif (keputusan produk, bukan kesalahan hitung).
        $filterAktif = $filters['sub_kegiatan'] !== '' || $filters['kode_rekening'] !== '';
        $totalSpmUpGu = $this->totalSpmUpGu();
        $realisasiSp2dNominal = $rincian['total']['realisasi_ls'] + $totalSpmUpGu;

        // Sisa Anggaran (kartu Dashboard) = Pagu - Realisasi SPJ3. Sengaja
        // BUKAN 'sisa_tersedia' (dipakai validasi nominal NPD & Rincian
        // Realisasi, yang mengurangi dana_terikat_npd - termasuk draft/proses
        // - supaya pagu tidak overcommit oleh dokumen yang belum final).
        // Kartu ini hanya melihat realisasi yang benar-benar sudah final
        // (NPD Selesai + SPM LS), jadi angkanya bisa lebih besar dari
        // sisa_tersedia - lihat catatan penyerahan/AUDIT untuk konteksnya.
        $sisaAnggaranSpj3 = $rincian['total']['pagu'] - $rincian['total']['realisasi_aktual'];

        return [
            'tahun' => $tahun,
            'bulan_acuan' => $bulanAcuan,
            'bulan_acuan_label' => self::BULAN[$bulanAcuan - 1],
            'total' => $rincian['total'],
            'dana_terikat_belum_selesai' => $rincian['total']['dana_terikat_belum_selesai'],
            'filter_aktif' => $filterAktif,
            'spm_up_gu_total' => $totalSpmUpGu,
            'realisasi_sp2d' => [
                'nominal' => $realisasiSp2dNominal,
                'persentase' => MasterAnggaran::hitungPersentaseRealisasi($realisasiSp2dNominal, $rincian['total']['pagu']),
            ],
            'sisa_anggaran_spj3' => [
                'nominal' => $sisaAnggaranSpj3,
                'persentase' => MasterAnggaran::hitungPersentaseRealisasi($sisaAnggaranSpj3, $rincian['total']['pagu']),
            ],
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

    /**
     * Total nominal SPM UP/GU/TU (replenishment kas BPP untuk isi ulang
     * panjar - BUKAN realisasi per mata anggaran, lihat Spm::buatUpGu()).
     * SPM jenis ini tidak pernah tertaut ke master_anggaran sama sekali,
     * sehingga selalu berupa total nasional dan tidak bisa disempitkan
     * mengikuti filter Sub Kegiatan/Kode Rekening.
     */
    public function totalSpmUpGu(): float
    {
        return (float) Spm::where('jenis_spm', 'up_gu')->sum('nominal');
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
                // Kolom kode dan nama dicari terpisah sejak keduanya punya
                // kolom sendiri - tanpa ini, mencari "Belanja Kertas" atau
                // "6.01.01" hanya kena separuh datanya.
                $query->where('program', 'like', $cari)
                    ->orWhere('kode_program', 'like', $cari)
                    ->orWhere('kegiatan', 'like', $cari)
                    ->orWhere('kode_kegiatan', 'like', $cari)
                    ->orWhere('sub_kegiatan', 'like', $cari)
                    ->orWhere('kode_sub_kegiatan', 'like', $cari)
                    ->orWhere('kode_rekening', 'like', $cari)
                    ->orWhere('rekening', 'like', $cari)
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
                ->where('kode_rekening_bersih', $filters['kode_rekening']));
    }

    private function rakQuery(array $filters, int $tahun): Builder
    {
        return RakBulanan::query()
            ->where('tahun', $tahun)
            ->whereExists(function ($query) use ($filters) {
                $query->selectRaw('1')
                    ->from('master_anggaran')
                    ->whereColumn('master_anggaran.sub_kegiatan_kunci', 'rak_bulanan.sub_kegiatan_kunci')
                    ->whereColumn('master_anggaran.kode_rekening_bersih', 'rak_bulanan.kode_rekening')
                    ->where('master_anggaran.aktif', true)
                    ->when(($filters['sub_kegiatan'] ?? '') !== '', fn ($query) => $query
                        ->where('master_anggaran.sub_kegiatan_kunci', $filters['sub_kegiatan']))
                    ->when(($filters['kode_rekening'] ?? '') !== '', fn ($query) => $query
                        ->where('master_anggaran.kode_rekening_bersih', $filters['kode_rekening']));
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
