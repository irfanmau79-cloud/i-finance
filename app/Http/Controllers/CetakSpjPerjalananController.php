<?php

namespace App\Http\Controllers;

use App\Helpers\GuestSession;
use App\Models\Npd;
use App\Models\NpdTim;
use App\Models\SuratPerintah;
use Illuminate\Http\Request;

/**
 * Cetak SPJ Perjalanan Dinas — layanan mandiri, TANPA LOGIN.
 *
 * Port dari cetakSPJPerjalanan(noSP) di CodePerjalanan.gs. Pegawai memasukkan
 * Nomor Surat Perintah, lalu mendapat salah satu dari empat jawaban berjenjang
 * yang sama persis dengan GAS:
 *
 *   1. SP tidak ada                      -> "Surat Perintah tidak ditemukan."
 *   2. SP ada, belum ada NPD tertaut     -> "... belum dibuat."
 *   3. Ada NPD tapi tidak satu pun Selesai -> "... sedang dalam proses."
 *   4. Ada NPD berstatus Selesai         -> daftar NPD + nominal per anggota +
 *                                           tombol Daftar Penerimaan & SPD Rampung.
 *
 * Berkasnya tidak disimpan di disk (PDF digenerate on-demand, lihat CLAUDE.md),
 * jadi tombolnya menunjuk ke route cetak publik di bawah — bukan tautan Drive
 * seperti GAS. Aksesnya dijaga oleh syarat yang sama dengan yang menampilkan
 * tombol itu: NPD harus berjenis pd/tr, berstatus Selesai, dan tertaut ke SP.
 */
class CetakSpjPerjalananController extends Controller
{
    /** Alasan kegagalan, sama urutan dan maknanya dengan kode di GAS. */
    public const KODE_TIDAK_ADA = 'notfound';

    public const KODE_BELUM_ADA_NPD = 'nonpd';

    public const KODE_PROSES = 'proses';

    /** Kata kunci terlalu pendek untuk dicarikan awalan. */
    public const KODE_TERLALU_PENDEK = 'pendek';

    /** Beberapa SP berawalan sama - pegawai memilih dulu yang mana. */
    public const KODE_BANYAK = 'banyak';

    /**
     * Panjang minimal kata kunci pencarian awalan. Nomor SP yang lengkap tetap
     * boleh diketik utuh dan langsung ketemu; batas ini hanya berlaku saat
     * nomornya diketik sebagian.
     */
    public const MIN_CARI = 3;

    /** Batas saran yang ditampilkan sekaligus, supaya daftarnya tetap terbaca. */
    public const MAKS_SARAN = 15;

    public function index(Request $request)
    {
        GuestSession::login();

        $nomorSp = trim((string) $request->query('nomor_sp', ''));

        return view('surat-perintah.cetak-spj', [
            'nomorSp' => $nomorSp,
            'minCari' => self::MIN_CARI,
            'hasil' => $nomorSp === '' ? null : $this->cari($nomorSp),
        ]);
    }

    /**
     * Saran nomor SP untuk pencarian ketik-langsung. Sengaja HANYA
     * mengembalikan identitas SP-nya - nama anggota dan nominal tidak pernah
     * ikut, itu baru muncul setelah SP-nya benar-benar dipilih.
     */
    public function saran(Request $request)
    {
        GuestSession::login();

        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < self::MIN_CARI) {
            return response()->json([]);
        }

