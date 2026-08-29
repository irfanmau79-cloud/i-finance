<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Services\NpdHistorisImportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Template Import NPD Historis. Kode dan nama berada di kolom terpisah
 * (Kode Sub Kegiatan/Sub Kegiatan, Kode Rekening/Rekening) mengikuti
 * template Pagu Anggaran dan RAK. Baris marker & instruksi DIPERTAHANKAN di
 * sini - berbeda dengan RAK, marker NPD Historis bersifat wajib dan
 * ditegakkan importer (lihat NpdHistorisImportService::normalisasiWorkbook).
 */
class NpdHistorisTemplateExport implements FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents
{
    use MenulisSheetPetunjuk;

    public function petunjukCatatan(): string
    {
        return 'Template Import NPD Historis: satu dokumen NPD per baris, untuk memasukkan NPD yang sudah terjadi. Baris 1-3 berisi marker dan petunjuk - JANGAN diubah atau dihapus, karena importer menolak berkas tanpa marker. Isi data mulai baris 5. Tahun Anggaran dan Bulan Realisasi diturunkan otomatis dari Tanggal NPD, jadi tanggal di luar tahun anggaran berjalan akan ditolak. PPN termasuk di dalam nominal bruto dan mengurangi nilai transfer bersama PPh, tetapi TIDAK mengurangi realisasi bruto.';
    }

    public function petunjukKolom(): array
    {
        return [
            ['Tanggal NPD', 'Ya', 'Tanggal YYYY-MM-DD', 'Tanggal dokumen NPD. Menentukan tahun anggaran dan bulan realisasi. Tanggal di luar tahun anggaran berjalan ditolak.', '2026-07-15'],
            ['Nomor NPD', 'Ya', 'Teks', 'Nomor dokumen NPD apa adanya dari arsip. Nomor + tanggal yang sudah ada di database dilewati, tidak ditimpa.', '001/NPD/HIST/2026'],
            ['Jenis NPD', 'Ya', 'Pilihan', 'Salah satu dari: Barang/Jasa, Perjalanan Dinas, Transport, Narasumber, Kontribusi Diklat. Jenis di luar daftar ditolak - sistem tidak menebak dari kolom lain.', 'Barang/Jasa'],
            ['Kode Sub Kegiatan', 'Tidak', 'Teks', 'Kode sub kegiatan. Boleh dikosongkan bila kolom Sub Kegiatan sudah memuat kode dan nama sekaligus.', '6.01.01.2.01'],
            ['Sub Kegiatan', 'Ya', 'Teks', 'Nama sub kegiatan. Bersama Kode Rekening dan Tagging dipakai mencari mata anggaran yang sudah ada dan aktif.', 'Penyusunan Dokumen Perencanaan Perangkat Daerah'],
            ['Kode Rekening', 'Ya', 'Teks', 'Kode rekening belanja yang dibebani.', '5.1.02.01.01.0024'],
            ['Rekening', 'Tidak', 'Teks', 'Uraian rekening tanpa kodenya. Hanya referensi.', 'Belanja Alat Tulis Kantor'],
            ['Tagging', 'Tidak', 'Teks', 'Tagging mata anggaran. Isi bila kode rekening tersebut dibedakan per tagging.', 'Rutin'],
            ['Penerima', 'Ya', 'Teks', 'Nama penerima. Bila tidak ditemukan di master Pegawai/Vendor, namanya tetap dipakai sebagai snapshot dengan peringatan - master TIDAK dibuat otomatis.', 'CV Sumber Rejeki'],
            ['Rekening Penerima', 'Ya', 'Teks', 'Nomor rekening bank penerima.', '1234567890'],
            ['Nominal Bruto', 'Ya', 'Angka, tanpa Rp', 'Nilai BRUTO dokumen. Inilah angka yang mengikat pagu dan menjadi realisasi.', '1000000'],
            ['Uraian', 'Ya', 'Teks', 'Keterangan dokumen.', 'Belanja ATK bulan Juli'],
            ['PPN', 'Ya', 'Angka, tanpa Rp', 'PPN yang sudah termasuk di dalam bruto. Isi 0 bila tidak ada. Total PPN + PPh tidak boleh melebihi Nominal Bruto.', '100000'],
            ['PPh1', 'Ya', 'Angka, tanpa Rp', 'Nominal potongan PPh pertama. Isi 0 bila tidak ada.', '50000'],
            ['Jenis PPh1', 'Ya', 'Teks', 'Jenis potongan PPh pertama. Kosongkan isian nominalnya dengan 0 bila tidak ada potongan.', 'PPh 21'],
            ['PPh2', 'Ya', 'Angka, tanpa Rp', 'Nominal potongan PPh kedua. Isi 0 bila tidak ada.', '25000'],
            ['Jenis PPh2', 'Ya', 'Teks', 'Jenis potongan PPh kedua.', 'PPh 22'],
            ['Status NPD', 'Tidak', 'Teks', 'Status akhir dokumen. Dikosongkan berarti Selesai. Status yang mengandung kata Batal tidak menghasilkan realisasi.', 'Selesai'],
        ];
    }

