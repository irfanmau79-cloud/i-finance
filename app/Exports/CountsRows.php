<?php

namespace App\Exports;

interface CountsRows
{
    /** Jumlah baris yang akan diekspor - dihitung sebelum file di-stream, dipakai untuk audit log. */
    public function jumlahBaris(): int;
}
