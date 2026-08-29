<?php

namespace App\Http\Controllers;

use App\Services\PerjalananDinasDashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PerjalananDinasDashboardController extends Controller
{
    public function __invoke(Request $request, PerjalananDinasDashboardService $service): View
    {
        $filters = array_merge(['bidang' => '', 'pegawai' => '', 'metrik' => 'terima'], $request->validate([
            'bidang' => ['nullable', 'string', 'max:100'],
            'pegawai' => ['nullable', 'string', 'max:255'],
            'metrik' => ['nullable', Rule::in(array_keys(PerjalananDinasDashboardService::METRIK))],
        ]));

        return view('dashboard-perjalanan.index', [
            'filters' => $filters,
            'dashboard' => $service->data($filters, (int) config('anggaran.tahun_aktif')),
        ]);
    }
}
