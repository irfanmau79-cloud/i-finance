<?php

namespace App\Exports;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;

class VendorExport extends DataManagementExport
{
    public function query(): Builder
    {
        return Vendor::query()->orderByDesc('aktif')->orderBy('nama');
    }

    public function headings(): array
    {
        return ['Nama', 'Rekening', 'Aktif'];
    }

    public function map($row): array
    {
        return [$row->nama, $row->rekening, $row->aktif ? 'Ya' : 'Tidak'];
    }
}
