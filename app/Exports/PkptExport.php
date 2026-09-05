<?php

namespace App\Exports;

use App\Exports\Concerns\MenulisSheetPetunjuk;
use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Services\PkptService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export Data PKPT tahun anggaran berjalan.
 *
 * Berbeda dari export Manajemen Data lain yang memakai DataManagementExport
 * (FromQuery + chunk), kelas ini menyusun barisnya lewat PkptService supaya
 * URUTANNYA persis sama dengan tabel Monitoring PKPT: unit Irban I..IV lalu
 * Investigasi, baru nomor. Urutan itu tidak bisa dinyatakan sebagai ORDER BY
 * yang portabel - unitnya tidak alfabetis dan nomornya tidak selalu angka -
 * dan PKPT jumlahnya ratusan baris, jauh di bawah alasan memakai chunk.
 */
class PkptExport implements CountsRows, FromArray, PunyaPetunjukKolom, ShouldAutoSize, WithEvents, WithHeadings
{
    use Exportable;
    use MenulisSheetPetunjuk;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $baris = null;

    public function __construct(private readonly ?int $tahun = null) {}

    public function petunjukCatatan(): string
    {
        return PkptTemplateExport::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return PkptTemplateExport::PETUNJUK;
    }

    public function headings(): array
    {
        return PkptTemplateExport::HEADERS;
    }

    public function jumlahBaris(): int
    {
        return count($this->baris());
    }

    public function array(): array
    {
        return array_map(fn (array $r) => [
            $r['nomor'],
            $r['unit'],
            $r['area'],
            $r['jenis'],
            $r['tujuan'],
            $r['ruang_lingkup'],
            $r['jumlah_tim'],
            $r['estimasi'],
            $r['realisasi'],
            $r['rencana'],
            $r['pelaksanaan'],
            $r['jumlah_laporan'] ?? '',
            $r['terlaksana'] ? 'Ya' : 'Tidak',
        ], $this->baris());
    }

    /** @return array<int, array<string, mixed>> */
    private function baris(): array
    {
        return $this->baris ??= app(PkptService::class)->ringkasanUntukExport($this->tahun);
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
