<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;

/** Header saja - kolom sama persis dengan RekapPotensiExport. */
class RekapPotensiTemplateExport implements FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents
{
    use MenulisSheetPetunjuk;

    public const CATATAN = 'Rekap kelebihan pembayaran per pegawai yang masih harus dikembalikan, ditampilkan di menu Data Gaji dan Tunjangan > Rekap Potensi Pengembalian. Baris dikenali dari NIP: NIP yang sudah ada akan DIPERBARUI, yang belum ada dibuat baru. Kolom Sisa Pengembalian TIDAK ada di berkas ini - nilainya selalu dihitung Potensi dikurangi Setoran.';

    public const PETUNJUK = [
        ['NIP', 'Ya', 'Teks', 'Menjadi identitas baris. Ditulis sebagai TEKS supaya nol di depan tidak hilang; tanda baca dan spasi diabaikan.', '198001012000011001'],
        ['Nama Pegawai', 'Ya', 'Teks', 'Nama pegawai yang bersangkutan.', 'Budi Santoso, S.E.'],
        ['Jabatan', 'Tidak', 'Teks', 'Jabatan pegawai saat rekap disusun.', 'Auditor Ahli Muda'],
        ['Potensi Kelebihan Pembayaran', 'Tidak', 'Angka', 'Taksiran kelebihan yang sudah terlanjur dibayarkan. Boleh ditulis "1.250.000" atau 1250000; sel kosong dihitung 0.', '1250000'],
        ['Setoran', 'Tidak', 'Angka', 'Yang sudah disetor kembali. TIDAK boleh melebihi Potensi - barisnya ditolak karena sisanya akan negatif.', '250000'],
        ['Keterangan', 'Tidak', 'Teks', 'Catatan bebas, mis. nomor bukti setor atau kesepakatan angsuran.', 'Angsuran 1 dari 4'],
    ];

    public function petunjukCatatan(): string
    {
        return self::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return self::PETUNJUK;
    }

    public const HEADERS = ['NIP', 'Nama Pegawai', 'Jabatan', 'Potensi Kelebihan Pembayaran', 'Setoran', 'Keterangan'];

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
