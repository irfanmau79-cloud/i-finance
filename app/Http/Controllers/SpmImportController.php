<?php

namespace App\Http\Controllers;

use App\Exports\SpmLsTemplateExport;
use App\Exports\SpmUpGuTemplateExport;
use App\Helpers\AuditLog;
use App\Http\Requests\StoreSpmImportRequest;
use App\Models\SpmImport;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class SpmImportController extends Controller
{
    /** Kunci di URL (selaras dengan jenis export Prompt 09) -> nilai jenis_spm internal. */
    private const JENIS_URL_KE_INTERNAL = [
        'spm-up-gu' => 'up_gu',
        'spm-ls' => 'ls',
    ];

    public function create(string $jenis)
    {
        $jenisSpm = self::resolveJenis($jenis);

        return view('manajemen-data.import.spm.create', ['jenis' => $jenis, 'jenisSpm' => $jenisSpm]);
    }

    public function template(string $jenis)
    {
        self::resolveJenis($jenis);

        $export = $jenis === 'spm-ls' ? new SpmLsTemplateExport : new SpmUpGuTemplateExport;

        return Excel::download($export, 'template-import-'.$jenis.'.xlsx');
    }

    public function store(StoreSpmImportRequest $request, string $jenis)
    {
        $jenisSpm = self::resolveJenis($jenis);

        SpmImport::bersihkanKedaluwarsa();

        try {
            $import = SpmImport::buatDariUpload($request->file('file'), $jenisSpm, $request->user()->id);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('manajemen-data.import.spm.preview', $import);
    }

    public function preview(SpmImport $import)
    {
        if ($import->status === SpmImport::STATUS_STAGED && $import->kedaluwarsa()) {
            return redirect()->route('manajemen-data.import.spm.create', self::jenisUntukUrl($import->jenis_spm))
                ->withErrors(['file' => 'Sesi staging sudah kedaluwarsa. Silakan upload ulang berkasnya.']);
        }

        $baris = $import->baris()->with(['penerimaPegawai:id,nama', 'penerimaVendor:id,nama'])->orderBy('nomor_baris')->paginate(50);

        return view('manajemen-data.import.spm.preview', compact('import', 'baris'));
    }

    public function konfirmasi(SpmImport $import)
    {
        try {
            $hasil = $import->konfirmasi();
        } catch (RuntimeException $e) {
            return redirect()->route('manajemen-data.import.spm.preview', $import)
                ->withErrors(['import' => $e->getMessage()]);
        }

        AuditLog::catat('Import SPM', sprintf(
            'Jenis: %s, File: %s, Baru: %d, Update: %d, Ditolak: %d',
            strtoupper($import->jenis_spm),
            $import->nama_file,
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));

        return redirect()->route('manajemen-data.index')->with('success', sprintf(
            'Import SPM %s berhasil: %d baru, %d diperbarui, %d ditolak.',
            strtoupper($import->jenis_spm),
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));
    }

    public function batalkan(SpmImport $import)
    {
        abort_if($import->status !== SpmImport::STATUS_STAGED, 404);

        $jenisUrl = self::jenisUntukUrl($import->jenis_spm);
        $import->delete();

        return redirect()->route('manajemen-data.import.spm.create', $jenisUrl)->with('success', 'Staging import dibatalkan.');
    }

    private static function resolveJenis(string $jenis): string
    {
        abort_unless(isset(self::JENIS_URL_KE_INTERNAL[$jenis]), 404);

        return self::JENIS_URL_KE_INTERNAL[$jenis];
    }

    private static function jenisUntukUrl(string $jenisSpm): string
    {
        return array_search($jenisSpm, self::JENIS_URL_KE_INTERNAL, true) ?: 'spm-up-gu';
    }
}
