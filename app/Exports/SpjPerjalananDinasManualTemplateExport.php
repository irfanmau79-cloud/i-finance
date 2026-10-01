<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SpjPerjalananDinasManualTemplateExport implements FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents
{
    use MenulisSheetPetunjuk;

    public const CATATAN = 'Dokumen SPJ perjalanan dinas periode sebelum migrasi yang tidak punya padanan baris di tabel NPD. Digabung dengan baris dari NPD di Dashboard SPJ Perjalanan Dinas, dan di sana bisa disaring lewat pilihan Sumber. Baris dikenali dari Nomor Dokumen: yang sudah ada DIPERBARUI, yang belum ada dibuat baru. Tahun Anggaran dipilih di formulir import.';

    public const PETUNJUK = [
        ['Nomor Dokumen', 'Ya', 'Teks', 'Menjadi identitas baris. Boleh nomor NPD lama atau penomoran internal.', '12/NPD-Keu.1.IBC/III/2026'],
        ['Tanggal', 'Ya', 'Tanggal', 'Tanggal dokumen. Boleh format tanggal Excel, 2026-03-14, atau 14/03/2026.', '2026-03-14'],
        ['Nomor SP', 'Tidak', 'Teks', 'Nomor Surat Perintah terkait, bila ada.', '087/PW.02.01/Sekre'],
        ['Sub Kegiatan', 'Tidak', 'Teks', 'Sub kegiatan pembebanannya.', '6.01.01.2.01 Administrasi Keuangan'],
        ['Uraian', 'Tidak', 'Teks', 'Maksud perjalanan.', 'Reviu LKPD'],
        ['Bidang', 'Ya', 'Teks', 'WAJIB salah satu dari: Inspektur Pembantu I, Inspektur Pembantu II, Inspektur Pembantu III, Inspektur Pembantu IV, Inspektur Pembantu Investigasi, Sekretariat. Di luar itu DITOLAK, karena barisnya akan jatuh ke luar pengelompokan dashboard dan seolah hilang.', 'Inspektur Pembantu I'],
        ['Nominal', 'Tidak', 'Angka', 'Boleh ditulis "1.250.000" atau 1250000; sel kosong dihitung 0.', '4500000'],
        ['Status SPJ', 'Tidak', 'Teks', 'Isi "Terverifikasi" bila SPJ-nya sudah diperiksa. Selain itu dianggap Belum.', 'Terverifikasi'],
        ['Tanggal Verifikasi', 'Tidak', 'Tanggal', 'Hanya dipakai bila Status SPJ Terverifikasi; selain itu diabaikan.', '2026-04-02'],
        ['Diverifikasi Oleh', 'Tidak', 'Teks', 'Nama petugas yang memverifikasi. Hanya dipakai bila Terverifikasi.', 'Siti Aminah'],
        ['Keterangan', 'Tidak', 'Teks', 'Catatan bebas.', 'Berkas lengkap'],
    ];

    public function petunjukCatatan(): string
    {
        return self::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return self::PETUNJUK;
    }

    public const HEADERS = ['Nomor Dokumen', 'Tanggal', 'Nomor SP', 'Sub Kegiatan', 'Uraian', 'Bidang', 'Nominal', 'Status SPJ', 'Tanggal Verifikasi', 'Diverifikasi Oleh', 'Keterangan'];

    public function array(): array
    {
        return [self::HEADERS];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $sheet->getStyle('A1:K1')->getFont()->setBold(true);
            $sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:K1');

            $this->tulisSheetPetunjuk($sheet, 'Data');
        }];
    }
}
