<?php

namespace App\Http\Controllers;

use App\Exports\MasterAnggaranTemplateExport;
use App\Helpers\AuditLog;
use App\Http\Requests\StoreMasterAnggaranImportRequest;
use App\Models\MasterAnggaranImport;
use App\Models\VersiPagu;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class MasterAnggaranImportController extends Controller
{
    public function create()
    {
        $tahun = (int) config('anggaran.tahun_aktif');

        return view('manajemen-data.import.master-anggaran.create', [
            'versiAktif' => VersiPagu::aktifTahun($tahun),
            'jumlahVersi' => VersiPagu::where('tahun', $tahun)->count(),
            'saranNama' => $this->saranNamaVersi($tahun),
        ]);
    }

    public function template()
    {
        return Excel::download(new MasterAnggaranTemplateExport, 'template-import-pagu-master-anggaran.xlsx');
    }

    public function store(StoreMasterAnggaranImportRequest $request)
    {
        MasterAnggaranImport::bersihkanKedaluwarsa();

        try {
            $import = MasterAnggaranImport::buatDariUpload(
                $request->file('file'),
                (int) $request->input('tahun'),
                (string) $request->input('versi_nama'),
                $request->input('versi_nomor_dpa'),
                $request->input('versi_keterangan'),
                $request->user()->id
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
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

        $baris = $import->baris()->orderBy('nomor_baris')->orderBy('id')->paginate(50);

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
            'File: %s, Tahapan: %s (draft), Nomor DPA: %s, Baru: %d, Update: %d, Dinolkan: %d, Ditolak: %d',
            $import->nama_file,
            $import->versi_nama,
            $import->versi_nomor_dpa ?: '-',
            $hasil['baru'],
            $hasil['update'],
            $hasil['dinolkan'],
            $import->fresh()->jumlah_ditolak
        ));

        return redirect()->route('versi-pagu.index')->with('success', sprintf(
            'Tahapan pagu "%s" tersimpan sebagai draft: %d mata anggaran baru, %d diperbarui, %d dinolkan, %d ditolak. Pagu ini BELUM berlaku - tekan Aktifkan untuk memberlakukannya.',
            $import->versi_nama,
            $hasil['baru'],
            $hasil['update'],
            $hasil['dinolkan'],
            $import->fresh()->jumlah_ditolak
        ));
    }

    public function batalkan(MasterAnggaranImport $import)
    {
        abort_if($import->status !== MasterAnggaranImport::STATUS_STAGED, 404);

        $import->delete();

        return redirect()->route('manajemen-data.import.master-anggaran.create')->with('success', 'Staging import dibatalkan.');
    }

    /**
     * Usulkan nama tahapan berikutnya supaya penamaan konsisten: belum ada
     * tahapan sama sekali -> "DPA Murni", selebihnya "DPA Pergeseran N"
     * dengan N melanjutkan nomor pergeseran tertinggi yang sudah ada.
     */
    private function saranNamaVersi(int $tahun): string
    {
        $nama = VersiPagu::where('tahun', $tahun)->pluck('nama');

        if ($nama->isEmpty()) {
            return 'DPA Murni';
        }

        $tertinggi = $nama
            ->map(fn (string $n) => preg_match('/pergeseran\s*(\d+)/i', $n, $cocok) === 1 ? (int) $cocok[1] : 0)
            ->max();

        return 'DPA Pergeseran '.($tertinggi + 1);
    }
}
