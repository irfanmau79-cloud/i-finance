<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Header saja - kolom sama persis dengan SpmUpGuExport/SpmUploadImport. */
class SpmUpGuTemplateExport implements FromArray, ShouldAutoSize, WithEvents
{
    public const HEADERS = [
        'Nomor Dokumen', 'Tanggal Dokumen', 'Nomor SP2D', 'Tanggal SP2D', 'Nominal',
        'PPN', 'PPh 1', 'Jenis PPh 1', 'PPh 2', 'Jenis PPh 2', 'Penerima', 'Uraian',
    ];

    public function array(): array
    {
        return [self::HEADERS];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastCol = 'L';
            $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastCol}1");
        }];
    }
}
