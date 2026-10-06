<?php

namespace App\Http\Controllers;

use App\Services\DashboardNpdService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Dashboard Nota Pencairan Dana - baca-saja, lihat App\Services\DashboardNpdService. */
class DashboardNpdController extends Controller
{
    public function __invoke(Request $request, DashboardNpdService $service): View
    {
        $filters = array_merge(['status' => '', 'bulan' => '', 'unit' => '', 'cari' => ''], $request->validate([
            'status' => ['nullable', Rule::in(['selesai', 'proses'])],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'unit' => ['nullable', 'string', 'max:150'],
            'cari' => ['nullable', 'string', 'max:255'],
        ]));

        return view('dashboard-npd.index', [
            'filters' => array_map(fn ($nilai) => (string) ($nilai ?? ''), $filters),
            'dashboard' => $service->ringkasan($filters, (int) config('anggaran.tahun_aktif')),
            'tahun' => (int) config('anggaran.tahun_aktif'),
        ]);
    }
}
