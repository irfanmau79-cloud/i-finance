<?php

namespace App\Exports;

use App\Exports\Concerns\PunyaPetunjukKolom;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Builder;

/**
 * Snapshot data Tunjangan Keluarga seluruh pegawai yang BERHAK (PNS & PPPK
 * Penuh Waktu), bukan hanya yang sudah punya data - baris kosong untuk
 * pegawai yang belum mengisi data pasangan/anak. Bentuk kolom sama persis dengan
 * TunjanganKeluargaTemplateExport supaya bisa diedit lalu diupload ulang
 * lewat Import Data Tunjangan Keluarga.
 */
class TunjanganKeluargaExport extends DataManagementExport implements PunyaPetunjukKolom
{
    public function petunjukCatatan(): string
    {
        return TunjanganKeluargaTemplateExport::CATATAN;
    }

    public function petunjukKolom(): array
    {
        return TunjanganKeluargaTemplateExport::PETUNJUK;
    }

    public function query(): Builder
    {
        // Hanya pegawai yang BERHAK tunjangan keluarga (PNS & PPPK Penuh
        // Waktu) - sama persis dengan daftar di halaman Data Tunjangan
        // Keluarga, supaya berkas ini bisa diedit lalu diunggah kembali
        // tanpa selisih jumlah baris.
        return Pegawai::query()
            ->berhakTunjangan()
            ->with(['tunjanganKeluarga.anggota'])
            ->orderBy('nama');
    }

    public function headings(): array
    {
        return TunjanganKeluargaTemplateExport::HEADERS;
    }

    /** @param  Pegawai  $row */
    public function map($row): array
    {
        $anggota = $row->tunjanganKeluarga?->anggota ?? collect();
        $pasangan = $anggota->firstWhere('hubungan', 'pasangan');
        $anak = $anggota->where('hubungan', 'anak')->values();
        $anak1 = $anak->get(0);
        $anak2 = $anak->get(1);

        return [
            $row->nama,
            $row->nip,
            $pasangan?->nama,
            optional($pasangan?->tanggal_lahir)->format('Y-m-d'),
            $pasangan ? ($pasangan->status_tunjangan ? 'Aktif' : 'Tidak Aktif') : null,
            $anak1?->nama,
            optional($anak1?->tanggal_lahir)->format('Y-m-d'),
            $anak1 ? ($anak1->status_tunjangan ? 'Aktif' : 'Tidak Aktif') : null,
            $anak1?->keterangan,
            $anak2?->nama,
            optional($anak2?->tanggal_lahir)->format('Y-m-d'),
            $anak2 ? ($anak2->status_tunjangan ? 'Aktif' : 'Tidak Aktif') : null,
            $anak2?->keterangan,
        ];
    }
}
