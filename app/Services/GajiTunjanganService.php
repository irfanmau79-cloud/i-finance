<?php

namespace App\Services;

use App\Models\GajiInduk;
use App\Models\Tpp;
use App\Support\UrutanPegawaiGaji;

/**
 * Pembacaan data untuk keempat sub-menu tabel Data Gaji & Tunjangan: Gaji
 * Induk, TPP Beban Kerja, TPP Kondisi Kerja, dan Total Penghasilan.
 *
 * Port dari getDataGajiTunjangan / _gtGaji / _gtTPP / _gtTotal / _gtAkumulasi
 * di CodeGajiTunjangan.gs. Semua nominal dikembalikan sebagai angka mentah;
 * pemformatan ribuan dilakukan di view, sama seperti di GAS.
 *
 * Dua mode:
 *   'bulan' - satu bulan tertentu, baris apa adanya.
 *   'tahun' - kumulatif Januari-Desember, dijumlah per pegawai (kunci NIP).
 */
class GajiTunjanganService
{
    /**
     * Kolom numerik yang dijumlahkan pada mode kumulatif untuk Gaji Induk.
     *
     * @var array<int, string>
     */
    private const JUMLAH_GAJI = [
        'gaji_pokok', 'suami_istri', 'anak', 'bruto1',
        'tj_umum', 'tj_struktural', 'tj_fungsional', 'tj_beras', 'tj_pph',
        'pembulatan', 'bruto2',
        'pot_beras', 'pot_iwp8', 'pot_iwp1', 'pot_pph', 'rumah_tanah',
        'lain_lain', 'jml_potongan', 'jml_dibayarkan',
    ];

    /**
     * Kolom numerik yang dijumlahkan pada mode kumulatif untuk TPP.
     *
     * "Besaran TPP 100%" dan "Prosentase Kinerja" sengaja TIDAK ikut
     * dijumlah: keduanya nilai referensi yang diisi manual, bukan nominal
     * yang bertambah tiap bulan. Menjumlahkannya akan menghasilkan angka
     * seperti "790%".
     *
     * @var array<int, string>
     */
    private const JUMLAH_TPP = ['penilaian', 'tpp_bruto', 'netto'];

    /**
     * Ambil data satu sub-menu.
     *
     * @param  string  $jenis  gaji|beban|kondisi|total
     * @param  string  $mode  bulan|tahun
     * @param  string|null  $nipTerbatas  bila diisi, hanya baris NIP itu yang dikembalikan
     * @return array{rows: array<int, array<string, mixed>>, mode: string, terbatas: bool}
     */
    public function data(string $jenis, string $mode, ?int $bulan, int $tahun, ?string $nipTerbatas = null): array
    {
        $rows = match ($jenis) {
            'gaji' => $this->gaji($mode, $bulan, $tahun),
            'total' => $this->total($mode, $bulan, $tahun),
            default => $this->tpp($jenis, $mode, $bulan, $tahun),
        };

        // Penyaringan milik-sendiri dilakukan di sini, SEBELUM data
        // meninggalkan server. Baris pegawai lain tidak pernah dikirim ke
        // browser pengguna yang kena gate.
        if ($nipTerbatas !== null) {
            $kunci = self::normal($nipTerbatas);
            $rows = array_values(array_filter($rows, fn (array $r) => self::normal($r['nip']) === $kunci));
        }

        return ['rows' => $rows, 'mode' => $mode, 'terbatas' => $nipTerbatas !== null];
    }

