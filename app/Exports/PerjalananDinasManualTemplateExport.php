<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PerjalananDinasManualTemplateExport implements FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents
{
    use MenulisSheetPetunjuk;

    public const CATATAN = 'Rincian perjalanan dinas per orang per bulan untuk menambal periode sebelum migrasi - dibaca Dashboard Perjalanan Dinas, dan TIDAK pernah ikut perhitungan anggaran. Baris dikenali dari NIP + Bulan: kombinasi yang sudah ada akan DIPERBARUI, yang belum ada dibuat baru. Tahun Anggaran dipilih di formulir import, bukan di berkas ini.';

    public const PETUNJUK = [
        ['NIP', 'Ya', 'Teks', 'WAJIB sudah terdaftar di Data Pegawai. NIP yang tidak dikenali DITOLAK - pegawainya tidak dibuat otomatis, supaya tidak lahir orang kembar di dashboard. Tanda baca dan spasi diabaikan.', '198001012000011001'],
        ['Nama Pegawai', 'Tidak', 'Teks', 'Hanya untuk memudahkan membaca berkas. Nama yang dipakai sistem selalu dari Data Pegawai sesuai NIP-nya.', 'Budi Santoso, S.E.'],
        ['Bulan', 'Ya', 'Angka 1-12', 'Bulan perjalanan. Satu orang hanya boleh punya satu baris per bulan.', '3'],
        ['Jumlah Hari', 'Tidak', 'Angka', 'Total hari perjalanan pada bulan itu. Sel kosong dihitung 0.', '3'],
        ['Uang Harian', 'Tidak', 'Angka', 'Boleh ditulis "1.250.000" atau 1250000; sel kosong dihitung 0.', '1290000'],
        ['Akomodasi', 'Tidak', 'Angka', 'Total biaya penginapan pada bulan itu.', '1140000'],
        ['Transport', 'Tidak', 'Angka', 'BBM + tol + tiket, sama seperti kolom Transportasi di dashboard.', '350000'],
        ['Representatif', 'Tidak', 'Angka', 'Uang representatif, bila ada.', '0'],
        ['Keterangan', 'Tidak', 'Teks', 'Catatan bebas, mis. nomor dokumen sumbernya.', 'Rekap SPPD Maret'],
    ];

    public function petunjukCatatan(): string
    {
        return self::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return self::PETUNJUK;
    }

    public const HEADERS = ['NIP', 'Nama Pegawai', 'Bulan', 'Jumlah Hari', 'Uang Harian', 'Akomodasi', 'Transport', 'Representatif', 'Keterangan'];

    public function array(): array
    {
        return [self::HEADERS];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $sheet->getStyle('A1:I1')->getFont()->setBold(true);
            $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:I1');

            $this->tulisSheetPetunjuk($sheet, 'Data');
        }];
    }
}
