<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Header saja - kolom sama persis dengan VendorExport/VendorUploadImport. */
class VendorTemplateExport implements FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents
{
    use MenulisSheetPetunjuk;

    public const CATATAN = 'Daftar penyedia/vendor yang dipakai sebagai penerima pada NPD Barang/Jasa dan SPM LS. Vendor dikenali dari NAMA: nama yang sudah ada akan DIPERBARUI datanya, yang belum ada dibuat baru.';

    public const PETUNJUK = [
        ['Nama', 'Ya', 'Teks', 'Nama badan usaha. Menjadi identitas baris - nama ganda dalam satu berkas ditolak. Tulis konsisten dengan yang dipakai di dokumen.', 'CV Sumber Rejeki'],
        ['Rekening', 'Tidak', 'Teks', 'Nomor rekening bank untuk transfer. Format sebagai TEKS supaya nol di depan tidak hilang.', '0012345678'],
        ['NPWP', 'Tidak', 'Teks', 'Nomor NPWP vendor. Format sebagai TEKS agar tanda titik dan strip tidak berubah.', '01.234.567.8-901.000'],
        ['Status PKP', 'Tidak', 'Teks', 'Status Pengusaha Kena Pajak, menentukan perlakuan PPN pada dokumen.', 'PKP'],
        ['Jenis Usaha', 'Tidak', 'Teks', 'Bidang usaha vendor.', 'Perdagangan alat tulis'],
        ['Aktif', 'Tidak', 'Ya / Tidak', 'Status vendor. Hanya "Tidak" yang menonaktifkan; sel kosong dianggap Aktif. Vendor non-aktif tidak muncul di pilihan penerima.', 'Ya'],
    ];

    public function petunjukCatatan(): string
    {
        return self::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return self::PETUNJUK;
    }

    public const HEADERS = ['Nama', 'Rekening', 'NPWP', 'Status PKP', 'Jenis Usaha', 'Aktif'];

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

            $this->tulisSheetPetunjuk($sheet, 'Data');
        }];
    }
}
