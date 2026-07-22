<?php

namespace App\Http\Controllers;

use App\Models\Npd;
use App\Models\Pegawai;
use App\Services\RiwayatPerjalananDinasPegawaiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PerjalananDinasPegawaiController extends Controller
{
    /** Role yang boleh membuka detail NPD (/npd/{npd}) — sama seperti middleware route npd.show, jangan diperluas di sini. */
    private const ROLE_BOLEH_LIHAT_NPD = ['superadmin', 'bendahara_pengeluaran', 'pptk', 'bpp', 'verifikator'];

    public function __invoke(Request $request, Pegawai $pegawai, RiwayatPerjalananDinasPegawaiService $service): View
    {
        $validated = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
            'jenis' => ['nullable', Rule::in(['pd', 'tr', 'kd'])],
            'status' => ['nullable', Rule::in(Npd::STATUS_LIST)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = [
            'dari' => $validated['dari'] ?? '',
            'sampai' => $validated['sampai'] ?? '',
            'jenis' => $validated['jenis'] ?? '',
            'status' => $validated['status'] ?? '',
        ];

        return view('dashboard-perjalanan.pegawai', [
            'pegawai' => $pegawai,
            'filters' => $filters,
            'riwayat' => $service->riwayat($pegawai, $filters, (int) ($validated['page'] ?? 1)),
            'bolehLihatDetailNpd' => in_array($request->user()->role, self::ROLE_BOLEH_LIHAT_NPD, true),
        ]);
    }
}
