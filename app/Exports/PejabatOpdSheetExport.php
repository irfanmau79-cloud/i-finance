<?php

namespace App\Exports;

use App\Models\PejabatOpd;
use Illuminate\Database\Eloquent\Builder;

class PejabatOpdSheetExport extends DataManagementExport
{
    public function query(): Builder
    {
        return PejabatOpd::query()
            ->with(['paPegawai', 'bendaharaPengeluaranPegawai'])
            ->orderByDesc('aktif')
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return ['Pengguna Anggaran (PA)', 'NIP PA', 'Bendahara Pengeluaran', 'NIP Bendahara Pengeluaran', 'Aktif'];
    }

    public function map($row): array
    {
        return [
            $row->paPegawai?->nama,
            $row->paPegawai?->nip,
            $row->bendaharaPengeluaranPegawai?->nama,
            $row->bendaharaPengeluaranPegawai?->nip,
            $row->aktif ? 'Ya' : 'Tidak',
        ];
    }
}
