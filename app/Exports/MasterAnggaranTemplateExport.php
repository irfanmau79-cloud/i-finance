<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Header saja - kolom sama persis dengan MasterAnggaranExport/MasterAnggaranUploadImport. */
class MasterAnggaranTemplateExport implements FromArray, ShouldAutoSize, WithEvents
{
    public const HEADERS = ['Tahun Anggaran', 'Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging', 'Pagu', 'Aktif'];

    public function array(): array
    {
        return [self::HEADERS];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastCol = 'I';
            $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastCol}1");
        }];
    }
}