    /**
     * Gaji Induk. Port _gtGaji().
     *
     * @return array<int, array<string, mixed>>
     */
    private function gaji(string $mode, ?int $bulan, int $tahun): array
    {
        $rows = GajiInduk::query()
            ->periode($mode === 'bulan' ? $bulan : null, $tahun)
            ->orderBy('bulan')
            ->get()
            ->map(function (GajiInduk $g) {
                $pokok = (float) $g->belanja_gaji_pokok;
                $suamiIstri = (float) $g->perhitungan_suami_istri;
                $anak = (float) $g->perhitungan_anak;

                return [
                    'nama' => (string) $g->nama_pegawai,
                    'nip' => (string) $g->nip,
                    'norek' => (string) $g->nomor_rekening_bank_pegawai,
                    'gol' => (string) $g->golongan,
                    'status' => (string) $g->pppk_pns,
                    'jabatan' => (string) $g->nama_jabatan,

                    // Blok Gaji Pokok
                    'gaji_pokok' => $pokok,
                    'suami_istri' => $suamiIstri,
                    'anak' => $anak,
                    'bruto1' => $pokok + $suamiIstri + $anak,
                    'tj_umum' => (float) $g->belanja_tunjangan_fungsional_umum,
                    'tj_struktural' => (float) $g->belanja_tunjangan_jabatan,
                    'tj_fungsional' => (float) $g->belanja_tunjangan_fungsional,
                    'tj_beras' => (float) $g->belanja_tunjangan_beras,
                    'tj_pph' => (float) $g->belanja_tunjangan_pph,
                    'pembulatan' => (float) $g->belanja_pembulatan_gaji,
                    'bruto2' => (float) $g->jumlah_gaji_tunjangan,

                    // Blok Potongan. Tiga kolom di bawah ini memang selalu 0 -
                    // kesepakatan kantor, ada di tabel supaya susunan kolomnya
                    // sama dengan cetakan SIPD.
                    'pot_beras' => 0.0,
                    'pot_iwp8' => (float) $g->tunjangan_jaminan_hari_tua,
                    'pot_iwp1' => (float) $g->iwp_1_persen,
                    'pot_pph' => (float) $g->pph_21,
                    'rumah_tanah' => 0.0,
                    'lain_lain' => 0.0,
                    'jml_potongan' => (float) $g->jumlah_potongan,
                    'jml_dibayarkan' => (float) $g->jumlah_ditransfer,

                    'bulan' => (int) $g->bulan,
                    'tahun' => (int) $g->tahun,
                ];
            })
            ->all();

        return $this->akumulasi($rows, $mode, self::JUMLAH_GAJI);
    }

