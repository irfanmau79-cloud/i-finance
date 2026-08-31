<?php

namespace App\Exports;

use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Builder;

class PegawaiExport extends DataManagementExport implements PunyaPetunjukKolom
{
    public function petunjukCatatan(): string
    {
        return PegawaiTemplateExport::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return PegawaiTemplateExport::PETUNJUK;
    }

    public function query(): Builder
    {
        return Pegawai::query()->orderByDesc('aktif')->orderBy('nama');
    }

    public function headings(): array
    {
        return ['Nama', 'NIP', 'Jabatan', 'Bidang', 'Golongan', 'Pangkat', 'Rekening', 'Nomor Handphone', 'Aktif'];
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
            $row->nomor_handphone,
            $row->aktif ? 'Ya' : 'Tidak',
        ];
    }
}
