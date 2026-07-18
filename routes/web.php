<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NpdBjController;
use App\Http\Controllers\NpdController;
use App\Http\Controllers\SuratPerintahController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Publik, tanpa login — dipakai role "layanan" untuk mengisi orderan SP dari luar.
Route::get('/sp/input', [SuratPerintahController::class, 'publicCreate'])->name('sp.input.create');
Route::post('/sp/input', [SuratPerintahController::class, 'publicStore'])->name('sp.input.store');

Route::middleware('auth')->group(function () {
    // Semua role yang login, kecuali "layanan" (layanan tidak login).
    Route::middleware('role:bendahara,pptk,bpp,verifikator,sekretaris,kasubbag,inspektur,inspektur_pembantu,perencanaan')->group(function () {
        Route::get('/surat-perintah', [SuratPerintahController::class, 'index'])->name('surat-perintah.index');
        Route::get('/surat-perintah/create', [SuratPerintahController::class, 'create'])->name('surat-perintah.create');
        Route::post('/surat-perintah', [SuratPerintahController::class, 'store'])->name('surat-perintah.store');
        Route::get('/surat-perintah/export-pdf', [SuratPerintahController::class, 'exportPdf'])->name('surat-perintah.export-pdf');
    });

    // Hanya PPTK dan Bendahara boleh mengubah / menghapus data SP.
    Route::middleware('role:pptk,bendahara')->group(function () {
        Route::get('/surat-perintah/{suratPerintah}/edit', [SuratPerintahController::class, 'edit'])->name('surat-perintah.edit');
        Route::put('/surat-perintah/{suratPerintah}', [SuratPerintahController::class, 'update'])->name('surat-perintah.update');
        Route::delete('/surat-perintah/{suratPerintah}', [SuratPerintahController::class, 'destroy'])->name('surat-perintah.destroy');
    });

    // Hanya Bendahara dan Inspektur boleh melihat log aktivitas (audit trail).
    Route::middleware('role:bendahara,inspektur')->group(function () {
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
    });

    // Pembuatan NPD: hanya Bendahara dan PPTK.
    Route::middleware('role:bendahara,pptk')->group(function () {
        Route::get('/npd', [NpdController::class, 'index'])->name('npd.index');
        Route::get('/npd/bj/create', [NpdBjController::class, 'create'])->name('npd.bj.create');
        Route::post('/npd/bj', [NpdBjController::class, 'store'])->name('npd.bj.store');
    });

    // Detail & transisi status NPD: semua peran yang terlibat di alur workflow.
    Route::middleware('role:bendahara,pptk,bpp,verifikator')->group(function () {
        Route::get('/npd/{npd}', [NpdController::class, 'show'])->name('npd.show');
        Route::post('/npd/{npd}/transisi', [NpdController::class, 'transisi'])->name('npd.transisi');
    });
});
