<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Http\Requests\StoreMasterAnggaranImportRequest;
use App\Models\MasterAnggaranImport;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MasterAnggaranImportController extends Controller
{
    public function create()
    {
        return view('manajemen-data.import.master-anggaran.create');
    }

    public function store(StoreMasterAnggaranImportRequest $request)
    {
        MasterAnggaranImport::bersihkanKedaluwarsa();

        try {
            $import = MasterAnggaranImport::buatDariUpload($request->file('file'), $request->user()->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('manajemen-data.import.master-anggaran.preview', $import);
    }

    public function preview(MasterAnggaranImport $import)
    {
        abort_if($import->status !== MasterAnggaranImport::STATUS_STAGED && $import->status !== MasterAnggaranImport::STATUS_COMMITTED, 404);

        if ($import->status === MasterAnggaranImport::STATUS_STAGED && $import->kedaluwarsa()) {
            return redirect()->route('manajemen-data.import.master-anggaran.create')
                ->withErrors(['file' => 'Sesi staging sudah kedaluwarsa. Silakan upload ulang berkasnya.']);
        }

        $baris = $import->baris()->orderBy('nomor_baris')->paginate(50);

        return view('manajemen-data.import.master-anggaran.preview', compact('import', 'baris'));
    }

    public function konfirmasi(MasterAnggaranImport $import)
    {
        try {
            $hasil = $import->konfirmasi();
        } catch (RuntimeException $e) {
            return redirect()->route('manajemen-data.import.master-anggaran.preview', $import)
                ->withErrors(['import' => $e->getMessage()]);
        }

        AuditLog::catat('Import Master Anggaran', sprintf(
            'File: %s, Baru: %d, Update: %d, Ditolak: %d',
            $import->nama_file,
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));

        return redirect()->route('manajemen-data.index')->with('success', sprintf(
            'Import Master Anggaran berhasil: %d baru, %d diperbarui, %d ditolak.',
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));
    }

    public function batalkan(MasterAnggaranImport $import)
    {
        abort_if($import->status !== MasterAnggaranImport::STATUS_STAGED, 404);

        $import->delete();

        return redirect()->route('manajemen-data.import.master-anggaran.create')->with('success', 'Staging import dibatalkan.');
    }
}
