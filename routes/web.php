<?php

use App\Http\Controllers\SuratPerintahController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/surat-perintah', [SuratPerintahController::class, 'index'])->name('surat-perintah.index');
Route::get('/surat-perintah/create', [SuratPerintahController::class, 'create'])->name('surat-perintah.create');
Route::post('/surat-perintah', [SuratPerintahController::class, 'store'])->name('surat-perintah.store');