    /**
     * TPP Beban Kerja / TPP Kondisi Kerja. Port _gtTPP().
     *
     * Nilai TPP: Penilaian = Bruto = Netto = jumlah_ditransfer (kolom AD).
     * TPP dan TOL tidak memiliki potongan, sehingga kolom Tunjangan PPh 21,
     * Potongan PPh 21, dan Pengurang IKP selalu 0.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tpp(string $jenis, string $mode, ?int $bulan, int $tahun): array
    {
        $rows = Tpp::query()
            ->periode($jenis, $mode === 'bulan' ? $bulan : null, $tahun)
            ->orderBy('bulan')
            ->get()
            ->map(function (Tpp $t) {
                $nilai = (float) $t->jumlah_ditransfer;

                return [
                    'nama' => (string) $t->nama_pegawai,
                    'nip' => (string) $t->nip,
                    'gol' => (string) $t->golongan,
                    'status' => (string) ($t->pns_pppk ?: 'PNS'),
                    'jabatan' => (string) $t->nama_jabatan,

                    'besaran100' => (float) $t->tpp_maksimum,
                    'persen' => (float) $t->nilai_kinerja,
                    'penilaian' => $nilai,
                    'tunj_pph21' => 0.0,
                    'tpp_bruto' => $nilai,
                    'pot_pph21' => 0.0,
                    'pengurang_ikp' => 0.0,
                    'netto' => $nilai,

                    'bulan' => (int) $t->bulan,
                    'tahun' => (int) $t->tahun,
                ];
            })
            ->all();

        return $this->akumulasi($rows, $mode, self::JUMLAH_TPP);
    }

    /**
     * Total Penghasilan. Port _gtTotal() - agregasi union-by-NIP dari ketiga
     * sumber, jadi pegawai yang hanya ada di salah satunya tetap muncul.
     *
     * Potongan Koperasi Praja & Zakat diambil dari TPP Beban Kerja saja;
     * kolom yang sama pada berkas Kondisi Kerja tidak dipakai (nilainya null
     * di basis data) karena di kantor potongan itu memang tidak pernah ada
     * di TOL.
     *
     * @return array<int, array<string, mixed>>
     */
    private function total(string $mode, ?int $bulan, int $tahun): array
    {
        $bulanFilter = $mode === 'bulan' ? $bulan : null;

        /** @var array<string, array<string, mixed>> $peta */
        $peta = [];

        $pastikan = function (string $nip, string $nama, string $jabatan, string $gol, string $status) use (&$peta): string {
            $kunci = $nip !== '' ? $nip : $nama;

            if (! isset($peta[$kunci])) {
                $peta[$kunci] = [
                    'nama' => $nama, 'nip' => $nip, 'jabatan' => $jabatan,
                    'gol' => $gol, 'status' => $status !== '' ? $status : 'PNS',
                    'gaji_bruto' => 0.0, 'tpp_bruto' => 0.0, 'tol_bruto' => 0.0, 'total_bruto' => 0.0,
                    'gaji_netto' => 0.0, 'tpp_netto' => 0.0, 'tol_netto' => 0.0, 'total_netto' => 0.0,
                    'pot_iuran' => 0.0, 'pot_koperasi' => 0.0, 'pot_zakat' => 0.0,
                ];
            }

            // Lengkapi identitas yang masih kosong dari sumber berikutnya.
            foreach (['nama' => $nama, 'jabatan' => $jabatan, 'gol' => $gol] as $k => $v) {
                if ($peta[$kunci][$k] === '' && $v !== '') {
                    $peta[$kunci][$k] = $v;
                }
            }

            if (($peta[$kunci]['status'] === '' || $peta[$kunci]['status'] === 'PNS') && $status !== '') {
                $peta[$kunci]['status'] = $status;
            }

            return $kunci;
        };

        foreach (GajiInduk::query()->periode($bulanFilter, $tahun)->get() as $g) {
            $kunci = $pastikan(
                (string) $g->nip, (string) $g->nama_pegawai, (string) $g->nama_jabatan,
                (string) $g->golongan, (string) $g->pppk_pns
            );

            $peta[$kunci]['gaji_bruto'] += (float) $g->jumlah_gaji_tunjangan;
            $peta[$kunci]['gaji_netto'] += (float) $g->jumlah_ditransfer;
            // Iuran 1% (kolom AL) + iuran 8% (kolom AJ) - lihat perubahan 17.
            $peta[$kunci]['pot_iuran'] += (float) $g->tunjangan_jaminan_hari_tua + (float) $g->iwp_1_persen;
        }

        foreach (Tpp::query()->periode(Tpp::JENIS_BEBAN, $bulanFilter, $tahun)->get() as $t) {
            $kunci = $pastikan(
                (string) $t->nip, (string) $t->nama_pegawai, (string) $t->nama_jabatan,
                (string) $t->golongan, (string) $t->pns_pppk
            );

            $nilai = (float) $t->jumlah_ditransfer;
            $peta[$kunci]['tpp_bruto'] += $nilai;
            $peta[$kunci]['tpp_netto'] += $nilai;
            $peta[$kunci]['pot_koperasi'] += (float) $t->koperasi_praja;
            $peta[$kunci]['pot_zakat'] += (float) $t->zakat_praja;
        }

        foreach (Tpp::query()->periode(Tpp::JENIS_KONDISI, $bulanFilter, $tahun)->get() as $t) {
            $kunci = $pastikan(
                (string) $t->nip, (string) $t->nama_pegawai, (string) $t->nama_jabatan,
                (string) $t->golongan, (string) $t->pns_pppk
            );

            $nilai = (float) $t->jumlah_ditransfer;
            $peta[$kunci]['tol_bruto'] += $nilai;
            $peta[$kunci]['tol_netto'] += $nilai;
        }

        $rows = array_map(function (array $r) {
            $r['total_bruto'] = $r['gaji_bruto'] + $r['tpp_bruto'] + $r['tol_bruto'];
            $r['total_netto'] = $r['gaji_netto'] + $r['tpp_netto'] + $r['tol_netto'];

            return $r;
        }, array_values($peta));

        return UrutanPegawaiGaji::urutkan($rows);
    }

