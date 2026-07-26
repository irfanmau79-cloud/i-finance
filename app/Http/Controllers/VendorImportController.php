<?php

namespace App\Http\Controllers;

use App\Exports\VendorTemplateExport;
use App\Helpers\AuditLog;
use App\Http\Requests\StoreVendorImportRequest;
use App\Models\VendorImport;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class VendorImportController extends Controller
{
    public function create()
    {
        return view('manajemen-data.import.vendor.create');
    }

    public function template()
    {
        return Excel::download(new VendorTemplateExport, 'template-import-vendor.xlsx');
    }

    public function store(StoreVendorImportRequest $request)
    {
        VendorImport::bersihkanKedaluwarsa();

        $import = VendorImport::buatDariUpload($request->file('file'), $request->user()->id);

        return redirect()->route('manajemen-data.import.vendor.preview', $import);
    }

    public function preview(VendorImport $import)
    {
        abort_if($import->status !== VendorImport::STATUS_STAGED && $import->status !== VendorImport::STATUS_COMMITTED, 404);

        if ($import->status === VendorImport::STATUS_STAGED && $import->kedaluwarsa()) {
            return redirect()->route('manajemen-data.import.vendor.create')
                ->withErrors(['file' => 'Sesi staging sudah kedaluwarsa. Silakan upload ulang berkasnya.']);
        }

        $baris = $import->baris()->orderBy('nomor_baris')->paginate(50);

        return view('manajemen-data.import.vendor.preview', compact('import', 'baris'));
    }

    public function konfirmasi(VendorImport $import)
    {
        try {
            $hasil = $import->konfirmasi();
        } catch (RuntimeException $e) {
            return redirect()->route('manajemen-data.import.vendor.preview', $import)
                ->withErrors(['import' => $e->getMessage()]);
        }

        AuditLog::catat('Import Vendor', sprintf(
            'File: %s, Baru: %d, Update: %d, Ditolak: %d',
            $import->nama_file,
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));

        return redirect()->route('manajemen-data.index')->with('success', sprintf(
            'Import Vendor berhasil: %d baru, %d diperbarui, %d ditolak.',
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));
    }

    public function batalkan(VendorImport $import)
    {
        abort_if($import->status !== VendorImport::STATUS_STAGED, 404);

        $import->delete();

        return redirect()->route('manajemen-data.import.vendor.create')->with('success', 'Staging import dibatalkan.');
    }
}
