<?php

use App\Helpers\Terbilang;

if (! function_exists('terbilang')) {
    function terbilang(float|int|string $angka): string
    {
        return Terbilang::rupiah($angka);
    }
}
