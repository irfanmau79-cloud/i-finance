<?php

namespace App\Exports;

use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\MasterAnggaran;
use App\Models\MasterAnggaranImport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export pagu yang SEDANG BERLAKU (cermin versi aktif di master_anggaran).
 * Kolomnya identik dengan MasterAnggaranTemplateExport supaya hasil unduhan
 * bisa diedit lalu diunggah kembali sebagai versi pagu berikutnya.
 */
class MasterAnggaranExport extends DataManagementExport implements PunyaPetunjukKolom
{
    public function petunjukCatatan(): string
    {
        return MasterAnggaranTemplateExport::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return MasterAnggaranTemplateExport::PETUNJUK;
    }

    public function query(): Builder
    {
        return MasterAnggaran::query()
            ->with('tagging')
            ->orderBy('kode_sub_kegiatan')
            ->orderBy('kode_rekening');
    }

    public function headings(): array
    {
        return MasterAnggaranImport::KOLOM;
    }

    public function map($row): array
    {
        return [
            (int) config('anggaran.tahun_aktif'),
            $row->kode_program,
            $row->program,
            $row->kode_kegiatan,
            $row->kegiatan,
            $row->kode_sub_kegiatan,
            $row->sub_kegiatan,
            $row->kode_rekening,
            $row->rekening,
            $row->tagging?->nama,
            (float) $row->pagu,
            $row->aktif ? 'Aktif' : 'Non Aktif',
        ];
    }
}