    /** 18 kolom -> kolom terakhir R. */
    private const KOLOM_TERAKHIR = 'R';

    /** Baris header (marker 1, instruksi 2-3). */
    private const BARIS_HEADER = 4;

    private const BARIS_TERAKHIR = 5004;

    /** Kolom kode: harus teks supaya "5.1.02.01.01.0024" tidak dibaca sebagai angka/tanggal. */
    private const KOLOM_KODE = ['D', 'F'];

    /** Kolom nominal: Nominal Bruto, PPN, PPh1, PPh2. */
    private const KOLOM_NOMINAL = ['K', 'M', 'N', 'P'];

    public function array(): array
    {
        $tahun = (int) config('anggaran.tahun_aktif');

        return [
            [NpdHistorisImportService::FORMAT_MARKER],
            ["TAHUN ANGGARAN {$tahun}: isi satu dokumen per baris dan gunakan Tanggal NPD tahun {$tahun}. Tanggal di luar {$tahun} ditolak. Status kosong menjadi Selesai. Jenis: Barang/Jasa, Perjalanan Dinas, Transport, Narasumber, atau Kontribusi Diklat."],
            ["Kode dan nama berada di kolom terpisah - jangan digabung dalam satu sel. PPN termasuk dalam bruto dan, sesuai aturan Lampiran yang ada, mengurangi nilai transfer bersama PPh; PPN tidak mengurangi realisasi bruto. Tahun Anggaran {$tahun} dan Bulan Realisasi diturunkan dari Tanggal NPD."],
            NpdHistorisImportService::HEADERS,
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $akhir = self::KOLOM_TERAKHIR;
            $header = self::BARIS_HEADER;
            $mulai = $header + 1;
            $batas = self::BARIS_TERAKHIR;

            $sheet->mergeCells("A1:{$akhir}1")->mergeCells("A2:{$akhir}2")->mergeCells("A3:{$akhir}3");
            $sheet->getStyle("A1:{$akhir}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle("A1:{$akhir}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF15314A');
            $sheet->getStyle("A2:{$akhir}3")->getAlignment()->setWrapText(true);
            $sheet->getStyle("A{$header}:{$akhir}{$header}")->getFont()->setBold(true);
            $sheet->getStyle("A{$header}:{$akhir}{$header}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $sheet->getRowDimension(2)->setRowHeight(36);
            $sheet->getRowDimension(3)->setRowHeight(36);
            $sheet->freezePane("A{$mulai}");
            $sheet->setAutoFilter("A{$header}:{$akhir}{$header}");

            $sheet->getStyle("A{$mulai}:A{$batas}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');

            foreach (self::KOLOM_KODE as $kolom) {
                $sheet->getStyle("{$kolom}{$mulai}:{$kolom}{$batas}")
                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }

            foreach (self::KOLOM_NOMINAL as $kolom) {
                $sheet->getStyle("{$kolom}{$mulai}:{$kolom}{$batas}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $this->tulisSheetPetunjuk($sheet, 'Data');
        }];
    }
}
