<?php

namespace App\Exports;

use App\Models\MasterAnggaran;
use App\Models\RakBulanan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Template RAK Bulanan: satu baris per KOMBINASI Sub Kegiatan + Kode
 * Rekening AKTIF (BUKAN per Tagging - lihat Prompt 12A), kolom
 * Januari..Desember berisi target BULANAN (bukan kumulatif - lihat
 * dokumentasi di migration create_rak_bulanan_table). Sel bulan yang belum
 * punya RAK dibiarkan KOSONG (null) - tidak pernah diisi pagu/12 - supaya
 * kekosongan tetap terlihat jelas sebagai "RAK belum tersedia" saat file
 * ini dibuka maupun saat diupload kembali sebagai template import.
 *
 * TIDAK ADA kolom Tagging. Satu Sub Kegiatan + Kode Rekening bisa punya
 * banyak baris master_anggaran (satu per Tagging, untuk dana terikat/
 * realisasi per NPD) - export ini menggabungkannya jadi SATU baris, dengan
 * Pagu = jumlah pagu seluruh Tagging pada kombinasi tersebut, supaya total
 * RAK tahunan tidak tergandakan.
 */
class RakBulananExport extends DataManagementExport implements WithCustomStartCell, WithEvents
{
    public const FORMAT_MARKER = 'IFINANCE_RAK_BULANAN_MONTHLY_V2';

    private const BULAN_LABEL = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** @var array<int, int> id master_anggaran wakil (satu per kombinasi Sub Kegiatan+Kode Rekening) */
    private array $idWakil;

    /** @var Collection<int, float> pagu gabungan lintas Tagging, key = id wakil */
    private Collection $paguByIdWakil;

    /** @var Collection<string, Collection> RAK bulan tahun ini, key = "sub_kegiatan_kunci|kode_rekening" */
    private Collection $rakByKunci;

    public function __construct(private readonly int $tahun)
    {
        $tahunAktif = (int) config('anggaran.tahun_aktif');
        if ($tahun !== $tahunAktif) {
            throw new InvalidArgumentException("Template RAK Bulanan hanya tersedia untuk Tahun Anggaran {$tahunAktif}.");
        }

        $agregat = MasterAnggaran::query()
            ->selectRaw('MIN(id) as id_wakil, SUM(pagu) as pagu_gabungan')
            ->where('aktif', true)
            ->groupBy('sub_kegiatan_kunci', 'kode_rekening')
            ->get();

        $this->idWakil = $agregat->pluck('id_wakil')->all();
        $this->paguByIdWakil = $agregat->pluck('pagu_gabungan', 'id_wakil');

        $this->rakByKunci = RakBulanan::where('tahun', $tahun)
            ->get()
            ->groupBy(fn ($rak) => $rak->sub_kegiatan_kunci.'|'.$rak->kode_rekening);
    }

    public function query(): Builder
    {
        return MasterAnggaran::query()
            ->whereIn('id', $this->idWakil)
            ->orderBy('sub_kegiatan')
            ->orderBy('kode_rekening');
    }

    public function headings(): array
    {
        return array_merge(
            ['Tahun Anggaran', 'Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Pagu'],
            array_values(self::BULAN_LABEL)
        );
    }

    public function startCell(): string
    {
        return 'A3';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setCellValue('A1', self::FORMAT_MARKER);
                $sheet->setCellValue('A2', "FORMAT RESMI TAHUN ANGGARAN {$this->tahun}: nilai Januari-Desember adalah alokasi BULANAN, bukan kumulatif. Tagging tidak digunakan dalam identitas atau saldo RAK. Tahun lain ditolak. Jangan mengubah marker ini.");
                $sheet->mergeCells('A1:S1');
                $sheet->mergeCells('A2:S2');
                $sheet->getStyle('A1:S1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle('A1:S1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF15314A');
                $sheet->getStyle('A2:S2')->getAlignment()->setWrapText(true);
                $sheet->getRowDimension(2)->setRowHeight(34);
                $sheet->freezePane('A4');
                $sheet->setAutoFilter('A3:S3');
            },
        ];
    }

    public function map($row): array
    {
        $kunci = $row->sub_kegiatan_kunci.'|'.$row->kode_rekening;
        $perBulan = ($this->rakByKunci->get($kunci) ?? collect())->keyBy('bulan');
        $pagu = (float) ($this->paguByIdWakil[$row->id] ?? $row->pagu);

        $kolomBulan = [];

        foreach (self::BULAN_LABEL as $bulan => $label) {
            $kolomBulan[] = $perBulan->has($bulan) ? (float) $perBulan[$bulan]->target : null;
        }

        return array_merge([
            $this->tahun,
            $row->program,
            $row->kegiatan,
            $row->sub_kegiatan,
            $row->kode_rekening,
            $row->uraian_rekening,
            $pagu,
        ], $kolomBulan);
    }
}
