<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Menambahkan worksheet "Petunjuk Pengisian" ke workbook yang sama dengan
 * sheet data.
 *
 * Sengaja dibuat lewat Spreadsheet::createSheet() di dalam event AfterSheet,
 * BUKAN lewat WithMultipleSheets: kelas export yang sudah ada tetap berperan
 * sebagai sumber sheet data (FromQuery/FromArray + WithMapping) tanpa perlu
 * dibongkar jadi kelas-per-sheet, dan pemanggil lama (controller, test) tetap
 * bisa memakai headings()/map()/jumlahBaris() seperti biasa.
 *
 * Sheet data tetap berada di indeks 0 dan tetap menjadi sheet aktif, sehingga
 * seluruh importer - yang hanya membaca sheet pertama - tidak terpengaruh.
 */
trait MenulisSheetPetunjuk
{
    private const JUDUL_SHEET_PETUNJUK = 'Petunjuk Pengisian';

    private const KOLOM_PETUNJUK = ['Kolom', 'Wajib', 'Format', 'Penjelasan', 'Contoh Isi'];

    /** Lebar kolom sheet petunjuk (A..E). */
    private const LEBAR_PETUNJUK = ['A' => 26, 'B' => 10, 'C' => 22, 'D' => 72, 'E' => 30];

    /**
     * @param  Worksheet  $sheetData  sheet data yang baru selesai ditulis
     * @param  string  $judulSheetData  judul untuk sheet data
     */
    protected function tulisSheetPetunjuk(Worksheet $sheetData, string $judulSheetData = 'Data'): void
    {
        if (! $this instanceof PunyaPetunjukKolom) {
            return;
        }

        $spreadsheet = $sheetData->getParent();

        if ($spreadsheet === null) {
            return;
        }

        $sheetData->setTitle($judulSheetData);

        // Idempoten: kalau sheet petunjuk sudah ada (mis. event terpanggil
        // dua kali), jangan digandakan.
        if ($spreadsheet->sheetNameExists(self::JUDUL_SHEET_PETUNJUK)) {
            return;
        }

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(self::JUDUL_SHEET_PETUNJUK);

        foreach (self::LEBAR_PETUNJUK as $kolom => $lebar) {
            $sheet->getColumnDimension($kolom)->setWidth($lebar);
        }

        $sheet->setCellValue('A1', 'Petunjuk Pengisian');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:E1')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF15314A');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->setCellValue('A2', $this->petunjukCatatan());
        $sheet->mergeCells('A2:E2');
        $sheet->getStyle('A2')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getRowDimension(2)->setRowHeight(58);

        $barisHeader = 4;
        $sheet->fromArray(self::KOLOM_PETUNJUK, null, 'A'.$barisHeader);
        $sheet->getStyle("A{$barisHeader}:E{$barisHeader}")->getFont()->setBold(true);
        $sheet->getStyle("A{$barisHeader}:E{$barisHeader}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        $baris = $barisHeader + 1;

        foreach ($this->petunjukKolom() as $petunjuk) {
            $sheet->fromArray(array_values($petunjuk), null, 'A'.$baris);
            $sheet->getStyle("A{$baris}")->getFont()->setBold(true);
            $baris++;
        }

        $barisTerakhir = $baris - 1;

        if ($barisTerakhir >= $barisHeader) {
            $sheet->getStyle("A{$barisHeader}:E{$barisTerakhir}")->getAlignment()
                ->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$barisHeader}:E{$barisTerakhir}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFBFCBD6');
            $sheet->getStyle("B{$barisHeader}:C{$barisTerakhir}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->freezePane('A'.($barisHeader + 1));
        }

        // Sheet data tetap yang pertama dibuka pengguna.
        $spreadsheet->setActiveSheetIndex(0);
    }
}
