<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris Gaji Induk seorang pegawai untuk satu bulan. Isinya berkas SIPD
 * apa adanya - lihat App\Support\GajiTunjanganKolom untuk peta kolomnya.
 *
 * Tidak ada $fillable: pengisian selalu lewat GajiTunjanganImportService yang
 * membangun payload dari peta kolom, bukan dari request pengguna.
 */
class GajiInduk extends Model
{
    protected $table = 'gaji_induk';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date'];
    }

    public function scopePeriode(EloquentBuilder $query, ?int $bulan, int $tahun): EloquentBuilder
    {
        return $query->where('tahun', $tahun)->when($bulan !== null, fn ($q) => $q->where('bulan', $bulan));
    }
}
