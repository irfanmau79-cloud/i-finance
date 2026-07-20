<?php

namespace App\Exports;

use App\Models\MasterAnggaran;
use Illuminate\Database\Eloquent\Builder;

class MasterAnggaranExport extends DataManagementExport
{
    public function query(): Builder
    {
        return MasterAnggaran::query()->with('tagging')->orderBy('sub_kegiatan')->orderBy('kode_rekening');
    }

    public function headings(): array
    {
        return ['Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging', 'Pagu', 'Aktif'];
    }

    public function map($row): array
    {
        return [
            $row->program,
            $row->kegiatan,
            $row->sub_kegiatan,
            $row->kode_rekening,
            $row->uraian_rekening,
            $row->tagging?->nama,
            (float) $row->pagu,
            $row->aktif ? 'Ya' : 'Tidak',
        ];
    }
}
