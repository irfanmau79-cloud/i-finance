<?php

namespace App\Http\Controllers;

use App\Exports\MasterAnggaranExport;
use App\Exports\NpdExport;
use App\Exports\PegawaiExport;
use App\Exports\PejabatExport;
use App\Exports\RakBulananExport;
use App\Exports\SpmLsExport;
use App\Exports\SpmUpGuExport;
use App\Exports\TaggingExport;
use App\Exports\VendorExport;
use App\Helpers\AuditLog;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ManajemenDataController extends Controller
{
    /** Daftar export yang tersedia di Manajemen Data. Kunci dipakai di URL & di-whitelist juga pada route. */
    private const EXPORTS = [
        'master-anggaran' => ['label' => 'Pagu / Master Anggaran', 'class' => MasterAnggaranExport::class],
        'npd' => ['label' => 'NPD', 'class' => NpdExport::class],
        'spm-up-gu' => ['label' => 'SPM UP/GU', 'class' => SpmUpGuExport::class],
        'spm-ls' => ['label' => 'SPM LS', 'class' => SpmLsExport::class],
        'pegawai' => ['label' => 'Pegawai', 'class' => PegawaiExport::class],
        'vendor' => ['label' => 'Vendor', 'class' => VendorExport::class],
        'tagging' => ['label' => 'Tagging', 'class' => TaggingExport::class],
        'pejabat' => ['label' => 'Data Pejabat (PA/Bendahara/KPA/BPP)', 'class' => PejabatExport::class],
        'rak-bulanan' => ['label' => 'RAK Bulanan', 'class' => RakBulananExport::class],
    ];

    public function index()
    {
        return view('manajemen-data.index', ['exports' => self::EXPORTS, 'tahunSekarang' => now()->year]);
    }

    public function export(string $jenis, Request $request)
    {
        abort_unless(isset(self::EXPORTS[$jenis]), 404);

        $meta = self::EXPORTS[$jenis];
        // RakBulananExport butuh tahun (RAK terikat per tahun anggaran) - export lain tidak punya parameter konstruktor.
        $export = $jenis === 'rak-bulanan'
            ? new $meta['class']($request->integer('tahun', now()->year))
            : new $meta['class']();
        $jumlahBaris = $export->jumlahBaris();
        $filename = 'export-'.$jenis.'-'.now()->format('Ymd-His').'.xlsx';

        AuditLog::catat('Export Data', "Jenis: {$meta['label']}, Baris: {$jumlahBaris}, File: {$filename}");

        return Excel::download($export, $filename);
    }
}
