<?php

namespace App\Imports;

use App\Imports\Concerns\MembacaSheetPertama;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Baca file upload Manajemen Data > Import Vendor. Header kolom mengikuti
 * persis output VendorExport: Nama, Rekening, NPWP, Status PKP, Jenis
 * Usaha, Aktif - supaya export bisa dipakai langsung sebagai template
 * import. WithHeadingRow men-slug header jadi key array.
 */
class VendorUploadImport implements ToCollection, WithHeadingRow, WithMultipleSheets
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
