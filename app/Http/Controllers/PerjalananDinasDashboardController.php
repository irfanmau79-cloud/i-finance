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
        $filters = array_merge(['bidang' => '', 'pegawai' => '', 'metrik' => 'diterima'], $request->validate([
            'bidang' => ['nullable', 'string', 'max:100'],
            'pegawai' => ['nullable', 'string', 'max:255'],
            'metrik' => ['nullable', Rule::in(['hari', 'uang_harian', 'akomodasi', 'transport', 'representatif', 'diterima'])],
        ]));

        return view('dashboard-perjalanan.index', [
            'filters' => $filters,
            'dashboard' => $service->ringkasan($filters, (int) config('anggaran.tahun_aktif')),
        ]);
    }
}
