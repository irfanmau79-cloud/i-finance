<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class RawSheetImport implements ToCollection, WithStartRow
{
    public Collection $rows;

    public function __construct(private readonly int $startRowNumber)
    {
        $this->rows = new Collection();
    }

    public function startRow(): int
    {
        return $this->startRowNumber;
    }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
