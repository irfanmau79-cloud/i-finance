<?php

namespace App\Exports;

use App\Services\AnggaranRealisasiService;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Data Realisasi Anggaran per rentang tanggal dalam bentuk Excel.
 *
 * Barisnya mengikuti susunan yang sama dengan tampilan layar dan PDF-nya -
 * Program > Kegiatan > Sub Kegiatan > Kode Rekening > Tagging - dengan kolom
 * Tingkat sebagai penanda level supaya berkasnya tetap bisa difilter dan
 * di-pivot ulang di Excel.
 */
class RealisasiPeriodeExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    private const HEADER = [
        'Tingkat', 'Kode / Nama', 'Uraian', 'Pagu Setahun',
        'Realisasi NPD', 'Realisasi LS', 'Realisasi Aktual', '% thd Pagu',
    ];

    /** Baris judul di atas tabel, sebelum header kolom. */
    private const BARIS_HEADER = 5;

    private int $jumlahBaris = 0;

    public function __construct(
        private readonly AnggaranRealisasiService $service,
        private readonly string $dari,
        private readonly string $sampai,
    ) {}

    public function title(): string
    {
        return 'Realisasi Anggaran';
    }

    public function array(): array
    {
        $hasil = $this->service->realisasiPeriode($this->dari, $this->sampai);

        $baris = [
            ['DATA REALISASI ANGGARAN'],
            ['Tahun Anggaran '.config('anggaran.tahun_aktif')],
            ['Periode '.$this->tanggal($this->dari).' s.d. '.$this->tanggal($this->sampai)],
            [],
            self::HEADER,
        ];

        foreach ($hasil['tree'] as $program) {
            $baris[] = $this->baris('Program', $program['nama'], null, $program['angka']);

            foreach ($program['kegiatan'] as $kegiatan) {
                $baris[] = $this->baris('Kegiatan', $kegiatan['nama'], null, $kegiatan['angka']);

                foreach ($kegiatan['sub'] as $sub) {
                    $baris[] = $this->baris('Sub Kegiatan', $sub['nama'], null, $sub['angka']);

                    foreach ($sub['rekening'] as $rekening) {
                        $baris[] = $this->baris('Kode Rekening', $rekening['nama'], $rekening['uraian'], $rekening['angka']);

                        foreach ($rekening['tagging'] as $tagging) {
                            $baris[] = $this->baris('Tagging', $tagging['nama'], null, $tagging['angka']);
                        }
                    }
                }
            }
        }

        $baris[] = $this->baris('TOTAL', 'Seluruh Mata Anggaran', null, $hasil['total']);

        $this->jumlahBaris = count($baris) - self::BARIS_HEADER;

        return $baris;
    }

    public function jumlahBaris(): int
    {
        return $this->jumlahBaris;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $terakhir = $sheet->getHighestRow();

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2:A3')->getFont()->setSize(10);

                $header = 'A'.self::BARIS_HEADER.':H'.self::BARIS_HEADER;
                $sheet->getStyle($header)->getFont()->setBold(true);
                $sheet->getStyle($header)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E9EEF3');
                $sheet->getStyle($header)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($terakhir > self::BARIS_HEADER) {
                    $isi = 'A'.self::BARIS_HEADER.':H'.$terakhir;
                    $sheet->getStyle($isi)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle('D'.(self::BARIS_HEADER + 1).':G'.$terakhir)
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle('H'.(self::BARIS_HEADER + 1).':H'.$terakhir)
                        ->getNumberFormat()->setFormatCode('0.00"%"');
                    $sheet->getStyle('A'.$terakhir.':H'.$terakhir)->getFont()->setBold(true);
                }

                $sheet->freezePane('A'.(self::BARIS_HEADER + 1));
            },
        ];
    }

    /** @param  array<string, float>  $angka */
    private function baris(string $tingkat, string $nama, ?string $uraian, array $angka): array
    {
        return [
            $tingkat,
            $nama,
            $uraian ?? '',
            $angka['pagu'],
            $angka['realisasi_npd'],
            $angka['realisasi_ls'],
            $angka['realisasi_aktual'],
            $angka['persentase_realisasi'],
        ];
    }

    private function tanggal(string $iso): string
    {
        return Carbon::parse($iso)->format('d-m-Y');
    }
}
