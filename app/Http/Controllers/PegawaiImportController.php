<?php

namespace App\Http\Controllers;

use App\Exports\PegawaiTemplateExport;
use App\Helpers\AuditLog;
use App\Http\Requests\StorePegawaiImportRequest;
use App\Models\PegawaiImport;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class PegawaiImportController extends Controller
{
    public function create()
    {
        return view('manajemen-data.import.pegawai.create');
    }

    public function template()
    {
        return Excel::download(new PegawaiTemplateExport, 'template-import-pegawai.xlsx');
    }

    public function store(StorePegawaiImportRequest $request)
    {
        PegawaiImport::bersihkanKedaluwarsa();

        $import = PegawaiImport::buatDariUpload($request->file('file'), $request->user()->id);

        return redirect()->route('manajemen-data.import.pegawai.preview', $import);
    }

    public function preview(PegawaiImport $import)
    {
        abort_if($import->status !== PegawaiImport::STATUS_STAGED && $import->status !== PegawaiImport::STATUS_COMMITTED, 404);

        if ($import->status === PegawaiImport::STATUS_STAGED && $import->kedaluwarsa()) {
            return redirect()->route('manajemen-data.import.pegawai.create')
                ->withErrors(['file' => 'Sesi staging sudah kedaluwarsa. Silakan upload ulang berkasnya.']);
        }

        $baris = $import->baris()->orderBy('nomor_baris')->paginate(50);

        return view('manajemen-data.import.pegawai.preview', compact('import', 'baris'));
    }

    public function konfirmasi(PegawaiImport $import)
    {
        try {
            $hasil = $import->konfirmasi();
        } catch (RuntimeException $e) {
            return redirect()->route('manajemen-data.import.pegawai.preview', $import)
                ->withErrors(['import' => $e->getMessage()]);
        }

        AuditLog::catat('Import Pegawai', sprintf(
            'File: %s, Baru: %d, Update: %d, Ditolak: %d',
            $import->nama_file,
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));

        return redirect()->route('manajemen-data.index')->with('success', sprintf(
            'Import Pegawai berhasil: %d baru, %d diperbarui, %d ditolak.',
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));
    }

    public function batalkan(PegawaiImport $import)
    {
        abort_if($import->status !== PegawaiImport::STATUS_STAGED, 404);

        $import->delete();

        return redirect()->route('manajemen-data.import.pegawai.create')->with('success', 'Staging import dibatalkan.');
    }
}
