<?php

namespace App\Exports;

use App\Models\MasterAnggaran;
use Illuminate\Database\Eloquent\Builder;

class MasterAnggaranExport extends DataManagementExport
{
    public function query(): Builder
    {
        return MasterAnggaran::query()->with('tagging')->orderBy('sub_kegiatan')->orderBy('kode_rekening_bersih');
    }

    public function headings(): array
    {
        return ['Tahun Anggaran', 'Program', 'Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Tagging', 'Pagu', 'Aktif'];
    }

    public function map($row): array
    {
        return [
            (int) config('anggaran.tahun_aktif'),
            $row->program,
            $row->kegiatan,
            $row->sub_kegiatan,
            $row->kode_rekening,
            $row->tagging?->nama,
            (float) $row->pagu,
            $row->aktif ? 'Ya' : 'Tidak',
        ];
    }
}
