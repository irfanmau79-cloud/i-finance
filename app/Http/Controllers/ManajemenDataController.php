<?php

namespace App\Http\Controllers;

use App\Exports\MasterAnggaranExport;
use App\Exports\NpdExport;
use App\Exports\PegawaiExport;
use App\Exports\PerjalananDinasExport;
use App\Exports\RakBulananExport;
use App\Exports\SpjPerjalananDinasExport;
use App\Exports\SpmLsExport;
use App\Exports\SpmUpGuExport;
use App\Exports\TunjanganKeluargaExport;
use App\Exports\VendorExport;
use App\Helpers\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ManajemenDataController extends Controller
{
    /** Daftar export yang tersedia di Manajemen Data. Kunci dipakai di URL & di-whitelist juga pada route. */
    private const EXPORTS = [
        'master-anggaran' => ['label' => 'Data Pagu Anggaran', 'class' => MasterAnggaranExport::class],
        'rak-bulanan' => ['label' => 'Data Rencana Anggaran Kas (RAK)', 'class' => RakBulananExport::class],
        'npd' => ['label' => 'Data Nota Pencairan Dana (NPD)', 'class' => NpdExport::class],
        'perjalanan-dinas' => ['label' => 'Data Perjalanan Dinas', 'class' => PerjalananDinasExport::class],
        'spj-perjalanan-dinas' => ['label' => 'Data SPJ Perjalanan Dinas', 'class' => SpjPerjalananDinasExport::class],
        'spm-up-gu' => ['label' => 'Data Surat Perintah Membayar (SPM) UP/GU/TU', 'class' => SpmUpGuExport::class],
        'spm-ls' => ['label' => 'Data Surat Perintah Membayar (SPM) LS', 'class' => SpmLsExport::class],
        'pegawai' => ['label' => 'Data Pegawai', 'class' => PegawaiExport::class],
        'vendor' => ['label' => 'Data Vendor', 'class' => VendorExport::class],
        'tunjangan-keluarga' => ['label' => 'Data Tunjangan Keluarga', 'class' => TunjanganKeluargaExport::class],
    ];

    /**
     * Susunan 10 tipe data yang ditampilkan di halaman Manajemen Data - satu
     * kartu per tipe, masing-masing dengan tombol Import + Export (+
     * Template kalau tersedia). 'import_template' sengaja null untuk RAK
     * Bulanan - hasil Export-nya sendiri SUDAH berperan sebagai template
     * (baris Sub Kegiatan/Kode Rekening terisi, kolom bulan kosong siap
     * diisi), lihat RakBulananExport.
     *
     * Perjalanan Dinas dan SPJ Perjalanan Dinas TIDAK punya tabel sendiri -
     * keduanya cuma tampilan terkomputasi dari tabel npd (+ npd_tim untuk
     * Perjalanan Dinas) yang sama dipakai Dashboard Perjalanan Dinas dan
     * Dashboard SPJ Perjalanan Dinas. Karena itu import-nya diarahkan ke
     * Import NPD Historis yang sama dipakai kartu Data NPD - lihat
     * 'import_note' yang ditampilkan di kartu untuk menjelaskan ini ke user.
     */
    private const TIPE_DATA = [
        'pagu' => [
            'label' => 'Data Pagu Anggaran',
            'export_jenis' => 'master-anggaran',
            'import_create' => ['manajemen-data.import.master-anggaran.create', null],
            'import_template' => ['manajemen-data.import.master-anggaran.template', null],
        ],
        'rak' => [
            'label' => 'Data Rencana Anggaran Kas (RAK)',
            'export_jenis' => 'rak-bulanan',
            'import_create' => ['manajemen-data.import.rak-bulanan.create', null],
            'import_template' => null,
        ],
        'npd' => [
            'label' => 'Data Nota Pencairan Dana (NPD)',
            'export_jenis' => 'npd',
            'import_create' => ['manajemen-data.import.npd-historis.create', null],
            'import_template' => ['manajemen-data.import.npd-historis.template', null],
        ],
        'perjalanan-dinas' => [
            'label' => 'Data Perjalanan Dinas',
            'export_jenis' => 'perjalanan-dinas',
            'import_create' => ['manajemen-data.import.npd-historis.create', null],
            'import_template' => ['manajemen-data.import.npd-historis.template', null],
            'import_note' => 'Data ini dihitung dari NPD Perjalanan Dinas/Transport - import lewat Import NPD Historis (sama seperti kartu Data NPD).',
        ],
        'spj-perjalanan-dinas' => [
            'label' => 'Data SPJ Perjalanan Dinas',
            'export_jenis' => 'spj-perjalanan-dinas',
            'import_create' => ['manajemen-data.import.npd-historis.create', null],
            'import_template' => ['manajemen-data.import.npd-historis.template', null],
            'import_note' => 'Data ini dihitung dari NPD dengan kode rekening Belanja Perjalanan Dinas - import lewat Import NPD Historis (sama seperti kartu Data NPD).',
        ],
        'spm-up-gu' => [
            'label' => 'Data Surat Perintah Membayar (SPM) UP/GU/TU',
            'export_jenis' => 'spm-up-gu',
            'import_create' => ['manajemen-data.import.spm.create', 'spm-up-gu'],
            'import_template' => ['manajemen-data.import.spm.template', 'spm-up-gu'],
        ],
        'spm-ls' => [
            'label' => 'Data Surat Perintah Membayar (SPM) LS',
            'export_jenis' => 'spm-ls',
            'import_create' => ['manajemen-data.import.spm.create', 'spm-ls'],
            'import_template' => ['manajemen-data.import.spm.template', 'spm-ls'],
        ],
        'pegawai' => [
            'label' => 'Data Pegawai',
            'export_jenis' => 'pegawai',
            'import_create' => ['manajemen-data.import.pegawai.create', null],
            'import_template' => ['manajemen-data.import.pegawai.template', null],
        ],
        'vendor' => [
            'label' => 'Data Vendor',
            'export_jenis' => 'vendor',
            'import_create' => ['manajemen-data.import.vendor.create', null],
            'import_template' => ['manajemen-data.import.vendor.template', null],
        ],
        'tunjangan-keluarga' => [
            'label' => 'Data Tunjangan Keluarga',
            'export_jenis' => 'tunjangan-keluarga',
            'import_create' => ['tunjangan.import.create', null],
            'import_template' => ['tunjangan.import.template', null],
        ],
    ];

    public function index()
    {
        return view('manajemen-data.index', [
            'tipeData' => self::TIPE_DATA,
            'tahunSekarang' => (int) config('anggaran.tahun_aktif'),
        ]);
    }

    public function export(string $jenis, Request $request)
    {
        abort_unless(isset(self::EXPORTS[$jenis]), 404);

        $meta = self::EXPORTS[$jenis];
        // RakBulananExport butuh tahun (RAK terikat per tahun anggaran) - export lain tidak punya parameter konstruktor.
        if ($jenis === 'rak-bulanan') {
            $tahunAktif = (int) config('anggaran.tahun_aktif');
            $tahun = $request->integer('tahun', $tahunAktif);
            if ($tahun !== $tahunAktif) {
                throw ValidationException::withMessages([
                    'tahun' => "Template RAK Bulanan hanya tersedia untuk Tahun Anggaran {$tahunAktif}.",
                ]);
            }
            $export = new $meta['class']($tahun);
        } else {
            $export = new $meta['class'];
        }
        $jumlahBaris = $export->jumlahBaris();
        $filename = 'export-'.$jenis.'-'.now()->format('Ymd-His').'.xlsx';

        AuditLog::catat('Export Data', "Jenis: {$meta['label']}, Baris: {$jumlahBaris}, File: {$filename}");

        return Excel::download($export, $filename);
    }
}
