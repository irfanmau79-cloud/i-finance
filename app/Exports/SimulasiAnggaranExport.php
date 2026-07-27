<?php

namespace App\Exports;

use App\Models\SimulasiAnggaran;
use App\Models\SimulasiAnggaranRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export satu Simulasi Pergeseran/Perubahan Anggaran tersimpan. Sumbernya
 * koleksi baris yang sudah dimuat (bukan query - simulasi cuma berisi
 * mata anggaran aktif saat dibuat, jumlahnya kecil), jadi tidak perlu
 * DataManagementExport (yang berbasis FromQuery+chunk untuk tabel besar).
 */
class SimulasiAnggaranExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private SimulasiAnggaran $simulasiAnggaran) {}

    public function collection(): Collection
    {
        return SimulasiAnggaranRow::lampirkanRealisasi($this->simulasiAnggaran->rows);
    }

    public function headings(): array
    {
        return ['Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging', 'Anggaran (Eksisting)', 'Realisasi', 'Anggaran (Simulasi)', 'Selisih'];
    }

    public function map($row): array
    {
        return [
            $row->program,
            $row->kegiatan,
            $row->sub_kegiatan,
            $row->kode_rekening,
            $row->uraian_rekening,
            $row->tagging_nama ?? '-',
            (float) $row->pagu_eksisting,
            (float) $row->realisasi,
            (float) $row->pagu_simulasi,
            (float) $row->selisih,
        ];
    }
}
