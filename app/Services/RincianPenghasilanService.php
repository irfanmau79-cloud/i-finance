<?php

namespace App\Services;

use App\Models\GajiInduk;
use App\Models\RincianPenghasilan;
use App\Models\Tpp;
use App\Support\GajiTunjanganKolom;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Surat Keterangan Penghasilan - port menu 5 & 6 di CodeGajiTunjangan.gs
 * (gtNomorSuratBaru, _gtRincianSatuBulan, gtSimpanRincian, gtDaftarRincian,
 * gtHapusRincian).
 *
 * Satu dokumen bisa memuat beberapa periode: tiap bulan menjadi satu halaman
 * dalam satu PDF.
 */
class RincianPenghasilanService
{
    public function __construct(
        private readonly PerjalananDinasDashboardService $perjalananDinas,
    ) {}

    /**
     * Nomor berikutnya. URUT GLOBAL lintas bulan & tahun, tidak pernah reset
     * - lihat perubahan 17 di README_PERUBAHAN.txt GAS. Bagian mm/yyyy pada
     * string nomor adalah bulan & tahun PEMBUATAN dokumen, bukan periode
     * penghasilan yang dicetak (perubahan 16).
     *
     * @return array{urut: int, nomor: string}
     */
    public function nomorBerikutnya(int $bulanBuat, int $tahunBuat): array
    {
        $urut = ((int) RincianPenghasilan::query()->max('nomor_urut')) + 1;

        return ['urut' => $urut, 'nomor' => $this->formatNomor($urut, $bulanBuat, $tahunBuat)];
    }

    private function formatNomor(int $urut, int $bulan, int $tahun): string
    {
        return strtr(config('gaji_tunjangan.format_nomor'), [
            ':urut' => (string) $urut,
            ':bulan' => str_pad((string) $bulan, 2, '0', STR_PAD_LEFT),
            ':tahun' => (string) $tahun,
        ]);
    }

    /**
     * Komponen surat untuk SATU periode. Port _gtRincianSatuBulan().
     *
     * @return array{ada: bool, gaji: array<string, float>, kinerja: array<string, float>}
     */
    public function rincianSatuBulan(string $nip, int $bulan, int $tahun): array
    {
        $g = GajiInduk::query()->where('nip', $nip)->where('bulan', $bulan)->where('tahun', $tahun)->first();
        $beban = Tpp::query()->periode(Tpp::JENIS_BEBAN, $bulan, $tahun)->where('nip', $nip)->first();
        $kondisi = Tpp::query()->periode(Tpp::JENIS_KONDISI, $bulan, $tahun)->where('nip', $nip)->first();

        $gaji = [
            'pokok' => (float) ($g->belanja_gaji_pokok ?? 0),
            'suami_istri' => (float) ($g->perhitungan_suami_istri ?? 0),
            'anak' => (float) ($g->perhitungan_anak ?? 0),
            // Satu baris surat menggabungkan tunjangan struktural dan umum.
            'struktural_umum' => (float) ($g->belanja_tunjangan_jabatan ?? 0) + (float) ($g->belanja_tunjangan_fungsional_umum ?? 0),
            'beras' => (float) ($g->belanja_tunjangan_beras ?? 0),
            'pph' => (float) ($g->belanja_tunjangan_pph ?? 0),
            'pembulatan' => (float) ($g->belanja_pembulatan_gaji ?? 0),
            'bruto' => (float) ($g->jumlah_gaji_tunjangan ?? 0),
            // Simpanan wajib (TASPEN) = iuran 8%; Iuran BPJS/Askes = iuran 1%.
            'pot_wajib' => (float) ($g->tunjangan_jaminan_hari_tua ?? 0),
            'pot_bpjs' => (float) ($g->iwp_1_persen ?? 0),
            'pot_pph' => (float) ($g->pph_21 ?? 0),
            'pot_total' => (float) ($g->jumlah_potongan ?? 0),
            'netto' => (float) ($g->jumlah_ditransfer ?? 0),
        ];

        $nilaiBeban = (float) ($beban->jumlah_ditransfer ?? 0);
        $nilaiKondisi = (float) ($kondisi->jumlah_ditransfer ?? 0);
        // Koperasi Praja & Zakat hanya ada di TPP Beban Kerja.
        $koperasi = (float) ($beban->koperasi_praja ?? 0);
        $zakat = (float) ($beban->zakat_praja ?? 0);

        $kinerja = [
            'beban' => $nilaiBeban,
            'kondisi' => $nilaiKondisi,
            'bruto' => $nilaiBeban + $nilaiKondisi,
            'koperasi' => $koperasi,
            'zakat' => $zakat,
            'pot_total' => $koperasi + $zakat,
            'netto' => ($nilaiBeban + $nilaiKondisi) - ($koperasi + $zakat),
        ];

        return [
            'ada' => $g !== null || $beban !== null || $kondisi !== null,
            'gaji' => $gaji,
            'kinerja' => $kinerja,
        ];
    }

