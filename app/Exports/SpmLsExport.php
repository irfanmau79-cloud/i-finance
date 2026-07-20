<?php

namespace App\Exports;

use App\Models\Spm;
use Illuminate\Database\Eloquent\Builder;

class SpmLsExport extends DataManagementExport
{
    public function query(): Builder
    {
        return Spm::query()
            ->where('jenis_spm', 'ls')
            ->with(['masterAnggaran.tagging', 'dibuatOleh'])
            ->orderByDesc('tanggal_dokumen')
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return [
            'Nomor Dokumen', 'Tanggal Dokumen', 'Nomor SP2D', 'Tanggal SP2D',
            'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging',
            'Nominal', 'PPN', 'PPh 1', 'Jenis PPh 1', 'PPh 2', 'Jenis PPh 2',
            'Penerima', 'Uraian', 'Dibuat Oleh', 'Dibuat Pada',
        ];
    }

    public function map($row): array
    {
        return [
            $row->nomor_dokumen,
            optional($row->tanggal_dokumen)->format('Y-m-d'),
            $row->nomor_sp2d,
            optional($row->tanggal_sp2d)->format('Y-m-d'),
            $row->masterAnggaran?->sub_kegiatan,
            $row->masterAnggaran?->kode_rekening,
            $row->masterAnggaran?->uraian_rekening,
            $row->masterAnggaran?->tagging?->nama,
            (float) $row->nominal,
            (float) $row->ppn,
            (float) $row->pph1,
            $row->jenis_pph1,
            (float) $row->pph2,
            $row->jenis_pph2,
            $row->penerima,
            $row->uraian,
            $row->dibuatOleh?->username,
            optional($row->created_at)->format('Y-m-d H:i'),
        ];
    }
}
