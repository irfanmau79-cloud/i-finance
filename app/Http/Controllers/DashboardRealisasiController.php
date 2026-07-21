<?php

namespace App\Http\Controllers;

use App\Services\AnggaranRealisasiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardRealisasiController extends Controller
{
    public function __invoke(Request $request, AnggaranRealisasiService $service): View
    {
        $validated = $request->validate([
            'sub_kegiatan' => ['nullable', 'string', 'max:255'],
            'kode_rekening' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', Rule::in([
                'nama', 'pagu', 'dana_terikat_npd', 'realisasi_aktual',
                'sisa_tersedia', 'persentase_realisasi', 'target_rak', 'deviasi_rupiah',
            ])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $filters = [
            'sub_kegiatan' => (string) ($validated['sub_kegiatan'] ?? ''),
            'kode_rekening' => (string) ($validated['kode_rekening'] ?? ''),
        ];
        $tahun = (int) config('anggaran.tahun_aktif');
        $bulanAcuan = now()->year === $tahun ? now()->month : (now()->year > $tahun ? 12 : 1);

        return view('dashboard.index', [
            'filters' => $filters,
            'pilihan' => $service->pilihanFilter($filters['sub_kegiatan']),
            'dashboard' => $service->dashboard(
                $filters,
                $tahun,
                $bulanAcuan,
                (string) ($validated['sort'] ?? 'nama'),
                (string) ($validated['direction'] ?? 'asc'),
            ),
        ]);
    }
}
