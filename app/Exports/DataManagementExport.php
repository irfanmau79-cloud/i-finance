<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Dasar untuk export Manajemen Data satu-sheet. query() dibaca per-chunk
 * (WithChunkReading) supaya tabel besar (mis. NPD, SPM) tidak dimuat
 * sekaligus ke memori. jumlahBaris() dipakai controller untuk audit log
 * sebelum file di-stream ke browser.
 *
 * Subclass yang mengimplementasikan PunyaPetunjukKolom otomatis mendapat
 * worksheet kedua berisi penjelasan tiap kolom (lihat MenulisSheetPetunjuk).
 * Subclass yang perlu menata sheet datanya sendiri cukup meng-override
 * siapkanSheet(); registerEvents() JANGAN di-override supaya sheet petunjuk
 * tidak hilang.
 */
abstract class DataManagementExport implements CountsRows, FromQuery, ShouldAutoSize, WithChunkReading, WithEvents, WithHeadings, WithMapping
{
    use Exportable;
    use MenulisSheetPetunjuk;

    public function chunkSize(): int
    {
        return 500;
    }

    public function jumlahBaris(): int
    {
        return $this->query()->count();
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $this->siapkanSheet($event);
            $this->tulisSheetPetunjuk($event->sheet->getDelegate(), $this->judulSheetData());
        }];
    }

    /** Hook penataan sheet data (freeze pane, format angka, dsb). */
    protected function siapkanSheet(AfterSheet $event): void {}

    protected function judulSheetData(): string
    {
        return 'Data';
    }
}
