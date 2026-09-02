<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header Simulasi Realisasi - lihat migrasi 2026_09_02_090000 untuk konsepnya.
 */
#[Fillable(['nama', 'keterangan', 'user_id', 'total_pagu', 'total_proyeksi'])]
class SimulasiRealisasi extends Model
{
    protected $table = 'simulasi_realisasi';

    protected function casts(): array
    {
        return [
            'total_pagu' => 'decimal:2',
            'total_proyeksi' => 'decimal:2',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SimulasiRealisasiRow::class)
            ->orderBy('sub_kegiatan')
            ->orderBy('kode_rekening');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
