<?php

namespace App\Models;

use App\Support\GajiTunjanganKolom;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'nomor_urut', 'nomor', 'nip', 'nama', 'jabatan', 'tahun', 'periode',
    'ada_pd', 'nominal_pd', 'total_pd',
    'penandatangan_kunci', 'penandatangan_nama', 'penandatangan_jabatan', 'penandatangan_pangkat',
    'tanggal_dokumen', 'dibuat_oleh', 'dibuat_oleh_nama',
])]
class RincianPenghasilan extends Model
{
    protected $table = 'rincian_penghasilan';

    protected function casts(): array
    {
        return [
            'periode' => 'array',
            'nominal_pd' => 'array',
            'ada_pd' => 'boolean',
            'tanggal_dokumen' => 'date',
        ];
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** "Januari, Februari 2026" - sama seperti kolom periode di sheet GAS. */
    public function labelPeriode(): string
    {
        $nama = array_map(
            fn (int $bulan) => GajiTunjanganKolom::NAMA_BULAN[$bulan] ?? '?',
            $this->periode ?? []
        );

        return implode(', ', $nama).' '.$this->tahun;
    }
}
