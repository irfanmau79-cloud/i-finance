<?php

namespace App\Exports;

use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\Npd;
use App\Services\SpjDashboardService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu baris per NPD yang kode rekeningnya termasuk Belanja Perjalanan Dinas
 * Biasa/Dalam Kota (lihat SpjDashboardService::KODE_REKENING_PERJALANAN_DINAS)
 * - sumber sama persis dengan Dashboard SPJ Perjalanan Dinas.
 */
class SpjPerjalananDinasExport extends DataManagementExport implements PunyaPetunjukKolom
{
    public function petunjukCatatan(): string
    {
        return 'Laporan status pertanggungjawaban (SPJ) perjalanan dinas, satu baris per dokumen NPD. Verifikasi SPJ TIDAK mengubah status alur NPD - keduanya berjalan terpisah. Berkas ini hasil ekspor untuk dibaca atau diarsipkan.';
    }

    public function petunjukKolom(): array
    {
        return [
            ['Tanggal NPD', '-', 'Tanggal YYYY-MM-DD', 'Tanggal dokumen NPD.', '2026-07-15'],
            ['Nomor NPD', '-', 'Teks', 'Nomor lengkap NPD.', '010/NPD-PD/2026'],
            ['Nomor SP', '-', 'Teks', 'Nomor Surat Perintah yang menyertai perjalanan.', '094/SP/2026'],
            ['Sub Kegiatan', '-', 'Teks', 'Sub kegiatan yang dibebani, lengkap dengan kodenya.', '6.01.02.1.01 Pengawasan Internal'],
            ['Bidang', '-', 'Teks', 'Bidang pelaksana perjalanan.', 'Irban Wilayah I'],
            ['Uraian', '-', 'Teks', 'Uraian penugasan pada Surat Perintah.', 'Pengawasan reguler Dinas Pendidikan'],
            ['Nominal', '-', 'Angka', 'Nilai bruto dokumen NPD.', '2750000'],
            ['Status SPJ', '-', 'terverifikasi / belum', 'Status pertanggungjawaban dokumen.', 'terverifikasi'],
            ['Diverifikasi Pada', '-', 'Tanggal jam', 'Waktu verifikasi SPJ. Kosong bila belum diverifikasi.', '2026-07-28 14:05'],
            ['Diverifikasi Oleh', '-', 'Teks', 'Pengguna yang memverifikasi SPJ.', 'verifikator-1'],
        ];
    }

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
