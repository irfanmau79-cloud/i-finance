<?php

namespace App\Models;

use App\Support\BidangOrganisasi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu kegiatan pengawasan yang diusulkan kebutuhan anggarannya oleh sebuah
 * Irban. Port dari satu baris sheet "Data Kebutuhan Anggaran" (CodeKebutuhan.gs).
 */
#[Fillable([
    'tahun',
    'unit_kerja',
    'user_id',
    'dalam_pkpt',
    'nomor_pkpt',
    'area',
    'jenis_kegiatan',
    'keterangan',
    'tanggal_mulai',
    'tanggal_selesai',
    'tarif_uh_dalam',
    'tarif_uh_luar',
    'total_uh_dalam',
    'total_uh_luar',
    'total_akomodasi',
    'total_transport',
    'total_estimasi',
])]
class KebutuhanAnggaran extends Model
{
    protected $table = 'kebutuhan_anggaran';

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'dalam_pkpt' => 'boolean',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'total_uh_dalam' => 'decimal:2',
            'total_uh_luar' => 'decimal:2',
            'total_akomodasi' => 'decimal:2',
            'total_transport' => 'decimal:2',
            'total_estimasi' => 'decimal:2',
        ];
    }

    public function rincian(): HasMany
    {
        return $this->hasMany(KebutuhanAnggaranRincian::class)->orderBy('urutan');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeTahun(Builder $query, ?int $tahun = null): Builder
    {
        return $query->where('tahun', $tahun ?? (int) config('anggaran.tahun_aktif'));
    }

    public function unitSingkat(): string
    {
        return BidangOrganisasi::singkat($this->unit_kerja);
    }

    /**
     * Keterangan yang tampil di tabel rekap: kegiatan di luar PKPT memakai
     * keterangan yang ditulis Irban, kegiatan PKPT memakai "Area - Jenis".
     */
    public function keteranganTampil(): string
    {
        if (! $this->dalam_pkpt) {
            return (string) ($this->keterangan ?: '-');
        }

        $bagian = array_filter([$this->area, $this->jenis_kegiatan]);

        return $bagian === [] ? '-' : implode(' — ', $bagian);
    }

    public function rentangTanggal(): string
    {
        return $this->tanggal_mulai->format('Y-m-d').' s.d. '.$this->tanggal_selesai->format('Y-m-d');
    }
}
