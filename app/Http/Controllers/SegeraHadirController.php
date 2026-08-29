<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Halaman "Under Progress" untuk menu yang rumahnya sudah dibuat tetapi
 * isinya belum. Satu controller untuk semuanya - tiap rute cukup menyebut
 * kunci menunya, judul dan nama modulnya diambil dari daftar di bawah.
 *
 * Menu di sini TIDAK boleh dianggap fitur yang sudah jalan: begitu satu
 * halaman digarap, entrinya dihapus dari HALAMAN dan rutenya diarahkan ke
 * controller sungguhan.
 */
class SegeraHadirController extends Controller
{
    /** @var array<string, array{judul: string, modul: string}> */
    public const HALAMAN = [
        'gt-gaji' => ['judul' => 'Gaji Induk', 'modul' => 'Gaji dan Tunjangan'],
        'gt-beban' => ['judul' => 'TPP Beban Kerja', 'modul' => 'Gaji dan Tunjangan'],
        'gt-kondisi' => ['judul' => 'TPP Kondisi Kerja', 'modul' => 'Gaji dan Tunjangan'],
        'gt-total' => ['judul' => 'Total Penghasilan', 'modul' => 'Gaji dan Tunjangan'],
        'gt-cetak' => ['judul' => 'Cetak Rincian Penghasilan', 'modul' => 'Gaji dan Tunjangan'],
        'gt-daftar' => ['judul' => 'Daftar Rincian Penghasilan', 'modul' => 'Gaji dan Tunjangan'],
        'sp-cetaksppd' => ['judul' => 'Cetak SPPD', 'modul' => 'Surat Perintah'],
    ];

    public function __invoke(string $menu): View
    {
        abort_unless(isset(self::HALAMAN[$menu]), 404);

        return view('segera.index', [
            'navKey' => $menu,
            'judul' => self::HALAMAN[$menu]['judul'],
            'modul' => self::HALAMAN[$menu]['modul'],
        ]);
    }
}
