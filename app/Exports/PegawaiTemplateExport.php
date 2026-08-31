<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Header saja - kolom sama persis dengan PegawaiExport/PegawaiUploadImport. */
class PegawaiTemplateExport implements FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents
{
    use MenulisSheetPetunjuk;

    public const CATATAN = 'Daftar pegawai yang dipakai sebagai penerima NPD, anggota tim perjalanan dinas, dan pejabat penanda tangan. Pegawai dikenali dari NIP: NIP yang sudah ada akan DIPERBARUI datanya, yang belum ada dibuat baru. Pegawai yang masih menjabat KPA/BPP/PPTK tidak bisa dinonaktifkan.';

    public const PETUNJUK = [
        ['Nama', 'Ya', 'Teks', 'Nama lengkap pegawai beserta gelar, ditulis sebagaimana akan tercetak di dokumen.', 'Budi Santoso, S.E.'],
        ['NIP', 'Ya', 'Teks 18 digit', 'Nomor Induk Pegawai. Menjadi identitas baris - NIP ganda dalam satu berkas ditolak. Format sebagai TEKS supaya angka 0 di depan tidak hilang.', '198504102010011005'],
        ['Jabatan', 'Tidak', 'Teks', 'Nama jabatan, dipakai pada dokumen dan daftar tanda tangan.', 'Auditor Muda'],
        ['Bidang', 'Tidak', 'Teks', 'Unit/bidang tempat pegawai bertugas.', 'Irban Wilayah I'],
        ['Golongan', 'Tidak', 'Teks', 'Golongan kepegawaian.', 'III/c'],
        ['Pangkat', 'Tidak', 'Teks', 'Pangkat kepegawaian.', 'Penata'],
        ['Rekening', 'Tidak', 'Teks', 'Nomor rekening bank untuk transfer. Format sebagai TEKS supaya nol di depan tidak hilang.', '0012345678'],
        ['Nomor Handphone', 'Tidak', 'Teks', 'Nomor WhatsApp pegawai, dipakai fitur Kirim Notifikasi di Data NPD. Boleh ditulis 08... atau +62... Sel yang DIKOSONGKAN tidak menghapus nomor yang sudah tersimpan.', '081234567890'],
        ['Aktif', 'Tidak', 'Ya / Tidak', 'Status kepegawaian. Hanya "Tidak" yang menonaktifkan; sel kosong dianggap Aktif. Pegawai non-aktif tidak muncul di pilihan penerima.', 'Ya'],
    ];

    public function petunjukCatatan(): string
    {
        return self::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return self::PETUNJUK;
    }

    public const HEADERS = ['Nama', 'NIP', 'Jabatan', 'Bidang', 'Golongan', 'Pangkat', 'Rekening', 'Nomor Handphone', 'Aktif'];

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

            $this->tulisSheetPetunjuk($sheet, 'Data');
        }];
    }
}