    /**
     * Mode 'bulan': kembalikan apa adanya (sudah diurutkan). Mode 'tahun':
     * gabungkan per NIP dan jumlahkan kolom yang disebut di $jumlahkan.
     *
     * Kolom di luar $jumlahkan diambil dari baris PERTAMA pegawai itu (urut
     * bulan menaik) - sama seperti GAS yang mengkloning baris pertama lalu
     * menolkan kolom yang akan dijumlah.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $jumlahkan
     * @return array<int, array<string, mixed>>
     */
    private function akumulasi(array $rows, string $mode, array $jumlahkan): array
    {
        if ($mode !== 'tahun') {
            return UrutanPegawaiGaji::urutkan($rows);
        }

        /** @var array<string, array<string, mixed>> $peta */
        $peta = [];

        foreach ($rows as $row) {
            $kunci = $row['nip'] !== '' ? $row['nip'] : $row['nama'];

            if (! isset($peta[$kunci])) {
                $peta[$kunci] = $row;
                $peta[$kunci]['bulan'] = 0;

                foreach ($jumlahkan as $kolom) {
                    $peta[$kunci][$kolom] = 0.0;
                }
            }

            foreach ($jumlahkan as $kolom) {
                $peta[$kunci][$kolom] += (float) ($row[$kolom] ?? 0);
            }
        }

        $hasil = array_map(function (array $r) {
            // Bruto 1 tetap konsisten dengan komponennya setelah dijumlah.
            if (array_key_exists('bruto1', $r)) {
                $r['bruto1'] = (float) $r['gaji_pokok'] + (float) $r['suami_istri'] + (float) $r['anak'];
            }

            return $r;
        }, array_values($peta));

        return UrutanPegawaiGaji::urutkan($hasil);
    }

    /**
     * Verifikasi identitas untuk role yang kena gate privasi: NIP harus ada
     * di data Gaji Induk DAN 4 digit terakhir rekeningnya cocok. Port
     * _gtVerifikasiNipRek().
     *
     * @return array{ok: bool, nip?: string, err?: string}
     */
    public function verifikasi(?string $nip, ?string $rek4): array
    {
        // NIP disimpan sebagai angka saja oleh importer, jadi masukan pengguna
        // dibersihkan dengan cara yang sama - ketikan bertitik atau berspasi
        // tetap cocok.
        $nip = preg_replace('/\D/', '', (string) $nip) ?? '';
        $rek4 = self::normal($rek4);

        if ($nip === '' || $rek4 === '') {
            return ['ok' => false, 'err' => 'NIP dan 4 digit akhir rekening wajib diisi.'];
        }

        if (! preg_match('/^\d{4}$/', $rek4)) {
            return ['ok' => false, 'err' => '4 digit akhir rekening harus berupa 4 angka.'];
        }

        // Disaring per NIP di basis data lebih dulu; hanya pencocokan 4 digit
        // rekeningnya yang dilakukan di PHP, karena rekening tersimpan apa
        // adanya dari SIPD (bisa memuat spasi atau tanda baca) sehingga tidak
        // bisa dibandingkan langsung lewat SQL.
        $cocok = GajiInduk::query()
            ->where('nip', $nip)
            ->pluck('nomor_rekening_bank_pegawai')
            ->contains(fn ($rekening) => self::empatDigit($rekening) === $rek4);

        if (! $cocok) {
            return ['ok' => false, 'err' => 'NIP atau 4 digit rekening tidak sesuai.'];
        }

        return ['ok' => true, 'nip' => $nip];
    }

