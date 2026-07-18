<?php

use App\Http\Controllers\SuratPerintahController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/surat-perintah', [SuratPerintahController::class, 'index'])->name('surat-perintah.index');
Route::get('/surat-perintah/create', [SuratPerintahController::class, 'create'])->name('surat-perintah.create');
Route::get('/surat-perintah/export-pdf', [SuratPerintahController::class, 'exportPdf'])->name('surat-perintah.export-pdf');
Route::post('/surat-perintah', [SuratPerintahController::class, 'store'])->name('surat-perintah.store');
Route::get('/surat-perintah/{suratPerintah}/edit', [SuratPerintahController::class, 'edit'])->name('surat-perintah.edit');
Route::put('/surat-perintah/{suratPerintah}', [SuratPerintahController::class, 'update'])->name('surat-perintah.update');
Route::delete('/surat-perintah/{suratPerintah}', [SuratPerintahController::class, 'destroy'])->name('surat-perintah.destroy');
