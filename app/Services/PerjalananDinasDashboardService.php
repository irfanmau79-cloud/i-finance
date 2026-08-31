<?php

namespace App\Services;

use App\Models\Npd;
use App\Models\NpdTim;
use App\Support\BidangOrganisasi;
use Illuminate\Support\Collection;

/**
 * Dashboard Perjalanan Dinas — port dari CodeDashboardPD.gs.
 *
 * BEDA SUMBER DATA, SAMA PERHITUNGANNYA. Di GAS datanya sheet "Monitoring
 * SPPD" yang diisi manual: satu baris per pegawai, 12 bulan x 5 komponen
 * (Jumlah Hari Penugasan, Uang Harian, Akomodasi, Transportasi, Jumlah
 * Diterima). Di sini baris itu DITURUNKAN dari anggota tim NPD Perjalanan
 * Dinas/Transport yang sudah Selesai, lalu diakumulasi per orang per bulan
 * sehingga bentuknya identik dengan sheet tersebut.
 *
 * IDENTITAS ORANG = NIP (dibersihkan dari karakter non-digit), mengikuti
 * getUangHarianPD() di GAS yang juga mencocokkan pegawai lewat NIP. Satu
 * orang yang namanya ditulis sedikit berbeda antar NPD, atau yang kadang
 * terpilih dari master dan kadang diketik manual, tetap menjadi SATU baris
 * selama NIP-nya sama. NIP kosong terpaksa jatuh ke nama sebagai kunci.
 *
 * Dua hal yang sengaja mengikuti GAS dan mudah terlewat:
 * 1. Tabel Rekap per Bidang TIDAK terpengaruh filter - getPDRekap() memang
 *    tidak menerima parameter filter. Yang mengikuti filter hanya grafik tren.
 * 2. Pegawai yang bidangnya tidak dikenali tidak dibuang, melainkan
 *    dikelompokkan ke "(Tanpa Bidang)" dan ditaruh setelah bidang resmi.
 */
class PerjalananDinasDashboardService
{
    /** Metrik grafik, urut sama dengan tombol segmented di GAS. */
    public const METRIK = [
        'terima' => 'Jumlah Diterima',
        'uh' => 'Uang Harian',
        'akom' => 'Akomodasi',
        'trans' => 'Transportasi',
        'hari' => 'Jumlah Hari',
    ];

    /** Komponen yang dijumlahkan per bulan (PD_DATA0 dst. pada sheet GAS). */
    private const KOMPONEN = ['hari', 'uh', 'akom', 'trans', 'terima'];

    public const TANPA_BIDANG = '(Tanpa Bidang)';

    /** @return array<string, mixed> */
    public function data(array $filters, int $tahun): array
    {
        $orang = $this->bacaData($tahun);
        $metrik = array_key_exists($filters['metrik'] ?? '', self::METRIK) ? $filters['metrik'] : 'terima';

        return [
            'tahun' => $tahun,
            'metrik' => $metrik,
            'metrik_label' => self::METRIK[$metrik],
            // Rekap SENGAJA memakai data penuh, tidak ikut filter (getPDRekap).
            'rekap' => $this->rekap($orang),
            'tren' => $this->tren($orang, $filters, $metrik),
            'pilihan' => $this->pilihan($orang),
        ];
    }

    /**
     * Uang Harian Perjalanan Dinas seorang pegawai, per bulan, untuk dipakai
     * bagian "V. PENGHASILAN LAINNYA" pada Surat Keterangan Penghasilan.
     *
     * Port getUangHarianPD() di CodeDashboardPD.gs. Di GAS angkanya dibaca
     * dari kolom Uang Harian sheet "Monitoring SPPD"; di sini diturunkan dari
     * sumber yang sama dengan dashboard ini, jadi angkanya selalu sinkron
     * dengan yang dilihat pengguna di Dashboard Perjalanan Dinas.
     *
     * Pencocokan lewat NIP yang dibersihkan dari karakter non-digit supaya
     * tahan terhadap spasi dan tanda baca. Bulan tanpa data bernilai 0 -
     * bukan kesalahan.
     *
     * @param  array<int, int>  $bulan
     * @return array<int, float> bulan => nominal uang harian
     */
    public function uangHarian(?string $nip, int $tahun, array $bulan): array
    {
        $kunci = preg_replace('/\D/', '', (string) $nip) ?? '';
        $hasil = array_fill_keys($bulan, 0.0);

        if ($kunci === '') {
            return $hasil;
        }

        $orang = $this->bacaData($tahun)->firstWhere('kunci', 'nip:'.$kunci);

        if ($orang === null) {
            return $hasil;
        }

        foreach ($bulan as $nomor) {
            $hasil[$nomor] = (float) ($orang['bulan'][$nomor]['uh'] ?? 0);
        }

        return $hasil;
    }

