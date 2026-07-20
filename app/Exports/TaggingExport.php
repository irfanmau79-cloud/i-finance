<?php

namespace App\Exports;

use App\Models\Tagging;
use Illuminate\Database\Eloquent\Builder;

class TaggingExport extends DataManagementExport
{
    public function query(): Builder
    {
        return Tagging::query()->orderByDesc('aktif')->orderBy('nama');
    }

    public function headings(): array
    {
        return ['Nama', 'Aktif'];
    }

    public function map($row): array
    {
        return [$row->nama, $row->aktif ? 'Ya' : 'Tidak'];
    }
}
