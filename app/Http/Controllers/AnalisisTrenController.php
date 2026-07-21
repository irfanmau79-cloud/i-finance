<?php

namespace App\Http\Controllers;

use App\Services\AnggaranRealisasiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalisisTrenController extends Controller
{
    public function __invoke(Request $request, AnggaranRealisasiService $service): View
    {
        $filters = array_merge([
            'sub_kegiatan' => '',
            'kode_rekening' => '',
        ], $request->validate([
            'sub_kegiatan' => ['nullable', 'string', 'max:255'],
            'kode_rekening' => ['nullable', 'string', 'max:50'],
        ]));

        $tahun = (int) config('anggaran.tahun_aktif');
        $bulanAcuan = now()->year === $tahun ? now()->month : (now()->year > $tahun ? 12 : 1);

        return view('analisis.index', [
            'filters' => $filters,
            'pilihan' => $service->pilihanFilter($filters['sub_kegiatan']),
            'analisis' => $service->analisis($filters, $tahun, $bulanAcuan),
        ]);
    }
}
