<?php

namespace App\Exports;

use App\Services\NpdHistorisImportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class NpdHistorisTemplateExport implements FromArray, ShouldAutoSize, WithEvents
{
    public function array(): array
    {
        $tahun = (int) config('anggaran.tahun_aktif');

        return [
            [NpdHistorisImportService::FORMAT_MARKER],
            ["TAHUN ANGGARAN {$tahun}: isi satu dokumen per baris dan gunakan Tanggal NPD tahun {$tahun}. Tanggal di luar {$tahun} ditolak. Status kosong menjadi Selesai. Jenis: Barang/Jasa, Perjalanan Dinas, Transport, Narasumber, atau Kontribusi Diklat."],
            ["PPN termasuk dalam bruto dan, sesuai aturan Lampiran yang ada, mengurangi nilai transfer bersama PPh; PPN tidak mengurangi realisasi bruto. Tahun Anggaran {$tahun} dan Bulan Realisasi diturunkan dari Tanggal NPD."],
            NpdHistorisImportService::HEADERS,
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $sheet->mergeCells('A1:P1')->mergeCells('A2:P2')->mergeCells('A3:P3');
            $sheet->getStyle('A1:P1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle('A1:P1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF15314A');
            $sheet->getStyle('A2:P3')->getAlignment()->setWrapText(true);
            $sheet->getStyle('A4:P4')->getFont()->setBold(true);
            $sheet->getStyle('A4:P4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->getRowDimension(2)->setRowHeight(36);
            $sheet->getRowDimension(3)->setRowHeight(36);
            $sheet->freezePane('A5');
            $sheet->setAutoFilter('A4:P4');
            $sheet->getStyle('A5:A5004')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            $sheet->getStyle('I5:I5004')->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('K5:N5004')->getNumberFormat()->setFormatCode('#,##0.00');
        }];
    }
}
