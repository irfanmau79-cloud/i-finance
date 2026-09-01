<?php

use App\Helpers\GuestSession;
use App\Helpers\Terbilang;

if (! function_exists('terbilang')) {
    function terbilang(float|int|string $angka): string
    {
        return Terbilang::rupiah($angka);
    }
}

if (! function_exists('fmt_rupiah')) {
    /** Format angka ke "1.234.567,89" (tanpa "Rp"), sama seperti fmtRupiah() di gas-lama/Code.gs. */
    function fmt_rupiah(float|int|string|null $angka): string
    {
        return number_format((float) $angka, 2, ',', '.');
    }
}

if (! function_exists('boleh_ubah')) {
    /**
     * False untuk role baca-saja (lihat config('akses.role_baca_saja')).
     *
     * Dipakai di tampilan untuk menyembunyikan tombol pengubah data pada
     * halaman yang memang boleh DIBACA role tersebut - supaya tidak ada
     * tombol yang terlihat tapi berujung 403. Ini pelengkap, BUKAN pengganti
     * penjagaan di route: middleware 'baca-saja' yang menegakkannya.
     */
    function boleh_ubah(): bool
    {
        return ! in_array(
            GuestSession::role(),
            config('akses.role_baca_saja', []),
            true
        );
    }
}
