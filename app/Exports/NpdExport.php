<?php

namespace App\Exports;

use App\Models\Npd;
use Illuminate\Database\Eloquent\Builder;

class NpdExport extends DataManagementExport
{
    public function query(): Builder
    {
        return Npd::query()
            ->with(['masterAnggaran.tagging', 'dibuatOleh', 'penerima'])
            ->orderByDesc('tanggal_npd')
            ->orderByDesc('id');
    }

    public function headings(): array
    {
        return [
            'Nomor NPD', 'Jenis', 'Tanggal NPD', 'Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening',
            'Tagging', 'Jenis Panjar', 'Nominal', 'Terbilang', 'Penerima', 'Status', 'Catatan',
            'Dibuat Oleh', 'Dibuat Pada',
        ];
    }

    public function map($row): array
    {
        return [
            $row->nomor_lengkap,
            Npd::JENIS_LABEL[$row->jenis] ?? $row->jenis,
            optional($row->tanggal_npd)->format('Y-m-d'),
            $row->masterAnggaran?->sub_kegiatan,
            $row->masterAnggaran?->kode_rekening,
            $row->masterAnggaran?->uraian_rekening,
            $row->tagging_snapshot ?: $row->masterAnggaran?->tagging?->nama,
            $row->jenis_panjar,
            (float) $row->nominal,
            $row->terbilang,
            $row->penerima->pluck('nama')->filter()->implode(', '),
            $row->status,
            $row->catatan,
            $row->dibuatOleh?->username,
            optional($row->created_at)->format('Y-m-d H:i'),
        ];
    }
}
