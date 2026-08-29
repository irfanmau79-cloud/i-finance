<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Header saja - kolom sama persis dengan SpmUpGuExport/SpmUploadImport.
 *
 * SPM UP/GU/TU adalah isi ulang kas: tidak mengurangi pagu dan tidak masuk
 * realisasi (lihat MODEL REALISASI di CLAUDE.md), sehingga hanya identitas
 * dokumen, nominal, dan uraiannya yang relevan. Kolom PPN/PPh dan Penerima
 * tidak lagi dibawa berkas ini - importer UP/GU tidak menulis kolom-kolom
 * itu sama sekali, jadi nilai yang sudah diisi lewat form tidak terhapus.
 */
class SpmUpGuTemplateExport implements FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents
{
    use MenulisSheetPetunjuk;

    public const HEADERS = [
        'Tanggal SPM', 'Nomor SPM', 'Tanggal SP2D', 'Nomor SP2D', 'Nominal', 'Uraian',
    ];

    public const CATATAN = 'Satu baris = satu dokumen SPM UP/GU/TU. Dokumen dikenali dari kombinasi Tanggal SPM + Nomor SPM: kombinasi yang sudah ada akan DIPERBARUI, yang belum ada dibuat baru. SPM UP/GU/TU adalah pengisian ulang kas, jadi TIDAK terikat mata anggaran, TIDAK mengurangi pagu, dan TIDAK dihitung sebagai realisasi - ia hanya masuk ke angka kas keluar (SP2D). PPN/PPh dan Penerima tidak dibawa berkas ini; nilai yang sudah diisi lewat form SPM tidak akan tertimpa oleh import.';

    public const PETUNJUK = [
        ['Tanggal SPM', 'Ya', 'Tanggal YYYY-MM-DD', 'Tanggal dokumen SPM. Bersama Nomor SPM menjadi identitas dokumen. Kosong atau tidak valid berarti baris ditolak.', '2026-07-01'],
        ['Nomor SPM', 'Ya', 'Teks, maks 100 karakter', 'Nomor dokumen SPM. Nomor + tanggal yang sama muncul dua kali dalam satu berkas dianggap duplikat dan ditolak.', '001/SPM-UP/2026'],
        ['Tanggal SP2D', 'Tidak', 'Tanggal YYYY-MM-DD', 'Tanggal SP2D terbit. Kosongkan bila SP2D belum turun.', '2026-07-02'],
        ['Nomor SP2D', 'Tidak', 'Teks, maks 100 karakter', 'Nomor SP2D. Kosongkan bila SP2D belum turun.', 'SP2D-0012/2026'],
        ['Nominal', 'Ya', 'Angka, tanpa Rp', 'Nilai dokumen SPM. Harus lebih besar dari 0. Isi angka saja, tanpa Rp atau huruf.', '3000000'],
        ['Uraian', 'Tidak', 'Teks', 'Keterangan singkat dokumen.', 'Pengisian UP triwulan III'],
    ];

    public function petunjukCatatan(): string
    {
        return self::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return self::PETUNJUK;
    }

    public function array(): array
    {
        return [self::HEADERS];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastCol = 'F';
            $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastCol}1");

            // Pra-format supaya pengetikan manual tidak berubah tipe.
            $sheet->getStyle('A2:A1000')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            $sheet->getStyle('C2:C1000')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            $sheet->getStyle('E2:E1000')->getNumberFormat()->setFormatCode('#,##0.00');

            $this->tulisSheetPetunjuk($sheet, 'Data');
        }];
    }
}
