<?php

namespace App\Imports;

use App\Imports\Concerns\MembacaSheetPertama;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Pembaca sheet pertama untuk berkas rincian Perjalanan Dinas & SPJ
 * Perjalanan Dinas. Keduanya cuma butuh baris mentah ber-key header, dan
 * kolomnya ditafsirkan masing-masing di model importnya.
 */
class RincianUploadImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    use MembacaSheetPertama;

    public Collection $rows;

    public function __construct()
    {
        $this->rows = new Collection;
    }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
