<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Header saja - kolom sama persis dengan SpmLsExport/SpmUploadImport. Satu
 * baris file per kombinasi SPM + mata anggaran; kolom header dokumen
 * (Nomor/Tanggal Dokumen, SP2D, PPN, PPh, Penerima, Uraian) harus diulang
 * sama persis di setiap baris milik SPM yang sama - lihat App\Models\SpmImport.
 */
class SpmLsTemplateExport implements FromArray, ShouldAutoSize, WithEvents
{
    public const HEADERS = [
        'Nomor Dokumen', 'Tanggal Dokumen', 'Nomor SP2D', 'Tanggal SP2D',
        'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging',
        'Nominal', 'PPN', 'PPh 1', 'Jenis PPh 1', 'PPh 2', 'Jenis PPh 2',
        'Penerima', 'Uraian',
    ];

    public function array(): array
    {
        return [self::HEADERS];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastCol = 'P';
            $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastCol}1");
        }];
    }
}
