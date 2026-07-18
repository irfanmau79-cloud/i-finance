<?php

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
