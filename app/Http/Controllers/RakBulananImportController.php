<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLog;
use App\Http\Requests\StoreRakBulananImportRequest;
use App\Models\RakBulananImport;
use App\Services\RakBulananDuplicateAudit;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RakBulananImportController extends Controller
{
    public function create()
    {
        return view('manajemen-data.import.rak-bulanan.create', [
            'tahunSekarang' => (int) config('anggaran.tahun_aktif'),
            'auditDuplikat' => app(RakBulananDuplicateAudit::class)->auditDatabase(),
        ]);
    }

    public function store(StoreRakBulananImportRequest $request)
    {
        RakBulananImport::bersihkanKedaluwarsa();

        try {
            $import = RakBulananImport::buatDariUpload($request->file('file'), (int) $request->input('tahun'), $request->user()->id, $request->input('format_legacy'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('manajemen-data.import.rak-bulanan.preview', $import);
    }

    public function preview(RakBulananImport $import)
    {
        if ($import->status === RakBulananImport::STATUS_STAGED && $import->kedaluwarsa()) {
            return redirect()->route('manajemen-data.import.rak-bulanan.create')
                ->withErrors(['file' => 'Sesi staging sudah kedaluwarsa. Silakan upload ulang berkasnya.']);
        }

        $baris = $import->baris()->orderBy('nomor_baris')->orderBy('bulan')->paginate(50);

        return view('manajemen-data.import.rak-bulanan.preview', compact('import', 'baris'));
    }

    public function konfirmasi(RakBulananImport $import)
    {
        try {
            $hasil = $import->konfirmasi();
        } catch (RuntimeException $e) {
            return redirect()->route('manajemen-data.import.rak-bulanan.preview', $import)
                ->withErrors(['import' => $e->getMessage()]);
        }

        AuditLog::catat('Import RAK Bulanan', sprintf(
            'Tahun: %d, File: %s, Baru: %d, Update: %d, Ditolak: %d',
            $import->tahun,
            $import->nama_file,
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));

        return redirect()->route('manajemen-data.index')->with('success', sprintf(
            'Import RAK Bulanan %d berhasil: %d baru, %d diperbarui, %d ditolak.',
            $import->tahun,
            $hasil['baru'],
            $hasil['update'],
            $import->fresh()->jumlah_ditolak
        ));
    }

    public function batalkan(RakBulananImport $import)
    {
        abort_if($import->status !== RakBulananImport::STATUS_STAGED, 404);

        $import->delete();

        return redirect()->route('manajemen-data.import.rak-bulanan.create')->with('success', 'Staging import dibatalkan.');
    }
}
