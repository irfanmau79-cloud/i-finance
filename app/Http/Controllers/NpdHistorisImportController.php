<?php

namespace App\Http\Controllers;

use App\Exports\NpdHistorisReportExport;
use App\Exports\NpdHistorisTemplateExport;
use App\Http\Requests\StoreNpdHistorisImportRequest;
use App\Models\NpdHistorisImport;
use App\Services\NpdHistorisImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class NpdHistorisImportController extends Controller
{
    public function create()
    {
        return view('manajemen-data.import.npd-historis.create', [
            'riwayat' => NpdHistorisImport::with('user')->latest('id')->paginate(15),
        ]);
    }

    public function template()
    {
        return Excel::download(new NpdHistorisTemplateExport, 'template-import-npd-historis-v1.xlsx');
    }

    public function store(StoreNpdHistorisImportRequest $request, NpdHistorisImportService $service)
    {
        try {
            $import = $service->stage($request->file('file'), $request->user()->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('manajemen-data.import.npd-historis.preview', $import);
    }

    public function preview(Request $request, NpdHistorisImport $import)
    {
        $query = $import->baris()->orderBy('nomor_baris');
        foreach (['hasil', 'jenis_kode', 'tahun', 'status_target'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->boolean('manual')) {
            $query->where('penerima_manual', true);
        }

        return view('manajemen-data.import.npd-historis.preview', [
            'import' => $import, 'baris' => $query->paginate(50)->withQueryString(),
            'totalsByType' => $import->baris()->whereIn('hasil', ['valid', 'warning'])->selectRaw('jenis_kode, COUNT(*) jumlah, SUM(nominal_bruto) nominal')->groupBy('jenis_kode')->get(),
            'totalsByPeriod' => $import->baris()->whereIn('hasil', ['valid', 'warning'])->selectRaw('tahun, bulan, status_target, COUNT(*) jumlah, SUM(nominal_bruto) nominal')->groupBy('tahun', 'bulan', 'status_target')->orderBy('tahun')->orderBy('bulan')->get(),
        ]);
    }

    public function confirm(NpdHistorisImport $import, NpdHistorisImportService $service)
    {
        try {
            $hasil = $service->commit($import);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('manajemen-data.import.npd-historis.preview', $import)
            ->with('success', "Import selesai: {$hasil['berhasil']} berhasil, {$hasil['dilewati']} dilewati.");
    }

    public function report(NpdHistorisImport $import, string $mode)
    {
        abort_unless(in_array($mode, ['validation', 'final'], true), 404);
        abort_if($mode === 'final' && $import->status !== NpdHistorisImport::STATUS_COMMITTED, 409);

        return Excel::download(new NpdHistorisReportExport($import, $mode), "npd-historis-{$mode}-batch-{$import->id}.xlsx");
    }
}
