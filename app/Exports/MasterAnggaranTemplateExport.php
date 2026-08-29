<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\MasterAnggaranImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Header saja - kolom sama persis dengan MasterAnggaranExport dan
 * MasterAnggaranUploadImport, sehingga hasil export bisa langsung dipakai
 * ulang sebagai file import.
 *
 * SENGAJA tanpa baris contoh: template yang berisi data bisa ikut terimpor
 * kalau pengguna mengunggahnya apa adanya.
 */
class MasterAnggaranTemplateExport implements FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents
{
    use MenulisSheetPetunjuk;

    public const HEADERS = MasterAnggaranImport::KOLOM;

    /** 12 kolom -> kolom terakhir L. */
    private const KOLOM_TERAKHIR = 'L';

    /** Kolom-kolom kode: harus teks, bukan angka/tanggal. */
    private const KOLOM_KODE = ['B', 'D', 'F', 'H'];

    private const KOLOM_PAGU = 'K';

    /** Baris yang diberi pra-format supaya pengetikan manual tidak berubah tipe. */
    private const BARIS_PRAFORMAT = 1000;

    public function array(): array
    {
        return [self::HEADERS];
    }

    public function petunjukCatatan(): string
    {
        return self::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return self::PETUNJUK;
    }

    public const CATATAN = 'Satu baris mewakili satu mata anggaran. Identitasnya adalah kombinasi Kode Sub Kegiatan + Kode Rekening + Tagging - kombinasi yang sudah ada akan DIPERBARUI pagunya, yang belum ada akan dibuat baru. Berkas ini diperlakukan sebagai dokumen DPA yang UTUH: mata anggaran yang sudah ada tapi tidak dicantumkan di sini akan berpagu 0 dan dinonaktifkan saat versi diaktifkan. Setelah dikonfirmasi, isinya tersimpan sebagai versi pagu berstatus draft dan BELUM berlaku sampai diaktifkan di halaman Versi Pagu.';

    public const PETUNJUK = [
        ['Tahun', 'Tidak', 'Angka 4 digit', 'Tahun anggaran. Boleh dikosongkan. Bila diisi, nilainya wajib sama dengan tahun anggaran yang sedang berjalan; kalau berbeda, SELURUH berkas ditolak.', '2026'],
        ['Kode Program', 'Tidak', 'Teks', 'Kode program sesuai DPA. Disimpan sebagai keterangan, tidak dipakai sebagai kunci.', '6.01'],
        ['Program', 'Tidak', 'Teks', 'Nama program TANPA kodenya.', 'Program Penunjang Urusan Pemerintahan Daerah'],
        ['Kode Kegiatan', 'Tidak', 'Teks', 'Kode kegiatan sesuai DPA.', '6.01.01'],
        ['Kegiatan', 'Tidak', 'Teks', 'Nama kegiatan TANPA kodenya.', 'Perencanaan, Penganggaran, dan Evaluasi Kinerja'],
        ['Kode Sub Kegiatan', 'Ya', 'Teks, maks 50 karakter', 'Kode sub kegiatan. Bagian dari identitas mata anggaran. Kosong berarti baris ditolak.', '6.01.01.2.01'],
        ['Sub Kegiatan', 'Tidak', 'Teks, maks 255 karakter', 'Nama sub kegiatan TANPA kodenya.', 'Penyusunan Dokumen Perencanaan Perangkat Daerah'],
        ['Kode Rekening', 'Ya', 'Teks, maks 50 karakter', 'Kode rekening belanja. Bagian dari identitas mata anggaran. Jangan digabung dengan uraiannya.', '5.1.02.01.01.0024'],
        ['Rekening', 'Tidak', 'Teks, maks 255 karakter', 'Uraian rekening TANPA kodenya.', 'Belanja Alat Tulis Kantor'],
        ['Tagging', 'Tidak', 'Teks', 'Penanda sumber dana/peruntukan. Bagian dari identitas mata anggaran: Kode Rekening yang sama dengan Tagging berbeda dihitung sebagai dua mata anggaran. Kosongkan bila memang tanpa tagging. Nama tagging baru akan dibuat otomatis.', 'Rutin'],
        ['Pagu', 'Ya', 'Angka, tanpa Rp', 'Nominal pagu. Isi angka saja (15000000). Format ribuan Indonesia seperti 15.000.000 masih diterima, tetapi "Rp 15.000.000", huruf, atau simbol lain DITOLAK. Nilai negatif ditolak.', '15000000'],
        ['Aktif/Non Aktif', 'Tidak', 'Aktif / Non Aktif', 'Status mata anggaran. Hanya "Non Aktif" (atau "Tidak") yang menonaktifkan; sel kosong dianggap Aktif.', 'Aktif'],
    ];

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $akhir = self::KOLOM_TERAKHIR;
            $batas = self::BARIS_PRAFORMAT;

            $sheet->getStyle("A1:{$akhir}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$akhir}1")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$akhir}1");

            // Kode berformat "5.1.02.01.01.0024": tanpa format teks, Excel
            // bisa membacanya sebagai tanggal atau memangkas nol di depan.
            foreach (self::KOLOM_KODE as $kolom) {
                $sheet->getStyle("{$kolom}2:{$kolom}{$batas}")
                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }

            // Pagu: nominal keuangan murni, tanpa simbol mata uang.
            $pagu = self::KOLOM_PAGU;
            $sheet->getStyle("{$pagu}2:{$pagu}{$batas}")
                ->getNumberFormat()->setFormatCode('#,##0');

            $sheet->getComment("{$pagu}1")->getText()->createTextRun(
                'Isi angka nominal saja, contoh: 15000000. Jangan menulis Rp, huruf, atau simbol lain.'
            );

            $this->tulisSheetPetunjuk($sheet, 'Data');
        }];
    }
}
