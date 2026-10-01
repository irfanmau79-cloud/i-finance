<?php

namespace App\Http\Controllers;

use App\Exports\RekapPotensiTemplateExport;
use App\Helpers\AuditLog;
use App\Http\Requests\StoreRekapPotensiImportRequest;
use App\Models\RekapPotensiImport;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class RekapPotensiImportController extends Controller
{
    public function create()
    {
        return view('manajemen-data.import.rekap-potensi.create');
    }

    public function template()
    {
        return Excel::download(new RekapPotensiTemplateExport, 'template-import-rekap-potensi-pengembalian.xlsx');
    }

    public function store(StoreRekapPotensiImportRequest $request)
    {
        RekapPotensiImport::bersihkanKedaluwarsa();

        $import = RekapPotensiImport::buatDariUpload($request->file('file'), $request->user()->id);

        return redirect()->route('manajemen-data.import.rekap-potensi.preview', $import);
    }

    public function preview(RekapPotensiImport $import)
    {
        abort_if(! in_array($import->status, [RekapPotensiImport::STATUS_STAGED, RekapPotensiImport::STATUS_COMMITTED], true), 404);

        if ($import->status === RekapPotensiImport::STATUS_STAGED && $import->kedaluwarsa()) {
            return redirect()->route('manajemen-data.import.rekap-potensi.create')
                ->withErrors(['file' => 'Sesi staging sudah kedaluwarsa. Silakan upload ulang berkasnya.']);
        }

        $baris = $import->baris()->orderBy('nomor_baris')->paginate(50);

        return view('manajemen-data.import.rekap-potensi.preview', compact('import', 'baris'));
    }

    public function konfirmasi(RekapPotensiImport $import)
    {
        try {
            $hasil = $import->konfirmasi();
        } catch (RuntimeException $e) {
            return redirect()->route('manajemen-data.import.rekap-potensi.preview', $import)
                ->withErrors(['import' => $e->getMessage()]);
        }

        AuditLog::catat('Import Rekap Potensi Pengembalian', sprintf(
            'File: %s, Baru: %d, Update: %d, Ditolak: %d',
            $import->nama_file,
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));

        return redirect()->route('manajemen-data.index')->with('success', sprintf(
            'Import Rekap Potensi Pengembalian berhasil: %d baru, %d diperbarui, %d ditolak.',
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));
    }

    public function batalkan(RekapPotensiImport $import)
    {
        abort_if($import->status !== RekapPotensiImport::STATUS_STAGED, 404);

        $import->delete();

        return redirect()->route('manajemen-data.import.rekap-potensi.create')->with('success', 'Staging import dibatalkan.');
    }
}
