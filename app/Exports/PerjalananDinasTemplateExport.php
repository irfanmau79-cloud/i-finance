<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\Pegawai;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Formulir rekap perjalanan dinas per pegawai.
 *
 * Identitas pegawai (Nama, NIP, Unit Kerja) SUDAH TERISI dari Data Pegawai
 * yang aktif, sehingga pengisi tinggal melengkapi angka per bulan. Kolom
 * Tahunan berisi rumus penjumlahan Januari s.d. Desember per aspek, jadi
 * ikut terhitung sendiri begitu angka bulanannya diketik.
 *
 * Berkas ini TIDAK dibaca importer mana pun - Data Perjalanan Dinas adalah
 * tampilan terhitung dari NPD dan npd_tim, bukan tabel tersendiri.
 */
class PerjalananDinasTemplateExport implements FromArray, PunyaPetunjukKolom, WithEvents
{
    use MenulisSheetPetunjuk;

    /** Kolom identitas di depan, urut A-C. */
    public const KOLOM_IDENTITAS = ['Nama', 'NIP', 'Unit Kerja'];

    /** Lima aspek yang diulang untuk tiap bulan dan sekali lagi untuk Tahunan. */
    public const ASPEK = [
        'Jumlah Hari Penugasan',
        'Uang Harian',
        'Akomodasi',
        'Transportasi',
        'Jumlah Diterima',
    ];

    public const BULAN = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    /** Judul kelompok kolom terakhir: total Januari s.d. Desember per aspek. */
    public const KELOMPOK_TAHUNAN = 'Tahunan';

    /** Hasil query pegawai ditahan supaya array() dan AfterSheet tidak query dua kali. */
    private ?Collection $daftarPegawai = null;

    /** Baris 1-2 header (bulan + aspek), data mulai baris 3. */
    private const BARIS_DATA = 3;

    /** Indeks kolom (1-based) tempat kelompok bulan pertama dimulai. */
    private const KOLOM_BULAN_PERTAMA = 4;

    /** @return array<int, array<int, string|null>> */
    public function array(): array
    {
        $baris = [$this->headerKelompok(), $this->headerAspek()];

        foreach ($this->pegawai() as $pegawai) {
            $baris[] = array_pad([
                (string) $pegawai->nama,
                (string) $pegawai->nip,
                (string) $pegawai->bidang,
            ], $this->jumlahKolom(), null);
        }

        return $baris;
    }

    /** Pegawai aktif, dikelompokkan per unit kerja supaya mudah diisi. */
    public function pegawai(): Collection
    {
        return $this->daftarPegawai ??= Pegawai::query()
            ->where('aktif', true)
            ->orderBy('bidang')
            ->orderBy('nama')
            ->get(['nama', 'nip', 'bidang']);
    }

    public function jumlahKolom(): int
    {
        return count(self::KOLOM_IDENTITAS) + (count(self::BULAN) + 1) * count(self::ASPEK);
    }

    /**
     * Baris 1: nama bulan hanya pada sel pertama tiap kelompok - sisanya
     * kosong karena akan digabung (merge) di AfterSheet.
     *
     * @return array<int, string|null>
     */
    public function headerKelompok(): array
    {
        $baris = self::KOLOM_IDENTITAS;

        foreach ([...self::BULAN, self::KELOMPOK_TAHUNAN] as $kelompok) {
            $baris[] = $kelompok;
            $baris = array_pad($baris, count($baris) + count(self::ASPEK) - 1, null);
        }

        return $baris;
    }

    /** @return array<int, string|null> */
    public function headerAspek(): array
    {
        $baris = [null, null, null];

        foreach ([...self::BULAN, self::KELOMPOK_TAHUNAN] as $ignored) {
            foreach (self::ASPEK as $aspek) {
                $baris[] = $aspek;
            }
        }

        return $baris;
    }

    public function petunjukCatatan(): string
    {
        return 'Formulir rekap perjalanan dinas per pegawai. Kolom Nama, NIP, dan Unit Kerja sudah terisi dari Data Pegawai - jangan diubah atau dihapus supaya rekapnya tetap bisa dicocokkan. Isi lima kolom pada bulan yang sesuai untuk tiap pegawai; bulan tanpa penugasan boleh dibiarkan kosong. Lima kolom Tahunan di paling kanan berisi rumus dan terhitung sendiri, jadi tidak perlu diisi.';
    }

