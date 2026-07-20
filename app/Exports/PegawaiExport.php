<?php

namespace App\Exports;

use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Builder;

class PegawaiExport extends DataManagementExport
{
    public function query(): Builder
    {
        return Pegawai::query()->orderByDesc('aktif')->orderBy('nama');
    }

    public function headings(): array
    {
        return ['Nama', 'NIP', 'Jabatan', 'Bidang', 'Golongan', 'Pangkat', 'Rekening', 'Aktif'];
    }

    public function map($row): array
    {
        return [
            $row->nama,
            $row->nip,
            $row->jabatan,
            $row->bidang,
            $row->golongan,
            $row->pangkat,
            $row->rekening,
            $row->aktif ? 'Ya' : 'Tidak',
        ];
    }
}