    /**
     * Daftar pegawai unik dari ketiga sumber, untuk pencarian di form Cetak
     * Rincian Penghasilan. Port gtDaftarPegawai().
     *
     * @return array<int, array{nip: string, nama: string, jabatan: string}>
     */
    public function daftarPegawai(): array
    {
        /** @var array<string, array{nip: string, nama: string, jabatan: string}> $peta */
        $peta = [];

        $tambah = function (?string $nip, ?string $nama, ?string $jabatan) use (&$peta): void {
            $nip = trim((string) $nip);
            $nama = trim((string) $nama);

            if ($nip === '' && $nama === '') {
                return;
            }

            $kunci = $nip !== '' ? $nip : $nama;

            if (! isset($peta[$kunci])) {
                $peta[$kunci] = ['nip' => $nip, 'nama' => $nama, 'jabatan' => trim((string) $jabatan)];
            } elseif ($peta[$kunci]['jabatan'] === '' && $jabatan) {
                $peta[$kunci]['jabatan'] = trim($jabatan);
            }
        };

        // Urut periode TERBARU lebih dulu supaya nama & jabatan yang terpakai
        // adalah yang paling mutakhir - pegawai yang berpindah jabatan tidak
        // muncul dengan jabatan lamanya di formulir.
        $terbaru = fn ($query) => $query->orderByDesc('tahun')->orderByDesc('bulan');

        foreach ($terbaru(GajiInduk::query())->get(['nip', 'nama_pegawai', 'nama_jabatan', 'tahun', 'bulan']) as $g) {
            $tambah($g->nip, $g->nama_pegawai, $g->nama_jabatan);
        }

        foreach ($terbaru(Tpp::query())->get(['nip', 'nama_pegawai', 'nama_jabatan', 'tahun', 'bulan']) as $t) {
            $tambah($t->nip, $t->nama_pegawai, $t->nama_jabatan);
        }

        $hasil = array_values($peta);
        usort($hasil, fn (array $a, array $b) => strcasecmp($a['nama'], $b['nama']));

        return $hasil;
    }

    /**
     * Identitas pegawai dari baris TERBARU (bulan+tahun terbesar) di Gaji
     * Induk, jatuh ke TPP bila tidak ada. Port gtInfoPegawai().
     *
     * @return array{ok: bool, nip?: string, nama?: string, jabatan?: string, err?: string}
     */
    public function infoPegawai(string $nip): array
    {
        $nip = trim($nip);

        $gaji = GajiInduk::query()->where('nip', $nip)
            ->orderByDesc('tahun')->orderByDesc('bulan')->first();

        if ($gaji) {
            return ['ok' => true, 'nip' => $nip, 'nama' => (string) $gaji->nama_pegawai, 'jabatan' => (string) $gaji->nama_jabatan];
        }

        $tpp = Tpp::query()->where('nip', $nip)
            ->orderByDesc('tahun')->orderByDesc('bulan')->first();

        if ($tpp) {
            return ['ok' => true, 'nip' => $nip, 'nama' => (string) $tpp->nama_pegawai, 'jabatan' => (string) $tpp->nama_jabatan];
        }

        return ['ok' => false, 'err' => 'Data pegawai tidak ditemukan.'];
    }

    /**
     * Tahun yang sudah punya data, terbaru dulu. Dipakai mengisi dropdown
     * supaya pengguna tidak memilih tahun yang pasti kosong.
     *
     * @return array<int, int>
     */
    public function tahunTersedia(): array
    {
        $tahun = GajiInduk::query()->distinct()->pluck('tahun')
            ->merge(Tpp::query()->distinct()->pluck('tahun'))
            ->map(fn ($t) => (int) $t)
            ->unique()
            ->sortDesc()
            ->values();

        return $tahun->isEmpty() ? [(int) date('Y')] : $tahun->all();
    }

    /** Buang seluruh spasi dan rapikan - dipakai membandingkan NIP & rekening. */
    private static function normal(mixed $nilai): string
    {
        return trim(preg_replace('/\s+/', '', (string) $nilai) ?? '');
    }

    /** Empat digit terakhir sebuah nomor rekening, angka saja. */
    private static function empatDigit(mixed $rekening): string
    {
        $angka = preg_replace('/\D/', '', (string) $rekening) ?? '';

        return mb_substr($angka, -4);
    }
}
