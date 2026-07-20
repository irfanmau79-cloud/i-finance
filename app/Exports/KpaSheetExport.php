<?php

namespace App\Exports;

use App\Models\Kpa;
use Illuminate\Database\Eloquent\Builder;

class KpaSheetExport extends DataManagementExport
{
    public function query(): Builder
    {
        return Kpa::query()
            ->with(['kpaPegawai', 'bppPegawai'])
            ->orderByDesc('aktif')
            ->orderBy('nama_jabatan');
    }

    public function headings(): array
    {
        return ['Nama Jabatan (KEU)', 'KPA', 'NIP KPA', 'BPP', 'NIP BPP', 'Aktif'];
    }

    public function map($row): array
    {
        return [
            $row->nama_jabatan,
            $row->kpaPegawai?->nama,
            $row->kpaPegawai?->nip,
            $row->bppPegawai?->nama,
            $row->bppPegawai?->nip,
            $row->aktif ? 'Ya' : 'Tidak',
        ];
    }
}
