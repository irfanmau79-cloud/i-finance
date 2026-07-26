<?php

namespace App\Exports;

use App\Models\NpdTim;
use App\Services\PerjalananDinasDashboardService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu baris per ANGGOTA tim (bukan per NPD) - NPD Perjalanan Dinas/Transport
 * bisa punya banyak anggota, masing-masing dengan rincian hari/uang harian/
 * akomodasi/transport/representatif sendiri. Rumus dipakai ulang dari
 * PerjalananDinasDashboardService::baris() supaya tetap satu sumber dengan
 * Dashboard Perjalanan Dinas.
 */
class PerjalananDinasExport extends DataManagementExport
{
    public function query(): Builder
    {
        return NpdTim::query()
            ->whereHas('npd', fn ($q) => $q->whereIn('jenis', ['pd', 'tr'])->where('status', 'Selesai'))
            ->with(['npd.masterAnggaran', 'pegawai', 'paket'])
            ->orderBy('npd_id');
    }

    public function headings(): array
    {
        return [
            'Tanggal NPD', 'Nomor NPD', 'Sub Kegiatan', 'Bidang', 'Nama', 'Jabatan',
            'Jumlah Hari', 'Uang Harian', 'Akomodasi', 'Transport', 'Representatif', 'Diterima',
        ];
    }

    /** @param  NpdTim  $row */
    public function map($row): array
    {
        $b = PerjalananDinasDashboardService::baris($row->npd, $row);

        return [
            optional($row->npd->tanggal_npd)->format('Y-m-d'),
            $row->npd->nomor_lengkap ?: 'NPD #'.$row->npd->id,
            $row->npd->masterAnggaran?->subKegiatanNormal(),
            $b['bidang'],
            $b['nama'],
            $b['jabatan'],
            $b['hari'],
            $b['uang_harian'],
            $b['akomodasi'],
            $b['transport'],
            $b['representatif'],
            $b['diterima'],
        ];
    }
}
