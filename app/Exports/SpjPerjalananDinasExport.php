<?php

namespace App\Exports;

use App\Models\Npd;
use App\Services\SpjDashboardService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu baris per NPD yang kode rekeningnya termasuk Belanja Perjalanan Dinas
 * Biasa/Dalam Kota (lihat SpjDashboardService::KODE_REKENING_PERJALANAN_DINAS)
 * - sumber sama persis dengan Dashboard SPJ Perjalanan Dinas.
 */
class SpjPerjalananDinasExport extends DataManagementExport
{
    public function query(): Builder
    {
        return Npd::query()
            ->whereHas('masterAnggaran', fn ($q) => $q->whereIn('kode_rekening_bersih', SpjDashboardService::KODE_REKENING_PERJALANAN_DINAS))
            ->where('status', 'Selesai')
            ->with(['masterAnggaran', 'suratPerintah', 'dibuatOleh.pegawai', 'spjVerifiedBy'])
            ->orderBy('tanggal_npd');
    }

    public function headings(): array
    {
        return [
            'Tanggal NPD', 'Nomor NPD', 'Nomor SP', 'Sub Kegiatan', 'Bidang', 'Uraian', 'Nominal',
            'Status SPJ', 'Diverifikasi Pada', 'Diverifikasi Oleh',
        ];
    }

    /** @param  Npd  $row */
    public function map($row): array
    {
        $b = SpjDashboardService::baris($row);

        return [
            optional($b['tanggal'])->format('Y-m-d'),
            $b['nomor_npd'],
            $b['nomor_sp'],
            $b['sub_kegiatan'],
            $b['bidang'],
            $b['uraian'],
            $b['nominal'],
            $b['status_spj'] === 'terverifikasi' ? 'Terverifikasi' : 'Belum',
            optional($b['verified_at'])->format('Y-m-d H:i'),
            $b['verified_by'],
        ];
    }
}
