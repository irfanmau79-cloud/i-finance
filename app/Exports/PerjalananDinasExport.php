<?php

namespace App\Exports;

use App\Exports\Concerns\PunyaPetunjukKolom;
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
class PerjalananDinasExport extends DataManagementExport implements PunyaPetunjukKolom
{
    public function petunjukCatatan(): string
    {
        return 'Laporan rincian perjalanan dinas: SATU BARIS PER ANGGOTA TIM, bukan per dokumen NPD. Nomor NPD yang sama muncul berulang sebanyak jumlah pesertanya, jadi jangan menjumlahkan kolom Diterima per baris untuk mendapat nilai dokumen. Berkas ini hasil ekspor untuk dibaca atau diarsipkan - mengunggahnya kembali tidak mengubah data.';
    }

    public function petunjukKolom(): array
    {
        return [
            ['Tanggal NPD', '-', 'Tanggal YYYY-MM-DD', 'Tanggal dokumen NPD perjalanan dinas.', '2026-07-15'],
            ['Nomor NPD', '-', 'Teks', 'Nomor lengkap NPD. Berulang untuk tiap anggota tim pada dokumen yang sama.', '010/NPD-PD/2026'],
            ['Sub Kegiatan', '-', 'Teks', 'Sub kegiatan yang dibebani, lengkap dengan kodenya.', '6.01.01.2.01 Penyusunan Dokumen Perencanaan'],
            ['Bidang', '-', 'Teks', 'Bidang asal anggota tim.', 'Irban Wilayah I'],
            ['Nama', '-', 'Teks', 'Nama anggota tim yang melakukan perjalanan.', 'Budi Santoso, S.E.'],
            ['Jabatan', '-', 'Teks', 'Jabatan anggota tim saat perjalanan.', 'Auditor Muda'],
            ['Jumlah Hari', '-', 'Angka', 'Lama perjalanan dalam hari, dasar perhitungan uang harian.', '3'],
            ['Uang Harian', '-', 'Angka', 'Total uang harian anggota tim ini.', '1350000'],
            ['Akomodasi', '-', 'Angka', 'Biaya penginapan anggota tim ini.', '900000'],
            ['Transport', '-', 'Angka', 'Biaya transportasi anggota tim ini.', '500000'],
            ['Representatif', '-', 'Angka', 'Uang representatif, hanya untuk pejabat yang berhak.', '0'],
            ['Diterima', '-', 'Angka', 'Total yang diterima anggota tim ini (uang harian + akomodasi + transport + representatif).', '2750000'],
        ];
    }

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
            $b['uh'],
            $b['akom'],
            $b['trans'],
            $b['representatif'],
            $b['terima'],
        ];
    }
}
