<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Header saja (baris 1) karena parser TunjanganKeluargaImportService::preview()
 * membaca baris pertama file sebagai header apa adanya (RawSheetImport startRow 1) —
 * tidak ada baris judul/instruksi tambahan di atasnya seperti pada template NPD Historis.
 */
class TunjanganKeluargaTemplateExport implements FromArray, ShouldAutoSize, WithEvents
{
    public const HEADERS = [
        'Nama Pegawai', 'NIP', 'Nama Pasangan', 'Tanggal Lahir Pasangan', 'Status Pasangan',
        'Nama Anak 1', 'Tanggal Lahir Anak 1', 'Status Anak 1', 'Keterangan Anak 1',
        'Nama Anak 2', 'Tanggal Lahir Anak 2', 'Status Anak 2', 'Keterangan Anak 2',
    ];

    public function array(): array
    {
        return [self::HEADERS];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastCol = 'M';
            $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastCol}1");
        }];
    }
}
