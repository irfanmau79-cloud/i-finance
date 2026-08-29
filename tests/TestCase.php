<?php

namespace Tests;

use App\Helpers\GuestSession;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Lolos gerbang kata sandi Pengguna Layanan.
     *
     * Halaman layanan (Input SP, Monitoring SP, Cetak SPJ, Perubahan
     * Tunjangan Keluarga) tanpa akun, tetapi sejak aplikasi dihosting berada
     * di balik satu kata sandi bersama. Test yang menguji ISI halaman itu
     * memakai jalan pintas ini supaya tidak perlu mengulang POST sandi;
     * gerbangnya sendiri diuji tersendiri di GerbangLayananTest.
     */
    protected function lolosGerbangLayanan(): static
    {
        return $this->withSession([GuestSession::kunciSesi() => true]);
    }
}
