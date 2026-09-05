<?php

namespace App\Http\Controllers;

use App\Exports\PkptTemplateExport;
use App\Helpers\AuditLog;
use App\Http\Requests\StorePkptImportRequest;
use App\Models\PkptImport;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * Import Data PKPT lewat Manajemen Data. Alur preview/dry-run yang sama
 * dengan import lain: unggah -> staging -> periksa -> Konfirmasi Simpan.
 */
class PkptImportController extends Controller
{
    public function create()
    {
        return view('manajemen-data.import.pkpt.create', [
            'tahunAktif' => (int) config('anggaran.tahun_aktif'),
        ]);
    }

    public function template()
    {
        return Excel::download(new PkptTemplateExport, 'template-import-pkpt.xlsx');
    }

    public function store(StorePkptImportRequest $request)
    {
        PkptImport::bersihkanKedaluwarsa();

        $import = PkptImport::buatDariUpload(
            $request->file('file'),
            (int) $request->integer('tahun'),
            $request->user()?->id
        );

        return redirect()->route('manajemen-data.import.pkpt.preview', $import);
    }

    public function preview(PkptImport $import)
    {
        abort_if(! in_array($import->status, [PkptImport::STATUS_STAGED, PkptImport::STATUS_COMMITTED], true), 404);

        if ($import->status === PkptImport::STATUS_STAGED && $import->kedaluwarsa()) {
            return redirect()->route('manajemen-data.import.pkpt.create')
                ->withErrors(['file' => 'Sesi staging sudah kedaluwarsa. Silakan upload ulang berkasnya.']);
        }

        $baris = $import->baris()->orderBy('nomor_baris')->paginate(50);

        return view('manajemen-data.import.pkpt.preview', compact('import', 'baris'));
    }

    public function konfirmasi(PkptImport $import)
    {
        try {
            $hasil = $import->konfirmasi();
        } catch (RuntimeException $e) {
            return redirect()->route('manajemen-data.import.pkpt.preview', $import)
                ->withErrors(['import' => $e->getMessage()]);
        }

        AuditLog::catat('Import PKPT', sprintf(
            'File: %s, Tahun: %d, Baru: %d, Update: %d, Ditolak: %d',
            $import->nama_file,
            $import->tahun,
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));

        return redirect()->route('manajemen-data.index')->with('success', sprintf(
            'Import Data PKPT %d berhasil: %d baru, %d diperbarui, %d ditolak.',
            $import->tahun,
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));
    }

    public function batalkan(PkptImport $import)
    {
        abort_if($import->status !== PkptImport::STATUS_STAGED, 404);

        $import->delete();

        return redirect()->route('manajemen-data.import.pkpt.create')->with('success', 'Staging import dibatalkan.');
    }
}
