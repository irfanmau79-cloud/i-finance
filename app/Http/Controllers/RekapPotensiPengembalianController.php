<?php

namespace App\Http\Controllers;

use App\Models\RekapPotensiPengembalian;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sub menu "Rekap Potensi Pengembalian" pada grup Data Gaji dan Tunjangan
 * (adopsi GAS #95 & #96).
 *
 * Halaman BACA-SAJA. Datanya masuk lewat Manajemen Data > Data Rekap Potensi
 * Pengembalian; di sini ia hanya dibaca, disaring, dan diurutkan.
 *
 * Isinya sensitif - kelebihan pembayaran per pegawai - jadi aksesnya
 * disamakan dengan Rekonsiliasi Gaji, modul terdekat yang juga menyangkut
 * selisih bayar per orang.
 */
class RekapPotensiPengembalianController extends Controller
{
    /** Pilihan saringan untuk tiap kolom nominal. */
    public const SARING = ['semua', 'ada', 'tidak'];

    public function __invoke(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));
        $saring = in_array($request->query('saring'), self::SARING, true)
            ? $request->query('saring')
            : 'semua';

        $rekap = RekapPotensiPengembalian::query()
            ->when($cari !== '', fn ($q) => $q->where(fn ($qq) => $qq
                ->where('nama', 'like', "%{$cari}%")
                ->orWhere('nip', 'like', "%{$cari}%")
                ->orWhere('jabatan', 'like', "%{$cari}%")))
            // "Terdapat Pengembalian" = masih ada yang harus ditagih; yang
            // lunas maupun yang nol sama-sama masuk kelompok sebaliknya.
            ->when($saring === 'ada', fn ($q) => $q->whereRaw('(potensi - setoran) > 0'))
            ->when($saring === 'tidak', fn ($q) => $q->whereRaw('(potensi - setoran) <= 0'))
            ->urutanBaku()
            ->paginate(10)
            ->withQueryString();

        // Ringkasan dihitung atas SELURUH data, bukan halaman yang tampil -
        // angka yang berubah-ubah mengikuti paginasi tidak ada gunanya.
        $semua = RekapPotensiPengembalian::query();

        return view('gaji-tunjangan.rekap-potensi', [
            'rekap' => $rekap,
            'cari' => $cari,
            'saring' => $saring,
            'totalPotensi' => (float) $semua->clone()->sum('potensi'),
            'totalSetoran' => (float) $semua->clone()->sum('setoran'),
            'totalSisa' => (float) $semua->clone()->sum('potensi') - (float) $semua->clone()->sum('setoran'),
            'jumlahBelumLunas' => $semua->clone()->whereRaw('(potensi - setoran) > 0')->count(),
        ]);
    }
}
