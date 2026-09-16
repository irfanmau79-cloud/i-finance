<?php

namespace App\Http\Controllers;

use App\Services\AnggaranRealisasiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Modul Rincian Realisasi, dua sub menu atas data yang sama:
 *
 *   - index()    "Realisasi Tahunan"  - pohon Program > Sub Kegiatan > Kode
 *                Rekening > Tagging dengan angka kumulatif setahun.
 *   - periodik() "Realisasi Periodik" - satu baris per mata anggaran, dua
 *                belas kolom bulan berisi realisasi SPJ3 bulan itu.
 *
 * Keduanya menumpang kunci akses 'rincian' yang sama (bukan kunci baru di
 * config/akses.php): isinya angka yang sama, cuma dipotong berbeda, jadi
 * tidak ada peran yang boleh melihat satu tapi tidak boleh melihat lainnya.
 */
class RincianRealisasiController extends Controller
{
    public function index(Request $request, AnggaranRealisasiService $service): View
    {
        $filters = $request->validate([
            'sub_kegiatan' => ['nullable', 'string', 'max:255'],
            'kode_rekening' => ['nullable', 'string', 'max:50'],
            'tagging' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $filters = array_merge([
            'sub_kegiatan' => '',
            'kode_rekening' => '',
            'tagging' => '',
            'q' => '',
        ], $filters);

        $pilihan = $service->pilihanFilter();
        $rincian = $service->rincian($filters);

        return view('rincian.index', [
            'tree' => $rincian['tree'],
            'total' => $rincian['total'],
            'filters' => $filters,
            'subKegiatanOptions' => $pilihan['sub_kegiatan'],
            'kodeRekeningOptions' => $pilihan['kode_rekening_berlabel'],
            'taggingOptions' => $pilihan['tagging'],
            'memilikiTanpaTagging' => $pilihan['tanpa_tagging'],
        ]);
    }

    /**
     * Realisasi Periodik. Penyaringnya dikerjakan di SERVER (bukan di
     * browser seperti Realisasi Tahunan) karena kolom Program/Sub Kegiatan/
     * Kodering digabung dengan rowspan: menyembunyikan baris di browser akan
     * menyisakan sel gabungan yang tingginya tidak lagi cocok dengan jumlah
     * baris yang tersisa.
     */
    public function periodik(Request $request, AnggaranRealisasiService $service): View
    {
        $filters = $request->validate([
            'sub_kegiatan' => ['nullable', 'string', 'max:255'],
            'kode_rekening' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $filters = array_merge([
            'sub_kegiatan' => '',
            'kode_rekening' => '',
            'q' => '',
        ], $filters);

        $pilihan = $service->pilihanFilter($filters['sub_kegiatan'] ?: null);
        $hasil = $service->realisasiBulanan($filters, (int) config('anggaran.tahun_aktif'));

        return view('rincian.periodik', [
            'baris' => $hasil['baris'],
            'pohon' => $hasil['pohon'],
            'total' => $hasil['total'],
            'bulan' => $hasil['bulan'],
            'tahun' => $hasil['tahun'],
            'filters' => $filters,
            'subKegiatanOptions' => $pilihan['sub_kegiatan'],
            'kodeRekeningOptions' => $pilihan['kode_rekening_berlabel'],
        ]);
    }
}
