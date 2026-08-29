<?php

namespace App\Exports;

use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\Npd;
use Illuminate\Database\Eloquent\Builder;

class NpdExport extends DataManagementExport implements PunyaPetunjukKolom
{
    public function petunjukCatatan(): string
    {
        return 'Laporan seluruh Nota Pencairan Dana. Berkas ini adalah HASIL EKSPOR untuk dibaca atau diarsipkan - mengunggahnya kembali tidak akan mengubah data NPD. Untuk memasukkan NPD lama, pakai menu Import Excel pada kartu ini yang memakai templatenya sendiri. Nominal NPD selalu berupa nilai BRUTO.';
    }

    public function petunjukKolom(): array
    {
        return [
            ['Nomor NPD', '-', 'Teks', 'Nomor lengkap NPD hasil penomoran otomatis sistem.', '01/NPD-Keu.1.IBC/7/2026'],
            ['Jenis', '-', 'Teks', 'Jenis NPD: Barang/Jasa, Perjalanan Dinas, Transport, Narasumber, atau Kontribusi Diklat.', 'Barang/Jasa'],
            ['Tanggal NPD', '-', 'Tanggal YYYY-MM-DD', 'Tanggal dokumen NPD.', '2026-07-20'],
            ['Kode Sub Kegiatan', '-', 'Teks', 'Kode sub kegiatan mata anggaran yang dibebani.', '6.01.01.2.01'],
            ['Sub Kegiatan', '-', 'Teks', 'Nama sub kegiatan tanpa kodenya.', 'Penyusunan Dokumen Perencanaan Perangkat Daerah'],
            ['Kode Rekening', '-', 'Teks', 'Kode rekening belanja yang dibebani.', '5.1.02.01.01.0024'],
            ['Rekening', '-', 'Teks', 'Uraian rekening tanpa kodenya.', 'Belanja Alat Tulis Kantor'],
            ['Tagging', '-', 'Teks', 'Tagging mata anggaran saat NPD dibuat. Kosong berarti mata anggarannya memang tanpa tagging.', 'Rutin'],
            ['Jenis Panjar', '-', 'Teks', 'Skema panjar yang dipakai dokumen ini.', 'Tanpa Panjar'],
            ['Nominal', '-', 'Angka', 'Nilai BRUTO NPD. Inilah angka yang mengikat pagu dan menjadi realisasi ketika status Selesai.', '1500000'],
            ['Terbilang', '-', 'Teks', 'Nominal dalam huruf, seperti tercetak di dokumen.', 'satu juta lima ratus ribu rupiah'],
            ['Penerima', '-', 'Teks', 'Nama penerima. Bila lebih dari satu, dipisahkan koma.', 'Budi Santoso, Siti Aminah'],
            ['Status', '-', 'Teks', 'Posisi dokumen dalam alur persetujuan. Hanya status Selesai yang dihitung sebagai realisasi; status mengandung kata Batal tidak mengikat pagu.', 'Selesai'],
            ['Catatan', '-', 'Teks', 'Catatan terakhir pada alur dokumen, mis. alasan dikembalikan.', 'Kelengkapan berkas kurang'],
            ['Dibuat Oleh', '-', 'Teks', 'Username pembuat dokumen.', 'pptk-irban1'],
            ['Dibuat Pada', '-', 'Tanggal jam', 'Waktu dokumen dibuat.', '2026-07-20 09:15'],
        ];
    }

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
            'Nomor NPD', 'Jenis', 'Tanggal NPD',
            'Kode Sub Kegiatan', 'Sub Kegiatan', 'Kode Rekening', 'Rekening',
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
            $row->masterAnggaran?->kode_sub_kegiatan,
            $row->masterAnggaran?->sub_kegiatan,
            $row->masterAnggaran?->kode_rekening,
            $row->masterAnggaran?->rekening,
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
