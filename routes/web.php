<?php

use App\Http\Controllers\AnalisisTrenController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardRealisasiController;
use App\Http\Controllers\InventarisasiSpjController;
use App\Http\Controllers\ManajemenDataController;
use App\Http\Controllers\MasterAnggaranImportController;
use App\Http\Controllers\NpdBjController;
use App\Http\Controllers\NpdController;
use App\Http\Controllers\NpdHistorisImportController;
use App\Http\Controllers\NpdKontribusiDiklatController;
use App\Http\Controllers\NpdNarasumberController;
use App\Http\Controllers\NpdPdController;
use App\Http\Controllers\NpdTransportController;
use App\Http\Controllers\PelimpahanController;
use App\Http\Controllers\PengumumanController;
use App\Http\Controllers\PerjalananDinasDashboardController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RakBulananImportController;
use App\Http\Controllers\RincianRealisasiController;
use App\Http\Controllers\SpjDashboardController;
use App\Http\Controllers\SpmController;
use App\Http\Controllers\SpmImportController;
use App\Http\Controllers\SuratPerintahController;
use App\Http\Controllers\TunjanganKeluargaController;
use App\Http\Controllers\TunjanganKeluargaImportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth.or.guest');

// Publik, tanpa login — dipakai role "layanan" untuk mengisi orderan SP dari luar.
Route::get('/sp/input', [SuratPerintahController::class, 'publicCreate'])->name('sp.input.create');
Route::post('/sp/input', [SuratPerintahController::class, 'publicStore'])->middleware('throttle:5,1')->name('sp.input.store');

// Publik, tanpa login — Monitoring SP juga dipakai role "layanan" untuk memantau
// orderan SP miliknya (lihat CodeSuratPerintah.gs: "Monitoring SP = daftar orderan
// yang diinput orang kantor"). Role yang login tetap melihatnya lewat sidebar biasa.
Route::get('/surat-perintah/monitoring', [SuratPerintahController::class, 'monitoring'])->name('surat-perintah.monitoring');
Route::get('/pengumuman', [PengumumanController::class, 'show'])->name('pengumuman.show');
Route::get('/tunjangan-keluarga/perubahan', [TunjanganKeluargaController::class, 'form'])->name('tunjangan.form');
Route::post('/tunjangan-keluarga/perubahan', [TunjanganKeluargaController::class, 'submit'])->middleware('throttle:5,1')->name('tunjangan.submit');

