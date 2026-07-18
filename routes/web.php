<?php

use App\Http\Controllers\SuratPerintahController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/surat-perintah', [SuratPerintahController::class, 'index'])->name('surat-perintah.index');
