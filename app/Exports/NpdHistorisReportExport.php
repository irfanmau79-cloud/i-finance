<?php

namespace App\Exports;

use App\Models\NpdHistorisImport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class NpdHistorisReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly NpdHistorisImport $import, private readonly string $mode) {}

    public function collection()
    {
        return $this->import->baris()->orderBy('nomor_baris')->get();
    }

    public function headings(): array
    {
        return ['Batch', 'Baris Sumber', 'Hasil', 'Pesan', 'Tanggal', 'Tahun', 'Bulan', 'Nomor NPD', 'Jenis Input', 'Jenis Kode', 'Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Tagging', 'Penerima', 'Penerima Manual', 'Nominal Bruto', 'PPN', 'PPh1', 'Jenis PPh1', 'PPh2', 'Jenis PPh2', 'Status', 'Pagu', 'RAK Bulan', 'Realisasi Sebelum', 'Realisasi Proyeksi', 'Sisa Proyeksi', 'Mapping', 'NPD ID'];
    }

    public function map($row): array
    {
        return [$this->import->id, $row->nomor_baris, $row->hasil, implode(' | ', $row->pesan ?? []), optional($row->tanggal_npd)->format('Y-m-d'), $row->tahun, $row->bulan, $row->nomor_npd, $row->jenis_input, $row->jenis_kode, $row->program, $row->kegiatan, $row->sub_kegiatan, $row->kode_rekening, $row->tagging_nama, $row->penerima, $row->penerima_manual ? 'Ya' : 'Tidak', (float) $row->nominal_bruto, (float) $row->ppn, (float) $row->pph1, $row->jenis_pph1, (float) $row->pph2, $row->jenis_pph2, $row->status_target, (float) $row->pagu, (float) $row->rak_bulan, (float) $row->realisasi_sebelum, (float) $row->realisasi_proyeksi, (float) $row->sisa_proyeksi, $row->mapping_status, $this->mode === 'final' ? $row->npd_id : null];
    }
}
