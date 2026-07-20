<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Dasar untuk export Manajemen Data satu-sheet. query() dibaca per-chunk
 * (WithChunkReading) supaya tabel besar (mis. NPD, SPM) tidak dimuat
 * sekaligus ke memori. jumlahBaris() dipakai controller untuk audit log
 * sebelum file di-stream ke browser.
 */
abstract class DataManagementExport implements CountsRows, FromQuery, ShouldAutoSize, WithChunkReading, WithHeadings, WithMapping
{
    use Exportable;

    public function chunkSize(): int
    {
        return 500;
    }

    public function jumlahBaris(): int
    {
        return $this->query()->count();
    }
}
