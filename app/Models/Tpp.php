<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris TPP seorang pegawai untuk satu bulan. Kolom `jenis` memisahkan
 * TPP Beban Kerja dari TPP Kondisi Kerja (TOL) - struktur berkas SIPD-nya
 * identik sehingga keduanya berbagi tabel.
 */
class Tpp extends Model
{
    protected $table = 'tpp';

    protected $guarded = [];

    public const JENIS_BEBAN = 'beban';

    public const JENIS_KONDISI = 'kondisi';

    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date'];
    }

    public function scopePeriode(EloquentBuilder $query, string $jenis, ?int $bulan, int $tahun): EloquentBuilder
    {
        return $query->where('jenis', $jenis)->where('tahun', $tahun)
            ->when($bulan !== null, fn ($q) => $q->where('bulan', $bulan));
    }
}