    /**
     * Satu baris per orang berisi 12 bulan komponen — bentuk yang sama dengan
     * hasil _pdBacaData() di GAS.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function bacaData(int $tahun): Collection
    {
        $baris = Npd::query()
            ->with(['tim.paket', 'tim.pegawai'])
            ->whereIn('jenis', ['pd', 'tr'])
            ->where('status', 'Selesai')
            ->whereYear('tanggal_npd', $tahun)
            ->orderBy('tanggal_npd')
            ->orderBy('id')
            ->get()
            ->flatMap(fn (Npd $npd) => $npd->tim->map(fn (NpdTim $tim) => self::baris($npd, $tim)));

        return $baris
            ->groupBy('kunci')
            ->map(function (Collection $rows) {
                // Identitas diambil dari NPD TERBARU orang ini: nama/jabatan/
                // bidang bisa berubah sepanjang tahun, yang terbaru paling
                // mewakili keadaan sekarang.
                $terbaru = $rows->last();

                $bulan = [];

                foreach (range(1, 12) as $nomor) {
                    $diBulan = $rows->where('bulan', $nomor);
                    $bulan[$nomor] = collect(self::KOMPONEN)
                        ->mapWithKeys(fn (string $k) => [$k => (float) $diBulan->sum($k)])
                        ->all();
                }

                return [
                    'kunci' => $terbaru['kunci'],
                    'nama' => $terbaru['nama'],
                    'nip' => $terbaru['nip'],
                    'jabatan' => $terbaru['jabatan'],
                    'bidang' => $terbaru['bidang'],
                    'pegawai_id' => $rows->pluck('pegawai_id')->filter()->last(),
                    'bulan' => $bulan,
                    'total' => collect(self::KOMPONEN)
                        ->mapWithKeys(fn (string $k) => [$k => (float) $rows->sum($k)])
                        ->all(),
                ];
            })
            ->values();
    }

    /**
     * Satu anggota tim pada satu NPD. Public + static: dipakai ulang oleh
     * App\Exports\PerjalananDinasExport supaya rumusnya tetap satu sumber.
     *
     * @return array<string, mixed>
     */
    public static function baris(Npd $npd, NpdTim $tim): array
    {
        $hasil = $tim->hitung();

        return [
            'npd_id' => $npd->id,
            'bulan' => (int) $npd->tanggal_npd->month,
            'kunci' => self::kunciOrang($tim),
            'pegawai_id' => $tim->pegawai_id,
            'nama' => (string) $tim->nama,
            'nip' => (string) $tim->nip,
            'jabatan' => (string) $tim->jabatan,
            'bidang' => BidangOrganisasi::petakan($tim->bidang_snapshot ?: $tim->pegawai?->bidang) ?? self::TANPA_BIDANG,
            'hari' => (float) $tim->paket->sum('lama_hari'),
            'uh' => (float) $hasil['jml_harian'],
            'akom' => (float) $hasil['jml_akom'],
            // Transportasi GAS = BBM + Tol + Tiket (lihat _hitungAnggota).
            'trans' => (float) $hasil['jml_transport'],
            // Jumlah Diterima sudah termasuk uang representatif; nilainya
            // tetap disediakan terpisah karena export Perjalanan Dinas
            // menampilkannya sebagai kolom tersendiri.
            'representatif' => (float) $hasil['representatif'],
            'terima' => (float) $hasil['jumlah'],
        ];
    }

