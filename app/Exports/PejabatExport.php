<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Gabungan Pejabat OPD (PA & Bendahara Pengeluaran) + KPA/BPP per KEU dalam satu workbook, 2 sheet. */
class PejabatExport implements CountsRows, WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            'Pejabat OPD' => new PejabatOpdSheetExport(),
            'KPA & BPP' => new KpaSheetExport(),
        ];
    }

    public function jumlahBaris(): int
    {
        return (new PejabatOpdSheetExport())->jumlahBaris() + (new KpaSheetExport())->jumlahBaris();
    }
}
