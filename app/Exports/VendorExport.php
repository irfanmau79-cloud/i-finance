<?php

namespace App\Exports;

use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;

class VendorExport extends DataManagementExport implements PunyaPetunjukKolom
{
    public function petunjukCatatan(): string
    {
        return VendorTemplateExport::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return VendorTemplateExport::PETUNJUK;
    }

    public function query(): Builder
    {
        return Vendor::query()->orderByDesc('aktif')->orderBy('nama');
    }

    public function headings(): array
    {
        return ['Nama', 'Rekening', 'NPWP', 'Status PKP', 'Jenis Usaha', 'Aktif'];
    }

    public function map($row): array
    {
        return [
            $row->nama,
            $row->rekening,
            $row->npwp,
            $row->pkp ? 'PKP' : 'Non-PKP',
            $row->jenis_usaha,
            $row->aktif ? 'Ya' : 'Tidak',
        ];
    }
}