    /**
     * Uang Harian Perjalanan Dinas per bulan, ditarik otomatis dari data NPD
     * Perjalanan Dinas - read-only bagi pengguna (perubahan 38).
     *
     * @param  array<int, int>  $periode
     * @return array<int, float>
     */
    public function uangHarian(string $nip, int $tahun, array $periode): array
    {
        return $this->perjalananDinas->uangHarian($nip, $tahun, $periode);
    }

    /**
     * Catat dokumen baru. Nomor urut diambil di dalam transaksi dengan kunci
     * baris supaya dua permintaan yang berbarengan tidak memperoleh nomor
     * yang sama - pola penomoran anti race-condition yang sama dipakai NPD.
     *
     * @param  array{nip: string, nama: string, jabatan: ?string, tahun: int, periode: array<int, int>, ada_pd: bool, penandatangan: string}  $data
     */
    public function simpan(array $data, ?int $userId, ?string $namaPembuat): RincianPenghasilan
    {
        $periode = collect($data['periode'])
            ->map(fn ($b) => (int) $b)
            ->filter(fn (int $b) => $b >= 1 && $b <= 12)
            ->unique()->sort()->values()->all();

        if ($periode === []) {
            throw new RuntimeException('Pilih minimal satu periode penghasilan.');
        }

        $daftarTtd = config('gaji_tunjangan.penandatangan');
        $kunciTtd = $data['penandatangan'];

        if (! isset($daftarTtd[$kunciTtd])) {
            throw new RuntimeException('Penandatangan tidak dikenali.');
        }

        $ttd = $daftarTtd[$kunciTtd];

        $adaPd = (bool) $data['ada_pd'];
        $nominalPd = $adaPd ? $this->uangHarian($data['nip'], (int) $data['tahun'], $periode) : [];
        $totalPd = array_sum($nominalPd);

        return DB::transaction(function () use ($data, $periode, $kunciTtd, $ttd, $adaPd, $nominalPd, $totalPd, $userId, $namaPembuat) {
            $terakhir = RincianPenghasilan::query()->lockForUpdate()->max('nomor_urut');
            $urut = ((int) $terakhir) + 1;

            $sekarang = now();

            return RincianPenghasilan::create([
                'nomor_urut' => $urut,
                'nomor' => $this->formatNomor($urut, (int) $sekarang->month, (int) $sekarang->year),
                'nip' => $data['nip'],
                'nama' => $data['nama'],
                'jabatan' => $data['jabatan'],
                'tahun' => (int) $data['tahun'],
                'periode' => $periode,
                'ada_pd' => $adaPd,
                'nominal_pd' => $nominalPd,
                'total_pd' => $totalPd,
                'penandatangan_kunci' => $kunciTtd,
                'penandatangan_nama' => $ttd['nama'],
                'penandatangan_jabatan' => $ttd['jabatan'],
                'penandatangan_pangkat' => $ttd['pangkat'],
                'tanggal_dokumen' => $sekarang->toDateString(),
                'dibuat_oleh' => $userId,
                'dibuat_oleh_nama' => $namaPembuat,
            ]);
        });
    }

    /**
     * Susun seluruh halaman sebuah dokumen: satu entri per periode, siap
     * dilempar ke view PDF.
     *
     * @return array<int, array<string, mixed>>
     */
    public function halaman(RincianPenghasilan $dokumen): array
    {
        $halaman = [];

        foreach ($dokumen->periode as $bulan) {
            $rincian = $this->rincianSatuBulan($dokumen->nip, (int) $bulan, (int) $dokumen->tahun);
            $nominalPd = $dokumen->ada_pd ? (float) ($dokumen->nominal_pd[$bulan] ?? 0) : 0.0;

            $halaman[] = [
                'bulan' => (int) $bulan,
                'nama_bulan' => GajiTunjanganKolom::NAMA_BULAN[(int) $bulan] ?? '',
                'gaji' => $rincian['gaji'],
                'kinerja' => $rincian['kinerja'],
                'nominal_pd' => $nominalPd,
                // Bagian "V. PENGHASILAN LAINNYA" hanya muncul bila toggle PD
                // aktif DAN nominalnya lebih dari nol - sama seperti GAS.
                'tampil_pd' => $dokumen->ada_pd && $nominalPd > 0,
                'jumlah_seluruh' => $rincian['gaji']['netto'] + $rincian['kinerja']['netto'] + $nominalPd,
            ];
        }

        return $halaman;
    }

    /**
     * Hapus dokumen. Nomor berikutnya ikut mundur karena penomoran dihitung
     * dari nomor tertinggi yang masih ada - perilaku ini disengaja di GAS
     * supaya percobaan cetak tidak membuang nomor.
     */
    public function hapus(RincianPenghasilan $dokumen): void
    {
        $dokumen->delete();
    }
}
