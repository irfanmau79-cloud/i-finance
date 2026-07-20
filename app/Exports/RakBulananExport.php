<?php

namespace App\Exports;

use App\Models\MasterAnggaran;
use Illuminate\Database\Eloquent\Builder;

/**
 * Template RAK Bulanan: satu baris per mata anggaran AKTIF, kolom
 * Januari..Desember berisi target BULANAN (bukan kumulatif - lihat
 * dokumentasi di migration create_rak_bulanan_table). Sel bulan yang belum
 * punya RAK dibiarkan KOSONG (null) - tidak pernah diisi pagu/12 - supaya
 * kekosongan tetap terlihat jelas sebagai "RAK belum tersedia" saat file
 * ini dibuka maupun saat diupload kembali sebagai template import.
 */
class RakBulananExport extends DataManagementExport
{
    private const BULAN_LABEL = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __construct(private readonly int $tahun) {}

    public function query(): Builder
    {
        return MasterAnggaran::query()
            ->with(['tagging', 'rakBulanan' => fn ($q) => $q->where('tahun', $this->tahun)])
            ->where('aktif', true)
            ->orderBy('sub_kegiatan')
            ->orderBy('kode_rekening');
    }

    public function headings(): array
    {
        return array_merge(
            ['Sub Kegiatan', 'Kode Rekening', 'Uraian Rekening', 'Tagging', 'Pagu'],
            array_values(self::BULAN_LABEL)
        );
    }

    public function map($row): array
    {
        $perBulan = $row->rakBulanan->keyBy('bulan');

        $kolomBulan = [];

        foreach (self::BULAN_LABEL as $bulan => $label) {
            $kolomBulan[] = $perBulan->has($bulan) ? (float) $perBulan[$bulan]->target : null;
        }

        return array_merge([
            $row->sub_kegiatan,
            $row->kode_rekening,
            $row->uraian_rekening,
            $row->tagging?->nama,
            (float) $row->pagu,
        ], $kolomBulan);
    }
}
