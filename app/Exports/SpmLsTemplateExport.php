<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Header saja - kolom sama persis dengan SpmLsExport/SpmUploadImport. Satu
 * baris file per kombinasi SPM + mata anggaran; kolom header dokumen
 * (Tanggal/Nomor SPM, SP2D, PPN, PPh, Penerima, Uraian) harus diulang sama
 * persis di setiap baris milik SPM yang sama - lihat App\Models\SpmImport.
 *
 * Kode dan nama berada di kolom terpisah mengikuti template Pagu/RAK/NPD.
 * Tagging DIPERTAHANKAN karena identitas mata anggaran adalah kombinasi Sub
 * Kegiatan + Kode Rekening + Tagging; tanpa kolom itu, satu Kode Rekening
 * yang punya beberapa tagging tidak bisa dipetakan secara pasti.
 */
class SpmLsTemplateExport implements FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents
{
    use MenulisSheetPetunjuk;

    public const HEADERS = [
        'Tanggal SPM', 'Nomor SPM', 'Tanggal SP2D', 'Nomor SP2D',
        'Kode Sub Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Rekening', 'Tagging',
        'Nominal', 'PPN', 'Jenis PPh 1', 'Nominal PPh 1', 'Jenis PPh 2', 'Nominal PPh 2',
        'Penerima', 'Uraian',
    ];

    /** 17 kolom -> kolom terakhir Q. */
    private const KOLOM_TERAKHIR = 'Q';

    /** Kolom tanggal. */
    private const KOLOM_TANGGAL = ['A', 'C'];

    /** Kolom kode: harus teks supaya "5.1.02.01.01.0024" tidak dibaca sebagai angka/tanggal. */
    private const KOLOM_KODE = ['E', 'G'];

    /** Kolom nominal: Nominal, PPN, Nominal PPh 1, Nominal PPh 2. */
    private const KOLOM_NOMINAL = ['J', 'K', 'M', 'O'];

    private const BARIS_PRAFORMAT = 1000;

    public const CATATAN = 'Satu baris = satu MATA ANGGARAN dalam sebuah dokumen SPM LS. Satu dokumen boleh mencakup beberapa mata anggaran: tulis satu baris per mata anggaran, dan ulangi Tanggal SPM, Nomor SPM, SP2D, PPN, PPh, Penerima, serta Uraian SAMA PERSIS di semua baris milik dokumen itu - kalau berbeda, seluruh dokumen ditolak. Satu dokumen disimpan sekaligus atau tidak sama sekali: bila satu barisnya ditolak, baris lain pada dokumen yang sama ikut dibatalkan. Berbeda dengan UP/GU/TU, SPM LS MENGURANGI pagu dan masuk sebagai realisasi, sehingga total per mata anggaran tidak boleh melebihi sisa tersedia.';

    public const PETUNJUK = [
        ['Tanggal SPM', 'Ya', 'Tanggal YYYY-MM-DD', 'Tanggal dokumen SPM. Bersama Nomor SPM menjadi identitas dokumen.', '2026-07-05'],
        ['Nomor SPM', 'Ya', 'Teks, maks 100 karakter', 'Nomor dokumen SPM. Baris dengan nomor + tanggal yang sama dianggap satu dokumen yang sama.', '002/SPM-LS/2026'],
        ['Tanggal SP2D', 'Tidak', 'Tanggal YYYY-MM-DD', 'Tanggal SP2D terbit. Kosongkan bila belum turun. Harus sama di semua baris dokumen ini.', '2026-07-08'],
        ['Nomor SP2D', 'Tidak', 'Teks, maks 100 karakter', 'Nomor SP2D. Harus sama di semua baris dokumen ini.', 'SP2D-0031/2026'],
        ['Kode Sub Kegiatan', 'Ya', 'Teks', 'Kode sub kegiatan mata anggaran yang dibebani. Bersama Kode Rekening dan Tagging dipakai mencari mata anggaran yang sudah ada dan AKTIF.', '6.01.01.2.01'],
        ['Sub Kegiatan', 'Ya', 'Teks', 'Nama sub kegiatan TANPA kodenya.', 'Penyusunan Dokumen Perencanaan Perangkat Daerah'],
        ['Kode Rekening', 'Ya', 'Teks', 'Kode rekening belanja yang dibebani.', '5.1.02.01.01.0024'],
        ['Rekening', 'Tidak', 'Teks', 'Uraian rekening TANPA kodenya. Hanya referensi.', 'Belanja Alat Tulis Kantor'],
        ['Tagging', 'Tidak', 'Teks', 'Tagging mata anggaran. WAJIB diisi bila Kode Rekening tersebut punya lebih dari satu tagging, karena tanpa itu mata anggarannya tidak bisa ditentukan. Kosongkan bila mata anggarannya memang tanpa tagging. Tagging yang belum terdaftar akan ditolak, bukan dibuat baru.', 'Rutin'],
        ['Nominal', 'Ya', 'Angka, tanpa Rp', 'Nominal BRUTO yang dibebankan ke mata anggaran baris ini. Harus lebih besar dari 0. Total seluruh baris pada satu mata anggaran tidak boleh melebihi sisa tersedia.', '2000000'],
        ['PPN', 'Tidak', 'Angka, tanpa Rp', 'PPN dokumen (bukan per baris). Termasuk di dalam bruto dan mengurangi nilai transfer, tidak mengurangi realisasi. Harus sama di semua baris dokumen ini.', '200000'],
        ['Jenis PPh 1', 'Tidak', 'Teks', 'Jenis pemotongan PPh pertama.', 'PPh Pasal 22'],
        ['Nominal PPh 1', 'Tidak', 'Angka, tanpa Rp', 'Nominal PPh pertama. Harus sama di semua baris dokumen ini.', '50000'],
        ['Jenis PPh 2', 'Tidak', 'Teks', 'Jenis pemotongan PPh kedua. Kosongkan bila hanya ada satu jenis.', 'PPh Pasal 23'],
        ['Nominal PPh 2', 'Tidak', 'Angka, tanpa Rp', 'Nominal PPh kedua. Harus sama di semua baris dokumen ini.', '25000'],
        ['Penerima', 'Tidak', 'Teks', 'Pihak ketiga penerima pencairan. Harus sama di semua baris dokumen ini.', 'CV Sumber Rejeki'],
        ['Uraian', 'Tidak', 'Teks', 'Keterangan dokumen. Harus sama di semua baris dokumen ini.', 'Pembayaran ATK triwulan III'],
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
            $akhir = self::KOLOM_TERAKHIR;
            $batas = self::BARIS_PRAFORMAT;

            $sheet->getStyle("A1:{$akhir}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$akhir}1")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$akhir}1");

            foreach (self::KOLOM_TANGGAL as $kolom) {
                $sheet->getStyle("{$kolom}2:{$kolom}{$batas}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            }

            foreach (self::KOLOM_KODE as $kolom) {
                $sheet->getStyle("{$kolom}2:{$kolom}{$batas}")
                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }

            foreach (self::KOLOM_NOMINAL as $kolom) {
                $sheet->getStyle("{$kolom}2:{$kolom}{$batas}")->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $this->tulisSheetPetunjuk($sheet, 'Data');
        }];
    }
}