    /**
     * Kunci identitas orang: NIP tanpa karakter non-digit. NIP kosong jatuh
     * ke nama yang dinormalisasi supaya orang tanpa NIP tetap terkelompok,
     * bukan berserakan satu baris per NPD.
     */
    public static function kunciOrang(NpdTim $tim): string
    {
        $nip = preg_replace('/\D/', '', (string) $tim->nip) ?? '';

        if ($nip !== '') {
            return 'nip:'.$nip;
        }

        return 'nama:'.mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $tim->nama) ?? ''));
    }

    /**
     * Rekap per bidang untuk setahun penuh, urut sesuai daftar resmi lalu
     * bidang lain di belakangnya, ditutup baris TOTAL. Port getPDRekap().
     *
     * @param  Collection<int, array<string, mixed>>  $orang
     * @return array<string, mixed>
     */
    private function rekap(Collection $orang): array
    {
        $perBidang = $orang->groupBy('bidang');

        $urut = collect(BidangOrganisasi::PERJALANAN)
            ->filter(fn (string $b) => $perBidang->has($b))
            ->concat($perBidang->keys()->reject(fn (string $b) => in_array($b, BidangOrganisasi::PERJALANAN, true)))
            ->values();

        $rows = $urut->map(function (string $bidang) use ($perBidang) {
            $anggota = $perBidang->get($bidang)->sortBy('nama')->values();

            return $this->jumlahkan($anggota->pluck('total')) + [
                'bidang' => $bidang,
                'pegawai' => $anggota->count(),
                'anggota' => $anggota->map(fn (array $p) => $p['total'] + [
                    'nama' => $p['nama'],
                    'jabatan' => $p['jabatan'],
                    'nip' => $p['nip'],
                    'pegawai_id' => $p['pegawai_id'],
                ])->all(),
            ];
        })->values();

        return [
            'rows' => $rows->all(),
            'total' => $this->jumlahkan($rows) + [
                'bidang' => 'TOTAL',
                'pegawai' => (int) $rows->sum('pegawai'),
            ],
        ];
    }

    /**
     * Deret bulanan sesuai filter. Port getPDTren().
     *
     * @param  Collection<int, array<string, mixed>>  $orang
     * @return array<string, mixed>
     */
    private function tren(Collection $orang, array $filters, string $metrik): array
    {
        $bidang = trim((string) ($filters['bidang'] ?? ''));
        $pegawai = trim((string) ($filters['pegawai'] ?? ''));

        $terpilih = $orang
            ->when($bidang !== '', fn (Collection $rows) => $rows->where('bidang', $bidang))
            ->when($pegawai !== '', fn (Collection $rows) => $rows->where('kunci', $pegawai))
            ->values();

        $bulan = collect(range(1, 12))->map(fn (int $nomor) => [
            'nomor' => $nomor,
            'label' => now()->startOfYear()->setMonth($nomor)->locale('id')->translatedFormat('F'),
            'nilai' => (float) $terpilih->sum(fn (array $p) => $p['bulan'][$nomor][$metrik]),
        ])->values();

        $cakupan = $pegawai !== ''
            ? ($terpilih->first()['nama'] ?? 'Pegawai')
            : ($bidang !== '' ? $bidang : 'Semua Bidang');

        return [
            'bulan' => $bulan->all(),
            'total' => $this->jumlahkan($terpilih->pluck('total')),
            'jumlah_pegawai' => $terpilih->count(),
            'cakupan' => $cakupan,
            // GAS menyembunyikan grafik saat seluruh deret bernilai nol.
            'kosong' => $bulan->sum('nilai') <= 0,
        ];
    }

    /**
     * Isi dropdown: daftar bidang yang benar-benar ada datanya, dan pasangan
     * (bidang -> pegawai) supaya pilihan nama menyesuaikan bidang terpilih.
     *
     * @param  Collection<int, array<string, mixed>>  $orang
     * @return array<string, mixed>
     */
    private function pilihan(Collection $orang): array
    {
        $adaBidang = $orang->pluck('bidang')->unique();

        $bidang = collect(BidangOrganisasi::PERJALANAN)
            ->filter(fn (string $b) => $adaBidang->contains($b))
            ->concat($adaBidang->reject(fn (string $b) => in_array($b, BidangOrganisasi::PERJALANAN, true)))
            ->values()
            ->all();

        $pegawai = $orang->sortBy('nama')->map(fn (array $p) => [
            'value' => $p['kunci'],
            'label' => $p['nama'],
            'bidang' => $p['bidang'],
        ])->values()->all();

        return ['bidang' => $bidang, 'pegawai' => $pegawai];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, float>
     */
    private function jumlahkan(Collection $rows): array
    {
        return collect(self::KOMPONEN)
            ->mapWithKeys(fn (string $k) => [$k => (float) $rows->sum($k)])
            ->all();
    }
}