Route::middleware('auth.or.guest')->group(function () {
    Route::get('/dashboard', DashboardRealisasiController::class)
        ->middleware('menu-akses:dashboard')
        ->name('dashboard.index');
    Route::get('/rincian-realisasi', RincianRealisasiController::class)
        ->middleware('menu-akses:rincian')
        ->name('rincian.index');
    Route::get('/analisis-tren', AnalisisTrenController::class)
        ->middleware('menu-akses:analisis')
        ->name('analisis.index');
    Route::get('/dashboard/perjalanan-dinas', PerjalananDinasDashboardController::class)
        ->middleware('menu-akses:dashpd')
        ->name('dashboard.perjalanan.index');
    Route::get('/dashboard/spj-pengawasan', [SpjDashboardController::class, 'index'])
        ->middleware('menu-akses:dashspj')
        ->name('dashboard.spj.index');
    Route::post('/dashboard/spj-pengawasan/{npd}/verifikasi', [SpjDashboardController::class, 'verify'])
        ->middleware(['menu-akses:dashspj', 'role:superadmin,verifikator'])
        ->name('dashboard.spj.verify');
    Route::get('/inventarisasi-spj', [InventarisasiSpjController::class, 'index'])
        ->middleware('menu-akses:invspj')->name('inventarisasi-spj.index');
    Route::get('/tunjangan-keluarga/dashboard', [TunjanganKeluargaController::class, 'dashboard'])
        ->middleware('menu-akses:dash-tk')->name('tunjangan.dashboard');
    Route::get('/tunjangan-keluarga/monitoring', [TunjanganKeluargaController::class, 'monitoring'])
        ->middleware('menu-akses:tk-monitor')->name('tunjangan.monitoring');

    // Semua role yang login, kecuali "layanan" (layanan tidak login).
    Route::middleware('role:superadmin,bendahara_pengeluaran,pptk,bpp,verifikator,sekretaris,kasubbag,inspektur,inspektur_pembantu,perencanaan')->group(function () {
        Route::get('/surat-perintah', [SuratPerintahController::class, 'index'])->name('surat-perintah.index');
        Route::get('/surat-perintah/create', [SuratPerintahController::class, 'create'])->name('surat-perintah.create');
        Route::post('/surat-perintah', [SuratPerintahController::class, 'store'])->name('surat-perintah.store');
        Route::get('/surat-perintah/export-pdf', [SuratPerintahController::class, 'exportPdf'])->name('surat-perintah.export-pdf');
        Route::get('/surat-perintah/{suratPerintah}/file', [SuratPerintahController::class, 'downloadFile'])->name('surat-perintah.file');

        Route::get('/profil', [ProfilController::class, 'show'])->name('profil.show');
        Route::put('/profil', [ProfilController::class, 'update'])->name('profil.update');
    });

    // Manajemen Users & Pelimpahan: khusus superadmin.
    Route::middleware('role:superadmin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/username', [UserController::class, 'updateUsername'])->name('users.username.update');
        Route::patch('/users/{user}/toggle-aktif', [UserController::class, 'toggleAktif'])->name('users.toggle-aktif');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/pelimpahan', [PelimpahanController::class, 'index'])->name('pelimpahan.index');
        Route::post('/pelimpahan/opd', [PelimpahanController::class, 'updateOpd'])->name('pelimpahan.opd.update');
        Route::post('/pelimpahan/kpa', [PelimpahanController::class, 'storeKpa'])->name('pelimpahan.kpa.store');
        Route::put('/pelimpahan/kpa/{kpa}', [PelimpahanController::class, 'updateKpa'])->name('pelimpahan.kpa.update');
        Route::patch('/pelimpahan/kpa/{kpa}/toggle-aktif', [PelimpahanController::class, 'toggleKpaAktif'])->name('pelimpahan.kpa.toggle-aktif');
        Route::post('/pelimpahan/pptk', [PelimpahanController::class, 'storePptk'])->name('pelimpahan.pptk.store');
        Route::patch('/pelimpahan/pptk/{kpaPptk}/toggle-aktif', [PelimpahanController::class, 'togglePptk'])->name('pelimpahan.pptk.toggle-aktif');
        Route::post('/pelimpahan/sub-kegiatan', [PelimpahanController::class, 'setSubKegiatan'])->name('pelimpahan.sub-kegiatan.set');

        Route::get('/manajemen-data/import/npd-historis', [NpdHistorisImportController::class, 'create'])->name('manajemen-data.import.npd-historis.create');
        Route::get('/manajemen-data/import/npd-historis/template', [NpdHistorisImportController::class, 'template'])->name('manajemen-data.import.npd-historis.template');
        Route::post('/manajemen-data/import/npd-historis', [NpdHistorisImportController::class, 'store'])->name('manajemen-data.import.npd-historis.store');
        Route::get('/manajemen-data/import/npd-historis/{import}/preview', [NpdHistorisImportController::class, 'preview'])->name('manajemen-data.import.npd-historis.preview');
        Route::post('/manajemen-data/import/npd-historis/{import}/confirm', [NpdHistorisImportController::class, 'confirm'])->name('manajemen-data.import.npd-historis.confirm');
        Route::get('/manajemen-data/import/npd-historis/{import}/report/{mode}', [NpdHistorisImportController::class, 'report'])->name('manajemen-data.import.npd-historis.report');

        Route::get('/tunjangan-keluarga/import', [TunjanganKeluargaImportController::class, 'create'])->name('tunjangan.import.create');
        Route::post('/tunjangan-keluarga/import', [TunjanganKeluargaImportController::class, 'store'])->name('tunjangan.import.store');
        Route::get('/tunjangan-keluarga/import/{import}', [TunjanganKeluargaImportController::class, 'preview'])->name('tunjangan.import.preview');
        Route::post('/tunjangan-keluarga/import/{import}/confirm', [TunjanganKeluargaImportController::class, 'confirm'])->name('tunjangan.import.confirm');
    });

    // Hanya PPTK dan superadmin boleh mengubah / menghapus data SP, toggle Monitoring, & ubah Pengajuan.
    Route::middleware('role:pptk,superadmin')->group(function () {
        Route::get('/surat-perintah/{suratPerintah}/edit', [SuratPerintahController::class, 'edit'])->name('surat-perintah.edit');
        Route::put('/surat-perintah/{suratPerintah}', [SuratPerintahController::class, 'update'])->name('surat-perintah.update');
        Route::delete('/surat-perintah/{suratPerintah}', [SuratPerintahController::class, 'destroy'])->name('surat-perintah.destroy');
        Route::patch('/surat-perintah/{suratPerintah}/toggle-pantau', [SuratPerintahController::class, 'togglePantau'])->name('surat-perintah.toggle-pantau');
        Route::patch('/surat-perintah/{suratPerintah}/pengajuan', [SuratPerintahController::class, 'updatePengajuan'])->name('surat-perintah.pengajuan');
    });

    // Edit Pemberitahuan dari Tim Keuangan (Monitoring SP): hanya 4 role ini.
    Route::middleware('role:superadmin,pptk,bpp,verifikator')->group(function () {
        Route::post('/pengumuman', [PengumumanController::class, 'store'])->name('pengumuman.store');
    });

    // Hanya superadmin dan Inspektur boleh melihat log aktivitas (audit trail).
    Route::middleware('role:superadmin,inspektur')->group(function () {
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
    });

    // Monitoring seluruh NPD: superadmin, Bendahara Pengeluaran, dan PPTK.
    Route::middleware('role:superadmin,bendahara_pengeluaran,pptk')->group(function () {
        Route::get('/npd', [NpdController::class, 'index'])->name('npd.index');
    });

    // Pembuatan NPD: hanya superadmin dan PPTK.
    Route::middleware('role:superadmin,pptk')->group(function () {
        Route::get('/npd/create', [NpdController::class, 'create'])->name('npd.create');
        Route::get('/npd/bj/create', [NpdBjController::class, 'create'])->name('npd.bj.create');
        Route::post('/npd/bj', [NpdBjController::class, 'store'])->name('npd.bj.store');
        Route::get('/npd/bj/{npd}/edit', [NpdBjController::class, 'edit'])->name('npd.bj.edit');
        Route::put('/npd/bj/{npd}', [NpdBjController::class, 'update'])->name('npd.bj.update');
        Route::get('/npd/pd/create', [NpdPdController::class, 'create'])->name('npd.pd.create');
        Route::post('/npd/pd', [NpdPdController::class, 'store'])->name('npd.pd.store');
        Route::get('/npd/pd/{npd}/edit', [NpdPdController::class, 'edit'])->name('npd.pd.edit');
        Route::put('/npd/pd/{npd}', [NpdPdController::class, 'update'])->name('npd.pd.update');
        Route::get('/npd/ns/create', [NpdNarasumberController::class, 'create'])->name('npd.ns.create');
        Route::post('/npd/ns', [NpdNarasumberController::class, 'store'])->name('npd.ns.store');
        Route::get('/npd/ns/{npd}/edit', [NpdNarasumberController::class, 'edit'])->name('npd.ns.edit');
        Route::put('/npd/ns/{npd}', [NpdNarasumberController::class, 'update'])->name('npd.ns.update');
        Route::get('/npd/kd/create', [NpdKontribusiDiklatController::class, 'create'])->name('npd.kd.create');
        Route::post('/npd/kd', [NpdKontribusiDiklatController::class, 'store'])->name('npd.kd.store');
        Route::get('/npd/kd/{npd}/edit', [NpdKontribusiDiklatController::class, 'edit'])->name('npd.kd.edit');
        Route::put('/npd/kd/{npd}', [NpdKontribusiDiklatController::class, 'update'])->name('npd.kd.update');
        Route::get('/npd/tr/create', [NpdTransportController::class, 'create'])->name('npd.tr.create');
        Route::post('/npd/tr', [NpdTransportController::class, 'store'])->name('npd.tr.store');
        Route::get('/npd/tr/{npd}/edit', [NpdTransportController::class, 'edit'])->name('npd.tr.edit');
        Route::put('/npd/tr/{npd}', [NpdTransportController::class, 'update'])->name('npd.tr.update');
        Route::delete('/npd/{npd}', [NpdController::class, 'destroy'])->name('npd.destroy');
    });

    // Antrean Persetujuan NPD: BPP. Port dari getNPDuntukBPP di gas-lama/CodeRevisi.gs.
    Route::middleware('role:bpp,superadmin')->group(function () {
        Route::get('/npd/persetujuan', [NpdController::class, 'persetujuan'])->name('npd.persetujuan');
    });

    // Antrean Verifikasi NPD: Verifikator. Port dari getNPDuntukVerifikator di gas-lama/CodeRevisi.gs.
    Route::middleware('role:verifikator,superadmin')->group(function () {
        Route::get('/npd/verifikasi', [NpdController::class, 'verifikasi'])->name('npd.verifikasi');
    });

    // Detail dan cetak: semua pelaku workflow serta Bendahara Pengeluaran sebagai pemantau.
    Route::middleware('role:superadmin,bendahara_pengeluaran,pptk,bpp,verifikator')->group(function () {
        Route::get('/npd/{npd}', [NpdController::class, 'show'])->name('npd.show');
        Route::get('/npd/{npd}/cetak-npd', [NpdController::class, 'cetakNpd'])->name('npd.cetak-npd');
        Route::get('/npd/{npd}/cetak-lampiran', [NpdController::class, 'cetakLampiran'])->name('npd.cetak-lampiran');
        Route::get('/npd/{npd}/cetak-daftar', [NpdController::class, 'cetakDaftar'])->name('npd.cetak-daftar');
        Route::get('/npd/{npd}/cetak-spd', [NpdController::class, 'cetakSpd'])->name('npd.cetak-spd');
        Route::get('/npd/{npd}/cetak-daftar-nara', [NpdController::class, 'cetakDaftarNarasumber'])->name('npd.cetak-daftar-nara');
        Route::get('/npd/{npd}/cetak-daftar-kd', [NpdController::class, 'cetakDaftarKontribusiDiklat'])->name('npd.cetak-daftar-kd');
        Route::post('/npd/{npd}/arsip-spj', [InventarisasiSpjController::class, 'store'])->name('npd.arsip-spj.store');
    });

    Route::middleware('role:superadmin,bendahara_pengeluaran')->group(function () {
        Route::post('/tunjangan-keluarga/pengajuan/{pengajuan}/proses', [TunjanganKeluargaController::class, 'proses'])->name('tunjangan.pengajuan.proses');
        Route::get('/tunjangan-keluarga/lampiran/{lampiran}', [TunjanganKeluargaController::class, 'download'])->name('tunjangan.lampiran.download');
    });

    // Transisi workflow tidak diberikan kepada Bendahara Pengeluaran.
    Route::middleware('role:superadmin,pptk,bpp,verifikator')->group(function () {
        Route::post('/npd/{npd}/transisi', [NpdController::class, 'transisi'])->name('npd.transisi');
    });

    // Data SPM: khusus superadmin dan Bendahara Pengeluaran.
    Route::middleware('role:superadmin,bendahara_pengeluaran')->group(function () {
        Route::get('/spm/up-gu', [SpmController::class, 'indexUpGu'])->name('spm.up-gu.index');
        Route::get('/spm/up-gu/create', [SpmController::class, 'createUpGu'])->name('spm.up-gu.create');
        Route::post('/spm/up-gu', [SpmController::class, 'storeUpGu'])->name('spm.up-gu.store');
        Route::get('/spm/up-gu/{spm}/edit', [SpmController::class, 'editUpGu'])->name('spm.up-gu.edit');
        Route::put('/spm/up-gu/{spm}', [SpmController::class, 'updateUpGu'])->name('spm.up-gu.update');

        Route::get('/spm/ls', [SpmController::class, 'indexLs'])->name('spm.ls.index');
        Route::get('/spm/ls/create', [SpmController::class, 'createLs'])->name('spm.ls.create');
        Route::post('/spm/ls', [SpmController::class, 'storeLs'])->name('spm.ls.store');
        Route::get('/spm/ls/{spm}/edit', [SpmController::class, 'editLs'])->name('spm.ls.edit');
        Route::put('/spm/ls/{spm}', [SpmController::class, 'updateLs'])->name('spm.ls.update');

        Route::delete('/spm/{spm}', [SpmController::class, 'destroy'])->name('spm.destroy');
    });

    // Manajemen Data (export + import): khusus superadmin dan Bendahara Pengeluaran.
    Route::middleware('role:superadmin,bendahara_pengeluaran')->group(function () {
        Route::get('/manajemen-data', [ManajemenDataController::class, 'index'])->name('manajemen-data.index');
        Route::get('/manajemen-data/export/{jenis}', [ManajemenDataController::class, 'export'])
            ->whereIn('jenis', ['master-anggaran', 'npd', 'spm-up-gu', 'spm-ls', 'pegawai', 'vendor', 'tagging', 'pejabat', 'rak-bulanan'])
            ->name('manajemen-data.export');

        // Import Pagu/Master Anggaran: upload -> staging (preview/dry-run) -> konfirmasi simpan.
        Route::get('/manajemen-data/import/master-anggaran', [MasterAnggaranImportController::class, 'create'])->name('manajemen-data.import.master-anggaran.create');
        Route::post('/manajemen-data/import/master-anggaran', [MasterAnggaranImportController::class, 'store'])->name('manajemen-data.import.master-anggaran.store');
        Route::get('/manajemen-data/import/master-anggaran/{import}/preview', [MasterAnggaranImportController::class, 'preview'])->name('manajemen-data.import.master-anggaran.preview');
        Route::post('/manajemen-data/import/master-anggaran/{import}/konfirmasi', [MasterAnggaranImportController::class, 'konfirmasi'])->name('manajemen-data.import.master-anggaran.konfirmasi');
        Route::delete('/manajemen-data/import/master-anggaran/{import}', [MasterAnggaranImportController::class, 'batalkan'])->name('manajemen-data.import.master-anggaran.batalkan');

        // Import SPM UP/GU dan LS: upload -> staging (preview/dry-run) -> konfirmasi simpan.
        Route::get('/manajemen-data/import/spm/{jenis}', [SpmImportController::class, 'create'])
            ->whereIn('jenis', ['spm-up-gu', 'spm-ls'])
            ->name('manajemen-data.import.spm.create');
        Route::post('/manajemen-data/import/spm/{jenis}', [SpmImportController::class, 'store'])
            ->whereIn('jenis', ['spm-up-gu', 'spm-ls'])
            ->name('manajemen-data.import.spm.store');
        Route::get('/manajemen-data/import/spm/{import}/preview', [SpmImportController::class, 'preview'])->name('manajemen-data.import.spm.preview');
        Route::post('/manajemen-data/import/spm/{import}/konfirmasi', [SpmImportController::class, 'konfirmasi'])->name('manajemen-data.import.spm.konfirmasi');
        Route::delete('/manajemen-data/import/spm/{import}', [SpmImportController::class, 'batalkan'])->name('manajemen-data.import.spm.batalkan');

        // Import RAK Bulanan: upload (format lebar Jan-Des) -> staging (preview/dry-run) -> konfirmasi simpan.
        Route::get('/manajemen-data/import/rak-bulanan', [RakBulananImportController::class, 'create'])->name('manajemen-data.import.rak-bulanan.create');
        Route::post('/manajemen-data/import/rak-bulanan', [RakBulananImportController::class, 'store'])->name('manajemen-data.import.rak-bulanan.store');
        Route::get('/manajemen-data/import/rak-bulanan/{import}/preview', [RakBulananImportController::class, 'preview'])->name('manajemen-data.import.rak-bulanan.preview');
        Route::post('/manajemen-data/import/rak-bulanan/{import}/konfirmasi', [RakBulananImportController::class, 'konfirmasi'])->name('manajemen-data.import.rak-bulanan.konfirmasi');
        Route::delete('/manajemen-data/import/rak-bulanan/{import}', [RakBulananImportController::class, 'batalkan'])->name('manajemen-data.import.rak-bulanan.batalkan');
    });

});
