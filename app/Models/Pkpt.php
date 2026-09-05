<?php

namespace App\Models;

use App\Support\BidangOrganisasi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris Program Kerja Pengawasan Tahunan. Padanan satu baris sheet
 * "Monitoring PKPT" di GAS - lihat migrasi create_pkpt_tables untuk asal
 * tiap kolom.
 *
 * Tabel ini MURNI DIBACA oleh aplikasi: isinya datang dari Manajemen Data >
 * Data PKPT, dan dipakai Monitoring PKPT serta modul Estimasi Kebutuhan
 * (yang menawarkan kegiatan PKPT belum terlaksana milik unit ybs).
 */
#[Fillable([
    'tahun',
    'nomor',
    'unit_kerja',
    'area',
    'jenis_kegiatan',
    'tujuan',
    'ruang_lingkup',
    'jumlah_tim',
    'estimasi_anggaran',
    'realisasi',
    'pelaksanaan',
    'jumlah_laporan',
    'rencana_pelaksanaan',
    'terlaksana',
])]
class Pkpt extends Model
{
    protected $table = 'pkpt';

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'estimasi_anggaran' => 'decimal:2',
            'realisasi' => 'decimal:2',
            'terlaksana' => 'boolean',
        ];
    }

    public function scopeTahun(Builder $query, ?int $tahun = null): Builder
    {
        return $query->where('tahun', $tahun ?? (int) config('anggaran.tahun_aktif'));
    }

    /** "Inspektur Pembantu III" -> "Irban III"; dipakai kolom tabel & label chart. */
    public function unitSingkat(): string
    {
        return BidangOrganisasi::singkat($this->unit_kerja);
    }
}
