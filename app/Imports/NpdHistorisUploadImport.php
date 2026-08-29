<?php

namespace App\Imports;

use App\Imports\Concerns\MembacaSheetPertama;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class NpdHistorisUploadImport implements ToCollection, WithMultipleSheets
{
    use MembacaSheetPertama;

    public Collection $rows;

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
