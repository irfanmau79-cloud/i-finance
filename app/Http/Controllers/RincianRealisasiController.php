<?php

namespace App\Http\Controllers;

use App\Services\AnggaranRealisasiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RincianRealisasiController extends Controller
{
    public function __invoke(Request $request, AnggaranRealisasiService $service): View
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
}