    public function petunjukKolom(): array
    {
        return [
            ['Nama', 'Ya', 'Teks', 'Nama pegawai, sudah terisi dari Data Pegawai.', 'Budi Santoso'],
            ['NIP', 'Ya', 'Teks', 'NIP pegawai, sudah terisi dari Data Pegawai.', '198001012005011001'],
            ['Unit Kerja', 'Tidak', 'Teks', 'Unit kerja pegawai, sudah terisi dari Data Pegawai.', 'Irbanwil I'],
            ['Jumlah Hari Penugasan', 'Tidak', 'Angka', 'Banyaknya hari penugasan pada bulan tersebut. Kosongkan bila tidak ada penugasan.', '3'],
            ['Uang Harian', 'Tidak', 'Angka, tanpa Rp', 'Total uang harian yang diterima pada bulan tersebut.', '1350000'],
            ['Akomodasi', 'Tidak', 'Angka, tanpa Rp', 'Total biaya penginapan pada bulan tersebut.', '900000'],
            ['Transportasi', 'Tidak', 'Angka, tanpa Rp', 'Total transportasi pada bulan tersebut: BBM, tol, dan tiket.', '450000'],
            ['Jumlah Diterima', 'Tidak', 'Angka, tanpa Rp', 'Total yang diterima pegawai pada bulan tersebut. Diisi sendiri, bukan rumus, karena nilainya bisa memuat uang representatif di luar ketiga komponen di atas.', '2700000'],
            [self::KELOMPOK_TAHUNAN, 'Tidak', 'Rumus', 'Lima kolom terakhir: total Januari s.d. Desember untuk tiap aspek. Sudah berisi rumus, tidak perlu diisi.', '=SUM(...)'],
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $jumlahBaris = $this->pegawai()->count();
            $barisAkhir = self::BARIS_DATA + max($jumlahBaris, 1) - 1;
            $kolomAkhir = Coordinate::stringFromColumnIndex($this->jumlahKolom());

            // Identitas: satu sel tinggi dua baris.
            foreach (range(1, count(self::KOLOM_IDENTITAS)) as $indeks) {
                $kolom = Coordinate::stringFromColumnIndex($indeks);
                $sheet->mergeCells("{$kolom}1:{$kolom}2");
                $sheet->getColumnDimension($kolom)->setWidth($indeks === 1 ? 30 : 24);
            }

            $kelompok = [...self::BULAN, self::KELOMPOK_TAHUNAN];

            foreach ($kelompok as $urutan => $nama) {
                $mulai = self::KOLOM_BULAN_PERTAMA + $urutan * count(self::ASPEK);
                $selesai = $mulai + count(self::ASPEK) - 1;
                $kolomMulai = Coordinate::stringFromColumnIndex($mulai);
                $kolomSelesai = Coordinate::stringFromColumnIndex($selesai);

                $sheet->mergeCells("{$kolomMulai}1:{$kolomSelesai}1");

                // Kelompok bulan dibedakan warnanya bergantian supaya batas
                // antar bulan tetap terlihat saat digulir ke kanan.
                $warna = $nama === self::KELOMPOK_TAHUNAN
                    ? 'FFD9A938'
                    : ($urutan % 2 === 0 ? 'FFDCE6F1' : 'FFEDF2F8');

                $sheet->getStyle("{$kolomMulai}1:{$kolomSelesai}2")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($warna);

                foreach (range($mulai, $selesai) as $indeks) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($indeks))->setWidth(16);
                }
            }

            $sheet->getStyle("A1:{$kolomAkhir}2")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$kolomAkhir}2")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $sheet->getStyle("A1:{$kolomAkhir}2")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFBFCBD6');
            $sheet->getRowDimension(2)->setRowHeight(34);
            $sheet->freezePane('D'.self::BARIS_DATA);

            if ($jumlahBaris === 0) {
                $this->tulisSheetPetunjuk($sheet, 'Data');

                return;
            }

            // NIP disimpan sebagai teks supaya 18 digitnya tidak dibulatkan
            // menjadi notasi ilmiah oleh Excel.
            $sheet->getStyle('B'.self::BARIS_DATA.":B{$barisAkhir}")
                ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

            foreach (range(self::BARIS_DATA, $barisAkhir) as $baris) {
                foreach (self::ASPEK as $urutanAspek => $ignored) {
                    $selBulan = [];

                    foreach (array_keys(self::BULAN) as $urutanBulan) {
                        $indeks = self::KOLOM_BULAN_PERTAMA + $urutanBulan * count(self::ASPEK) + $urutanAspek;
                        $selBulan[] = Coordinate::stringFromColumnIndex($indeks).$baris;
                    }

                    $indeksTahunan = self::KOLOM_BULAN_PERTAMA
                        + count(self::BULAN) * count(self::ASPEK) + $urutanAspek;

                    $sheet->setCellValue(
                        Coordinate::stringFromColumnIndex($indeksTahunan).$baris,
                        '=SUM('.implode(',', $selBulan).')'
                    );
                }
            }

            $kolomAngkaMulai = Coordinate::stringFromColumnIndex(self::KOLOM_BULAN_PERTAMA);
            $sheet->getStyle("{$kolomAngkaMulai}".self::BARIS_DATA.":{$kolomAkhir}{$barisAkhir}")
                ->getNumberFormat()->setFormatCode('#,##0');

            $sheet->getStyle('A'.self::BARIS_DATA.":{$kolomAkhir}{$barisAkhir}")->getBorders()
                ->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E9F0');

            $this->tulisSheetPetunjuk($sheet, 'Data');
        }];
    }
}
