<?php

namespace App\Http\Controllers;

use App\Helpers\GuestSession;
use App\Services\GajiTunjanganService;
use App\Support\GajiTunjanganKolom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Empat sub-menu tabel Data Gaji & Tunjangan: Gaji Induk, TPP Beban Kerja,
 * TPP Kondisi Kerja, dan Total Penghasilan.
 *
 * GATE PRIVASI. Role di luar config('gaji_tunjangan.role_data_penuh') wajib
 * memverifikasi NIP + 4 digit akhir rekening lebih dulu, dan hanya menerima
 * barisnya sendiri. Penyaringan dilakukan di GajiTunjanganService sebelum
 * data meninggalkan server - menyembunyikannya di view saja tidak cukup,
 * karena data pegawai lain akan tetap terkirim ke browser.
 *
 * Verifikasi berlaku selama sesi berjalan sehingga berpindah antar sub-menu
 * tidak perlu mengetik ulang; tersedia tombol "Ganti NIP" untuk memeriksa
 * identitas lain dalam sesi yang sama.
 */
class GajiTunjanganController extends Controller
{
    /** Kunci session penyimpan NIP yang sudah terverifikasi. */
    private const SESI_NIP = 'gt_nip_terverifikasi';

    private const PER_HALAMAN = 10;

    /**
     * Sub-menu -> [kunci menu, judul halaman, keterangan di bawah judul].
     * Judul & keterangannya disalin dari gtView() di GAS.
     */
    private const SUB = [
        'gaji' => ['gt-gaji', 'Gaji Induk', 'Rincian gaji pokok, tunjangan, dan potongan pegawai.'],
        'beban' => ['gt-beban', 'TPP Beban Kerja', 'Tambahan Penghasilan Pegawai berdasarkan Beban Kerja.'],
        'kondisi' => ['gt-kondisi', 'TPP Kondisi Kerja', 'Tambahan Penghasilan Pegawai berdasarkan Kondisi Kerja.'],
        'total' => ['gt-total', 'Total Penghasilan', 'Rekapitulasi total penghasilan bruto & netto (Gaji + TPP + TOL) per pegawai.'],
    ];

    public function __construct(private readonly GajiTunjanganService $service) {}

    public function index(Request $request, string $jenis): View
    {
        abort_unless(isset(self::SUB[$jenis]), 404);

        [$navKey, $judul, $subJudul] = self::SUB[$jenis];

        $mode = in_array($request->query('mode'), ['bulan', 'tahun'], true) ? $request->query('mode') : 'bulan';
        $tahunTersedia = $this->service->tahunTersedia();
        $tahun = (int) ($request->integer('tahun') ?: $tahunTersedia[0]);
        $bulan = min(12, max(1, (int) ($request->integer('bulan') ?: now()->month)));
        $cari = trim((string) $request->query('q', ''));

        $penuh = self::bolehDataPenuh();
        $nipSesi = $penuh ? null : session(self::SESI_NIP);

        // Belum terverifikasi -> halaman gate, tanpa satu baris pun dibaca.
        $terkunci = ! $penuh && $nipSesi === null;

        $rows = $terkunci
            ? []
            : $this->service->data($jenis, $mode, $bulan, $tahun, $nipSesi)['rows'];

        if ($cari !== '') {
            $rows = array_values(array_filter($rows, fn (array $r) => $this->cocok($r, $cari)));
        }

        return view('gaji-tunjangan.tabel', [
            'navKey' => $navKey,
            'judul' => $judul,
            'subJudul' => $subJudul,
            'jenis' => $jenis,
            'mode' => $mode,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'cari' => $cari,
            'tahunTersedia' => $tahunTersedia,
            'namaBulan' => GajiTunjanganKolom::NAMA_BULAN,
            'terkunci' => $terkunci,
            'terbatas' => ! $penuh && ! $terkunci,
            'nipSesi' => $nipSesi,
            'baris' => $this->paginasi($rows, $request),
        ]);
    }

    /** Gate privasi: cek NIP + 4 digit akhir rekening. */
    public function verifikasi(Request $request): RedirectResponse
    {
        $request->validate([
            'nip' => ['required', 'string', 'max:30'],
            'rek4' => ['required', 'string', 'max:10'],
        ], [], ['nip' => 'NIP', 'rek4' => '4 digit akhir rekening']);

        $hasil = $this->service->verifikasi($request->string('nip'), $request->string('rek4'));

        if (! $hasil['ok']) {
            return back()->withErrors(['nip' => $hasil['err']]);
        }

        session([self::SESI_NIP => $hasil['nip']]);

        return back();
    }

    /** Lupakan identitas terverifikasi supaya bisa memeriksa NIP lain. */
    public function gantiNip(): RedirectResponse
    {
        session()->forget(self::SESI_NIP);

        return back();
    }

    /**
     * Apakah role yang sedang membuka boleh melihat seluruh pegawai tanpa
     * verifikasi. Dipakai juga oleh RincianPenghasilanController.
     */
    public static function bolehDataPenuh(): bool
    {
        return in_array(GuestSession::role(), config('gaji_tunjangan.role_data_penuh'), true);
    }

    /** @param  array<string, mixed>  $baris */
    private function cocok(array $baris, string $cari): bool
    {
        $kata = mb_strtolower($cari);

        foreach (['nama', 'nip', 'jabatan'] as $kolom) {
            if (str_contains(mb_strtolower((string) ($baris[$kolom] ?? '')), $kata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Paginasi 10 pegawai per halaman, mengikuti perubahan 6 di
     * README_PERUBAHAN.txt. Datanya berupa array hasil hitungan (bukan query
     * builder), jadi paginator-nya dirakit manual.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginasi(array $rows, Request $request): LengthAwarePaginator
    {
        $halaman = max(1, (int) $request->integer('page', 1));
        $total = count($rows);

        return new LengthAwarePaginator(
            array_slice($rows, ($halaman - 1) * self::PER_HALAMAN, self::PER_HALAMAN),
            $total,
            self::PER_HALAMAN,
            $halaman,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}
