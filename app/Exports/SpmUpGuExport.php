<?php

namespace App\Exports;

use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\Spm;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export SPM UP/GU/TU. Kolomnya identik dengan SpmUpGuTemplateExport supaya
 * hasil unduhan bisa diedit lalu diunggah kembali sebagai berkas import.
 * PPN/PPh dan Penerima sengaja tidak ikut - lihat catatan di template.
 */
class SpmUpGuExport extends DataManagementExport implements PunyaPetunjukKolom
{
    public function petunjukCatatan(): string
    {
        return SpmUpGuTemplateExport::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return SpmUpGuTemplateExport::PETUNJUK;
    }

    public function query(): Builder
    {
        return Spm::query()
            ->where('jenis_spm', 'up_gu')
            ->orderByDesc('tanggal_dokumen')
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return SpmUpGuTemplateExport::HEADERS;
    }

    public function map($row): array
    {
        return [
            optional($row->tanggal_dokumen)->format('Y-m-d'),
            $row->nomor_dokumen,
            optional($row->tanggal_sp2d)->format('Y-m-d'),
            $row->nomor_sp2d,
            (float) $row->nominal,
            $row->uraian,
        ];
    }
}
