<?php

namespace App\Http\Controllers;

use App\Exports\TunjanganKeluargaTemplateExport;
use App\Models\TunjanganKeluargaImport;
use App\Services\TunjanganKeluargaImportService;
use App\Services\TunjanganKeluargaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class TunjanganKeluargaImportController extends Controller
{
    public function create(): View
    {
        return view('tunjangan-keluarga.import.create');
    }

    public function template()
    {
        return Excel::download(new TunjanganKeluargaTemplateExport, 'template-import-tunjangan-keluarga.xlsx');
    }

    public function store(Request $request, TunjanganKeluargaImportService $service): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);
        $import = $service->preview($request->file('file'), $request->user()->id);

        return redirect()->route('tunjangan.import.preview', $import);
    }

    public function preview(TunjanganKeluargaImport $import): View
    {
        return view('tunjangan-keluarga.import.preview', ['import' => $import->load('baris')]);
    }

    public function confirm(TunjanganKeluargaImport $import, TunjanganKeluargaImportService $service, TunjanganKeluargaService $keluarga): RedirectResponse
    {
        $count = $service->confirm($import, $keluarga, request()->user()->id);

        return redirect()->route('tunjangan.monitoring')->with('success', "Import awal selesai: {$count} pegawai diperbarui.");
    }
}
