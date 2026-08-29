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
 * Header saja (baris 1) karena parser TunjanganKeluargaImportService::preview()
 * membaca baris pertama file sebagai header apa adanya (RawSheetImport startRow 1) —
 * tidak ada baris judul/instruksi tambahan di atasnya seperti pada template NPD Historis.
 */
class TunjanganKeluargaTemplateExport implements FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents
{
    use MenulisSheetPetunjuk;

    public const CATATAN = 'Data keluarga pegawai untuk keperluan tunjangan. Satu baris per pegawai, dikenali dari NIP - data pegawai yang sudah ada akan DIPERBARUI. Format ini menampung paling banyak dua anak; bila ada lebih, lengkapi sisanya lewat menu Tunjangan Keluarga. Data keluarga ikut terhapus bila pegawainya dihapus.';

    public const PETUNJUK = [
        ['Nama Pegawai', 'Ya', 'Teks', 'Nama pegawai pemilik data keluarga. Hanya referensi - pencocokan memakai NIP.', 'Budi Santoso, S.E.'],
        ['NIP', 'Ya', 'Teks 18 digit', 'NIP pegawai yang bersangkutan. Wajib sudah terdaftar di data Pegawai; NIP yang tidak dikenal akan ditolak, bukan membuat pegawai baru.', '198504102010011005'],
        ['Nama Pasangan', 'Tidak', 'Teks', 'Nama suami/istri. Kosongkan bila tidak ada.', 'Siti Aminah'],
        ['Tanggal Lahir Pasangan', 'Tidak', 'Tanggal YYYY-MM-DD', 'Tanggal lahir pasangan.', '1987-03-21'],
        ['Status Pasangan', 'Tidak', 'Teks', 'Keterangan status pasangan untuk keperluan tunjangan.', 'Istri'],
        ['Nama Anak 1', 'Tidak', 'Teks', 'Nama anak pertama yang ditanggung. Kosongkan bila tidak ada.', 'Rizky Pratama'],
        ['Tanggal Lahir Anak 1', 'Tidak', 'Tanggal YYYY-MM-DD', 'Tanggal lahir anak pertama.', '2012-05-09'],
        ['Status Anak 1', 'Tidak', 'Teks', 'Status anak pertama.', 'Kandung'],
        ['Keterangan Anak 1', 'Tidak', 'Teks', 'Keterangan tambahan, mis. masih sekolah.', 'Masih sekolah'],
        ['Nama Anak 2', 'Tidak', 'Teks', 'Nama anak kedua yang ditanggung. Kosongkan bila tidak ada.', 'Aisyah Putri'],
        ['Tanggal Lahir Anak 2', 'Tidak', 'Tanggal YYYY-MM-DD', 'Tanggal lahir anak kedua.', '2016-11-02'],
        ['Status Anak 2', 'Tidak', 'Teks', 'Status anak kedua.', 'Kandung'],
        ['Keterangan Anak 2', 'Tidak', 'Teks', 'Keterangan tambahan untuk anak kedua.', 'Masih sekolah'],
    ];

    public function petunjukCatatan(): string
    {
        return self::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return self::PETUNJUK;
    }

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

            $this->tulisSheetPetunjuk($sheet, 'Data');
        }];
    }
}