        return response()->json(
            $this->berawalan($q)
                ->map(fn (SuratPerintah $sp) => [
                    'nomor_sp' => $sp->nomor_sp,
                    'keterangan' => trim(collect([
                        $sp->tanggal_sp?->format('d-m-Y'),
                        $sp->unit_kerja,
                        $sp->lokasi,
                    ])->filter()->implode(' · ')),
                ])
                ->values()
        );
    }

    /**
     * SP yang nomornya DIAWALI kata kunci. Pencocokannya sengaja awalan, bukan
     * "mengandung": nomor SP dibaca dari depan, dan pegawai mengetik digit
     * pertamanya.
     *
     * @return \Illuminate\Support\Collection<int, SuratPerintah>
     */
    private function berawalan(string $q): \Illuminate\Support\Collection
    {
        // Karakter pola LIKE di kata kunci di-escape supaya "%" yang diketik
        // pegawai dicari apa adanya, bukan jadi wildcard.
        $pola = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';

        return SuratPerintah::query()
            ->where('nomor_sp', 'like', $pola)
            ->orderBy('nomor_sp')
            ->limit(self::MAKS_SARAN)
            ->get();
    }

    /**
     * @return array<string, mixed> {ok, kode, pesan, nomor_sp, daftar}
     */
    private function cari(string $nomorSp): array
    {
        // Nomor utuh selalu menang: pegawai yang menyalin nomor lengkap tidak
        // perlu memilih dari daftar walau ada nomor lain berawalan sama.
        $suratPerintah = SuratPerintah::where('nomor_sp', $nomorSp)->first();

        if (! $suratPerintah) {
            if (mb_strlen($nomorSp) < self::MIN_CARI) {
                return $this->gagal(
                    self::KODE_TERLALU_PENDEK,
                    'Ketik minimal '.self::MIN_CARI.' karakter awal Nomor Surat Perintah.'
                );
            }

            $cocok = $this->berawalan($nomorSp);

            if ($cocok->isEmpty()) {
                return $this->gagal(self::KODE_TIDAK_ADA, 'Surat Perintah tidak ditemukan.');
            }

            if ($cocok->count() > 1) {
                // array_replace, bukan union: gagal() sudah memuat kunci
                // 'pilihan' kosong, dan union akan mempertahankan yang kiri.
                return array_replace(
                    $this->gagal(self::KODE_BANYAK, 'Ada '.$cocok->count().' Surat Perintah berawalan "'.$nomorSp.'". Pilih salah satu.'),
                    ['pilihan' => $cocok]
                );
            }

            $suratPerintah = $cocok->first();
        }

        $terkait = Npd::query()
            ->where('surat_perintah_id', $suratPerintah->id)
            ->whereIn('jenis', ['pd', 'tr'])
            ->with(['tim.paket'])
            ->orderBy('tanggal_npd')
            ->orderBy('id')
            ->get();

        if ($terkait->isEmpty()) {
            return $this->gagal(
                self::KODE_BELUM_ADA_NPD,
                'Nota Pencairan Dana terkait Surat Perintah tersebut belum dibuat.'
            );
        }

        $selesai = $terkait->where('status', 'Selesai');

        if ($selesai->isEmpty()) {
            return $this->gagal(self::KODE_PROSES, 'Nota Pencairan Dana sedang dalam proses.');
        }

        return [
            'ok' => true,
            'kode' => null,
            'pesan' => null,
            'nomor_sp' => $suratPerintah->nomor_sp,
            'surat_perintah' => $suratPerintah,
            'daftar' => $selesai->map(fn (Npd $npd) => [
                'npd' => $npd,
                'jenis' => $npd->jenis === 'tr' ? 'Reimburse Transportasi' : 'Perjalanan Dinas',
                // Nominal per anggota dihitung ulang dari komponennya lewat
                // NpdPerjalananHitung (port _hitungAnggota), bukan disimpan.
                'anggota' => $npd->tim->map(fn (NpdTim $anggota) => [
                    'nama' => (string) $anggota->nama,
                    'jabatan' => (string) $anggota->jabatan,
                    'nip' => (string) $anggota->nip,
                    'nominal' => (float) ($anggota->hitung()['jumlah'] ?? 0),
                ])->all(),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function gagal(string $kode, string $pesan): array
    {
        return ['ok' => false, 'kode' => $kode, 'pesan' => $pesan, 'daftar' => [], 'pilihan' => collect()];
    }

    /**
     * Cetak Daftar Penerimaan (Daftar Pembayaran) tanpa login. Dokumen hanya
     * dilayani bila memenuhi syarat yang sama dengan yang memunculkan
     * tombolnya di halaman pencarian.
     */
    public function cetakDaftar(Npd $npd, NpdController $npdController)
    {
        GuestSession::login();
        $this->pastikanBolehDicetak($npd);

        return $npdController->cetakDaftar($npd);
    }

    /** Cetak SPD Rampung tanpa login, dengan syarat yang sama. */
    public function cetakSpd(Npd $npd, NpdController $npdController)
    {
        GuestSession::login();
        $this->pastikanBolehDicetak($npd);

        return $npdController->cetakSpd($npd);
    }

    /**
     * Dokumen perjalanan dinas hanya boleh diunduh publik bila sudah SELESAI
     * dan tertaut ke sebuah Surat Perintah - persis syarat yang dipakai GAS
     * sebelum menampilkan tautan filenya.
     */
    private function pastikanBolehDicetak(Npd $npd): void
    {
        abort_unless(in_array($npd->jenis, ['pd', 'tr'], true), 404);
        abort_unless($npd->status === 'Selesai', 404);
        abort_unless($npd->surat_perintah_id !== null, 404);
    }
}
