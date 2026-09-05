<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris rincian estimasi (per jenis anggota) pada sebuah kegiatan.
 *
 * Rumus yang berlaku - sama dengan formulirnya:
 *   jumlah_uh_dalam   = hari_dalam   x tarif_uh_dalam
 *   jumlah_uh_luar    = hari_luar    x tarif_uh_luar
 *   total_akomodasi   = jumlah_malam x tarif_akomodasi
 *   estimasi_kebutuhan = ketiganya dijumlah
 *
 * Transport TIDAK ada di sini: sejak perubahan #62 di GAS, transport dihitung
 * sekali untuk seluruh kegiatan, bukan per rincian.
 */
#[Fillable([
    'kebutuhan_anggaran_id',
    'urutan',
    'jenis_anggota',
    'jumlah_orang',
    'hari_dalam',
    'tarif_uh_dalam',
    'jumlah_uh_dalam',
    'hari_luar',
    'tarif_uh_luar',
    'jumlah_uh_luar',
    'jumlah_malam',
    'tarif_akomodasi',
    'total_akomodasi',
    'estimasi_kebutuhan',
])]
class KebutuhanAnggaranRincian extends Model
{
    protected $table = 'kebutuhan_anggaran_rincian';

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'jumlah_orang' => 'integer',
            'hari_dalam' => 'integer',
            'hari_luar' => 'integer',
            'jumlah_malam' => 'integer',
            'tarif_uh_dalam' => 'decimal:2',
            'jumlah_uh_dalam' => 'decimal:2',
            'tarif_uh_luar' => 'decimal:2',
            'jumlah_uh_luar' => 'decimal:2',
            'tarif_akomodasi' => 'decimal:2',
            'total_akomodasi' => 'decimal:2',
            'estimasi_kebutuhan' => 'decimal:2',
        ];
    }

    public function kebutuhan(): BelongsTo
    {
        return $this->belongsTo(KebutuhanAnggaran::class, 'kebutuhan_anggaran_id');
    }
}
